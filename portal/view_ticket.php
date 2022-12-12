<?php

require_once("classes/pt_init.php");

$validator = new Validator();

// make sure the user is logged-in, has plan portal access and can view this ticket
if ( !( $userInfo = getPlanPortalUserCredentials() ) ) {
    addAlert("danger", "You must be logged in to access this resource.");
    header("Location: " . getReferralPage());
    exit();
}
$ticket_id = $validator->requiredGetVar('id');
if (!is_numeric($ticket_id)){
    addAlert("danger", "I'm sorry, the floorplan id you specified is invalid!");
    header("Location: " . getReferralPage());
    exit();
}
// make sure the user can view this ticket
if ( !( $ticket = PtTicket::get( $userInfo, $ticket_id ) ) ) {
    addAlert("danger", "You cannot view this floorplan.");
    header("Location: " . getReferralPage());
    exit();
}

// fetch the cookie alerts; @TODO: implement this fully in JS
$cookieAlerts = fetchCookieAlerts();

// load billing data for the ticket; is this used?
// $planBillingData  = new PlanBillingData( $ticket->id );

?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Plan Portal - View Floorplan</title>

      <?php require_once("../account/includes.php");  ?>

    <!-- Page Specific Plugins -->
	<link rel="stylesheet" href="../css/bootstrap-switch.min.css" type="text/css" />
    <link rel="stylesheet" href="css/portal.css" type="text/css" />

	<script src="../js/date.min.js"></script>
    <script src="../js/handlebars-v1.2.0.js"></script> 
    <script src="../js/bootstrap-switch.min.js"></script>

    <!-- Include selectize -->
    <script src="../js/standalone/selectize.js"></script>
    <link rel="stylesheet" href="../css/selectize.bootstrap2.css">

    <!-- Page Plugin -->
    <script src="js/widget-tickets.js?v=<?php echo filemtime(__DIR__."/js/widget-tickets.js"); ?>"></script>
  </head>
<body>
  
<?php
echo "<script>ticket_id = $ticket_id;</script>";
?>
  
<!-- Begin page contents here -->
<div id="wrapper">

<!-- Sidebar -->
<nav class="app-nav" role="navigation">
</nav>

	<div id="page-wrapper">
		<div class="container-fluid">
		<div class="row">
		  <div id='display-alerts' class="col-lg-12">
  
		  </div>
		</div>
		<div class="row">
			<div id='widget-ticket-info' class="col-lg-12">

			</div>
		</div>
		</div>
  </div><!-- /#page-wrapper -->

</div><!-- /#wrapper -->
    <script>
		$(document).ready(function() {
			// Load the header
            $('.app-nav').load('../account/header.php?area=portla', function() {
                $('.navitem-planportal').addClass('active');
            });

<?php
    if ( strlen($cookieAlerts) ) {
?>
            var alerts = '<?php echo $cookieAlerts; ?>';
            alertWidgetResponse('display-alerts', processJSONResult(alerts));
<?php
    }
?>

            function displayCurrentTicket() {
                ticketDisplay('widget-ticket-info', ticket_id);
            }
            displayCurrentTicket();
        });
    </script>
<?php
    // load chat
    include_once(__DIR__."/../forms/chat.php")
?>
  </body>
</html>

