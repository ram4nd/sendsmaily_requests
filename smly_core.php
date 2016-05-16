<?php
/**
 * @file Core class for Sendsmaily API requests.
 *
 * @author Ra Mänd <ram4nd@gmail.com>
 * @link http://browse-tutorials.com/
 *
 * Example:
 * require_once 'smly_core.php'
 * $smly = new smly('username', 'password', 'client');
 *
 * $list = $smly->curl_get('contact.php', array(
 *   'list' => 1,
 * ));
 */

class smly
{
  private $username;
  private $password;
  private $domain;

  public $errors = array();

  private $protocol = 'https';
  private $tld = 'net';

  public function __construct($username, $password, $domain) {
    $this->username = $username;
    $this->password = $password;

    $this->domain = $this->protocol . '://' . $domain . '.sendsmaily.' . $this->tld . '/api/';
  }

  public function curl_get($url, $query = array()) {
    $query = urldecode(http_build_query($query));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->domain . $url . '?' . $query);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);

    $result = curl_exec($ch);
    if ($result === false) {
      $this->_error(curl_error($ch));
    }
    curl_close($ch);

    return $this->_process_request($result);
  }

  public function curl_post($url, $query) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->domain . $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($query));
    curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);

    $result = curl_exec($ch);
    if ($result === false) {
      $this->_error(curl_error($ch));
    }
    curl_close($ch);

    $result = $this->_process_request($result);

    if (!isset($result['code'])) {
      $this->_error('Something went wrong with the request.');
      return FALSE;
    }
    elseif ((int) $result['code'] === 101) {
      return TRUE;
    }
    else {
      $this->_error($result['message']);
      return FALSE;
    }
  }

  private function _process_request($curl_result) {
    return json_decode($curl_result, true);
  }

  private function _error($msg) {
    $this->errors[] = $this->domain . ' - ' . date('d.m.Y H:i:s') . ': ' . $msg;
  }

  public function set_domain($domain) {
    $this->domain = $this->protocol . '://' . $domain . '.sendsmaily.' . $this->tld . '/api/';
  }
}
