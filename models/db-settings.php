<?php
/*

UserFrosting Version: 0.2.0
By Alex Weissman
Copyright (c) 2014

Based on the UserCake user management system, v2.0.2.
Copyright (c) 2009-2012

UserFrosting, like UserCake, is 100% free and open-source.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the 'Software'), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.

*/

$server_name = $_SERVER['SERVER_NAME'];

$CONFIG = parse_ini_file(__DIR__."/../.env");

defined('IS_LOCAL') ||
    define('IS_LOCAL', strpos($server_name, 'localhost') !== FALSE );

$db_host    = $CONFIG['DB_HOST'];
$db_name    = $CONFIG['DB_DATABASE'];
$db_user    = $CONFIG['DB_USERNAME'];
$db_pass    = $CONFIG['DB_PASSWORD'];
$db_table_prefix = "uf_";

// Amazon S3 settings
defined('STORAGE_BUCKET') ||
	define('STORAGE_BUCKET', $CONFIG['S3_BUCKET']);
defined('STORAGE_REGION') ||
	define('STORAGE_REGION', $CONFIG['S3_REGION']);
defined('S3_REGION_PATH') ||
	define('S3_REGION_PATH', "https://s3-".STORAGE_REGION.".amazonaws.com/");
defined('S3_BUCKET_PATH') ||
	define('S3_BUCKET_PATH', S3_REGION_PATH . STORAGE_BUCKET . "/");
defined('S3_LANDSPOT_BUCKET_PATH') ||
	define('S3_LANDSPOT_BUCKET_PATH', S3_REGION_PATH . 'landconnect/');

// Amazon SES settings
defined('SES_REGION') ||
	define('SES_REGION', 'us-east-1');

// load the AWS SDK
require_once __DIR__."/../aws/aws-autoloader.php";

/**
 * @return \Aws\S3\S3Client
 */
function getS3Client()
{
	global $CONFIG;
	$params = [
		'version' => 'latest',
		'region'  => STORAGE_REGION
	];
	if ( isset($CONFIG['S3_KEY']) && isset($CONFIG['S3_SECRET']) && strlen($CONFIG['S3_KEY']) && strlen($CONFIG['S3_SECRET']) ) {
		$params['credentials'] = [
			'key' => $CONFIG['S3_KEY'],
			'secret' => $CONFIG['S3_SECRET']
		];
	}
	return new Aws\S3\S3Client($params);
}

/**
 * Emails are to be sent from sesmail.landconnect.com.au
 * @return \Aws\Ses\SesClient
 */
function getSESClient()
{
	global $CONFIG;
	$params = ['region'=>SES_REGION, 'version'=>'latest'];
	if ( isset($CONFIG['SES_KEY']) && isset($CONFIG['SES_SECRET']) && strlen($CONFIG['SES_KEY']) && strlen($CONFIG['SES_SECRET']) ) {
		$params['credentials'] = [
			'key' => $CONFIG['SES_KEY'],
			'secret' => $CONFIG['SES_SECRET']
		];
	}
	return new \Aws\Ses\SesClient($params);
}

// All SQL queries use PDO now
function pdoConnect(){
	// Let this function throw a PDO exception if it cannot connect
	global $db_host, $db_name, $db_user, $db_pass;
	$db = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
	$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	return $db;
}

GLOBAL $errors;
GLOBAL $successes;

$errors = array();
$successes = array();

//Direct to install directory, if it exists
if(is_dir("install/"))
{
	header("Location: install/");
	die();

}

?>
