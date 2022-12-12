<?php

class LcBillingView {
    
    /**
     * @var $account LcAccount      the current billing account
     */
    private $account;

    /**
     * LcBillingView constructor.
     * @param LcAccount $account
     */
    public function __construct( $account=null ) {
        if ( $account ) {
            // use the given account
            $this->account = $account;
        }   else {
            // load the account for the currently logged-in user
            $this->loadAccount();
        }
    }

    /**
     * @return LcAccount the billing account associated to the currently logged-in user
     */
    public function billingAccount() { return $this->account; }

    /**
     * displayBillingSection
     * output the entire statistics page
     */
    public function displayBillingSection() {
        global $stats;

        // first make sure that the user has access to the billing section
        $this->verifyPermissions();

        // output the HTML head (scripts, stylesheets, etc.)
        $this->getHeader();

        $start = t();
        // output the HTML content/body of the page
        $this->getContent();
        $stats["billing-content"] = t($start);

        // attach the page script
		$this->getPageScript();

        // output the closing tags for the page
        $this->endPage();
    }

    /**
     * displayInvoice
     * output a single invoice + payment options
     *
     * @param $invoice LcInvoice
     */
    public function displayInvoice( $invoice )
    {
        // first make sure that the user has access to the billing section
        $this->verifyPermissions( false );

        // output the HTML head (scripts, stylesheets, etc.)
        $this->getHeader();

        // output the invoice page
        LcInvoiceView::pageTemplate( $invoice, $this->account );

        // attach the page script
        $this->getPageScript();

        // output the closing tags for the page
        $this->endPage();
    }

    /**
     * return the portal payments section
     */
    public function getPlanPortalPayments( )
    {
        // verify that the user can make Plan Portal payments
        // don't display the invoices before a payment method gets associated with this billing account

        if ( $this->account->getUserRole()->canMakePayments() &&
             $this->account->getSubscription() != NULL ) {

            // load all the outstanding invoices on this account
            $invoices = $this->account->getOutstandingInvoices(false);

            // only display the billing section when there are outstanding invoices to be paid
            if ( $invoices && sizeof($invoices) > 0 ) {
                $invoiceDisplay = array();

                /**
                 * @var $invoice LcInvoice
                 */
                foreach ( $invoices as $invoice ) {
                    $invoiceDisplay[]= LcInvoiceView::rowTemplate( $invoice, $this->account );
                }

                return '
                <div class=\'panel panel-default\'>
                    <div class=\'panel-heading\'>
                        <span class=\'pull-left\'>
                            Outstanding Invoices
                        </span>
                        <div class=\'clearfix\'></div>
                    </div>
                    <div class=\'panel-body\'>
                        <div class=\'row label-row\'>
                            <div class=\'col-sm-5\'>
                                Invoice
                            </div>
                            <div class=\'col-sm-2\'>
                                Date
                            </div>
                            <div class=\'col-sm-1\'>
                                Cost
                            </div>
                            <div class=\'col-sm-1\'>
                                Status
                            </div>
                            <div class=\'col-sm-3\'>
                                Actions
                            </div>
                        </div>
                        
                        '.join("\n", $invoiceDisplay).'
                    </div>
                </div>';
            }
        }

        return '';
    }

    private function loadAccount()
    {
        // load billing account details for the currently logged-in user
        global $loggedInUser;
        $this->account  = LcAccount::getForSelf( $loggedInUser, true );
    }

    /**
     * verifyPermissions
     * make sure that a user is logged-in and that he can access this page
     */
    private function verifyPermissions( $fullPermissions=true )
    {
        if ( !$this->account ) {
            // an error should've been generated already, it will be displayed at the top of the page
            // addAlert("danger", "Whoops, looks like you don't have permission to view that page.");
            header("Location: ../account/index.php");
            exit();
        }

        // check that the user has access to the billing section
        if ( !$this->account->role()->canManageBilling() && !(
                !$fullPermissions && $this->account->role()->canMakePayments()
            ) ) {
            // Forward to index page
            addAlert("danger", $fullPermissions ?
                $this->account->role()->manageBillingDenied() :
                $this->account->role()->paymentsDenied()
            );
            header("Location: ../account/index.php");
            // refresh
            // header("Location: ../account/index.php");
            exit();
        }
    }

