<?php

$start = microtime(true);

// include API first so we declare that BILLING doesn't need to initialize
require_once("inc_api.php");
require_once("../classes/pm_init.php");

// make sure the user is logged-in, and has plan portal access
if ( !( $userInfo = getPlanManagementUserCredentials() ) ) {
    $apiErrors[] = "You do not have plan portal access.";
    exitWithError( );
}

$state = $userInfo->state_id;

$plans  = PmPlan::all($userInfo);

if (!$plans || !sizeof($plans)) {
    exitWithSuccess( array() );
}   else {
    exitWithSuccess( $plans );
}

?>