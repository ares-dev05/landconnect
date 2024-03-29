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

include('../models/config.php');

set_error_handler('logAllErrors');

// User must be logged in
if (!isUserLoggedIn()){
  addAlert("danger", "You must be logged in to access this resource.");
  echo json_encode(array("errors" => 1, "successes" => 0));
  exit();
}

global $loggedInUser;
$admin_id			= $loggedInUser->user_id;
$is_global_admin	= isGlobalAdmin( $admin_id );

// GET Parameters: [user_id, group_id, limit]
// If a user_id is specified, attempt to load information for the specified user (self if set to 0).
// If a group_id is specified, attempt to load information for all users in the specified group.
// Otherwise, attempt to load all users.
$validator = new Validator();
$limit = $validator->optionalGetVar('limit');
$user_id = $validator->optionalGetVar('user_id');
$group_id = $validator->optionalGetVar('group_id');

// Add alerts for any failed input validation
foreach ($validator->errors as $error){
  addAlert("danger", $error);
}

if ($user_id){
  // Special case to load groups for the logged in user
  if ($user_id == "0"){
    $user_id = $loggedInUser->user_id;
  }
  if (!$results = loadUser($user_id)) {
    echo json_encode(array("errors" => 1, "successes" => 0));
    exit();
  }
} else if ($group_id) {
  if (!$results = loadUsersInGroup($group_id)) {
    echo json_encode(array("errors" => 1, "successes" => 0));
    exit();
  }
} else {
  // load users belonging to the company of the logged in user
  // @TODO: pass along the user's state & permission to see all the users or just those in the same state
  if ( $is_global_admin )
  {
	if (!$results = loadUsers()) {
		echo json_encode(array("errors" => 1, "successes" => 0));
		exit();
	}
  }
  else {
	  if (!$results = loadUsersInCompany($loggedInUser->company_id)) {
		echo json_encode(array("errors" => 1, "successes" => 0));
		exit();
	  }
  }
}

restore_error_handler();

echo json_encode($results);

?>