<?php
// process a grant

// try to fetch the grant
$id = $_REQUEST["g"];
if(!isGrantId($id))
{
  $id = false;
  $GRANT = false;
}
else
{
  $sql = "SELECT * FROM grant WHERE id = " . $db->quote($id);
  $GRANT = $db->query($sql)->fetch();
}

$ref = "$masterPath?g=$id";
if($GRANT === false || isGrantExpired($GRANT))
{
  includeTemplate("style/include/nogrant.php", array('id' => $id));
  exit();
}

if(isset($GRANT['pass_md5']) && !isset($_SESSION['g'][$id]))
{
  $pass = (empty($_POST["p"])? false: md5($_POST["p"]));
  if($pass === $GRANT['pass_md5'])
  {
    // authorize the grant for this session
    $_SESSION['g'][$id] = array('pass' => $_POST["p"]);
  }
  else
  {
    include("grantp.php");
    exit();
  }
}


// upload handler
function failUpload($file)
{
  unlink($file);
  return false;
}

function handleUpload($GRANT, $FILE)
{
  global $dataDir, $db;

  // generate new unique id/file name
  list($id, $tmpFile) = genTicketId($FILE["name"]);
  if(!move_uploaded_file($FILE["tmp_name"], $tmpFile))
    return failUpload($tmpFile);

  // convert the upload to a ticket
  $db->beginTransaction();

  $sql = "INSERT INTO ticket (id, user_id, name, path, size, cmt, pass_md5"
    . ", time, last_time, expire, expire_dln) VALUES (";
  $sql .= $db->quote($id);
  $sql .= ", " . $GRANT['user_id'];
  $sql .= ", " . $db->quote(basename($FILE["name"]));
  $sql .= ", " . $db->quote($tmpFile);
  $sql .= ", " . $FILE["size"];
  $sql .= ", " . (empty($GRANT["cmt"])? 'NULL': $db->quote($GRANT["cmt"]));
  $sql .= ", " . (empty($GRANT["pass_md5"])? 'NULL': $db->quote($GRANT["pass_md5"]));
  $sql .= ", " . time();
  $sql .= ", " . (empty($GRANT["last_time"])? 'NULL': $GRANT['last_time']);
  $sql .= ", " . (empty($GRANT["expire"])? 'NULL': $GRANT['expire']);
  $sql .= ", " . (empty($GRANT["expire_dln"])? 'NULL': $GRANT['expire_dln']);
  $sql .= ")";
  $db->exec($sql);

  $sql = "DELETE FROM grant WHERE id = " . $db->quote($GRANT['id']);
  $db->exec($sql);

  if(!$db->commit())
    return failUpload($tmpFile);

  // fetch defaults
  $sql = "SELECT * FROM ticket WHERE id = " . $db->quote($id);
  $DATA = $db->query($sql)->fetch();
  if(!empty($GRANT['pass'])) $DATA['pass'] = $GRANT['pass'];

  // trigger use hooks
  withDefLocale('onGrantUse', array($GRANT, $DATA));

  return $DATA;
}


// handle the request
$DATA = false;
if(isset($_FILES["file"])
&& is_uploaded_file($_FILES["file"]["tmp_name"])
&& $_FILES["file"]["error"] == UPLOAD_ERR_OK)
{
  if(!empty($_SESSION['g'][$id]['pass']))
    $GRANT['pass'] = $_SESSION['g'][$id]['pass'];
  $DATA = handleUpload($GRANT, $_FILES["file"]);
}

// resulting page
if($DATA === false)
  include("grants.php");
else
{
  unset($ref);
  includeTemplate("style/include/grantr.php");

  // kill the session ASAP
  if($auth === false)
    session_destroy();
}

?>
