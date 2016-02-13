<?hh

// A utility script to fetch bookmarked restaurants and their coordinates.

require_once ('lib/OAuth.php');

// Setup Yelp API info.
$CONSUMER_KEY = $_ENV['CONSUMER_KEY'];
$CONSUMER_SECRET = $_ENV['CONSUMER_SECRET'];
$TOKEN = $_ENV['TOKEN'];
$TOKEN_SECRET = $_ENV['TOKEN_SECRET'];
$API_HOST = 'api.yelp.com';
$BUSINESS_PATH = '/v2/business/';

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

async function get_business(string $businessID): Awaitable<mixed> {
  $businessPath = $GLOBALS['BUSINESS_PATH'].$businessID;
  $rawURL = "https://".$GLOBALS['API_HOST'].$businessPath;
  $token = new OAuthToken($GLOBALS['TOKEN'], $GLOBALS['TOKEN_SECRET']);
  $consumer =
    new OAuthConsumer($GLOBALS['CONSUMER_KEY'], $GLOBALS['CONSUMER_SECRET']);

  $signatureMethod = new OAuthSignatureMethod_HMAC_SHA1();

  $oauthrequest =
    OAuthRequest::from_consumer_and_token($consumer, $token, 'GET', $rawURL);
  $oauthrequest->sign_request($signatureMethod, $consumer, $token);
  $signedURL = $oauthrequest->to_url();

  $data = await \HH\Asio\curl_exec($signedURL);
  // Could return null if curl returned empty string.
  return json_decode($data, true);
}

// Output array: a list of JSON objects containing restaurant ID and
// corresponding coordinates.
$coordinates = [];
$restaurants = fetch_restaurants();

// Fetch business info concurrently.
$infoList = \HH\Asio\join(
  \HH\Asio\v($restaurants->toVector()->map($r ==> get_business($r))),
);

foreach ($infoList as $businessInfo) {
  if (is_null($businessInfo)) {
    continue;
  }

  $c = $businessInfo['location']['coordinate'];
  $c['name'] = $businessInfo['id'];
  array_push($coordinates, $c);
}

file_put_contents('data/coordinates.json', json_encode($coordinates));
