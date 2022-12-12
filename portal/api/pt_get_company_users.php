<?php

// include API first so we declare that BILLING doesn't need to initialize
require_once("inc_api.php");
require_once("../classes/pt_init.php");

// make sure the user is logged-in, and has plan portal access
if ( !( $userInfo = getPlanPortalUserCredentials() ) ) {
    $apiErrors[] = "permission denied.";
    exitWithError( );
}

if ( isGlobalAdmin($userInfo->id) ) {
    // make sure the required params are present
    $cId = intval( requiredPostVar("companyId") );
    if (!$cId) {
        $apiErrors[] = "Missing parameters.";
        exitWithError();
    }
}   else {
    // if not a root admin, select by default own company
    $cId = $userInfo->company_id;
}

// fetch all users with plan portal access
if ( ( $users = PtUserInfo::getPortalUsers( $cId ) ) === FALSE ) {
    $apiErrors[] = "This information is not available.";
    exitWithError();
}   else {
    exitWithSuccess( $users );
}

?>