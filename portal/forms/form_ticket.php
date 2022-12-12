<?php



// Request method: GET

require_once("../../models/config.php");

require_once("../classes/pt_init.php");

require_once("../views/elements.php");



// Parameters: box_id, render_mode, [ticket_id]

// box_id: the desired name of the div that will contain the form.

// render_mode: modal or panel

// ticket_id (optional): if specified, will load the relevant data for the group into the form.  Form will then be in "update" mode.



$validator = new Validator();



$box_id      = $validator->requiredGetVar('box_id');

$render_mode = $validator->requiredGetVar('render_mode');

$ticket_id   = $validator->optionalNumericGetVar('ticket_id');



// Buttons (optional)

// button_submit: If set to true, display the submission button for this form.

$button_submit = $validator->optionalBooleanGetVar('button_submit', true);



// make sure the user is logged-in, and has plan portal access

if ( !( $userInfo = getPlanPortalUserCredentials() ) ) {

    addAlert("danger", "You cannot view this floorplan.");

    echo json_encode(array("errors" => 1, "successes" => 0));

    exit();

}



global $loggedInUser, $billingAccount;



// fetch the ticket, make sure the user can edit it

// Create appropriate labels

if ($ticket_id){

    // make sure the current user is a watcher for this ticket
    if ( !( $ticket = PtTicket::get( $userInfo, $ticket_id ) ) ) {
        addAlert("danger", "You cannot view this floorplan.");
        echo json_encode(array("errors" => 1, "successes" => 0));
        exit();
    }

    if ( !$ticket->isValid() ) {
        addAlert("danger", "This floorplan has been deleted");
        echo json_encode(array("errors" => 1, "successes" => 0));
        exit();
    }

    $isGlobalAdmin = $ticket->isGlobalAdmin($userInfo->id);



    $button_submit_text = "Update Floorplan";

    $target = "update_ticket.php";



    if ( $ticket->floorName )

        $box_title = "Plan Portal / ".$ticket->floorName;

    else

        $box_title = "Plan Portal / ".$ticket->ticketName;



    $companyName    = PtCompanyInfo::get($ticket->cid)->name;

    $stateName      = PtStateInfo::get($ticket->stateId)->name;

    $floorName      = $ticket->floorName;

    $ownerWatcher   = $ticket->getOwner();

    $ownerName      = (

        $ownerWatcher?

            $ownerWatcher->userInfo->display_name." (".$ownerWatcher->userInfo->email.")" :

            "N/A or Unknown"

    );

    $currentStatus  = strtoupper($ticket->status);
    $statusButtons  = "";
    $statusLabel    = getStatusLabel($ticket->status);


    // only global admins can delete floorplans
    if ( $isGlobalAdmin ) {
        $statusButtons .= "
                <button id='deletePlanBtn' type='button' class='btn btn-danger'>
                    <span class='glyphicon glyphicon-remove-circle'></span>
                    Delete plan
                </button>
        ";
    }
    // only global admins can close (i.e. make active) a floorplan
    if ( $ticket->status != PtTicket::CLOSED && $isGlobalAdmin ) {
        $statusButtons .= "
                <button id='markResolvedBtn' type='button' class='btn btn-success'>
                    <span class='glyphicon glyphicon-check'></span>
                    Mark as Active
                </button>
        ";
    }
    // report a problem - if the status is CLOSED
    if ( $ticket->status == PtTicket::CLOSED ) {
        $statusButtons .= "
                <button id='reportIssueBtn' type='button' class='btn btn-danger'>
                    <span class='glyphicon glyphicon-exclamation-sign'></span>
                    Report Plan Issue
                </button>
        ";
    }

}   else {

    addAlert("danger", "You cannot access this resource.");

    echo json_encode(array("errors" => 1, "successes" => 0));

    exit();

}



$ticket_name = "";

$status = "";



$ticket_name = $ticket->ticketName;

$status = $ticket->status;



// @TODO: also see: Navbar

$companyName    = PtCompanyInfo::get($ticket->cid)->builder_id;

$imageAlt       = $companyName;



$response       = "";

$toolbarContent = "



    <!-- Brand and toggle get grouped for better mobile display

    <div class='navbar-header'>

      <a class='navbar-brand' style='padding:13px;' href='#'>

        <img alt='$imageAlt' src='/portal/images/builders/{$imageAlt}.png'>

      </a>

    </div>

    -->



    <!-- Collect the nav links, forms, and other content for toggling -->

    <div class='row'>

      <div class='col-md-12 text-right'>

        <div class='well well-sm'>
		$statusButtons
		</div>

      </div>
	  
	  </div>


