<?php

// this file outputs an invoice for printing without requiring a logged-in account
// include the invoice pdf generator
include_once(__DIR__.'/classes/LcInvoiceOutput.php');

// validate that this is a bot user
if ( isset($_GET['vid']) && isset($_GET['type']) &&
     isset($_GET['key']) && $_GET['key'] == LcInvoiceOutput::GENERATE_KEY ) {

    // loading config.php sets up the paywall and the billing account for the currently logged-in user
    // we don't want to display any paywall so we capture the output
    ob_start();
    defined('BILLING_NO_INIT') OR define('BILLING_NO_INIT', true);
    require_once(__DIR__ . "/../models/config.php");
    require_once(__DIR__ . "/init.php");
    // end capturing the output
    ob_end_clean();

    $vid  = intval($_GET["vid"]);
    $type = $_GET["type"];

    if ($vid && $vid > 0) {
        /**
         * @var $invoice IInvoice
         */
        if ( $type == LcSubscriptionInvoice::typeName() ) {
            $invoice = LcSubscriptionInvoice::byDBId( $vid );
        }   else
        if ( $type == LcInvoice::typeName() ) {
            $invoice = LcInvoice::get( $vid );
        }   else {
            error_log("trying to generate invoice with unknown type: {$_GET['type']}");
        }

        if ($invoice && $invoice->isValid()) {
            // load an information-only billing account
            $account = new LcAccount( $invoice->getAccountId(), null, false, false );

            require_once(__DIR__ . "/classes/LcInvoiceFactory.php");
            LcInvoiceFactory::generate(
                $invoice,
                $account,
                false
            );

            exit;
        }   else {
            error_log("unable to load invoice");
        }
    }   else {
        error_log("invoice ID is unusable: {$vid}");
    }
}   else {
    error_log("missing values: vid=".$_GET['vid']."; type=".$_GET['type']."; key=".$_GET['key']);
}

error_log("failed generation on URL: "."https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");

// all other cases, 404
header(' ', true, 404);

?>