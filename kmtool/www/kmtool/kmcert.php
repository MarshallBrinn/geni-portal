<?php
//----------------------------------------------------------------------
// Copyright (c) 2012-2015 Raytheon BBN Technologies
//
// Permission is hereby granted, free of charge, to any person obtaining
// a copy of this software and/or hardware specification (the "Work") to
// deal in the Work without restriction, including without limitation the
// rights to use, copy, modify, merge, publish, distribute, sublicense,
// and/or sell copies of the Work, and to permit persons to whom the Work
// is furnished to do so, subject to the following conditions:
//
// The above copyright notice and this permission notice shall be
// included in all copies or substantial portions of the Work.
//
// THE WORK IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS
// OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
// MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
// NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
// HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
// WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE WORK OR THE USE OR OTHER DEALINGS
// IN THE WORK.
//----------------------------------------------------------------------

/**
 * Home of GENI key management tool
 */

require_once('km_utils.php');
require_once('ma_client.php');
require_once('maintenance_mode.php');

$member_id_key = 'eppn';
$member_id_value = null;
$members = array();
$member = null;
$member_id = null;
if (array_key_exists($member_id_key, $_SERVER)) {
  $member_id_value = strtolower($_SERVER[$member_id_key]);
  $members = ma_lookup_member_id($ma_url, $km_signer,
				 $member_id_key, $member_id_value);
} else if (array_key_exists("member_id", $_REQUEST)) {
  $member_id = $_REQUEST["member_id"];
} else {
  error_log("No member_id_key $member_id_key given to kmcert");
}

if (count($members) > 0 && ! isset($member_id)) {
  $member = $members[0];
  $member_id = $member->member_id;
} else if (! isset($member_id)) {
  error_log("kmcert: No members found for member_id $member_id_value");
}

$username = '*** Undefined ***';
if (array_key_exists('displayName', $_SERVER)) {
  $username = $_SERVER['displayName'];
} else if (array_key_exists('sn', $_SERVER) && array_key_exists('givenName', $_SERVER)){
  $username = $_SERVER['givenName'] . " " . $_SERVER['sn'];
} else if (array_key_exists('eppn', $_SERVER)) {
  $username = strtolower($_SERVER['eppn']);
} else if (array_key_exists("username", $_REQUEST)) {
  $username = $_REQUEST["username"];
}

$renew = (key_exists('renew', $_REQUEST) && $_REQUEST['renew']);

function show_close_button() {
  if (key_exists("close", $_REQUEST)) {
    print "<br/>\n";
    print "<button onclick=\"window.close();return false;\"><b>Close</b></button>\n";
  }
}

/**
 * If in xml-signer context, show a way to continue
 * signing thread.
 */
function show_xml_signer_button() {
  if (isset($_SESSION['xml-signer'])) {
    /* We're in the thread of the xml-signer tool. Put up a continue
     * button to go to loadcert page.
     */
    $loc = $_SESSION['xml-signer'];
    unset($_SESSION['xml-signer']);
    print "<br/>\n";
    print ("<button onclick=\"window.location='$loc';\">"
           . "<b>Continue to signing tool</b>"
           . "</button>\n");
  }
}

function download_cert($ma_url, $km_signer, $member) {
  $member_id = $member->member_id;
  $username = $member->username;
  $result = ma_lookup_certificate($ma_url, $km_signer, $member_id);
  $cert_filename = "geni-$username.pem";
  // Set headers for download
  header("Cache-Control: public");
  header("Content-Description: File Transfer");
  header("Content-Disposition: attachment; filename=$cert_filename");
  header("Content-Type: application/pem");
  header("Content-Transfer-Encoding: binary");
  if (key_exists(MA_ARGUMENT::PRIVATE_KEY, $result)) {
    print $result[MA_ARGUMENT::PRIVATE_KEY];
    print "\n";
  }
  print $result[MA_ARGUMENT::CERTIFICATE];
}

