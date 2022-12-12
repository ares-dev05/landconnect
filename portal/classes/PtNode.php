<?php

class PtNode {

    // node types
    const MESSAGE  = "message";
    const FILE     = "file";
    const RANGE    = "range";

    public static function tableName(){ return DB_PREFIX."node"; }
    public static function viewTableName() { return DB_PREFIX."node_view"; }

    /**
     * @var DateTime $createdAt
     */
    // public class variables
    public $id;
    // parent ticket id
    public $tid;
    public $parentStoragePath;

    // properties
    public $type;
    public $data;   // message | file path | range name
    public $ownerId;
    public $createdAt;

    // array of users that viewed this node
    public $views;

    // private class variables
    private $dbObj;
    private $valid;

    public function isValid() { return $this->valid; }

    /**
     * returns the extension of the file
     */
    public function getExtension() {
        if ( $this->type == self::FILE ) {//$this->getServerStoragePath()
            return strtolower( @pathinfo($this->data, PATHINFO_EXTENSION) );
        }
    }

    /**
     * returns the path of this file on the server
     * @return string
     */
    public function getServerStoragePath() {
        if ( $this->type == self::FILE ) {
            // return $_SERVER['DOCUMENT_ROOT']."portal/".$this->getRelativeFilePath();
            return $this->getRelativeFilePath();
        }
        return "";
    }

    /**
     * return the relative path of this file to the root of the server
     * @return string
     */
    public function getRelativeFilePath() {
        if ( $this->type == self::FILE ) {
            return $this->parentStoragePath.$this->data;
        }
        return "";
    }

    /*
    public function getAbsolutePath() {
        return "//$_SERVER[HTTP_HOST]/".$this->getServerStoragePath();
    }
    */

    public function __construct( $id, $parentStoragePath ) {
        $this->id = intval($id);
        $this->parentStoragePath = $parentStoragePath;

        // fetch the object
        $this->fetch();
        // load all views
        $this->loadViews();
    }

    private function fetch( ) {
        $stmt   = pdoConnect()->query("SELECT * FROM ".self::tableName()." WHERE id={$this->id}");

        if ( $stmt && ($this->dbObj=$stmt->fetch(PDO::FETCH_OBJ))!=NULL ) {
            $this->valid    = true;

            $this->tid      = $this->dbObj->tid;
            $this->type     = $this->dbObj->type;
            $this->data     = $this->dbObj->data;
            $this->ownerId  = $this->dbObj->owner;
            $this->createdAt= toDateTime( $this->dbObj->created_at );
        }
        else {
            $this->valid = false;
        }
    }

    private function loadViews( ) {
        $this->views = array();

        $sql = "SELECT * FROM ".self::viewTableName()." WHERE nid=".$this->id;
        foreach (pdoConnect()->query($sql, PDO::FETCH_OBJ) as $view) {
            $this->views[] = $view->uid;
        }
    }

    public function addView( $userId ) {
        $userId = intval( $userId );
        if ( array_search($userId, $this->views) === FALSE ) {
            // insert a view
            // @OBSOLETTE: we don't really use this, no need to SQL
            // pdoConnect()->query("INSERT INTO ".self::viewTableName()." SET nid=".$this->id.", uid=$userId");
            $this->views[] = $userId;
        }
    }
    public function markViewed( $userCredentials ) {
        $this->addView( $userCredentials->user_id );
    }

    /**
     * @param PtTicket $ticket
     * @return array
     */
    public static function all( $ticket ) {
        $nodes    = array();
        $ticketId = $ticket->id;
        $sql      = "SELECT id FROM ".self::tableName()." WHERE tid=$ticketId ORDER BY created_at DESC";
        foreach ( pdoConnect()->query($sql, PDO::FETCH_OBJ) as $nodeObj ) {
            $nodes[] = new PtNode( $nodeObj->id, $ticket->storagePath );
        }
        return $nodes;
    }

    private static $createdAtStmt;
    public static function ticketUpdateTime( $ticket ) {
        if ( !self::$createdAtStmt ) {
            self::$createdAtStmt = pdoConnect()->prepare( "SELECT MAX(created_at) AS created_at FROM pt_node WHERE tid=:tid" );
        }

        self::$createdAtStmt->execute(array(":tid"=>$ticket->id));
        $createdAt = self::$createdAtStmt->fetchObject()->created_at;

        if ( $createdAt !== NULL && $createdAt !== "NULL" ) {
            return toDateTime($createdAt);
        }   else {
            return new DateTime();
        }
    }

    public static function create($ticket, $userId, $type, $data)
    {
        // make sure we're using a valid type
        if ( $type != self::FILE && $type != self::MESSAGE && $type != self::RANGE )
            return NULL;

        $db       = pdoConnect();
        $ticketId = $ticket->id;
        $userId   = intval( $userId );

        $stmt = $db->prepare("INSERT INTO ".self::tableName()."
             SET
                tid=:tid,
                `type`=:type,
                `data`=:data,
                owner=:uid
         ");
        $result = $stmt->execute(array(
            ":tid"  => $ticketId,
            ":type" => $type,
            ":data" => $data,
            ":uid"  => $userId
        ));

        return $result!==FALSE ? new PtNode( $db->lastInsertId(), $ticket->storagePath ) : NULL;
    }

    public static function getTicketId( $nodeId )
    {

    }
}
?>