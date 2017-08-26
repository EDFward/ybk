<?hh

// A utility script to fetch bookmarked restaurants and their coordinates.

// Import `config()` and `get_business()`.
require_once ('Restaurant.php');

function fetch_restaurants(string $userID = 'XzkPRDkpb5WH1KuNDkYGuA'): Set {
  $restaurantNames = Set {};

  // Loop ended when no more restaurants can be fetched.
  while (true) {
    $cnt = $restaurantNames->count();
    $bookmarkHTML = file_get_contents(
      "http://www.yelp.com/user_details_bookmarks?userid={$userID}&start={$cnt}",
    );
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($bookmarkHTML);
    $xpath = new DOMXPath($dom);
    $anchors = $xpath->query(
      "//div[@class='arrange']//a[starts-with(@href, '/biz')]/@href",
    );

    $updated = false;
    foreach ($anchors as $a) {
      if (isset($a->value)) {
        // Trim the prefix '/biz/'.
        $name = substr($a->value, 5);
        $restaurantNames->add($name);
        $updated = true;
      }
    }
    // Exit if no new restaurant added.
    if (!$updated) {
      break;
    }

    sleep(5);  // Avoid blacklisting.
  }
  return $restaurantNames;
}

function send_text_to_slack(string $text) {
  $payload = array('text' => $text);
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $GLOBALS['SLACK_URL']);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
  curl_exec($ch);
}

$BOOKMARK_FILE_PATH = 'data/bookmarks.json';

/**
  * Compare fetched bookmarks with existing ones. In case fetching fails,
  * return false and notify slack the situation.
  */
function compare_and_notify_slack(array $newBookmarks): bool {
  $oldBookmarks =
    json_decode(file_get_contents($GLOBALS['BOOKMARK_FILE_PATH']), true);
  $oldCount = count($oldBookmarks);
  $newCount = count($newBookmarks);

  if (abs($oldCount - $newCount) / $oldCount > 0.5) {
    // Size changed too much, regard fetched results as failures.
    $failureReport = "Bookmark count changed too much. Stopped.\n".
                     "current: {$oldCount}, fetched: {$newCount}";
    send_text_to_slack($failureReport);
    return false;
  }

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

  send_text_to_slack($report);
  return true;
}

/****
 **** Execution entry point - main.
 ****/

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
$fetchOk = compare_and_notify_slack($bookmarks);
// Then write the new bookmarks to disk if fetching is OK.
if ($fetchOk) {
  file_put_contents($BOOKMARK_FILE_PATH, json_encode($bookmarks));
}
