<?php

/**
 * Class LcAccount
 * Stores details for a billable account.
 * 	All Subscription and Invoice actions are done through the account interface; this makes sure that no account
 * can view or edit another account's details.
 */
class LcAccount
{
	// @TODO: implement variable, per-account tax rates
	const TAX_RATE			= 0.1;

	// set this to false to continue the payment retry of past due subscriptions if we fail to update the payment method
	const haltRetryOnPaymentMethodChangeFail	= true;

	// account type: TEAM vs SINGLE
	const TYPE_TEAM			= 1;
	const TYPE_SINGLE 		= 2;

	// billing accounts table name
	const tableName			= "billing_account";
    // const teamTableName		= "companies";
	// const singleTableName	= "uf_users";
	/**
	 * 		User Role Control
	 * @var $userRole LcUserRole			the role in the current billing account of the logged-in user
	 */
	private $userRole;
	
	/**
	 * 		Account details
     * @var $id int							the unique ID of this billing account
	 * @var $type int						TYPE_TEAM or TYPE_SINGLE
	 * @var $clientId int					represents either a builder, or a user ID
	 * @var $customerId string				braintree customer ID associated with this account; e.g. '13528174'
	 * @var $customer Braintree_Customer	the braintree customer object
	 * @var $paymentMethods array			array of Braintree_PaymentMethod objects
	 * @var $currencyIsoCode string			@TODO [this is now redundant; use the merchant account currency] the default currency for this account
     */
	private $id;
	private $type;
	private $clientId;
	private $customerId;
	private $customer;
	private $paymentMethods;
	private $currencyIsoCode;

	/**
	 * 		Merchant Account that this customer is billed on
	 * @var $merchantAccount LcMerchantAccount	The merchant account that the current account is billed on; contains location, currency, tax settings
	 */
	private $merchantAccount;

	/**
	 * 		Account Options
	 * @var $price double
	 * @var $startNextMonth int
	 * @var $name string
	 * @var $addressCity string
	 * @var $addressPostalCode string
	 * @var $addressStreet string
	 * @var $addressRegion string
	 * @var $addressCountry string
	 * @var $agreementEndDate DateTime
	 * @var $statesLicensed string
	 * @var $abn string
	 */
	public $price;
	public $startNextMonth;
	public $name;

	public $addressCity;
	public $addressPostalCode;
	public $addressStreet;
	public $addressRegion;
	public $addressCountry;

	public $agreementEndDate;
	public $statesLicensed;
	public $abn;

	/**
	 * 		Subscription details
	 * @var $subList array					the list of all the subscriptions created on this account
	 */
	// private $subCost;
	private $subList;

	/**
	 * @var LcSubscription 					the latest (if any) subscriptions created on this account
	 */
	private $subscription;
	
	/**
	 *		One-time invoices
	 * @var $invoices array					all the invoices associated with this account, and their receipts
	 */
	private $invoices;

	/**
	 * @var $outstandingInvoices array		(helper) list of invoices that have yet to be paid
	 */
	private $outstandingInvoices;
	
	/**
	 * 		Database object
	 * @var $syncWithBraintree Boolean		true if loading
	 * @var $valid Boolean
	 * @var $dbObj stdClass
	 */
	private $syncWithBraintree;
	private $valid;
	private $dbObj;


	/**
	 * LcAccount constructor.
	 * @param $id int 						the ID of the billing account
	 * @param $loggedInUser loggedInUser	the currently logged-in user requesting access to this billing account's details
	 * @param $syncWithBraintree bool		flag that indicates if the user's details should be synced with Braintree
	 */
	public function __construct( $id, $loggedInUser, $syncWithBraintree=false, $loadSubscription=true )
	{
		$start = microtime(true);
		$this->id	= intval($id);
		$this->syncWithBraintree = $syncWithBraintree;

		// fetch the account details from the database
		$this->fetch();

		if ( $loggedInUser ) {
			// establish the role of the currently logged-in user on this account
			$this->userRole = new LcUserRole(
			// the currently logged-in user
				$loggedInUser,
				// see if the logged in user is actually linked to this billing account
				// for single users, check that the user id is the same as the
				$this->clientId == (
				$this->type == self::TYPE_TEAM ?
					$loggedInUser->company_id :
					$loggedInUser->user_id
				)
			);
		}	else {
			// this must be an automatic call from the webhook processor, so we have no logged-in user
			// the user role will have no access
			$this->userRole = new LcUserRole( null, false );
		}

		// if the access is not authorized, exit the app here
		if ( $this->userRole->role() == LcUserRole::ROLE_NONE ) {
			$this->valid = false;
			Config::apiError("Unauthorized access.");
			Config::halt();
		}
		
		if ($this->valid){
            if ($loadSubscription) {
                /// load the subscriptions
                $this->subList = LcSubscription::all($this->id, $this->syncWithBraintree);
                // load the latest subscription, it is the only one we will usually work with
                if ($this->subList && count($this->subList)) {
                    $this->subscription = $this->subList[0];
                }
            }

            /* @lazy-loading: invoices, payment methods, customer details */
        }
	}

