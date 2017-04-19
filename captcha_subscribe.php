<?php
/**
 * @file Post against this script to convert regular opt-in to captcha opt-in.
 *
 * @author Ra Mänd <ram4nd@gmail.com>
 *
 * How to use:
 *
 * 1. Get your captcha site and secret keys and write them to this file.
 *   // Secret key
 *   $secretKey = 'SECRET_KEY';
 *
 * 2. Put recaptcha snippet to your form tag.
 *      <script src="https://www.google.com/recaptcha/api.js" async defer></script>
 *      <div class="g-recaptcha" data-sitekey="SITE_KEY"></div>
 *
 * 3. Put your domain(DOMAIN.sendsmaily.net) to hidden input called domain.
 *      <input type="hidden" name="domain" value="DOMAIN">
 *
 * 4. Use this file instead of opt-in for post to create the check.
 *      <form action="http://example.com/my_dir/captcha_subscribe.php" method="post">
 *
 * @see http://help.smaily.com/en/support/solutions/articles/16000008835-an-example-of-a-signup-form
 */

// Secret key.
$secretKey = 'SECRET_KEY';

// Make domain (or write this to static and exclude from the form).
$domain = 'https://' . $_POST['domain'] . '.sendsmaily.net/api/opt-in/';

// Success or fail urls (or write this to static and exclude from the form).
$successUrl = $_POST['success_url'];
$failureUrl = $_POST['failure_url'];

// Validate captcha.
if(!isset($_POST['g-recaptcha-response']) or empty($_POST['g-recaptcha-response'])) {
  header('Location: ' . $failureUrl);
  exit;
}
$response = file_get_contents(
  'https://www.google.com/recaptcha/api/siteverify?secret=' . $secretKey . '&response=' . $_POST['g-recaptcha-response'] . '&remoteip=' . $_SERVER['REMOTE_ADDR']
);
$responseObj = json_decode($response);
if ($responseObj->success != true) {
  header('Location: ' . $failureUrl);
  exit;
}

// Forward the post.
if (isset($_POST['domain'])) unset($_POST['domain']);
if (isset($_POST['g-recaptcha-response'])) unset($_POST['g-recaptcha-response']);
if (isset($_POST['success_url'])) unset($_POST['success_url']);
if (isset($_POST['failure_url'])) unset($_POST['failure_url']);

// Send data to Smaily.
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $domain);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(
  array_merge(
    $_POST,
    array(
      'remote' => 1,
	  'success_url' => $successUrl,
	  'failure_url' => $failureUrl,
    )
  )
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);

$response = curl_exec($ch);
curl_close($ch);
if(strlen($response) > 0){
  $json = @json_decode($response);
  if (!isset($json->code) or $json->code > 200) {
    header('Location: ' . $failureUrl);
    exit;
  }

  header('Location: ' . $successUrl);
}
else {
  header('Location: ' . $failureUrl);
}
