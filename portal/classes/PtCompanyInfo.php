<?php

class PtCompanyInfo {

    // envelopes modes
    const ENVELOPES_MANAGED     = 1;
    const ENVELOPES_UNMANAGED   = 2;

    public static function tableName(){ return "companies"; }

    // public class variables
    public $id;
    public $ckey;
    public $folder;
    public $name;
    public $theme_id;
    public $builder_id;
    public $domain;
    public $use_as_bot;
    public $chas_multihouse;
	public $chas_exclusive;
    /**
     * @var $chas_envelopes
     *  0 -> no Envelopes access
     *  1 -> Managed Envelopes mode
     *  2 -> Unmanaged Envelopes mode
     */
    public $chas_envelopes;
    public $chas_portal_access;
    public $chas_master_access;
    
    // private class variables
    private $dbObj;
    public $valid;

    public function __construct( $id ) {
        $this->id = intval($id);
        // fetch the object
        $this->fetch();
    }

    private function fetch( ) {
        $stmt   = pdoConnect()->query("SELECT * FROM ".self::tableName()." WHERE id={$this->id}");
        if ( $stmt && ($this->dbObj=$stmt->fetch(PDO::FETCH_OBJ)) ) {
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
     * @return PtCompanyInfo
     */
    public static function get( $uid )
    {
        if ( !isset(self::$cache) ) {
            self::$cache = array();
        }
        if ( !isset(self::$cache[$uid]) ) {
            self::$cache[$uid] = new PtCompanyInfo( $uid );
        }
        return self::$cache[$uid];
    }
}
?>