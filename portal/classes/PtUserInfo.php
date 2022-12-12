<?php

class PtUserInfo {

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
	public $has_nearmap;
    public $is_envelope_admin;
    public $is_discovery_manager;
    public $is_admin;
    public $is_admin_state_locked;

    // private class variables
    private $dbObj;
    private $valid;

    public function __construct( $id ) {
        $this->id = intval($id);
        // fetch the object
        $this->fetch();
    }

    private function fetch( ) {
        $statement = pdoConnect()->query("SELECT * FROM ".self::tableName()." WHERE id={$this->id}");
        if ( $statement !== FALSE && ( $this->dbObj = $statement->fetch(PDO::FETCH_OBJ) ) ) {
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

    // keep a cache of all users
    public static $users;

    /**
     * @param int $uid
     * @return PtUserInfo
     */
    public static function get( $uid )
    {
        if ( !isset(self::$users) ) {
            self::$users = array();
        }
        if ( !isset(self::$users[$uid]) ) {
            self::$users[$uid] = new PtUserInfo( $uid );
        }
        return self::$users[$uid];
    }

    /**
     * @param int $company
     * @return Array
     */
    public static function getPortalUsers( $company ) {
        $company= intval( $company );
        $pUsers = array();

        $sql    = "SELECT id,display_name,email FROM ".self::tableName()." WHERE company_id={$company} AND has_portal_access=1";
        foreach ( pdoConnect()->query($sql, PDO::FETCH_OBJ) as $dbObj ) {
            $pUsers[ $dbObj->id ] = $dbObj->display_name." (".$dbObj->email.")";
        }

        // add the global recipients
        $globalRecipients = self::getGlobalRecepients();
        foreach ($globalRecipients as $uid) {
            $uInfo  = PtUserInfo::get( $uid );
            $pUsers[ $uInfo->id ] = $uInfo->display_name." (".$uInfo->email.")";
        }

        return $pUsers;
    }

    public static function getPortalUsersInfos( $company ) {
        $company = intval( $company );
        $pUsers = array();

        // @TODO @HACK: disable emailing to all PD users
        if ( $company != 4 ) {
            $sql = "SELECT id FROM " . self::tableName() . " WHERE company_id={$company} AND has_portal_access=1";
            foreach ( pdoConnect()->query($sql, PDO::FETCH_OBJ) as $dbObj ) {
                $pUsers[] = new PtUserInfo($dbObj->id);
            }
        }

        // add the global recipients
        $globalRecipients = self::getGlobalRecepients();
        foreach ($globalRecipients as $uid) {
            $pUsers[] = new PtUserInfo( $uid );
        }

        return $pUsers;
    }

    public static function getGlobalRecepients() {
        // uid 1   = Mihai Dersidan
        // uid 451 = Jeremy Santi
        return array(1, 451, 3169);
    }
}
?>