</nav>";



$response .=

"

<div class='page-header'>
  <h1>$box_title</h1>
</div>


<div class='row'>

    <div class='col-sm-12'>

        $toolbarContent

    </div>

</div>



<div class='row'>

    <div class='col-sm-12'>

         <div class='panel panel-default'>

            <div class='panel-heading'>

                <span class='pull-left'>

                    <b>Floorplan Details</b>

                </span>

                <div class='clearfix'></div>

            </div>

            <div class='panel-body'>

    ";





$response .= "

                    <div class='row'>

                        <div class='col-sm-3'>

                              Company

                        </div>

                        <div class='col-sm-9'>

                            <b>$companyName</b>

                        </div>

                    </div>

                    <div class='row'>

                        <div class='col-sm-3'>

                            State

                        </div>

                        <div class='col-sm-9'>

                            <b>$stateName</b>

                        </div>

                    </div>

                    <div class='row'>

                        <div class='col-sm-3'>

                            <!-- icon-picture -->

                            Floorplan name

                        </div>

                        <div class='col-sm-9'>

                            <b>$floorName</b>

                        </div>

                    </div>

                    <div class='row'>

                        <div class='col-sm-3'>

                            Created by

                        </div>

                        <div class='col-sm-9'>

                            <b>$ownerName</b>

                        </div>

                    </div>

                    <div class='row'>

                        <div class='col-sm-3'>

                            Current status

                        </div>

                        <div class='col-sm-9'>

                            $statusLabel

                        </div>

                    </div>



                    <hr />

";



// display the release date

if ( ($releaseDate = $ticket->props->getReleaseDate())!=null ) {

    if ( $ticket->status == PtTicket::CLOSED ) {

        $releaseStatus = formatAsLabel( "released", "success", true );

    }   else {

        $releaseStatus = formatDaysLeft( $releaseDate );

    }

    $dateFmt = $releaseStatus."&nbsp;".formatDate( $releaseDate );

}

else {

    $dateFmt = missingProperty();

}

$response .= "

                <div class='row'>

                    <div class='col-sm-3'>

                        Release Date

                    </div>

                    <div class='col-sm-9'>

                        <b>$dateFmt</b>

                        <!--<div class='pull-right'>

                            <a href='#'>[edit]</a>

                        </div>-->

                    </div>

                </div>

";



// display the target range

if ( ($targetRange = $ticket->props->getTargetRange() ) == null ) {

    $targetRange = missingProperty();

}

$response .= "

                <div class='row'>

                    <div class='col-sm-3'>

                           Range

                    </div>

                    <div class='col-sm-9'>

                        <b>$targetRange</b>

                        <!--<div class='pull-right'>

                            <a href='#'>[edit]</a>

                        </div>-->

                    </div>

                </div>

";



// ------------------------------------------- @BILLING ------------------------------------------

// display billing information for this plan, if available



if ( $billingAccount && BillingRelease::ENABLE_PORTAL_PAYMENTS && $ticket->billingData ) {

    if ( $ticket->billingData->getBillingStatus() == PlanBillingData::PAID ) {

        $billingContent = "All plan updates have been paid.";

    }   else if ( $ticket->billingData->getBillingStatus() == PlanBillingData::UNPAID ) {

        /**

         * @var $charge LcInvoiceProduct

         */

        $chargeList = array( );

        foreach ($ticket->billingData->outstandingCharges as $charge) {

            if ( $charge->getType() == LcInvoiceProduct::PRODUCT_PLAN_ADDITION ) {

                $chargeList[]= "<span class='label label-success'>Plan Addition</span> - \$".$charge->cost.(

                    $charge->hasMessage() ? " ({$charge->message})" : ""

                );

            }   else

            if ( $charge->getType() == LcInvoiceProduct::PRODUCT_PLAN_UPDATE ) {

                $chargeList[]= "<span class='label label-primary'>Plan Update</span> - \$".$charge->cost.(

                    $charge->hasMessage() ? " ({$charge->message})" : ""

                );

            }

        }



        // $chargeList[] = "<pre>".print_r($ticket->billingData, true)."</pre>";



        $billingContent =

            "The following charges are outstanding for this plan:<br/>".

            join( "<br/>", $chargeList )."<br/>

            <hr style='margin: 8px 0 5px 0;'/>

            <h4>

                <span class='label label-danger'>

                    Total: \${$ticket->billingData->outstandingAmount}

                </span>

            </h4>";

    }



    if ( isset( $billingContent ) ) {

        $response .= "

                <hr />

                

                <div class='row'>

                    <div class='col-sm-3'>


                        Billing Details

                    </div>

                    <div class='col-sm-9'>

                        {$billingContent}

                    </div>

                </div>

";

    }

}





