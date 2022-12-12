<?php

/**
 * Class LcInvoice
 *
 * @TODO: optimize this by removing the need to load transaction data from Braintree,
 * @TODO		by saving the invoice status in our local database once the status becomes paid,settled etc
 */

class LcInvoice implements IInvoice
{
	const tableName = "billing_invoice";
	// start counting invoices generated in the billing system from 5001
	const DISPLAY_OFFSET = 5000;


	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Class Definition

	/**
	 * 		Invoice settings
	 * @var $id 					int this invoice's unique ID
	 * @var $accountId				string the ID of the account this invoice is assigned to
	 * @var $name					string this invoice's display name
	 * @var $details				string description of this invoice
	 * @var $amount					double the amount due for this invoice
	 * @var $createdAt DateTime the Date/Time the object was created
	 * @var $dueAt 					DateTime the date by which this invoice must be paid; usually instantly
	 * @var $isMandatory			int flag
	 * @var $transactionId			string the transaction ID for this invoice
	 * @var $transaction 			Braintree_Transaction the Braintree transaction object
     */
	private $id;
	private $accountId;
	private $name;
	private $details;
	private $amount;
	private $createdAt;
	private $dueAt;
	private $isMandatory;
	private $transactionId;
	private $transaction;
	
	private $products;

	/**
	 * 		Database object
	 * @var $valid Boolean
	 * @var $dbObj stdClass
	 */
	private $valid;
	private $dbObj;
	
	
	function __construct( $id, $loadFromBraintree=true )
	{
		$this->id	= $id;
		$this->fetch($loadFromBraintree);
	}

	function __destruct() {}

	static $fetchStatement;

	private function fetch($loadFromBraintree=true)
    {
		$db = pdoConnect();
		if (!self::$fetchStatement) {
			self::$fetchStatement = $db->prepare(
				"SELECT *
			 	 FROM ".self::tableName."
			 	 WHERE id=:id"
			);
		}

		if	( self::$fetchStatement->execute( array(":id" => $this->id ) ) &&
			( $this->dbObj=self::$fetchStatement->fetch(PDO::FETCH_ASSOC) ) ) {
			$this->valid		= true;
			$this->accountId	= $this->dbObj["account_id"];
			$this->name			= $this->dbObj["name"];
			$this->details		= $this->dbObj["details"];
			// $this->amount		= $this->dbObj["amount"];
			$this->createdAt	= DateTime::createFromFormat( Config::SQL_DATETIME, $this->dbObj["created_date"] );
			$this->dueAt		= DateTime::createFromFormat( Config::SQL_DATETIME, $this->dbObj["due_date"] );
			$this->isMandatory	= $this->dbObj["is_mandatory"];
			$this->transactionId= $this->dbObj["transaction_id"];

			if ( $loadFromBraintree && $this->transactionId && strlen($this->transactionId) ) {
				$this->transaction	= self::fetchBraintreeTransaction( $this->transactionId );
			}

			// load all the products
			$this->products		= LcInvoiceProduct::all( $this->id );

			// calculate the amount as a sum of the products' costs
			$this->amount		= 0;
			foreach ($this->products as $product) {
				$this->amount  += $product->cost * $product->quantity;
			}
		}	else {
			$this->valid	= false;
		}
    }

	private function commit()
	{
		$db = pdoConnect();

		$statement = $db->prepare(
			"UPDATE ".self::tableName."
			 	SET name			= :name,
			 		details			= :details,
			 		amount			= :amount,
			 		due_date		= :due_date,
			 		is_mandatory	= :is_mandatory,
			 		transaction_id	= :transaction_id 
			  WHERE id				= :id"
		);

		return $statement->execute( array(
			":id"				=> $this->id,
			":name"				=> $this->name,
			":details"			=> $this->details,
			":amount"			=> $this->amount,
			":due_date"			=> $this->getDueAt()->format(Config::SQL_DATETIME),
			":is_mandatory"		=> $this->isMandatory,
			":transaction_id"	=> $this->transactionId
		) );
	}


	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Invoice Status & Settlement (payment processing)

