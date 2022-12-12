<?php
    define('BILLING_NO_INIT', true);

	include_once("../models/config.php");
    include_once("includes/Utils.php");
	include_once("includes/LcApi.php");

	// mark this process as an API call, so that we don't initialize any of the billing functionality
	define('API_CALL', true);

	// run this API call
	$call = new LcAPi();
?>