    private function getHeader()
    {
        echo '
<!DOCTYPE html><html lang="en">


<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Landconnect</title>

    <!-- Page Specific Plugins -->
	<link rel="stylesheet" href="../css/bootstrap-switch.min.css" type="text/css" />
    <!-- <link rel="stylesheet" href="css/portal.css" type="text/css" /> -->
    ';

        require_once("../account/includes.php");

        echo '
	<script src="../js/date.min.js"></script>
    <script src="../js/handlebars-v1.2.0.js"></script>
    <script src="../js/bootstrap-switch.min.js"></script>
    <script src="../js/jquery.tablesorter.js"></script>
    <script src="../js/tables.js"></script>

    <script type="text/javascript" src="//ajax.aspnetcdn.com/ajax/globalize/0.1.1/globalize.min.js"></script>

    <!-- Include selectize -->
    <script src="../js/standalone/selectize.js"></script>
    <link rel="stylesheet" href="../css/selectize.bootstrap2.css">

    <!-- include bootstrap daterange picker -->
    <link rel="stylesheet" type="text/css" media="all" href="../js/daterange/daterangepicker-bs3.css" />
    <script type="text/javascript" src="../js/daterange/moment.min.js"></script>
    <script type="text/javascript" src="../js/daterange/daterangepicker.js"></script>

    <!-- include bootstrap multiselect -->
    <script type="text/javascript" src="../js/bootstrap-multiselect.js"></script>
    <link rel="stylesheet" href="../css/bootstrap-multiselect.css" type="text/css"/>

   
</head>';
    }

    /**
     * getContent
     * output the HTML body of the page
     * @return string
     */
    private function getContent()
    {
        // @TODO: use different displays for Enterprise / Single-User / Admin accounts ?
        echo '
<body>
    <div id="wrapper">
        <!-- Sidebar -->
        <nav class="app-nav" role="navigation">
		</nav>

        <div id="page-wrapper">
            <div class="container-fluid">
                <div class="page-header">
                    <h1>Billing Management</h1>
                </div>
                
                <div class="row">
                    <div id="display-alerts" class="col-lg-12"></div>
                </div>
    
                <div class="row" id="subscription">
                    <div class="col-lg-12">
                        ' . $this->getServiceDetails() . '
                    </div>
                </div>
                
                <div class="row" id="invoices">
                    <div class="col-lg-12">
                        ' . $this->getInvoices() . '
                    </div>
                </div>
            </div><!-- /.container-fluid -->
        </div><!-- /#page-wrapper -->
    </div><!-- /#wrapper -->
';

    }

    /**
     * getBillingSettings
     * display general settings related to the billing account, including the preferred payment method
     */
    private function getBillingSettings( )
    {
        if ( !$this->account->getSubscription() ) {
            // @TODO: do we need to handle this case ?
            return "";
        }

        // load the payment method details attached to this subscription
        $this->account->getSubscription()->attachPaymentMethodInfo();

        $paymentMethodMask = $this->account->getSubscription()->paymentMethodMask;

        return '
        <div class=\'panel panel-default\'>
            <div class=\'panel-heading\'>
                <span class=\'pull-left\'>
                    Billing Settings
                </span>
                <div class=\'clearfix\'></div>
            </div>
            <div class=\'panel-body panel-details\'>
                <div class=\'row\'>
                    <div class=\'col-sm-3 detail-header\'>
                        <span class=\'fa fa-credit-card\'></span>
                        Payment Method<br/>
                        (this is the default payment method that is charged for the subscription)
                    </div>
                    <div class=\'col-sm-9\'>
                        '.$paymentMethodMask.'<br/>
                        <a id=\'btn-manage-payment-methods\' class=\'btn btn-info\' href=\'#\' role=\'button\'>
                            Manage
                        </a>
                    </div>
                </div>
            </div>
        </div>';
    }

