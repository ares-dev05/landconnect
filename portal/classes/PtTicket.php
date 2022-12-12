<?php

/**
 * Class PtTicket
 * naming convention:
 *  {builderName}-{ticketNum}
 *      e.g.:
 *      SIMONDS-1   / Jasper 2100
 */
class PtTicket {

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Class Definition

    const OPEN     = "open";
    const PROBLEM  = "problem";
    const CLOSED   = "closed";

    // 100MB
    const MAX_FILE_SIZE = 104857600;

    public static function tableName(){ return DB_PREFIX."ticket"; }

    /**
     * @var $createdAt      DateTime
     * @var $props          PtTicketProps
     * @var $billingData    PlanBillingData contains all the invoices associated with this ticket
     */

    // public class variables
    public $id;

    // company
    public $cid;
    public $stateId;

    // current status of the ticket
    public $status;
    public $createdAt;

    // display vars
    public $ticketName;
    public $storagePath;

    public $createdAtTs;
    public $updatedAtTs;

    public $companyName;
    public $stateName;
    public $rangeName;
    public $floorName;

    // list of all the messages/files.
    public $nodes;
    // list of all the watchers
    public $watchers;
    // ticket properties
    public $props;

    // billing data
    public $billingData;

    // private class variables
    private $dbObj;
    private $valid;

    public function isValid() { return $this->valid; }

    public function __construct($id, $loadFullProperties=true ) {
        $this->id = intval($id);

        // fetch the object
        $this->fetch();

        if ( $this->valid ) {
            if ( $loadFullProperties ) {
                // load this ticket's properties
                $this->loadProps();

                // load all nodes
                $this->loadNodes();
                // load all the watchers
                $this->loadWatchers();
                // load all display variables
                $this->loadDisplayVars();

                if (class_exists("PlanBillingData", false)) {
                    // load billing data associated to this ticket
                    $this->billingData = new PlanBillingData($this->id);
                }
            }   else {
                // load just the info that's needed for
                $this->loadProps();
                $this->loadDisplayVars();
            }
        }
    }

    private static $fetchStmt;

