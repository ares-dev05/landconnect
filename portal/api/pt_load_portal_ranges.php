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
    $cId = intval(requiredPostVar("companyId"));
}
else {
    $cId = $userInfo->company_id;
}

// output the ranges
$sId = intval( requiredPostVar("stateId") );
$ranges = PtFootprintsProps::getAllRanges( $cId, $sId );
$ajax = true;
exitWithSuccess( $ranges );

?>