	/**
	 * @return bool indicates if the invoice has been paid or is in the process of being paid (i.e. settling)
	 */
	public function isPaidOrSettling()
	{
		if ($this->transaction) {
			return is_a($this->transaction, "Braintree_Transaction") &&
				self::isStatusAlive( $this->transaction->status );
		}	else {
			// assume paid if we have a transaction ID
			return strlen($this->transactionId)>0;
		}
	}
	
	/**
	 * return bool if we have no transaction on record for the current invoice
	 */
	public function hasNoPaymentAttempt()
	{
		return !$this->transactionId || !strlen($this->transactionId);
	}

	/**
	 * returns the number of days left before this invoice must be paid
	 */
	public function getDaysLeftToPay()
	{
		$nowDate = new DateTime();
		$nowDate->setTimezone($this->getDueAt()->getTimezone());
		$dateDif = $nowDate->diff( $this->getDueAt() );
		return (int)$dateDif->format("%r%a");
	}

	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Invoice Operations

	/**
	 * @param $name String
	 * @param $details String
	 * @param $amount double
	 * @param $dueAt DateTime
	 * @param $isMandatory boolean
	 * @return boolean indicating success
	 */
	public function edit(
		$name,
		$details,
		$amount,
		$dueAt,
		$isMandatory
	)	{
		// check if the invoice hasn't been paid already
		if ( $this->isPaidOrSettling() ) {
			Config::apiError( "This invoice has already been paid and cannot be edited." );
			return false;
		}

		// validate the input
		if ( !self::isAmountValid($amount) ) {
			Config::apiError( "Can't set the cost to an invalid value." );
			return false;
		}
		if ( !self::isDueDateValid($dueAt) ) {
			Config::apiError( "Can't set the due date to be in the past." );
			return false;
		}

		// update the properties
		$this->name			= $name;
		$this->details		= $details;
		$this->amount		= floatval($amount);
		$this->dueAt		= $dueAt;
		$this->isMandatory	= $isMandatory;

		if ( $this->commit() ) {
			return true;
		}	else {
			Config::apiError( "A database error occurred while editing this invoice. Please try again later." );
			return false;
		}
	}

	/**
	 * @TODO
	 * @return bool
	 */
	public function delete()
	{
		Config::apiError("This action is not available.");
		return false;
	}

	/**
	 * Check if a transaction exists on Braintree that was made for this invoice, without it getting recorded to the database
	 * @param $account LcAccount
	 */
	private function verifyHasTransaction( $account )
	{
		/**
		 * @TODO load all the transactions that have been recorded on this account,
		 * @TODO  look if any transaction exists that has its orderId = $this->getOrderId, and a successful status
		 */
		return false;
	}

	/**
	 * @param $account LcAccount
	 * @param $paymentMethodToken string
	 * @param $paymentMethodNonce string
	 * @return bool
	 */
	public function pay( $account, $paymentMethodToken=null, $paymentMethodNonce=NULL )
	{
		// make sure the invoice is not already paid
		if ( $this->isPaidOrSettling() ) {
			Config::apiError("This invoice has already been paid.");
			return false;
		}

		// check if a transaction exists in Braintree that has this invoice's orderId and restore it
		if ( $this->verifyHasTransaction( $account ) ) {
			return false;
		}

		////////////////////////////////////////////////////////////////////////////////////////////////////////////////
		// Process the sale

		// options must contain the customerId and amount; orderId is just the unique invoice ID in our database
		// @TODO: do we need to specify the address?
		$saleParams = array(
			'customerId'	=> $account->getCustomerId(),
			'amount'		=> $this->getAmount(),
			'orderId'		=> $this->getOrderId(),
			'options'		=> array(
				'submitForSettlement' => true
			)
		);

		// specify the braintree merchant ID if this is not sandbox
		if ( !IS_BRAINTREE_SANDBOX ) {
			// @CURRENCY @27AUG2019 - adding merchant account ID + multiple currencies support
			$saleParams["merchantAccountId"] = $account->getMerchantAccount()->getId();
		}

		// check which payment parameter is specified
		if ( $paymentMethodNonce ) {
			$saleParams["paymentMethodNonce"] = $paymentMethodNonce;
		}	else
		if ( $paymentMethodToken ) {
			$saleParams["paymentMethodToken"] = $paymentMethodToken;
		}	else {
			Config::apiError("No payment method has been specified.");
			return false;
		}

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

			see list of error codes here:
			https://developers.braintreepayments.com/javascript+php/reference/general/processor-responses/authorization-responses
		*/
		// @TESTING: uncomment the following line and set the desired value to test the different possible error messages
		// $saleParams["amount"] = 2000;

		// process the sale
		$result = Braintree_Transaction::sale( $saleParams );

		// verify the transaction result
		if ( $result->success ) {
			// transaction was successfully created
			$this->transaction = $result->transaction;
			// store the transaction ID in the database
			$this->transactionId = $this->transaction->id;

			// attempt to commit the new transaction ID to the database multiple times
			for ( $retryCount=5; $retryCount>0; --$retryCount) {
				if ( $this->commit() )
					// new transaction ID has been recorded
					break;

				// wait 100ms before trying again
				usleep( 100000 );
			}

			// @TODO: we shouldn't fail transactions that are paid without us being able to record them to the DB;
			if ( $retryCount <= 0 ) {
				// @TODO: database error, save message to an error log
				return false;
			}

			if ( self::isStatusAlive( $this->transaction->status ) ) {
				// the invoice has been successfully paid, send a receipt and invoice by email
				LcMailer::sendReceipt(
					$this->transaction,
					$account,
					$this
				);

				return true;
			}	else
			if ( !isset($result->errors) || !sizeof($result->errors->deepAll() ) ) {
				Config::apiError("An unexpected error occurred processing the payment. Please refresh the page to check if the payment went through.");
				return false;
			}
			// otherwise the transaction error will be parsed
		}

		// process the transaction error
		LcSubscription::parseTransactionErrors(
			$result->errors->deepAll(),
			$result->transaction
		);

		return false;
	}

