<?php

class LcInvoiceOutput {

    // name of the wk html to pdf tool
    // @MD 30JAN2018 - WKhtml doesn't run directly on the EC2 instance, but does run through the xvfb display server
    const GENERATOR         = "xvfb-run wkhtmltopdf --quiet";
    // path to the script that's used to generate invoice PDFs
    const SCRIPT_PATH       = "https://www.landconnect.com.au/billing/generate_invoice.php";
    // secret key that's used to confirm that the generation script is called from here
    const GENERATE_KEY      = "4eVDKb6Qhvv5vnMz8zjz9KEC;mFRwxWPQcGXqtjvv7G65RbKA;erVQ8Td2LmrdtGkX9bqQKuHk";
    // path to the folder that stores the PDF invoice outputs
    // const PDF_OUTPUT_FOLDER = __DIR__."/../pdf/";

    // path to the invoice output script
    const OUTPUT_PATH       = "/billing/output_invoice.php";
    // valid invoice output formats
    const FORMAT_PRINT      = "print";
    const FORMAT_PDF        = "pdf";

    /**
     * @MD 28APR18 - switched to S3 stream wrapper
     */
    private static function PDF_OUTPUT_FOLDER() {
        return BILLING__STORAGE;
    }

    /**
     * output an invoice in the current page in a specified format
     * @param $invoice IInvoice
     * @param $format string
     */
    public static function outputByFormat( $invoice, $format, $billingAccount, $overwriteIfExists=false )
    {
        if ( $invoice && $format ) {
            if ( $format == self::FORMAT_PRINT ) {
                require_once("LcInvoiceFactory.php");

                LcInvoiceFactory::generate(
                    $invoice,
                    $billingAccount
                );
            }   else
            if ( $format == self::FORMAT_PDF ) {

                // generate the PDF
                $fileName = self::generatePDF( $invoice, $overwriteIfExists );

                if ( $fileName !== false ) {
                    $downloadName   = $invoice->getDisplayName().".pdf";

                    // set the output type as PDF
                    header("Content-type:application/pdf");
                    // set the download name
                    header("Content-Disposition:attachment;filename='{$downloadName}'");
                    // output the file; don't display errors
                    @readfile( $fileName );
                }   else {
                    // couldn't generate the file!
                    addAlert('warning', "Couldn't generate a PDF for this invoice at the moment; please retry later or open the print mode instead.");
                    header('Location:.');
                }
            }
        }
    }

    /**
     * @param $invoice IInvoice
     * @param $format string
     * @return string output url for invoices
     */
    public static function getOutputUrl( $invoice, $format )
    {
        // build the url
        return self::OUTPUT_PATH . "?" . join( "&", array(
            "id=".$invoice->getId(),
            "type=".$invoice->typeName(),
            "format=".$format
        ) );
    }

    /**
     * @param $invoice IInvoice
     * @param $outputPath string
     * @return mixed path on success, false on failure
     */
    public static function generatePDF($invoice, $overwriteIfExists=false )
    {
        // @TEMPORARY!!
        $overwriteIfExists = true;
        
        // generate a unique file name for the invoice from its unique details
        $fileName   = $invoice->getPDFFileName();

        if ( !$fileName ) {
            // invoice has not been paid yet, can't generate a PDF for it
            Config::apiError("The invoice has not been paid yet, can't generate a PDF for it");
            return false;
        }

        // build the output path
        $outputPath = self::PDF_OUTPUT_FOLDER() . $fileName;

        // if the file exists already and we don't have to overwrite it, report a success
        if ( file_exists($outputPath) && !$overwriteIfExists ) {
            return $outputPath;
        }

        // build the URL to the generate file
        $params = array(
            "key=".self::GENERATE_KEY,
            "vid=".$invoice->getId(),
            "type=".$invoice->typeName()
        );

        // prepare the batch command to execute in the following format:
        // we output the wkhtmltopdf result to stdout and then write the content to a file
        $command =  self::GENERATOR . " \"" .
            self::SCRIPT_PATH . "?" . join("&", $params) . "\" -";

        $RETRY_COUNT = 3;

        // retry up to {$RETRY_COUNT} times in case the generation fails
        for ($attempt=1; $attempt<=$RETRY_COUNT; ++$attempt) {
            // run the command
            $output = shell_exec($command);

            // check for common fail messages in the output
            if (strstr($output, "Exit with code") === false &&
                strstr($output, "due to network error") === false &&
                strstr($output, "UnknownServerError") === false) {

                if (strlen($output) < 10000) {
                    error_log("Suspicious output size: ".strlen($output)." But no 'network error'/'UnknownServerError' in content.");
                }

                // looks like a successful generation; write the output to the path
                file_put_contents($outputPath, $output);

                if ( file_exists($outputPath) ) {
                    return $outputPath;
                }   else {
                    error_log("Failed to write the invoice output to {$outputPath} on attempt {$attempt}. Retrying. Check stderr.");
                }

                break;
            }   else {
                // invoice generation failed
                error_log("Failed invoice generation on attempt {$attempt}; size=".strlen($output).": command= {$command}");
            }
        }

        return false;
    }
}

?>