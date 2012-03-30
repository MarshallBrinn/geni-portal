<?php
// Form for creating a slice. Submit to self.
?>

<?php
require_once("settings.php");
require_once("db-util.php");
require_once("util.php");
require_once("user.php");
?>

<?php
$user = geni_loadUser();
$name = NULL;
$message = NULL;
if (count($_GET)) {
  // parse the args
  /* print "got parameters<br/>"; */
  if (array_key_exists('name', $_GET)) {
    /* print "found name<br/>"; */
    $name = $_GET['name'];
  }
  /* print "got name = $name<br/>"; */

} else {
  /* print "no parameters in _GET<br/>"; */
}

function omni_create_slice($user, $slice_id, $name)
{
    /* Write key and credential files */
    $row = db_fetch_inside_private_key_cert($user->account_id);
    $cert = $row['certificate'];
    $private_key = $row['private_key'];
    $cert_file = '/tmp/' . $user->username . "-cert.pem";
    $key_file = '/tmp/' . $user->username . "-key.pem";	
    $omni_file = '/tmp/' . $user->username . "-omni.ini";
    file_put_contents($cert_file, $cert);
    file_put_contents($key_file, $private_key);

    /* Create OMNI config file */
    $omni_config = "[omni]\n"
    . "default_cf = my_gcf\n"
    . "[my_gcf]\n"
    . "type=gcf\n"
    . "authority=geni:gpo:portal\n"
    . "ch=https://localhost:8000\n"
    . "cert=" . $cert_file . "\n"
    . "key=" . $key_file;
    file_put_contents($omni_file, $omni_config);

    /* Call OMNI */
    global $portal_gcf_dir;
    $cmd_array = array($portal_gcf_dir . '/src/omni.py',
                   '-c',
		   $omni_file,
		   'createslice',
		   $name
                   );
     $command = implode(" ", $cmd_array);
     $result = exec($command, $output, $status);
//     print_r($output);  
//     print_r($result);
//     print "RESULT = " . $result . "\n";
//     print "STATUS = " . $status . "\n";
     unlink($cert_file);
     unlink($key_file);
     unlink($omni_file);

}

function sa_create_slice($user, $slice_id, $name)
{
  /* Could be HTTP_HOST or SERVER_NAME */
  $http_host = $_SERVER['HTTP_HOST'];
  $sa_url = "https://" . $http_host . "/sa/sa_controller.php";
  $message['operation'] = 'create_slice';
  $message['slice_name'] = $name;
  $message = json_encode($message);
  // sign
  // encrypt
  $tmpfile = tempnam(sys_get_temp_dir(), "msg");
  file_put_contents($tmpfile, $message);
  $ch = curl_init();
  $fp = fopen($tmpfile, "r");
  curl_setopt($ch, CURLOPT_URL, $sa_url);
  curl_setopt($ch, CURLOPT_PUT, true);
  curl_setopt($ch, CURLOPT_INFILE, $fp);
  curl_setopt($ch, CURLOPT_INFILESIZE, strlen($message));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $result = curl_exec($ch);
  $error = curl_error($ch);
  curl_close($ch);
  fclose($fp);
  unlink($tmpfile);
  if ($error) {
    error_log("sa_create_slice error: $error");
    $result = NULL;
  }
  return $result;
}

// Do we have all the required params?
if ($name) {
  $slice = fetch_slice_by_name($name);
  if (is_null($slice)) {
    // no slice by that name, create it
    /* print "name = $name, creating slice<br/>"; */
    $slice_id = make_uuid();
    /* print "slice id = $slice_id<br/>"; */

    /* Get a slice from GCF slice authority */
    //omni_create_slice($user, $slice_id, $name);
    $result = sa_create_slice($user, $slice_id, $name);
    $pretty_result = print_r($result, true);
    error_log("sa_create_slice result: $pretty_result\n");

    db_create_slice($user->account_id, $slice_id, $name);
    /* print "done creating slice<br/>"; */
    relative_redirect('home');
  } else {
    $message = "Slice name \"" . $name . "\" is already taken."
      . " Please choose a different name." ;
  }
}

// If here, present the form
include("header.php");
if ($message) {
  // It would be nice to put this in red...
  print "<i>" . $message . "</i>\n";
}
print '<form method="GET" action="createslice">';
print "\n";
print 'Slice name: ';
print "\n";
print '<input type="text" name="name"/><br/>';
print "\n";
print '<input type="submit" value="Create slice"/>';
print "\n";
print '</form>';
print "\n";
?>
<?php
include("footer.php");
?>