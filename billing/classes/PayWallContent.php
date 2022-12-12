<?php

class PayWallContent
{
    const PAY_INVOICE_BUTTON_CLASS  = "paywall-pay-invoice";


    public static function start()
    {
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="">
        <meta name="author" content="">

        <title>Landconnect</title>

        <!-- Favicon -->
        <link rel="icon" type="image/x-icon" href="../../css/favicon.ico" />

        <!-- Core CSS -->
        <link rel="stylesheet" href="../css/bootstrap.css">
        <link rel="stylesheet" href="../css/admin.css?v=1">
        <link rel="stylesheet" href="../css/sb-admin.css?v=3">
        <link rel="stylesheet" href="../css/font-awesome.min.css">

        <!-- Core JavaScript -->
        <!-- <script src="../js/jquery-1.10.2.min.js"></script> -->
        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
        <script src="../js/bootstrap.js"></script>
        <script src="../js/userfrosting.js"></script>

        <!-- PayWall assets -->
        <script src="js/paywall.js"></script>
        <link rel="stylesheet" href="content/paywall.css">
        <!-- Billing JavaScript -->
        <script src="js/billing.js"></script>

        <!-- Braintree scripts -->
        <script src="https://js.braintreegateway.com/js/braintree-2.27.0.min.js"></script>
        <script src="https://js.braintreegateway.com/v1/braintree-data.js"></script>

        <!-- Setup Braintree variables -->
        <script type="text/javascript">
            // $.ajaxSetup({ cache: false });

            // define braintree/api vars
            var MERCHANT_ID = '<?php echo BRAINTREE_MERCHANT_ID; ?>';
            var ENVIRONMENT = null;

            function initEnvironment()  {
                ENVIRONMENT = <?php
                    echo IS_BRAINTREE_SANDBOX ?
                        'BraintreeData.environments.sandbox' :
                        'BraintreeData.environments.production';
                    ?>;
            }

            try {
                // document.domain = "landconnect.com.au";
            } catch (err) {}
        </script>
    </head>

    <body>

        <div id="payWallFrame">
            <!-- info panel to display over the entire page when something goes wrong or if the paywall shouldn't appear -->
            <div id="infoPanel" style="display:none; position:absolute;top:0;left:0;width: 100%;height:100%; min-height:500px; z-index:100;">
                <div style="background-color:#FFFFFF; position:absolute;top:0;left:0;width: 100%;height:100%; opacity:0.5;filter: alpha(opacity=50); z-index:101;"></div>
                <div class="centerbox" style="z-index:102; padding:10px;">
                    <div id="infoPanelTitle">
                    </div>
                    <div id="infoPanelContent">
                    </div>
                    <div id="infoPanelLoading" style="text-align:center;">
                        <img style="margin:0 auto; display:block" src="./content/img/ajax-loader-black.gif" id="imgLoader" />
                    </div>
                </div>
            </div>
        <!-- end PayWallFrame -->
        </div>

        <div id="payWallContent" class="container-fluid text-center col-md-6 col-md-offset-3">
		<?php
		}
		public static function end()
		{
	?>
        <!-- END payWallContent -->
        </div>
    </body>
</html>
<?php
    }

    /**
     * displays the initial subscription form and handles payment processing
     * @param LcAccount $account
     */
    public static function subscribeForm( $account )
    {
        $planCost = 0;
        $planStartDate = "";
        $clientName = "";

        if ( $account->getType() == LcAccount::TYPE_TEAM ) {
            // load the company details
            $company = PtCompanyInfo::get($account->getClientId());
            $clientName = $company->name;

            // load the parameter details
            $planParams = $account->loadTeamSubscriptionSettings();

            $planCost   = $planParams["price"];
            if ( isset($planParams["firstBillingDate"]) )
                $planStartDate = "on the ".$planParams["firstBillingDate"]->format( LcSubscription::DATE_DISPLAY_FORMAT );
            else
                $planStartDate = "immediately";
        }   else {
            // @TODO
        }

        self::getPaymentForm(
               "subscribeOnNonceReceived",
               "PURCHASE",
               "<h1>SUBSCRIBE</h1>
                Enter your payment method details to start your subscription.<br/>
                <p>You are subscribing to Footprints as <b>{$clientName}</b></p>
                <p>The monthly cost is \${$planCost} (inclusive of GST) and the first charge will be applied {$planStartDate}</p>"
        );
    }