    /**
     * getServiceDetails
     * display general settings related to the billing account, including the preferred payment method
     */
    private function getServiceDetails()
    {
        global $stats;
        $start = time();
        if ( !$this->account->getSubscription() ) {
            $projectedCost  = $this->account->getMerchantAccount()->formatCurrency(
                $this->account->price,
                false
            );

            // start date
            if ( $this->account->startNextMonth ) {
                $billingStartsAt = "Your first billing date is on the ".
                    $this->account->getSubscriptionStartDate()->format("jS \of F Y");
            }   else {
                $billingStartsAt = "The first charge will be billed immediately.";
            }

            // return a form offering a link to set up the subscription
            return '
            <div class=\'panel panel-default\'>
                <div class=\'panel-heading\'>
                    <span class=\'pull-left\'>
                        Footprints subscription
                    </span>
                    <div class=\'clearfix\'></div>
                </div>
                
                <div class=\'panel-body\'>
                    <div class=\'row\'>
                        <div class=\'col-sm-12 text-center\'>
                            <h1 style="font-size: 24px;">
                                Welcome to your payment portal.
                            </h1>
                            
                            <p>
                                To set up your credit card billing, please click subscribe.<br/>
                                Your monthly cost is '.$projectedCost.' (inclusive of GST)<br/>
                                '.$billingStartsAt.'
                            </p>
                            
                            <p><i>
                                Payments are conducted via Braintree, a PayPal Company.<br/>
                                All cardholder data is stored and processed securely on their side,<br/>
                                in compliance with the PCI data security standard.
                            </i></p>
						</div>
                    </div>
                    
                    <div class=\'row\'>
                        <div class=\'col-sm-12\' style=\'text-align: center;\'>
                            <button id=\'btn-create-subscription\' data-id=\'{$invoice->getId()}\' class=\'btn btn-md btn-success\' >
                                Subscribe
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            ';
        }

        $subscription = $this->account->getSubscription();
        $subscription->fetchBraintreeData();

        // I. Prepare the status details for this account
        $statusDetails = "";

        if ( $subscription->status == Braintree_Subscription::PENDING ) {
            $statusDetails = "
                <span class='label label-warning'>Pending</span>
                First charge will be on the ".
            $subscription->startsAtString;
        }   else
        if ( $subscription->status == Braintree_Subscription::ACTIVE ) {
            // check the number of days left on the subscription
            $daysLeft       = $subscription->getDaysLeft();
            $paidThrough    = $subscription->endsAtString;

            if ( $daysLeft <= 3 ) {
                // display a warning
                $statusDetails = "
                    <span class='label label-warning'>Renews in {$daysLeft} days</span>
                    (Please make sure your payment method has enough funds)
                ";
            }   else {
                // everything is in order
                $statusDetails = "
                   <span class='label label-success'>Paid</span>
                    through {$paidThrough}
                ";
            }
        }   else
        if ( $subscription->isInGracePeriod() ) {
            // @TODO: this branch is incorrectly triggered for subscriptions that have a delayed start (at the end of the month)
            $daysOverdue    = $subscription->daysOverdue();

            LcAccount::formatCurrency(
                $subscription->subscription->balance,
                $subscription->subscription->merchantAccountId
            );

            $failCount      = $subscription->subscription->failureCount;
            // $paidThrough    = $subscription->subscription->paidThroughDate->format(LcSubscription::DATE_DISPLAY_FORMAT);
            $paidThrough    = "N/A";
            if ( $subscription && $subscription->subscription && $subscription->subscription->paidThroughDate ) {
                $paidThrough    = $subscription->subscription->paidThroughDate->format(LcSubscription::DATE_DISPLAY_FORMAT);
            }

            $daysLeft       = LcSubscription::PAST_DUE_GRACE_PERIOD - $daysOverdue;
            $daysLeftFmt    = !$daysLeft ? "today" : "in ".($daysLeft==1?"one day":$daysLeft." days");

            $statusDetails = "
                <span class='label label-danger'>Overdue</span>
				{$overdueBalance} overdue since {$paidThrough}.<br/>
                Account access will be interrupted {$daysLeftFmt} unless the overdue balance is paid before.
                Please pay immediately to avoid service interruption or contact us if you encounter any issues with payment processing.<br/>
                <a id='btn-print' class='btn btn-info' href='#' target='_blank' role='button'>
                    Pay Now
                </a>
            ";
        }

        // II. Prepare details for the next charge period amount/date
        $nextBillingCost    = LcAccount::formatCurrency(
            $subscription->subscription->nextBillingPeriodAmount,
            $subscription->subscription->merchantAccountId
        );
        $nextBillingDate    = $subscription->subscription->nextBillingDate->format( LcSubscription::DATE_DISPLAY_FORMAT );

        // III. Prepare details for the default payment method that is used for this subscription
        $this->account->getSubscription()->attachPaymentMethodInfo();
        $paymentMethodMask  = $this->account->getSubscription()->paymentMethodMask;

        // IV. Build the transactions summary
        $transactions       = $subscription->subscription->transactions;

        // if ( sizeof($transactionsList) ) {
        if ( sizeof($transactions) ) {
            // fetch the most recent transaction
            $transaction         = $transactions[0];
            $dateFmt             = $transaction->createdAt->format(LcSubscription::DATE_DISPLAY_FORMAT);
            // $latestTransactionSummary    = join("<br/>", $transactionsList);
            $latestTransactionSummary = "
                {$transaction->currencyIsoCode} {$transaction->amount} on {$dateFmt} 
            ";

            $transactionsBody   = $this->getSubscriptionInvoices( $transactions );
            
            if ( $this->account->getUserRole()->user()->company_id==2 ) {
                // Simonds: display history
                $transactionsBody .= $this->getArchivedSubscriptionInvoices();
            }
        }   else {
            $latestTransactionSummary   = "there are no transactions associated with this subscription";
            $transactionsBody           = '';
        }

        $stats["billing-content-overview"] = t($start);

        return '
        <div class=\'panel panel-default\'>
            <div class=\'panel-heading\'>
                <span class=\'pull-left\'>
                    Footprints Subscription
                </span>
                <div class=\'clearfix\'></div>
            </div>
            <div class=\'panel-body panel-details\'>
                <div class=\'row\'>
                    <div class=\'col-sm-3 detail-header\'>
                        Payment Method
                    </div>
                    <div class=\'col-sm-9\'>
                        '.$paymentMethodMask.'&nbsp;
                        <a id=\'btn-manage-payment-methods\' class=\'btn btn-xs btn-info\' href=\'#\' role=\'button\'>
                            Change payment method
                        </a>
                    </div>
                </div>

                <div class=\'row\'>
                    <div class=\'col-sm-3 detail-header\'>
                        Status
                    </div>
                    <div class=\'col-sm-9\'>
                        '.$statusDetails.'
                    </div>
                </div>
				
                <div class=\'row\'>
                    <div class=\'col-sm-3 detail-header\'>
                        Cost
                    </div>
                    <div class=\'col-sm-9\'>
                        '.$nextBillingCost.' 
                    </div>
                </div>
				
				<div class=\'row\'>
                    <div class=\'col-sm-3 detail-header\'>
                        Next billing date
                    </div>
                    <div class=\'col-sm-9\'>
                        '.$nextBillingDate.'
                    </div>
                </div>
				
                <div class=\'row\'>
                    <div class=\'col-sm-3 detail-header\'>
                        Last Transaction
                    </div>
                    <div class=\'col-sm-9\'>
                        '.$latestTransactionSummary.'
                    </div>
                </div>
                
                <div class=\'row\'>
                    <div class=\'col-sm-3 detail-header\'>
                        Billing Address
                    </div>
                    <div class=\'col-sm-9\'>
                        '.$this->account->getAddress().'
                    </div>
                </div>
                
                <div class=\'row\'>
                    <div class=\'col-sm-3 detail-header\'>
                        Agreement End Date
                    </div>
                    <div class=\'col-sm-9\'>
                        '.$this->account->getEndDate().'
                    </div>
                </div>
                
                <div class=\'row\'>
                    <div class=\'col-sm-3 detail-header\'>
                        States Licenced
                    </div>
                    <div class=\'col-sm-9\'>
                        '.$this->account->getLicensedStates().'
                    </div>
                </div>
                
                <div class=\'row\'>
                    <div class=\'col-sm-3 detail-header\'>
                        ABN
                    </div>
                    <div class=\'col-sm-9\'>
                        '.$this->account->getABN().'
                    </div>
                </div>
            </div>
        
            '.$transactionsBody.'
        </div>';
    }

