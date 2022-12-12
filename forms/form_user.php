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

// Request method: GET

require_once("../models/config.php");

if (!securePage(__FILE__)){
  // Forward to index page
  addAlert("danger", "Whoops, looks like you don't have permission to view that page.");
  echo json_encode(array("errors" => 1, "successes" => 0));
  exit();
}

// make sure an admin is logged in to view this page
if (!isUserLoggedIn()){
  addAlert("danger", "You must be logged in to access this resource.");
  echo json_encode(array("errors" => 1, "successes" => 0));
  exit();
}

global $loggedInUser;
$admin_id			= $loggedInUser->user_id;
$is_global_admin	= isGlobalAdmin( $admin_id );

// TODO: allow setting default groups

// Parameters: box_id, render_mode, [user_id, show_dates, disabled]
// box_id: the desired name of the div that will contain the form.
// render_mode: modal or panel
// user_id (optional): if specified, will load the relevant data for the user into the form.  Form will then be in "update" mode.
// show_dates (optional): if set to true, will show the registered and last signed in date fields (fields will be read-only)
// show_passwords (optional): if set to true, will show the password creation fields
// disabled (optional): if set to true, disable all fields

$validator = new Validator();

$box_id = $validator->requiredGetVar('box_id');
$render_mode = $validator->requiredGetVar('render_mode');
$show_dates = $validator->optionalBooleanGetVar('show_dates', false);
$show_passwords = $validator->optionalBooleanGetVar('show_passwords', true);

// Buttons (optional)
// button_submit: If set to true, display the submission button for this form.
// button_edit: If set to true, display the edit button for panel mode.
// button_disable: If set to true, display the enable/disable button.
// button_activate: If set to true, display the activate button for inactive users.
// button_delete: If set to true, display the deletion button for deletable users.

$button_submit = $validator->optionalBooleanGetVar('button_submit', true);
$button_edit = $validator->optionalBooleanGetVar('button_edit', false);
$button_disable = $validator->optionalBooleanGetVar('button_disable', false);
$button_activate = $validator->optionalBooleanGetVar('button_activate', false);
$button_delete = $validator->optionalBooleanGetVar('button_delete', false);
$disabled = $validator->optionalBooleanGetVar('disabled', false);

$disable_str = "";
if ($disabled) {
    $disable_str = "disabled";
    $username_disable_str = "disabled";
}

$userid = $validator->optionalNumericGetVar('user_id');
// Create appropriate labels
if ($userid){
    $populate_fields = true;
    $button_submit_text = "Update user";
    $user_id = htmlentities($userid);
    $target = "update_user.php";
    $box_title = "Update User";
    $username_disable_str = "disabled";
} else {
    $populate_fields = false;
    $button_submit_text = "Create user";
    $target = "create_user.php";
    $box_title = "New User";
    $username_disable_str = "";
}

$user_name = "";
$display_name = "";
$email = "";
$user_title = "";
$user_active = "0";
$user_enabled = "0";
$user_state = -1;
$user_company = -1;
$dual_occupancy_chk = "";
$portal_access_chk = "";
$master_access_chk = "";
$exclusive_chk = "";
$envelope_chk = "";
$discovery_chk = "";
$nearmap_chk = "";

$phone = "";
$job_role = 0;
$sales_location = 0;

// If we're in update mode, load user data
if ($populate_fields){
    $user = loadUser($user_id);
    $user_name = $user['user_name'];
    $display_name = $user['display_name'];
    $email = $user['email'];
    $user_title = $user['title'];
    $user_active = $user['active'];
    $user_enabled = $user['enabled'];
	$primary_group_id = $user['primary_group_id'];
	
	$user_state			= $user['state_id'];
	$phone              = $user['phone'];
	$user_company		= $user['company_id'];
	$dual_occupancy_chk	= intval( $user['has_multihouse'   ] ) ? " checked" : "";
	$portal_access_chk	= intval( $user['has_portal_access'] ) ? " checked" : "";
	$master_access_chk	= intval( $user['has_master_access'] ) ? " checked" : "";
	$exclusive_chk		= intval( $user['has_exclusive'    ] ) ? " checked" : "";
    $envelope_chk       = intval( $user['is_envelope_admin'] ) ? " checked" : "";
    $discovery_chk      = intval( $user['is_discovery_manager'] ) ? " checked" : "";
    $nearmap_chk        = intval( $user['has_nearmap'] ) ? " checked" : "";
    
    if ($user['last_sign_in_stamp'] == '0'){
        // $last_sign_in_date = "Brand new!";
		$last_sign_in_date = "Never signed in";
    } else {
        $last_sign_in_date_obj = new DateTime();
        $last_sign_in_date_obj->setTimestamp($user['last_sign_in_stamp']);
        $last_sign_in_date = $last_sign_in_date_obj->format('l, F j Y');
    }
    
    $sign_up_date_obj = new DateTime();
    $sign_up_date_obj->setTimestamp($user['sign_up_stamp']);
    $sign_up_date = $sign_up_date_obj->format('l, F j Y');
    
    $user_permissions = loadUserGroups($user_id);
    if ($render_mode == "panel"){
        $box_title = $display_name; 
    }

    // fetch Job Role and Sales Location
    $job_role          = fetchJobRole($user_id);
    $sales_location    = fetchUserSalesLocation($user_id);
}