	/**
	 * @TODO
	 * @return bool
	 */
	public function refund()
	{
		Config::apiError("This action is not available.");
		return false;
	}

	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Getters

	/**
	 * @return string
	 */
	static public function typeName() { return "inv_manual"; }

	/**
	 * @return bool
	 */
	public function isValid() { return $this->valid; }

	/**
	 * @return String
	 */
	public function getId() { return $this->id; }

	/**
	 * @return int
	 */
	public function getAccountId() { return $this->accountId; }

	/**
	 * @return mixed
	 */
	public function getDisplayId() { return $this->id + self::DISPLAY_OFFSET; }

	/**
	 * @return string
	 */
	public function getDisplayName() { return "INV-".$this->getDisplayId(); }

	/**
	 * @return String
	 * @TODO: do we need anything more detailed here?
	 */
	public function getOrderId() { return $this->id."_".$this->getCreatedAt()->getTimestamp(); }

	/**
	 * @return String
	 */
	public function getName() { return $this->name; }

	/**
	 * @return String
	 */
	public function getDetails() { return $this->details; }

	/**
	 * @return float
	 */
	public function getAmount() { return $this->amount; }

	/**
	 * @return float
	 */
	public function getNetAmount() { return LcAccount::netCost($this->amount); }

	/**
	 * @return float
	 */
	public function getTaxAmount() { return LcAccount::taxFromGross($this->amount); }

	/**
	 * @return float
	 */
	public function getAmountPaid()
	{
		if ( $this->getTransaction() ) {
			return doubleval( $this->getTransaction()->amount );
		}

		return 0;
	}

	/**
	 * @return float
	 */
	public function getAmountOutstanding()
	{
		return intval( ( $this->getAmount() - $this->getAmountPaid() ) * 100 ) / 100.0;
	}


	/**
	 * @return DateTime
	 */
	public function getCreatedAt() { return $this->createdAt; }

	/**
	 * @return string
	 */
	public function getCreatedAtString() { return $this->getCreatedAt()->format(Config::DATE_VERBOSE); }

	/**
	 * @return DateTime
	 */
	public function getDueAt() { return $this->dueAt; }

	/**
	 * @return Boolean
	 */
	public function getIsMandatory() { return $this->isMandatory; }

	/**
	 * @return Braintree_Transaction
	 */
	public function getTransaction() { return $this->transaction; }

	/**
	 * @return array
	 */
	public function getProducts() { return $this->products; }

