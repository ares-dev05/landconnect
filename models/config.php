<?php

error_reporting(0);

$whitelist = array('127.0.0.1', '::1');

if( in_array($_SERVER['REMOTE_ADDR'], $whitelist)){
    define('HOST_PREFIX', '/landconnect');
}   else {
    define('HOST_PREFIX', '');
}

// Used to force backend scripts to log errors rather than print them as output
function logAllErrors($errno, $errstr, $errfile, $errline, array $errcontext) {
	ini_set("log_errors", 1);
	ini_set("display_errors", 0);
	
    error_log("Error ($errno): $errstr in $errfile on line $errline");
	throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}

require_once("db-settings.php"); //Require DB connection
require_once("funcs.php");
require_once("password.php");
require_once("db_functions.php");
require_once("error_functions.php");

//Retrieve basic configuration settings

$settings = fetchConfigParameters();

//Set Settings
$emailDate = date('d/m/y');
$emailActivation = $settings['activation'];
$can_register = $settings['can_register'];
$websiteName = $settings['website_name'];
$websiteUrl = $settings['website_url'];
$emailAddress = $settings['email'];
$resend_activation_threshold = $settings['resend_activation_threshold'];
$language = $settings['language'];
$new_user_title = $settings['new_user_title'];
$email_login = $settings['email_login'];
$token_timeout = $settings['token_timeout'];
$remember_me_length = $settings['remember_me_length'];
$oauth_client_id = array_key_exists('oauth_client_id', $settings) ? $settings['oauth_client_id'] : null;
$oauth_client_secret = array_key_exists('oauth_client_secret', $settings) ? $settings['oauth_client_secret'] : null;
$oauth_provider_url = array_key_exists('oauth_provider_url', $settings) ? $settings['oauth_provider_url'] : null;
$oauth_callback = array_key_exists('oauth_callback_url', $settings) ? $settings['oauth_callback_url'] : null;
if ( isset($settings['portal_email_enabled']) && intval($settings['portal_email_enabled'])==0 ) {
    $portal_email_enabled = false;
}   else {
    $portal_email_enabled = true;
}

// Determine if this is SSL or unsecured connection

// On AWS, because the instances are behind the Load Balancer, we can't detect HTTPS with $_SERVER
// Instead, we just assume that we're always on https
$url_prefix = "https://";

// Flag that indicates if we allow the storage of sessions on the server.
// Set to false once we're running on AWS because our application runs on multiple instances
defined("ALLOW_SERVER_SESSIONS")
    or define("ALLOW_SERVER_SESSIONS", false);

// Define paths here
defined("SITE_ROOT")
    or define("SITE_ROOT", $url_prefix.$_SERVER['HTTP_HOST'].'/');

defined("LANDSPOT_DOMAIN")
    or define("LANDSPOT_DOMAIN", 'www.landspot.com.au');
    // or define("LANDSPOT_DOMAIN", 'mountapollo.com');

defined("LANDSPOT_ROOT")
    or define("LANDSPOT_ROOT", $url_prefix.LANDSPOT_DOMAIN);

defined("ACCOUNT_ROOT")
    or define("ACCOUNT_ROOT", SITE_ROOT . "account/");
		
defined("LOCAL_ROOT")
	or define ("LOCAL_ROOT", realpath(dirname(__FILE__)."/.."));
	
defined("MENU_TEMPLATES")
    or define("MENU_TEMPLATES", dirname(__FILE__) . "/menu-templates/");

defined("MAIL_TEMPLATES")
	or define("MAIL_TEMPLATES", dirname(__FILE__) . "/mail-templates/");

defined("FILE_SECURE_FUNCTIONS")
	or define("FILE_SECURE_FUNCTIONS", dirname(__FILE__) . "/secure_functions.php");	

defined("DEBUG")
	or define("DEBUG", true );

// the ID of the global admins group
defined("GLOBAL_ADMINS_GROUP")
	or define("GLOBAL_ADMINS_GROUP", 2);

// the ID of the builder admins group
defined("BUILDER_ADMINS_GROUP")
    or define("BUILDER_ADMINS_GROUP", 3);

defined("DEVELOPER_ADMINS_GROUP")
    or define("DEVELOPER_ADMINS_GROUP", 4);


// Include paths for pages to add to site page management
$page_include_paths = array(
	"account",
	"forms",
	"",
    "portal"
	// Define more include paths here
);

// This is the user id of the master (root) account.
// The root user cannot be deleted, and automatically has permissions to everything regardless of group membership.
$master_account = 1; // this is mike.samy@landconnect.com.au
// $master_account = 247; // this is root.admin@landconnect.com.au

