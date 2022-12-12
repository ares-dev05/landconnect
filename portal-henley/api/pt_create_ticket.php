<?php

// include API first so we declare that BILLING doesn't need to initialize
require_once("inc_api.php");
require_once("../classes/pt_init.php");

$ajax       = checkRequestMode("post");

if ( ( $userInfo = getPlanManagementUserCredentials() ) ) {
    // Prepare optional parameters for the Ticket Creation
    $options     = array();

    if ( isGlobalAdmin($userInfo->id) ) {
        // only admins can select a different company
        // check if we have a valid company selected
        if ( isset($_POST['company_id']) ) {
            $cid     = intval($_POST['company_id']);
            $company = PtCompanyInfo::get($cid);
            if ( $company->valid ) {
                $options["company"]  = $cid;
                // fill in a list of users
                $options["watchers"] = PtUserInfo::getPortalUsers( $cid );
            }
        }
    }

    // allow any user to select state
    // check if we have a valid state selected
    if ( isset($_POST['state_id']) ) {
        $sid    = intval($_POST['state_id']);
        $state  = PtStateInfo::get($sid);
        if ( $state->valid ) {
            $options["state"] = $sid;
        }
    }

    // validate vars before creating the ticket
    if ( !isset($_POST['floorName']) || !strlen($_POST['floorName']) ) {
        // show an error
        $apiErrors[] = "Please enter a name for the new plan.";
        exitWithError( );
    }

    if ( !isset($_POST['select-range']) || !strlen($_POST['select-range']) ) {
        // show an error
        $apiErrors[] = "Please select a range for the new plan.";
        exitWithError( );
    }

    // user has plan portal access
    $ticket = PtTicket::create( $userInfo, $options );

    // optional: set release date
    if ( isset($_POST['release-date']) ) {
        // convert to DateTime
        try {
            $releaseDate = DateTime::createFromFormat('d/m/Y', requiredPostVar('release-date'));
            $ticket->props->setReleaseDate( $releaseDate );
        }   catch (Exception $e) {
            // ignore
        }
    }

    // optional: set target range
    if ( isset($_POST['select-range']) ) {
        // add the release range
        try {
            $ticket->props->setTargetRange( requiredPostVar('select-range') );
        }   catch (Exception $e) {
            // ignore
        }
    }

    // optional: set floor name
    if ( isset($_POST['floorName']) ) {
        try {
            $ticket->props->setFloorName( requiredPostVar('floorName') );
        }   catch (Exception $e) {
            // ignore
        }
    }

    // optional: create message & attachment files
    $nodeSuccess = runInsertNode( $ticket, $userInfo );

    if ( $ticket != NULL ) {
        PtEmailer::ticketCreated( $ticket, $userInfo );
        exitWithSuccess( );
    }
}   else {
    $apiErrors[] = "permission denied.";
    exitWithError( );
}
?>