	/**
	 * @lazy-loading
	 * load the payment methods for this account
	 */
	private function loadPaymentMethods()
	{
		// only load the payment methods if the user can make payments
		if ( !$this->paymentMethods && $this->userRole->canMakePayments() ) {
			// load this account's customer details; create one if one doesn't exist
			$this->loadCustomerDetails();

			if ( $this->customer ) {
				// save a reference to the payment methods
				/* @INFO
				 * Array of Braintree_PaymentMethod objects
				 * paymentMethods is a method in versions 2.28.0 to 3.1.0 of the PHP library. It is now an attribute.
				 * The method will be deprecated in a future release.
				 */
				$this->paymentMethods = $this->customer->paymentMethods();
			}
		}
	}

	/**
	 * @lazy-loading
	 * load the invoices for this account
	 */
	private function loadInvoices($loadFromBraintree=true)
	{
		global $stats;
		$start = microtime(true);
		if ( !$this->invoices ) {
			// load all the one-time invoices
			$this->invoices = LcInvoice::allOf($this->id, $this->customerId, $loadFromBraintree);

			// setup the outstanding invoices
			$this->outstandingInvoices = array();

			/**
			 * @var $invoice LcInvoice
			 */
			foreach ($this->invoices as $invoice) {
				if ( !$invoice->isPaidOrSettling() ) {
					$this->outstandingInvoices[] = $invoice;
				}
			}
		}
		$stats["account-invoices"] = microtime(true) - $start;
	}

	function __destruct() { }

	private function fetch( )
	{
		$db = pdoConnect();
		$statement = $db->prepare(
			"SELECT *
			 FROM ".self::tableName."
			 WHERE id=:id"
		);

		if	( $statement->execute( array(":id" => $this->id ) ) &&
			( $this->dbObj=$statement->fetch(PDO::FETCH_ASSOC) ) ) {
			// we have found a valid record for this account
			$this->valid				= true;
			$this->type					= $this->dbObj["type"];
			$this->clientId				= $this->dbObj["client"];
			$this->customerId			= $this->dbObj["customer"];
			$this->currencyIsoCode		= $this->dbObj["currencyIsoCode"];

			// Setup the merchant account
			$this->merchantAccount      = LcMerchantAccount::getFromID(
                $this->dbObj["merchantAccountId"]
            );

			$this->price				= $this->dbObj["price"];
			$this->startNextMonth		= $this->dbObj["start_next_month"];
			$this->name					= $this->dbObj["name"];

			$this->addressCity			= $this->dbObj["address_city"];
			$this->addressPostalCode	= $this->dbObj["address_postal_code"];
			$this->addressStreet		= $this->dbObj["address_street"];
			$this->addressRegion		= $this->dbObj["address_region"];
			$this->addressCountry		= $this->dbObj["address_country"];

			$this->agreementEndDate		= DateTime::createFromFormat( Config::SQL_DATETIME, $this->dbObj["agreement_end_date"] );

			$this->statesLicensed		= $this->dbObj["states"];
			$this->abn					= $this->dbObj["abn"];
		}	else {
			$this->valid = false;
		}
	}

	private function commit( )
	{
		$db = pdoConnect();

		$statement = $db->prepare(
			"UPDATE ".self::tableName.
			" SET customer=:customer".
			" WHERE id=:id"
		);
		return $statement->execute(array(
			":id"		=> $this->id,
			":customer"	=> $this->customerId
		));
	}

	/**
	 * @return bool
	 */
	public function isValid() { return $this->valid; }

	/**
	 * @return int
	 */
	public function getId() { return $this->id; }

	/**
	 * @return int the billing account type
	 */
	public function getType() { return $this->type; }

	/**
	 * @return int the client ID
	 */
	public function getClientId() { return $this->clientId; }

	/**
	 * @return string the Braintree customer ID associated to this account
	 */
	public function getCustomerId() { return $this->customerId; }

	/**
	 * @return string the currency code for this account
	 */
	public function getCurrencyIsoCode() { return $this->currencyIsoCode; }

	/**
	 * @return LcUserRole the permissions of the logged-in user on this billing account
	 */
	public function getUserRole() { return $this->userRole; }

	/**
	 * @return LcSubscription
	 */
	public function getSubscription() { return $this->subscription; }

	/**
	 * @return string
	 */
	public function getAddress()
	{
		$pieces = array();

		if ( $this->addressStreet && strlen($this->addressStreet) )
			$pieces[] = $this->addressStreet;
		if ( $this->addressCity && strlen($this->addressCity) )
			$pieces[] = $this->addressCity;
		if ( $this->addressRegion && strlen($this->addressRegion) )
			$pieces[] = $this->addressRegion;
		if ( $this->addressPostalCode && strlen($this->addressPostalCode) )
			$pieces[] = $this->addressPostalCode;
		if ( $this->addressCountry && strlen($this->addressCountry) )
			$pieces[] = $this->addressCountry;

		if ( sizeof($pieces) )
			return join(", ", $pieces);

		return "N/A";
	}

