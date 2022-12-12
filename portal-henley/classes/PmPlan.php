<?php

class PmPlan {

    // 100MB
    const MAX_FILE_SIZE    = 104857600;
    const META_FILE_NAME   = "HOUSE_DB_METADATA";

    // public class variables
    public $id;
    public $uid;        // ID of the user that uploaded this plan
    public $owner_name;
    public $rangeId;
    public $name;
    public $url;
    public $areaData;
    public $updatedAt;
    public $updatedAtTs;
    public $isMetaDataFile;

    // private class variables
    private $dbObj;
    private $valid;

    public function isValid() { return $this->valid; }

    public function __construct($id, $dbObj=null) {
        $this->id = intval($id);

        // fetch the object
        $this->fetch($dbObj);
    }

    /**
     * @param $userInfo PmUserInfo
     * @return bool
     */
    public function delete($userInfo)
    {
        // reset the cache
        self::resetCacheDataFile($userInfo);

        if ($this->dbObj && $this->valid && $this->id>0) {
            if (!self::$deleteStmt) {
                self::$deleteStmt = pdoConnect()->prepare("DELETE FROM house_svgs WHERE id=:id");
            }
            
            if (self::$deleteStmt->execute(array("id"=>$this->id))) {
                // @TODO: delete from S3
                $this->valid = false;
                return true;
            }
        }

        return false;
    }

    private static $fetchStmt;
    private static $deleteStmt;

