<?php

require_once(__DIR__ . "/../models/config.php");

set_error_handler('logAllErrors');

// Request method: POST
$ajax = checkRequestMode("get");

//Forward the user to their default page if he/she is already logged in
if (isUserLoggedIn()) {
    addAlert("warning", "You're already logged in!");
    apiReturnError($ajax, ACCOUNT_ROOT);
}

/**
 * Login oauth user to the system
 * @param array $user
 * @param array $tokens
 * @return array
 */
function oauthLogin(array $user, array $tokens)
{
    global $db_table_prefix, $remember_me_length, $loggedInUser;

    $userdetails = fetchUserAuthByEmail($user['email']);
    $errors = [];

    //See if the user's account is activated
    if ($userdetails["active"] == 0) {
        $errors[] = lang("ACCOUNT_INACTIVE");
    } // See if user's account is enabled
    else if ($userdetails["enabled"] == 0) {
        $errors[] = lang("ACCOUNT_DISABLED");
    } // See if the user's company is disabled
    else if (isCompanyDisabled($userdetails['company_id'])) {
        $errors[] = "Access to the application is currently suspended.<br/> Please contact your management.";
    } else {
        /**
         * @var loggedInUser $loggedInUser
         */
        $loggedInUser = new loggedInUser();
        $loggedInUser->email   = $userdetails["email"];
        $loggedInUser->user_id = $userdetails["id"];
        $loggedInUser->hash_pw = $userdetails["password"];
        $loggedInUser->domain  = $userdetails["domain"];
        $loggedInUser->ckey    = $userdetails["ckey"];
        $loggedInUser->ll      = $userdetails["last_sign_in_stamp"];
        $loggedInUser->alerts  = array();
        $loggedInUser->theme_id          = $userdetails["theme_id"];
        $loggedInUser->builder_id        = $userdetails["builder_id"];
        $loggedInUser->company_id        = $userdetails["company_id"];
        $loggedInUser->username          = $userdetails["user_name"];

        /**
         * @INFO: Possible discovery flags:
         * companies:
         *      chas_discovery=0 > NO ACCESS
         *      chas_discoevry=1 > ACCESS FOR ALL USERS
         *
         * uf_users:
         *      has_discovery=0 > INHERIT FROM COMPANY
         *      has_discovery=1 > GRANT ACCESS
         *      has_discovery=2 > RESTRICT ACCESS
         */
        $userDiscovery                   = $userdetails["uhas_discovery"];
        $companyDiscovery                = $userdetails["chas_discovery"];
        $loggedInUser->chas_discovery    = ($userDiscovery==1);
        if ($companyDiscovery>0) {
            $loggedInUser->chas_discovery= ($userDiscovery<=1);
        }

        $loggedInUser->chas_estates_access = $userdetails["chas_estates_access"];
        if ($userdetails["disabled_estates_access"]) {
            $loggedInUser->chas_estates_access = 0;
        }

        $loggedInUser->chas_footprints   = $userdetails["chas_footprints"];
        $loggedInUser->title             = $userdetails["title"];
        $loggedInUser->displayname       = $userdetails["display_name"];
        $loggedInUser->state_id          = $userdetails["state_id"];
        $loggedInUser->has_multihouse    = $userdetails["has_multihouse"];
        $loggedInUser->has_portal_access = $userdetails["has_portal_access"];
        $loggedInUser->has_exclusive     = $userdetails["has_exclusive"];
        $loggedInUser->is_envelope_admin = $userdetails["is_envelope_admin"];
        $loggedInUser->is_discovery_manager = $userdetails["is_discovery_manager"];
        $loggedInUser->has_nearmap       = $userdetails["has_nearmap"];
        $loggedInUser->remember_me       = 1;
        $loggedInUser->remember_me_sessid   = passwordHashUF(uniqid(rand(), true));
        $loggedInUser->billing_access_level = $userdetails["billing_access_level"];

        /**
         * @Discovery: Disable Discovery for all users outside of Victoria */
        if ($loggedInUser->state_id!=7) {
            $loggedInUser->chas_discovery = 0;
        }
        
        //Update last sign in
        $loggedInUser->updateLastSignIn();

        // Create the user's CSRF token
        $loggedInUser->csrf_token(true);

        // @MD 27MAR18 - we want to always store the user session in $_COOKIES and not in $ SESSION,
        // as on Amazon we can't store anything on the server.
        updateSessionObj();

        $serialloggedin = serialize($loggedInUser);
        $loginsessid    = $loggedInUser->remember_me_sessid;
        $nowtime        = time();

        $db = pdoConnect();
        $sqlVars = array();

        $stmt = $db->prepare("INSERT INTO " . $db_table_prefix . "sessions VALUES(:time, :sessiondata, :sessionid)");
        $sqlVars[':time'] = $nowtime;
        $sqlVars[':sessiondata'] = $serialloggedin;
        $sqlVars[':sessionid'] = $loginsessid;
        $stmt->execute($sqlVars);

        setcookie(
            "userCakeUser",
            $loggedInUser->remember_me_sessid,
            $loggedInUser->remember_me ? (time() + parseLength($remember_me_length)) : 0,
            "/"
        );

        if ($userdetails["last_sign_in_stamp"]== 0 ){
            updateUserField($userdetails["id"], 'last_sign_in_stamp', 0);
        }
        updateUserField($userdetails["id"], 'access_token', $tokens['access_token']);
        updateUserField($userdetails["id"], 'refresh_token', $tokens['refresh_token']);
    }

    return $errors;
}

