<?php

class LcInvoiceView
{

    private static function sentence_case($string) {
        $sentences = preg_split('/([.?!_ ]+)/', $string, -1, PREG_SPLIT_NO_EMPTY|PREG_SPLIT_DELIM_CAPTURE);
        $new_string = '';
        foreach ($sentences as $key => $sentence) {
            $new_string .= ($key & 1) == 0?
                ucfirst(strtolower(trim($sentence))) :
                $sentence.' ';
        }
        return trim($new_string);
    }

    private static function translateTransactionStatus( $status )
    {
        return self::sentence_case( $status );
    }

    /**
     * @param $invoice LcInvoice
     * @param $account LcAccount
     */
    public static function pageTemplate( $invoice, $account )
    {
        echo '
<body>
    <div id="wrapper">
        <nav class="app-nav" role="navigation">
		</nav>

        <div id="page-wrapper">
            <div class="container-fluid">
                <div class="page-header">
                    <h1>'.$invoice->getDisplayName().'</h1>
                </div>
            
                <div class="row">
                    <div id="display-alerts" class="col-lg-12"></div>
                </div>
                
                <div class="row" id="details">
                    <div class="col-lg-12">
                        ' . self::getInvoiceDetails( $invoice, $account ) . '
                    </div>
                </div>
                
                <div class="row" id="products">
                    <div class="col-lg-12">
                        ' . self::getInvoiceProducts( $invoice, $account ) . '
                    </div>
                </div>
            </div><!-- /.container-fluid --> 
        </div><!-- /#page-wrapper -->
    </div><!-- /#wrapper -->
    
    <div id="overlay" />';
    }

    /**
     * @param $invoice LcInvoice
     * @param $account LcAccount
     */
    private static function getInvoiceDetails( $invoice, $account )
    {
        $operations         = '';

        // prepare the invoice status
        if ( $invoice->hasNoPaymentAttempt() ) {
            $invoiceStatus  = "<span class='label label-default'>Not Paid</span>";
            $operations     .= "
                <button class='".PayWallContent::PAY_INVOICE_BUTTON_CLASS." btn btn-md btn-success' data-id='{$invoice->getId()}' >
                    Pay Now
                </button>
            ";
        }   else
        if ( $invoice->isPaidOrSettling() ) {
            $invoiceStatus = "<span class='label label-success'>Paid</span>";

            // generate the print/pdf download links
            $printUrl       = LcInvoiceOutput::getOutputUrl( $invoice, LcInvoiceOutput::FORMAT_PRINT );
            $downloadUrl    = LcInvoiceOutput::getOutputUrl( $invoice, LcInvoiceOutput::FORMAT_PDF );

            if ( BillingRelease::INVOICE_DOWNLOAD_READY ) {
                $operations .= "
                    <a id='btn-download' class='btn btn-md btn-default' href='{$downloadUrl}' target='_blank' role='button'>
                        <span class='glyphicon glyphicon-download-alt'></span>
                        PDF
                    </a>
                ";
            }
            if ( BillingRelease::INVOICE_PRINT_READY ) {
                $operations .= "
                    <a id='btn-print' class='btn btn-md btn-primary' href='{$printUrl}' target='_blank' role='button'>
                        <span class='glyphicon glyphicon-print'></span>
                        Print Invoice
                    </a>
                ";
            }
        }   else
        if ( !$invoice->getTransaction() ) {
            // unknown ?
            $invoiceStatus = "<span class='label label-default'>Unknown</span>";
        }   else {
            $invoiceStatus = "<span class='label label-danger'>".self::translateTransactionStatus( $invoice->getTransaction()->status )."</span>";
            $operations     .= "
                <a id='btn-pay' class='btn btn-md btn-success' href='#' target='_blank' role='button'>
                    Retry payment
                </a>
            ";
        }

        $invoiceCost        = LcAccount::formatCurrencyWithIsoCode(
            $invoice->getAmount(),
            $account->getCurrencyIsoCode()
        );

        return "
        <div class='panel panel-default'>
            <div class='panel-heading'>
                <span class='pull-left'>
                    {$invoice->getName()}
                </span>
                <div class='clearfix'></div>
            </div>
            <div class='panel-body'>
                <div class='row'>
                    <div class='col-sm-3 detail-header'>
						Details
                    </div>
                    <div class='col-sm-9'>
                        {$invoice->getDetails()}
                    </div>
                </div>

                <div class='row'>
                    <div class='col-sm-3 detail-header'>
                        Generated on
                    </div>
                    <div class='col-sm-9'>
                        {$invoice->getCreatedAt()->format(LcSubscription::DATE_DISPLAY_FORMAT)}
                    </div>
                </div>
                
                <div class='row'>
                    <div class='col-sm-3 detail-header'>
						Overview
                    </div>
                    <div class='col-sm-9'>
                        {$invoice->getOverview()}
                    </div>
                </div>
                
                <div class='row'>
                    <div class='col-sm-3 detail-header'>
                        Cost
                    </div>
                    <div class='col-sm-9'>
                        {$invoiceCost}
                    </div>
                </div>
				
                <div class='row'>
                    <div class='col-sm-3 detail-header'>
						Status
                    </div>
                    <div class='col-sm-9'>
                        {$invoiceStatus}  
                    </div>
                </div>
                
                <div class='row'>
                    <div class='col-sm-12' style='text-align: center;'>
                        {$operations}
                    </div>
                </div>
            </div>
        </div>";
    }