// Check company settings
if ( $is_global_admin ) {
	if ( $populate_fields ) {
		$company_data = fetchCompanyData( $user_company );
	}	else {
		$company_data = array(
			'chas_multihouse'	 => 0,
			'chas_exclusive'	 => 0,
            'chas_envelopes'     => 0,
            // set has portal access to true globally
			'chas_portal_access' => 1,
			'chas_master_access' => 0,
            "chas_discovery"     => 1
		);
	}
}
else {
	$company_data = fetchCompanyData( $loggedInUser->company_id );
}

$dual_occupancy_disable	= $company_data['chas_multihouse']		? "" : "disabled";
$portal_access_disable	= $company_data['chas_portal_access']	? "" : "disabled";
$master_access_disable	= $company_data['chas_master_access']	? "" : "disabled";
$exclusive_visible		= $company_data['chas_exclusive']		? "display:block;" : "display:none;";
$envelopes_access       = $company_data['chas_envelopes']	    ? "" : "disabled";
$discovery_access       = $company_data['chas_discovery']	    ? "" : "disabled";
$show_nearmap           = $company_data['chas_nearmap']===2;
$nearmap_access         = $show_nearmap ? "" : "disabled";


// Load the companies list
if ( $is_global_admin )
{
	$companies		= fetchAllCompanies();
    $cHasExclusive = array();
	// disable company selection if the user is being updated
	$cDisabled		= $populate_fields ? "disabled" : "";
    // <select name='company_id' id='state_id' class='form-control' $disable_str $cDisabled>
	$companiesSelect = "
	<select name='company_id' id='company_id' class='form-control' $disable_str $cDisabled>
		<option value='' disabled=\"disabled\">-</option>
";
	foreach ( $companies as $id => $data ) {
        if ( $data["chas_exclusive"] )
            $cHasExclusive[]=$id;

		$selected	   = ( $user_company == $id ) ? " selected=\"selected\"" : "";
		$name		   = $data['name'];
		$companiesSelect .= "
			<option value='$id' $selected>$name</option>";
	}
	
	$companiesSelect   .= "
	</select>
";
    $cHasExclusiveStr = join(",", $cHasExclusive);
	// wrap inside a row/column
	// $companiesInput	= 
	$companiesInput	= "
    <script type='text/javascript'>
        var exclusive = [{$cHasExclusiveStr}];
    </script>
	<div class='row'>
		<div class='col-sm-12'>
			<h5>Company</h5>
			<div class='input-group'>
				<span class='input-group-addon'><i class='fa fa-edit'></i></span>
				$companiesSelect
			</div>
		</div>
	</div>
";
}
else
{
	// create a hidden input with the company id
	$companiesInput = "<input type='hidden' name='company_id' id='company_id' value='".$loggedInUser->company_id."'/>";
}

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// fetch only the states that are available for the current builder
//
if ( $is_global_admin )
{
	if ( $populate_fields ) {
		// we already have the company_id of the user
		$states = fetchCompanyStates( $user_company );
	}
	else {
		// display all states
		$states = fetchStates( );
	}
}	else {
	$states		= fetchCompanyStates( $loggedInUser->company_id );
}

$selected		= !isset( $states[$user_state] ) ? " selected=\"selected\"" : "";
$statesSelect	= "
	<select name='state_id' id='state_id' class='form-control' $disable_str>
		<option value='' disabled=\"disabled\" $selected>-</option>