function generate_cert($ma_url, $km_signer, $member_id, $csr=NULL) {
  $result = ma_create_certificate($ma_url, $km_signer, $member_id, $csr);
}

function handle_upload($ma_url, $km_signer, $member_id, &$error) {
  // Get the uploaded CSR
  if (array_key_exists('csrfile', $_FILES)) {
    $errorcode = $_FILES['csrfile']['error'];
    if ($errorcode != 0) {
      // An error occurred with the upload.
      if ($errorcode == UPLOAD_ERR_NO_FILE) {
        $error = "No file was uploaded.";
      } else {
        $error = "Unknown upload error (code = $errorcode).";
      }
      return false;
    } else {
      /* A file was uploaded. Do a rudimentary test to see if it is
       * a CSR. If not, explain.
       */
      $cmd_array = array('/usr/bin/openssl',
              'req',
              '-noout',
              '-in',
              $_FILES["csrfile"]["tmp_name"],
      );
      $command = implode(" ", $cmd_array);
      $result = exec($command, $output, $status);
      if ($status != 0) {
        $fname = $_FILES['csrfile']['tmp_name'];
        $error = "File $fname is not a valid certificate signing request.";
        return false;
      } else {
        // HERE EVERYTHING LOOKS GOOD, SO PROCESS THE CSR
        // LOAD THE CONTENTS OF THE FILE AND PASS ALONG TO generate_cert()
        $csr = file_get_contents($_FILES["csrfile"]["tmp_name"]);
        if ($csr === false) {
          // Something went wrong loading the uploaded csr
          $error = "Unable to read uploaded file contents.";
          return false;
        }
        generate_cert($ma_url, $km_signer, $member_id, $csr);
        return true;
      }
    }
  } else {
    $error = "No file uploaded.";
    return false;
  }
}

$generate_key = "generate";
$upload_key = "upload";
$download_key = "download";
$close_key = key_exists("close", $_REQUEST) ? "close" : "noclose";

$disabled = "";
$enable = True;
$user = null;
if ($in_maintenance_mode) {
  require_once("user.php");
  $user = geni_loadUser();
}
if (($in_maintenance_mode && ! is_null($user) && 
    !$user->isAllowed(CS_ACTION::ADMINISTER_MEMBERS, CS_CONTEXT_TYPE::MEMBER, 
		      null))
    or $in_lockdown_mode) {
  //  error_log("KMCert disabling input");
  $enable = False;
  $disabled = " disabled ";
  //} else {
  //  error_log("KMCert NOT disabling input");
}

if (key_exists($generate_key, $_REQUEST) and $enable) {
  // User has asked to generate a cert/key.
  generate_cert($ma_url, $km_signer, $member_id);
}
if (key_exists($upload_key, $_REQUEST) and $enable) {
  $status = handle_upload($ma_url, $km_signer, $member_id, $error);
}
if (key_exists($download_key, $_REQUEST)) {
  download_cert($ma_url, $km_signer, $member);
  return;
}

// If invoked with a ?redirect=url argument, grab that
// argument and go there from the 'continue' button
$redirect_key = "redirect";

//$redirect_address = "home.php";
$redirect_address = "";

if(array_key_exists($redirect_key, $_GET)) {
  $redirect_address = $_GET[$redirect_key];
}

// Set up the error display
if (isset($error)) {
  $_SESSION['lasterror'] = $error;
  unset($error);
}

if (isset($_SESSION['xml-signer']) && !$renew) {
  /* Special key when working with the xml-signer tool.
     This means we're in the flow of putting a cert/key into the tool, so
     maybe HTTP redirect there if this key exists in the session.
  */
  $result = ma_lookup_certificate($ma_url, $km_signer, $member_id);
  if (! is_null($result) && key_exists(MA_ARGUMENT::PRIVATE_KEY, $result)) {
    /* If the user has an outside certificate AND key, redirect back to the
       certificate loading page.
    */
    $loc = $_SESSION['xml-signer'];
    unset($_SESSION['xml-signer']);
    relative_redirect($loc);
    exit();
  }
}