    /**
     * @return string
     */
	private function getInvoices()
	{
        global $stats;
        $start = t();
        // don't display the invoices before a payment method gets associated with this billing account
        if ( !BillingRelease::SHOW_INVOICES_ON_PENDING_ACCOUNT && !$this->account->getSubscription() ) {
            return "";
        }

        // @TODO: do NOT load the full data for the invoices from Braintree, we don't really need it at this point
        $invoices   = LcInvoice::allOf( $this->account->getId(), $this->account->getCustomerId(), false );
        $invoiceDisplay = array();

        $stats["billing-content-invoices-load"] = t($start);
        $start = t();

        /**
         * @var $invoice LcInvoice
         */
        foreach ( $invoices as $invoice ) {
            $invoiceDisplay[]= LcInvoiceView::rowTemplate( $invoice, $this->account );
        }

        $stats["billing-content-invoices-display"] = t($start);

        return '
        <div class=\'panel panel-default\'>
            <div class=\'panel-heading\'>
                <span class=\'pull-left\'>
                    Invoices
                </span>
                <div class=\'clearfix\'></div>
            </div>
            <div class=\'panel-body\'>
				<div class=\'row label-row\'>
                    <div class=\'col-sm-5\'>
                        Invoice
                    </div>
                    <div class=\'col-sm-2\'>
						Date
                    </div>
					<div class=\'col-sm-1\'>
						Cost
                    </div>
					<div class=\'col-sm-1\'>
						Status
					</div>
					<div class=\'col-sm-3\'>
						Actions
					</div>
                </div>
				
                '.join("\n", $invoiceDisplay).'
            </div>
        </div>';
	}

