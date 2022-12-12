<?php

class PmUserInfo {

    static public $HENLEY_CID = 22;
    static public $HENLEY_DEMO_CID = 42;
    static public $PLANTATION_CID = 23;
    static public $HENLEY_SA_CID = 39;

    public static function tableName(){ return "uf_users"; }

    // public class variables
    public $id;
    public $user_name;
    public $display_name;
    public $password;
    public $email;
    public $activation_token;
    public $last_activation_request;
    public $lost_password_request;
    public $lost_password_timestamp;
    public $active;
    public $title;
    public $sign_up_stamp;
    public $last_sign_in_stamp;
    public $enabled;
    public $primary_group_id;
    public $company_id;
    public $state_id;
    public $has_multihouse;
    public $has_portal_access;
    public $has_master_access;
	public $has_exclusive;
    public $is_envelope_admin;
    public $is_admin;
    public $is_admin_state_locked;

    // private class variables
    private $dbObj;
    public $valid;

    public function __construct( $id ) {
        $this->id = intval($id);
        // fetch the object
        $this->fetch();
    }

    private function fetch( ) {
        // invalidate by default
        $this->valid = false;

        $statement = pdoConnect()->query("SELECT * FROM ".self::tableName()." WHERE id={$this->id}");
        if ( $statement !== FALSE && ( $this->dbObj = $statement->fetch(PDO::FETCH_OBJ) ) ) {
            $this->valid = true;
            foreach ($this->dbObj as $prop => $value) {
                if ($prop == "id") continue;
                $this->$prop = $value;
            }
        }
    }

    //////////////////////////////////////////////////////////////////////////////////
    // static functionality

    // keep a cache of all users
    public static $users;

    /**
     * @param int $uid
     * @return PmUserInfo
     */
    public static function get( $uid )
    {
        if ( !isset(self::$users) ) {
            self::$users = array();
        }
        if ( !isset(self::$users[$uid]) ) {
            self::$users[$uid] = new PmUserInfo( $uid );
        }
        return self::$users[$uid];
    }


    /**
     * @param $user loggedInUser
     * @return bool
     */
    public static function isUserAllowed($user) {
        return self::isCompanyAllowed($user->company_id);
    }

    public static function isCompanyAllowed($cid) {
        return $cid == self::$HENLEY_CID || $cid == self::$PLANTATION_CID || $cid == self::$HENLEY_SA_CID || $cid == self::$HENLEY_DEMO_CID;
    }

}
?>