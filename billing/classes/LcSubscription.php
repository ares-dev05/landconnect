<?php

// LcSubscription
// handles account subscriptions

// @TODO: update the apiErrors interface

class LcSubscription
{
    /*
     * the possible states of a subscription are taken from Braintree_Subscription:
    const ACTIVE = 'Active';
    const CANCELED = 'Canceled';
    const EXPIRED = 'Expired';
    const PAST_DUE = 'Past Due';
    const PENDING = 'Pending';
    */

    const tableName				= "billing_subscription";
    const webhooksTableName		= "billing_webhook";
    // set this to false if you want to edit a braintree subscription's info locally for testing purposes
    const UPDATE_BRAINTREE_DATA = true;

    const DATE_DISPLAY_FORMAT   = "jS \of F Y";

    const PAST_DUE_GRACE_PERIOD = 7;

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Class Definition

    /**
     * @var string $id the id of the subscription
     * @var DateTime $createdAt the date when the subscription was created
     * @var DateTime $startsAt the date when the subscription activates
     * @var $endsAt DateTime the date when the subscription ends
     * @var DateTime $renewsAt the date when the subscription renews
     * @var string $status the current status of the subscription
     * @var Braintree_Subscription $subscription the associated Braintree_Subscription object, if this is a Braintree subscription
     */
    public $id;
    public $createdAt;
    public $startsAt;
    public $endsAt;
	
    // Eastern Standard Time Timezone
    public $endsAtEST;
	public $endsAtString;
	public $renewsAtEST;
	public $renewsAtString;
    public $startsAtString;

    public $status;
    public $subscription;
    public $baseId;
	
    // a mask string representing the payment method used for automatic renewals on this subscription
    public $paymentMethodMask;
	
	// flag that indicates if this subscription is in a trial period
    public $isInTrialPeriod;
	
    private $loadBTSubscription;
    private $dbObj;
    private $valid;


    /**
     * @return string the current status of the subscription, either from Braintree (if we have available data) or from the local database
     */
    private function getCurrentStatus()
    {
        return ( $this->loadBTSubscription && $this->subscription ) ? $this->subscription->status : $this->status;
    }


	/**
     * @TODO: we may want to include extra functionality here for stopping the access to our services
     *        for PAST_DUE enterprise accounts, without cancelling the subscription
     * @return bool
     */
    public function isAlive() {
        if ( $this->isStatusOverdue() ) {
            return $this->isInGracePeriod();
        }   else {
            return self::isStatusAlive( $this->getCurrentStatus() );
        }
    }

	/**
	 * @return bool
	 */
	public function isExpiredOrCanceled()
	{
		return self::isStatusExpiredOrCanceled( $this->getCurrentStatus() );
	}

    /**
     * @return boolean true if this subscription's status is past due
     */
    public function isStatusOverdue()
    {
        return $this->getCurrentStatus() == Braintree_Subscription::PAST_DUE;
    }

    /**
     * @return int the number of days this subscription has been past due
     */
    public function daysOverdue()
    {
        $this->fetchBraintreeData();
        return $this->subscription->daysPastDue;
    }

    /**
     * @return boolean true if this subscription is in the overdue grace period
     */
    public function isInGracePeriod()
    {
        return $this->daysOverdue() < self::PAST_DUE_GRACE_PERIOD;
    }

    /**
     * @return boolean true if this subscription is overdue and outside of the grace period; this will lock all access to the account
     */
    public function isHardOverdue()
    {
        return $this->isStatusOverdue() && $this->isInGracePeriod() == false;
    }

    /**
     * @return bool
     */
    public function isValid() { return $this->valid; }

    /**
     * @return int the number of days left on the subscription
     */
    public function getDaysLeft() {
        if ( $this->isAlive() )
        {
            $nowDate = new DateTime();
            $nowDate->setTimezone($this->endsAt->getTimezone());

			// @TODO: fix this; also, endsAt doesn't update correctly when a new cycle begins!!!
            $dateDif = $nowDate->diff( $this->endsAt );
			// $dateDif = $this->endsAt->diff( $nowDate );

            return (int)$dateDif->format("%r%a");
        }

        // random high negative for inactive subscriptions
        return -365;
    }

	/**
	 * LcSubscription constructor.
	 * @param $id
	 * @param bool $loadBTSubscription
	 */
    function __construct( $id, $loadBTSubscription=true ) {
        $this->id       = $id;
        $this->baseId   = $this->id;
        $this->loadBTSubscription  = $loadBTSubscription;
        $this->isInTrialPeriod = false;
        $this->fetch( true );

		if ( self::UPDATE_BRAINTREE_DATA && $this->loadBTSubscription && isset($this->subscription) )
        {
            // if we're loading Braintree information, we also update our status, in case a webhook call failed
            $this->updateFromBraintree( $this->subscription );
        }
    }

    function __destruct() {
        $this->__commit();
    }

