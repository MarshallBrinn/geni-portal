<?php
//----------------------------------------------------------------------
// Copyright (c) 2013-2015 Raytheon BBN Technologies
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

require_once('response_format.php');
require_once('util.php');
require_once('guard.php');
require_once 'geni_syslog.php';

//
// requires php5-xmlrpc (as a .deb).
//

class CHAPI_OPTION {
  const DUMMY = "_dummy";
  const SPEAKING_FOR = "speaking_for";
}

class CHAPI_KEY {
  const CREDENTIAL_TYPE_ABAC = "geni_abac";
}

/**
 * Maximum number of characters to pass to error-text.php. If the
 * error output is too long, the browser can't display the error
 * properly because the URL is too long.
 */
const MAX_ERROR_CHARS = 1500;

// A non standard result handler, that does not redirect to the error-text page on error
// Use this when making calls after starting the page header
// Be sure to handle a null result carefully
// To use this, set the global $put_message_result_handler='no_redirect_result_handler';
// Be sure to unset that global when done
function no_redirect_result_handler($result)
{
  $not_array = ($result == null) || !is_array($result);
  $not_standard_result = $not_array ||
    !array_key_exists(RESPONSE_ARGUMENT::CODE, $result) ||
    !array_key_exists(RESPONSE_ARGUMENT::VALUE, $result) ||
    !array_key_exists(RESPONSE_ARGUMENT::OUTPUT, $result);
  $not_fault_result = $not_array || !isset($result['faultString']);

  if ($not_standard_result && $not_fault_result) {
    error_log("no_redirect got nonfatal error: System error: Invalid response " . print_r($result, true));
    return null;
  }

  if (isset($result['faultString'])) {
    $short_string = substr($result['faultString'], 0,
			   MAX_ERROR_CHARS);
    error_log("no_redirect got nonfatal error from: SCRIPT_NAME = " . $_SERVER['SCRIPT_NAME']);
    error_log("faultString = " . $result["faultString"]);
    return null;
  }

  if ($result[RESPONSE_ARGUMENT::CODE] != RESPONSE_ERROR::NONE) {
    error_log("no_redirect got nonfatal error from: SCRIPT_NAME = " . $_SERVER['SCRIPT_NAME']);
    error_log("ERROR.CODE " . print_r($result[RESPONSE_ARGUMENT::CODE], true));
    error_log("ERROR.VALUE " . print_r($result[RESPONSE_ARGUMENT::VALUE], true));
    error_log("ERROR.OUTPUT " . print_r($result[RESPONSE_ARGUMENT::OUTPUT], true));

    return null;
  }
  return $result[RESPONSE_ARGUMENT::VALUE];
}

// CH API XML/RPC client abstraction.
// If $signer (also called user) is supplied, will use private key and cert to sign 
// messages.
class XMLRPCClient
{
  private $url;
  private $signer;
  private $combined = null;   // memory cache of PEM encoded cert+privatekey
  private $keyfile = null;    // name of the keyfile written to (if known)

  // this is intended to allow for the maintenance of a cache of 
  // constructed instances, but requires a long-lived php process (along
  // with access to an appropriately long-lived cache variable) to 
  // get substantial benefits.  The _SESSION array is not a good match
  // for such a cache, since it gets serialized to disk between invocations
  // and objects are destructed after serializing, leaving things like
  // the signer files broken on the disk.
  public static function get_client($url, $signer=null) {
    return $client = new XMLRPCClient($url, $signer);
  }    

  // arguments:
  //  
  function __construct($url, $signer=null)
  {
    $this->url = $url;
    $this->signer = $signer;
    if (!is_null($signer)) {
      $this->private_key = $signer->privateKey();
      $this->certificate = $signer->certificate();
    }
  }

  // clean up by deleting the cred file if we made one
  function __destruct() 
  {
    if (! is_null($this->keyfile)) {
      unlink($this->keyfile);
      $this->keyfile = null;  // wipe out the variable just to be sure
    }
  }
  // magic calls.  $this->foo(arg1, arg2) turns into:
  //     $this->__call("foo", array(arg1, arg2))
  public function __call($fun, $args)
  {
    return $this->call($fun, $args);
  }

