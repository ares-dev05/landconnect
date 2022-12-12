<?php

// include API first so we declare that BILLING doesn't need to initialize
require_once("inc_api.php");
require_once("../classes/pm_init.php");

$ajax       = checkRequestMode("post");
// make sure the required params are present
$planId   = requiredPostVar( "planId" );

if ( !$planId ) {
    $apiErrors[] = "Missing parameters.";
    exitWithError( );
}

// make sure the user is logged-in, and has plan portal access
if ( !( $userInfo = getPlanManagementUserCredentials() ) ) {
    $apiErrors[] = "permission denied.";
    exitWithError();
}

// find it
/**
 * @var $plan PmPlan
 */
$plan = new PmPlan($planId);
if (!$plan || !$plan->isValid()) {

    // check if we are trying to delete everything
    if (requiredPostVar("deleteEverything")=="true") {
        $list = PmPlan::all($userInfo);
        $failures = 0;
        foreach ($list as $plan) {
            if (!$plan->delete($userInfo))
                ++$failures;
        }

        if ($failures>0) {
            $apiErrors[] = "Failed to delete {$failures} plans.";
            exitWithError();
        }   else {
            exitWithSuccess();
        }
    }   else {
        $apiErrors[] = "Plan already deleted.";
        exitWithError();
    }
}   else {
    if ($plan->delete($userInfo)) {
        exitWithSuccess();
    } else {
        $apiErrors[] = "There was an error deleting this plan.";
        exitWithError();
    }
}

?>