// prepare the list of 'other watchers' - all the watchers to this ticket, except

// for the current user

$otherWatchersList = "";



if ( !EMAIL_ENABLED )

    $otherWatchersList = "<i>(email sending disabled; the current users will be able to see the update)</i>";



/**

 * @var PtWatcher $watcher

 */



if ( WATCHER_SYSTEM_ENABLED ) {

    foreach ($ticket->watchers as $watcher) {

        if ( $userInfo->id != $watcher->uid ) {

            if ( strlen($otherWatchersList) ) {

                $otherWatchersList .= ", ";

            }

            $otherWatchersList .= getUserLabel(

                $watcher->userInfo->id,

                $watcher->userInfo->display_name// ." (".$watcher->userInfo->email.")"

            );

        }

    }



}   else {

    $users = PtUserInfo::getPortalUsersInfos( $ticket->cid );

    /**

     * @var PtUserInfo $portalUserInfo

     */

    foreach ($users as $portalUserInfo) {

        if ($portalUserInfo->id != $userInfo->id) {

            if ( strlen($otherWatchersList) ) {

                $otherWatchersList .= ", ";

            }

            $otherWatchersList .= getUserLabel(

                $portalUserInfo->id,

                $portalUserInfo->display_name// ." (".$watcher->userInfo->email.")"

            );

        }

    }

}



$response .=

"

                </div>  <!-- panel-body -->

            </div>  <!-- panel -->

        </div>  <!-- col-sm-8 -->

    </div> <!-- row -->

";



//////////////////////////////////////////////////////////////////////////////////////

// prepare the latest file attachments section

$latestSVG = null;

$latestDWG = null;



/**

 * @var PtNode $node

 */

foreach ($ticket->nodes as $node) {

    if ($node->type == PtNode::FILE) {

        if ($node->getExtension() == "svg" && $latestSVG == NULL) {

            $latestSVG = $node;

        }

        if ( $isGlobalAdmin && $node->getExtension() == "dwg" && $latestDWG == NULL) {

            $latestDWG = $node;

        }

    }

}



if ( $latestSVG || $latestDWG ) {

    $files = "";

    if ( $latestSVG ) {

        $files .= "<div class='col-sm-3 col-md-3'>".getFileIcon( $latestSVG )."</div>";

    }

    if ( $latestDWG ) {

        $files .= "<div class='col-sm-3 col-md-3'>".getFileIcon( $latestDWG )."</div>";

    }



    $response .= "

        <!-- Display attachments options -->

        <div class='row'>

            <div class='col-sm-12'>

                 <div class='panel panel-default'>

                    <div class='panel-heading'>

                        <span class='pull-left'>


                             <b>Latest File Upload</b>

                        </span>

                        <div class='clearfix'></div>

                    </div>

                    <div class='panel-body'>

                        <div class='row'>

                            $files

                        </div>

                    </div>  <!-- panel-body -->

                </div>  <!-- panel -->

            </div>  <!-- col-sm-8 -->

        </div>  <!-- row -->

    ";

}



    $response .= "

    <!-- Display all nodes -->

    <div class='row'>

        <div class='col-sm-12'>

             <div class='panel panel-default'>

                <div class='panel-heading'>

                   <b>Activity</b>

                    <div class='clearfix'></div>

                </div>

                <ul class='list-group'>



";



///////////////////////////////////////////////////////////////////////////////////

// Add Message

$stats = array(

    PtTicket::PROBLEM => array(

        "label"=>"attention",

        "class"=>"danger"

    ),

    PtTicket::CLOSED  => array(

        "label"=>"active",

        "class"=>"success"

    )

);

$statOptions = "

    <input type='hidden' name='status' id='status' value='".$ticket->status."' />

";



foreach ($stats as $status=>$options) {

    $active = ( $ticket->status == $status ?  "active" : "" );

    $class  = ( $ticket->status == $status ? $options["class"] : 'default' );

    $statOptions .= "

        <label id='label{$status}' class='btn btn-sm btn-{$class} {$active}'>

            ".$options["label"]."

        </label>

    ";

}



// add a form

