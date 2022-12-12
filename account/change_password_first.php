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

setReferralPage(getAbsoluteDocumentPath(__FILE__));

?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="shortcut icon" href="/css/favicon.ico">

    <title>Landconnect</title>

	<link rel="icon" type="image/x-icon" href="/css/favicon.ico" />
	
	
    <!-- Bootstrap core CSS -->
    <link href="/css/bootstrap.css" rel="stylesheet">

    <!-- Custom styles for this template -->
    <!-- <link href="css/jumbotron-narrow.css" rel="stylesheet"> -->

	<!-- Simonds Homes Styles -->
    <link rel="stylesheet" href="../css/sb-admin.css?v=3">  
	
	<link rel="stylesheet" href="/css/font-awesome.min.css">
	<link rel="stylesheet" href="/css/bootstrap-switch.min.css" type="text/css" />
	 
    <!-- JavaScript -->
    

  </head>

  <body>

    <div id="wrapper">

      <!-- Sidebar
      <nav class="app-nav" role="navigation">
      </nav>
     -->

      <div id="page-wrapper" style="padding-top:0px; display: block; overflow: visible; height: 100%; margin: 25px;">
	  	<div class="row">
          <div class="col-lg-12">
              <div class="alert alert-success">This is the first time you have logged in. Please change your password.</div>
          </div>
          <div id='display-alerts' class="col-lg-12">

          </div>
        </div>
		<h1>Change Password</h1>
		<div class="row">
		  <div class="col-lg-6">
		  <form class="form-horizontal" role="form" name="updateAccount" action="update_user_first.php" method="post">
		  <div class="form-group">
			<label class="col-sm-4 control-label">Email</label>
			<div class="col-sm-8">
			  <input type="email" class="form-control" readonly placeholder="Email" name='email' value=''>
			</div>
		  </div>
		  <div class="form-group">
			<label class="col-sm-4 control-label">Current Password</label>
			<div class="col-sm-8">
			  <input type="password" class="form-control" placeholder="Current Password" name='passwordcheck'>
			</div>
		  </div>
		  <div class="form-group">
			<label class="col-sm-4 control-label">New Password</label>
			<div class="col-sm-8">
			  <input type="password" class="form-control" placeholder="New Password" name='password'>
			</div>
		  </div>
		  <div class="form-group">
			<label class="col-sm-4 control-label">Confirm New Password</label>
			<div class="col-sm-8">
			  <input type="password" class="form-control" placeholder="Confirm New Password" name='passwordc'>
			</div>
		  </div>
		  
		  <div class="form-group">
			<div class="col-sm-offset-4 col-sm-8">
			  <button type="submit" class="btn btn-success submit" value='Update'>Update</button>
			</div>
		  </div>
		  <input type="hidden" name="csrf_token" value="<?php echo $loggedInUser->csrf_token; ?>" />
		  <input type="hidden" name="user_id" value="0" />
		  </form>
		  </div>
		</div>
	  </div>
	</div>
	<script src="/js/jquery-1.10.2.min.js"></script>
    <script src="/js/placeholder.min.js"></script> 
	<script src="/js/bootstrap.js"></script>
	<script src="/js/userfrosting.js"></script>
	<script src="/js/date.min.js"></script>
    <script src="/js/handlebars-v1.2.0.js"></script> 
	<script>

        $(document).ready(function() {
          // Get id of the logged in user to determine how to render this page.
          var user = loadCurrentUser();
          var user_id = user['user_id'];
          
		  alertWidget('display-alerts');
		  


		  // Set default form field values
		  $('form[name="updateAccount"] input[name="email"]').val(user['email']);

		  var request;
		  $("form[name='updateAccount']").submit(function(event){

			var url = APIPATH + 'update_user_first.php';
			// abort any pending request
			if (request) {
				request.abort();
			}
			var $form = $(this);
			var $inputs = $form.find("input");
			// post to the backend script in ajax mode
			var serializedData = $form.serialize() + '&ajaxMode=true';
			// Disable the inputs for the duration of the ajax request
			$inputs.prop("disabled", true);
		
			// fire off the request
			request = $.ajax({
				url: url,
				type: "post",
				data: serializedData
			})
			.done(function (result, textStatus, jqXHR){
				var resultJSON = processJSONResult(result);
				// Render alerts
				alertWidget('display-alerts');
				
				// Clear password input fields on success
				if (resultJSON['successes'] > 0) {
				  $form.find("input[name='password']").val("");
				  $form.find("input[name='passwordc']").val("");
				  $form.find("input[name='passwordcheck']").val("");

                  window.location.href = '/account';
				}
			}).fail(function (jqXHR, textStatus, errorThrown){
				// log the error to the console
				console.error(
					"The following error occured: "+
					textStatus, errorThrown
				);
			}).always(function () {
				// reenable the inputs
				$inputs.prop("disabled", false);
			});
		
			// prevent default posting of form
			event.preventDefault();  
		  });

		});
	</script>
  </body>
</html>
