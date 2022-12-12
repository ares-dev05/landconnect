<?php

// Request method: GET

require_once("../../models/config.php");
require_once("../classes/pt_init.php");
require_once("../views/elements.php");

// Parameters: box_id, render_mode
// box_id: the desired name of the div that will contain the form.
// render_mode: modal

$validator = new Validator();

$box_id      = $validator->requiredGetVar('box_id');
$render_mode = $validator->requiredGetVar('render_mode');

// make sure the user is logged-in, and has plan portal access
if ( !( $userInfo = getPlanPortalUserCredentials() ) ) {
    addAlert("danger", "You cannot access this resource.");
    echo json_encode(array("errors" => 1, "successes" => 0));
    exit();
}

global $loggedInUser;

$user_id            = $userInfo->id;
$is_global_admin	= isGlobalAdmin( $user_id );

$button_submit = true;

// Create appropriate labels
$populate_fields = false;
$button_submit_text = "Add Floorplan";
$target = "/portal/api/pt_create_ticket.php";
$box_title = "New Floorplan";

$group_name = "";
$home_page_id = "";

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
                    <form method='post' action='$target' enctype='multipart/form-data'>";
} else if ($render_mode == "panel"){
    $response .=
        "<div class='panel panel-default'>
        <div class='panel-heading'>
            <h2 class='panel-title pull-left'>$box_title</h2>
            <div class='clearfix'></div>
            </div>
            <div class='panel-body'>
                <form method='post' action='$target' enctype='multipart/form-data'>";
} else {
    echo "Invalid render mode.";
    exit();
}

// Load CSRF token
$csrf_token = $loggedInUser->csrf_token;
$response .= "<input type='hidden' name='csrf_token' value='$csrf_token'/>";

// start the content
$response .= "
<div class='dialog-alert'>
</div>
<div class='row'>
    <div class='col-sm-12'>
        <!-- <h5>Create Ticket</h5> -->
        <div class='input-group'>
            <!-- <span class='input-group-addon'><i class='fa fa-edit'></i></span> -->
            <!-- <input type='text' class='form-control' name='group_name' autocomplete='off' value='$group_name' data-validate='{\"minLength\": 1, \"maxLength\": 50, \"label\": \"Ticket name\" }'> -->
            <i>The new floorplan thread will be added to your list.</i>
        </div>
    </div>
</div>";

// if we are creating a ticket as a Root admin, we are allowed to add it to any company
if ( $is_global_admin ) {
    $companies = fetchAllCompanies();
    // <select name='company_id' id='state_id' class='form-control' $disable_str $cDisabled>
    $companiesSelect = "
	<select name='company_id' id='company_id' class='form-control'>
		<option value='' selected=\"selected\" disabled=\"disabled\">Please Select...</option>
";
    foreach ($companies as $id => $data) {
        $name = $data['name'];
        $companiesSelect .= "
			<option value='$id'>$name</option>";
    }

    $companiesSelect .= "
	</select>
";

    $emailListPrefix = "";

    if (EMAIL_ENABLED) {
        $emailListPrefix = "Users that will be notified of this floorplan:<br/>";
    } else {
        $emailListPrefix = "Users that will have access to this floorplan (email sending disabled):<br/>";
    }

    // wrap inside a row/column
    // $companiesInput	=
    $response .= "
	<div class='row'>
		<div class='col-sm-12'>
			<h5>Company</h5>
			<div class='input-group'>
			    <span class='input-group-addon'><i class='fa fa-edit'></i></span>
				$companiesSelect
			</div>
		</div>
		<div class='col-sm-12' id='portal_users_list'>
            Please select a company before continuing.
        </div>
	</div>

<script type='text/javascript'>
    $('#company_id').change(function(){
        refreshRanges();

        $.ajax({
            type: 'POST',
            url: PORTAL_APIPATH + 'pt_get_company_users.php',
            data: { companyId: $('#company_id').val() },
            dataType: 'json',
            cache: false
        })
            .fail(function(result) {
                addAlert('danger', 'Oops, looks like our server might have goofed.  If you\'re an admin, please check the PHP error logs.');
                alertWidget('display-alerts');
            })
            .done(function(result) {
                console.log('Received users: ');

                var data = result['data'];
                console.log( data );

                if ( data && Object.keys(data).length > 0 ) {
                    var users = '';
                    $.each(data, function(index,value) {
                        if ( users.length ) users += '<br/>';
                        users += getUserLabel(index,value); // '<i>'+value+'</i>';
                    });

                    $('#portal_users_list').html(
                        '$emailListPrefix' + users
                    );
                }   else {
                    $('#portal_users_list').html(
                        'This company has no users with portal access.<br/>'+
                        'You need to give portal access to at least one user before continuing.<br/>' +
                        '<a href=\'/users.php\'>View Users</a>'
                    );
                }
            });
    });
</script>";
}   else {
    // store the company ID for the user as a hidden input
    $response .= "<input name='company_id' id='company_id' type='hidden' value='{$userInfo->company_id}' />";
}

////////////////////////////////////////////////////////////////////////////////////////////////////////////
// state & range selection for both admins and users
$response .= "
<script type='text/javascript'>
    // make sure we load the entered ranges
    $('#state_id').change(function(){
        refreshRanges();
    });

    function refreshRanges() {
        if ( $('#company_id').val() != '' && $('#state_id').val() != '' ) {
            $.ajax({
                type: 'POST',
                url: PORTAL_APIPATH + 'pt_load_portal_ranges.php',
                data: {
                    companyId: $('#company_id').val(),
                    stateId  : $('#state_id').val()
                },
                dataType: 'json',
                cache: false
            }).fail(function(result) {
                addAlert('danger', 'Oops, looks like our server might have goofed. ');
                alertWidget('display-alerts');
            })
            .done(function(result) {
                var data = result['data'];
                var selectize = $('#select-range')[0].selectize;
                selectize.clear();
                selectize.clearOptions();

                if ( data && Object.keys(data).length > 0 ) {
                    // we received data, fill it into the selectize
                    $.each(data, function(index,value) {
                        selectize.addOption({value:value,text:value});
                    });
                }
            });
        }
    }
