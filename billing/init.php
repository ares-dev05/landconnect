<?php

// load requisites
include_once(__DIR__.'/../models/db-settings.php');
include_once(__DIR__.'/../portal/classes/PtCompanyInfo.php');

// register the S3 stream wrapper so it can be used as needed
$s3Client = getS3Client();
$s3Client->registerStreamWrapper();

define( 'BILLING__STORAGE', "s3://".STORAGE_BUCKET."/billing/" );

// Load models
include_once(__DIR__.'/../portal/api/inc_api.php');
include_once(__DIR__.'/classes/BillingRelease.php');
include_once(__DIR__.'/classes/Config.php');
include_once(__DIR__.'/classes/IInvoice.php');
include_once(__DIR__.'/classes/LcMerchantAccount.php');
include_once(__DIR__.'/classes/LcInvoice.php');
include_once(__DIR__.'/classes/LcInvoiceProduct.php');
include_once(__DIR__.'/classes/LcSubscriptionInvoice.php');
include_once(__DIR__.'/classes/LcPlanInfo.php');
include_once(__DIR__.'/classes/LcSubscription.php');
include_once(__DIR__.'/classes/LcUserRole.php');
include_once(__DIR__.'/classes/LcAccount.php');
include_once(__DIR__.'/classes/LcPayWall.php');
include_once(__DIR__.'/classes/LcInvoiceOutput.php');

// load views
include_once(__DIR__.'/classes/LcInvoiceView.php');
include_once(__DIR__.'/classes/LcBillingView.php');

// load content
include_once(__DIR__."/classes/PayWallContent.php");

// load module interfaces
include_once(__DIR__.'/classes/PlanBillingData.php');

// load webhooks / mailer
include_once(__DIR__.'/classes/WebhookProcessor.php');
include_once(__DIR__.'/classes/LcMailer.php');

?>