";
foreach ( $states as $id => $name )
{
	$selected	   = ( $user_state == $id ) ? " selected=\"selected\"" : "";
	$statesSelect .= "
		<option value='$id' $selected>$name</option>";
}

$statesSelect  .= "
	</select>
";

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Prepare the Role Dropdown

$jobRoleOptions = "";
foreach (array("Select role", "Sales Consultant", "Sales Manager", "Other") as $id=>$role) {
    $jobRoleOptions .= "<option value={$id}".
        (!$id?" disabled='disabled' ":"").
        ($id==$job_role?" selected='selected' ":""). ">{$role}</option>";
}
$jobRoleSelect = "
    <select name='job_role' id='job_role' class='form-control' $disable_str>
        {$jobRoleOptions}
    </select>
";

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Prepare the Sale Locations Dropdown
$saleLocationsInput = '';
if ($is_global_admin) {
    if ($populate_fields) {
        // we already have the company_id of the user
        $builder_locations = fetchBuilderSalesLocations($user_company);
    }   else {
        // needs to be filled dynamically when picking a builder?
        $builder_locations = array();
    }

    $saleLocationOptions = "<option value='0' disabled='disabled'".($sales_location==0?" selected='selected' ":"").">Select sales location</option>";
    foreach ($builder_locations as $location) {
        $id = $location["id"];
        $name = $location["name"];

        $saleLocationOptions .= "<option value={$id}".
            (!$id?" disabled='disabled' ":"").
            ($id==$sales_location?" selected='selected' ":""). ">{$name}</option>";
    }

    $saleLocationsInput = "
    <select name='sales_location' id='sales_location' class='form-control' $disable_str>
        {$saleLocationOptions}
    </select>
    ";
}   else {
    $saleLocationsInput = "<input type='hidden' name='sales_location' id='sales_location' value='".$sales_location."'/>";
}

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Prepare the Form

$response = "";

if ($render_mode == "modal"){
    $response .=
    "<div id='$box_id' class='modal fade'>
        <div class='modal-dialog'>
            <div class='modal-content'>
                <div class='modal-header'>
                    <button type='button' class='close' data-dismiss='modal' aria-hidden='true'>&times;</button>
                    <h4 class='modal-title'>$box_title</h4>
                </div>
                <div class='modal-body'>
                    <form method='post' action='$target'>";        
} else if ($render_mode == "panel"){
    $response .=
    "<div class='panel panel-default'>
        <div class='panel-heading'>
            <h2 class='panel-title pull-left'>$box_title</h2>
            <div class='clearfix'></div>
            </div>
            <div class='panel-body'>
                <form method='post' action='$target'>";
} else {
    echo "Invalid render mode.";
    exit();
}

// Load CSRF token
$csrf_token = $loggedInUser->csrf_token;
$response .= "<input type='hidden' name='csrf_token' value='$csrf_token'/>";

$response .= "
<div class='dialog-alert'>
</div>

$companiesInput

<div class='row'>
    <div class='col-sm-6'>
        <h5>Email</h5>
        <div class='input-group'>
            <span class='input-group-addon'><a id='email-link' href=''><i class='fa fa-envelope'></i></a></span>
            <input type='text' class='form-control' name='email' autocomplete='off' value='$email' data-validate='{\"email\": true, \"label\": \"Email\" }' $disable_str>
        </div>
    </div>

    <div class='col-sm-6'>
        <h5>Display Name</h5>
        <div class='input-group'>
            <span class='input-group-addon'><i class='fa fa-edit'></i></span>
            <input type='text' class='form-control' name='display_name' autocomplete='off' value='$display_name' data-validate='{\"minLength\": 1, \"maxLength\": 50, \"label\": \"Display name\" }' $disable_str>
        </div>
    </div>
</div>

<div class='row'>
    <div class='col-sm-6'>
        <h5>Phone Number</h5>
        <div class='input-group optional'>
            <span class='input-group-addon'><i class='fa fa-edit'></i></span>
            <input type='text' class='form-control' name='phone_number' autocomplete='off' value='$phone' type='tel' data-validate='{\"minLength\": 0, \"maxLength\": 12, \"label\": \"Phone Number\" }' $disable_str>
        </div>
    </div>
    
	<div class='col-sm-6'>
        <h5>State</h5>
        <div class='input-group'>
            <span class='input-group-addon'><i class='fa fa-edit'></i></span>
			$statesSelect
        </div>
    </div>
