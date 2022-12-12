<?php
// no billing required on Statistics
define('BILLING_NO_INIT', true);

require_once("../models/config.php");

if (!securePage(__FILE__)){
  // Forward to index page
  addAlert("danger", "Whoops, looks like you don't have permission to view that page.");
  header("Location: index.php");
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

    <?php require_once("includes.php");  ?>
 
    <!-- Page Specific Plugins -->
    <link rel="stylesheet" href="../css/bootstrap-switch.min.css" type="text/css" />
  
    <script src="../js/date.min.js"></script>
    <script src="../js/handlebars-v1.2.0.js"></script> 
    <script src="../js/bootstrap-switch.min.js"></script>
	<script src="../js/jquery.tablesorter.js"></script>
	<script src="../js/tables.js"></script>    
    <script src="../js/widget-users.js?v=2"></script>
  </head>

  <body>

    <div id="wrapper">

      <!-- Sidebar -->
      <nav class="app-nav" role="navigation">
      </nav>

      <div id="page-wrapper" class="user-manager">
      <div class="container-fluid">
      <div class="page-header">
		  <h1>User Manager</h1>
		</div>
	  	<div class="row">
          <div id='display-alerts' class="col-lg-12">

          </div>
		  
		  <div id="dev-tests">
<?php
	// runPasswordsTest();
?>
		  </div>
        </div>
        <div class="row">
          <div id='widget-users' class="col-lg-12">          

          </div>
        </div><!-- /.row -->
        
		</div>  
      </div><!-- /#page-wrapper -->

    </div><!-- /#wrapper -->
    
    <script>
        $(document).ready(function() {
          // Load the header
          $('.app-nav').load('header.php?area=manage', function() {
            $('.navitem-users').addClass('active');
          });
                              
          alertWidget('display-alerts');
          
		  var columns, headers;
		  
			<?php if ( $is_global_admin ) { ?>
				columns = {
				  user_company: 'Company',
				  user_info: 'User/Info',
				  user_state: 'State',
				  user_sign_in: 'Last Sign-in',
				  user_since: 'User Since',
				  action: 'Actions'
				};
				headers = {
					1: {sorter: 'metatext'},
					3: {sorter: 'metadate'},
					4: {sorter: 'metadate'}
				};
			<?php } else { ?>
				columns = {
				  user_info: 'User/Info',
				  user_state: 'State',
				  user_sign_in: 'Last Sign-in',
				  user_since: 'User Since',
				  action: 'Actions'
				};
				headers = {
					0: {sorter: 'metatext'},
					2: {sorter: 'metadate'},
					3: {sorter: 'metadate'}
				};
			<?php } ?>
		  
          usersWidget('widget-users', {
            title: 'Users',
            limit: 1000,
            sort: 'asc',
            columns: columns,
			headers: headers  
          }); 
        });      
    </script>

<?php
    // load chat
    include_once(__DIR__."/../forms/chat.php")
?>

  </body>
</html>
