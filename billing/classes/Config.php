<?php

// set this to true to store the errors in the 'apiErrors' array instead of 'userAlerts' in the session
$isApiCall	= false;
$apiErrors	= array();
$db			= pdoConnect();

define("BillingdbPrefix", "billing_");

// use the Braintree sandbox locally
if ( in_array($_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1')) ) {
	define('IS_BRAINTREE_SANDBOX', true);
}	else {
	define('IS_BRAINTREE_SANDBOX', false);
}

define('BRAINTREE_VERSION', 'braintree_php-3.40.0');

// Braintree constant definitions
if ( IS_BRAINTREE_SANDBOX ) {
	define('BRAINTREE_ENVIRONMENT', 'sandbox');
	define('BRAINTREE_MERCHANT_ID', 'mgtp3dthdqwsgc9h');
	define('BRAINTREE_PUBLIC_KEY' , 'vkwmhbn6p3d4rnpg');
	define('BRAINTREE_PRIVATE_KEY', '229e64ee8732eeb023cc1a2e14cae567');
}   else {
	define('BRAINTREE_ENVIRONMENT', 'production');
	define('BRAINTREE_MERCHANT_ID', '5xmv7b58xvhbdzxr');
	define('BRAINTREE_PUBLIC_KEY' , '9gnmctqbsbzxj6zd');
	define('BRAINTREE_PRIVATE_KEY', '7399007ce1559e4abcedd0b186db7428');
}

// Braintree initialization and configuration
require_once(__DIR__.'/'.BRAINTREE_VERSION.'/lib/Braintree.php');

Braintree_Configuration::environment( BRAINTREE_ENVIRONMENT );
Braintree_Configuration::merchantId ( BRAINTREE_MERCHANT_ID );
Braintree_Configuration::publicKey  ( BRAINTREE_PUBLIC_KEY  );
Braintree_Configuration::privateKey ( BRAINTREE_PRIVATE_KEY );

class Config
{
	const SQL_DATETIME		= "Y-m-d H:i:s";
	const DISPLAY_DATETIME	= "Y-m-d";					// E.g. 2016-10-03
	const DATE_VERBOSE		= "d M Y";					// E.g. 03 Oct 2016
	const FULL_DATETIME		= "M jS Y, h:i A T";		// E.g. Dec 6th 2016, 04:29 PM CET
	const TIMEZONE			= DateTimeZone::UTC;	// = 1024

	public static function apiError( $message, $type="danger" )
	{
		global $isApiCall, $apiErrors;

		if ( $isApiCall ) {
			$apiErrors[] = $message;
		}	else {
			addAlert( $type, $message );
		}
	}

	public static function halt()
	{
		// @TODO
		exit;
	}
}

$point = 1;

function logPoint( $data=NULL )
{
	global $point;
	echo "keypoint $point<br/>";
	if ( isset($data) && $data!=NULL ) {
		echo"<pre>". print_r($data, true); echo "</pre>";
	}
	$point++;
}

function fmt_amount( $number )
{
	return number_format( $number, 2, '.', '');
}

?>