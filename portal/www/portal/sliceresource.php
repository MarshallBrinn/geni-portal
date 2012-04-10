<?php
//----------------------------------------------------------------------
// Copyright (c) 2011 Raytheon BBN Technologies
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
?>
<?php
require_once("settings.php");
require_once("user.php");
require_once("file_utils.php");
require_once("sr_client.php");
require_once("sr_constants.php");
require_once("am_client.php");
require_once("sa_client.php");
$user = geni_loadUser();
if (! $user->privSlice()) {
  exit();
}
?>
<?php
function no_slice_error() {
  header('HTTP/1.1 404 Not Found');
  print 'No slice id specified.';
  exit();
}

if (! count($_GET)) {
  // No parameters. Return an error result?
  // For now, return nothing.
  no_slice_error();
}
if (array_key_exists('id', $_GET)) {
  $slice_id = $_GET['id'];
} else {
  no_slice_error();
}

// Look up the slice...
$slice = fetch_slice($slice_id);

// Get slice authority URL
$sa_url = get_first_service_of_type(SR_SERVICE_TYPE::SLICE_AUTHORITY);

// Get an AM
$am_url = get_first_service_of_type(SR_SERVICE_TYPE::AGGREGATE_MANAGER);
error_log("AM_URL = " . $am_url);

$result = get_version($am_url, $user);
error_log("VERSION = " . $result);

/*
$rspec = list_resources($am_url, $user);
error_log("RSPEC = " . $rspec);
*/

// Get the slice credential from the SA
$slice_credential = get_slice_credential($sa_url, $slice_id, $user);

// Retrieve a canned RSpec
$rspec = fetchRSpecById(1);
$rspec_file = writeDataToTempFile($rspec);

// Call create sliver at the AM
$sliver_output = create_sliver($am_url, $user, $slice_credential,
                               $slice_id, $rspec_file);
unlink($rspec_file);
error_log("CreateSliver output = " . $sliver_output);

relative_redirect('slices');

?>