  // if the function called has a leading '_' the return value will not be processed
  // using the usual triple.
  public function call($fun, $args)
  {
    $rawreturn = false;
    if (strpos($fun, '_') === 0) {
      $rawreturn = true;
      $fun = substr($fun, 1);
    }

    /* Declare UTF-8 encoding because that's what
     * we're sending.
     */
    $output_options = array('encoding' => 'UTF-8',
                            'escaping' => 'markup');
    $request = xmlrpc_encode_request($fun, $args, $output_options);

    // mik: I would have liked to use the following, but it
    // had problems dealing with HTTPS+POST in some situations
    // Note: It *might* have been that it wanted the content-length header
    // added, but CURL works, so we'll go with it.
    //$context = stream_context_create(array('http' => $opts));
    //$file = file_get_contents($this->url, false, $context);
    $ch = curl_init();
    $headers = array("Content-Type: text/xml",
		     "Content-Length: ".strlen($request),
		     "\r\n");
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // enable this
    // CURLOPT_CAPATH, /path/to/CA/dir
    //

    $pemf = null;
    if (!is_null($this->signer)) {
      if (is_null($this->keyfile)) {
	//	error_log("SIGNER = " . print_r($this->signer, true));
	$pemf = $this->_write_combined_credentials();
	$this->keyfile = $pemf;
      }
      curl_setopt($ch, CURLOPT_SSLKEY, $this->keyfile);
      curl_setopt($ch, CURLOPT_SSLKEYTYPE, "PEM");
      curl_setopt($ch, CURLOPT_SSLCERT, $this->keyfile);
    }

    //   error_log("CURL : " . $this->url);
    $ret = curl_exec($ch);
    if ($ret === FALSE) {
      error_log("CHAPI: CURL_ERROR = " . curl_error($ch));
    }

    curl_close($ch);  // TODO: would be nice to reuse these connections at this level
    
    // keyfile cleanup is handled by the destructor

    if ($rawreturn) {
      return $result;
    }

    $result = xmlrpc_decode($ret);

    // Translate the result into UTF-8
    //
    // We get Unicode strings back from the clearinghouse via
    // XML-RPC. We want UTF-8 to send back to the web browser. The
    // choice is to do it all here, a bit blindly, or sprinkle calls
    // to utf8_encode everywhere we're printing strings that might be
    // unicode. This is the more maintainable approach.
    array_walk_recursive($result, "encode_string");

    return $this->result_handler($result);
  }

  // Write the combined cert (cert and private key) to a file for our transport.
  // the signer instance has files, but they are raw binary, rather than the
  // PEM encoded files that cURL expects.
  //
  // TODO: figure out how to use the raw files rather than having to write these.
  //
  // arguments:
  //   $file: if null, will create a temporary file, returning the name.  Otherwise, 
  //     will write to the filename specified. 
  // return:
  //   the name of the file written to.
  function _write_combined_credentials($file=null) {
    if (is_null($this->combined)) {
      $this->combined = $this->private_key . $this->certificate;
    }
    if (is_null($file)) {
      $file = tempnam(sys_get_temp_dir(), "signer");
    }
    file_put_contents($file, $this->combined);
    return $file;
  }