</div>

<div class='row'>
    <div class='col-sm-6'>
        <h5>Job Role</h5>
        <div class='input-group'>
            <span class='input-group-addon'><i class='fa fa-edit'></i></span>
			$jobRoleSelect
        </div>
    </div>
";

if ($is_global_admin) {
    $response .= "
    <div class='col-sm-6'>
        <h5>Sales Location</h5>
        <div class='input-group'>
            <span class='input-group-addon'><i class='fa fa-edit'></i></span>
			{$saleLocationsInput}
        </div>
    </div>
    ";
}

$response .= "
</div>
    <div class='row'>";

// show settings
$response .= "
	<div class='col-sm-6'>
		<h5>Settings</h5>
		<ul class='list-group permission-summary-rows'>
			<li class='list-group-item'>
				Dual Occupancy
				<span class='pull-right'>
					<input name='select_settings' type='checkbox' class='form-control' data-id='0' $disable_str $dual_occupancy_disable $dual_occupancy_chk />
				</span>
			</li>
			
			<li class='list-group-item'>
				Plan Portal Access
				<span class='pull-right'>
					<input name='select_settings' type='checkbox' class='form-control' data-id='1' $disable_str $portal_access_disable $portal_access_chk />
				</span>
			</li>
			
			<li class='list-group-item'>
				Master Folders Access
				<span class='pull-right'>
					<input name='select_settings' type='checkbox' class='form-control' data-id='2' $disable_str $master_access_disable $master_access_chk />
				</span>
			</li>
			
			<li id='exclusive-access' class='list-group-item' style='{$exclusive_visible}'>
				Exclusive Access
				<span class='pull-right'>
					<input id='exclusive-checkbox' name='select_settings' type='checkbox' class='form-control' data-id='3' $disable_str $exclusive_chk />
				</span>
			</li>
			
			<li id='envelopes-admin' class='list-group-item'>
				Envelopes Admin
				<span class='pull-right'>
					<input id='envelopes-checkbox' name='select_settings' type='checkbox' class='form-control' data-id='4' $disable_str $envelopes_access $envelope_chk />
				</span>
			</li>
			
			<li id='envelopes-admin' class='list-group-item'>
				Discovery Admin
				<span class='pull-right'>
					<input id='discovery-checkbox' name='select_settings' type='checkbox' class='form-control' data-id='5' $disable_str $discovery_access $discovery_chk />
				</span>
			</li>".($show_nearmap ? "

			<li id='envelopes-admin' class='list-group-item'>
                Nearmap Access
                <span class='pull-right'>
                    <input id='nearmap-checkbox' name='select_settings' type='checkbox' class='form-control' data-id='6' $disable_str $nearmap_access $nearmap_chk />
                </span>
            </li>
            " : "")."
        </ul>
    </div>";

/*
passwords have to be generated automatically
if ($show_passwords){
    $response .= "
    <div class='col-sm-6'>
        <div class='input-group'>
            <h5>Password</h5>
            <div class='input-group'>
                <span class='input-group-addon'><i class='fa fa-lock'></i></span>
                <input type='password' name='password' class='form-control'  autocomplete='off' data-validate='{\"minLength\": 8, \"maxLength\": 50, \"passwordMatch\": \"passwordc\", \"label\": \"Password\"}'>
            </div>
        </div>
        <div class='input-group'>
            <h5>Confirm password</h5>
            <div class='input-group'>
                <span class='input-group-addon'><i class='fa fa-lock'></i></span>
                <input type='password' name='passwordc' class='form-control'  autocomplete='off' data-validate='{\"minLength\": 8, \"maxLength\": 50, \"label\": \"Confirm password\"}'>
            </div>
        </div>         
    </div>";
}
*/

// Attempt to load all user groups
// @TODO: disable global-admin group for non-global admins
$groups = loadGroups();