    /**
     * @param $invoice LcInvoice
     * @param $account LcAccount
     */
    private static function getInvoiceProducts( $invoice, $account )
    {
        $invoiceDisplay = array();
        $indx = 1;

        /**
         * @var $product LcInvoiceProduct
         */
        foreach ( $invoice->getProducts() as $product ) {
            $invoiceDisplay[]= self::planProductRowTemplate( $indx++, $product, $account );
        }

        return '
        <div class=\'panel panel-default\'>
            <div class=\'panel-heading\'>
                <span class=\'pull-left\'>
                    Items
                </span>
                <div class=\'clearfix\'></div>
            </div>
            <div class=\'panel-body\'>
				<div class=\'row\' style=\'padding-bottom:5px;\'>
                    <div class=\'col-sm-8\'>
                        <span class=\'detail-header\'>Item Details</span>
                    </div>
                    <div class=\'col-sm-2\'>
						<span class=\'detail-header\'>Item Type</span>
                    </div>
					<div class=\'col-sm-2\'>
						<span class=\'detail-header\'>Cost</span>
                    </div>
                </div>
				
                '.join("\n", $invoiceDisplay).'
            </div>
        </div>';
    }

    /**
     * @param $product LcInvoiceProduct
     * @param $account LcAccount
     */
    private static function planProductRowTemplate( $indx, $product, $account )
    {
        $productTypeLabel   = "";
        $productInfo        = "";

        // build the plan pieces
        $planPieces         = array();
        if (strlen($product->getSafeTargetName())) {
            $planPieces []= $product->getSafeTargetName();
        }
        if (strlen($product->getSafePlanName())) {
            $planPieces []= $product->getSafePlanName();
        }

        // prepare the product type label
        switch ( $product->getType() ) {
            case LcInvoiceProduct::PRODUCT_PLAN_ADDITION:
                $productTypeLabel = "<span class='label label-success'>New Plan</span>";
                $productInfo      = $indx.". ".join($planPieces, " / ");
                break;

            case LcInvoiceProduct::PRODUCT_PLAN_UPDATE:
                $productTypeLabel = "<span class='label label-primary'>Update</span>";
                $productInfo      = $indx.". ".join($planPieces, " / ");
                break;

            case LcInvoiceProduct::PRODUCT_SUBSCRIPTION:
                $productTypeLabel = "<span class='label label-warning'>Subscription</span>";
                $productInfo      = $indx.". ".$product->message;
                break;

            case LcInvoiceProduct::PRODUCT_BULK_ADDITION:
                $productTypeLabel = "<span class='label label-success'>New Plans</span>";
                $productInfo      = $indx.". ".$product->message;
                break;

            case LcInvoiceProduct::PRODUCT_LOTMIX_LEAD:
                $productTypeLabel = "<span class='label label-info'>Lotmix Leads</span>";
                $productInfo      = $indx.". ".$product->getQuantity()." Lotmix Lead".($product->getQuantity()!=1 ? "s" : "");
                break;
        }

        $productCost        = LcAccount::formatCurrencyWithIsoCode(
            $product->cost * $product->getQuantity(),
            $account->getCurrencyIsoCode(),
            false
        );

        return "
                <div class='row' style='padding:2px 0px'>
                    <div class='col-sm-8'>
                        {$productInfo}
                    </div>
                    <div class='col-sm-2'>
						{$productTypeLabel}
					</div>
					<div class='col-sm-2'>
						{$productCost}
                    </div>
                </div>
            ";
    }