  // unpack the CHAPI results, retaining compatibilty with the 
  // old put_message functionality:  If $put_message_result_handler
  // is defined (and not null), invoke it to process the results
  // otherwise do the default thing.
  //
  function result_handler($result)
  {
    // allow a custom result handler
    global $put_message_result_handler;
    if (isset($put_message_result_handler)) {
      if ($put_message_result_handler != null) {
	return $put_message_result_handler($result);
      }
    }

    $not_array = ($result == null) || !is_array($result);
    $not_standard_result = $not_array ||
      !array_key_exists(RESPONSE_ARGUMENT::CODE, $result) ||
      !array_key_exists(RESPONSE_ARGUMENT::VALUE, $result) ||
      !array_key_exists(RESPONSE_ARGUMENT::OUTPUT, $result);
    $not_fault_result = $not_array || !isset($result['faultString']);

//    error_log("NA = " . print_r($not_array, true));
//    error_log("NSR = " . print_r($not_standard_result, true));
//    error_log("NFR = " . print_r($not_fault_result, true));
//    error_log("RESULT = " . print_r($result, true));

    if ($not_standard_result && $not_fault_result) {
      error_log("System error: Invalid response " . print_r($result, true));
      $short_string = 'Invalid result received from Clearinghouse API: ';
      $short_string .= substr(print_r($result, true), 0,
                             MAX_ERROR_CHARS);
      relative_redirect('error-text.php?system_error=1&error='
                        . urlencode($short_string));
    }

    // default handling
    if (isset($result['faultString'])) {
//      error_log("FS = " . $result['faultString']);
//      error_log("FS.enc = " . urlencode($result['faultString']));
//      error_log("SCRIPT_NAME = " . $_SERVER['SCRIPT_NAME']);
//      error_log("ERROR.OUTPUT " . print_r($result['faultString'], true));
      $short_string = substr($result['faultString'], 0,
                             MAX_ERROR_CHARS);
      relative_redirect('error-text.php?system_error=1&error='
                        . urlencode($short_string));
    }

    if ($result[RESPONSE_ARGUMENT::CODE] != RESPONSE_ERROR::NONE) {
      error_log("SCRIPT_NAME = " . $_SERVER['SCRIPT_NAME']);
      error_log("ERROR.CODE " . print_r($result[RESPONSE_ARGUMENT::CODE], true));
      error_log("ERROR.VALUE " . print_r($result[RESPONSE_ARGUMENT::VALUE], true));
      error_log("ERROR.OUTPUT " . print_r($result[RESPONSE_ARGUMENT::OUTPUT], true));
      
      $short_string = substr($result[RESPONSE_ARGUMENT::OUTPUT], 0,
                             MAX_ERROR_CHARS);
      relative_redirect('error-text.php?error=' . urlencode($short_string));
    }
    return $result[RESPONSE_ARGUMENT::VALUE];
  }

  // get the "credentials" blob needed for various CHAPI service calls,
  // mainly in support of SPEAKS-FOR functionality.
  // Some future use will likely want to use $this->signer
  // Note: this is creds() rather than get_credentials() because there
  // is an sa->get_credentials()
  function creds() {
    $sfcred = NULL;
    if (! is_null($this->signer)) {
      $sfcred = $this->signer->speaksForCred();
    }
    if (is_null($sfcred)) {
      return array();
    } else {
      return array($sfcred->credentialForFedAPI());
    }
  }

  function options() {
    $sfcred = NULL;
    if (! is_null($this->signer)) {
      $sfcred = $this->signer->speaksForCred();
    }
    if (is_null($sfcred)) {
      /* For options we have to return non-empty so that it gets sent
       * as a dictionary through XML-RPC. Use a dummy key. */
      return array(CHAPI_OPTION::DUMMY => 'null');
    } else {
      return array(CHAPI_OPTION::SPEAKING_FOR => $sfcred->signerURN());
    }
  }
}

/*
 * UTF-8 encode all strings.
 *
 * This is used to translate all the unicode strings we got back from
 * the clearinghouse into UTF-8 encoded strings. This function is
 * called via array_walk_recursive() so it only handles the one item
 * it is sent. array_walk_recursive() handles the rest.
 */
function encode_string(&$item, $key)
{
  $item_type = gettype($item);
  if ($item_type == "string") {
    $item = utf8_encode($item);
  }
}

// Session cache access.  This is a simpler static interface than 
// session_cache.php
// 
function get_session_cached($skey)
{
  if(!isset($_SESSION)) {
    session_start(); // make sure that _session exists
  }
  if (!array_key_exists($skey, $_SESSION)) {
    $_SESSION[$skey] = array();
  }
  return $_SESSION[$skey];
}

function set_session_cached($skey, $ar)
{
  if(!isset($_SESSION)) {
    session_start(); // make sure that _session exists
  }
  $_SESSION[$skey] = $ar;
  return $ar;
}

function get_session_cached_value($skey, $valuekey)
{
  $cache = get_session_cached($skey);
  if (array_key_exists($valuekey, $cache)) {
    return $cache[$valuekey];
  } else {
    return null;
  }
}

function set_session_cached_value($skey, $valuekey, $value)
{
  $cache = get_session_cached($skey);
  $cache[$valuekey] = $value;
  set_session_cached($skey, $cache);
}
