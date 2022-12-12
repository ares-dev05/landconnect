<?php

// include API first so we declare that BILLING doesn't need to initialize
require_once("inc_api.php");
require_once("../classes/pt_init.php");

$ajax       = checkRequestMode("post");
// make sure the required params are present
$ticketId   = requiredPostVar( "ticketId" );
$status     = requiredPostVar( "status" );

if ( !$ticketId || !$status ) {
    $apiErrors[] = "Missing parameters.";
    exitWithError( );
}

// make sure the user is logged-in, and has plan portal access
if ( !( $userInfo = getPlanPortalUserCredentials() ) ) {
    $apiErrors[] = "permission denied.";
    exitWithError( );
}

// fetch the ticket, make sure the user can edit it
if ( !( $ticket = PtTicket::get( $userInfo, $ticketId ) ) ||
     !( $ticket -> userCanEdit( $userInfo ) ) ) {
    $apiErrors[] = "You cannot make changes to this ticket.";
    exitWithError();
}

if ( $ticket->setStatus( $status ) ) {
    exitWithSuccess( );
}   else {
    $apiErrors[] = "An error occurred changing the status.";
    exitWithError();
}
?>