    /**
     * @param $invoice LcInvoice
     * @param $account LcAccount
     */
    public static function rowTemplate( $invoice, $account )
    {
        //
        $operations = "";

        // prepare the invoice status
        if ( $invoice->hasNoPaymentAttempt() ) {
            $invoiceStatus  = "<span class='label label-default'>Not Paid</span>";
            $operations     .= "
                <button class='".PayWallContent::PAY_INVOICE_BUTTON_CLASS." btn btn-xs btn-success' data-id='{$invoice->getId()}' >
                    Pay Now
                </button>
            ";
        }   else
        if ( $invoice->isPaidOrSettling() ) {
            $invoiceStatus = "<span class='label label-success'>Paid</span>";

            // generate the print/pdf download links
            $printUrl       = LcInvoiceOutput::getOutputUrl( $invoice, LcInvoiceOutput::FORMAT_PRINT );
            $downloadUrl    = LcInvoiceOutput::getOutputUrl( $invoice, LcInvoiceOutput::FORMAT_PDF );

            if ( BillingRelease::INVOICE_DOWNLOAD_READY ) {
                $operations .= "
                    <a id='btn-download' class='btn btn-xs btn-default' href='{$downloadUrl}' target='_blank' role='button'>
                        <span class='glyphicon glyphicon-download-alt'></span>
                        PDF
                    </a>
                ";
            }
            if ( BillingRelease::INVOICE_PRINT_READY ) {
                $operations .= "
                    <a id='btn-print' class='btn btn-xs btn-primary' href='{$printUrl}' target='_blank' role='button'>
                        <span class='glyphicon glyphicon-print'></span>
                        Print
                    </a>
                ";
            }

            // @TODO: add print button
        }   else
        if ( !$invoice->getTransaction() ) {
            // unknown ?
            $invoiceStatus = "<span class='label label-default'>Unknown</span>";
        }   else {
            $invoiceStatus = "<span class='label label-danger'>".self::translateTransactionStatus( $invoice->getTransaction()->status )."</span>";
            $operations     .= "
                <a id='btn-pay' class='btn btn-xs btn-success' href='#' target='_blank' role='button'>
                    Retry payment
                </a>
            ";
        }

        $creationDate       = $invoice->getCreatedAt()->format( Config::DISPLAY_DATETIME );

        // count the product types
        $typesInfo          = $invoice->getOverview();

        $invoiceCost        = LcAccount::formatCurrencyWithIsoCode(
            $invoice->getAmount(),
            $account->getCurrencyIsoCode(),
            false
        );

        return "
                <div class='row invoice-row'>
                    <div class='col-sm-5'>
                        <a href='/billing/invoice.php?id={$invoice->getId()}'>
                            <b>{$invoice->getName()}</b>
                        </a><br />
                        <span>{$typesInfo}</span>
                    </div>
                    <div class='col-sm-2'>
						{$creationDate}
                    </div>
					<div class='col-sm-1'>
						{$invoiceCost}
                    </div>
					<div class='col-sm-1'>
						{$invoiceStatus}
					</div>
					<div class='col-sm-3'>
						<a id='btn-view' class='btn btn-xs btn-default' href='/billing/invoice.php?id={$invoice->getId()}' role='button'>
                            Details
                        </a>
                        {$operations}
					</div>
                </div>
            ";

        /*
                        <a id='btn-download' class='btn btn-xs btn-default' href='#' target='_blank' role='button'>
							<span class='glyphicon glyphicon-download-alt'></span>
                            Download
                        </a>
         */
    }

    /**
     * @param $invoice LcSubscriptionInvoice
     * @param $account LcAccount
     */
    public static function subRowTemplate( $invoice, $account )
    {
        //
        $operations = "";

        // prepare the invoice status
        $invoiceStatus  = "<span class='label label-success'>Paid</span>";

        // $printUrl       = "/billing/output_invoice.php?id={$invoice->getId()}&type=".LcSubscriptionInvoice::typeName();
        // generate the print/pdf download links
        $printUrl       = LcInvoiceOutput::getOutputUrl( $invoice, LcInvoiceOutput::FORMAT_PRINT );
        $downloadUrl    = LcInvoiceOutput::getOutputUrl( $invoice, LcInvoiceOutput::FORMAT_PDF );

        if ( BillingRelease::INVOICE_DOWNLOAD_READY ) {
            $operations .= "
                <a id='btn-download' class='btn btn-xs btn-default' href='{$downloadUrl}' role='button'>
                    <span class='glyphicon glyphicon-download-alt'></span>
                    PDF
                </a>
            ";
        }
        if ( BillingRelease::INVOICE_PRINT_READY ) {
            $operations .= "
                <a id='btn-print' class='btn btn-xs btn-primary' href='{$printUrl}' target='_blank' role='button'>
                    <span class='glyphicon glyphicon-print'></span>
                    Print
                </a>
            ";
        }

        $creationDate       = $invoice->getCreatedAt()->format( Config::DISPLAY_DATETIME );
        $details            = $invoice->getDetails();

        $invoiceCost        = LcAccount::formatCurrencyWithIsoCode(
            $invoice->getAmount(),
            $account->getCurrencyIsoCode(),
            false
        );

        return "
                <div class='row invoice-row'>
                    <div class='col-sm-5'>
                        <a href='$printUrl'>
                            <b>{$invoice->getName()}</b>
                        </a><br />
                        <span>{$details}</span>
                    </div>
                    <div class='col-sm-2'>
						{$creationDate}
                    </div>
					<div class='col-sm-1'>
						{$invoiceCost}
                    </div>
					<div class='col-sm-1'>
						{$invoiceStatus}
					</div>
					<div class='col-sm-3'>
					    <!--
						<a id='btn-view' class='btn btn-xs btn-default' href='/billing/invoice.php?id={$invoice->getId()}' role='button'>
                            Details
                        </a>
                        -->
                        {$operations}
					</div>
                </div>
            ";

        /*
                        <a id='btn-download' class='btn btn-xs btn-default' href='#' target='_blank' role='button'>
							<span class='glyphicon glyphicon-download-alt'></span>
                            Download
                        </a>
         */
    }
}

?>