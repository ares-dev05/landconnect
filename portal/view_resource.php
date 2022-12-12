<?php

// don't initialize billing for resource fetches
define('BILLING_NO_INIT', true);

require_once("classes/pt_init.php");

$validator = new Validator();

// make sure the user is logged-in, has plan portal access and can view this ticket
if ( !( $userInfo = getPlanPortalUserCredentials() ) ) {
    denyAccess( "You must be logged in to access this resource." );
    exit;
}
$nodeId = $validator->requiredGetVar('id');
if (!is_numeric($nodeId)){
    denyAccess();
    exit;
}

// make sure a node with this ID exists, and that it is a file
$node = new PtNode( $nodeId, "" );
if ( !$node->isValid() ) {
    denyAccess();
    exit;
}

// see if the user has access to the node's parent ticket
$ticket_id = $node->tid;
// make sure the user can view this ticket
if ( !( $ticket = PtTicket::get( $userInfo, $ticket_id ) ) ) {
    denyAccess();
    exit;
}

function file_mime_type( $filePath ) {
    $extension = strtolower( @pathinfo($filePath, PATHINFO_EXTENSION) );
    if ( $extension=="svg" ) {
        return "image/svg+xml";
    }
    if ( $extension=="dwg" ) {
        return "image/x-dwg";
    }
    return 'application/octet-stream';
}

// update the node's storage path
$node->parentStoragePath = $ticket->storagePath;
$resourcePath = $node->getRelativeFilePath();

$type = file_mime_type( $resourcePath );

// set the headers for this resource
header("Content-Type: ".$type);
header("Content-Length: " . filesize($resourcePath));
header('Content-Disposition: filename="'.$node->data.'"');

// output the file
readfile( $resourcePath );

exit;

?>