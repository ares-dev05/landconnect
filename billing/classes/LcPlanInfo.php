<?php

/**
 * Class LcPLanInfo
 *
 * Represents the product information field of an invoice product with type=product
 * @TODO: create an IProductInfo interface that LcPlanInfo will implement
 */
class LcPLanInfo
{
    const tableName = "billing_invoice_product";

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Class Definition

    // plan property fields
    const PROP_NAME     = "floorName";
    const PROP_RANGE    = "targetRange";
    const PROP_RELEASE  = "releaseDate";

    /**
     * 		Invoice Product Settings
     * @var $tid				int ticket ID for the current plan
     * @var $floorName			string the name of the floorplan
     * @var $targetRange		string range this floorplan will go into
     */
    public $tid;
    public $floorName;
    public $targetRange;

    public $props;

    function __construct( $tid )
    {
        $this->tid  = intval( $tid );
        // available ticket properties
        $this->props = array(
            self::PROP_NAME     => "N/A",
            self::PROP_RANGE    => "N/A",
            self::PROP_RELEASE  => "N/A"
        );

        $db = pdoConnect();

        $statement = $db->prepare(
            "SELECT *
			 FROM pt_ticket_props
			 WHERE tid=:tid"
        );

        if	( $statement->execute( array(":tid" => $this->tid ) ) ) {
            while ( $obj = $statement->fetch(PDO::FETCH_ASSOC) ) {
                $this->props[ $obj["name"] ] = $obj["value"];
            }
        }

        // store properties shortcuts
        $this->floorName    = $this->props[ self::PROP_NAME ];
        $this->targetRange  = $this->props[ self::PROP_RANGE ];
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // @TODO IProductInfo implementation

    public function getName() {
        return $this->floorName;
    }

    public function getUrl() {
        return "/portal/view_ticket.php?id=".$this->tid;
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Caching

    static $map;

    public static function find( $tid )
    {
        if ( !self::$map ) {
            self::$map = array();
        }

        if ( !isset(self::$map[$tid]) ) {
            self::$map[$tid] = new LcPLanInfo( $tid );
        }

        return self::$map[$tid];
    }
}

?>