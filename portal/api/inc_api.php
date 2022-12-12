<?php

error_reporting(0);

// we don't need to initialize any billing during API calls
defined('BILLING_NO_INIT') or define('BILLING_NO_INIT', true);

// keep a list of error messages for the current api call
if ( !isset($apiErrors) )
    $apiErrors = array();

function requiredGetVar($varname){
    // Confirm that data has been submitted via GET
    if (!($_SERVER['REQUEST_METHOD'] == 'GET')) {
        $apiErrors[] = "Error: data must be submitted via GET.";
        return null;
    }

    if (isset($_GET[$varname]))
        return htmlentities($_GET[$varname]);
    else {
        $apiErrors[] = "Parameter $varname must be specified!";
        return null;
    }
}

function requiredPostVar($varname){
    // Confirm that data has been submitted via POST
    if (!($_SERVER['REQUEST_METHOD'] == 'POST')) {
        $apiErrors[] = "Error: data must be submitted via POST.";
        return null;
    }

    if (isset($_POST[$varname]))
        return htmlentities($_POST[$varname]);
    else {
        $apiErrors[] = "Parameter $varname must be specified!";
        return null;
    }
}

function exitWithError( $echoErrors=true )
{
    global $apiErrors, $ajax;

    // output all the errors
    if ($echoErrors) {
        foreach ($apiErrors as $error)
            addAlert("danger", $error);
    }

    if ( $ajax ) {
        echo json_encode(array(
            "errors" => 1,
            "successes" => 0,
            "alerts" => getAlerts()
        ));
    }   else {
        // put the alerts in a cookie
        setCookieAlerts();
        header('Location: ' . $_SERVER['HTTP_REFERER']);
    }

    exit;
}

function exitWithSuccess( $data=null )
{
    global $ajax;

    if ( $data !== null || $ajax == true ) {
        echo json_encode(array(
            "success" => true,
            "data" => $data,
            "alerts" => getAlerts()
        ));
    }   else {
        // put the alerts in a cookie
        setCookieAlerts();
        header('Location: ' . $_SERVER['HTTP_REFERER']);
    }
}

function fileInputEmpty( $inputName ) {
    return $_FILES[$inputName]['size'][0] == 0 && $_FILES[$inputName]['error'][0] == UPLOAD_ERR_NO_FILE;
}

/**
 * runInsertNode
 * @var PtTicket $ticket
 * @var PtUserInfo $userInfo
 * runs a node insertion on the active POST request
 */
function runInsertNode( $ticket, $userInfo, $sendEmail=false ) {
    $nodeAdded = false;

    $messageNode = NULL;
    $fileNodes = [];

    // allow for both a file addition and message addition
    // check if we are adding a text message
    if ( isset($_POST['textMessage'] ) ) {
        // adding a message
        $type   = PtNode::MESSAGE;
        $data   = requiredPostVar( "textMessage" );

        if ( ( $messageNode = $ticket->addNode( $userInfo, $type, $data ) ) != NULL ) {
            addAlert("success", "Message sent.");
            $nodeAdded = true;
        }   else {
            $apiErrors[] = "There was an error adding this message.";
            return false;
        }
    }

    // check if we are uploading a file
    if ( isset($_FILES) && isset($_FILES['uploadFile']) && !fileInputEmpty('uploadFile') ) {
        // support multiple file upload
        $fileInput = $_FILES['uploadFile'];
        $fileCount = count($fileInput["name"]);

        for ($i=0; $i<$fileCount; ++$i) {
            $newFileNode = $ticket->addFileNode( $userInfo, $_FILES['uploadFile'], $i );

            if ($newFileNode !== FALSE && is_a($newFileNode, "PtNode")) {
                // successful file upload
                $fileNodes []= $newFileNode;
                $nodeAdded   = true;
            }   else {
                // error: $apiErrors[] = "An error occurred uploading this file.";
            }
        }

        // if no file upload was successfull
        if (sizeof($fileNodes) === 0 && $fileCount > 0) {
            return false;
        }
    }

    if ( $nodeAdded && $sendEmail ) {
        // send email updates to all watchers
        PtEmailer::nodesAdded( $ticket, $messageNode, $fileNodes, $userInfo );
    }

    return $nodeAdded;
}

?>