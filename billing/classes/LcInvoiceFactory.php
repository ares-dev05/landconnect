<?php

class LcInvoiceFactory
{

    /**
     * @param $invoice IInvoice
     * @param $account LcAccount
     */
    public static function generate( $invoice, $account, $addPageBreaks=true )
    {
        self::startPage( $invoice );

        self::displayHeader( $invoice, $account );
        self::displayInvoiceContents( $invoice, $account, $addPageBreaks );
        self::displayFooter();

        self::endPage();
    }

    /**
     * output the html, head tags + required CSS rules
     * @param $invoice IInvoice
     */
    private static function startPage( $invoice )
    {
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <title><?php echo $invoice->getDisplayName(); ?></title>

    <!-- Core CSS -->
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,400i,700,700i" rel="stylesheet">
    <!-- <link rel="stylesheet" href="../css/bootstrap.css"> -->

    <!--
        @UNUSED
        <script src="../js/bootstrap.js"></script>
    -->

    <style>
    /* http://meyerweb.com/eric/tools/css/reset/
       v2.0 | 20110126
       License: none (public domain)
        html, body, div, span, applet, object, iframe,
        h1, h2, h3, h4, h5, h6, p, blockquote, pre,
        a, abbr, acronym, address, big, cite, code,
        del, dfn, em, img, ins, kbd, q, s, samp,
        small, strike, strong, sub, sup, tt, var,
        b, u, i, center,
        dl, dt, dd, ol, ul, li,
        fieldset, form, label, legend,
        table, caption, tbody, tfoot, thead, tr, th, td,
        article, aside, canvas, details, embed,
        figure, figcaption, footer, header, hgroup,
        menu, nav, output, ruby, section, summary,
        time, mark, audio, video {
            margin: 0;
            padding: 0;
            border: 0;
            font-size: 100%;
            font: inherit;
            vertical-align: baseline;
        }
        article, aside, details, figcaption, figure,
        footer, header, hgroup, menu, nav, section {
            display: block;
        }
        body {
            line-height: 1;
        }
        ol, ul {
            list-style: none;
        }
        blockquote, q {
            quotes: none;
        }
        blockquote:before, blockquote:after,
        q:before, q:after {
            content: '';
            content: none;
        }
        table {
            border-collapse: collapse;
            border-spacing: 0;
        }
    */

        body {
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
            background-color: white;
            font-family: Roboto, sans-serif;
            font-size: 11pt;
        }
        h1 {
            padding: 0; margin: 0;
            font-size: 20pt;
            font-weight: normal;
        }

        .row { width:100%; float:left; }

        /* @TODO: add some padding between columns */
        .col-print-1 {width:8%;  float:left;}
        .col-print-2 {width:16%; float:left;}
        .col-print-3 {width:25%; float:left;}
        .col-print-4 {width:33%; float:left;}
        .col-print-5 {width:42%; float:left;}
        .col-print-6 {width:50%; float:left;}
        .col-print-7 {width:58%; float:left;}
        .col-print-8 {width:66%; float:left;}
        .col-print-9 {width:75%; float:left;}
        .col-print-10{width:83%; float:left;}
        .col-print-11{width:92%; float:left;}
        .col-print-12{width:100%; float:left;}

        .col { float:left; }

        .table-header {
            border-bottom: 2pt solid black;
            font-weight: bold;
            padding: 3pt 0;
        }

        .table-line {
            border-bottom: 1pt solid #CCCCCC;
            padding: 3pt 0;
        }

        .table-line-black {
            border-bottom: 1pt solid black;
            padding: 3pt 0;
        }
        .table-line-double {
            border-bottom: 1.5pt solid black;
            padding: 3pt 0;
        }

        .tabulations {
            width: 50%;
            float:right;
        }

        .stamp-holder {
            width: 50%;
            float: left;
            text-align: center;
        }

        .image-center {
            padding-top: 20px;
        }

        /*
        @TODO: do we need this for print?
        * {
            box-sizing: border-box;
            -moz-box-sizing: border-box;
        }
        */

        .page {
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            margin: 10mm auto;
        }

        @page {
            size: A4;
            margin: 0;
        }
        @media print {
            html, body {
                width: 210mm;
                height: 297mm;
            }
            .page {
                margin: 0;
                border: initial;
                border-radius: initial;
                width: initial;
                min-height: initial;
                box-shadow: initial;
                background: initial;
                page-break-after: always;
            }
        }
    </style>
</head>
    <!-- <body onload="window.print()"> -->
    <body>
        <div class="invoice">
            <div class="page container">

<?php
    }

    /**
     * closing tags for the html page
     */
    private static function endPage()
    {
?>
            </div>
        </div>
    </body>
</html>

<?php
    }

