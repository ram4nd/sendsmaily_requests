<?php
/**
 * @file Class for Smaily unsubscription.
 *
 * @author Ra Mänd <ram4nd@gmail.com>
 *
 * Example:
 * require_once 'smaily_unsubscribe.php';
 * $smly = new smaily('USERNAME', 'PASSWORD', 'CLIENT');
 *
 * echo $smly->head();
 *
 * echo $smly->content();
 */

require_once '../smly_requests.php';

class smly_unsubscribe extends smly
{
  private $options = array(
    'form_prefix' => '',
    'forms' => array(
      'frequency' => array(),
      'unsubscribe' => array(
        'reasons' => array(
          array(
            'label' => 'Reason 1',
          ),
          array(
            'label' => 'Reason 2',
          ),
          array(
            'label' => 'Reason 3',
            'text' => true,
          ),
        ),
        'unsubscribe_success' => 'Loobumine uudiskirjast õnnestus',
      ),
      'change_email' => array(),
      'cancel' => array(),
    ),
    'submit_button' => '<input type="submit" value="Submit" />',
  );

  public $forms_count = 0;

  public $frequency_form = '';
  public $unsubscribe_form = '';
  public $change_email_form = '';
  public $cancel_form = '';

  private $html = '';

  public function __construct($username, $password, $domain, $options) {
    parent::__construct($username, $password, $domain);

    $this->options = array_merge($this->options, $options);

    foreach ($this->options['forms'] as $option) {
      if ($option !== FALSE) {
        ++$this->forms_count;
      }
    }

    $this->unsubscribe_form = $this->_unsubscribe_form();
    $this->change_email_form = $this->_change_email_form();

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
        $this->options['form_prefix'] .
        '<form action="' . $this->_request_uri() . '" method="post" style="height:100%;" id="smly-form">' .
          $this->_edit_form() .
          $this->options['submit_button'] .
        '</form>';
    }