    private function fetch( $setProperties=false )
    {
        $db = pdoConnect();
        $statement = $db->prepare(
            "SELECT *
			 FROM ".self::tableName."
			 WHERE id=:id"
        );

        if	( $statement->execute( array(":id" => $this->id) ) &&
            ( $this->dbObj=$statement->fetch(PDO::FETCH_OBJ) ) )
        {
            $this->valid = true;
            if ( $setProperties )
            {
                $this->createdAt    = DateTime::createFromFormat(Config::SQL_DATETIME, $this->dbObj->created_at);
                $this->startsAt     = DateTime::createFromFormat(Config::SQL_DATETIME, $this->dbObj->starts_at);
                $this->startsAtString = $this->startsAt->format( self::DATE_DISPLAY_FORMAT );

                if ( $this->dbObj->ends_at == "0000-00-00 00:00:00" ) {
                    // end datetime is not defined
                    // calculate it as start_time + plan duration (=1M)
                    $this->endsAt   = DateTime::createFromFormat(Config::SQL_DATETIME, $this->dbObj->starts_at);
                    $this->endsAt->add( new DateInterval("P1M") );
                }   else {
                    $this->endsAt   = DateTime::createFromFormat(Config::SQL_DATETIME, $this->dbObj->ends_at);
                }

				$this->formatEndsAtString();
				$this->formatRenewsAtString();
                $this->status       = $this->dbObj->status;
				
                // loadBTSubscription if false by default; all the information is updated via webhooks anyway,
                // so we almost never need to double-check the status of the subscription on Braintree
                if ( $this->loadBTSubscription )
                {
                    $this->fetchBraintreeData();
                }
            }
        }
        else
        {
            // subscription ID not found. this will never happen with correct api usage, as the subscription IDs are
            // fetched from the DB before the rest of the details.
            $this->valid =false;
            //error_log("LcSubscription::fetch(). Subscription ID=[{$this->id}] not found in the Database.");
        }
    }
	private function formatEndsAtString()
	{
		// clone the endsAt date
		$this->endsAtEST = clone $this->endsAt;

		/* Trials actually renew the day they end, whereas normally renewal is the day after - so we treat the endDate as the day prior.
		   We only modify the endsAtEST (and not endsAt) because this is used by Flash and the Manage Dialog, and not updated back to the database. */
		$is_trial = $this->createdAt == $this->startsAt; // fastest way to test if trial without fetching from Braintree
		if ( $is_trial )
			$this->endsAtEST->sub( new DateInterval("P1D") );

		// set EST timezone
        $this->endsAtEST->setTimezone( new DateTimeZone("EST") );	// America/New_York?
		// $this->endsAtString = $this->endsAtEST->format("jS \of F Y")." at ".$this->endsAtEST->format("g:i A")." EST.";
        // $this->endsAtString = $this->endsAtEST->format( self::DATE_DISPLAY_FORMAT );

		// we don't want to use the EST timezone, but the local timezone of the server (for Australia)
		$this->endsAtString = $this->endsAt->format( self::DATE_DISPLAY_FORMAT );
	}

	private function formatRenewsAtString()
	{
        // renewal date is the day after expiry
        $this->renewsAtEST = clone $this->endsAtEST;
        date_add($this->renewsAtEST, date_interval_create_from_date_string("1 days"));
        $this->renewsAtString = $this->renewsAtEST->format( self::DATE_DISPLAY_FORMAT );
	}

    public function fetchBraintreeData()
    {
        if ( !isset($this->subscription) ) {
            $this->subscription = Braintree_Subscription::find($this->baseId);

            // @INFO
            // * we modify the 'trialPeriod' flag to actually indicate if this subscription IS in a trial period
            //   the default value from Braintree_Subscription indicates if the subscription STARTED with a trial period
            // * according to this article: https://articles.braintreepayments.com/guides/recurring-billing/trial-periods
            //   the trial period is part of the first billing cycle; this doesn't appear to be correct though, because
            //   subscriptions in a trial period have 'currentBillingCycle'=0, and after a successful billing, this is increase to 1
            // * because of this, a safe way to check if a subscription is out of trial is by verifying that 'paidThroughDate' is non-null
            if ( $this->subscription->trialPeriod  ) {
                $this->isInTrialPeriod = !$this->subscription->currentBillingCycle && $this->subscription->paidThroughDate==null;
            }
        }
    }
	
    private function __commit()
    {
        if ( $this->isValid() ) {
            // save the recalculated informations to the DB
            // account_id, id, creation & start times need not be edited
            $db = pdoConnect();
            $statement = $db->prepare(
                "UPDATE ".self::tableName."
                 SET
                    ends_at = :ends_at,
                    status  = :status
                 WHERE id=:id"
            );

            return $statement->execute(array(
                ':ends_at'  => $this->endsAt->format(Config::SQL_DATETIME),
                ':status'   => $this->status,
                ':id'       => $this->id
            ));
        }
    }
	
    /**
     * @param Braintree_Subscription $btSub
     * @return bool indicating success
     */
    public function updateFromBraintree( $btSub )
    {
		// can't revive canceled subscriptions
		if ( $this->status != Braintree_Subscription::CANCELED ) {
			$this->status = $btSub->status;
		}

		// only update the endsAt from Braintree if we didn't extend it locally
		// MD 19DEC2015: for PastDue subscriptions, paidThroughDate is NULL; we don't change it locally in case a valid end_date was already has set, as this would disable the premium access instantly
		if ( $btSub->paidThroughDate ) {
			$this->endsAt = $btSub->paidThroughDate;
			$this->formatEndsAtString();
		}

		return $this->__commit();
    }

    /**
     * synchWithBraintree if the current subscription is Braintree-based, synchronize it with the most up-to-date data on Braintree
     */
    public function synchWithBraintree( )
    {
		$this->fetchBraintreeData();
		$this->updateFromBraintree( $this->subscription );
    }
	