    /**
     * displays details for overdue accounts, along with a payment form to allow manual payment retries
     * @param LcAccount $account
     */
    public static function overdueForm( $account )
    {
        $overdueSince   = $account->getSubscription()->endsAtString;
        // calling daysOverdue() also loads the Braintree_Subscription
        // Useful fields:
        //  - failureCount
        //      The number of consecutive failed attempts by Braintree's recurring billing engine to charge a subscription. This count includes the transaction attempt that caused the subscription's status to become past due, starting at 0 and increasing for each failed attempt. If the subscription is active and no charge attempts failed, the count is 0.
        //  - nextBillingDate
        //      The date that the gateway will try to bill the subscription again. The gateway adjusts this date each time it tries to charge the subscription. If the subscription is past due and you have set your processing options to automatically retry failed transactions, the gateway will continue to adjust this date, advancing it based on the settings that you configured in advanced settings.
        //  - nextBillingPeriodAmount
        //      The total subscription amount for the next billing period. This amount includes add-ons and discounts but does not include the current balance.

        // we need additional details for Overdue subscriptions
        // @TODO: check that this load of the Braintree_Subscription succeded
        $account->getSubscription()->fetchBraintreeData();

        // number of overdue days
        $overdueDays    = $account->getSubscription()->daysOverdue();
        // next day when the automatic charge will be attempted
        $nextPayDate    = $account->getSubscription()->subscription->nextBillingDate->format( LcSubscription::DATE_DISPLAY_FORMAT );
        // amount overdue
        $overdueAmount  = $account->getSubscription()->subscription->nextBillingPeriodAmount;
        // number of automatic charge failures
        $failCount      = $account->getSubscription()->subscription->failureCount;

        self::getPaymentForm(
               "retryOverdueOnNonceReceived",
               "MAKE PAYMENT",
               "Your account has been overdue for <b>{$overdueDays} days</b> and access to Footprints has been suspended.<br/><br/>
                The overdue amount for your account is <b>{$overdueAmount}</b><br/><br/>
                To regain access to Footprints, please update your billing method or make sure you have enough funds on your current one and make the payment as soon as possible.<br/><br/>
                Otherwise, the payment will automatically be retried on the <b>{$nextPayDate}</b>"
        );
    }

    /**
     * allow users to pick or enter a new payment method for the current subscription
     * @param LcAccount $account
     */
    public static function editSubscriptionForm( $account )
    {
        self::getPaymentForm(
            "editSubscriptionOnNonceReceived",
            "SAVE",
            "<h1>Payment Method</h1>Select the new payment method that you want to use for your Footprints subscription.",
            "false"
        );
    }

    /**
     * allow users to pick or enter a new payment method for the current subscription
     * @param $account LcAccount
     * @param $invoice LcInvoice
     */
    public static function payInvoiceForm( $account, $invoice )
    {
?>
        <!-- content -->
        <div id="payment_section">
            <div id="payment_details">
               
			<h1>Pay Invoice #<?php echo $invoice->getId(); ?></h1>
				<p><?php echo $invoice->getName().": ".$invoice->getDetails(); ?></p>
                <p>Amount due: <b>$<?php echo $invoice->getAmount(); ?></b> (Inclusive of GST)</p>
            </div>

            <div id="payment_feedback" class="error">
            </div>

            <div id="payment_form">
            </div>

            <div class="navigation" id="retryCancelDiv" >
                <div style="clear:both; height:15px;"></div>
                <input id="cancel_subscribe" class="btn btn-default" type="button" value="CANCEL" style="width:200px;">
            </div>
        </div>

        <div id="payment_result" style="padding-top:20px; display: none;">
        </div>

        <!--  -->
        <script type="text/javascript">
            $( document ).ready(function() {
                // set the callback for the nonce received event
                endOnSubscribed     = false;
                // initialize the page
                // loadUserAndShowContent();
                // payWallLoading();
                invoiceId = <?php echo $invoice->getId(); ?>;

                loadInvoicePaymentForm();
            });

            $( "#cancel_subscribe" ).click(
                function() {
                    payWallCancel();
                }
            );
        </script>
<?php
    }

    /**
     * outputs the payment form used for subscriptions and overdue payments
     * @param $nonceCallback
     * @param $purchaseBtnName
     * @param $details
     */
    private static function getPaymentForm( $nonceCallback, $purchaseBtnName, $details, $endOnSubscribed="true" )
    {
?>
        <!-- content -->
        <div id="payment_section">
            <div id="payment_details">
                <?php echo $details; ?>
            </div>

            <div id="payment_feedback" class="error">
            </div>

            <div id="payment_form">
            </div>

            <div class="navigation" id="retryCancelDiv" >
                <div style="clear:both; height:15px;"></div>
                <input id="cancel_subscribe" class="btn btn-default" type="button" value="CANCEL" style="width:200px;">
            </div>
        </div>

        <div id="payment_result" style="padding-top:20px; display: none;">
        </div>

        <!--  -->
        <script type="text/javascript">
            $( document ).ready(function() {
                // set the callback for the nonce received event
                fnOnNonceReceived   = <?php echo $nonceCallback; ?>;
                payButtonName       = "<?php echo $purchaseBtnName; ?>";
                endOnSubscribed     = <?php echo $endOnSubscribed; ?>;
                // initialize the page
                loadUserAndShowContent();
                payWallLoading();
            });

            $( "#cancel_subscribe" ).click(
                function() {
                    payWallCancel();
                }
            );
        </script>
<?php
    }

