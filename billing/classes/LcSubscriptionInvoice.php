<?php

/**
 * Class LcSubscriptionInvoice
 */

class LcSubscriptionInvoice implements IInvoice
{
    const tableName = "billing_subscription_invoice";
    const DISPLAY_OFFSET = 3240;

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Class Definition

    /**
     * 		Invoice settings
     * @var $id 					int this invoice's unique ID
     * @var $accountId				int the ID of the account this invoice is assigned to
     * @var $amount					double the amount due for this invoice
     * @var $createdAt DateTime the Date/Time the object was created
     * @var $transactionId			string the transaction ID for this invoice
     * @var $transaction 			Braintree_Transaction the Braintree transaction object
     */
    private $id;
    private $accountId;
    private $amount;
    private $createdAt;
    private $transactionId;
    private $transaction;
    private $transactionDate;

    /**
     * 		Database object
     * @var $valid Boolean
     * @var $dbObj stdClass
     */
    private $valid;
    private $dbObj;


    function __construct( $transactionId, $transaction=null, $dbObj=null )
    {
        $this->transactionId = $transactionId;
        $this->transaction   = $transaction;
        $this->dbObj         = $dbObj;

        if ( $this->transactionId ) {
            $this->fetch();
        }
    }
    
    public static function byDBId( $id )
    {
        $db = pdoConnect();
        $statement = $db->prepare(
            "SELECT *
			 FROM ".self::tableName."
			 WHERE id=:id"
        );

        if ( $statement->execute( array(":id" => $id ) ) && ( $dbObj=$statement->fetch(PDO::FETCH_ASSOC) ) ) {
            return new self(
                $dbObj["transaction_id"],
                null,
                $dbObj
            );
        }

        return null;
    }

    function __destruct()
    {
    }

    private function fetch()
    {
        // if the database object wasn't pre-supplied, fetch it
        if ( !$this->dbObj ) {
            $db = pdoConnect();
            $statement = $db->prepare(
                "SELECT *
                 FROM ".self::tableName."
                 WHERE transaction_id=:transaction_id"
            );

            if  ( $statement->execute( array(":transaction_id" => $this->transactionId ) ) ) {
                $this->dbObj = $statement->fetch(PDO::FETCH_ASSOC);
            }
        }

        if ( $this->dbObj ) {
            $this->valid		= true;
            $this->id           = $this->dbObj["id"];
            $this->accountId	= $this->dbObj["account_id"];
            $this->amount       = $this->dbObj["amount"];
            $this->createdAt	= DateTime::createFromFormat( Config::SQL_DATETIME, $this->dbObj["created_date"] );
            $this->transactionId= $this->dbObj["transaction_id"];

            // @TODO: optimize this by loading all the transactions for a certain customer instead of fetching each one-at-a-time
            if ( $this->transactionId && strlen($this->transactionId) && !$this->transaction ) {
                $this->transaction	= self::fetchBraintreeTransaction( $this->transactionId );
            }

            if ( $this->transaction ) {
                $this->transactionDate = $this->transaction->createdAt;
            }
        }	else {
            $this->valid	= false;
        }
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Getters

    /**
     * @return string
     */
    static public function typeName() { return "inv_sub"; }

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
    public function getDisplayName() { return "INV-S-".$this->getDisplayId(); }

    /**
     * @return String
     */
    public function getName() { return "Footprints - Monthly Maintenance"; }

    /**
     * @return string
     */
    public function getDetails()
    {
        if ( $this->getTransactionDate() ) {
            return "Monthly Maintenance - ".$this->getTransactionDate()->format("F");
        }
        return "Monthly Maintenance";
    }

    /**
     * @return double
     */
    public function getAmount() { return $this->amount; }

    /**
     * @return double
     */
    public function getNetAmount() { return LcAccount::netCost($this->amount); }

    /**
     * @return double
     */
    public function getTaxAmount() { return LcAccount::taxFromGross($this->amount); }

    /**
     * @return double
     */
    public function getAmountPaid()
    {
        if ( $this->getTransaction() ) {
            return doubleval( $this->getTransaction()->amount );
        }

        return 0;
    }

    /**
     * @return double
     */
    public function getAmountOutstanding()
    {
        return $this->getAmount() - $this->getAmountPaid();
    }

    /**
     * @return DateTime
     */
    public function getCreatedAt() { return $this->transactionDate ? $this->transactionDate : $this->createdAt; }

    /**
     * @return string
     */
    public function getCreatedAtString() { return $this->getCreatedAt()->format(Config::DATE_VERBOSE); }

    /**
     * @return Braintree_Transaction
     */
    public function getTransaction() { return $this->transaction; }

    /**
     * @return DateTime
     */
    public function getTransactionDate() { return $this->transactionDate; }

    /**
     * @return array
     */
    public function getProducts() {
        // create a subscription product to display in the invoice
        return array( LcInvoiceProduct::forSubscription( $this ) );
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

    /**
     * @return bool
     @TODO @UNUSED - can solve this with a direct call to generator::generate
    public function generatePDF( $overwriteIfExists=false )
    {
        return LcInvoicePDFGenerator::generate( $this, $overwriteIfExists );
    }
     */

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Static functions

    /**
     * @param $account LcAccount
     * @param $transactionId string
     * @return LcSubscriptionInvoice
     */
    public static function loadFor( $account, $transactionId )
    {
        $transaction = self::fetchBraintreeTransaction( $transactionId );
        if ( $transaction ) {
            return self::loadForTransaction( $account, $transaction );
        }

        return null;
    }

    /**
     * @param $account LcAccount
     * @param $transaction Braintree_Transaction
     * @return LcSubscriptionInvoice
     */
    public static function loadForTransaction( $account, $transaction )
    {
        if ( $transaction && LcInvoice::isStatusAlive($transaction->status) ) {
            $invoice = new LcSubscriptionInvoice($transaction->id, $transaction);
            if ( $invoice->valid ) {
                return $invoice;
            }   else {
                // create a new invoice for this subscription transaction
                $db = pdoConnect();

                $statement = $db->prepare(
                    "INSERT INTO ".self::tableName."
                            ( account_id,  transaction_id,  amount) 
                     VALUES (:account_id, :transaction_id, :amount)"
                );

                if( $statement->execute( array(
                    ":account_id"       => $account->getId(),
                    ":transaction_id"   => $transaction->id,
                    ":amount"		    => $transaction->amount
                ) ) ) {
                    // return the created invoice
                    // no caching needed in this case
                    return new LcSubscriptionInvoice( $transaction->id, $transaction );
                }	else {
                    Config::apiError( "An error occurred while creating the invoice. Please try again later." );
                    return NULL;
                }
            }
        }

        return NULL;
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Braintree Caching

    /**
     * @TODO: create a cache for all the transactions on this account and use it when fetching informations about an invoice
    $collection = Braintree_Transaction::search([
    Braintree_TransactionSearch::customerId()->is('the_customer_id'),
    ]);
     */
    private static function fetchBraintreeTransaction( $transactionId )
    {
        // $this->transaction = Braintree_Transaction::find($this->transactionId);
        return Braintree_Transaction::find( $transactionId );
    }
}

?>