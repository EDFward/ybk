<?hh

// A utility script to fetch bookmarked restaurants and their coordinates.

// Import `config()` and `get_business()`.
require_once ('Restaurant.php');

function fetch_restaurants(string $userID = 'XzkPRDkpb5WH1KuNDkYGuA'): Set {
  $bookmarkHTML = file_get_contents(
    "http://www.yelp.com/user_details_bookmarks?userid=".$userID,
  );
  $dom = new DOMDocument();
  libxml_use_internal_errors(true);
  $dom->loadHTML($bookmarkHTML);
  $xpath = new DOMXPath($dom);
  $anchors = $xpath->query(
    "//div[@class='arrange']//a[starts-with(@href, '/biz')]/@href",
  );
  $restaurantNames = Set {};

  foreach ($anchors as $a) {
    if (isset($a->value)) {
      // Trim the prefix '/biz/'.
      $name = substr($a->value, 5);
      $restaurantNames->add($name);
    }
  }
  return $restaurantNames;
}

// Setup API-related configurations.
config();

// Output array: a list of JSON objects containing restaurant information.
$bookmarks = [];
$restaurants = fetch_restaurants();

// Fetch business info concurrently.
$infoList = \HH\Asio\join(
  \HH\Asio\v($restaurants->toVector()->map($r ==> get_business($r))),
);

foreach ($infoList as $businessInfo) {
  if (is_null($businessInfo)) {
    continue;
  }

  // Only read necessary information.
  $bookmark = $businessInfo['location']['coordinate'];
  $bookmark['id'] = $businessInfo['id'];
  $bookmark['name'] = $businessInfo['name'];
  $bookmark['image_url'] = $businessInfo['image_url'];
  $bookmark['url'] = $businessInfo['url'];
  $bookmark['rating'] = $businessInfo['rating'];
  $bookmark['categories'] =
    array_map($p ==> $p[0], $businessInfo['categories']);

  array_push($bookmarks, $bookmark);
}

file_put_contents('data/bookmarks.json', json_encode($bookmarks));
