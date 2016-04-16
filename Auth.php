<?hh

/* Dead simple authentication. I'm lazy. */

$code = $_GET['code'];

if (isset($code)) {
  $config = json_decode(file_get_contents("config.json"), true);
  if ($code === $config['authCode']) {
    // Two hour cookies.
    setcookie("ybkAuthCode", $config["authCookie"], time() + 3600 * 2, '/');
  }
}
