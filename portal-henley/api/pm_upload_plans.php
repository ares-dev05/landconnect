<?php

// include API first so we declare that BILLING doesn't need to initialize
require_once("inc_api.php");
require_once('../classes/pm_init.php');

$ajax       = true;
$userInfo   = null;

// make sure the user is logged-in, and has plan portal access
if ( !( $userInfo = getPlanManagementUserCredentials() ) ) {
    $apiErrors[] = "permission denied.";
    exitWithError( );
}

// upload multiple files
if (isset($_FILES)) {
    $filesCount = count($_FILES['files']['name']);

    $response = PmPlan::massUpload($userInfo);

    if ($response && is_array($response)) {
        echo json_encode($response, true);
    }   else {
        echo json_encode(array(
            "error"=>"The upload failed due to a database error. Please contact support@landconnect.com.au",
            "type"=>1
        ));
    }
}   else {
    echo json_encode(array(
        "error"=>"The files failed to be sent to the server. Please contact support@landconnect.com.au",
        "type"=>2
    ));
}


/*
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
*/

?>