$default_hooks = array("#WEBSITENAME#","#WEBSITEURL#","#DATE#");
$default_replace = array($websiteName,SITE_ROOT,$emailDate);

// The dirname(__FILE__) . "/..." construct tells PHP to look for the include file in the same directory as this (the config) file
if (!file_exists($language)) {
	$language = dirname(__FILE__) . "/languages/en.php";
}

if(!isset($language)) $language = dirname(__FILE__) . "/languages/en.php";

function getAbsoluteDocumentPath($localPath){
	return SITE_ROOT . getRelativeDocumentPath($localPath);
}

function grab_subdomain($echo = false) {
    $hostAddress = explode ( '.', $_SERVER ["HTTP_HOST"] );
    if (is_array ( $hostAddress )) {
        if ( preg_match('/www/i', $hostAddress[0]) ) {
            $passBack = 1;
        } else {
            $passBack = 0;
        }
        if ($echo == false) {
            return ($hostAddress [$passBack]);
        } else {
            echo ($hostAddress [$passBack]);
        }
    } else {
        return (false);
    }
}

// Return the document path of a file, relative to the root directory of the site.  Takes the absolute local path of the file (such as defined by __FILE__)
function getRelativeDocumentPath($localPath){
	// Replace backslashes in local path (if we're in a windows environment)
	$localPath = str_replace('\\', '/', $localPath);
	
	// Get lowercase version of path
	$localPathLower = strtolower($localPath);

	// Replace backslashes in local root (if we're in a windows environment)
	$localRoot = str_replace('\\', '/', LOCAL_ROOT);	
	
	// Get lowercase version of path
	$localRootLower = strtolower($localRoot) . "/";
	
	// Remove local root but preserve case
	$pos = strpos($localPathLower, $localRootLower);
	if ($pos !== false) {
		return substr_replace($localPath,"",$pos,strlen($localRootLower));
	} else {
		return $localRoot;
	}
}

//Pages to require
require_once($language);
require_once("class_validator.php");
require_once("authorization.php");
require_once("secure_functions.php");
require_once("class.mail.php");
require_once("class.user.php");

session_start();

/**
 * @var $loggedInUser loggedInUser
 */

if(isset($_COOKIE["userCakeUser"]))
{
    $db = pdoConnect();
    $sqlVars = array();
    $stmt = $db->prepare("SELECT sessionData FROM ".$db_table_prefix."sessions WHERE sessionID = :cookie");
    $sqlVars[':cookie'] = $_COOKIE['userCakeUser'];
    $stmt->execute($sqlVars);

    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

        $row = array('sessionData' => $r['sessionData']);
    }

    if(empty($row['sessionData']))
    {
        $loggedInUser = NULL;
        setcookie("userCakeUser", "", -parseLength($remember_me_length));
    }
    else
    {
        $loggedInUser = unserialize($row['sessionData']);
    }

}
else
{
    $db = pdoConnect();
    $sqlVars = array();
    $nowtime = time();
    $plength = parseLength($remember_me_length);
    $stmt = $db->prepare("DELETE FROM ".$db_table_prefix."sessions WHERE :time >= (`sessionStart` + :rmlength)");
    $sqlVars[':time'] = $nowtime;
    $sqlVars[':rmlength'] = $plength;
    $stmt->execute($sqlVars);

    $loggedInUser = NULL;
}

// ---------------------------------------------- @BILLING ---------------------------------------------
// Billing is initialized incrementally, per builder

/**
 * @var $payWall        LcPayWall
 * @var $billingAccount LcAccount
 */
$payWall        = null;
$billingAccount = null;

function t($since=0) {
    return microtime(true)-$since;
}

// we don't want to initialize billing / activate the paywall during certain calls
if ( !defined('BILLING_NO_INIT') ) {
    global $stats;
    $stats = array();
    $start = t();
    // initialize billing
    require_once(__DIR__ . "/../billing/init.php");

    $stats["require_classes"] = t($start);

    if ($loggedInUser) {
        // setup the PayWall before anything else gets processed
        if (BillingRelease::enabledFor($loggedInUser->company_id)) {
            // the Paywall will only appear if it is enforced for the user's account
            // if the user doesn't have a billing account, one is created for him in the paywall
            $payWall = new LcPayWall();

            // load the user's billing account
            $billingAccount = $payWall->getAccount();
        }
    }

    $stats["load_billing"] = t($start);
}

?>