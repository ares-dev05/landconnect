<?php

// include API first so we declare that BILLING doesn't need to initialize
require_once("inc_api.php");
require_once("../classes/pt_init.php");

// make sure the required params are present
$ticketId   = requiredPostVar( "ticketId" );

if ( !$ticketId ) {
    $apiErrors[] = "Missing parameters.";
    exitWithError( );
}

// make sure the user is logged-in, and has plan portal access
if ( !( $userInfo = getPlanPortalUserCredentials() ) ) {
    // @TODO
    $apiErrors[] = "permission denied.";
    exitWithError( );
}

// fetch the ticket, make sure the user can edit it
if ( !( $ticket = PtTicket::get( $userInfo, $ticketId ) ) ) {
    $apiErrors[] = "You cannot view this board.";
    exitWithError();
}   else {
    exitWithSuccess( $ticket );
}
?>