    /**
     * @param bool $autoRenew sets the autoRenew flag to true/false
     * @return bool
     */
    public function updateBraintreeSettings( $autoRenew )
    {
		// @DISABLE - we shouldn't be allowed to turn off the auto-renewal
		return false;
		
		// fetch the current info
		$params = array( "neverExpires" => $autoRenew );
		if ( !$autoRenew ) {
			/* Braintree require us to set the number of billing cycles.
			   You can't set it to less than the current cycle. */
			$this->fetchBraintreeData();
			$params["numberOfBillingCycles"] = max($this->subscription->currentBillingCycle, 1);
		}
		
		$result = Braintree_Subscription::update( $this->baseId,  $params );
		
		if ( $result->success ) {
			$this->subscription = $result->subscription;
			return true;
		}

		return false;
    }
	
    /**
     * isStatusCancelable
     * @return bool true if the subscription can be canceled from the status it is in at the moment
     */
    public function isCancelable()
    {
        /* This is how cancellation is handled depending on the subscription's Braintree status:
         * Active   -> if (trial) cancel on Braintree; else toggle autoRenew
         * Past_Due -> cancel on Braintree
         * Expired  -> N/A
         * Canceled -> N/A
         */
        $this->fetchBraintreeData();

        return (
            $this->status == Braintree_Subscription::PAST_DUE ||
          ( $this->status == Braintree_Subscription::ACTIVE && $this->isInTrialPeriod )
        );
    }

    /**
     * @return bool
     */
    public function cancel()
    {
        // no need to cancel already cancelled or expired subscriptions
        // if ( $this->status != Braintree_Subscription::CANCELED && $this->status != Braintree_Subscription::EXPIRED ) {
        if ( $this->isCancelable() ) {
            // cancel on braintree as well
            $result = Braintree_Subscription::cancel($this->baseId);

            // on success, a webhook will update the status of this subscription automatically
            // we still update it immediately for accuracy
            $this->status = Braintree_Subscription::CANCELED;
            $this->__commit();

            return true;
        }

        return false;
    }

	/**
	 * fetches the payment method mask associated to this subscription
	 * used for display purposes
	 */
    public function attachPaymentMethodInfo()
    {
		$this->fetchBraintreeData();

		if ( $this->subscription->paymentMethodToken != NULL ) {
			$paymentMethod = Braintree_PaymentMethod::find($this->subscription->paymentMethodToken);

			// property_exists,
			if (is_a($paymentMethod, "Braintree_CreditCard")) {
				$this->paymentMethodMask = $paymentMethod->cardType . " " . $paymentMethod->maskedNumber . " ending " . $paymentMethod->expirationMonth . "/" . $paymentMethod->expirationYear;
			} else {
				$this->paymentMethodMask = "PayPal " . $paymentMethod->email;
			}
		} else // Expired Cards expire the subscription, unless the Braintree callback failed
			$this->paymentMethodMask = "Expired Credit Card";
    }

    /**
     * retry the payment on a past due subscription
     * @return bool
     */
    public function retryPayment( )
    {
        global $apiErrors;

        // @TODO: do we need to check if the subscription is actually past due before doing this?

		$retryResult = Braintree_Subscription::retryCharge( $this->baseId );
		$transaction = $retryResult->transaction;

		if ($retryResult->success) {
			// If you want to collect funds, you must submit authorized transactions for settlement
			$settlementResult = Braintree_Transaction::submitForSettlement( $transaction->id );

			if ( $settlementResult->success ) {
				/*
				   We need to update the ends_at and status from Braintree, both because:
				   - __destruct() will otherwise override any webhooks called in the meantime with invalid data
				   - the 'Manage your settings' dialog needs to display the updated data
				*/
				if ( $this->updateFromBraintree( Braintree_Subscription::find($this->baseId) ) ) {
					$this->formatEndsAtString();
					$this->formatRenewsAtString();
					return true;
				} else
					return false;
			} else {
                // refund / void it
				// $transaction = $settlementResult->transaction;
				Braintree_Transaction::void( $transaction->id );
				$apiErrors[] = "We were unable to collect the funds for an unknown reason. You have not been charged and your subscription is still past due.";
			}
		}   else {
			$apiErrors[] = "The payment retry was unsuccessful. You have not been charged and your subscription is still past due.";
			self::parseTransactionErrors( $retryResult->errors->deepAll(), $transaction );
		}

        return false;
    }