</script>
";

    // create the states selection
    if ( $is_global_admin )
        $states = fetchStates( );
    else
        $states = fetchCompanyStates( $userInfo->company_id );

    $statesSelect	= "
        <select name='state_id' id='state_id' class='form-control'>
            <option value='' selected=\"selected\" disabled=\"disabled\">Please Select...</option>
    ";
    foreach ( $states as $id => $name )
    {
        $statesSelect .= "
            <option value='$id'>$name</option>";
    }

    $statesSelect  .= "
        </select>
    ";

    // wrap inside a row/column
    /**/
    $response .= "
        <div class='row'>
            <div class='col-sm-12'>
                <h5>State</h5>
                <div class='input-group'>
                    <span class='input-group-addon'><i class='fa fa-edit'></i></span>
                    $statesSelect
                </div>
            </div>
        </div>
";

// end of {company} / state selection
////////////////////////////////////////////////////////////////////////////////////////////////////////////

// allow the users to input the floor plan's name
$response .= "
<div class='row'>
    <div class='col-sm-12'>
        <h5>Floorplan name</h5>
        <div class='input-group'>
            <input id='floorName' name='floorName' type='text' data-validate='{\"minLength\": 4, \"label\": \"Floorplan Name\" }' class='form-control' />
            <span class='input-group-addon'>
                <span class='glyphicon glyphicon-pencil'></span>
            </span>
        </div>
    </div>
</div>";

// allow the user to select a range
// @TODO: make sure the ranges belong to the selected company/state
$rangeSelect = getRangesSelectize( $userInfo );
$response .= "
<div class='row'>
    <div class='col-sm-12'>
        <h5>Range</h5>
        <div class='form-group'>
            {$rangeSelect}
        </div>
    </div>
</div>";

// allow the user to select the release date
$nowDate = new DateTime();
$minDateStr = $nowDate->format('d/m/Y');
// default release date: 1 week from now
$nowDate->add(new DateInterval('P7D'));
$releaseDateStr = $nowDate->format('d/m/Y');

$response .= "
<div class='row'>
    <div class='col-sm-12'>
        <h5>Release date</h5>
        <div class='form-group'>
            <div class='input-group date' id='release-date' data-date='{$releaseDateStr}' data-date-format='dd/mm/yyyy' data-date-start-date='{$minDateStr}' data-date-calendar-weeks='true'>
				<input name='release-date' class='form-control' size='16' type='text' value='{$releaseDateStr}' readonly>
				<span class='input-group-addon'>
				    <span class='glyphicon glyphicon-calendar'></span>
                </span>
			</div>
			<script type='text/javascript'>
                $('#release-date').datepicker();
            </script>
        </div>
    </div>
</div>";

// allow the user to add a description
$response .= "
<div class='row'>
    <div class='col-sm-12'>
        <h5>Description</h5>
        <div class='input-group'>

            <textarea id='textMessage' name='textMessage' data-validate='{\"minLength\": 10, \"label\": \"Message\" }' class='form-control' aria-label='Your message here' style='height:100px;'></textarea>
            <span class='input-group-addon'>
                <span class='glyphicon glyphicon-pencil'></span>
            </span>
        </div>
    </div>
</div>";

// allow the user to attach a file
$response .= "
<div class='row'>
    <div class='col-sm-12'>
        <h5>Upload a file</h5>
        <div class='input-group'>
            <span class='input-group-addon'><i class='fa fa-upload'></i></span>
            <div class='form-control' style='height:45px;'>
                <input type='file' name='uploadFile[]' multiple style='height:30px; padding:0px 10px;'/>
            </div>
        </div>
    </div>
</div>";

/*
<div class='control-group'>
    <select id='select-beast' class='demo-default' placeholder='Select a person...'>
        <option value=''>Select a person...</option>
        <option value='4'>Thomas Edison</option>
        <option value='1'>Nikola</option>
        <option value='3'>Nikola Tesla</option>
        <option value='5'>Arnold Schwarzenegger</option>
    </select>
</div>
<script>
$('#select-beast').selectize({
    create: true,
    sortField: {
        field: 'text',
        direction: 'asc'
    },
    dropdownParent: 'body'
});
</script>
*/

/*
$pages = loadSitePages();
$response .= "
<div class='row'>
    <div class='col-sm-12'>
        <h5>Home Page</h5>
        <div class='form-group'>
          <select class='form-control' name='home_page_id' data-validate='{\"selected\": 1, \"label\": \"Home page\" }'><option value=\"\"></option>";

foreach ($pages as $page){
    $name = $page['page'];
    $id = $page['id'];
    if ($id == $home_page_id){
        $response .= "<option value=\"$id\" selected>$name</option>";
    } else {
        $response .= "<option value=\"$id\">$name</option>";
    }
}
$response .= "
          </select>
        </div>
    </div>
</div>";
*/

// Buttons
$response .= "
<br><div class='row'>
";

if ($button_submit){
    $response .= "<div class='col-xs-8'><div class='vert-pad'><button type='submit' data-loading-text='Please wait...' class='btn btn-lg btn-success'>$button_submit_text</button></div></div>";
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