/* Auto-redirect to KM activate page if there's no member id. */
if (! isset($member_id)) {
  relative_redirect("kmactivate.php");
  exit;
}

$result = ma_lookup_certificate($ma_url, $km_signer, $member_id);
$has_cert = (! is_null($result));
// Has the certificate expired?
$expired = false;
// Will the certificate expire soon?
$expiring = false;
if ($has_cert && array_key_exists('expiration', $result)) {
  // Is expiration real soon or in the past?
  $expiration = $result['expiration'];
  $now = new DateTime('now', new DateTimeZone("UTC"));
  $diff = $now->diff($expiration);
  $days = $diff->days;
  $expired = ($days < 1);
  $expiring = ($days < 31);
}


//----------------------------------------------------------------------
// Mostly display after this point.
//----------------------------------------------------------------------

include('kmheader.php');
?>
<script type="text/javascript">
function toggleDiv(divId) {
   $("#"+divId).toggle();
   $("#shownButton").toggle();
   $("#hiddenButton").toggle();
}
</script>

<?php
$page_header = "GENI Certificate Management";
if ($renew) {
  $page_header = "GENI Certificate Renewal";
}
print "<h2>$page_header</h2>\n";
include("tool-showmessage.php");

if (! isset($member_id)) {
  print "Please <a href=\"kmactivate.php\">activate your GENI account</a>.\n";
  print "<br\>\n";
  include("kmfooter.php");
  return;
}

$result = ma_lookup_certificate($ma_url, $km_signer, $member_id);
if (!$renew && $has_cert && (! $expired)) {
  $msg = "Download Your Portal Generated Certificate and Private Key";
  // User has an outside cert. Show the download screen.
  if (! key_exists(MA_ARGUMENT::PRIVATE_KEY, $result)
      || ! $result[MA_ARGUMENT::PRIVATE_KEY]) {
    $msg = "Download your Portal Signed Certificate";
  }
  if ($expiring) {
    print '<p>';
    print "Your certificate will expire in $days days.";
    print ' <a href="kmcert.php?renew=1">Renew now</a>.';
    print '</p>';
  }
?>
<h4><?php print $msg;?>:</h4>
<form name="download" action="kmcert.php" method="post">
<input type="hidden" name="<?php print $download_key ?>" value="y"/>
<input type="submit" name="submit" value="<?php print $msg;?>"/>
</form>
<?php
  show_close_button();
  show_xml_signer_button();
  include("kmfooter.php");
  return;
}

?>

<p>In order to use some GENI tools (like
<a href="http://trac.gpolab.bbn.com/gcf/wiki/Omni">omni</a>) you need a signed SSL user certificate.
</p><p>
There are two options for
<?php
if ($renew) {
  echo 'renewing';
} else {
  echo 'creating';
}
?>
 a certificate:
<ol>
<li>Have it generated for you.  This is the easiest option. <b>If in doubt, use this option.</b></li>
<li>Have the SSL certificate generated for you based on a private key you keep locally. This is the most secure option.  For advanced users only.</li>
</ol>
</p>
<!-- This next div used to have style="padding-left:10px; background...." But why? It looks funny. -->
<div style="background-color:#F0F0F0;">
<hr/>
<h2>Simple Option: Have the SSL certificate generated for you </h2>

<p><b>If in doubt, use this option.</b></p>

<form name="generate" action="kmcert.php" method="post">
<input type="hidden" name="<?php print $generate_key;?>" value="y"/>
<input type="hidden" name="<?php print $close_key; ?>" value="1"/>
<?php
  print "<input type=\"submit\" name=\"submit\" value=\"Generate Combined Certificate and Key File\" $disabled/>\n";
?>
</form>

