<?php

error_reporting(E_ALL);

// disable walling on this page so that we can offer the required subscription pages
require_once(__DIR__."/../classes/LcPayWall.php");
LcPayWall::$enableWalling = false;

require_once(__DIR__."/../../models/config.php");
// require_once("init.php");

define("debug_billing", true);

function fetchVar( $varName, $required=true ) {
    $value = null;
    if ( debug_billing )
        $value = requiredGetVar($varName);
    if ( !$value )
        $value = requiredPostVar($varName);
    if ( !$value && $required ) {
        Config::apiError( debug_billing ? "missing parameter $value" : "unknown error" );
        exitWithError();
    }

    return $value;
}

// set this as an api call
$isApiCall = true;
$method    = null;

// initialize the billing account for the currently logged-in user
global $loggedInUser, $apiErrors;

if ( !$loggedInUser ) {
    Config::apiError( "No logged-in user" );
    exitWithError();
}

// fetch the billing account for the currently logged-in user
$account = LcAccount::getForSelf( $loggedInUser, true );
if ( !$account ) {
    Config::apiError( "No billing account associated." );
    exitWithError();
}

// fetch the api method
$method = fetchVar( "method" );

// execute the indicated API method
$result = null;
switch ($method) {
    case "getcustomer":
        $result = array(
            "isSubscribed"  => $account->isSubscribed(),
            "clientToken"   => $account->getClientToken()
        );
        break;

    case "subscription/create":
        // make sure the needed parameters are present
        $nonce = fetchVar("payment_method_nonce");
        // the subscription plan is optional
        $plan  = fetchVar("subscription_plan", false);

        // run the subscribe
        $subscription = $account->subscribeToPlan( $nonce, $plan );

        if ( $subscription ) {
            $result = array(
                "subscription"  => $subscription
            );
        }
        break;

    case "subscription/retry":
        // make sure the needed parameters are present
        $nonce = fetchVar("payment_method_nonce");

        // run the payment retry
        if ( $account->retrySubscriptionPayment( $nonce ) ) {
            $result = array(
                "subscription"  => $account->getSubscription()
            );
        }

        break;

    case "subscription/setpaymentmethod":
        // make sure the needed parameters are present
        $nonce = fetchVar("payment_method_nonce");

        // set the new payment method
        if ( $account->editSubscriptionPaymentMethod( $nonce ) ) {
            $result = array(
                "success"   => true
            );
        }

        break;

    case "invoice/pay":
        $invoiceId = fetchVar("invoice_id");

        // pay the indicated invoice
        if ( $account->payInvoice( $invoiceId ) ) {
            $result = array(
                "success"   => true
            );
        }
        break;

    // when making payments as a portal user, the payment methods are loaded into the account

    default:
        $apiErrors[] = "Unknown command $method";
        break;
}

if ( $result ) {
    apiSuccess($result);
}   else {
    apiError();
}

function apiError( $echoErrors=true )
{
    global $apiErrors;

    $output = array("success"=>false);
    if ($echoErrors)
        $output["errors"] = $apiErrors;

    echo json_encode($output);
    exit;
}

function apiSuccess( $data=null )
{
    echo json_encode(array(
        "success" => true,
        "data" => $data
    ));
    exit();
}

?>
