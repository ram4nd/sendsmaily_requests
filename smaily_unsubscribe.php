<?php
/**
 * @file Class for Smaily unsubscription.
 *
 * @author Ra Mänd <ram4nd@gmail.com>
 * @link http://browse-tutorials.com/
 *
 * Example:
 * require_once 'smaily_unsubscribe.php';
 * $user = '';
 * $pass = '';
 * $smly = new smaily($user, $pass, 'client');
 *
 * echo $smly->content();
 */

class smaily_unsubscribe
{
  private $username;
  private $password;
  private $domain;

  private $errors = array();
  private $html = '';

  public $form_prefix = '';

  public function __construct($username, $password, $domain) {
    $this->username = $username;
    $this->password = $password;

    $this->domain = 'https://' . $domain . '.sendsmaily.net/api/';

    if (!isset($_GET['email'])) {
      $this->_error('Email is missing.');
    }

    if (isset($_POST) && isset($_POST['smly'])) {
      $this->form_submit($_POST['smly']);
    }
  }

  public function content() {

    if (!empty($this->errors)) {
      $this->html .= implode('<br />', $this->errors);
    }
    elseif (empty($this->html)) {
      $this->html =
        $this->form_prefix .
        '<form action="' . $this->_request_uri() . '" method="post" style="height:100%;" id="smly-form">' .
          $this->_edit_form() .
          '<input type="submit" value="Edasta" />' .
        '</form>';
    }

    return $this->html;
  }

  public function form_submit($values) {
    if (!isset($values['action']) && !empty($values['action'])) {
      $this->_error('Action has not been chosen.');
    }

    if ($values['action'] === 'change_email') {
      if ($this->_change_email($values['change_email'])) {
        $this->html .= $this->_success_html('E-maili muutmine õnnestus');
      }
    }
    elseif ($values['action'] === 'receive_frequency') {
      if ($this->_frequency($values['frequency'])) {
        $this->html .= $this->_success_html('Saatmise sageduse muutmine õnnestus');
      }
    }
    elseif ($values['action'] === 'unsubscribe') {
      if (isset($_GET['campaign_id']) && !empty($_GET['campaign_id'])) {

        if ($this->_unsubscribe(
          $values['campaign_id'],
          $values['unsubscribe'],
          ((int)$values['unsubscribe'] === 1 ? $values['unsubscribe_comment'] : '')
        )) {
          $this->html .= $this->_success_html('Loobumine uudiskirjast õnnestus');
        }
      }
      else {
        $this->_error('Campaign id is missing.');
      }
    }
    else {
      $this->_error('Form elements does not exist.');
    }
  }

  /**
   * FORM HTML.
   */

  private function _edit_form() {
    return '<ul class="smly_actions">' .
      '<li>' .
        '<input type="radio" name="smly[action]" value="change_email" id="smly_change_email">&nbsp;' .
        '<label for="smly_change_email">Soovin muuta oma emaili aadressi. ('.$_GET['email'].')</label>' .
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
        '<label for="smly_change_email">Sisestage uus emaili aadress</label>&nbsp;' .
        '<input name="smly[change_email]" type="text" value="" />' .
      '</li>' .
    '</ul>';
  }

  private function _unsubscribe_form() {
    return '<input type="hidden" name="smly[campaign_id]" value="' . $_GET['campaign_id'] . '" />' .
      '<ul class="smly_action_unsubscribe">' .
        '<li>' .
          '<input type="radio" name="smly[unsubscribe]" value="4" id="unsubscribe_reason_4" checked="checked">&nbsp;' .
          '<label for="unsubscribe_reason_4">Liiga sage saatmistihedus</label>' .
        '</li><li>' .
          '<input type="radio" name="smly[unsubscribe]" value="3" id="unsubscribe_reason_3">&nbsp;' .
          '<label for="unsubscribe_reason_3">Sisaldab ainult müügipakkumisi</label>' .
        '</li><li>' .
          '<input type="radio" name="smly[unsubscribe]" value="2" id="unsubscribe_reason_2">&nbsp;' .
          '<label for="unsubscribe_reason_2">Ebahuvitav sisu</label>' .
        '</li><li>' .
          '<input type="radio" name="smly[unsubscribe]" value="1" id="unsubscribe_reason_1">&nbsp;' .
          '<label for="unsubscribe_reason_1">Muu</label>' .
          '<textarea name="smly[unsubscribe_comment]" rows="4" placeholder="Kirjutage paari sõnaga oma otsusest"></textarea>' .
        '</li>' .
      '</ul>';
  }

  private function _frequency_form() {
    return '<ul class="smly_action_frequency">' .
      '<li>' .
        '<input type="radio" name="smly[frequency]" value="3" id="smly_frequency_3" checked="checked">&nbsp;' .
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

  public function _change_email($new_email) {
    $loc = $this->domain . 'contact.php';

    $contact = $this->_curl_get($loc, array('email' => $_GET['email']));
    $contact['email'] = $new_email;
    $contact['is_unsubscribed'] = 0;
    $create = $this->_curl_post($loc, $contact);

    $query = array(
      'email' => $_GET['email'],
      'is_unsubscribed' => 1,
    );
    $unsubscribed = $this->_curl_post($loc, $query);

    $_GET['email'] = $new_email;

    return $contact && $unsubscribed && $create;
  }

  public function _frequency($receive_frequency) {
    $loc = $this->domain . 'contact.php';
    $query = array(
      'email' => $_GET['email'],
      'receive_frequency' => $receive_frequency,
    );
    return $this->_curl_post($loc, $query);
  }

  public function _unsubscribe($campaign_id, $reason, $reason_other) {
    if (!isset($_GET['campaign_id'])) {
      $this->_error('Campaign id is missing.');
    }

    $loc = $this->domain . 'contact.php';
    $query = array(
      'email' => $_GET['email'],
      'unsubscribe_reason' => $reason,
    );
    if (!empty($reason_other)) {
      $query['unsubscribe_reason_other'] = $reason_other;
    }
    $reason = $this->_curl_post($loc, $query);
    $loc = $this->domain . 'unsubscribe.php';
    $query = array(
      'email' => $_GET['email'],
      'campaign_id' => $campaign_id,
    );
    $unsubscribe = $this->_curl_post($loc, $query);

    return $reason && $unsubscribe;
  }

  /**
   * HELPER FUNCTIONS.
   */

  private function _curl_get($url, $query) {
    $query = urldecode(http_build_query($query));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '?' . $query);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);

    $result = curl_exec($ch);
    curl_close($ch);

    return $this->_process_request($result);
  }

  private function _curl_post($url, $query) {
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
    $this->errors[] = $this->_error_html($msg);
  }

  private function _success_html($msg) {
    return '<p style="padding:15px;background:#dff0d8;margin:0 0 10px;">' . $msg . '</p>';
  }
  private function _error_html($msg) {
    return '<p style="padding:15px;background:#f2dede;margin:0 0 10px;">' . $msg . '</p>';
  }
}
