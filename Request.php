<?hh

// Import `config()`.
require_once ('Restaurant.php');
// Import DB operations for bookmark notes.
require_once ('Notes.php');

// Following code snippet is copied from
// http://stackoverflow.com/questions/10053358/measuring-the-distance-between-two-coordinates-in-php

/**
 * Calculates the great-circle distance between two points, with
 * the Vincenty formula.
 * @param float $latitudeFrom Latitude of start point in [deg decimal]
 * @param float $longitudeFrom Longitude of start point in [deg decimal]
 * @param float $latitudeTo Latitude of target point in [deg decimal]
 * @param float $longitudeTo Longitude of target point in [deg decimal]
 * @param float $earthRadius Mean earth radius in [m]
 * @return float Distance between points in [m] (same as earthRadius)
 */
function calculate_distance(
  $latitudeFrom,
  $longitudeFrom,
  $latitudeTo,
  $longitudeTo,
  $earthRadius = 6371000,
) {
  $latFrom = deg2rad($latitudeFrom);
  $lonFrom = deg2rad($longitudeFrom);
  $latTo = deg2rad($latitudeTo);
  $lonTo = deg2rad($longitudeTo);

  $lonDelta = $lonTo - $lonFrom;
  $a =
    pow(cos($latTo) * sin($lonDelta), 2) +
    pow(
      cos($latFrom) * sin($latTo) -
      sin($latFrom) * cos($latTo) * cos($lonDelta),
      2,
    );
  $b =
    sin($latFrom) * sin($latTo) +
    cos($latFrom) * cos($latTo) * cos($lonDelta);

  $angle = atan2(sqrt($a), $b);
  return $angle * $earthRadius;
}

// Maximum distance allowed from the restaurant to current position.
$ALLOWED_DISTANCE = 200.0;

function get_nearest_restaurants(float $lat, float $lon): void {
  // Get previously notified restaurants.
  $previousFilePath = 'data/previous.json';
  if (file_exists($previousFilePath)) {
    $previous =
      new Vector(json_decode(file_get_contents($previousFilePath), true));
  } else {
    $previous = new Vector(null);
  }
  // Filter notifications within 10 mins.
  $now = time();
  $previous =
    $previous->filter($n ==> round(abs($n['time'] - $now) / 60, 1) <= 10.0);
  // Get restaurant IDs appeared before.
  $appearedRestaurants = Set {};
  foreach ($previous as $n) {
    foreach ($n['ids'] as $id) {
      $appearedRestaurants->add($id);
    }
  }

  $nearest = [];

  $bookmarks = json_decode(file_get_contents('data/bookmarks.json'), true);

  foreach ($bookmarks as $b) {
    if ($appearedRestaurants->contains($b['id'])) {
      // Ignore already notified ones.
      continue;
    }

    $dist = calculate_distance($b['latitude'], $b['longitude'], $lat, $lon);
    if ($dist < $GLOBALS['ALLOWED_DISTANCE']) {
      array_push($nearest, Pair {$b, intval($dist)});
    }
  }

  send_restaurant_to_slack($nearest);

  // Save this time's restaurant notification into `previous.json`.
  if (count($nearest) > 0) {
    $previous->add(
      array(
        'time' => $now,
        'ids' => array_map($ele ==> $ele[0]['id'], $nearest),
      ),
    );
    file_put_contents($previousFilePath, json_encode($previous));
  }
}

function send_restaurant_to_slack(array $nearest): void {
  if (count($nearest) == 0) {
    return;
  }

  $attachments = array_map(
    $p ==> array(
      'fallback' => "<{$p[0]['url']}|{$p[0]['name']}>",
      'title' => $p[0]['name'],
      'title_link' => $p[0]['url'],
      'thumb_url' => $p[0]['image_url'],
      'text' => "Only {$p[1]} meters away!",
      'color' => '#D00000',
      'fields' => [
        array(
          'title' => 'Rating',
          'value' => "{$p[0]['rating']}",
          'short' => true,
        ),
        array(
          'title' => 'Categories',
          'value' => implode(', ', $p[0]['categories']),
          'short' => true,
        ),
      ],
    ),
    $nearest,
  );

  $payload = array('attachments' => $attachments);
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $GLOBALS['SLACK_URL']);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
  curl_exec($ch);
}

function echo_bookmarks(): void {
  header('Content-type: application/json');
  echo file_get_contents('data/bookmarks.json');
}

function prepare_mysql() {
  $conn = mysqli_connect(
    'localhost', 'edfward', $GLOBALS['MYSQL_PASSWORD'], 'ybk');
  if (!$conn) {
    http_response_code(400);
    die('Could not connect: ' . mysqli_connect_error());
  }
  return $conn;
}

function echo_notes(): void {
  $conn = prepare_mysql();
  // TODO: hard code my user name.
  $notes = get_notes($conn, 'edfward');
  mysqli_close($conn);
  header('Content-type: application/json');
  echo $notes;
}

// Setup API-related configurations.
config();

// Allow cross-origin requests.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');


if (isset($_GET['lat']) &&
    preg_match('/^-?\d+\.\d+$/', $_GET['lat']) &&
    isset($_GET['lon']) &&
    preg_match('/^-?\d+\.\d+$/', $_GET['lon'])) {
  list($lat, $lon) = array(floatval($_GET['lat']), floatval($_GET['lon']));
  get_nearest_restaurants($lat, $lon);
} else if (isset($_GET['json'])) {
  // Return bookmarks (JSON array) or notes (JSON object as a dictionary).
  $neededResource = $_GET['json'];
  switch ($neededResource) {
    case 'bookmarks':
      echo_bookmarks();
      break;
    case 'notes':
      echo_notes();
      break;
    default:
      http_response_code(400);
  }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Dead simple authorization.
  if (!isset($_COOKIE['ybkAuthCode']) ||
      $_COOKIE['ybkAuthCode'] !== $AUTH_COOKIE) {
    http_response_code(400);
    die('Need authorization');
  }

  if (!isset($_POST['id']) || !isset($_POST['type']) ||
      !isset($_POST['content'])) {
    http_response_code(400);
    die('Wrong format');
  }
  // TODO: hard code my user name.
  $user = 'edfward';
  $conn = prepare_mysql();
  $res = edit_notes(
    $conn, $_POST['id'], $user, $_POST['type'], $_POST['content']);

  if (!$res) {
    // Update failed.
    http_response_code(400);
  }
} else {
  http_response_code(400);
  die('Request not recognized');
}