/**
 * Getting the access token
 * @param $code
 * @return mixed
 */
function getTokenResponse($code)
{
    global $oauth_client_id, $oauth_provider_url, $oauth_client_secret, $oauth_callback;

    $postData = [
        'grant_type' => 'authorization_code',
        'client_id' => $oauth_client_id,
        'client_secret' => $oauth_client_secret,
        'redirect_uri' => $oauth_callback,
        'code' => $code,
    ];

    try {
        $curl = curl_init($oauth_provider_url . '/oauth/token');

        if ($curl === false) {
            throw new Exception('failed to initialize');
        }

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);

        $data = curl_exec($curl);
        $contentType = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($data === false) {
            throw new Exception(curl_error($curl), curl_errno($curl));
        }

        if ($httpCode !== 200 || strpos($contentType, 'application/json') !== 0) {
            throw new Exception('Invalid sso response');
        } else {
            $response = json_decode($data, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                invalidOauthResponse();
            } elseif (isset($response['error'])) {
                invalidOauthResponse("SSO " . $response['hint']);
            }
        }
        curl_close($curl);

        return $response;
    } catch(Exception $e) {
        invalidOauthResponse("SSO " . $e->getMessage() . '. Please try again later.');
    }
}

/**
 * Getting the user from oauth server
 * @param array $tokens
 * @return mixed
 */
function getOauthUser(array $tokens)
{
    global $oauth_provider_url;

    $access_token = $tokens['access_token'];
    $token_type = $tokens['token_type'];

    try {
        $curl = curl_init($oauth_provider_url . '/api/user');

        if ($curl === false) {
            throw new Exception('failed to initialize');
        }

        $authorization = "Authorization: $token_type $access_token";
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json', $authorization));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $data = curl_exec($curl);
        $contentType = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($data === false) {
            throw new Exception(curl_error($curl), curl_errno($curl));
        }

        if ($httpCode !== 200 || strpos($contentType, 'application/json') !== 0) {
            throw new Exception('Invalid token response');
        } else {
            $response = json_decode($data, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                invalidOauthResponse();
            } elseif (isset($response['error'])) {
                invalidOauthResponse("SSO " . $response['hint']);
            }
        }

        curl_close($curl);

        return $response;
    } catch(Exception $e) {
        invalidOauthResponse("SSO " . $e->getMessage() . '. Please try again later.');
    }
}

$validate = new Validator();

if (!empty($_GET)) {
    if (isset($_GET['code'])) {
        $code = $validate->requiredGetVar('code');

        global $oauth_client_id, $oauth_provider_url, $oauth_client_secret, $oauth_callback;

        $tokenResponse = getTokenResponse($code);

        $errors = [];
        if (isset($tokenResponse['access_token'])) {
            $user   = getOauthUser($tokenResponse);

            $errors = oauthLogin($user, $tokenResponse);
        } else {
            invalidOauthResponse();
        }

        // Always redirect to login page on error
        if (count($errors) > 0) {
            $errors = implode(',', $errors);
            invalidOauthResponse($errors);
        } else {
            global $loggedInUser;

            // make sure user has Landconnect access prior to sending him/her to the homepage
            confirmUserHasLandconnectAccess();

            // Automatically forward to the user's default home page
            $home_page = SITE_ROOT . fetchUserHomePage($loggedInUser->user_id);

            /**
             * @MD 06MAR19 - removed
             * verify if the user needs to go to the splash screen
            if ($loggedInUser->chas_estates_access && $loggedInUser->chas_discovery) {
                try {
                    $stmt = pdoConnect()->prepare("SELECT * FROM user_splash_screen WHERE uid=:uid");
                    if ($stmt->execute(array(":uid" => $loggedInUser->user_id))) {
                        if ($stmt->fetchObject() === FALSE) {
                            // we can redirect to the splash screen
                            $home_page = SITE_ROOT . 'splash.php';
                        }
                    }
                } catch (Exception $e) {}
            }
             */

            header( "Location: $home_page" ) ;
        }
        exit();
    }
    invalidOauthResponse();
}