    /**
     * update the payment method associated with this subscription
     * @param string $customerId the ID of the customer that owns this subscription
     * @param string $paymentNonce the nonce that was passed by the drop-in form when this action was initiated
     * @return bool
     */
    public function changePaymentMethod( $customerId, $paymentNonce )
    {
        global $apiErrors;

		$changeResult = Braintree_Subscription::update(
			$this->baseId,
			array(
                "paymentMethodNonce" => $paymentNonce
            )
		);

		if ($changeResult->success) {
			// save the new subscription
			$this->subscription = $changeResult->subscription;
			return true;
		} else {
			// fix for PayPal not being saved to vault
			foreach( $changeResult->errors->deepAll() AS $error ) {
				if ( $error->code == 91927 ) { // Payment method nonce represents an un-vaulted payment instrument.
					$result_pm = Braintree_PaymentMethod::create(
                        array(
                            "customerId" => $customerId,
                            "paymentMethodNonce" => $paymentNonce
                        )
                    );

					if ( $result_pm->success ) {
						$changeResult = Braintree_Subscription::update(
                            $this->baseId,
                            array( "paymentMethodToken" => $result_pm->paymentMethod->token )
						);

						if ($changeResult->success)
							return true;
					}
				}
			}

			$apiErrors[] = "We were unable to change your payment method at this time. Please try again.";
		}

        return false;
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Static Functions

    /**
     * creates a subscription
     * @param LcAccount $account
     * @param array $options the options object, having the following format
     *      planId
     *      paymentMethodNonce
     *      paymentMethodToken (optional)
     * @param array $planParams
     */
    public static function create( $account, $options, $planParams=null )
    {
        global $apiErrors;

		// options must contain the planId & paymentMethodNonce
		$btParams = array( "planId" => $options['planId'] );

		// merge the additional plan params if they are provided; this is used to setup custom trial periods
		// or to change the subscription costs
		if ( is_array($planParams) )
			$btParams = array_merge( $btParams, $planParams );
		
		// specify the braintree merchant ID if this is not sandbox
		if ( !IS_BRAINTREE_SANDBOX ) {
			// @CURRENCY @09JUL2017 - adding merchant account ID + multiple currencies support
			$btParams["merchantAccountId"] = $account->getMerchantAccount()->getId();
		}
		
		if ( isset($options['paymentMethodNonce']) )
			$btParams["paymentMethodNonce"] = $options["paymentMethodNonce"];

		if ( isset($options['paymentMethodToken']) )
			$btParams["paymentMethodToken"] = $options["paymentMethodToken"];
		
		// testing error messages
		/*
			Amount 	                Processor Response
			$0.01 - $1999.99 	    Authorized
			$2000.00 - $2077.99 	Processor Declined with the processor response equal to the amount
			$2078.00 - $2999.99 	Processor Declined with a decline code equal to the amount
			$3000.00 - $3000.99 	Processor Declined with the processor response equal to the amount
			$3001.00 - $4000.99 	Authorized
			$4001.00 	            Settlement Declined on PayPal transactions
			$4001.01 - $4001.99 	Authorized
			$4002.00 	            Settlement Pending on PayPal transactions
			$5001.01 and up 	    Authorized

			see list of error codes here: https://developers.braintreepayments.com/javascript+php/reference/general/processor-responses/authorization-responses
		*/
		// @TESTING: uncomment thed  following line and set the desired value to test the different possible error messages
			// $btParams["price"] = 2004;

			// @TESTING: uncomment the following lines to create a subscription that will renew after one day and become 'Past Due'
			// $btParams["price"] = 2000;
			// $btParams["trialPeriod"] = true;
			// $btParams["trialDuration"] = 1;
			// $btParams["trialDurationUnit"] = "day";

			// run the creation of the subscription
			$result = Braintree_Subscription::create( $btParams );

			if ( $result->success ) {
				// subscription was successfully created.
				$subscription = $result->subscription;
				// changed start and end date calculation for subscriptions that start with a trial period
				$startDate = $subscription->firstBillingDate;

				// changed start and endate calculation for subscriptions that start with a trial period
            if ( $subscription->trialPeriod && $subscription->trialDuration > 0 ) {
                // this subscription starts with a trial period, initialize the start and end dates differently
                $startDate = $subscription->billingPeriodStartDate;
                // endDate will be set to first billing date, so that the user knows when the first payment is due
                $endDate = $subscription->firstBillingDate;
            }   else {
                // no trial, no special offer
                $endDate = $subscription->paidThroughDate;
            }

			// record the subscription in the local table
			$addResult = self::addUserSubscription(
				// ID of the billing account
				$account->getId(),
				// ID of the subscription
				$subscription->id,
				// creation date; the creation time must be from the same timezone as the server;
				// $subscription->createdAt,
				new DateTime(),
				// start date
				$startDate,
				// end date; if this date is not yet in the future (not paid yet), it will be soon updated via a webhook
				$endDate,
				// state of the subscription
				$subscription->status
			);

			if ( $subscription->status == Braintree_Subscription::ACTIVE ||
				$subscription->status == Braintree_Subscription::PENDING ) {
				return $addResult;
			}
			else {
				// the subscription was added, but it is not active; please contact
				$apiErrors[] = "Your account will be activated shortly. If it isn't active within 10 minutes please contact support@landconnect.com.au for further assistance.";
				return NULL;
			}
		} else {
			// Start temp fix for PayPal not being saved to vault
			if ( !isset( $options["retried"] ) ) {
				foreach( $result->errors->deepAll() AS $error ) {
					if ( $error->code == 91927 ) { // Payment method nonce represents an un-vaulted payment instrument.
						$btParams_pm = array(
                            "customerId" => $account->getCustomerId(),
                            "paymentMethodNonce" => $options['paymentMethodNonce']
                        );
						$result_pm = Braintree_PaymentMethod::create( $btParams_pm );

						if ( $result_pm->success ) {
							$options["retried"] = "yes";
							$options["paymentMethodNonce"] = ''; // nonce is now invalid as can only be used once
							$options["paymentMethodToken"] = $result_pm->paymentMethod->token;

							return LcSubscription::create( $account, $options );
						}
                        // throw new Exception("reached unfixed point: temp fix for PayPal not getting saved to vault");
					}
                    /**
                     * @TODO: support payment_method_nonce is invalid error types:
                     *      93102
                     *      91925
                     * @TODO: this errors appear when, e.g. a payment method was selected, but not submitted for a long time
                     * @TODO:       e.g. after a day when the computer is closed and reopened
                     */
				}
			}

			self::parseTransactionErrors( $result->errors->deepAll(), $result->transaction );
			return NULL;
		}

        // unknown subscription type
        return NULL;
    }

    public static function getFormattedError( $headerText, $extraText ) {
    	// return '<div style="text-align: left"><div style="color: #627A90; font-size: 18px">'.$headerText.'</div><div style="font-size: 13px; font-weight: normal; color: #767F86; padding: 18px 0 9px">'.$extraText.'</div></div>';
		return '
			<div style="text-align: left">
				<h1 style="font-size:24px; text-transform: uppercase;">'.
					$headerText.'
				</h1>
				<p>'.
					$extraText.'
				</p>
			</div>';
    }

    public static function parseTransactionErrors( $errors, $transaction ) {
        global $apiErrors;

        // check for validation errors; we shouldn't get these because we are using the drop-in form?
        if ( sizeof($errors) > 0 ) {
            foreach($errors AS $error) {
                if ( $error->code == 91924 )
                   	$apiErrors[] =  self::getFormattedError( 'Card not accepted.', 'Unfortunately we were unable to accept the card that you entered. We are currently only able to accept MasterCard, Visa, American Express or PayPal. Please try one of these by clicking "BACK" and then "Change payment method".' );
                else
	                $apiErrors[] = $error->message;
            }
        }

        // check the transaction status for errors;
        if ( isset($transaction) && $transaction ) {
            switch ($transaction->status) {

                case Braintree_Transaction::AUTHORIZED:
                    // N/A no errors present; case present so we don't show the generic error message for authorized transactions.
                    break;

                // processor declined transaction at the authorization stage
                // see https://developers.braintreepayments.com/javascript+php/reference/general/processor-responses/authorization-responses
                case Braintree_Transaction::PROCESSOR_DECLINED:
                   $errorText = $transaction->processorResponseText;

                    // uncomment the following 3 lines to display the additional bank info (if present); this will most likely be a duplicate of the processor's response text
                    // if ( strlen($transaction->additionalProcessorResponse) ) {
                    // $errorText .= " (Bank Info: {$transaction->additionalProcessorResponse})";
                    // }

                    $extraText = '';
                    $code = $transaction->processorResponseCode;
                    if ( $code == 2000 || $code == 2015 || $code == 2019 || $code == 2038 ) {
                    	$headerText = 'Your bank declined the transaction.';
                    	$extraText = 'The reasons for this vary and may be because Landconnect is an Australian company. You will need to call the number on the back of your card for more details and to ask if the transaction can be allowed.';

                    	if ( $code != 2015 && $code != 2019 ) // soft codes can retry
							$extraText .= ' You can also try upgrading again later and it may be successful.';

                    	$extraText .= '<br>
                    	<br>
						Alternatively you can try a different card, or check out with PayPal (by clicking "BACK" and then "Change payment method").';

					} else if ( $code == 2057 ) {
                    	$headerText = 'Your bank declined the transaction.';
                    	$extraText = 'The reasons for this vary and may be because Landconnect is an Australian company, or because the charge is recurring.<br>
                    	<br>
                    	To resolve this you can:<br>
                    	<br>
                    	- Try a different card or upgrading with PayPal by clicking "BACK" and then "Change payment method"<br>
                    	- Call the number on the back of your card for more details and to ask if the transaction can be allowed<br>
                    	- Try upgrading again later and it may be successful';
					} else if ( $code == 2074 || $code == 2063 || $code == 2066 || $code == 2067 || $code == 2068 || $code == 2076 ) {
						$headerText = 'Paypal declined the transaction.';
                    	$extraText = 'PayPal or your bank has declined the transaction. To resolve this you will need to either:<br>
                    	<br>
						- Ensure your PayPal account has a sufficient balance to complete the transaction<br>
						- Upgrade with a credit or debit card (by clicking "BACK" and then "Change payment method"). Or,<br>
						- Follow these steps to change your PayPal payment method:<br>
						<ol>
							<li style="color: #00A2E8">Log in to your PayPal account</li>
							<li style="color: #00A2E8">Click the settings icon next to "Log out"</li>
							<li style="color: #00A2E8">Click "Preapproved Payments" (under "Payment settings" or "My money")</li>
							<li style="color: #00A2E8">Click "Set Available Funding Sources" to the top right of the table</li>
							<li style="color: #00A2E8">Change the available funding sources</li>
							<li style="color: #00A2E8">Close this dialog and try again</li>
						</ol>';
					} else if ( $code == 2001 ) {
						$headerText = 'Your bank declined the transaction due to insufficient funds.';
						$extraText = 'You can try a different card, or check out with PayPal (by clicking "BACK" and then "Change payment method"). You can also try upgrading again later and it may be successful.';
					} else if ( $code == 2002 || $code == 2003 ) {
						$headerText = 'Your bank declined the transaction due to Limit Exceeded';
						$extraText = 'This normally indicates that your account may be overdrawn, over limit, or overdue.<br>
									  <br>
									  You can try a different card, or check out with PayPal (by clicking "BACK" and then "Change payment method"). You can also try upgrading again later and it may be successful.';
					} else if ( $code == 2014 ) {
						$headerText = 'The transaction was declined by your bank';
						$extraText = 'The reasons for this vary and may be because Landconnect is an Australian company. You will need to call the number on the back of your card for more details and to ask if the transaction can be allowed.<br>
						<br>
						Alternatively you can try a different card, or check out with PayPal (by clicking "BACK" and then "Change payment method").';

					} else if ( $code == 2007 || $code == 2008 || $code == 2009 || $code == 2012 ||
							    $code == 2013 || $code == 2047 || $code == 2053 ) {

						if ( $code == 2047 || $code == 2053 )
							$headerText = 'The transaction was declined with error "'.$transaction->processorResponseText.'"';
						else
							$headerText = $transaction->processorResponseText;

						if ( $code != 2012 && $code != 2013 && $code != 2047 && $code != 2053 )
							$extraText = 'This normally means that there is no credit account associated with the card number that was entered (i.e. the number is invalid).<br>
							<br>
							You can try entering the number again, try a different card, or check out with PayPal by clicking "BACK" and then "Change payment method".';
						else
							$extraText = 'This normally indicates that your card may have been reported lost or stolen.<br>
							<br>
							You can try entering the number again, try a different card, or check out with PayPal by clicking "BACK" and then "Change payment method".';

					} else if ( $code == 2004 || $code == 2005 || $code == 2006 ) {
						$headerText = $transaction->processorResponseText;

						if ( $code == 2004 )
							$extraText = 'This normally means that the card you have entered has expired.';

						else if ( $code == 2005 )
							$extraText = 'This normally means that the details you have entered are invalid.';

						else if ( $code == 2006 )
							$extraText = 'This normally means that expiration date you enetered is invalid.';

						$extraText .= '<br>
						<br>
						We recommend entering the details again, trying a different card, or upgrading with PayPal by clicking "BACK" and then "Change payment method".';
					} else if ( $code == 2010 ) {
						$headerText = 'Invalid CVV';
						$extraText = 'This normally means that you entered an incorrect CVV.<br>
						<br>
						You can try entering the number again, try a different card, or check out with PayPal by clicking "BACK" and then "Change payment method".';

					} else if ( $code == 2058 ) {
						$headerText = 'Transaction declined as we are not MasterCard SecureCode enabled';
						$extraText = 'You can try entering the number again, try a different card, or check out with PayPal by clicking "BACK" and then "Change payment method".';

					} else if ( $code == 2069 || $code == 2070 || $code == 2071 || $code == 2072 || $code == 2073 || $code == 2075 ||
								$code == 2077 || $code == 2079 || $code == 2081 || $code == 2082 || $code == 2083 ) { // generic PayPal errors
						$headerText = 'The transaction was declined with error "'.$transaction->processorResponseText.'"';
						$extraText = 'We recommend trying a different debit card, credit card, or PayPal account (by clicking "BACK" and then "Change payment method").';

					} else {
						if ( $code == 2020 || $code == 2021 || $code == 2022 || $code == 2023 || $code == 2046 ) // do not show response text for these errors
							$headerText = 'The transaction was declined';
						else
							$headerText = 'The transaction was declined with error "'.$transaction->processorResponseText.'"';

						if ( $code == 2016 || $code == 2017 || $code == 2018 || $code == 2024 || $code == 2031 || $code == 2032 ||
							 $code == 2033 || $code == 2034 || $code == 2035 || $code == 2036 || $code == 2037 || $code == 2039 ||
							 $code == 2040 || $code == 2042 || $code == 2045 || $code == 2048 || $code == 2049 || $code == 2050 ||
							 $code == 2051 || $code == 2054 || $code == 2055 || $code == 2056 || $code == 2061 || $code == 2062 ||
							 $code == 2078 || $code == 2080 ) // no Australian company message on these errors
							$extraText = 'The reasons for this vary; y';
						else
							$extraText = 'The reasons for this vary and may be because Landconnect is an Australian company. Y';

						$extraText .= 'ou will need to call the number on the back of your card for more details and to ask if the transaction can be allowed.';

						if ( $code == 2016 || $code == 2024 || $code == 2025 || $code == 2026 || $code == 2027 || $code == 2028 ||
							 $code == 2029 || $code == 2030 || $code == 2035 || $code == 2040 || $code == 2042 || $code == 2045 ||
							 $code == 2046 || $code == 2048 || $code == 2055 || $code == 2056 || $code == 2078 || $code == 2080 || ( $code >= 2084 && $code <= 2999 ) ) // soft codes can retry
							$extraText .= ' You can also try upgrading again later and it may be successful.';

						$extraText .= '<br>
                    	<br>
						Alternatively you can try a different card, or check out with PayPal (by clicking "BACK" and then "Change payment method").';
					}

					if ( $extraText )
						$apiErrors[] = self::getFormattedError( $headerText, $extraText );
					else
                    	$apiErrors[] = "Your transaction could not be processed: ".$errorText;

                    break;

                // processor settlement error codes
                // see https://developers.braintreepayments.com/javascript+php/reference/general/processor-responses/settlement-responses
                case Braintree_Transaction::SETTLEMENT_DECLINED:
                    /*
                        $result->transaction->processorSettlementResponseCode
                        # e.g. 4001
                        $result->transaction->processorSettlementResponseText
                        # e.g. "Settlement Declined"
                     */

                    $apiErrors[] = "Your transaction could not be processed: {$transaction->processorSettlementResponseText}";
                    break;

                // gateway error codes
                case Braintree_Transaction::GATEWAY_REJECTED:
                    /*
                        $result->transaction->gatewayRejectionReason
                        # e.g. "cvv"
                        This value will only be set if the transaction status is gateway rejected. Possible values:
                            avs
                            avs_and_cvv
                            cvv
                            duplicate
                            fraud
                            three_d_secure
                    */
                    // check the gateway rejection reason
                    switch ($transaction->gatewayRejectionReason) {
                        case Braintree_Transaction::AVS:
                            // N/A because we don't request the postal code
                            break;
                        case Braintree_Transaction::AVS_AND_CVV:
                            // N/A because we don't request the postal code
                            break;
                        case Braintree_Transaction::CVV:
                            $apiErrors[] = "Invalid CVV code. Please check and try again.";
                            break;
                        case Braintree_Transaction::DUPLICATE:
                            // Transactions will be rejected if another successful transaction has been created with the same payment method, order id and amount within the last [30] seconds.
                            // this value can be changed from the Braintree settings panel
                            $apiErrors[] = "This transaction has been identified as a duplicate (probably due to refreshing the page). You have only been charged once and your subscription is now active.";
                            break;
                        case Braintree_Transaction::FRAUD:
                            $apiErrors[] = "Your transaction was declined by our fraud detection system. Please try a different payment method.";
                            break;
                        case Braintree_Transaction::THREE_D_SECURE:
                            $apiErrors[] = "Your payment has been rejected by the 3D secure system. Please try again or try a different payment method.";
                            break;
                    }

                    break;

                case Braintree_Transaction::FAILED:
                	$apiErrors[] = self::getFormattedError( 'Payment Network Temporarily Unavailable', 'We are experiencing a temporary issue contacting our payment processor. Please try again shortly. You have not been charged for this transaction.' );
                	break;

                default:
                    // check if we have any response text
                    if (isset($transaction->processorResponseText) && strlen($transaction->processorResponseText)) {
                        $apiErrors[] = $transaction->processorResponseText;
                    } else {
                        // show a generic message for all other cases
                        $apiErrors[] = "Failed to subscribe due to an unknown error.";
                    }

                    break;
            }
        }
    }

    /**
     * helper function to add a subscription to the database
     * @param int $account
     * @param string $subId
     * @param DateTime $createdAt
     * @param DateTime $startsAt
     * @param DateTime $endsAt
     * @param string $status
     * @return LcSubscription
     */
    private static function addUserSubscription( $account, $subId, $createdAt, $startsAt, $endsAt, $status )
    {
        $db = pdoConnect();
        $createStmt = $db->prepare(
            "INSERT INTO ".self::tableName."
					(  account_id,  id,  created_at,  starts_at,  ends_at,  status ) 
			 VALUES ( :account_id, :id, :created_at, :starts_at, :ends_at, :status )"
        );
		$now = new DateTime();
		$future = new DateTime('+2 weeks');

        if ( $createStmt->execute( array(
                ':account_id'   => $account,
                ':id'           => $subId,
                ':created_at'   => $createdAt->format( Config::SQL_DATETIME ),
                ':starts_at'    => ( $startsAt ? $startsAt->format( Config::SQL_DATETIME ) : $now->format( Config::SQL_DATETIME ) ),
                ':ends_at'      => ( $endsAt ? $endsAt->format( Config::SQL_DATETIME ) : $future->format(Config::SQL_DATETIME) ),
                ':status'       => $status
            ) ) ) {
            return self::find( $subId );
        }   else {
            return NULL;
        }
    }

    /**
     * @param string $subId the ID of the subscription to find
     * @return LcSubscription
     */
    public static function find( $subId ) {
        $subscription = new LcSubscription($subId);
        return $subscription->isValid() ? $subscription : NULL;
    }

    /**
     * @param int $accountId the ID of the account for which to fetch all the subscriptions; if not passed, all subscriptions in the database are returned
     * @param int $limit the search limit; if 0, no limit is applied
     * @param bool $loadBTSubscription; true if we should load the Subscription details from Braintree, if this is a Braintree Subscription
     * @return array a list of LcSubscription objects
	 */
    public static function all( $accountId, $loadBTSubscription=false, $limit=0 ) {
        $limit      = intval( $limit );

        $db = pdoConnect();
        $statement = $db->prepare(
            "SELECT *
			 FROM ".self::tableName."
			 WHERE account_id=:account
             ORDER BY created_at DESC".($limit?" LIMIT {$limit}":"")
        );

        $list = array();
        if	( $statement->execute( array( ":account" => intval($accountId) ) ) ) {
            while ( ( $obj = $statement->fetch(PDO::FETCH_ASSOC) ) != NULL ) {
                $subscription = new LcSubscription( $obj["id"], $loadBTSubscription );
                if ( $subscription->isValid() ) {
                    $list[] = $subscription;
                }
            }
        }

        return $list;
    }

    /**
     * Verifies if a certain user is subscribed to the premium plan
     * @param int $uid the unique id of the user
     * @param bool $forceBTConfirmation flag that indicates if Braintree subscriptions should be verified on Braintree as well
     * @return bool
	 @TODO
	 @UNUSED
    public static function isUserSubscribed( $uid, $forceBTConfirmation=false )
    {
        // fetch the latest subscription of this user, and check if it's active
        $list = self::all( $uid, 1, $forceBTConfirmation );

        if ( count($list) ) {
            $subscription = $list[0];

            if ( $forceBTConfirmation && isset($subscription->subscription) )
                return self::isStatusAlive( $subscription->subscription->status );
            else
                return $subscription->isAlive();
        }

        return FALSE;
    }
     */
	
    /**
     * @param Braintree_WebhookNotification $notification
     * @return bool indicating success
     */
    public static function parseWebhookNotification( $notification )
    {
        $db = pdoConnect();

        $btSub          = $notification->subscription;
        $subId          = $btSub->id;
        $subscription   = self::find( $subId );
		
        if ( $subscription && $subscription->isValid() )
        {
            $hookDT     = $notification->timestamp->format( Config::SQL_DATETIME );
            $hookKind   = $notification->kind;

            $statement = $db->prepare(
                "SELECT subscription_id
                 FROM ".self::webhooksTableName."
                 WHERE
                    subscription_id = :id AND
                    timestamp > :timestamp"
            );

            if ( $statement->execute(array(":id"=>$subId, ":timestamp"=>$hookDT))!==FALSE && $statement->rowCount()==0 ) {
                // there is no more recent webhook that has already been processed on this subscription

                if ( $subscription->updateFromBraintree($btSub) ) {
                    // we want to make sure that the success of this webhook gets recorded in the DB, otherwise
                    // it may be overwritten by an older, pending webhook.
					$insert = $db->prepare("INSERT INTO " . self::webhooksTableName . "
                         SET
                            kind = :kind,
                            timestamp = :timestamp,
                            subscription_id = :id,
                            subscription_status = :status"
					);
					return $insert->execute(array(
						":kind"		 => $hookKind,
						":timestamp" => $hookDT,
						":id"		 => $subId,
						":status"	 => $btSub->status
					));
                }   else {
                    return FALSE;
                }
            }   else {
                // another, more recent webhook has already been processed by this subscription
            }
        }   else {
            //error_log("The subscription with id={$subId} could not be found in the local database. Should it be constructed?");
            return false;
        }
    }


    /**
     * getSubscriptionsPendingRenewal returns a list with all the subscriptions that are about to renew in the following $daysToRenewal days
     * This function only searches for subscriptions that are in trial mode or on a 1-year plan
     * @param $daysToRenewal int how many days from today can the subscriptions renew to be returned by the search
     @UNUSED
	 @TODO
    public static function getSubscriptionsPendingRenewal( $daysToRenewal )
    {
        /* @DEBUG: add the subscriptions here manually if you don't want to perform the braintree search
        $subs = [];
        $subs["BT:8m6z8b"] = Braintree_Subscription::find("cffvyw");
        $subs["BT:8g7xs6"] = Braintree_Subscription::find("8g7xs6");
        return $subs;
        // @DEBUG-END

        // calculate the maximum renewal date to search for
        // the subscriptions run from midnight to midnight, as seen here: https://developers.braintreepayments.com/guides/recurring-billing/create/php#subscription-days
        // the gateway timezone is CST (Central Standard Time) <=> 'America/Chicago'
        $date        = new DateTime("midnight America/Chicago");

        // add a 1-hour padding to the end date
        $endDateTo   = clone $date;
        $endDateTo  -> modify( "+$daysToRenewal days +1 hours" );
        // limit the search to subscriptions that have the renewal date as early as today
        $endDateFrom = clone $date;

        $subscriptionIds = [];

        // Search for active trial subscriptions whose first cycle will start between $endDateFrom -> $endDateTo
        try {
            $trialSubscriptions = Braintree_Subscription::search([
                Braintree_SubscriptionSearch::status()->is(Braintree_Subscription::ACTIVE),
                Braintree_SubscriptionSearch::inTrialPeriod()->is(true),
                Braintree_SubscriptionSearch::nextBillingDate()->between($endDateFrom, $endDateTo)
            ]);

            foreach ($trialSubscriptions as $sub) {
                $subscriptionIds[ $sub->id ] = $sub;
            }
        }   catch(Exception $e) {
            return null;
        }

        // Search for active subscriptions on the yearly plan
        try {
            // Look for subscriptions that have neverEnding=true OR at least 1 billing cycle remaining
            // we can't include the billingCyclesRemaining()->greaterThanOrEqualTo(1) condition because the search won't return subscriptions with neverExpires=TRUE
            $trialSubscriptions = Braintree_Subscription::search([
                // yearly plan = dddb
                Braintree_SubscriptionSearch::planId()->in(['dddb']), // @DEBUG: change this back to 6f76 for monthly plans
                // status = Active
                Braintree_SubscriptionSearch::status()->is(Braintree_Subscription::ACTIVE),
                // billing date between endDateFrom -> endDateTo
                Braintree_SubscriptionSearch::nextBillingDate()->between($endDateFrom, $endDateTo)
            ]);

            foreach ($trialSubscriptions as $sub) {
                if ( $sub->neverExpires || $sub->currentBillingCycle < $sub->numberOfBillingCycles )
                    $subscriptionIds[ $sub->id ] = $sub;
            }
        }   catch(Exception $e) {
            return null;
        }

        //
        return $subscriptionIds;
    }
	 */
	
    /**
     * getOutOfSynchSubscriptions
     * @return array a list with the Braintree IDs for all the subscriptions in our database that appear to have their status out of synch, and should be expired or past due according to their dates
     */
    public static function getOutOfSynchSubscriptionIDs()
    {
        $sql = "SELECT id
                FROM `".self::tableName."`
                WHERE status='Active' AND ends_at<date(NOW())";

        $subscriptionIds = array();
		foreach ( pdoConnect()->query($sql, PDO::FETCH_OBJ) as $obj ) {
            $subscriptionIds []= $obj->id;
        }

        return $subscriptionIds;
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // UTIL functions

    private static function isStatusAlive( $status )
    {
        return $status == Braintree_Subscription::ACTIVE ||
               $status == Braintree_Subscription::PENDING ||
               $status == Braintree_Subscription::PAST_DUE;
    }

	private static function isStatusExpiredOrCanceled($status )
	{
		return $status == Braintree_Subscription::EXPIRED ||
		       $status == Braintree_Subscription::CANCELED;
	}
}

?>