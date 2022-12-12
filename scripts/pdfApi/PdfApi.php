<?php

require_once("./pdf-to-image/Pdf.php");
// require_once('./../../models/db-settings.php');
require_once('./../../models/config.php');

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Setup the conversion

if ( isset($_POST["method"]) && $_POST["method"]=='convert' ) {
    // We also want Sitings.io users to be able to run PDF conversions
    // convertPdf2Img();

    if (isUserLoggedIn()) {
        convertPdf2Img();
    }   else {
        outputForbidden();
    }
}	else {
	outputError("error Footprints has updated to a new version; please refresh this page to access it - you may have to re-login to your account.");
}

function convertPdf2Img()
{
	// transfer all the images files to S3 and deliver them from there
	$s3Root = "s3://".STORAGE_BUCKET;

	// create an S3 client and register the stream wrapper
	$s3Client = getS3Client();
	$s3Client->registerStreamWrapper();

	$currentTime = microtime(true)."";
	$target		 = $_FILES[ "pdf_file_data" ][ "tmp_name" ];

	try {
		$pdf = new Spatie\PdfToImage\Pdf($target);
		// update settings to reduce file size
		$pdf->setResolution(120);
		$pdf->setColorspace(1);		// =COLORSPACE_RGB
		$pdf->setCompressionQuality(70);

		$output = $pdf->saveAllPagesAsImages("{$s3Root}/conversions", $currentTime);

		if (sizeof($output)) {
			// re-prefix everything
			array_walk(
				$output,
				function(&$image, $key, $s3Client) {
					/** @var $s3Client \Aws\S3\S3Client */
					// make the image public so it can be read
					$s3Client->putObjectAcl(array(
						'Bucket' => STORAGE_BUCKET,
						'Key'    => str_replace("s3://".STORAGE_BUCKET."/", "", $image),
						'ACL'    => 'public-read'
					));
					$image = str_replace( "s3://", S3_REGION_PATH, $image );
				},
				$s3Client
			);

			// output the paths to the pages
			outputImgSuccess($output);
		} else {
			outputError("We could not load this PDF. Please make sure it is not password-protected and contact us if the issue persists.");
		}
	}	catch ( Exception $e ) {
		outputError("An issue occurred while uploading your file to our server. Please contact us if the issue persists at support@landconnect.com.au");
		error_log("pdf-conversion ".$e->getMessage());
	}
}

function outputImgSuccess($pages)
{
	header('Content-Type: text/xml');
	echo "<data success='true' format='img'>\n";
	foreach ($pages as $pagePath) {
		echo "\t<fileName>{$pagePath}</fileName>\n";
	}
	echo "</data>";
}

function outputError($msg)
{
	header('Content-Type: text/xml');
	echo "
<data success='false'>
	<msg><![CDATA[$msg]]></msg>
</data>";
}

function outputForbidden()
{
    outputError("ERROR - YOU MUST REFRESH BROWSER NOW");
}

?>