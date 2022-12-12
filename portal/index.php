<?php

require_once("classes/pt_init.php");
require_once("views/elements.php");

// User must be logged in
if (!isUserLoggedIn()){
  header("Location: /login.php");
  exit();
}

// make sure a user is logged-in and has plan portal access
if ( !( $userInfo = getPlanPortalUserCredentials() ) ) {
    addAlert("danger", "You cannot access this resource.");
    header("Location: " . getReferralPage());
    exit();
}

// fetch the cookie alerts; @TODO: implement this fully in JS
$cookieAlerts = fetchCookieAlerts();

global $loggedInUser, $billingAccount;
$admin_id			= $loggedInUser->user_id;
$is_global_admin	= isGlobalAdmin( $admin_id ) || PtTicket::isGlobalAdmin( $admin_id );

// ------------------------------------------- @BILLING ------------------------------------------

if ( $billingAccount && BillingRelease::ENABLE_PORTAL_PAYMENTS ) {
    $billingView    = new LcBillingView( $billingAccount );
    $billingSection = $billingView->getPlanPortalPayments();
}


// by default, load all states
$crtState = -1;
// fetch current state
if ( isset($_GET['state']) ) {
    // verify that it's valid
    $list = PtStateInfo::fromIds(
        $is_global_admin ?
            array_keys( fetchStates() ) :
            array_keys( fetchCompanyStates( $userInfo->company_id ) )
    );

    // check the selected state
    foreach ( $list as $stateInfo ) {
        if ( $stateInfo->abbrev == $_GET['state'] ) {
            $crtState = $stateInfo->id;
            // we found a valid state
            break;
        }
    }
}

setReferralPage(getAbsoluteDocumentPath(__FILE__));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Landconnect</title>

    <?php require_once("../account/includes.php");  ?>

    <!-- Page Specific Plugins -->
    <link rel="stylesheet" href="../css/bootstrap-switch.min.css" type="text/css" />

    <script src="../js/date.min.js"></script>
    <script src="../js/handlebars-v1.2.0.js"></script>
    <script src="../js/bootstrap-switch.min.js"></script>
    <script src="../js/jquery.tablesorter.js"></script>
    <script src="../js/tables.js"></script>

    <!-- Include selectize -->
    <script src="../js/standalone/selectize.js"></script>
    <link rel="stylesheet" href="../css/selectize.bootstrap2.css">

    <!-- include bootstrap datepicker -->
    <script src="../js/bootstrap-datepicker.js"></script>
    <link rel="stylesheet" href="../css/bootstrap-datepicker.css">

    <!-- Page Plugin -->
    <script src="js/widget-tickets.js?v=<?php echo filemtime(__DIR__."/js/widget-tickets.js"); ?>"></script>
</head>

<body>

<div id="wrapper">

    <!-- Sidebar -->
    <nav class="app-nav" role="navigation">
    </nav>

    <div id="page-wrapper" class="plan-portal">
       <div class="container-fluid">
       <div class="page-header">
    	  <h1>Plan Portal</h1>
		</div>
       
        <div class="row">
            <div id='display-alerts' class="col-lg-12"></div>
        </div>

        <div class="row" id="settingsBar">
            <div class="col-lg-12">
                <?php echo getSettingsBar( $is_global_admin, $crtState, $userInfo->company_id ); ?>
            </div>
        </div>

        <?php
            if ( isset($billingSection) )
                echo $billingSection;
        ?>

        <div class="row">
            <div id='widget-tickets' class="col-lg-12"></div>
        </div><!-- /.row -->
		</div><!-- /.container-fluid -->
    </div><!-- /#page-wrapper -->

</div><!-- /#wrapper -->

<script>
    $(document).ready(function() {
        // Load the header
        $('.app-nav').load('../account/header.php?area=portal', function() {
            $('.navitem-planportal').addClass('active');
        });

<?php
    // display
    if ( strlen($cookieAlerts) ) {
?>
        var alerts = '<?php echo $cookieAlerts; ?>';
        alertWidgetResponse('display-alerts', processJSONResult(alerts));
<?php
    }
?>


        var columns, headers;

        <?php
            if ( $is_global_admin ) {
        ?>
        columns = {
            ticket_name: 'Floorplan',
            company: 'Company',
            state: 'State',
            range: 'Range',
            date_added: 'Date Added',
            last_update: 'Last Update'
            // action: 'Actions'
        };
        headers = {
            0: {sorter: 'metatext'},
            1: {sorter: 'metatext'},
            2: {sorter: 'metatext'},
            3: {sorter: 'metatext'},
            4: {sorter: 'metadate'},
            5: {sorter: 'metadate'}
        };
        <?php
            }   else {
        ?>
        columns = {
            ticket_name: 'Floorplan',
            state: 'State',
            range: 'Range',
            date_added: 'Date Added',
            last_update: 'Last Update',
            action: 'Actions'
        };
        headers = {
            0: {sorter: 'metatext'},
            1: {sorter: 'metatext'},
            2: {sorter: 'metatext'},
            3: {sorter: 'metadate'},
            4: {sorter: 'metadate'},
            5: {sorter: false     }
        };
        <?php
            }
        ?>

        function loadTicketsWidget() {
            ticketsWidget('widget-tickets', {
                title: 'Floorplans',
                limit: 1000,
                sort: 'asc',
                columns: columns,
                headers: headers,
                state: <?php echo $crtState; ?>
            });
        }
        loadTicketsWidget();
    });
</script>

<?php
    // add the paywall scripts if they are needed
    if ( $billingAccount && isset($billingSection) ) {
        PayWallContent::addPageScripts(array(
            "subscribeBtn"      => "btn-create-subscription",
            "managePaymentsBtn" => "btn-manage-payment-methods"
        ));
    }
?>

<?php
    // load chat
    include_once(__DIR__."/../forms/chat.php")
?>

</body>
</html>