	/**
	 * @return string
	 */
	public function getEndDate()
	{
		if ( $this->agreementEndDate ) {
			return $this->agreementEndDate->format( Config::DISPLAY_DATETIME );
		}

		return "N/A";
	}

	/**
	 * @return string
	 */
	public function getABN()
	{
		if ( $this->abn && strlen($this->abn)>3 )
			return $this->abn;

		return "N/A";
	}

	/**
	 * @return string
	 */
	public function getLicensedStates()
	{
		if ( $this->statesLicensed && strlen($this->statesLicensed) )
			return $this->statesLicensed;

		return "N/A";
	}

	/**
	 * @return array
	 */
	public function getInvoices() {
		// @lazy-loading
		$this->loadInvoices();
		return $this->invoices;
	}

	/**
	 * @return array
	 */
	public function getOutstandingInvoices($loadFromBraintree=true) {
		// @lazy-loading
		$this->loadInvoices($loadFromBraintree);
		return $this->outstandingInvoices;
	}

	/**
	 * @return array
	 */
	public function getAdminEmails() {
		$db = pdoConnect();
		$adminSearch = $db->prepare(
			"SELECT id, email
			 FROM uf_users
			 WHERE
			 	billing_access_level = :access_level AND
			 	company_id			 = :cid "
		);

		$list = array();

		if ( $adminSearch->execute( array(
			":access_level"	=> LcUserRole::BILLING_LEVEL_FULL,
			":cid"			=> $this->getClientId()
		) ) ) {
			while ( ( $adminRecord=$adminSearch->fetch(PDO::FETCH_ASSOC) ) != NULL ) {
				// $adminRecord["id"]
				$list[ ] = $adminRecord["email"];
			}
		}

		return $list;
	}

	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Customer/Client functionality
    //
    // - customer = the Braintree customer associated with a billing account. all payment methods, braintree subscriptions,
    //              transactions, etc. will be associated to one customer for each account.

	public function role() {
		return $this->userRole;
	}

    /**
     * @return string the client token generated for this account's customer
     */
    public function getClientToken( )
    {
		// account usage permission is required for this operation
		if ( $this->userRole->canUse() ) {
			return Braintree_ClientToken::generate(array(
				"customerId" => $this->customerId
			));
		}	else {
			Config::apiError( $this->userRole->useDenied() );
			return NULL;
		}
    }
	
    /**
     * loadCustomerDetails
     */
    public function loadCustomerDetails( )
    {
		if ( $this->customerId && !$this->customer ) {
			 // see if the associated customerId exists in the Braintree records
			 $this->fetchBraintreeCustomer();
		}
		
		// if either customerId is null, or it can't be found in the braintree records (maybe we switched from sandbox to production?), we create a new customer
		if ( !$this->customer ) {
			// try to create the customer
			if ( !$this->createBraintreeCustomer( ) ) {
				Config::apiError( "We could not register you with our payment provider at this time. Please contact us to let us know you've had this issue." );
			}	else {
				// make sure to save this customer ID to the database
				$this->customerId	= $this->customer->id;
				$this->commit();
			}
		}
    }
	
    /**
     * @return Braintree_Customer
     */
    private function fetchBraintreeCustomer( )
    {
		// don't fetch the customer twice
		if ( !$this->customer ) {
			try {
				$this->customer = Braintree_Customer::find( $this->customerId );
			}   catch ( Braintree_Exception_NotFound $e ) {
				error_log('failure in LcAccount::fetchBraintreeCustomer: no customer found with id: '.$this->customerId);
			}   catch( Braintree_Exception $e ) {
				error_log('unknown braintree failure in '.__FILE__.'::'.__FUNCTION__.': '.$e->getMessage());
			}   catch( Exception $e ) {
				error_log('unknown failure in '.__FILE__.'::'.__FUNCTION__.': '.$e->getMessage());
			}
		}
    }
	
    /**
     * @return Braintree_Customer
     */
    private function createBraintreeCustomer( )
    {
        try {
			// @TODO: fill the customer fields with this account's client (enterprise/user)
            $result = Braintree_Customer::create(array(
                // 'firstName' => $userObject->username,
                // 'email' => $userObject->email
            ));

            if ( $result->success  && $result->customer ) {
                // associate the customer ID to the user
				$this->customerId	= $result->customer->id;
				
				if ( $this->commit() ) {
					// save the customer object
					$this->customer			= $result->customer;
					return true;
				}
            }
        }   catch( Braintree_Exception $e ) {
			error_log('unknown braintree failure in '.__FILE__.'::'.__FUNCTION__.': '.$e->getMessage());
        }   catch( Exception $e ) {
			error_log('unknown failure in ' . __FILE__ . '::' . __FUNCTION__ . ': ' . $e->getMessage());
        }

        return true;
    }

