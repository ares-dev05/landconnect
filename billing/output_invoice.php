<?php

defined('SIMPLE_BILLING_LOAD') or
    define('SIMPLE_BILLING_LOAD', true);

// loading config.php sets up the paywall and the billing account for the currently logged-in user
require_once(__DIR__."/../models/config.php");
global $billingAccount;

if ( $billingAccount ) {
    $vid    = requiredGetVar("id");
    $type   = requiredGetVar("type");
    $format = requiredGetVar("format");

    // fetch the invoice by type and ID
    if ( $vid && $vid > 0 && $type && $format ) {
        if ( $type == LcSubscriptionInvoice::typeName() ) {
            // we're trying to display a subscription invoice
            $invoice = LcSubscriptionInvoice::byDBId( $vid );

            // make sure the invoice belongs to this account
            if ( $invoice->getAccountId() != $billingAccount->getId() ) {
                $invoice = null;
            }
        }   else
        if ( $type == LcInvoice::typeName() ) {
            // try to fetch the invoice from the current billing account
            // $invoice = $billingAccount->findInvoice($vid, false);
            $invoice = $billingAccount->loadSingleInvoice( $vid );
        }
        
        // output the invoice in the specified format
        if ( isset($invoice) && $invoice->isValid() ) {
            LcInvoiceOutput::outputByFormat( $invoice, $format, $billingAccount );
            // if we got to this point, there is a resolution in any case, so we exit the script
            exit;
        }
    }
}

// invoice not found
addAlert('warning', 'Invoice not found');
header('Location:.');

?>