    private function fetch($dbObj=null) {
        if (!$this->dbObj) {
            if ( !self::$fetchStmt ) {
                self::$fetchStmt = pdoConnect()->prepare("SELECT house_svgs.*, uf_users.display_name
                FROM house_svgs
                INNER JOIN uf_users ON house_svgs.owner=uf_users.id
                WHERE house_svgs.id=:id");
            }
            if ( self::$fetchStmt->execute(array(":id"=>$this->id)) ) {
                $this->dbObj = self::$fetchStmt->fetchObject();
            }
        }   else {
            $this->dbObj = $dbObj;
        }

        if ($this->dbObj) {
            $this->valid     = true;
            $this->id        = $this->dbObj->id;
            $this->uid       = $this->dbObj->owner;
            // $this->owner_name= isset($this->dbObj->display_name) ? $this->dbObj->display_name : "";
            // $this->owner_name= $this->dbObj;
            $userInfo = new PmUserInfo($this->uid);
            $this->owner_name = $userInfo->display_name;
            $this->rangeId   = $this->dbObj->range_id;
            $this->name      = $this->dbObj->name;
            $this->url       = $this->dbObj->url;
            $this->updatedAt = toDateTime($this->dbObj->updated_at);
            $this->updatedAtTs = $this->updatedAt->getTimestamp();

            if ($this->name==self::META_FILE_NAME) {
                // set the display name to the original filename
                $this->name  = $this->url;
                $this->isMetaDataFile = true;
            }   else {
                $this->isMetaDataFile = false;
            }
        }
        else {
            $this->valid = false;
        }
    }

    //////////////////////////////////////////////////////////////////////////////////
    // static functionality

    /**
     * @param PmUserInfo $userInfo
     * see process_login.php for all of this user's parameters
     */
    public static function all($userInfo)
    {
        $list   = array();

        // fetch all the plans
        $db     = pdoConnect();
        $stmt   = $db->prepare("
            SELECT house_svgs.*
            FROM house_svgs
            INNER JOIN house_ranges
                ON house_svgs.range_id=house_ranges.id
            WHERE house_ranges.cid=:cid AND house_ranges.state_id=:state_id");

        // if ($stmt->execute(array(":cid"=>$userInfo->company_id, ":state_id"=>$userInfo->state_id))) {
        if ($stmt->execute(array(":cid"=>$userInfo->company_id, ":state_id"=>$userInfo->state_id))) {
            while (($dbObj=$stmt->fetchObject())!=null) {
                $plan = new PmPlan($dbObj->id, $dbObj);

                // keep the metadata file at the top of the list
                if ($plan->isMetaDataFile) {
                    array_unshift($list, $plan);
                }   else {
                    $list []= $plan;
                }
            }
        }

        return $list;
    }

    private static function houseName($fileName)
    {
        if (!$fileName || $fileName=="")
            return "";

        $parts = explode(".", $fileName);
        if (sizeof($parts)>1 && strtolower($parts[1])=="txt") {
            return self::META_FILE_NAME;
        }

        return strtoupper($parts[0]);
    }

    /**
     * Upload the metadata file for Henley
     * @param $userInfo PmUserInfo
     * @param $metadata string
     * @return bool indicating success
     */
    public static function metaUpload($userInfo, $metadata) {
        $db = pdoConnect();

        // assume that we'll have a successful upload and reset the cache data file now
        self::resetCacheDataFile($userInfo);

        // create the delete & insert query
        $deleteStmt = $db->prepare(
            "DELETE FROM house_svgs
             WHERE range_id=:range_id AND name=:name"
        );
        $insertStmt = $db->prepare(
            "INSERT INTO house_svgs
             SET
                range_id=:range_id,
                name=:name,
                url=:url,
                area_data=:area_data,
                updated_at=NOW(),
                owner=:owner
              "
        );

        $name       = self::META_FILE_NAME;
        $fileName   = "{$name}.txt";
        $filePath   = getHenleyStoragePath($userInfo).$fileName;

        if (!file_put_contents($filePath, $metadata)) {
            return false;
        }   else {
            // we can put a unique on range_id + name though!
            if ($deleteStmt->execute(array(
                    "range_id" => getHenleyStorageRange($userInfo),
                    "name" => $name
                )) &&
                $insertStmt->execute(array(
                    "range_id" => getHenleyStorageRange($userInfo),
                    "name" => $name,
                    "url" => $fileName,
                    "area_data" => "",
                    "owner" => $userInfo->id
                )) ) {
                return true;
            }   else {
                return false;
            }
        }
    }

    /**
     * @param $userInfo PmUserInfo
     * @return array
     */
    public static function massUpload($userInfo) {
        $db = pdoConnect();

        // assume that we'll have a successful upload and reset the cache data file now
        self::resetCacheDataFile($userInfo);

        $uploadPath   = getHenleyStoragePath($userInfo);
        $currentRange = getHenleyStorageRange($userInfo);

        // create the delete & insert query
        $deleteStmt = $db->prepare(
            "DELETE FROM house_svgs
             WHERE range_id=:range_id AND name=:name"
        );
        $insertStmt = $db->prepare(
            "INSERT INTO house_svgs
             SET
                range_id=:range_id,
                name=:name,
                url=:url,
                area_data=:area_data,
                updated_at=NOW(),
                owner=:owner
              "
        );

        if (isset($_FILES) && isset($_FILES['files']) ) {
            $fileArray = $_FILES['files'];
            $count = count($fileArray['name']);

            $success = 0;
            $errors = 0;
            // define( 'PORTAL_STORAGE', "s3://".STORAGE_BUCKET."/storage/" );

            // Loop through each file
            for( $i=0 ; $i < $count ; $i++ ) {
                //check for possible errors
                $name   = self::houseName($fileArray["name"][$i]);
                $uploadName = $fileArray["name"][$i];
                $source = $fileArray['tmp_name'][$i];
                $dest   = $uploadPath.$uploadName;

                if ($source!="") {
                    if (!file_exists($source)) {
                        ++$errors;
                    }

                    if (!move_uploaded_file($source, $dest)) {
                        ++$errors;
                    }   else {
                        // @TODO: here we should INSERT ON DUPLICATE UPDATE (though we don't have a unique key on the house)
                        // we can put a unique on range_id + name though!
                        if ($deleteStmt->execute(array(
                                "range_id" => $currentRange,
                                "name" => $name
                            )) &&
                            $insertStmt->execute(array(
                                "range_id" => $currentRange,
                                "name" => $name,
                                "url" => $uploadName,
                                "area_data" => "",
                                "owner" => $userInfo->id
                            )) ) {
                            ++$success;
                        }   else {
                            ++$errors;
                        }
                    }
                }
            }

            return array(
                "success" => $success,
                "error" => ($errors?"Failed uploading $errors files":""),
                "failCount" => $errors
            );
        }

        return null;
    }

    /**
     * @param $userInfo PmUserInfo
     */
    public static function resetCacheDataFile($userInfo)
    {
        require_once(__DIR__."/../../lcapi/includes/LcApi.php");
        LcAPi::resetCompanyDataXml($userInfo->company_id, $userInfo->state_id);
    }
}
?>