    /**
     * updateUserCustomerEmail - changes the user's email in their braintree customer record
     */
    public function updateCustomerEmail( $newEmail )
    {
		if ( $this->userRole->canManageBilling() ) {
			$this->loadCustomerDetails();

			if ($this->customerId) {
				$updateResult = Braintree_Customer::update(
					$this->customerId,
					array( 'email' => $newEmail )
				);
				return $updateResult->success;
			}

			// email not changed or customer doesn't exist for specified user
			return false;
		}	else {
			Config::apiError( $this->userRole->editBillingDenied() );
			return false;
		}
    }
	
	/**
	 * used to make payments
	 * @return Braintree_PaymentMethod the default payment method for this account
	 */
	public function getDefaultPaymentMethod( )
	{
		if ( $this->userRole->canMakePayments() ) {
			// make sure we have the payment methods
			$this->loadPaymentMethods();

			if ( $this->paymentMethods ) {
				foreach ($this->paymentMethods as $paymentMethod) {
					/**
					 * @var $paymentMethod Braintree_PaymentMethod
					 */
					if ($paymentMethod->isDefault())
						return $paymentMethod;
				}
			}
			
			return NULL;
		}	else {
			Config::apiError( $this->userRole->paymentsDenied() );
			return NULL;
		}
	}

	/**
	 * @return string
	 */
	private function getDefaultPaymentMethodToken()
	{
		$paymentMethod = $this->getDefaultPaymentMethod();
		return $paymentMethod ? $paymentMethod->token : NULL;
	}

	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Merchant accounts

	/** @return LcMerchantAccount */
	public function getMerchantAccount() { return $this->merchantAccount; }


	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Subscription functionality
	// @TODO: interface all of the other Subscription operations here for safety
	// @TODO: functionality for overdue subscriptions

	/**
	 * checks if the current account is subscribed to use the Landconnect services
	 * @return Boolean
	 */
	public function isSubscribed()
	{
		if ( $this->userRole->canUse() ) {
			return $this->subscription && $this->subscription->isAlive();
		}	else {
			Config::apiError( $this->userRole->useDenied() );
			return false;
		}
	}

	/**
	 * checks if the current account is overdue and cannot be used anymore until the payment is processed
	 * @return bool
	 */
	public function isOverdue()
	{
		if ( $this->userRole->canUse() ) {
			return $this->subscription && $this->subscription->isHardOverdue();
		}	else {
			Config::apiError( $this->userRole->useDenied() );
			return false;
		}
	}

	/**
	 * @TODO: implement overdue warnings
	 */

	/**
	 * subscribes the current account to the indicated plan ID (or a custom plan for enterprise accounts)
	 * @param string $paymentMethodNonce	the payment method nonce that will be used to make the subscription
	 * @param string $aPlanId				the plan ID to use for the subscription
	 * @param stdClass $planParams			optional parameters to apply to the subscription when it's created
	 * @return bool
	 */
	public function subscribeToPlan( $paymentMethodNonce, $aPlanId=null, $planParams=null )
	{
		if ( !$this->merchantAccount ) {
			Config::apiError( "You must set up a correct billing address country before subscribing. Please contact us if you set up your country when you receive this error." );
			return null;
		}

		// OWNER privileges are needed to subscribe
		if ( $this->userRole->canSubscribe() ) {
			// make sure we are not already subscribed
			if ($this->isSubscribed()) {
				Config::apiError( "You are already subscribed to Footprints." );
				return NULL;
			}

			if (!$planParams) $planParams = array();

			// check the account type
			if ($this->type == self::TYPE_TEAM) {
				$aPlanId    = $this->merchantAccount->getSubscriptionPlanId();
				$planParams = $this->loadTeamSubscriptionSettings($planParams);
			}

			// @TEST: this doesn't work well because entering/selecting a new payment method in the drop-in form
			// doesn't make it the default payment method on that account

			// verify that we are authorized to make the payment
			$paymentMethodToken = null;
			if (!$paymentMethodNonce) {
				// try to use the default payment method
				$paymentMethodToken = $this->getDefaultPaymentMethod();

				// if we couldn't get it, quit
				if (!$paymentMethodToken) {
					Config::apiError( "You don't have any valid payment methods on your account, please add one first." );
					return NULL;
				}
			}
			
			// verify that the provided planId is valid
			if (!$aPlanId || !self::getSubscriptionPlan($aPlanId)) {
				Config::apiError( "This offer is not available for your account." );
				return NULL;
			}

			// create the subscription
			$params = array("planId" => $aPlanId);

			// save the payment method authorization
			$paymentMethodNonce ?
				$params["paymentMethodNonce"] = $paymentMethodNonce :
				$params["paymentMethodToken"] = $paymentMethodToken;

			// create the subscription
			// if (($this->subscription = LcSubscription::create($this->id, $params, $planParams)) != NULL) {
			if (($this->subscription = LcSubscription::create($this, $params, $planParams)) != NULL) {
				$this->subList[] = $this->subscription;
			} else {
				// the error should've been set in LcSubscription
				return NULL;
			}

			return $this->subscription;
		}	else {
			Config::apiError( $this->userRole->subscribeDenied() );
			return NULL;
		}
	}

