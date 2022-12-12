<?php
    // Braintree Webhook processor
    require_once(__DIR__."/../models/config.php");
    require_once("init.php");

    /* Braintree Webhook Verification; needed only to confirm the URL
    if ( isset($_GET["bt_challenge"]) ) {
        echo(Braintree_WebhookNotification::verify($_GET["bt_challenge"]));
    } */

    // For 4.3.0 <= PHP <= 5.4.0
    if (!function_exists('http_response_code'))
    {
        function http_response_code($newcode = NULL)
        {
            static $code = 200;
            if($newcode !== NULL)
            {
                header('X-PHP-Response-Code: '.$newcode, true, $newcode);
                if(!headers_sent())
                    $code = $newcode;
            }
            return $code;
        }
    }

    // Braintree Webhook Processing
    if ( isset($_POST["bt_signature"]) && isset($_POST["bt_payload"]) ) {
        $webhookNotification = Braintree_WebhookNotification::parse(
            $_POST["bt_signature"], $_POST["bt_payload"]
        );

        // check if this is a subscription notification
        if ( stripos( $webhookNotification->kind, "subscription" ) !== FALSE ) {
            $responseCode = 200;
            $prefix="";
            if ( WebhookProcessor::parseWebhookNotification($webhookNotification) )  {
                // return a 200 response
                $responseCode = 200;
                $prefix = "Processed";
            }   else {
                // return an error; the webhook will be retried at a later time
                $responseCode = 403;
                $prefix = "Unprocessed";
            }

            // log the webhook information
            $message =
                "[{$prefix} Webhook Received " . $webhookNotification->timestamp->format('Y-m-d H:i:s') . "] "
                . "Kind: " . $webhookNotification->kind . " | "
                . "Subscription: " . $webhookNotification->subscription->id . " = " . $webhookNotification->subscription->status . "\n";
            file_put_contents(__DIR__."/log/webhook.log", $message, FILE_APPEND);

            http_response_code( $responseCode );
        }

        if ( $webhookNotification->kind == "check" ) {
            // run a simple test
            $sampleNotification = Braintree_WebhookTesting::sampleNotification(
                Braintree_WebhookNotification::SUBSCRIPTION_CHARGED_SUCCESSFULLY,
                // test $1 subscription
                'g32y76'
            );

            $webhookNotification = Braintree_WebhookNotification::parse(
                $sampleNotification['bt_signature'],
                $sampleNotification['bt_payload']
            );

            if ( WebhookProcessor::parseWebhookNotification($webhookNotification) )  {
                // return a 200 response
                $responseCode = 200;
                $prefix = "Processed";
            }   else {
                // return an error; the webhook will be retried at a later time
                $responseCode = 403;
                $prefix = "Unprocessed";
            }

            http_response_code( $responseCode );
        }
    }
?>