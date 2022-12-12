<?php

// there's no reason to load billing when the user first logs in, before they are redirected
define('BILLING_NO_INIT', true);

require_once("../models/config.php");

// User must be logged in
if (!isUserLoggedIn()){
  addAlert("danger", "You must be logged in to access the account page.");
  header("Location: ../login.php");
  exit();
}

setReferralPage(getAbsoluteDocumentPath(__FILE__));

global $loggedInUser;

// Automatically forward to the user's default home page
$home_page = SITE_ROOT . fetchUserHomePage($loggedInUser->user_id);

header( "Location: $home_page" ) ;
exit();

?>
