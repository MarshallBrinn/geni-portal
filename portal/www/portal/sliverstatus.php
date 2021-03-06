<?php
//----------------------------------------------------------------------
// Copyright (c) 2011-2015 Raytheon BBN Technologies
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

require_once("header.php");
require_once("settings.php");
require_once("user.php");
require_once("file_utils.php");
require_once("sr_client.php");
require_once("sr_constants.php");
require_once("am_client.php");
require_once("sa_client.php");
require_once("am_map.php");
require_once("json_util.php");
require_once('status_constants.php');

$user = geni_loadUser();
if (! $user->isActive()) {
  relative_redirect('home.php');
}

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
unset($slice);
include("tool-lookupids.php");
if (! isset($slice)) {
  no_slice_error();
}

if (!$user->isAllowed(SA_ACTION::LOOKUP_SLICE, CS_CONTEXT_TYPE::SLICE, $slice_id)) {
  relative_redirect('home.php');
}

if (isset($slice_expired) && convert_boolean($slice_expired)) {
  if (! isset($slice_name)) {
    $slice_name = "";
  }
  $_SESSION['lasterror'] = "Slice " . $slice_name . " is expired.";
  relative_redirect('dashboard.php#slices');
}

$header = "Status of Slivers on slice: $slice_name";

// String to disable button or other active element
$disabled = "disabled = " . '"' . "disabled" . '"'; 

$get_slice_credential_privilege = $user->isAllowed(SA_ACTION::GET_SLICE_CREDENTIAL, 
						   CS_CONTEXT_TYPE::SLICE, $slice_id);
$get_slice_credential_disable_buttons = "";
if(!$get_slice_credential_privilege) {$get_slice_credential_disable_buttons = $disabled; }

show_header('GENI Portal: Slices');
include("tool-breadcrumbs.php");
if (! isset($am_id) or is_null($am_id)) {
  $am_id = "";
}
?>

<script src="amstatus.js"></script>
<script>
var slice_uid = "<?php echo $slice_id ?>";
var am_id= <?php echo json_encode($am_ids) ?>;

<?php include('status_constants_import.php'); ?>

$(document).ready(build_agg_table_on_sliverstatuspg);
</script>

<?php
print "<h1>$header</h1>\n";

// Count Aggs. If only one, change 'all' text
$amcnt = 0;
if (isset($ams)) {
  $amcnt = count($ams);
}
if (isset($am_ids) and $amcnt == 0) {
  $amcnt = count($am_ids);
}
if (! isset($ams) and ! isset($am_ids)) {
  $amcnt = 0;
  //error_log("sliverstatus had no ams or amids or am_id");
}

$amcntstr = "aggregate";
if ($amcnt >= 2) {
  $amcntstr = $amcnt . " aggregates";
} elseif ($amcnt == 0) {
  $amcntstr = "all aggregates";
}
        

//echo "<div class='aggregate'>Querying status of resources at " . $amcntstr . "...</div>";
print "<div class='aggregate' id='prettyxml'>";
print "<p id='query' style='display:block;'><i>Querying aggregates for status of resources...</i></p>";

print "<p id='summary' style='display:none;'><i>Queried resources at <span id='numagg'>0</span> aggregates. </i><br/>";
print "<button id='reload_all_button' type='button' onclick='location.reload(true)' $get_slice_credential_disable_buttons>Refresh Status</button></p>";

//print "<p><button id='reload_all_button' type='button' onclick='location.reload(true)' $get_slice_credential_disable_buttons>Refresh Status</button></p>";

print "</p>";
print "</div>\n";

echo "<div id='sliverstatus'><table id='sliverstatus'></table></div>";	

print "<div id='slivererror'>";
print "<table id='slivererror'></table></div>";

if (isset($am_id) && $am_id ) {
  $am_id_str = "&am_id=$am_id";
} else {
  $am_id_str = "";
}


print "<p><a href='raw-sliverstatus.php?slice_id=".$slice_id.$am_id_str."'>(Refetch and) Show Raw SliverStatus</a></p>";
print "<hr/>";
print "<p><a href='dashboard.php#slices'>Back to All slices</a>";
print "<br/>";
print "<a href='slice.php?slice_id=$slice_id'>Back to Slice <i>$slice_name</i></a></p>";

include("footer.php");
?>