    return $this->html;
  }

  public function form_submit($values) {
    if (!isset($values['action']) && !empty($values['action'])) {
      $this->_error('Action has not been chosen.');
    }

    if ($values['action'] === 'cancel') {
      header('Location: ' . $this->options['forms']['cancel']);
    }
    elseif ($values['action'] === 'change_email') {
      if ($this->_change_email($values['change_email'])) {
        $this->html .= $this->_success_html($this->form_strings['change_success']);
      }
    }
    elseif ($values['action'] === 'receive_frequency') {
      if ($this->_frequency()) {
        $this->html .= $this->_success_html($this->form_strings['frequency_success']);
      }
    }
    elseif ($values['action'] === 'unsubscribe') {
      if (isset($_GET['campaign_id']) && !empty($_GET['campaign_id'])) {

        if ($this->_unsubscribe(
          $values['campaign_id'],
          $values['unsubscribe'],
          ((int) $values['unsubscribe'] === 1 ? $values['unsubscribe_comment'] : '')
        )) {
          $this->html .= $this->_success_html($this->form_strings['unsubscribe_success']);
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

  public function head() {
    return '<script src="//cdn.jsdelivr.net/jquery/2.2.4/jquery.min.js"></script>';

    // Styles.
    $return = '<style type="text/css">
      ol,ul{list-style:none;margin:0}
      li{margin:2px 0 5px 3px;}
      #smly-form textarea{
        width: 450px;
        padding: 5px;
        margin: 10px 0 0 25px;
      }
    </style>';

    $return .= '<script src="//cdn.jsdelivr.net/jquery/2.2.4/jquery.min.js"></script>
      <script type="text/javascript">(function($){$().ready(function(){
        var $smly_form = $("#smly-form");

        // Actions.
        var $smly_unsubscribe_options = $("ul.smly_action_unsubscribe", $smly_form);
        var $smly_action_frequency = $("ul.smly_action_frequency", $smly_form);
        var $smly_change_email = $("ul.smly_change_email", $smly_form);
        $smly_unsubscribe_options.hide();
        $smly_action_frequency.hide();
        $smly_change_email.hide();
        $("input[name=\'smly[action]\']", $smly_form).change(function(){
          // Unsubscribe.
          if ($(this).val() == "unsubscribe"){
            $smly_unsubscribe_options.show();
          }
          else {
            $smly_unsubscribe_options.hide();
          }
          // Frequency.
          if ($(this).val() == "receive_frequency") {
            $smly_action_frequency.show();
          }
          else {
            $smly_action_frequency.hide();
          }
          // Change email.
          if ($(this).val() == "change_email") {
            $smly_change_email.show();
          }
          else {
            $smly_change_email.hide();
          }
        });

        // Unsubscribe.
        $smly_unsubscribe_options.find("textarea").hide();
        $(\'input[name="smly[unsubscribe]"]\', $smly_form).change(function(){
          if (this.id == "unsubscribe_reason_1") {
            $smly_unsubscribe_options.find("textarea").show();
          }
          else {
            $smly_unsubscribe_options.find("textarea").hide();
          }
        });

      });})(jQuery);</script>';

    return $return;
  }

  /**
   * FORM HTML.
   */

  private function _edit_form() {
    if ($this->forms_count === 1) {
      return
        ($this->options['forms']['change_email'] ? $this->_change_email_form() : '') .
        ($this->options['forms']['frequency'] ? '' : '') .
        ($this->options['forms']['unsubscribe'] ? $this->_unsubscribe_form() : '') .
        ($this->options['forms']['cancel'] ? '' : '');
    }

    return '<ul class="smly_actions" style="padding-left:0">' .
      ($this->options['forms']['change_email'] ?
        '<li>' .
          '<input type="radio" name="smly[action]" value="change_email" id="smly_change_email">&nbsp;' .
          '<label for="smly_change_email">' . $this->form_strings['change_label'] . '. ('.$_GET['email'].')</label>' .
          $this->_change_email_form() .
        '</li>'
      :'').
      ($this->options['forms']['frequency'] ?
        '<li>'.
          '<input type="radio" name="smly[action]" value="receive_frequency" id="smly_receive_frequency">&nbsp;' .
          '<label for="smly_receive_frequency">' . $this->form_strings['frequency_label'] . '</label>' .
        '</li>'
      :'').
      ($this->options['forms']['unsubscribe'] ?
        '<li>' .
          '<input type="radio" name="smly[action]" value="unsubscribe" id="smly_unsubscribe">&nbsp;' .
          '<label for="smly_unsubscribe">' . $this->form_strings['unsubscribe_label'] . '</label>' .
          $this->_unsubscribe_form() .
        '</li>'
      :'').
      ($this->options['forms']['cancel'] ?
        '<li>' .
          '<input type="radio" name="smly[action]" value="cancel" id="smly_cancel">&nbsp;' .
          '<label for="smly_cancel">' . $this->form_strings['cancel_label'] . '</label>' .
        '</li>'
      :'').
    '</ul>';
  }

  private function _change_email_form() {
    return '<ul class="smly_change_email">' .
      '<li>' .
        '<label for="smly_change_email">'.$this->form_strings['change_placeholder'].'</label>&nbsp;' .
        '<input name="smly[change_email]" type="text" value="" />' .
      '</li>' .
    '</ul>';
  }

  private function _unsubscribe_form() {
    $return = '<input type="hidden" name="smly[campaign_id]" value="' . $_GET['campaign_id'] . '" />' .
      '<script type="text/javascript">(function($){$().ready(function(){' .
        '$("input[type=\'radio\']").change(function(){' .
          '$(this).parent().parent().find("textarea").hide();' .
          '$(this).parent().find("textarea").show();' .
        '});' .
      '});})(jQuery);</script>';

    foreach ($this->options['forms']['unsubscribe']['reasons'] as $key => $reason) {
      $return .= '<p>' .
        '<input type="radio" name="smly[unsubscribe]" value="' . $key . '" id="unsubscribe_reason_' . $key . '" style="position:absolute;z-index:99">' .
        '<label for="unsubscribe_reason_' . $key . '" style="display:block;padding-left:30px">' . $reason['label'] . '</label>' .
        ($reason['text'] ? '<textarea name="smly[unsubscribe_comment]" rows="4" style="display:none"></textarea>' : '') .
      '</p>';
    }

    return '<div class="smly_action_unsubscribe">' . $return . '</div>';
  }

  /**
   * CURL COMMANDS.
   */

  public function _change_email($new_email) {
    $loc = 'contact.php';

    $contact = $this->curl_get($loc, array('email' => $_GET['email']));
    $contact['email'] = $new_email;
    $contact['is_unsubscribed'] = 0;
    $create = $this->curl_post($loc, $contact);

    $query = array(
      'email' => $_GET['email'],
      'is_unsubscribed' => 1,
    );
    $unsubscribed = $this->curl_post($loc, $query);

    $_GET['email'] = $new_email;

    return $contact && $unsubscribed && $create;
  }

  public function _frequency() {
    $loc = 'contact.php';
    $query = array(
      'email' => $_GET['email'],
      'weekly' => 1,
    );
    return $this->curl_post($loc, $query);
  }

  public function _unsubscribe($campaign_id, $reason, $reason_other) {
    if (!isset($_GET['campaign_id'])) {
      $this->_error('Campaign id is missing.');
    }

    $loc = 'contact.php';
    $query = array(
      'email' => $_GET['email'],
      'unsubscribe_reason' => $reason,
    );
    if (!empty($reason_other)) {
      $query['unsubscribe_reason_other'] = $reason_other;
    }
    $reason = $this->curl_post($loc, $query);
    $loc = 'unsubscribe.php';
    $query = array(
      'email' => $_GET['email'],
      'campaign_id' => $campaign_id,
    );
    $unsubscribe = $this->curl_post($loc, $query);

    return $reason && $unsubscribe;
  }

  /**
   * HELPER FUNCTIONS.
   */

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

  protected function _error($msg) {
    $this->errors[] = $this->_error_html(date('d.m.Y H:i:s') . ': ' . $msg);
  }

  private function _success_html($msg) {
    return '<p style="padding:15px;background:#dff0d8;margin:0 0 10px;">' . $msg . '</p>';
  }
  private function _error_html($msg) {
    return '<p style="padding:15px;background:#f2dede;margin:0 0 10px;">' . $msg . '</p>';
  }
}
