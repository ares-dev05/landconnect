<?php

class PtWatcher {

    public static function tableName(){ return DB_PREFIX."watcher"; }

    /**
     * @var int $targetTicket
     * @var int $uid
     * @var PtUserInfo $userInfo
     * @var int $isOwner
     * @var int $isAssignee
     */
    // the id of the ticket watched by this watcher
    public $targetTicketId;
    // the id of the user
    public $uid;
    public $userInfo;

    // is the owner of the ticket?
    public $isOwner;
    // only admins can be assignees?
    public $isAssignee;

    // private class variables
    private $dbObj;
    private $valid;

    public function isValid() { return $this->valid; }

    //
    public function __construct( $tid, $uid ) {
        $this->targetTicketId = intval($tid);
        $this->uid = intval($uid);
        // fetch the object
        $this->fetch();
    }

    private function fetch( ) {
		$db = pdoConnect();
        $statement = $db->prepare(
            "SELECT *
			 FROM ".self::tableName()."
			 WHERE tid=:tid AND uid=:uid"
        );

        if	( $statement->execute( array(":tid"=>$this->targetTicketId, ":uid"=>$this->uid ) ) &&
            ( $this->dbObj=$statement->fetch(PDO::FETCH_OBJ) ) ) {
            $this->valid        = true;
            $this->userInfo     = PtUserInfo::get( $this->uid );
            $this->isOwner      = $this->dbObj->role_owner;
            $this->isAssignee   = $this->dbObj->role_assignee;
        }
        else {
            $this->valid = false;
        }
    }

    // credentials/permission check
    public function canEdit( ) {
        return $this->isOwner || $this->isAssignee;
    }

    ////////////////////////////////////////////////////////////////////////////////
    public static function all( $ticketId ) {
        $list     = array();
        $ticketId = intval( $ticketId );

        $db       = pdoConnect();
        $statement= $db->prepare(
            "SELECT uid
             FROM ".self::tableName()."
             WHERE tid=:tid"
        );

        if ( $statement->execute(array(":tid"=>$ticketId)) ) {
            while ( ( $watcherObj = $statement->fetch(PDO::FETCH_OBJ) ) != NULL ) {
                $list[] = new PtWatcher( $ticketId, $watcherObj->uid );
            }
        }

        return $list;
    }

    public static function create($ticketId, $userId, $isOwner, $isAssignee)
    {
        // @TEMP - disable
        return;
        $ticketId   = intval( $ticketId );
        $userId     = intval( $userId );
        $isOwner    = intval( $isOwner );
        $isAssignee = intval( $isAssignee );

        $db         = pdoConnect();
        $statement  = $db->prepare(
            "INSERT INTO ".self::tableName()."
             SET
                tid             = :tid,
                uid             = :uid,
                role_owner      = :role_owner,
                role_assignee   = :role_assignee
             "
        );
        $result     = $statement->execute(array(
            ":tid"              => $ticketId,
            ":uid"              => $userId,
            ":role_owner"       => $isOwner,
            ":role_assignee"    => $isAssignee
        ));

        return $result ? new PtWatcher( $ticketId, $userId ) : NULL;
    }
}
?>