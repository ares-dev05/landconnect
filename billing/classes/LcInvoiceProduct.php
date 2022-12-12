<?php

/**
 * Created by Mihai Dersidan
 */
class LcInvoiceProduct
{
    const tableName = "billing_invoice_product";

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Class Definition

    // Product types
    const PRODUCT_PLAN_ADDITION     = 1;
    const PRODUCT_PLAN_UPDATE       = 2;
    const PRODUCT_SUBSCRIPTION      = 3;
    const PRODUCT_BULK_ADDITION     = 4;
    const PRODUCT_LOTMIX_LEAD       = 5;
    // @TODO: add more products if needed

    /**
     * 		Invoice Product Settings
     * @var $id 					int IMMUTABLE unique ID to identify a product added to an invoice
     * @var $invoiceId				int IMMUTABLE the ID of the invoice
     * @var $productType			int
     * @var $productId              int IMMUTABLE the ID of the product in its database
     * @var $cost                   double price of the product in the invoice
     * @var $message			    string optional info message attached to this product
     * @var $productInfo            LcPLanInfo details for the current product
     */
    private $id;
    private $invoiceId;
    private $productId;
    private $productType;
    public $quantity;
    public $cost;
    public $message;

    // product info
    public $productInfo;

    /**
     * 		Database object
     * @var $valid Boolean
     * @var $dbObj stdClass
     */
    private $valid;
    private $dbObj;


    function __construct( $id, $obj=null )
    {
        $this->id	= $id;
        if ( $id >= 0 ) {
            $this->fetch($obj);
        }
    }

    /**
     * @param $invoiceId int
     * @param $productType
     * @param $productId
     * @param $cost
     * @param $message
     * @param $quantity
     * @return LcInvoiceProduct
     */
    public static function manual( $invoiceId, $productType, $productId, $cost, $message, $quantity=1 ) {
        $product               = new self(-1);

        $product->valid        = true;
        $product->invoiceId    = $invoiceId;
        $product->productType  = $productType;
        $product->productId	   = $productId;
        $product->cost 		   = $cost;
        $product->message	   = $message;
        $product->quantity     = $quantity;

        return $product;
    }

    /**
     * @param $subscriptionInvoice LcSubscriptionInvoice
     * @return LcInvoiceProduct
     */
    public static function forSubscription( $subscriptionInvoice ) {
        return self::manual(
            0,
            self::PRODUCT_SUBSCRIPTION,
            0,
            $subscriptionInvoice->getAmount(),
            $subscriptionInvoice->getDetails(),
            1
        );
    }

    private function fetch( $obj=null )
    {
        if ( $obj ) {
            $this->dbObj    = $obj;
            $this->valid    = true;
        }   else {
            $db = pdoConnect();

            $statement = $db->prepare(
                "SELECT *
                 FROM " . self::tableName . "
                 WHERE id=:id"
            );

            if	( $statement->execute( array(":id" => $this->id ) ) &&
                ( $this->dbObj=$statement->fetch(PDO::FETCH_ASSOC) ) ) {
                $this->valid = true;
            }
        }

        if ( $this->dbObj ) {
            $this->invoiceId    = $this->dbObj["vid"];
            $this->productType  = $this->dbObj["ptype"];
            $this->productId	= $this->dbObj["pid"];
            $this->cost 		= $this->dbObj["cost"];
            $this->message	    = $this->dbObj["message"];
            $this->quantity     = $this->dbObj["quantity"];

            switch ( $this->productType ) {
                case self::PRODUCT_PLAN_ADDITION:
                case self::PRODUCT_PLAN_UPDATE:
                    $this->productInfo = LcPLanInfo::find( $this->productId );
                    break;

                // @TODO: support additional product types as needed;
            }
        }	else {
            $this->valid	= false;
        }
    }

