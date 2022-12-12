<?php

$hb = microtime(true);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// loading config.php sets up the paywall and the billing account for the currently logged-in user
require_once(__DIR__."/../models/config.php");

// User must be logged in
if (!isUserLoggedIn()){
    header("Location: /login.php");
    exit();
}

//
global $billingAccount, $stats;

$start = t();

if ( $billingAccount ) {
    // display the billing section
    $pageView = new LcBillingView($billingAccount);
    $pageView->displayBillingSection();
}   else {
    // display the 'no billing account' message
    PayWallContent::start();
    PayWallContent::noAccount();
    PayWallContent::end();
}

$stats["view-loading"] = t($start);
$stats["full-page"] = t($hb);

?>