	/**
	 * @param string $paymentNonce (optional) the nonce to use for this payment retry
	 * @return bool
	 */
	public function retrySubscriptionPayment( $paymentNonce )
	{
		// OWNER privileges are needed to retry the subscription payment
		if ( $this->userRole->canSubscribe() ) {
			// make sure we are not already subscribed
			if ($this->isSubscribed()) {
				Config::apiError("You are already subscribed to Footprints.");
				return false;
			}

			if (!$this->subscription) {
				Config::apiError("You are not subscribed yet!");
				return false;
			}

			// if this method fails, we let it fail silently - unless the subscription retry fails also
			if ( !$this->subscription->changePaymentMethod(
				$this->customerId,
				$paymentNonce
			) && self::haltRetryOnPaymentMethodChangeFail ) {
				Config::apiError( "We couldn't change your default payment method" );
				return false;
			}

			// If the Subscription's payment method successfully changed, it will now contain the latest paymentMethodToken
			// we can use it to set the default payment method on the account
			try {
				Braintree_PaymentMethod::update(
					// the token for the new payment method
					$this->subscription->subscription->paymentMethodToken,
					array(
						"options" => array(
							'makeDefault' => true
						)
					)
				);
			}	catch (Exception $e) {
				// ignore failures as this is not a critical change
			}

			// retry the payment on the subscription
			return $this->subscription->retryPayment( );
		}	else {
			Config::apiError( $this->userRole->subscribeDenied() );
			return false;
		}
	}

	/**
	 * @param string $paymentNonce (optional) the nonce to use for this payment retry
	 * @return bool
	 */
	public function editSubscriptionPaymentMethod( $paymentNonce )
	{
		// OWNER privileges are needed to edit the subscription
		if ( $this->userRole->canManageBilling() ) {
			// make sure there is a subscription to edit
			if ( !$this->subscription ) {
				Config::apiError("You are not subscribed yet!");
				return false;
			}

			// change the payment method
			if ( !$this->subscription->changePaymentMethod( $this->customerId, $paymentNonce ) ) {
				Config::apiError( "We couldn't change the payment method associated with the subscription" );
				return false;
			}

			// If the Subscription's payment method successfully changed, it will now contain the latest paymentMethodToken
			// we can use it to set the default payment method on the account
			try {
				Braintree_PaymentMethod::update(
				// the token for the new payment method
					$this->subscription->subscription->paymentMethodToken,
					array(
						"options" => array(
							'makeDefault' => true
						)
					)
				);
			}	catch (Exception $e) {
				// ignore failures as this is not a critical change
			}

			return true;
		}	else {
			Config::apiError( $this->userRole->manageBillingDenied() );
			return false;
		}
	}

	/**
	 * @param $planParams
	 * @return mixed
	 */
	public function loadTeamSubscriptionSettings( $planParams=NULL )
	{
		if ( !$planParams ) $planParams = array();
		
		if ( $this->type == self::TYPE_TEAM ) {
			if ( $this->price ) {
				$planParams["price"] = $this->price;
			}

			if ( $this->startNextMonth ) {
				$planParams["firstBillingDate"] = $this->getSubscriptionStartDate();
			}
		}
		// load the subscription settings for this team
		return $planParams;
	}

	/**
	 * @return DateTime
	 */
	public function getSubscriptionStartDate()
	{
		return DateTime::createFromFormat(
			"U",
			strtotime(
				$this->startNextMonth==1 ?
					'first day of next month' :
					"first day of +{$this->startNextMonth} month"
			)
		);
	}

	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Invoice functionality

	/**
	 * @return TRUE if all the mandatory invoices are settled on this account
	 */
	public function areInvoicesSettled()
	{
		// @lazy-loading
		$this->loadInvoices();

		if ( $this->userRole->canUse() ) {
			foreach ($this->invoices as $invoice) {
				// a mandatory invoice allows access either if it's paid, or if its due date didn't arrive yet
				if ($invoice->isMandatory() && !(
						$invoice->isPaidOrSettling() ||
						$invoice->getDaysLeftToPay() >= 0
					)
				) {
					return false;
				}
			}

			return true;
		}	else {
			Config::apiError( $this->userRole->useDenied() );
			return false;
		}
	}

