<?php

// include API first so we declare that BILLING doesn't need to initialize
require_once("inc_api.php");
require_once("../classes/pt_init.php");

$ajax       = checkRequestMode("post");

// make sure the required params are present
$ticketId   = requiredPostVar( "ticketId" );
if ( !$ticketId ) {
    $apiErrors[] = "Missing parameters.";
    exitWithError( );
}

// make sure the user is logged-in, and has plan portal access
if ( !( $userInfo = getPlanPortalUserCredentials() ) ) {
    $apiErrors[] = "permission denied.";
    exitWithError( );
}
// fetch the ticket, make sure the user can edit it
if ( !( $ticket = PtTicket::get( $userInfo, $ticketId ) ) ) {
    $apiErrors[] = "You cannot make changes to this board.";
    exitWithError();
}

// change the status before sending the emails
if ( isset($_POST['status']) ) {
    $apiSuccess = $ticket->setStatus($_POST['status']);
}   else {
    // start with success
    $apiSuccess = true;
}

if ( $apiSuccess ) {
    // run the node insertion, and send emails;
    // will return false on failure, true on success
    $apiSuccess = runInsertNode($ticket, $userInfo, true);
}

// check if a node was actually added
if ( $apiSuccess ) {
    exitWithSuccess( );
}   else {
    // something went wrong. nothing was changed.
    $apiErrors[] = "An error occurred while making these changes. Please try again later.";
    exitWithError( );
}

?>