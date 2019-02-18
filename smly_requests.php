<?php

/**
 * @file Core class for Sendsmaily API requests.
 *
 * @author Ra Mänd <ram4nd@gmail.com>
 * @link http://browse-tutorials.com/
 *
 * Example:
 * require_once 'sendsmaily_api.php'
 * $smly = new sendsmaily('username', 'password', 'client');
 *
 * $list = $smly->curl_get('contact.php', array(
 *   'list' => 1,
 * ));
 */

class smly
{
  private $username;
  private $password;
  private $limit = 20000;
  public $domain;

  public function __construct($username, $password, $domain) {
    $this->username = $username;
    $this->password = $password;

    $this->domain = 'https://' . $domain . '.sendsmaily.net/api/';
  }

  public function get_history($start_at, $end_at, $actions = 'all', $modify_fields = 'all') {
    $params = array(
      'start_at' => $start_at,
      'end_at' => $end_at,
      'offset' => 0,
      'limit' => 0,
    );

    // Optional limit of response actions.
    if ($actions !== 'all') {
      $params['actions'] = $actions;
    }

    // Optional limit of response actions.
    if ($modify_fields !== 'all') {
      $params['modify_fields'] = $modify_fields;
    }

    $contacts = $this->curl_get('history.php', $params);

    if (count($contacts) > 0) {
      foreach ($contacts as &$contact) {
        if (isset($contact['value']) && !empty($contact['value'])) {
          $this->_history_data_to_array($contact['value']);
        }
        yield $contact;
        $contact = NULL;
      }
    }
  }

  public function get_contacts($list) {
    $offset = 0;

    $contacts = array();
    while (true) {
      $contacts = $this->curl_get('contact.php', array(
        'list' => $list,
        'offset' => $offset,
        'limit' => $this->limit,
      ));

      if (count($contacts) > 0) {
        foreach ($contacts as &$contact) {
          yield $contact;
          $contact = NULL;
        }
      }
      else {
        break;
      }

      ++$offset;
    }
  }

  public function forget_contacts($emails) {
    $this->curl_post('contact/forget.php', $emails, TRUE);
  }

  public function curl_get($url, $query = array()) {
    $query = urldecode(http_build_query($query));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->domain . $url . '?' . $query);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);

    $result = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ((int) $code !== 200) {
      throw new Exception('Http request error ' . $code);
    }

    if (isset($result['code']) && (int) $result['code'] !== 101) {
      throw new Exception('Http request error ' . $result['message']);
    }

    return $this->_process_request($result);
  }

  public function post_contacts(&$contacts) {
    $contacts = array_chunk($contacts, $this->limit);
    foreach ($contacts as &$bulk) {
      $this->curl_post('contact.php', $bulk);

      $bulk = NULL;
    }
  }

  public function curl_post($url, $query, $json = FALSE) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->domain . $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json ? json_encode($query) : http_build_query($query));
    curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
    if ($json) {
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
      ));
    }

    $result = curl_exec($ch);
    curl_close($ch);

    $result = $this->_process_request($result);

    if (!isset($result['code'])) {
      throw new Exception('Something went wrong with the request.');
    }
    elseif ((int) $result['code'] === 101) {
      return TRUE;
    }
    else {
      throw new Exception('Http request error ' . $result['message']);
    }
  }

  private function _history_data_to_array(&$values) {
    $matches = array();
    preg_match_all('/([a-z0-9_]+)=([^=]+);/', $values, $matches);
    $values = array_combine($matches[1], $matches[2]);
  }

  private function _process_request(&$curl_result) {
    return json_decode($curl_result, true);
  }

  public function set_domain($domain) {
    $this->domain = 'https://' . $domain . '.sendsmaily.net/api/';
  }

  public function round_time($strtotime, $minutes = 15) {
    date_default_timezone_set('UTC');

    $seconds = $minutes * 60;
    $dateTimeZoneTallinn = new DateTimeZone('Europe/Tallinn');
    $dateTimeTallinn = new DateTime($strtotime, $dateTimeZoneTallinn);
    $tallinnOffset = $dateTimeZoneTallinn->getOffset($dateTimeTallinn);

    $tallinnDateTime = strtotime($strtotime) + $tallinnOffset;

    if ($minutes) {
      return floor($tallinnDateTime / $seconds) * $seconds;
    }

    return $tallinnDateTime;
  }
}