    /**
     * Landconnect logo, client details, invoice details, landconnect details
     * @param $invoice IInvoice
     * @param $account LcAccount
     */
    private static function displayHeader( $invoice, $account )
    {
        echo "
                <div class='row'>
                    <img src='content/img/logo.png' width='40%' align='right' style='padding-bottom:30pt' />
                </div>
                <div class='row' style='padding-bottom:30pt;'>
                    <div class='col-print-5' style='padding-right:10pt;'>
                        <!-- invoice name -->
                        <h1>{$invoice->getName()}</h1>
                        <br/>
                        <!-- client details -->
                        {$account->name}<br/>
                        {$account->addressStreet}<br/>
                        {$account->addressCity} {$account->addressRegion} {$account->addressPostalCode}<br/>
                        {$account->addressCountry}<br/>
                        <b>ABN:</b> {$account->getABN()}
                    </div>

                    <div class='col-print-3'>
                        <b>Invoice Date</b><br/>
                        {$invoice->getCreatedAtString()}<br/>
                        <br/>
                        <b>Invoice Number</b><br/>
                        {$invoice->getDisplayName()}<br/>
                        <br/>
                        <b>ABN</b><br/>
                        {$account->getMerchantAccount()->getBusinessABN()}<br/>
                    </div>

                    <div class='col-print-4'>
                        {$account->getMerchantAccount()->getBusinessName()}<br/>
                        {$account->getMerchantAccount()->getBusinessAddress()}<br/>
                        {$account->getMerchantAccount()->getBusinessCity()}<br/>
                        {$account->getMerchantAccount()->getBusinessCountry()}
                    </div>
                </div>
        ";
    }

    /**
     * invoice tabulation
     * @param $invoice IInvoice
     * @param $account LcAccount
     * @param $addPageBreaks bool
     */
    private static function displayInvoiceContents( $invoice, $account, $addPageBreaks=true )
    {
        // load the display settings
        $widths = array( 45, 10, 14.75, 10, 20.25 );
        $labels = array( "Description", "Quantity", "Unit Price", "GST", "Amount ".$account->getCurrencyIsoCode() );

        // @TODO: fill the data array with all the invoice products
        $data   = array();

        // prepare the invoice data

        // @OPTION1 - display each product individually
        /**
         * @var $product LcInvoiceProduct
         */
        foreach ($invoice->getProducts() as $product) {
            $data[] = array(
                $product->getDescription(),
                $product->getQuantity(),
                fmt_amount( $product->getCostNet() ),
                LcAccount::taxPercent()."%",
                fmt_amount( $product->getOverallCostNet() ),
            );
        }

        // @OPTION2 - display products grouped together
        // @OPTION3 - group plan updates and additions, break down subscription payments

        $count  = sizeof( $widths );

        // display the header
        echo "<div class='row table-header' >";
        for ($col=0; $col<$count; ++$col) {
            self::col( $labels[$col], $widths[$col], $col ? "right" : "left" );
        }
        echo "</div>";

        // number of products on the first page
        $firstPageProducts  = 21;
        $innerPageProducts  = 34;

        $productCount       = 0;

        // display the contents
        foreach ($data as $product) {
            // display all the columns for this product
            echo "<div class='row table-line'>";
            for ($col=0; $col<$count; ++$col) {
                self::col( $product[$col], $widths[$col], $col ? "right" : "left" );
            }
            echo "</div>";

            if ( $addPageBreaks ) {
                if (++$productCount >= $firstPageProducts && (
                        ($productCount - $firstPageProducts) % $innerPageProducts == 0)
                ) {
                    // break the page and add a new one
                    echo "</div>";
                    echo "<div class=\"page container\">";
                }
            }
        }

        echo "<div class='row'>";

            echo "<div class='stamp-holder'>";
                echo "<img src='content/img/paid-stamp.png' width='35%' class='image-center' />";
            echo "</div>";

            // display the calculations
            // @TODO: number_format("5.2",2)
            echo "<div class='tabulations'>";
                // row 1: subtotal
                echo "<div class='row'>";
                    self::col("Subtotal", 60, "right");
                    self::col( fmt_amount( $invoice->getNetAmount() ), 40, "right");
                echo "</div>";

                // row 2: tax
                echo "<div class='row table-line-black'>";
                    self::col("Total GST ".LcAccount::taxPercent()."%", 60, "right");
                    self::col( fmt_amount( $invoice->getTaxAmount() ), 40, "right");
                echo "</div>";

                // row 3: Invoice total
                echo "<div class='row'>";
                    self::col("Invoice Total ".$account->getCurrencyIsoCode(), 60, "right");
                    self::col($invoice->getAmount(), 40, "right");
                echo "</div>";

                // row 4: payments
                echo "<div class='row table-line-double'>";
                    self::col("Total Net Payments ".$account->getCurrencyIsoCode(), 60, "right");
                    self::col($invoice->getAmountPaid(), 40, "right");
                echo "</div>";

                // row 5: outstanding
                echo "<div class='row'>";
                    self::col("<b>Amount Due ".$account->getCurrencyIsoCode()."</b>", 60, "right");
                    self::col($invoice->getAmountOutstanding(), 40, "right");
                echo "</div>";

            echo "</div>";

        echo "</div>";
    }

    private static function col( $content, $width, $float )
    {
        echo "\t\t\t\t\t<div class='col' style='width:{$width}%; text-align:{$float}'>{$content}</div>\n";
    }

    /**
     * invoice footer: Landconnect bank details (not needed? or can be placed in header?)
     */
    private static function displayFooter()
    {

    }
}

?>