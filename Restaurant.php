<?hh

require_once ('lib/OAuth.php');

function config(): void {
  $config = json_decode(file_get_contents("config.json"), true);
  $GLOBALS['BUSINESS_PATH'] = $config['businessPath'];
  $GLOBALS['API_HOST'] = $config['apiHost'];
  $GLOBALS['TOKEN'] = $config['token'];
  $GLOBALS['TOKEN_SECRET'] = $config['tokenSecret'];
  $GLOBALS['CONSUMER_KEY'] = $config['consumerKey'];
  $GLOBALS['CONSUMER_SECRET'] = $config['consumerSecret'];
  $GLOBALS['SLACK_URL'] = $config['slackUrl'];
  $GLOBALS['AUTH_COOKIE'] = $config['authCookie'];
  $GLOBALS['MYSQL_PASSWORD'] = $config['mysqlPassword'];
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