    /**
     * @param $transactions
     * @return string
     */
    private function getSubscriptionInvoices( $transactions )
    {
        $invoiceDisplay = array();

        /**
         * @var $transaction Braintree_Transaction
         */
        foreach ( $transactions as $transaction ) {
            // load or create the subscription invoice for this transaction
            $invoice = LcSubscriptionInvoice::loadForTransaction(
                $this->account,
                $transaction
            );

            if ( $invoice ) {
                $invoiceDisplay[] = LcInvoiceView::subRowTemplate($invoice, $this->account);
            }
        }

        return '
            <div class=\'panel-heading\'>
                <span class=\'pull-left\'>
                    Transactions
                </span>
                <div class=\'clearfix\'></div>
            </div>
            <div class=\'panel-body\'>
				<div class=\'row label-row\'>
                    <div class=\'col-sm-5\'>
                        Details
                    </div>
                    <div class=\'col-sm-2\'>
						Date
                    </div>
					<div class=\'col-sm-1\'>
						Cost
                    </div>
					<div class=\'col-sm-1\'>
						Status
					</div>
					<div class=\'col-sm-3\'>
						Actions
					</div>
                </div>
				
                '.join("\n", $invoiceDisplay).'
            </div>
        ';

        return "";
    }

    private function getArchivedSubscriptionInvoices()
    {
        // @TODO: offer a download link for all the archived subscription invoices
        // For Simonds, the subscription ID is 3c3cgm
        /**
        INSERT INTO `billing_subscription` (`account_id`, `id`, `created_at`, `starts_at`, `ends_at`, `ends_at_extended`, `status`, `sent_payment_reminder`) VALUES
        (2, '3c3cgm', '2017-01-13 01:36:49', '2017-02-01 00:00:00', '2017-12-31 00:00:00', 0, 'Active', '0000-00-00');
         **/

        return "";
    }

	/**
     * getPageScript
     * output the JS code that creates the stats
     */
    private function getPageScript()
    {
        // prepare the settings depending on the page
        echo "
    <script type='text/javascript'>
        $(document).ready(function() {
        
            // Load the header
            $('.app-nav').load('../account/header.php?area=manage', function() {
                $('.navitem-billing').addClass('active');
            });

            // display page alerts / errors
            alertWidget('display-alerts');
        });
    </script>
        ";
        
        PayWallContent::addPageScripts(array(
            "subscribeBtn"      => "btn-create-subscription",
            "managePaymentsBtn" => "btn-manage-payment-methods"
        ));
    }
	
    private function endPage() {
        // load chat
        include_once(__DIR__."/../../forms/chat.php");

        echo "
    </body>
</html>
        ";
    }

    private function getDateString( $dateStr, $default, $format="Y-m-d" )
    {
        if ( isset( $dateStr ) && $dateStr != null && ( $validate = DateTime::createFromFormat($format, $dateStr) ) != FALSE ) {
            return $validate->format($format);
        }   else {
            $now = new DateTime();

            if ( $default == "yearstart" ) {
                $now->setDate($now->format("Y"), 1, 1);
                return $now->format($format);
            }   else if ( $default == "now" ) {
                return $now->format($format);
            }   else {
                // @INFO: it's up to the user to make sure that the default date has the correct format
                return $default;
            }
        }
    }
}
?>