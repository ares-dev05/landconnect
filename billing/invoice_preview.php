<?php

require_once(__DIR__."/../models/config.php");
global $loggedInUser;

if ( !$loggedInUser && !isGlobalAdmin($loggedInUser->user_id) ) {
    header('HTTP/1.0 404 Not Found', true, 404);
    exit;
}

// check that the invoice exists
$vid = requiredGetVar("id");

if ($vid && $vid > 0) {
    // just load the invoice
    $invoice = new LcInvoice($vid);

    if ( $invoice && $invoice->isValid()) {
        // create the account for this invoice
        $account = new LcAccount($invoice->getAccountId(), $loggedInUser);

        $pageView = new LcBillingView($account);
        $pageView->displayInvoice($invoice);
        exit;
    }
}

echo "Invoice can't be loaded at the moment.";

?>