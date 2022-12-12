<?php

// disable walling on this page so that we can offer the required subscription pages
require_once("./classes/LcPayWall.php");
LcPayWall::$enableWalling = false;

require_once(__DIR__."/../models/config.php");
// require_once("init.php");


if ( !isset( $_GET['action'] ) ) {
    // @TODO: just display something here so we don't have a frame in a frame in a frame
    header('Location:index.php');
    exit;
}   else {
    $action = $_GET['action'];
}

// proxy used for loading a certain feature of the paywall through ajax
global $payWall;

// make sure billing is enabled for the current account before moving on
if ( !$payWall ) {
    // display the 'no billing account' message
    PayWallContent::start();
    PayWallContent::noAccount();
    PayWallContent::end();
    exit;
}

// the Paywall exits the page if there is something wrong with the subscription; otherwise, we get here
switch ( $action ) {
    case "create-subscription":
        $payWall->createSubscriptionForm();
        break;

    case "manage-subscription":
        $payWall->editSubscriptionForm();
        break;

    case "pay-invoice":
        // load the invoice details
        $iid = requiredGetVar( "invoice" );
        
        if ( $iid ) {
            $invoice = new LcInvoice( $iid );
            if ( $invoice->isValid() ) {
                $payWall->payInvoiceForm( $invoice );
                exit;
            }
        }

        $payWall->invoiceNotFound();
        break;
    // @TODO: what other features does the paywall need to offer?
}

?>