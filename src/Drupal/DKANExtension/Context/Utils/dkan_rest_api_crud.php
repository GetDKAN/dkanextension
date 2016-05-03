<?php
namespace Drupal\DKANExtension\Context\Utils;

use \stdClass;
use \Exception;

/**
 * Init CURL objetc.
 */
function dkan_rest_api_curl_init($request_url, $csrf_token = FALSE) {
  // cURL.
  $curl = curl_init($request_url);
  if ($csrf_token) {
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
      'Accept: application/json',
      'X-CSRF-Token: ' . $csrf_token
    ));
  }
  else {
    // Accept JSON response.
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Accept: application/json'));
  }
  // Ask to not return Header.
  curl_setopt($curl, CURLOPT_HEADER, FALSE);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($curl, CURLOPT_FAILONERROR, TRUE);
  return $curl;
}

/**
 * Execute CURL request and process response.
 */
function dkan_rest_api_curl_parse($curl) {
  $response = array();

  $result = curl_exec($curl);
  $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

  $response['http_code'] = $http_code;

  if ($http_code == 200) {
    $response['success'] = true;
    $response['response'] = json_decode($result);
  }
  else {
    $response['success'] = false;
    $response['response'] = curl_error($curl);
  }

  return $response;
}

/**
 * Logs in user.
 */
function dkan_rest_api_user_login($base_url, $endpoint, $username, $password) {
  // Build request URL.
  $request_url = $base_url . $endpoint . '/user/login';
  // User data.
  $user_data = array(
    'username' => $username,
    'password' => $password,
  );
  $user_data = http_build_query($user_data);

  $curl = dkan_rest_api_curl_init($request_url);
  // Do a regular HTTP POST.
  curl_setopt($curl, CURLOPT_POST, 1);
  // Set POST data.
  curl_setopt($curl, CURLOPT_POSTFIELDS, $user_data);

  $response = dkan_rest_api_curl_parse($curl);

  if ($response['success']) {
    // Define cookie session.
    $cookie_session = $response['response']->session_name . '=' . $response['response']->sessid;
    return array('cookie_session' => $cookie_session, 'curl' => $curl);
  } else {
    throw new \Exception(sprintf('Error: %s', $response['response']));
  }
}

/**
 * Retrives CSRF token.
 */
function dkan_rest_api_get_csrf($cookie_session, $curl, $base_url) {
  // GET CSRF TOKEN.
  curl_setopt_array($curl, array(
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_URL => $base_url . '/services/session/token',
  ));
  curl_setopt($curl, CURLOPT_COOKIE, "$cookie_session");

  $ret = new \stdClass();

  $ret->response = curl_exec($curl);
  $ret->error = curl_error($curl);
  $ret->info = curl_getinfo($curl);

  $csrf_token = $ret->response;
  return $csrf_token;
}

/**
 * Create node.
 */
function dkan_rest_api_create_node($node_data, $csrf_token, $cookie_session, $base_url, $endpoint, $entity) {
  // REST Server URL.
  $request_url = $base_url . $endpoint . '/' . $entity;

  $node_data = http_build_query($node_data);

  $curl = dkan_rest_api_curl_init($request_url, $csrf_token);
  // Do a regular HTTP POST.
  curl_setopt($curl, CURLOPT_POST, 1);
  // Set POST data.
  curl_setopt($curl, CURLOPT_POSTFIELDS, $node_data);
  // Use the previously saved session.
  curl_setopt($curl, CURLOPT_COOKIE, "$cookie_session");

  $response = dkan_rest_api_curl_parse($curl);

  if ($response['success']) {
    return $response['response'];
  } else {
    throw new \Exception(sprintf('Error: %s', $response['response']));
  }
}

/**
 * Update node.
 */
function dkan_rest_api_update_node($node_data, $nid, $csrf_token, $cookie_session, $base_url, $endpoint, $entity) {
  // REST Server URL.
  $request_url = $base_url . $endpoint . '/' . $entity . '/' . $nid;

  $node_data = http_build_query($node_data);

  $curl = dkan_rest_api_curl_init($request_url, $csrf_token);
  // Do a regular HTTP POST.
  curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
  // Set POST data.
  curl_setopt($curl, CURLOPT_POSTFIELDS, $node_data);
  // Use the previously saved session.
  curl_setopt($curl, CURLOPT_COOKIE, "$cookie_session");

  $response = dkan_rest_api_curl_parse($curl);

  if ($response['success']) {
    return $response['response'];
  } else {
    throw new \Exception(sprintf('Error: %s', $response['response']));
  }
}

/**
 * Attach file to node.
 */
function dkan_rest_api_attach_file($file_data, $node_id, $csrf_token, $cookie_session, $base_url, $endpoint, $entity) {
  // REST Server URL.
  $request_url = $base_url . $endpoint . '/' . $entity . '/' . $node_id . '/attach_file';

  $curl = dkan_rest_api_curl_init($request_url, $csrf_token);
  // Add 'Content-Type: multipart/form-data' on header.
  curl_setopt($curl, CURLOPT_HTTPHEADER, array(
    'Content-Type: multipart/form-data',
    'Accept: application/json',
    'X-CSRF-Token: ' . $csrf_token
  ));
  // Do a regular HTTP POST.
  curl_setopt($curl, CURLOPT_POST, 1);
  // Set POST data.
  curl_setopt($curl, CURLOPT_POSTFIELDS, $file_data);
  // Use the previously saved session.
  curl_setopt($curl, CURLOPT_COOKIE, "$cookie_session");

  $response = dkan_rest_api_curl_parse($curl);

  if ($response['success']) {
    return $response['response'];
  } else {
    throw new \Exception(sprintf('Error: %s', $response['response']));
  }
}

/*
 * Delete node.
 */
function dkan_rest_api_delete_node($nid, $csrf_token, $cookie_session, $base_url, $endpoint, $entity) {
  // REST Server URL.
  $request_url = $base_url . $endpoint . '/' . $entity . '/' . $nid;

  $curl = dkan_rest_api_curl_init($request_url, $csrf_token);
  // Set POST data.
  curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
  // Use the previously saved session.
  curl_setopt($curl, CURLOPT_COOKIE, "$cookie_session");

  $response = dkan_rest_api_curl_parse($curl);

  if ($response['success']) {
    return $response['response'];
  } else {
    throw new \Exception(sprintf('Error: %s', $response['response']));
  }
}