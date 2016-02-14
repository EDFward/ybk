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

$BOOKMARK_FILE_PATH = 'data/bookmarks.json';

function compare_and_notify_slack(array $newBookmarks): void {
  $oldBookmarks =
    json_decode(file_get_contents($GLOBALS['BOOKMARK_FILE_PATH']), true);
  // Bookmark map keyed on restaurant IDs.
  $oldBookmarkMap = Map {};
  $newBookmarkMap = Map {};

  $oldBookmarkMap->addAll(
    array_map($b ==> Pair {$b['id'], $b}, $oldBookmarks),
  );
  $newBookmarkMap->addAll(
    array_map($b ==> Pair {$b['id'], $b}, $newBookmarks),
  );

  // Start building slack reports.
  $report = "Booksmarks updated. Total number: {$newBookmarkMap->count()}";

  $added = $newBookmarkMap->differenceByKey($oldBookmarkMap);
  if (!$added->isEmpty()) {
    $addedRestaurants =
      $added->values()->map($b ==> "<{$b['url']}|{$b['name']}>");
    $info = "Added {$added->count()} restaurant(s): ";
    $info .= implode(", ", $addedRestaurants);
    $report .= "\n".$info;
  }

  $deleted = $oldBookmarkMap->differenceByKey($newBookmarkMap);
  if (!$deleted->isEmpty()) {
    $deletedRestaurants =
      $deleted->values()->map($b ==> "<{$b['url']}|{$b['name']}>");
    $info = "Deleted {$deleted->count()} restaurant(s): ";
    $info .= implode(", ", $deletedRestaurants);
    $report .= "\n".$info;
  }

  $payload = array('text' => $report);

  $ch = curl_init();

  curl_setopt($ch, CURLOPT_URL, $GLOBALS['SLACK_URL']);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

  curl_exec($ch);
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
  $b = $businessInfo['location']['coordinate'];
  $b['id'] = $businessInfo['id'];
  $b['name'] = $businessInfo['name'];
  $b['image_url'] = $businessInfo['image_url'];
  $b['url'] = $businessInfo['url'];
  $b['rating'] = $businessInfo['rating'];
  $b['categories'] = array_map($p ==> $p[0], $businessInfo['categories']);

  array_push($bookmarks, $b);
}

// First compare with old bookmarks.
compare_and_notify_slack($bookmarks);
// Then write the new bookmarks to disk.
file_put_contents($BOOKMARK_FILE_PATH, json_encode($bookmarks));