$response .= "

                    <li class='list-group-item' id='form-controls'>

                        <div class='row'>

                            <div class='col-xs-12 col-sm-12'>

                                <div class='vert-pad pull-right'>

                                    <button class='btn btn-sm btn-primary showUploadForm'>

                                        <span class='glyphicon glyphicon-file'></span>

                                        Add message

                                    </button>

                                </div>

                            </div>

                        </div>

                    </li>

                    <li class='list-group-item' id='file-upload-form'>

                        <form id='form-upload' method='post' action='api/pt_add_node.php'  enctype='multipart/form-data'>

                            <div id='uploadFileForm' class='row'>

                                <div class='col-sm-12'>

                                    <span>To:</span>

                                    <span>

                                        $otherWatchersList

                                    </span>

                                </div>

                                <div class='col-sm-12'>

                                    <div class='input-group message-textarea'>

          <textarea id='textMessage' name='textMessage' data-validate='{\"minLength\": 10, \"label\": \"Message\" }' class='form-control' aria-label='Your message here' style='height:100px;'></textarea>

                                        <span class='input-group-addon'><i class='glyphicon glyphicon-comment'></i></span>

                                    </div>

                                    <div class='well well-sm clearfix'>

                                        <!-- <div class='form-control' style='height:45px;'> -->

                                        <div class='pull-left'>

                                            <input type='hidden' name='ticketId' value='{$ticket->id}' />

                                            <input type='file' name='uploadFile[]' multiple style='height:30px; padding:0px 0px;'/>

                                        </div>

                                        <!-- </div> -->

                                        <!-- <span class='input-group-addon'><i class='fa fa-upload'></i></span> -->

                                        <!-- <textarea name='textMessage' data-validate='{\"minLength\": 10, \"label\": \"Message\" }' class='form-control' aria-label='Your message here' style='height:200px;'></textarea> -->

                                        <div class='pull-right'>

                                            <span style='font-weight:500'>

                                                New status:

                                            </span>

                                            <div class='btn-group' data-toggle='buttons'>

                                                $statOptions

                                            </div>

                                        </div>

                                    </div>

                                </div>

                            </div>

                            <div class='row text-center'>

                                            <button type='submit' data-loading-text='Please wait...' class='btn btn-sm btn-success'>

                                                Send Update

                                            </button>

                                            <button class='btn btn-sm btn-link btnCancelForm' data-dismiss='modal'>

                                                Cancel

                                            </button>


                            </div>

                        </form>

                    </li>

";



///////////////////////////////////////////////////////////////////////////////////

// Display Sections

foreach ($ticket->nodes as $node) {

    /**

     * @var PtNode $node

     * @var PtUserInfo $nodeOwner

     */



    // prepare header information

    $nodeOwner   = PtUserInfo::get($node->ownerId);

    $userInfo    = "{$nodeOwner->display_name}";

    $nodeDate    = formatDate( $node->createdAt );



    if ( $node->type == PtNode::MESSAGE ) {

        $nodeContent = "<p class='message-body'>".prepareUserMessage($node->data)."</p>";

        $userInfo   .= "";

    }   else {

        $nodeContent =

            "<div class='row'>".

                "<div class='col-sm-3 col-md-3'>".

                    getFileIcon($node).

                "</div>".

            "</div>";

        $userInfo   .= " uploaded a file";

    }



    $response .= "<li class='list-group-item'>

                    <span class='pull-left message-user'>

                        $userInfo

                    </span>

                    <span class='pull-right message-date'>

                        $nodeDate

                    </span>

                    <hr class='message-split'>

                    $nodeContent

                    <div class='clearfix'></div>

                 </li>";

}



/*

@TODO: add CSRF protection

$csrf_token = $loggedInUser->csrf_token;

$response .= "<input type='hidden' name='csrf_token' value='$csrf_token'/>";

*/





// enclose the nodes

$response .= "

                </ul>  <!-- list-group -->

            </div>  <!-- panel -->

        </div>  <!-- col-sm-8 -->

    </div>  <!-- row -->



    <!-- continuation

    <div class='row'>

        <div class='col-sm-12'>

             <div class='panel panel-default'>

                <div class='panel-heading'>

                    <span class='pull-left'>

                        @TODO - add any other needed content here

                    </span>

                    <div class='clearfix'></div>

                </div>

                <div class='panel-body'>

                </div>

             </div>

        </div>

    </div>

     -->

";

// $response .= "</div></div></div></div>";
echo json_encode(array("data" => $response), JSON_FORCE_OBJECT);

?>