    /**
     * commit the object to the database; can only edit fields that are not immutable
     * @return bool
     */
    private function commit()
    {
        if ( !$this->valid ) return false;

        $db = pdoConnect();

        $statement = $db->prepare(
            "UPDATE ".self::tableName."
			 	SET ptype			= :ptype
			 		cost			= :cost,
			 		message			= :message,
			 		quantity        = :quantity,
			  WHERE id				= :id"
        );

        return $statement->execute( array(
            ":id"				=> $this->id,
            ":ptype"			=> $this->productType,
            ":cost"			    => $this->cost,
            ":message"			=> $this->message,
            ":quantity"         => $this->quantity
        ) );
    }

    /**
     * @param $newType int @TODO: only allow to change with a related type
     * @param $newCost double
     * @param $newMessage string
     */
    public function edit($newType, $newCost, $newMessage,$quantity=1)
    {
        $this->productType  = $newType;
        $this->cost         = doubleval( $newCost );
        $this->message      = $newMessage;
        $this->quantity     = $quantity;

        $this->commit();
    }

    /**
     * @return double
     */
    public function getCostNet() {
        return LcAccount::netCost( $this->cost );
    }

    /**
     * @return double
     */
    public function getOverallCostNet() {
        return LcAccount::netCost( $this->cost * $this->quantity );
    }

    public function getId()         { return $this->id; }
    public function getType()       { return $this->productType; }
    public function getProductId()  { return $this->productId; }
    public function hasMessage()    { return $this->message && strlen($this->message); }
    public function getQuantity()   { return $this->quantity; }

    public function getDescription()
    {
        switch ( $this->getType() ) {
            // we also have $this->productInfo->targetRange
            case LcInvoiceProduct::PRODUCT_PLAN_ADDITION:
                return "New Plan ".$this->getSafePlanName();

            case LcInvoiceProduct::PRODUCT_PLAN_UPDATE:
                return "Update to ".$this->getSafePlanName();

            case LcInvoiceProduct::PRODUCT_SUBSCRIPTION:
                return $this->message;

            case LcInvoiceProduct::PRODUCT_BULK_ADDITION:
                return $this->message;

            case LcInvoiceProduct::PRODUCT_LOTMIX_LEAD:
                return "Lotmix Lead(s)";
        }
    }

    public function getSafePlanName() {
        if ($this->productInfo && $this->productInfo->getName() !== null && strlen($this->productInfo->getName()) > 0 && $this->productInfo->getName()!="N/A") {
            return $this->productInfo->getName();
        }

        return $this->message;
    }

    public function getSafeTargetName() {
        if ($this->productInfo && $this->productInfo->targetRange !== null && strlen($this->productInfo->targetRange) > 0 && $this->productInfo->targetRange !="N/A") {
            return $this->productInfo->targetRange;
        }

        return "";
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Operations

    public static function create( $invoiceId, $type, $productId, $cost, $message )
    {
        $db = pdoConnect();

        $statement = $db->prepare(
            "INSERT INTO ".self::tableName."
			 		( vid,  ptype,  pid,  cost,  message ) 
             VALUES (:vid, :ptype, :pid, :cost, :message )"
        );

        if ( $statement->execute( array(
            ":vid"		    => $invoiceId,
            ":ptype"		=> $type,
            ":pid"			=> $productId,
            ":cost"			=> $cost,
            ":message"		=> $message
        ) ) ) {
            $id = $db->lastInsertId();
            return new LcInvoiceProduct( $id );
        }

        return null;
    }

    public static function all( $invoiceId )
    {
        $invoiceId  = intval( $invoiceId );
        $results    = array();

        $db = pdoConnect();
        $statement = $db->prepare(
            "SELECT *
			 FROM ".self::tableName."
			 WHERE vid=:invoice"
        );

        if	( $statement->execute( array(":invoice" => $invoiceId ) ) ) {
            while ( $obj = $statement->fetch(PDO::FETCH_ASSOC) ) {
                $results[]= new LcInvoiceProduct( $obj["id"], $obj );
            }
        }

        return $results;
    }
}