	/**
	 * @param $name string
	 * @param $details string
	 * @param $amount float
	 * @param $dueAt DateTime
	 * @param $isMandatory bool
	 * @return bool
	 */
	public function createInvoice( $name, $details, $amount, $dueAt, $isMandatory )
	{
		if ( $this->userRole->canMakePayments() ) {
			$invoice = LcInvoice::create(
				$this->id,
				$name, $details, $amount, $dueAt, $isMandatory
			);

			if ($invoice) {
				if ( !$this->invoices ) {
					$this->invoices = array();
				}

				$this->invoices[] = $invoice;
				return true;
			}

			return false;
		}	else {
			Config::apiError( $this->userRole->paymentsDenied() );
			return false;
		}
	}

	/**
	 * @param $invoiceId string
	 * @param $name string
	 * @param $details string
	 * @param $amount float
	 * @param $dueAt DateTime
	 * @param $isMandatory bool
	 * @return bool
	 */
	public function editInvoice( $invoiceId, $name, $details, $amount, $dueAt, $isMandatory )
	{
		if ( $this->userRole->canEditInvoice() ) {
			if (($invoice = $this->findInvoice($invoiceId))) {
				return $invoice->edit($name, $details, $amount, $dueAt, $isMandatory);
			} else {
				return false;
			}
		}	else {
			Config::apiError( $this->userRole->editInvoiceDenied() );
			return false;
		}
	}

	/**
	 * @param $invoiceId string
	 * @return bool
	 */
	public function deleteInvoice( $invoiceId )
	{
		if ( $this->userRole->canDelete() ) {
			if (($invoice = $this->findInvoice($invoiceId))) {
				return $invoice->delete();
			} else {
				return false;
			}
		}	else {
			Config::apiError( $this->userRole->deleteDenied() );
			return false;
		}
	}

	/**
	 * @param $invoiceId string
	 * @param $paymentMethodNonce string
	 * @return bool
	 */
	public function payInvoice( $invoiceId, $paymentMethodNonce=NULL )
	{
		if ( $this->userRole->canMakePayments() ) {
			// make sure that we have a NONCE or TOKEN to pay with
			$paymentMethodToken = NULL;
			if (!$paymentMethodNonce) {
				// fetch the default payment method
				$paymentMethodToken = $this->getDefaultPaymentMethodToken();
				if (!$paymentMethodToken) {
					// @TODO: display a message indicating that no payment method exists on the account
					// when a portal user wants to make a payment
					Config::apiError("No payment method has been selected.");
					return false;
				}
			}

			if (($invoice = $this->findInvoice($invoiceId))) {
				return $invoice->pay($this, $paymentMethodToken, $paymentMethodNonce);
			} else {
				return false;
			}
		}	else {
			Config::apiError( $this->userRole->paymentsDenied() );
			return false;
		}
	}

	/**
	 * @param $invoiceId string
	 * @return bool
	 */
	public function refundInvoice( $invoiceId )
	{
		if ( $this->userRole->canRefund() ) {
			if (($invoice = $this->findInvoice($invoiceId))) {
				return $invoice->refund();
			} else {
				return false;
			}
		}	else {
			Config::apiError( $this->userRole->refundDenied() );
			return false;
		}
	}

	/**
	 * @param $invoiceId string
	 * @param $errorOnNotFound bool
	 * @return LcInvoice
	 */
	public function findInvoice( $invoiceId, $errorOnNotFound=true )
	{
		// @lazy-loading
		$this->loadInvoices();

		/**
		 * @var $invoice LcInvoice
		 */
		// see if that invoice exists and is associated to this account
		foreach ( $this->invoices as $invoice ) {
			if ( $invoice->getId() == $invoiceId ) {
				return $invoice;
			}
		}

		$errorOnNotFound && Config::apiError("The invoice was not found on this account.");
		return NULL;
	}

	/**
	 * @param $invoiceId
	 * @return LcInvoice|null
	 */
	public function loadSingleInvoice( $invoiceId )
	{
		$invoice = new LcInvoice( $invoiceId, true );
		if ( $invoice->getAccountId() == $this->id ) {
			return $invoice;
		}

		return null;
	}

	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Self-Account management for users

