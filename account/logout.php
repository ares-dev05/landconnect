<?php
// no billing required on Statistics
define('BILLING_NO_INIT', true);

require_once("../models/config.php");

if (!securePage(__FILE__)){
  // Forward to index page
  //addAlert("danger", "Whoops, looks like you don't have permission to view that page.");
  header("Location: /login.php");
  exit();
}

setReferralPage(getAbsoluteDocumentPath(__FILE__));

//Log the user out
if(isUserLoggedIn())
{
	$loggedInUser->userLogOut();
}

// Forward to index root page
header("Location: " . SITE_ROOT);
die();

?>

