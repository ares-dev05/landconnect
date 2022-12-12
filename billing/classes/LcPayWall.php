<?php

class LcPayWall {

    /**
     * disable this flag when on the paywall and API pages so that other features can be accessed
     */
    static $enableWalling = true;

    /**
     * @var loggedInUser
     */
    private $user;
    /**
     * @var LcAccount
     */
    private $account;

    /**
     * @return bool
     */
    public function hasUser() { return $this->user!=null; }

    /**
     * @return loggedInUser|mixed|null
     */
    public function user() { return $this->user; }

    /**
     * @return bool
     */
    public function hasAccount() { return $this->account!=null; }

    /**
     * @return LcAccount
     */
    public function getAccount() { return $this->account; }


    public function __construct( )
    {
        global $loggedInUser;
        
        $this->user = $loggedInUser;
        if ( $this->user ) {
            // we want to always update from braintree in case a status change occured in the subscription;
            // we're going to load the braintree details anyway
            if (defined('SIMPLE_BILLING_LOAD') && SIMPLE_BILLING_LOAD==true) {
                $this->account = LcAccount::getForSelf( $this->user, true, false, false );
            }   else {
                $this->account = LcAccount::getForSelf( $this->user, true, true );
            }

            // display the paywall only if it is enforced on the current billing account and on the current page
            if ( BillingRelease::enforcePayWallFor( $this->user->company_id ) && self::$enableWalling ) {
                $this->displayIfWalled();
            }
        }
    }

    /**
     * return true if the currently logged-in user has access to the app
     */
    public function hasAccess()
    {
        return $this->hasAccount() && $this->account->isSubscribed() && $this->account->role()->canUse() ;
    }

    /**
     * default permission checks that need to be ran inside the paywall
     */
    private function runPermissionChecks()
    {
        if ( !$this->user ) {
            echo "You are not logged in";
            // header("Location: ../account/index.php");
            exit;
        }

        if ( !$this->hasAccount() ) {
            PayWallContent::start();
            PayWallContent::noAccount();
            PayWallContent::end();
            exit;
        }

        if ( !$this->account->role()->canUse() ) {
            PayWallContent::start();
            PayWallContent::noUsePermission($this->account);
            PayWallContent::end();
            exit;
        }
    }

    /**
     * display the paywall if it is needed
     */
    public function displayIfWalled()
    {
        $this->runPermissionChecks();

        if ( !$this->hasAccess() ) {
            PayWallContent::start();

            // display the required content depending on the status of the account
            if ( $this->account->role()->canSubscribe() ) {
                // this user can make subscriptions
                if ( $this->account->isOverdue() ) {
                    // @TODO: check that Braintree overdues are handled correctly
                    // a subscription exists but was overdue
                    PayWallContent::overdueForm( $this->account );
                }   else {
                    // no subscription exists on this account; show the initial payment form
                    PayWallContent::subscribeForm( $this->account );
                }
            }   else {
                // this user can't make subscriptions
                PayWallContent::guestWall( $this->account->isOverdue() );
            }

            PayWallContent::end();

            // cut off any other functionality
            exit;
        }
    }

    /**
     * show the form that allows you to create the Footprints subscription
     */
    public function createSubscriptionForm()
    {
        $this->runPermissionChecks();

        // make sure the current user can manage the billing details
        if ( !$this->account->role()->canSubscribe() ) {
            PayWallContent::start();
            PayWallContent::noUsePermission($this->account);
            PayWallContent::end();
            exit;
        }

        PayWallContent::start();
        PayWallContent::subscribeForm($this->account);
        PayWallContent::end();
    }

    /**
     * show the form that allows you to pick a different payment method for the subscription
     */
    public function editSubscriptionForm()
    {
        $this->runPermissionChecks();

        // make sure the current user can manage the billing details
        if ( !$this->account->role()->canManageBilling() ) {
            PayWallContent::start();
            PayWallContent::noUsePermission($this->account);
            PayWallContent::end();
            exit;
        }

        PayWallContent::start();
        PayWallContent::editSubscriptionForm($this->account);
        PayWallContent::end();
    }

    /**
     * @param $invoice LcInvoice
     */
    public function payInvoiceForm( $invoice )
    {
        $this->runPermissionChecks();

        // make sure the user has payment access
        if ( !$this->account->role()->canMakePayments() ) {
            PayWallContent::start();
            PayWallContent::noUsePermission($this->account);
            PayWallContent::end();
            exit;
        }

        PayWallContent::start();
        PayWallContent::payInvoiceForm($this->account, $invoice);
        PayWallContent::end();
    }

    /**
     * display an error message when an invoice is not found
     */
    public function invoiceNotFound( )
    {
        PayWallContent::start();
        PayWallContent::displayError( "Invoice not found", $this->account );
        PayWallContent::end();
        exit;
    }
}

?>