	/**
	 * this interface is used for fetching the account for the currently logged-in user;
	 * do not use this for the admin account, but use the constructor directly
	 * @param $loggedInUser loggedInUser	the currently logged-in user
	 * @param $createIfMissing bool			set to true to create a billing account if it doesn't exist for the given user
	 * @param $syncWithBraintree bool		set to true to load the subscription details from braintree
	 * @return LcAccount					this user's billing account, or NULL if none exists
	 */
	public static function getForSelf( $loggedInUser, $createIfMissing=false, $syncWithBraintree=false, $loadSubscription=true )
	{
		// this function is a security wrapper for the _getForSelfHelper worker
		$account = self::_getForSelfHelper( $loggedInUser, $createIfMissing, $syncWithBraintree, $loadSubscription );

		if ( $account ) {
			// make sure the user can use the account.
			// this should always be the case because of how the fetch/creation of the account is handled
			if ($account->userRole->canUse()) {
				return $account;
			} else {
				Config::apiError($account->userRole->useDenied());
			}
		}

		return NULL;
	}
	// this function does the actual work for ::getForSelf
	private static function _getForSelfHelper( $loggedInUser, $createIfMissing, $syncWithBraintree, $loadSubscription=true )
	{
		if ( isGlobalAdmin($loggedInUser->user_id) ) {
			// Config::apiError("Incorrect interface usage.");
			return NULL;
		}
		if ( !$loggedInUser ) {
			Config::apiError("No logged-in user.");
			return NULL;
		}

		// prepare and execute the query
		$db = pdoConnect();
		$accountSearch = $db->prepare(
			"SELECT id
			 FROM ".self::tableName."
			 WHERE type=:type AND client=:client"
		);

		// fetch the billing account ID for the current user
		if	(
			$accountSearch->execute( array(
				":type"   => self::typeFromCid( $loggedInUser->company_id ),
				":client" => self::billingIdForUser( $loggedInUser )
			) ) &&
			(
				$accountObj = $accountSearch->fetch(PDO::FETCH_ASSOC)
			) ) {

			// return the billing account
			return new LcAccount( $accountObj["id"], $loggedInUser, $syncWithBraintree, $loadSubscription );
		}	else {
			// can we create the missing account?
			if ( $createIfMissing ) {
				return self::createForSelf( $loggedInUser );
			}	else {
				Config::apiError("No billing account has been found.");
			}
		}

		return NULL;
	}

	/**
	 * @param $loggedInUser loggedInUser
	 * @return LcAccount
	 */
	public static function createForSelf( $loggedInUser )
	{
		// no permission check is required here
		return self::createAccountWithParams(array(
			":type"		=> self::typeFromCid( $loggedInUser->company_id ),
			":client"	=> self::billingIdForUser( $loggedInUser )
		),	$loggedInUser);
	}

	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Global billing account management for admins

	/**
	 * @param $loggedInUser loggedInUser
	 * @return array
	 */
	public static function all( $loggedInUser, $syncWithBraintree=false )
	{
		// permission is checked through the global admin system
		if ( isGlobalAdmin($loggedInUser->user_id) ) {
			$db = pdoConnect();
			$accountSearch = $db->prepare( "SELECT id FROM ".self::tableName );
			$list = array();

			if ( $accountSearch->execute() ) {
				while ( ( $accountRecord=$accountSearch->fetch(PDO::FETCH_ASSOC) ) != NULL ) {
					$list[] = new LcAccount( $accountRecord["id"], $loggedInUser, $syncWithBraintree );
				}
			}

			return $list;
		}	else {
			// don't return any error, this usage is forbidden.
			return NULL;
		}
	}

	/**
	 * @param $loggedInUser loggedInUser
	 * @param $companyId int
	 * @return LcAccount
	 */
	public static function getForCompany( $loggedInUser, $companyId, $createIfMissing=true )
	{
		if ( isGlobalAdmin($loggedInUser->user_id) ) {
			// prepare and execute the query
			$db = pdoConnect();
			$accountSearch = $db->prepare(
				"SELECT id
				 FROM ".self::tableName."
				 WHERE type=:type AND client=:client"
			);

			// fetch the billing account ID for the current user
			if	(
				$accountSearch->execute( array(
					":type"		=> self::TYPE_TEAM,
					":client" 	=> $companyId
				) ) &&
				(
					$accountObj = $accountSearch->fetch(PDO::FETCH_ASSOC)
				)
			) {
				// return the billing account
				return new LcAccount( $accountObj["id"], $loggedInUser );
			}	else {
				// can we create the missing account?
				if ( $createIfMissing ) {
					return self::createAccountWithParams( array(
						":type"		=> self::TYPE_TEAM,
						":client" 	=> $companyId
					), $loggedInUser );
				}	else {
					Config::apiError("No billing account has been found.");
				}
			}
		}

		return NULL;
	}

	/**
	 * returns the billing account that corresponds to a certain subscription
	 * this must only be used by the webhook processor as the returned account will not expose any functionality,
	 * only information
	 */
	public static function getForSubscription( $subId )
	{
		$db = pdoConnect();
		$accountSearch = $db->prepare(
			"SELECT account_id
			 FROM ".LcSubscription::tableName."
			 WHERE id=:id"
		);

		// fetch the billing account ID for the current user
		if	(
			$accountSearch->execute( array(
				":id" => $subId
			) ) && ( $subObj = $accountSearch->fetch(PDO::FETCH_ASSOC) )
		) {
			// return the billing account
			return new LcAccount( $subObj["account_id"], null );
		}

		return null;
	}