	/**
	 * @return string
	 */
	public function getOverview() {
		// count the product types
		$productTypes       = array();

		/**
		 * @var $product LcInvoiceProduct
		 */
		foreach ( $this->getProducts() as $product ) {
			if ( isset($productTypes[$product->getType()]) )
				++$productTypes[$product->getType()];
			else
				$productTypes[$product->getType()] = 1;
		}

		$typesDisplay       = array();

		foreach ($productTypes as $type => $count) {
			switch ( $type ) {
				case LcInvoiceProduct::PRODUCT_PLAN_ADDITION:
					$typesDisplay[]= $count." new plan".($count>1?"s":"");
					break;

				case LcInvoiceProduct::PRODUCT_PLAN_UPDATE:
					$typesDisplay[]= $count." plan update".($count>1?"s":"");
					break;

				case LcInvoiceProduct::PRODUCT_SUBSCRIPTION:
					// $typesDisplay[]= $count." monthly maintenance charge".($count>1?"s":"");
					$typesDisplay[]= "Monthly Maintenance Fee";
					break;

				case LcInvoiceProduct::PRODUCT_BULK_ADDITION:
					$typesDisplay[]= "New Floorplans";
					break;

                case LcInvoiceProduct::PRODUCT_LOTMIX_LEAD:
                    $typesDisplay[]= "Lotmix Leads";
			}
		}

		return join(", ", $typesDisplay);
	}

	/**
	 * returns a certain product in this
	 * @param $tid int the ID of the ticket
	 * @return LcInvoiceProduct
	 */
	public function getProduct( $tid )
	{
		/**
		 * @var $product LcInvoiceProduct
		 */
		foreach ($this->products as $product)
		{
			if ( ( $product->getProductId() == $tid ) && (
					$product->getType() == LcInvoiceProduct::PRODUCT_PLAN_ADDITION ||
					$product->getType() == LcInvoiceProduct::PRODUCT_PLAN_UPDATE
				) ) {
				return $product;
			}
		}
		
		return null;
	}
	
	/**
	 * @return string file name that uniquely identifies this invoice
	 */
	public function getPDFFileName()
	{
		// because the pdf file name is used in a system exec() call, we strictly validate all the name pieces
		// so that even if our DB is breached, the values can't get into exec()

		/*
         -> the transactionId is generated by Braintree; from their site, here is what we know about its format:
         https://developers.braintreepayments.com/reference/general/best-practices/php#token-and-id-lengths-and-formats

         "In order to avoid interruptions in processing, it's best to make minimal assumptions about what our
         gateway-generated tokens and identifiers will look like in the future. The length and format of these
         identifiers – including payment method tokens and transaction IDs – can change at any time, with or without
         advance notice. However, it is safe to assume that they will remain 1 to 36 alphanumeric characters."
         */
		if ( $this->transactionId && ctype_alnum($this->transactionId) )
			return self::typeName()."-".
			intval($this->accountId)."-".
			intval($this->id)."-".
			$this->transactionId.".pdf";
		
		return null;
	}

	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Static functions

	/**
	 * @param $accountId int
	 * @param $name String
	 * @param $details String
	 * @param $amount double
	 * @param $dueAt DateTime
	 * @param $isMandatory boolean
	 * @return LcInvoice the created invoice, or NULL on failure
	 */
	public static function create(
		$accountId,
		$name,
		$details,
		$amount,
		$dueAt,
		$isMandatory
	)	{
		$db = pdoConnect();

		// validate the input
		if ( !self::isAmountValid($amount) ) {
			Config::apiError( "Can't set the cost to an invalid value." );
			return false;
		}
		if ( !self::isDueDateValid($dueAt) ) {
			Config::apiError( "Can't set the due date to be in the past." );
			return false;
		}

		$statement = $db->prepare(
			"INSERT INTO ".self::tableName."
			 		( account_id,  name,  details,  amount,  due_date,  is_mandatory) 
             VALUES (:account_id, :name, :details, :amount, :due_date, :is_mandatory)"
		);

		if( $statement->execute( array(
				":account_id"	=> intval($accountId),
				":name"			=> $name,
				":details"		=> $details,
				":amount"		=> floatval($amount),
				":due_date"		=> $dueAt->format(Config::SQL_DATETIME),
				":is_mandatory"	=> intval($isMandatory)
			) ) ) {
			// return the created invoice
			// no caching needed in this case
			return new LcInvoice( $db->lastInsertId() );
		}	else {
			Config::apiError( "An error occurred while creating the invoice. Please try again later." );
			return NULL;
		}
	}

