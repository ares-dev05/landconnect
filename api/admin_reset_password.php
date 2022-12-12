<?php
/*

UserFrosting Version: 0.2.0
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

require_once("../models/config.php");
set_error_handler('logAllErrors');

if (!isUserLoggedIn()){
    addAlert("danger", "You must be logged in to access this resource.");
    echo json_encode(array("errors" => 1, "successes" => 0));
    exit();
}

$validator = new Validator();
// Required: csrf_token, user_id
$csrf_token = $validator->requiredPostVar('csrf_token');
$user_id = $validator->requiredNumericPostVar('user_id');

// Add alerts for any failed input validation
foreach ($validator->errors as $error){
  addAlert("danger", $error);
}

// Validate csrf token
if (!$csrf_token or !$loggedInUser->csrf_validate(trim($csrf_token))){
	addAlert("danger", lang("ACCESS_DENIED"));
    echo json_encode(array("errors" => 1, "successes" => 0));
	exit();
}

// Cannot 
$self = false;
if ($user_id == "0"){
	addAlert("danger", lang("ACCESS_DENIED"));
    echo json_encode(array("errors" => 1, "successes" => 0));
	exit();
}

//Check if selected user exists
if(!$user_id or !userIdExists($user_id)){
	addAlert("danger", "I'm sorry, the user id you specified is invalid!");
	if (isset($_POST['ajaxMode']) and $_POST['ajaxMode'] == "true" ){
	  echo json_encode(array("errors" => 1, "successes" => 0));
	} else {
	  header("Location: " . getReferralPage());
	}
	exit();
}

$userdetails = fetchUserAuthById($user_id); //Fetch user details
if(!$userdetails){
	addAlert("danger", "I'm sorry, the user id you specified is invalid!");
    if (isset($_POST['ajaxMode']) and $_POST['ajaxMode'] == "true" ){
	  echo json_encode(array("errors" => 1, "successes" => 0));
	} else {
	  header("Location: " . getReferralPage());
	}
	exit();
}

$company_id = $userdetails['company_id'];
// $company_id = $loggedInUser->company_id;
$email		= $userdetails['email'];

$error_count = 0;
$success_count = 0;

// Reset user's password
if ( resetUserPassword($user_id, $email, $company_id) !== false ) {
	addAlert("success", lang("PASS_RESET_COMPLETE", array($email)));
	$success_count++;
}	else {
	$error_count++;
}

restore_error_handler();

$ajaxMode = $validator->optionalBooleanPostVar('ajaxMode', 'true');
if ($ajaxMode == "true" ){
  echo json_encode(array(
	"errors" => $error_count,
	"successes" => $success_count));
} else {
  header('Location: ' . getReferralPage());
  exit();
}

?>