    public static function guestWall( $overdue )
    {
        if ( $overdue ) {
?>
        Your account is overdue and access to Footprints has been suspended. Please contact your supervisor or Landconnect Support if you have any questions.
<?php
        }   else {
?>
        Your account has not yet been set up, please contact your supervisor or Landconnect Support if you have any questions.
<?php
        }
    }

    /**
     * called when no billing account exists for the currently logged-in user;
     */
    public static function noAccount()
    {
?>
        Your account has not been set up yet. Please contact us so we can get it ready for you as soon as possible!
<?php
    }

    /**
     * @param LcAccount $accoutn
     * called when no billing account exists for the currently logged-in user;
     */
    public static function noUsePermission( $account )
    {
?>
        Sorry, you are not authorized to access this feature. Please contact your supervisor or Landconnect Support if you have any questions.
<?php
    }

    /**
     * @param $message string
     * @param $accoutn LcAccount
     */
    public static function displayError( $message, $account )
    {
        echo $message;

        self::outputCloseButton();
    }

    private static function outputCloseButton()
    {
?>
        <div class="navigation" id="retryCancelDiv" style="padding-top: 20px;" >
            <input id="close_btn" class="btnGray" type="button" value="CLOSE" style="width:200px;">
        </div>

        <script type="text/javascript">
            $( "#close_btn" ).click( payWallCancel );
        </script>
<?php
    }

    /**
     * @param $options
     */
    public static function addPageScripts( $options=null ) {
        $functions = array();

        // add optional functions
        if ( $options ) {
            if ( isset($options["managePaymentsBtn"]) ) {
                // manage payments methods button
                $functions[]= "
            $('#".$options["managePaymentsBtn"]."').click( function() {
                $('#paywall-overlay').html(
                    '<div id=\"subscription-edit\">'+
                    '<iframe id=\"paywall-frame\" width=\"100%\" height=\"100%\" frameborder=\"0\" src=\"./paywall.php?action=manage-subscription\" />'+
                    '</div>'
                );
            });
                ";
            }

            if ( isset($options["subscribeBtn"]) ) {
                // create subscription button
                $functions[]= "
            $('#".$options["subscribeBtn"]."').click( function() {
                $('#paywall-overlay').html(
                    '<div id=\"subscription-create\">'+
				
                    '<iframe id=\"paywall-frame\" frameborder=\"0\" src=\"".self::url('create-subscription')."\" />'+
                    '</div>'
                );
            });
                ";
            }
        }

        $functions_str = join('', $functions);

        echo "
    <script type='text/javascript'>
        $(document).ready(function() {
            // append the paywall overlay
            $(document.body).append('<div id=\"paywall-overlay\"></div>');
            
            // add hook to all the pay-invoice buttons
            $('.".self::PAY_INVOICE_BUTTON_CLASS."').click( function() {
                var invoiceId = $(this).attr('data-id');
                
                $('#paywall-overlay').html(
    '<div id=\"subscription-edit\">'+
        '<iframe id=\"paywall-frame\" width=\"100%\" height=\"100%\" frameborder=\"0\" src=\"".self::url('pay-invoice')."&invoice='+ invoiceId +'\" />'+
    '</div>'
                );
            });
            
            {$functions_str}
        });
        
        function cancelPayWall() {
            $('#paywall-overlay').html('');
        }
        
        function onPayWallSuccess() {
            console.log('on paywall success!');
            
            $('#paywall-overlay').html(
    '<div style=\"width:100%; height:100%; z-index:1050; position:fixed; top:0; left:0; bottom:0; right:0; background:#ffffff; filter: alpha(opacity=60); -moz-opacity: 0.6; opacity: 0.6; \" />'
            );
            
            console.log('reloading from the top!');
            window.top.location.reload(false);
        }
    </script>
        ";
    }

    public static function url($action, $extra="")
    {
        return HOST_PREFIX."/billing/paywall.php?action={$action}{$extra}";
    }
}

?>