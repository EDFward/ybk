<?hh

// Import `config()`.
require_once ('Restaurant.php');

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
$ALLOED_DISTANCE = 200.0;

function get_nearest_restaurants(float $lat, float $lon): void {
  $bookmarks = json_decode(file_get_contents("data/bookmarks.json"), true);
  $nearest = [];

  foreach ($bookmarks as $bookmark) {
    $dist = calculate_distance(
      $bookmark['latitude'],
      $bookmark['longitude'],
      $lat,
      $lon,
    );
    if ($dist < $GLOBALS['ALLOED_DISTANCE']) {
      array_push($nearest, Pair {$bookmark, intval($dist)});
    }
  }

  send_to_slack($nearest);
}

function send_to_slack(array $nearest): void {
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

// Setup API-related configurations.
config();

if (isset($_GET['lat']) &&
    preg_match('/^-?\d+\.\d+$/', $_GET['lat']) &&
    isset($_GET['lon']) &&
    preg_match('/^-?\d+\.\d+$/', $_GET['lon'])) {
  list($lat, $lon) = array(floatval($_GET['lat']), floatval($_GET['lon']));
  get_nearest_restaurants($lat, $lon);
} else {
  echo 'Request not recognized';
}
