<?php
    // Include the IconCaptcha classes.
    require_once __DIR__ . '/../../vendor/autoload.php';

    // Start a session.
    // * Required when using any 'session' driver in the configuration.
    // * Required when using the IconCaptcha Token, referring to the use of 'IconCaptchaToken' in the form below.
    // For more information, please refer to the documentation.
    session_start();

    // If the form has been submitted, validate the captcha.
    if(!empty($_POST)) {

        // Load the IconCaptcha options.
        $options = require __DIR__ . '/../captcha-config.php';

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
?>
<!DOCTYPE HTML>
<html lang="en">
    <head>
        <title>IconCaptcha v4.0.5 - By Fabian Wennink</title>
        <meta charset="UTF-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=11" />
        <meta name="author" content="Fabian Wennink © <?= date('Y') ?>" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
		<link href="../assets/favicon.ico" rel="shortcut icon" type="image/x-icon" />

        <!-- JUST FOR THE DEMO PAGE -->
        <link href="../assets/demo.css" rel="stylesheet" type="text/css">
        <script src="../assets/demo.js" type="text/javascript"></script>
        <link href="https://fonts.googleapis.com/css?family=Poppins:400,600,700" rel="stylesheet">

        <!-- Include IconCaptcha stylesheet - REQUIRED -->
        <link href="../../client/css/iconcaptcha.min.css" rel="stylesheet" type="text/css">
    </head>
    <body>
        <div class="container">

            

            <div class="section">

                <?php
                    if(isset($captchaMessage)) {
                        echo '<b>Captcha Message: </b>' . $captchaMessage;
                    }
                ?>

                <!-- The IconCaptcha holder should ALWAYS be placed WITHIN the <form> element -->
                <form action="" method="post">

                    <!-- Additional security token to prevent CSRF. -->
                    <!-- Optional, but highly recommended - disable via IconCaptcha options. -->
                    <!-- Note: using the default IconCaptcha Token class? Make sure to start a PHP session. -->
                    <?= \IconCaptcha\Token\IconCaptchaToken::render() ?>

                    <!-- The IconCaptcha widget will be rendered in this element - REQUIRED -->
                    <div class="iconcaptcha-widget" data-theme="light"></div>

                    <!-- Submit button to test your IconCaptcha input -->
                    <input type="submit" value="Submit demo captcha" class="btn btn-invert">
                </form>

                <!-- Theme selector - JUST FOR THE DEMO PAGE -->
                <div class="themes">
                    <div class="theme theme--light"><span data-theme="light"></span><span>Light Theme</span></div>
                    <div class="theme theme--dark"><span data-theme="dark"></span><span>Dark Theme</span></div>
                </div>
                <small class="smaller">- The theme selector only works when no challenge has been rendered yet -</small>
            </div>

           
        </div>

        
        
        <!-- Include IconCaptcha script - REQUIRED -->
        <script src="../../client/js/iconcaptcha.min.js" type="text/javascript"></script>

        <!-- Initialize the IconCaptcha - REQUIRED -->
        <script type="text/javascript">

            // Note: jQuery can be used as well. Check the README.md for more information.

            document.addEventListener('DOMContentLoaded', function () {

                // Check the README.md for information about the options.
                IconCaptcha.init('.iconcaptcha-widget', {
                    general: {
                        endpoint: '../captcha-request.php',
                        fontFamily: 'inherit',
                    },
                    security: {
                        interactionDelay: 1000,
                        hoverProtection: true,
                        displayInitialMessage: true,
                        initializationDelay: 500,
                        incorrectSelectionResetDelay: 3000,
                        loadingAnimationDuration: 1000,
                    },
                    locale: {
                        initialization: {
                            verify: 'Verify that you are human.',
                            loading: 'Loading challenge...',
                        },
                        header: 'Select the image displayed the <u>least</u> amount of times',
                        correct: 'Verification complete.',
                        incorrect: {
                            title: 'Uh oh.',
                            subtitle: "You've selected the wrong image."
                        },
                        timeout: {
                            title: 'Please wait.',
                            subtitle: 'You made too many incorrect selections.'
                        }
                    }
                })
                // .bind('init', function(e) { // You can bind to custom events, in case you want to execute custom code.
                //     console.log('Event: Captcha initialized', e.detail.captchaId);
                // }).bind('selected', function(e) {
                //     console.log('Event: Icon selected', e.detail.captchaId);
                // }).bind('refreshed', function(e) {
                //     console.log('Event: Captcha refreshed', e.detail.captchaId);
                // }).bind('invalidated', function(e) {
                //     console.log('Event: Invalidated', e.detail.captchaId);
                // }).bind('reset', function(e) {
                //     console.log('Event: Reset', e.detail.captchaId);
                // }).bind('success', function(e) {
                //     console.log('Event: Correct input', e.detail.captchaId);
                // }).bind('error', function(e) {
                //     console.log('Event: Wrong input', e.detail.captchaId);
                // });
            });
        </script>
    </body>
</html>
