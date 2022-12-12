<?php

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
    global $apiErrors, $ajax, $admin;

    // output all the errors
    if ($echoErrors) {
        foreach ($apiErrors as $error)
            addAlert("danger", $error);
        // $output["errors"] = $apiErrors;
    }

    apiReturnError($ajax, $_SERVER['HTTP_REFERER'] );//getReferralPage());
    exit;
}

function exitWithSuccess( $data=null )
{
    global $ajax;

    if ( $data !== null || $ajax == true ) {
        echo json_encode(array(
            "success" => true,
            "data" => $data
        ));
        exit();
    } else {
        header('Location: ' . $_SERVER['HTTP_REFERER'] );
        exit();
    }

    /*
    echo json_encode(array(
        "success" => true,
        "data" => $data
    ));
    exit;
    */
}

function fileInputEmpty( $inputName ) {
    echo "fileInputEmpty test: SIZE=[".$_FILES[$inputName]['size']."\<br/>\n";
    echo "ERROR=[".$_FILES[$inputName]['error']."]<br/>\n";
    return $_FILES[$inputName]['size'] == 0 && $_FILES[$inputName]['error'] == 0;
}

/**
 * runInsertNode
 * runs a node insertion on the active POST request
 */
function runInsertNode( $ticket, $userInfo ) {
    $nodeAdded = false;

    // allow for both a file addition and message addition
    // check if we are adding a text message
    if ( isset($_POST['textMessage'] ) ) {
        // adding a message
        $type   = PtNode::MESSAGE;
        $data   = requiredPostVar( "textMessage" );

        if ( $ticket->addNode( $userInfo, $type, $data ) ) {
            $nodeAdded = true;
        }   else {
            $apiErrors[] = "There was an error adding this message.";
            return false;
        }
    }

    // check if we are uploading a file
    if ( isset($_FILES) && isset($_FILES['uploadFile']) && !fileInputEmpty('uploadFile') ) {
        // uploading a file
        if ( $ticket->addFileNode( $userInfo, $_FILES['uploadFile'] ) ) {
            addAlert("success", "File successfully uploaded.");
            $nodeAdded   = true;
        }   else {
            $apiErrors[] = "An error occurred uploading this file.";
            return false;
        }
    }

    return $nodeAdded;
}

?>