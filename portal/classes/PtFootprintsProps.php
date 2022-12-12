<?php

/**
 * Class PtFootprintsProps
 *   wrapper functions for footprints-specific plan portal vars
 */
class PtFootprintsProps extends PtTicketProps {

    const RELEASE_DATE = "releaseDate";
    const TARGET_RANGE = "targetRange";
    const FLOOR_NAME   = "floorName";

    /**
     * @return String
     */
    public function getFloorName() {
        return $this->getProperty( self::FLOOR_NAME );
    }
    /**
     * @param String $name
     */
    public function setFloorName( $name ) {
        $this->setProperty( self::FLOOR_NAME, $name, PtTicketProps::STRING );
    }

    /**
     * @return DateTime
     */
    public function getReleaseDate() {
        return $this->getProperty( self::RELEASE_DATE );
    }
    /**
     * @param DateTime $date
     */
    public function setReleaseDate( $date ) {
        $this->setProperty( self::RELEASE_DATE, $date, PtTicketProps::DATE_TIME );
    }

    /**
     * @return String
     */
    public function getTargetRange() {
        return $this->getProperty( self::TARGET_RANGE );
    }
    /**
     * @param String $rangeName
     */
    public function setTargetRange( $rangeName ) {
        $this->setProperty( self::TARGET_RANGE, $rangeName, PtTicketProps::STRING );
    }

    public static function getAllRanges( $cid, $sid ) {
        $cid    = intval( $cid );
        $sid    = intval( $sid );

        $sql = "SELECT value FROM ".self::tableName()."
                 WHERE
                    name  ='".self::TARGET_RANGE."' AND
                    tid IN (
                        SELECT id FROM " . PtTicket::tableName() . "
                        WHERE cid=$cid AND state_id=$sid
                    )";

        $ranges = array();
        foreach (pdoConnect()->query($sql, PDO::FETCH_OBJ) as $obj) {
            array_push( $ranges, $obj->value );
        }

        return $ranges;
    }
}
?>