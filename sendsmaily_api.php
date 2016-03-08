<?php
/**
 * @file Class for Sendsmaily API requests.
 */

class sendsmaily
{
  private $username;
  private $password;
  private $domain;
  private $email;

  private $errors = array();

  public function __construct($username, $password, $domain, $email) {
    $this->username = $username;
    $this->password = $password;

    $this->domain = 'https://' . $domain . '.sendsmaily.net/api/';

    $this->email = $email;

    if (isset($_POST) && isset($_POST['smly'])) {
      $this->form_submit($_POST['smly']);
    }
  }

  public function form_submit($values) {
    if (!isset($values['action'])) {
      $this->_error('Action has not been chosen.');
    }
    if ($values['action'] === 'change_email') {
      echo '<pre>'.print_r($values, true).'</pre>';
    }
    elseif ($values['action'] === 'receive_frequency') {
      echo '<pre>'.print_r($values, true).'</pre>';
    }
    elseif ($values['action'] === 'unsubscribe') {
      $this->_unsubscribe(
        $values['campaign_id'],
        $values['unsubscribe'],
        $values['unsubscribe_comment'][$values['unsubscribe']]
      );
    }
    else {
      $this->_error('Form elements does not exist.');
    }
  }

  /**
   * FORM HTML.
   */

  public function form() {
    if (!empty($this->errors)) {
      return implode('<br />', $this->errors);
    }
    else {
      return
        '<form action="' . $this->_request_uri() . '" method="post" style="height:100%;" id="smly-form">' .
          '<input type="hidden" name="smly[campaign_id]" value="' . $_GET['campaign_id'] . '" />' .
          $this->_edit_form() .
          '<input type="submit" value="Edasta" />' .
        '</form>';
    }
  }

  private function _edit_form() {
    return '<ul class="smly_actions">' .
      '<li>' .
        '<input type="radio" name="smly[action]" value="change_email" id="smly_change_email">&nbsp;' .
        '<label for="smly_change_email">Soovin muuta oma emaili aadressit. ('.$_GET['email'].')</label>' .
        $this->_change_email_form() .
      '</li><li>' .
        '<input type="radio" name="smly[action]" value="receive_frequency" id="smly_receive_frequency">&nbsp;' .
        '<label for="smly_receive_frequency">Muuda uudiskirja saamise sagedust</label>' .
        $this->_frequency_form() .
      '</li><li>' .
        '<input type="radio" name="smly[action]" value="unsubscribe" id="smly_unsubscribe">&nbsp;' .
        '<label for="smly_unsubscribe">Loobu uudiskirjast</label>' .
        $this->_unsubscribe_form() .
      '</li>' .
    '</ul>';
  }

  private function _change_email_form() {
    return '<ul class="smly_change_email">' .
      '<li>' .
        '<label for="smly_change_email">Sisestage uus emaili aadress</label>' .
        '<input name="smly[change_email]" type="text" value="" />' .
      '</li>' .
    '</ul>';
  }

  private function _unsubscribe_form() {
    return '<ul class="smly_action_unsubscribe">' .
      '<li>' .
        '<input type="radio" name="smly[unsubscribe]" value="4" id="unsubscribe_reason_4">&nbsp;' .
        '<label for="unsubscribe_reason_4">Liiga sage saatmistihedus</label>' .
        '<textarea name="smly[unsubscribe_comment][4]" rows="4" style="width:100%" placeholder="Kirjutage paari sõnaga oma otsusest"></textarea>' .
      '</li><li>' .
        '<input type="radio" name="smly[unsubscribe]" value="3" id="unsubscribe_reason_3">&nbsp;' .
        '<label for="unsubscribe_reason_3">Sisaldab ainult müügipakkumisi</label>' .
        '<textarea name="smly[unsubscribe_comment][3]" rows="4" style="width:100%" placeholder="Kirjutage paari sõnaga oma otsusest"></textarea>' .
      '</li><li>' .
        '<input type="radio" name="smly[unsubscribe]" value="2" id="unsubscribe_reason_2">&nbsp;' .
        '<label for="unsubscribe_reason_2">Ebahuvitav sisu</label>' .
        '<textarea name="smly[unsubscribe_comment][2]" rows="4" style="width:100%" placeholder="Kirjutage paari sõnaga oma otsusest"></textarea>' .
      '</li><li>' .
        '<input type="radio" name="smly[unsubscribe]" value="1" id="unsubscribe_reason_1">&nbsp;' .
        '<label for="unsubscribe_reason_1">Muu</label>' .
        '<textarea name="smly[unsubscribe_comment][1]" rows="4" style="width:100%" placeholder="Kirjutage paari sõnaga oma otsusest"></textarea>' .
      '</li>' .
    '</ul>';
  }

  private function _frequency_form() {
    return '<ul class="smly_action_frequency">' .
      '<li>' .
        '<input type="radio" name="smly[frequency]" value="3" id="smly_frequency_3">&nbsp;' .
        '<label for="smly_frequency_3">Ilma piiranguteta</label>' .
      '</li><li>' .
        '<input type="radio" name="smly[frequency]" value="2" id="smly_frequency_2">&nbsp;' .
        '<label for="smly_frequency_2">Mitte saata rohkem kui üks kord kuus</label>' .
      '</li><li>' .
        '<input type="radio" name="smly[frequency]" value="1" id="smly_frequency_1">&nbsp;' .
        '<label for="smly_frequency_1">Mitte saata rohkem kui üks kord kolme kuu jooksul</label>' .
      '</li>' .
    '</ul>';
  }

  /**
   * CURL COMMANDS.
   */

  public function _unsubscribe($campaign_id, $reason, $reason_other) {
    $loc = $this->domain . 'contact.php';
    $query = array(
      'email' => $this->email,
      'unsubscribe_reason' => $reason,
      'unsubscribe_reason_other' => $reason_other,
    );
    $reason = $this->_curl($loc, $query);

    $loc = $this->domain . 'unsubscribe.php';
    $query = array(
      'email' => $this->email,
      'campaign_id' => $campaign_id,
    );
    $unsubscribe = $this->_curl($loc, $query);

    return $reason && $unsubscribe;
  }

  /**
   * HELPER FUNCTIONS.
   */

  private function _curl($url, $query) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($query));
    curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);

    $result = curl_exec($ch);
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

  private function _request_uri() {
    if (isset($_SERVER['REQUEST_URI'])) {
      $uri = $_SERVER['REQUEST_URI'];
    }
    else {
      if (isset($_SERVER['argv'])) {
        $uri = $_SERVER['SCRIPT_NAME'] . '?' . $_SERVER['argv'][0];
      }
      elseif (isset($_SERVER['QUERY_STRING'])) {
        $uri = $_SERVER['SCRIPT_NAME'] . '?' . $_SERVER['QUERY_STRING'];
      }
      else {
        $uri = $_SERVER['SCRIPT_NAME'];
      }
    }
    // Prevent multiple slashes to avoid cross site requests via the Form API.
    $uri = '/' . ltrim($uri, '/');

    return $uri;
  }

  private function _error($msg) {
    $errors[] = $msg;
  }
}