	/**
	 * @param $accountId int the ID of the account for which we're loading the invoices
	 * @return array
	 */
	public static function allOf($accountId, $customerId=null, $loadFromBraintree=true)
	{
		$db = pdoConnect();
		$accountId = intval($accountId);
		$statement = $db->prepare(
			"SELECT id
			 FROM ".self::tableName."
			 WHERE account_id=:account_id
			 ORDER BY created_date DESC"
		);

		if ($customerId && $loadFromBraintree) {
			self::cacheTransactionsOf($customerId);
		}

		$invoices = array();
		if	( $statement->execute( array(":account_id" => $accountId ) ) ) {
			while ( $row = $statement->fetch(PDO::FETCH_ASSOC) ) {
				$invoices[] = self::get($row["id"], $loadFromBraintree);
			}
		}

		return $invoices;
	}

	/**
	 * return all the invoices
	 * @param $productId int
	 */
	public static function allWithProduct( $productId )
	{
		$db = pdoConnect();

		$statement = $db->prepare(
			"SELECT billing_invoice.id
			 FROM ".self::tableName."
			 INNER JOIN billing_invoice_product
			 	ON billing_invoice.id = billing_invoice_product.vid
			 WHERE billing_invoice_product.pid=:product_id
			 ORDER BY created_date DESC"
		);

		$invoices = array();
		if	( $statement->execute( array(":product_id" => intval($productId) ) ) ) {
			while ( $row = $statement->fetch(PDO::FETCH_ASSOC) ) {
				// $invoices[] = new LcInvoice( $row["id"] );
				$invoices[] = self::get( $row["id"] );
			}
		}

		return $invoices;
	}

	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////1
	// Braintree Caching

	static $transactions;

	/**
	 * Create a cache for all the transactions on this account and use it when fetching informations about an invoice
	 */
	public static function cacheTransactionsOf( $customerId ) {
		if ( !isset(self::$transactions) ) {
			self::$transactions = array();
		}
		// this instantiates the search but only fetches a list of transaction IDs
		$collection = Braintree_Transaction::search([
			Braintree_TransactionSearch::customerId()->is($customerId)
		]);
		// this actual fetches the transaction details from the server
		foreach ($collection as $transaction) {
			self::$transactions[$transaction->id] = $transaction;
		}
	}
	private static function fetchBraintreeTransaction( $transactionId )
	{
		// search the transaction cache first
		if ( isset(self::$transactions) ) {
			foreach (self::$transactions as $transaction) {
				if ( $transaction->id==$transactionId ) {
					return $transaction;
				}
			}
		}

		// find the requested transaction
		return Braintree_Transaction::find( $transactionId );
	}

	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// Object Caching

	static $map;

	/**
	 * Return (and cache) an invoice
	 * @param $invoiceId
	 */
	public static function get( $invoiceId, $loadFromBraintree=true )
	{
		$invoiceId = intval($invoiceId);

		if ( !self::$map ) {
			self::$map = array();
		}

		if ( !isset(self::$map[ $invoiceId ]) ) {
			$invoice = new LcInvoice( $invoiceId, $loadFromBraintree );
			if ( $invoice->valid ) {
				self::$map[$invoiceId] = $invoice;
			}	else {
				Config::apiError("Trying to load nonexistent invoice {$invoiceId}");
				return null;
			}
		}

		return self::$map[$invoiceId];
	}

	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// UTIL functions

	private static function isAmountValid( $amount )
	{
		return floatval( $amount ) > 0;
	}

	/**
	 * @param $dueDate DateTime
	 * @return bool
	 */
	private static function isDueDateValid( $dueDate )
	{
		return $dueDate && $dueDate > new DateTime("now", Config::TIMEZONE);
	}

	/**
	 * @param $status String a Braintree_Transaction status
	 * @return bool
	 */
	public static function isStatusAlive( $status )
	{
		return $status == Braintree_Transaction::SUBMITTED_FOR_SETTLEMENT ||
			   $status == Braintree_Transaction::SETTLING ||
			   $status == Braintree_Transaction::SETTLEMENT_PENDING ||
			   $status == Braintree_Transaction::SETTLED;
	}
}

?>