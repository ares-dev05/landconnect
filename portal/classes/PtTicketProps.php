<?php

class PtTicketProps {

    // property types
    const DATE_TIME = "datetime";
    const NUMERIC   = "numeric";
    const STRING    = "string";
    const JSON      = "json";

    public static function tableName(){ return DB_PREFIX."ticket_props"; }

    private $tid;
    // name -> property association array
    private $props;

    private static $fetchStmt;

    public function __construct( $tid )
    {
        $this->tid = $tid;
        $this->fetch();
    }

    private function fetch()
    {
        $this->props = array();

        if ( !self::$fetchStmt ) {
            // get all the properties
            self::$fetchStmt = pdoConnect()->prepare("
                SELECT *
                FROM " . self::tableName() . "
                WHERE tid=:tid
            ");
        }

        if ( self::$fetchStmt->execute(array(":tid"=>$this->tid)) ) {
            while ( ($dbObj=self::$fetchStmt->fetch(PDO::FETCH_OBJ)) != NULL ) {
                $this->props[ $dbObj->name ] = array(
                    "value" => $dbObj->value,
                    "type"  => $dbObj->type
                );
            }
        }
    }

    public function getProperty( $name ) {
        if ( isset( $this->props[$name] ) ) {
            $prop = $this->props[$name];

            switch ( $prop["type"] ) {
                case self::DATE_TIME:
                    return DateTime::createFromFormat(
                        DateTime::ATOM,
                        $prop["value"]
                    );

                case self::NUMERIC:
                    return floatval($prop["value"]);

                case self::STRING:
                    return $prop["value"];

                case self::JSON:
                    return json_decode($prop["value"], true);
            }
        }   else {
            // property is not set
            return NULL;
        }
    }

    public function setProperty( $name, $value, $type ) {
        // make sure the property is a valid name
        if ( !preg_match( "/^[A-Za-z0-9_]+$/", $name ) )
            return false;

        $db_value   = null;

        switch ( $type ) {
            case self::DATE_TIME:
                $db_value   = $value->format( DateTime::ATOM );
                break;

            case self::NUMERIC:
                if ( !is_numeric($value) )
                    return false;
                $db_value   = floatval( $value );
                break;

            case self::JSON:
                $db_value   = json_encode( $value );
                break;

            case self::STRING:
                $db_value   = $value;
                break;

            default:
                return false;
        }

        // process the query
        if ( isset($this->props[$name]) ) {
            // update
            $success    = pdoConnect()->prepare(
                "UPDATE ".self::tableName()."
                 SET
                    value = :value,
                    type  = :type
                 WHERE
                    tid   = :tid AND
                    name  = :name"
            )->execute(array(
                ":value"    => $db_value,
                ":type"     => $type,
                ":tid"      => $this->tid,
                ":name"     => $name
            ));
        }   else {
            $success    = pdoConnect()->prepare(
                "INSERT INTO ".self::tableName()."
                 SET
                    tid   = :tid,
                    name  = :name,
                    value = :value,
                    type  = :type"
            )->execute(array(
                ":value"    => $db_value,
                ":type"     => $type,
                ":tid"      => $this->tid,
                ":name"     => $name
            ));
        }

        if ( $success ) {
            $this->props[$name] = array(
                "value" => $db_value,
                "type"  => $type
            );
        }

        return $success;
    }
}

?>