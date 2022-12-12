<?php
/*

UserFrosting Version: 0.1
By Alex Weissman
Copyright (c) 2014

Based on the UserCake user management system, v2.0.2.
Copyright (c) 2009-2012

UserFrosting, like UserCake, is 100% free and open-source.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the 'Software'), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.

*/

require_once(__DIR__."/../models/config.php");

set_error_handler('logAllErrors');

// Publically accessible API

// Request method: POST
$ajax = checkRequestMode("post");

//Forward the user to their default page if he/she is already logged in
if(isUserLoggedIn()) {
	addAlert("warning", "You're already logged in!");
	apiReturnError($ajax, ACCOUNT_ROOT);
}

$validate = new Validator();

$postedUsername = str_normalize($validate->requiredPostVar('username'));
$lastlogin = 1;
//Forms posted
if(!empty($_POST))
{
    global $email_login;

    $isEmail = count(explode('@', $postedUsername));

    if ($isEmail == 2 && $email_login == 1) {
        $email = 1;
        $email_address = $postedUsername;
    } elseif ($isEmail == 1 && $email_login == 1){
        $email = 0;
        $username = $postedUsername;
    }else {// ($email_login == 0){
        $email = 0;
        $username = $postedUsername;
    }

	$errors = array();
	$password = $validate->requiredPostVar('password');
    $remember_choice = $validate->requiredPostVar('remember_me');

	//Perform some validation
	//Feel free to edit / change as required
    if ($email == 1){
        if($email_address == "")
        {
            $errors[] = lang("ACCOUNT_SPECIFY_USERNAME");
        }
    } else {
        if($username == "")
        {
            $errors[] = lang("ACCOUNT_SPECIFY_USERNAME");
        }
    }
	if($password == "")
	{
		$errors[] = lang("ACCOUNT_SPECIFY_PASSWORD");
	}
	if(count($errors) == 0)
	{
		//A security note here, never tell the user which credential was incorrect
        if($email == 1){
            $existsVar = !emailExists($email_address);
        }else{
            $existsVar = !usernameExists($username);
        }

        if($existsVar)
		{
		  	$errors[] = lang("ACCOUNT_USER_OR_PASS_INVALID");
		}
        else
		{
            if ($email == 1){
                $userdetails = fetchUserAuthByEmail($email_address);
            } elseif ($email == 0) {
                $userdetails = fetchUserAuthByUserName($username);
            }

			//See if the user's account is activated
			if($userdetails["active"]==0)
			{
				$errors[] = lang("ACCOUNT_INACTIVE");
			}
			// See if user's account is enabled
			else if ($userdetails["enabled"]==0){
				$errors[] = lang("ACCOUNT_DISABLED");
			}
            /*
            else if (
				$userdetails["domain"]!=$_SERVER['HTTP_HOST'] &&
				strpos($websiteUrl,$_SERVER['HTTP_HOST']) === false &&
				strpos("www.".$websiteUrl,$_SERVER['HTTP_HOST']) === false &&
                ( !IS_LOCAL || (
                   IS_LOCAL && strpos("localhost", $_SERVER['HTTP_HOST'] === false )
                ) ) ) {
                $errors[] = lang("ACCOUNT_DOMAIN_INVALID").$websiteUrl."-".$_SERVER['HTTP_HOST'];
            }
            */
            // See if the user's company is disabled
            else if ( isCompanyDisabled( $userdetails['company_id']) ) {
                $errors[] = "Access to the application is currently suspended.<br/> Please contact your management.";
            }
            else {
				// Validate the password
				if(!passwordVerifyUF($password, $userdetails["password"]))
				{
					//Again, we know the password is at fault here, but lets not give away the combination incase of someone bruteforcing
					$errors[] = lang("ACCOUNT_USER_OR_PASS_INVALID");
				} else {
					//Passwords match! we're good to go'
					//Construct a new logged in user object
					//Transfer some db data to the session object
                    /**
                     * @var loggedInUser $loggedInUser
                     */
					$loggedInUser = new loggedInUser();
					$loggedInUser->email = $userdetails["email"];
					$loggedInUser->user_id = $userdetails["id"];
					$loggedInUser->hash_pw = $userdetails["password"];
                    $loggedInUser->domain = $userdetails["domain"];
                    $loggedInUser->ckey = $userdetails["ckey"];
                    $loggedInUser->ll = $userdetails["last_sign_in_stamp"];
                    $loggedInUser->remember_me = $remember_choice;
                    $loggedInUser->remember_me_sessid = passwordHashUF(uniqid(rand(), true));
					$loggedInUser->title = $userdetails["title"];
					$loggedInUser->displayname = $userdetails["display_name"];
					$loggedInUser->state_id = $userdetails["state_id"];
					$loggedInUser->has_multihouse = $userdetails["has_multihouse"];
                    $loggedInUser->has_portal_access = $userdetails["has_portal_access"];
					$loggedInUser->has_exclusive = $userdetails["has_exclusive"];
					$loggedInUser->has_nearmap = $userdetails["has_nearmap"];
                    $loggedInUser->is_envelope_admin = $userdetails["is_envelope_admin"];
                    $loggedInUser->is_discovery_manager = $userdetails["is_discovery_manager"];
                    $loggedInUser->billing_access_level= $userdetails["billing_access_level"];
					$loggedInUser->theme_id = $userdetails["theme_id"];
                    $loggedInUser->chas_discovery = $userdetails["chas_discovery"];
                    $loggedInUser->chas_footprints = $userdetails["chas_footprints"];
					$loggedInUser->builder_id = $userdetails["builder_id"];
					$loggedInUser->company_id = $userdetails["company_id"];
					$loggedInUser->username = $userdetails["user_name"];
					$loggedInUser->alerts = array();

					//Update last sign in
					$loggedInUser->updateLastSignIn();

					// Update password if we had encountered an outdated hash
					if (getPasswordHashTypeUF($userdetails["password"]) != "modern"){
					    // Hash the user's password and update
						$password_hash = passwordHashUF($password);
						if ($password_hash === null){
							error_log("Notice: outdated password hash could not be updated because new hashing algorithm is not supported.  Are you running PHP >= 5.3.7?");
						} else {
							$loggedInUser->hash_pw = $password_hash;
							updateUserField($loggedInUser->user_id, 'password', $password_hash);
							error_log("Notice: outdated password hash has been automatically updated to modern hashing.");
						}
					}

					// Create the user's CSRF token
					$loggedInUser->csrf_token(true);

                    // @MD 27MAR18 - we want to always store the user session in $_COOKIES and not in $ SESSION,
                    // as on Amazon we can't store anything on the server.
                    updateSessionObj();

                    $serialloggedin = serialize($loggedInUser);
                    $loginsessid = $loggedInUser->remember_me_sessid;
                    $nowtime= time();

                    $db = pdoConnect();
                    $sqlVars = array();

                    $stmt = $db->prepare("INSERT INTO ".$db_table_prefix."sessions VALUES(:time, :sessiondata, :sessionid)");
                    $sqlVars[':time'] = $nowtime;
                    $sqlVars[':sessiondata'] = $serialloggedin;
                    $sqlVars[':sessionid'] = $loginsessid;
                    $stmt->execute($sqlVars);

                    setcookie(
                        "userCakeUser",
                        $loggedInUser->remember_me_sessid,
                        $loggedInUser->remember_me ? (time()+parseLength($remember_me_length)) : 0,
                        "/"
                    );

                    $lastlogin = $userdetails["last_sign_in_stamp"];
					$successes = array();
                    if ($userdetails["last_sign_in_stamp"]== 0 ){
                        updateUserField($userdetails["id"], 'last_sign_in_stamp', 0);
                        //$successes[] = lang("ACCOUNT_USER_FIRST_LOGIN");
                    }else{
                        //$successes[] = "Welcome back, " . $loggedInUser->displayname;
                    }
				}
			}
		}
	}
} else {
	$errors[] = lang("NO_DATA");
}

restore_error_handler();

foreach ($errors as $error){
  addAlert("danger", $error);
}
foreach ($successes as $success){
  addAlert("success", $success);
}

if (isset($_POST['ajaxMode']) and $_POST['ajaxMode'] == "true" ){
  echo json_encode(array(
	"errors" => count($errors),
	"successes" => count($successes),
    "firsttime" => ($lastlogin== 0)
  ));
} else {
  // Always redirect to login page on error
  if (count($errors) > 0)
	header('Location: login.php');
  else
	header('Location: account');
  exit();
}