	/**
	 * @param $loggedInUser loggedInUser
	 * @param $uid int
	 * @return LcAccount
	 * @TODO: make sure the user doesn't already have a billing account
	 */
	public static function createForUserID( $loggedInUser, $uid )
	{
		// permission is checked through the global admin system
		if ( isGlobalAdmin($loggedInUser->user_id) ) {
			// get the user details
			$db = pdoConnect();
			$statement = $db->prepare(
				"SELECT company_id
			 	 FROM uf_users
			 	 WHERE id=:id"
			);

			$uid = intval($uid);
			if	( $statement->execute( array(":id"=>$uid) ) && ( $userObj=$statement->fetch(PDO::FETCH_ASSOC) ) ) {
				// fetch the UID details
				return self::createAccountWithParams(array(
					":type"		=> self::typeFromCid( $userObj["company_id"] ),
					":client"	=> $uid
				),	$loggedInUser);
			}	else {
				Config::apiError("Unknown account {$uid}");
			}
		}	else {
			// don't return any error, this usage is forbidden.
			return NULL;
		}
	}


	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Static helpers/utils

	/**
	 * create
	 * @param $clientParams array
	 * @param $loggedInUser loggedInUser
	 * @return LcAccount
	 */
	private static function createAccountWithParams( $clientParams, $loggedInUser )
	{
		$db = pdoConnect();
		$accountCreate = $db->prepare(
			"INSERT INTO ".self::tableName."
					( type,  client ) 
			 VALUES (:type, :client )"
		);

		if ( $accountCreate->execute( $clientParams ) ) {
			// return the newly created account; this will create a Braintree customer
			// since this account has just been created, we don't need to do any BT synch
			return new LcAccount( $db->lastInsertId(), $loggedInUser );
		}	else {
			Config::apiError("There was an error creating your billing account.");
		}

		return null;
	}

	/**
	 * returns the type of the account based on the company id it belongs to
	 * @param $cid int
	 * @return int
	 */
	private static function typeFromCid( $cid ) {
		return $cid > 0 ? self::TYPE_TEAM : self::TYPE_SINGLE;
	}

	/**
	 * returns the unique ID that is used for this user's associated billing accounts;
	 * 		For single users, it is the user ID.
	 * 		For users in a company (or joint accounts), it is the company ID
	 * @param loggedInUser $loggedInUser
	 * @return int
	 */
	private static function billingIdForUser( $loggedInUser ) {
		return $loggedInUser->company_id > 0 ?
			$loggedInUser->company_id :
			$loggedInUser->user_id;
	}


	/**
	 * @param $aPlanId string the ID of the plan to lookup
	 * @return Braintree_Plan the plan with the given ID, or NULL if not found
	 */
	public static function getSubscriptionPlan( $aPlanId )
	{
		try {
			// fetch all plans
			$plans = Braintree_Plan::all();
			foreach ($plans as $plan)
				if ( $plan->id == $aPlanId )
					return $plan;

		}   catch( Braintree_Exception $e ) {
			error_log('unknown braintree failure in '.__FILE__.'::'.__FUNCTION__.': '.$e->getMessage());
		}   catch( Exception $e ) {
			error_log('unknown failure in '.__FILE__.'::'.__FUNCTION__.': '.$e->getMessage());
		}

		return NULL;
	}

	/**
	 * @param $merchantAccountId
	 * @return string the currency associated with this merchant account
	 */
	public static function getCurrency( $merchantAccountId )
	{
		switch ( strtolower($merchantAccountId) ) {
			case "landconnect":
			default:
				return "AUD";
		}
	}

	/**
	 * format a balance from a certain merchant account
	 * @param $balance
	 * @param $merchantAccountId
	 * @return string
	 */
	public static function formatCurrency( $balance, $merchantAccountId, $taxIncluded=true )
	{
		return self::formatCurrencyWithIsoCode(
			$balance,
			self::getCurrency( $merchantAccountId ),
			$taxIncluded
		);
	}

	///////////////////////////////////////////////////////////////////////////////////////////////////
    // @TODO Replace these with the LcMerchantAccount calls
    //

	/**
	 * @param $balance
	 * @param $currencyCode
	 * @param bool $taxIncluded
	 * @return string
	 */
	public static function formatCurrencyWithIsoCode( $balance, $currencyCode, $taxIncluded=true )
	{
		return "\$".$balance." ".$currencyCode.($taxIncluded ? " (GST included)" : "");
	}

	/**
	 * calculate the net (tax excluded) cost from the given gross amount
	 * @param $gross double
	 * @return double
	 */
	public static function netCost( $gross )
	{
		return $gross / ( 1 + self::TAX_RATE );
	}

	/**
	 * calculate the tax amount from the given gross amount
	 * @param $gross double
	 * @return double
	 */
	public static function taxFromGross( $gross )
	{
		return self::TAX_RATE * $gross / ( 1+self::TAX_RATE );
	}

	/**
	 * @param $net double
	 * @return double
	 */
	public static function taxFromNet( $net )
	{
		return self::TAX_RATE * $net;
	}

	/**
	 * return the tax rate as a percentage representation
	 * @return double
	 */
	public static function taxPercent()
	{
		return 100 * self::TAX_RATE;
	}
}

?>