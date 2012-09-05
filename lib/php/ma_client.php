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

// Client-side interface to GENI Clearinghouse Member Authority (MA)

require_once('ma_constants.php');
require_once('message_handler.php');

// Get list of all member_ids in repository
function get_member_ids($ma_url, $signer)
{
  $get_member_ids_message['operation'] = 'get_member_ids';
  $result = put_message($ma_url, $get_member_ids_message,
          $signer->certificate(), $signer->privateKey());
  return $result;
}

// Associate SSH public key with user
function register_ssh_key($ma_url, $signer, $member_id, $filename,
        $description, $ssh_key)
{
  $register_ssh_key_message['operation'] = 'register_ssh_key';
  $register_ssh_key_message[MA_ARGUMENT::MEMBER_ID] = $member_id;
  $register_ssh_key_message[MA_ARGUMENT::SSH_FILENAME] = $filename;
  $register_ssh_key_message[MA_ARGUMENT::SSH_DESCRIPTION] = $description;
  $register_ssh_key_message[MA_ARGUMENT::SSH_KEY] = $ssh_key;
  $result = put_message($ma_url, $register_ssh_key_message);
  return $result;
}

// Lookup SSH keys associated with user
function lookup_ssh_keys($ma_url, $signer, $member_id)
{
  $lookup_ssh_keys_message['operation'] = 'lookup_ssh_keys';
  $lookup_ssh_keys_message[MA_ARGUMENT::MEMBER_ID] = $member_id;
  $ssh_keys = put_message($ma_url, $lookup_ssh_keys_message,
          $signer->certificate(), $signer->privateKey());
  return $ssh_keys;
}

// Lookup inside keys/certs associated with a user UUID
function lookup_keys_and_certs($ma_url, $signer, $member_uuid)
{
  $lookup_keys_and_certs_message['operation'] = 'lookup_keys_and_certs';
  $lookup_keys_and_certs_message[MA_ARGUMENT::MEMBER_ID] = $member_uuid;
  $keys_and_certs = put_message($ma_url, $lookup_keys_and_certs_message);
}

function ma_create_account($ma_url, $signer, $attrs,
        $self_asserted_attrs)
{
  $all_attrs = array();
  foreach (array_keys($attrs) as $attr_name) {
    $all_attrs[] = array(MA_ATTRIBUTE::NAME => $attr_name,
            MA_ATTRIBUTE::VALUE => $attrs[$attr_name],
            MA_ATTRIBUTE::SELF_ASSERTED => FALSE);
  }
  foreach (array_keys($self_asserted_attrs) as $attr_name) {
    $all_attrs[] = array(MA_ATTRIBUTE::NAME => $attr_name,
            MA_ATTRIBUTE::VALUE => $self_asserted_attrs[$attr_name],
            MA_ATTRIBUTE::SELF_ASSERTED => TRUE);
  }
  $msg['operation'] = 'create_account';
  $msg[MA_ARGUMENT::ATTRIBUTES] = $all_attrs;
  $result = put_message($ma_url, $msg,
          $signer->certificate(), $signer->privateKey());
  return $result;
}

class Member {
  function __construct() {
  }
  function init_from_record($record) {
    $this->member_id = $record[MA_ARGUMENT::MEMBER_ID];
    $attrs = $record[MA_ARGUMENT::ATTRIBUTES];
    foreach ($attrs as $attr) {
      $aname = $attr[MA_ATTRIBUTE::NAME];
      $aval = $attr[MA_ATTRIBUTE::VALUE];
      $this->{$aname} = $aval;
    }
  }
}

function ma_lookup_members($ma_url, $signer, $lookup_attrs)
{
  $attrs = array();
  foreach (array_keys($lookup_attrs) as $attr_name) {
    $attrs[] = array(MA_ATTRIBUTE::NAME => $attr_name,
            MA_ATTRIBUTE::VALUE => $lookup_attrs[$attr_name]);
  }
  $msg['operation'] = 'lookup_members';
  $msg[MA_ARGUMENT::ATTRIBUTES] = $attrs;
  $members = put_message($ma_url, $msg,
          $signer->certificate(), $signer->privateKey());
  $result = array();
  foreach ($members as $member_info) {
    $member = new Member();
    $member->init_from_record($member_info);
    $result[] = $member;
  }
  return $result;
}

function ma_list_clients($ma_url, $signer)
{
  $list_clients_message['operation'] = "ma_list_clients";
  $result = put_message($ma_url, 
			 $list_clients_message, 
			 $signer->certificate(), 
			 $signer->privateKey());
  return $result;
}

function ma_list_authorized_clients($ma_url, $signer, $member_id)
{
  $list_authorized_clients_message['operation'] = "ma_list_authorized_clients";
  $list_authorized_clients_message[MA_ARGUMENT::MEMBER_ID] = $member_id;
  $result = put_message($ma_url, 
			 $list_authorized_clients_message, 
			 $signer->certificate(), 
			 $signer->privateKey());
  return $result;
}

function ma_authorize_client($ma_url, $signer, $member_id, $client_urn,
			     $authorize_sense)
{
  //  error_log("MAAC = " . print_r($authorize_sense, true));

  $authorize_client_message['operation'] = "ma_authorize_client";
  $authorize_client_message[MA_ARGUMENT::MEMBER_ID] = $member_id;
  $authorize_client_message[MA_ARGUMENT::CLIENT_URN] = $client_urn;
  $authorize_client_message[MA_ARGUMENT::AUTHORIZE_SENSE] = $authorize_sense;
  $result = put_message($ma_url, 
			 $authorize_client_message, 
			 $signer->certificate(), 
			 $signer->privateKey());

  //  error_log("MAAC.result = " . print_r($result, true));

  return $result;
}

// Use ma_lookup_members interface
function ma_lookup_member_id($ma_url, $signer, $member_id_key, $member_id_value)
{

  $lookup_attrs[$member_id_key] = $member_id_value;
  $result = ma_lookup_members($ma_url, $signer, $lookup_attrs);

  //  error_log("MALI.RES = " . print_r($result, true));
  return $result;
}

function ma_lookup_member_by_id($ma_url, $signer, $member_id)
{
  $msg['operation'] = 'lookup_member_by_id';
  $msg[MA_ARGUMENT::MEMBER_ID] = $member_id;
  $result = put_message($ma_url, $msg,
          $signer->certificate(), $signer->privateKey());
  $member = new Member();
  $member->init_from_record($result);
  return $member;
}
?>
