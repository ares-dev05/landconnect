<?php

require_once("inc_api.php");
require_once('../classes/pm_init.php');

// the name of the post field containing the TXT content
define("UPLOAD_FIELD", "txt_field");

if (!isset($_SERVER['PHP_AUTH_USER'])) {
    // authentication is required to access this resource
    header('WWW-Authenticate: Basic realm="LC API Endpoint"');
    header('HTTP/1.0 401 Unauthorized');
    endCall('Authentication required');
}   else {
    /**
     * validate the user/password combo;
     * @INFO: most of the authentication code is copied from /api/process_login.php
     */
    $userdetails = authenticate($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
    if ($userdetails!=null) {
        // create a portal user from the auth and give it user access
        $userInfo = new PmUserInfo($userdetails["id"]);
        $userInfo->has_portal_access = true;

        // verify if data has been uploaded correctly, via $_FILES
        if (isset($_FILES) && isset($_FILES['files']) ) {
            // attempt to upload the file to the bucket
            $result = PmPlan::massUpload($userInfo);

            if ($result["success"]) {
                // return success
                endCall('Upload succeeded', true);
            }   else {
                endCall(
                    ($result["failCount"] > 0 ?
                        'Failed uploading '.$result["failCount"].' files.' :
                        'Upload failed due to an unexpected error.') .
                    'Please try again later or contact Landconnect support.',
                    false,
                    true
                );
            }
        }   else {
            endCall('Upload data is missing');
        }
    }   else {
        // this point should never be reached, because we handle all authentication failures in the authenticate() call
        endCall('Authentication failed');
    }
}

/**
 * @param $authUser string username
 * @param $authPass string password
 * @return bool indicating success
 */
function authenticate($authUser, $authPass) {
    $authUser = str_normalize($authUser);

    // make sure the user exists
    if(!usernameExists($authUser)) {
        endCall(lang("ACCOUNT_USER_OR_PASS_INVALID"));
    }

    // fetch user details; authUser will be the username
    $userdetails    = fetchUserAuthByUserName($authUser);

    // Make sure this is a Henley user. We don't want other users to access this API
    if (!PmUserInfo::isCompanyAllowed($userdetails["company_id"])) {
        // return a 404 Not Found if the user's company is not Henley
        header('HTTP/1.0 404 Not Found');
        exit;
    }

    // See if the API account is activated (this should always be the case)
    if($userdetails["active"]==0) {
        endCall(lang("ACCOUNT_INACTIVE"));
    }
    /**
     * See if the API account is enabled (this should always be the case. However, we can disable it in one of these cases:
     * - usage abuse is detected
     * - client contract ends and service is halted
     */
    else if ($userdetails["enabled"]==0){
        endCall(lang("ACCOUNT_DISABLED"));
    }

    // See if the user's company is disabled. Same situation applies as above
    else if ( isCompanyDisabled( $userdetails['company_id']) ) {
        endCall("Access to the application is currently suspended.<br/> Please contact your management.");
    }
    else {
        // Validate the password
        if (!passwordVerifyUF($authPass, $userdetails["password"])) {
            endCall(lang("ACCOUNT_USER_OR_PASS_INVALID"));
        }   else {
            return $userdetails;
        }
    }

    return null;
}

function endCall($message, $success=false, $can_retry=false) {
    if ($message) {
        echo json_encode(array(
            "success" => $success,
            "can_retry" => $can_retry,
            "message" => $message
        ));
    }

    exit;
}