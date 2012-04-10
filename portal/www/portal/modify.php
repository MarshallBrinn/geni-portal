<?php
//----------------------------------------------------------------------
// Copyright (c) 2012 Raytheon BBN Technologies
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
require_once 'db-util.php';
$user = geni_loadUser();
include("header.php");
show_header('GENI Portal Home', $TAB_HOME);
?>

<?php
// Local functions
function shib_input($shib_name, $pretty_name, $value)
{
  print $pretty_name . ": ";
  print "<input type=\"text\" name=\"$shib_name\"";
  if (array_key_exists($shib_name, $_SERVER)) {
      if (isset($value)) {
	echo " value=\"$value\"";
      } else {
	$value = $_SERVER[$shib_name];
	echo " value=\"$value\" disabled=\"yes\"";
      }
  } else {
     echo " value=\"$value\"";
  }
  print "/><br/>\n";
}

// Only allow modifying fields that didn't come from Shib

// This should go through stuff in DB for this user.
// If user-supplied then make it editable, else disabled=yes
// pull from identity_attribute table I think
// so need the identity_id

//--------------------------------------------------
// Now pull the id out of that newly inserted identity record and add
// the additional attributes.
//--------------------------------------------------
$conn = portal_conn();
$conn->setFetchMode(MDB2_FETCHMODE_ASSOC);
$sql = "SELECT * from identity_attribute WHERE identity_id = "
  . $conn->quote($user->identity_id, 'text')
  . ";";
//print "Query = $sql<br/>";
$resultset = $conn->query($sql);
if (PEAR::isError($resultset)) {
  die("error on identity attrs select: " . $resultset->getMessage());
}
$rows = $resultset->fetchall(MDB2_FETCHMODE_ASSOC);
$rowcount = count($rows);
$attrs = array();
foreach ($rows as $row) {
  if (strtolower($row['self_asserted']) == 't') {
    // FIXME: attrs a list of name/value? so I can get it out ok....
    array_push($attrs, $row);
    //    print "Found self asserted " . $row['name'] . " = " . $row['value'] . "<br/>\n";
  } else {
    //    print "Found NOT self asserted " . $row['name'] . " = " . $row['value'] . "<br/>\n";
  }
}
?>

<br/>
<h2> Modify Account Page </h2>
Request a modification to user supplied account properties.<br>
<form method="POST" action="do-modify.php">
<?php
  $shib_fields = array('givenName' => 'First name', 'sn' => 'Last name', 'mail' => 'Email', 'telephoneNumber' => 'Telephone');
foreach (array_keys($shib_fields) as $fieldkey) {
    $is_user = false;
    foreach ($attrs as $a) {
      if ($a['name'] == $fieldkey ) {
	$is_user = true;
	shib_input($a['name'], $shib_fields[$a['name']], $a['value']);
        $a['printed'] = true;
      }
    }
    if (!$is_user) {
      shib_input($fieldkey, $shib_fields[$fieldkey], null);
    }
  }
  foreach ($attrs as $a) {
    if (! array_key_exists($a['name'], $shib_fields)) {
      shib_input($a['name'], $a['name'], $a['value']);
    }
  }
?>
<br/>
<input type="submit" value="Modify Account"/>
</form>
<?php
include("footer.php");
?>