    private function fetch( ) {
        if ( !self::$fetchStmt ) {
            self::$fetchStmt = pdoConnect()->prepare("
                SELECT *
                FROM ".self::tableName()."
                WHERE id=:id
            ");
        }

        if ( self::$fetchStmt->execute(array(":id"=>$this->id)) &&
           ( $this->dbObj=self::$fetchStmt->fetch(PDO::FETCH_OBJ) ) != NULL ) {
            $this->valid     = true;

            $this->cid       = $this->dbObj->cid;
            $this->stateId   = $this->dbObj->state_id;
            $this->status    = $this->dbObj->status;
            $this->createdAt = toDateTime( $this->dbObj->created_at );

            // prepare ticket name & paths
            // Company / State / Range / House Name
            $this->ticketName =
                PtCompanyInfo::get($this->cid)->builder_id."-".
                PtStateInfo::get($this->stateId)->abbrev."-".
                $this->id;

            $this->storagePath = PORTAL_STORAGE.$this->ticketName."/";
            $this->createdAtTs = $this->createdAt->getTimestamp();
        }
        else {
            $this->valid = false;
        }
    }

    private function commit( ) {
        return pdoConnect()->prepare("
            UPDATE ".self::tableName()."
             SET status=:status
             WHERE id=:id"
        )->execute(array(
            ":status"=>$this->status,
            ":id"=>$this->id)
        ) !== FALSE;
    }

    public function delete( ) {
        return pdoConnect()->prepare(
            "DELETE FROM ".self::tableName()." WHERE id=:id"
        )->execute(array(":id"=>$this->id)) !== FALSE;
    }

    public function setStatus( $newStatus )
    {
        if ( !self::validStatus($newStatus) ) {
            return false;
        }
        if ( $this->status==$newStatus ) {
            // silent success
            return true;
        }
        $this->status = $newStatus;
        return $this->commit();
    }

    /**
     * load the properties for this ticket
     */
    public function loadProps( ) {
        $propsClass  = new ReflectionClass( PROPERTIES_CLASS );
        $this->props = $propsClass->newInstance( $this->id );
    }

    /**
     * load (or reload) all the nodes for this ticket; order by creation time
     */
    public function loadNodes( ) {
        $this->nodes = PtNode::all( $this );
    }
    /**
     * load (or reload) all the watchers for this ticket;
     */
    public function loadWatchers( ) {
        $this->watchers = PtWatcher::all( $this->id );
    }

    private function loadDisplayVars( ) {
        $this->updatedAtTs = $this->createdAt->getTimestamp();

        if ( !isset($this->nodes) ) {
            // nodes weren't loaded; fetch the update time with an SQL query
            $this->updatedAtTs = PtNode::ticketUpdateTime($this)->getTimestamp();
        }   else {
            if (count($this->nodes)) {
                // nodes are in a descending date order: latest node is the first one
                // i.e. LIFO order
                $this->updatedAtTs = $this->nodes[0]->createdAt->getTimestamp();
            } else {
                $this->updatedAtTs = $this->createdAt->getTimestamp();
            }
        }

        $this->companyName  = PtCompanyInfo::get( $this->cid )->name;
        $this->stateName    = PtStateInfo::get( $this->stateId )->name;
        $this->rangeName    = $this->props->getTargetRange();
        $this->floorName    = $this->props->getFloorName();
    }

    /**
     * @param PtUserInfo $userInfo
     * @param array $fileArray
     * @return PtNode
     */
    public function addFileNode( $userInfo, $fileArray, $i=0 ) {
        global $apiErrors;

        if ( $this->userCanEdit( $userInfo ) ) {
            try {
                // Undefined | Multiple Files | $_FILES Corruption Attack
                // If this request falls under any of them, treat it invalid.
                if ( !isset($fileArray['error'][$i]) || is_array($fileArray['error'][$i]) ) {
                    $apiErrors[] = 'Invalid parameters.';
                    return NULL;
                }

                // Check $fileArray['error'] value.
                switch ($fileArray['error'][$i]) {
                    case UPLOAD_ERR_OK:
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $apiErrors[] = 'No file sent.';
                        return NULL;
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        $apiErrors[] = 'Exceeded filesize limit.';
                        return NULL;
                    default:
                        $apiErrors[] = 'Unknown file uploading error.';
                        return NULL;
                }

                if ($fileArray['size'][$i] > self::MAX_FILE_SIZE ) {
                    $apiErrors[] = 'Exceeded filesize limit of 100MB.';
                    return NULL;
                }

                // strip all slashes from the filename
                // $fileUploadName = stripslashes( $fileArray['name'] );
                $fileUploadName = security( $fileArray['name'][$i] );

                /*
                if ( !ctype_alnum($fileUploadName) ||
                     !preg_match('/^(?:[a-z0-9_- .,:;]|\.(?!\.))+$/iD', $fileUploadName) ) {
                    $apiErrors[] = 'Invalid filename. '.$fileUploadName;
                    return false;
                }

                no need to verify the extension at this point
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                if (false === $ext = array_search(
                        $finfo->file($fileArray['tmp_name']),
                        array(
                            'jpg' => 'image/jpeg',
                            'png' => 'image/png',
                            'gif' => 'image/gif',
                        ),
                        true
                    )) {
                    throw new RuntimeException('Invalid file format.');
                }
                */

                // You should name it uniquely.
                // DO NOT USE $fileArray['name'] WITHOUT ANY VALIDATION !!
                // On this example, obtain safe unique name from its binary data.
                $source = $fileArray['tmp_name'][$i];
                $dest   = "{$this->storagePath}{$fileUploadName}";

                if ( !file_exists($source) ) {
                    $apiErrors[] = "Failed to upload the file, please try again and contact us with the error details if the issue persist.";
                    return NULL;
                }

                if ( !$this->hasStorageDir() ) {
                    if ( !$this->createStorageDir() ) {
                        $apiErrors[] = "Failed to create storage for this file. Please contact us immediately so we can rectify the problem.";
                        return NULL;
                    }
                }

                if ( !move_uploaded_file($source, $dest) ) {
                    // $apiErrors[] = "Failed to move uploaded file.";
                    $apiErrors[] = "Failed to move uploaded file. Please contact us immediately so we can rectify the problem.";
                    return NULL;
                }

                if (file_exists($source)) {
                    try {
                        unlink($source);
                    }   catch (Exception $exception) {}
                }

                return $this->addNode( $userInfo, PtNode::FILE, $fileUploadName );
            } catch (RuntimeException $e) {
                $apiErrors[] = $e->getMessage();
                return NULL;
            }
        }
    }

    /**
     * @param PtUserInfo $userInfo
     * @param $type
     * @param $data
     * @return PtNode
     */
    public function addNode( $userInfo,  $type,  $data ) {
        if ( $this->userCanEdit( $userInfo) ) {
            if ( ( $node = PtNode::create( $this, $userInfo->id, $type, $data ) ) != NULL ) {
                $this->nodes[] = $node;
            }
            return $node;
        }
        return NULL;
    }

    /**
     * @param PtUserInfo $userInfo the user making the edit
     * @param int $uid the new
     * @return null|PtWatcher
     */
    public function addWatcher( $userInfo, $uid, $isOwner=0, $isAssignee=0 ) {
        if ( $this->userCanEdit( $userInfo ) ) {
            $newWatcherUI = PtUserInfo::get($uid);

            // make sure this user is not already a watcher
            if ( $this->userCanView( $newWatcherUI ) ) {
                return $this->findWatcher( $newWatcherUI );
            }
            if ( ( $watcher = PtWatcher::create( $this->id, $uid, $isOwner, $isAssignee ) ) != NULL ) {
                $this->watchers[] = $watcher;
            }
            return $watcher;
        }
        return NULL;
    }

    /**
     * @return PtWatcher
     */
    public function getOwner( ) {
        foreach ( $this->watchers as $watcher ) {
            /** @var PtWatcher $watcher */
            if ( $watcher->isOwner ) {
                return $watcher;
            }
        }

        return NULL;
    }

    public function getAbsolutePath( ) {
        return "//$_SERVER[HTTP_HOST]/portal/view_ticket.php?id=".$this->id;
    }

    //////////////////////////////////////////////////////////////////////////////////
    // Watcher functionality

    /**
     * @param PtUserInfo $userInfo
     * @return bool
     */
    public function userInTicketGroup( $userInfo ) {
        // the user is in group either if he/she's a global admin, or in the same company
        return self::isGlobalAdmin($userInfo->id) ||
                $userInfo->company_id == $this->cid;
    }

    /**
     * @param PtUserInfo $userInfo
     */
    public function userCanEdit( $userInfo ) {
        // first make sure the user is in the group that owns this ticket
        if ( $this->userInTicketGroup($userInfo) ) {
            if ( WATCHER_SYSTEM_ENABLED ) {
                // if the watcher system is enabled, check if the user is allowed
                // to edit this ticket
                $watcher = $this->findWatcher($userInfo);
                return $watcher ? $watcher->canEdit() : false;
            }   else
                return true;
        }
    }
    /**
     * @param PtUserInfo $userInfo
     */
    public function userCanView( $userInfo ) {
        // first make sure the user is in the group that owns this ticket
        if ( $this->userInTicketGroup($userInfo) ) {
            if ( WATCHER_SYSTEM_ENABLED ) {
                // if the watcher system is enabled, check if the user is allowed
                // to view this ticket
                return $this->findWatcher($userInfo) != NULL;
            }   else
                return true;
        }
    }
    public function findWatcher( $userInfo ) {
        foreach ( $this->watchers as $watcher ) {
            if ( $watcher->uid == $userInfo->id )
                return $watcher;
        }
        return NULL;
    }

    /**
     * @return bool indicates if the storage path exists
     */
    private function hasStorageDir()
    {
        return file_exists( $this->storagePath ) && is_dir( $this->storagePath );
    }

    /**
     * create the storage folder for this ticket
     * @return bool true on success, or if dir exists already
     */
    public function createStorageDir() {
        // create the storage path if it doesn't exist yet
        if ( !$this->hasStorageDir() ) {
            //if ( !mkdir( $this->storagePath, 0755, true ) ) {
            if ( !mkdir( $this->storagePath ) ) {
                // throw new Exception("Unable to create dir at ".$this->storagePath." | ".getcwd());
                return false;
            }
        }

        return true;
    }

    //////////////////////////////////////////////////////////////////////////////////
    // static functionality

    public static function validStatus( $status )
    {
        return $status==self::OPEN || $status==self::CLOSED || $status==self::PROBLEM;
    }

    /**
     * @param PtUserInfo $userInfo
     * see process_login.php for all of this user's parameters
     */
    public static function all( $userInfo, $state=-1, $loadProperties=true )
    {
        // sanitize input
        $state  = intval($state);
        $db     = pdoConnect();

        // fetch all the tickets available to this user.
        if ( self::isGlobalAdmin( $userInfo->id ) ) {
            // load all tickets!
            $whereClause = "";
            if ( $state > 0 ) {
                $whereClause = " WHERE state_id = $state ";
            }

            $sqlRes = $db->query(
                "SELECT id
                 FROM ".self::tableName()."
                 $whereClause
                 ORDER BY created_at DESC",
                PDO::FETCH_OBJ
            );
        }   else {
            // load only the tickets that can be viewed (watched by this user)
            // add a company check for added security: cross-company watchers are forbidden
            // (e.g. a user from company A watching tickets from company B).

            // build optional watcher clause
            $watcherClause = "";
            if ( WATCHER_SYSTEM_ENABLED ) {
                $watcherClause = "
                    AND id IN (
                        SELECT tid
                        FROM ".PtWatcher::tableName()."
                        WHERE uid={$userInfo->id}
                    )
                ";
            }

            // build optional state clause; verify that this is an allowed state
            $stateClause = "";
            if ( $state > 0 && array_key_exists($state, fetchCompanyStates($userInfo->company_id)) ) {
                $stateClause = " AND state_id = $state ";
            }

            $sqlRes = $db->query(
                "SELECT id
                 FROM ".self::tableName()."
                 WHERE cid={$userInfo->company_id}
                 {$watcherClause}
                 {$stateClause}
                 ORDER BY created_at DESC",
                PDO::FETCH_OBJ
            );
        }

        // build all the tickets
        $list   = array();
        foreach ( $sqlRes as $tObj ) {
            $list[] = new PtTicket( $tObj->id, $loadProperties );
        }
        return $list;
    }

    /**
     * @param PtUserInfo $userInfo
     * @param int $tickedId
     * @return PtTicket
     */
    public static function get( $userInfo, $tickedId )
    {
        $ticket = new PtTicket( $tickedId );
        return $ticket->userCanView( $userInfo ) ? $ticket : NULL;
    }

    /**
     * @param PtUserInfo $userInfo
     * see process_login.php for all of this user's parameters
     */
    public static function create( $userInfo, $options=NULL )
    {
        // find this ticket's Company
        if ( $options!=NULL && isset($options['company']) ) {
            $company = $options['company'];
        }   else {
            $company = $userInfo->company_id;
        }

        // find the ticket's State
        if ( $options!=NULL && isset($options['state']) ) {
            $state = $options['state'];
        }   else {
            $state = $userInfo->state_id;
        }

        $db = pdoConnect();
        $stmt = $db->prepare("INSERT INTO ".self::tableName()."
             SET
                cid      = :cid,
                state_id = :sid,
                status   = :status"
        );
        // created by the current user
        if ( $stmt->execute(array(
                ":cid" => $company,
                ":sid" => $state,
                ":status" => PtTicket::PROBLEM
            )) !== FALSE ) {
            // fetch the ticket object, and add the default assignees
            $ticket = new PtTicket( $db->lastInsertId() );
            // create the folder structure
            $ticket -> createStorageDir();

            self::loadGlobalAdmins();

            $watchers = self::$globalAdmins;
            if ( !in_array( $userInfo->id, $watchers ) )
                $watchers[] = $userInfo->id;

            // add extra watchers to the list
            if ( $options!=NULL && isset($options['watchers']) ) {
                foreach ($options['watchers'] as $opWatcher=>$name  )
                    if ( !in_array($opWatcher, $watchers) )
                        $watchers[] = $opWatcher;
            }

            // create watchers for this ticket
            foreach ($watchers as $watcherId) {
                PtWatcher::create(
                    $ticket->id,
                    $watcherId,
                    ( $watcherId == $userInfo->id ) ? 1 : 0,
                    self::isGlobalAdmin( $watcherId ) ? 1 : 0
                );
            }

            $ticket->loadWatchers();

            return $ticket;
        }

        return NULL;
    }

    private static $globalAdmins;

    private static function loadGlobalAdmins() {
        if ( !isset(self::$globalAdmins) ) {
            // uid 247 = root.admin
            // uid 451 = Jeremy
            // uid 1   = Mike Samy
            self::$globalAdmins = array( 247, 451, 1 );
        }
    }

    /**
     * @param PtUserInfo $userInfo
     * @return bool
     */
    public static function isGlobalAdmin( $uid ) {
        self::loadGlobalAdmins();
        return in_array( $uid, self::$globalAdmins );
    }
}
?>