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

require_once("user.php");
require_once("header.php");
require_once('util.php');
require_once('pa_constants.php');
require_once('pa_client.php');
require_once('sr_constants.php');
require_once('sr_client.php');

$user = geni_loadUser();
if (!isset($user) || is_null($user) || ! $user->isActive()) {
  relative_redirect('home.php');
}

$isnew = true;
$name = "";
$purpose = "";
$expiration = "";
$lead_id = $user->account_id;
$newlead = $lead_id;
unset($project);
include("tool-lookupids.php");
if (isset($project)) {
  $isnew = false;
}

if (array_key_exists(PA_PROJECT_TABLE_FIELDNAME::PROJECT_NAME, $_REQUEST)) {
  // FIXME validate inputs
  $name = $_REQUEST[PA_PROJECT_TABLE_FIELDNAME::PROJECT_NAME];
}
if (array_key_exists(PA_PROJECT_TABLE_FIELDNAME::PROJECT_PURPOSE, $_REQUEST)) {
  // FIXME validate inputs
  $purpose = $_REQUEST[PA_PROJECT_TABLE_FIELDNAME::PROJECT_PURPOSE];
}
if (array_key_exists(PA_PROJECT_TABLE_FIELDNAME::EXPIRATION, $_REQUEST)) {
  $expiration = $_REQUEST[PA_PROJECT_TABLE_FIELDNAME::EXPIRATION];
  if (isset($expiration) && ! is_null($expiration) && $expiration != "") {
    $desired_array = date_parse($expiration);
    // If you didn't specify a time for the project expiration
    if ($desired_array["hour"] == 0 and $desired_array["minute"] == 0 and $desired_array["second"] == 0 and $desired_array["fraction"] == 0) {
      // renew for the end of the day
      $expiration = $expiration . " 23:59:59";
    }
  }
}

if (array_key_exists("newlead", $_REQUEST)) {
  // FIXME validate inputs
  $newlead = $_REQUEST['newlead'];
  if (is_null($newlead) || $newlead == '' || ! uuid_is_valid($newlead)) {
    $newlead = $lead_id;
  }

  // FIXME: Check if newlead is allowed to be a project lead?
}
//print "ID=$project_id, Name=$name, Purpose=$purpose, newlead=$newlead.<br/>\n";

// FIXME: If got a newlead diff from in DB, then send a message to them to accept it
$result = null;

if ($isnew) {
  if (! $user->isAllowed(PA_ACTION::CREATE_PROJECT, CS_CONTEXT_TYPE::RESOURCE, null)) {
    error_log("Non project lead " . $user->urn . " tried to create a project");
    relative_redirect('home.php');
  }

  // Validate the project name...
  if (! isset($name) or is_null($name) or $name == '') {
    error_log("do-edit-project missing a project name");
    relative_redirect('error-text.php?error=' . urlencode("Project Name is required."));
  } else if (strpos($name, ' ') !== false) {
    error_log("do-edit-project: project name '$name' contains spaces");
    relative_redirect('error-text.php?error=' . urlencode("Project Name may not contain spaces."));
  } else if (strlen($name) > 32) {
    error_log("do-edit-project: project name '$name' too long");
    relative_redirect('error-text.php?error=' . urlencode("Project Name '$name' is too long - use at most 32 characters."));
  } else if (! is_valid_project_name($name)) {
    error_log("do-edit-project: project name '$name' invalid");
    relative_redirect('error-text.php?error=' . urlencode("Project Name '$name' is invalid: Use at most 32 alphanumeric characters or hyphen or underscore. No leading hyphen or underscore. "));
  }
  // Re-check authorization?
  // Auto?
  // Ensure project name is unique?!
  $project_id = create_project($sa_url, $user, $name, $lead_id, $purpose,
          $expiration);
  if ($project_id == "-1" || ! uuid_is_valid($project_id)) {
    error_log("do-edit-project create_project got project_id $project_id");
    $result = "Error";
  } else {
    $result = "New";
  }
  //  print "Created project, got ID: $project_id<.br/>\n";
  // Return on error?
} else {
  //  error_log("about to update project");

  if (! $user->isAllowed(PA_ACTION::UPDATE_PROJECT, CS_CONTEXT_TYPE::PROJECT, $project_id)) {
    error_log("Non privileged user " . $user->urn . " tried to update project " . $project_id);
    relative_redirect('home.php');
  }

  // FIXME: Diff new vals from old?
  
  // If this fails, message_handler currently redirects to an error page
  // If this fails, the return is a count of rows updated (should be 1)
  $result = update_project($sa_url, $user, $project_id, $name, $purpose,
          $expiration);
  if ($result == '') {
    error_log("update_project failed? empty...");
    $result = "update failed";
  } else {
    $result = "updated";
  }

  //  print "Edited project $project, got result: $result.<br/>\n";
  // Return on error?
}

if (! isset($name) or is_null($name) or $name == '') {
  $_SESSION['lastmessage'] = "Edited project: $result";
} else {
  $_SESSION['lastmessage'] = "Edited project $name: $result";
}
// show_header('GENI Portal: Projects');
relative_redirect('project.php?project_id='.$project_id . "&result=" . $result);

include("footer.php");
?>
