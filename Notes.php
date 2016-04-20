<?php

/**
 * Get bookmarks from MySQL. Die directly if can not fetch notes.
 */
function get_notes($conn, $user) {
  $sql = "SELECT * FROM `ybk_notes` WHERE user = '$user'";
  $notes = mysqli_query($conn, $sql);

  if (!$notes) {
    http_response_code(400);
    die('Could not get data: ' . mysqli_error($conn));
  }
  return json_encode(mysqli_fetch_all($notes, MYSQLI_ASSOC));
}

/**
 * Hard code editing-supported columns and check whether it matches.
 */
function support_edit($type) {
  return $type === 'context' || $type === 'review' || $type === 'mark';
}

/**
 * Insert or update notes for a bookmark, where `$type` is a field of notes.
 * Return true or false indicating success or not.
 */
function edit_notes($conn, $bookmarkId, $user, $type, $content) {
  if (!support_edit($type)) {
    return False;
  }
  // Escape.
  $content = str_replace('"', '\'', $content);

  $sql = <<<EOD
INSERT INTO `ybk_notes`
  (bookmark_id, user, $type) VALUES ("$bookmarkId", "$user", "$content")
ON DUPLICATE KEY UPDATE
  $type = "$content"
EOD;

  // True or false.
  return mysqli_query($conn, $sql);
}

?>