if ($groups){
  $response .= "    
      <div class='col-sm-6'>
          <h5>Groups</h5>
          <ul class='list-group permission-summary-rows'>";
  
  foreach ($groups as $id => $group){
	  // don't show the global admins group to non-global admins
	  if ($id==GLOBAL_ADMINS_GROUP && !$is_global_admin)
		continue;

	  if ($id==DEVELOPER_ADMINS_GROUP)
	      continue;
	  
      $group_name = $group['name'];
      $is_default = $group['is_default'];
      $disable_primary_toggle = $disable_str;
      $response .= "
      <li class='list-group-item'>
          $group_name
          <span class='pull-right'>
          <input name='select_permissions' type='checkbox' class='form-control' data-id='$id' $disable_str";
      if ((!$populate_fields and $is_default >= 1) || ($populate_fields && isset($user_permissions[$id]))){
          $response .= " checked";
      } else {
        $disable_primary_toggle = "disabled";
      }
      $response .= "/>";
      if ((!$populate_fields and $is_default == 2) || ($populate_fields && ($id == $primary_group_id))){
        $primary_group_class = "btn-toggle-primary-group btn-toggle-primary-group-on";
      } else {
        $primary_group_class = "btn-toggle-primary-group";
      }
      
      $response .= "  <button type='button' class='btn btn-xs $primary_group_class $disable_primary_toggle' data-id='$id' title='Set as primary group'><i class='fa fa-home'></i></button>";
      
      
      $response .= "</span>
      </li>";  
  }
        
  $response .= "
          </ul>
      </div>";
}

$response .= "</div>";

// login / registration dates
if ($show_dates){
    $response .= "
    <div class='row'>
        <div class='col-sm-6'>
        <h5>Last Sign-in</h5>
        <div class='input-group optional'>
            <span class='input-group-addon'><i class='fa fa-calendar'></i></span>
            <input type='text' class='form-control' name='last_sign_in_date' value='$last_sign_in_date' disabled>
        </div>
        </div>
        <div class='col-sm-6'>
        <h5>Registered Since</h5>
        <div class='input-group optional'>
            <span class='input-group-addon'><i class='fa fa-calendar'></i></span>
            <input type='text' class='form-control' name='sign_up_date' value='$sign_up_date' disabled>
        </div>
        </div>
    </div>";
}

// Buttons
$response .= "
<br><div class='row'>
";

if ($button_submit){
    $response .= "<div class='col-xs-6 col-sm-3'><div class='vert-pad'><button type='submit' data-loading-text='Please wait...' class='btn btn-lg btn-success'>$button_submit_text</button></div></div>";
	
	if ($userid){
		// user already exists; show a 'reset password' button
		$response .= "<div class='col-xs-6 col-sm-3'><div class='vert-pad'><button class='btn btn-lg btn-warning btn-reset-password'>Reset Pass</button></div></div>";
	}
}

// Create the edit button
if ($button_edit){
    $response .= "<div class='col-xs-6 col-sm-3'><div class='vert-pad'><button class='btn btn-block btn-primary btn-edit-dialog' data-toggle='modal'><i class='fa fa-edit'></i> Edit</button></div></div>";
}

// Create the activate button if the user is inactive
if ($button_activate and ($user_active == '0')){
    $response .= "<div class='col-xs-6 col-sm-3'><div class='vert-pad'><button class='btn btn-block btn-success btn-activate-user' data-toggle='modal'><i class='fa fa-bolt'></i> Activate</button></div></div>";
}

// Create the disable/enable buttons
if ($button_disable){
    if ($user_enabled == '1') {
        $response .= "<div class='col-xs-6 col-sm-3'><div class='vert-pad'><button class='btn btn-block btn-warning btn-disable-user' data-toggle='modal'><i class='fa fa-minus-circle'></i> Disable</button></div></div>";
    } else {
        $response .= "<div class='col-xs-6 col-sm-3'><div class='vert-pad'><button class='btn btn-block btn-warning btn-enable-user' data-toggle='modal'><i class='fa fa-plus-circle'></i> Re-enable</button></div></div>";
    }
}

// Create the deletion button
if ($button_delete){
    $response .= "<div class='col-xs-6 col-sm-3'><div class='vert-pad'><button class='btn btn-block btn-danger btn-delete-user' data-toggle='modal' data-user_name='$user_name'><i class='fa fa-trash-o'></i> Delete</button></div></div>";
}

// Create the cancel button for modal mode
if ($render_mode == 'modal'){
    $response .= "<div class='col-xs-4 col-sm-3 pull-right'><div class='vert-pad'><button class='btn btn-block btn-lg btn-link' data-dismiss='modal'>Cancel</button></div></div>";
}
$response .= "</div>";

// Add closing tags as appropriate
if ($render_mode == "modal")
    $response .= "</form></div></div></div></div>";
else
    $response .= "</form></div></div>";
    
echo json_encode(array("data" => $response), JSON_FORCE_OBJECT);

?>