<?php

class PtStateInfo {

    public static function tableName(){ return "house_states"; }
    
    // public class variables
    public $id;
    public $name;
    public $abbrev;
    
    // private class variables
    private $dbObj;
    public $valid;

    public function __construct( $id ) {
        $this->id = intval($id);
        // fetch the object
        $this->fetch();
    }

    private function fetch( ) {
        $stmt = pdoConnect()->query("SELECT * FROM ".self::tableName()." WHERE id={$this->id}");
        if ( $stmt && ($this->dbObj=$stmt->fetch(PDO::FETCH_OBJ)) != NULL ) {
            $this->valid    = true;
            foreach ( $this->dbObj as $prop => $value ) {
                if ( $prop == "id" ) continue;
                $this->$prop = $value;
            }
        }
        else {
            $this->valid = false;
        }
    }

    //////////////////////////////////////////////////////////////////////////////////
    // static functionality

    // keep a cache of all companies
    public static $cache;

    /**
     * @param int $uid
     * @return PtStateInfo
     */
    public static function get( $uid )
    {
        if ( !isset(self::$cache) ) {
            self::$cache = array();
        }
        if ( !isset(self::$cache[$uid]) ) {
            self::$cache[$uid] = new PtStateInfo( $uid );
        }
        return self::$cache[$uid];
    }

    /**
     * @return array
     */
    public static function all()
    {
        // $list = array();
        $stateIds = array();

        foreach ( pdoConnect()->query("SELECT id FROM ".self::tableName(), PDO::FETCH_OBJ) as $obj ) {
            // array_push( $list, PtStateInfo::get( $obj->id ) );
            $stateIds[] = $obj->id;
        }

        return self::fromIds( $stateIds );
    }

    public static function fromIds( $stateIds )
    {
        $list = array();
        foreach ($stateIds as $id) {
            $list[] = PtStateInfo::get( $id );
        }
        return $list;
    }
}
?>