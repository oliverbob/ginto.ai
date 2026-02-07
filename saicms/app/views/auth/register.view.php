<?php
require_once CONFV_PATH.'/loader.php';

use Medoo\Medoo;

// Load the Medoo instance
$db = require CONFV_PATH . '/medoo/mdcreds.php';

// // Now use the DBConnect wrapper
// $test = new DBConnect($db);
// echo $test->checkConnection();


if (isset($_SESSION['user'])) { // redirect when user session is active.
    // echo "<pre>".print_r($_SESSION, 1)."</pre>";
    header('Location: /');
    exit;
} 

// list_dir_contents(__DIR__ . '/../../../public/vendor/iconcaptcha/loader.php');
// exit;

$oldEmail = $_SESSION['old_email'] ?? '';
unset($_SESSION['old_email']);

$error = $_SESSION['error'] ?? null;
unset($_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!IconCaptcha::validateSubmission($_POST)) {
        $_SESSION['error'] = 'Captcha verification failed.';
        $_SESSION['old_email'] = $_POST['email'] ?? '';
        // header('Location: /login');
        exit;
    }
    // Proceed with login credentials check
}
    

// If the form has been submitted, validate the captcha.
if(!empty($_POST)) {

      // Load the IconCaptcha options.
      // $options = require __DIR__ . '/../captcha-config.php';
      $options = require CONFV_PATH.'/ic_config.php';

      // Create an instance of IconCaptcha.
      $captcha = new \IconCaptcha\IconCaptcha($options);

      // Validate the captcha.
      $validation = $captcha->validate($_POST);

      // Confirm the captcha was validated.
      if($validation->success()) {
          $captchaMessage = 'The form has been submitted!';
      } else {
          $captchaMessage = 'Validation failed with error code: ' . $validation->getErrorCode();
      }
  }

require_once('login.php');

?>