<p><i>An SSL certificate always has a corresponding SSL private key.  This option will generate one file which contains both the signed SSL certificate and the corresponding private key.  (This is a new key generated for this SSL certificate and is different from your SSH private key.)</i></p>
<p>
Remember, in order to use this, you will need to have the downloaded combination certificate/private key file. 
</p>
<hr/>
</div>

<button id='shownButton' type='button' onclick='toggleDiv("alternative")'><b>Show Advanced Option</b></button>
<button id='hiddenButton' type='button' style='display: none;' onclick='toggleDiv("alternative")'><b>Hide Advanced Option</b></button>

<div id="alternative" style="display: none; background-color:#F0F0F0;">
<hr>
<h2>Advanced Option: Have the SSL certificate generated for you based on a private key you keep locally </h2>

<p><i>If you want to maintain control of your SSL private key, you can request to generate an SSL certificate based on a private key stored locally on your computer.  You have two options, create a new private key or reuse an existing one.</i></p>

<p>There are two variations on this option, only do one of them.</p>
<ul>
	<li>Option 2a: Create a private key, then upload a certificate signing request (CSR)</li>
<p><b>For the most security, use this option.</b></p>
	<ul>
		<li>
Run the following command in a terminal window on a Mac or Linux host. When prompted, enter the same PEM passphrase twice.
This will generate two files: <code>CSR.csr</code> and <code>geni_ssl_portal.key</code>.  <i>Note: The command below will overwrite any existing file at <code>~/.ssl/geni_ssl_portal.key</code>.</i>
Upload <code>CSR.csr</code> in the form below.
<br/>
<pre>openssl req -out CSR.csr -new -newkey rsa:2048 -keyout ~/.ssl/geni_ssl_portal.key -batch</pre>
<h4>Now upload the file CSR.csr below:</h4>
<form name="upload" action="kmcert.php" method="post" enctype="multipart/form-data">
<label for="csrfile">Certificate Signing Request File:</label>
<?php
   print "<input type=\"file\" name=\"csrfile\" id=\"csrfile\" $disabled/>\n";
?>
<br/>
<input type="hidden" name="<?php print $upload_key; ?>" value="y"/>
<input type="hidden" name="<?php print $close_key; ?>" value="1"/>
<?php
print "<input type=\"submit\" name=\"submit\" value=\"Create Certificate\" $disabled/>\n";
?>
</form>
	<br/>

		</li>
	</ul>
	<li>Option 2b: Reuse an existing private key, then upload a certificate signing request (CSR) </li>
	<br/>
	<ul>
		<li>
Run the following command in a terminal window on a Mac or Linux host. When prompted, enter the passphrase for the private key. 
This will generate a file named <code>CSR.csr</code>.
Upload <code>CSR.csr</code> in the form below.
<pre>openssl req -out CSR.csr -new -key &lt;YourPrivateKey&gt; -batch</pre>
<h4>Now upload the file CSR.csr below:</h4>
<form name="upload" action="kmcert.php" method="post" enctype="multipart/form-data">
<label for="csrfile">Certificate Signing Request File:</label>
<?php
    print "<input type=\"file\" name=\"csrfile\" id=\"csrfile\" $disabled/>\n";
?>
<br/>
<input type="hidden" name="<?php print $upload_key; ?>" value="y"/>
<input type="hidden" name="<?php print $close_key; ?>" value="1"/>
<?php
    print "<input type=\"submit\" name=\"submit\" value=\"Create Certificate\" $disabled/>\n";
?>
</form>
		</li>
	</ul>
</ul>
<p>
Remember, in order to use these, you will need to keep track of the downloaded certificate, the private key and the passphrase for the key.  
</p>
<hr>

</div>
<?php
show_close_button();

// Include this only if the redirect address is a web address
if (! empty($redirect_address)) {
  print"<button onclick=\"window.location='" .
    $redirect_address . "'" . "\"<b>Continue</b></button> back to your " .
    "Clearinghouse tool.<br/>";
}

include("kmfooter.php");
?>
