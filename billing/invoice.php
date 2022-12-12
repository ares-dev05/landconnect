<?php

require_once(__DIR__."/../models/config.php");

global $billingAccount;

if ( $billingAccount ) {
    // check that the invoice exists
    $vid = requiredGetVar("id");
    if ($vid && $vid > 0) {
        // $invoice = $billingAccount->findInvoice( $vid, false );
        $invoice = $billingAccount->loadSingleInvoice($vid);

        if ( $invoice && $invoice->isValid()) {
            $pageView = new LcBillingView( $billingAccount );
            $pageView->displayInvoice($invoice);
            exit;
        }
    }
}

// invoice not found
addAlert('warning', 'Invoice not found');
header('Location:.');

?>