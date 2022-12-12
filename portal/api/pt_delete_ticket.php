<?php

// include API first so we declare that BILLING doesn't need to initialize
require_once("inc_api.php");
require_once("../classes/pt_init.php");

$ajax       = checkRequestMode("post");

// make sure the required params are present
$ticketId   = requiredPostVar( "ticketId" );
if ( !$ticketId ) {
    exitWithError( );
}

// make sure the user is logged-in, and has plan portal access
if ( !( $userInfo = getPlanPortalUserCredentials() ) ) {
    exitWithError( );
}
// for now, only global admins can delete portal entries
if ( !isGlobalAdmin($userInfo->id) ) {
    exitWithError();
}
// fetch the ticket, make sure the user can edit it
if ( !( $ticket = PtTicket::get( $userInfo, $ticketId ) ) ) {
    $apiErrors[] = "This plan does not exist.";
    exitWithError();
}

if ($ticket->delete()) {
    exitWithSuccess();
}   else {
    $apiErrors[] = "Cannot delete this plan due to a DB error.";
    exitWithError();
}

?>