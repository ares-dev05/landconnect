<?php

define( 'SQL_DATETIME', 'Y-m-d H:i:s');

/**
 * TODO: update
 * @param $userInfo PmUserInfo
 * @return string
 */
function getHenleyStoragePath($userInfo) {
    if ($userInfo->company_id==PmUserInfo::$HENLEY_CID) {
        return "s3://" . STORAGE_BUCKET . "/floorplans/RCNRDMzu1d7VSLgZmOH7/vic/vic_range/";
    }   else if ($userInfo->company_id==PmUserInfo::$PLANTATION_CID) {
        return "s3://".STORAGE_BUCKET."/floorplans/WNCnLUSBMqekWHRbhUEW/qld/qld_range/";
    }   else if ($userInfo->company_id==PmUserInfo::$HENLEY_SA_CID) {
        return "s3://".STORAGE_BUCKET."/floorplans/OtInvfpuLwAv80YsQUPA/sa/sa_range/";
    }   else if ($userInfo->company_id==PmUserInfo::$HENLEY_DEMO_CID) {
        return "s3://" . STORAGE_BUCKET . "/floorplans/RCNRDMzu1d7VSLgZmOH7_demo/vic/vic_range/";
    }
    return '';
}

/**
 * TODO: update
 * @param $userInfo PmUserInfo
 * @return int
 */
function getHenleyStorageRange($userInfo) {
    if ($userInfo->company_id==PmUserInfo::$HENLEY_CID) {
        return 116;
    }   else if ($userInfo->company_id==PmUserInfo::$PLANTATION_CID) {
        return 142;
    }   else if ($userInfo->company_id==PmUserInfo::$HENLEY_SA_CID) {
        return 362;
    }   else if ($userInfo->company_id==PmUserInfo::$HENLEY_DEMO_CID) {
        return 364;
    }

    return 0;
}

/**
 * Prepare the S3 Client & Stream Wrapper
 */
$s3Client = getS3Client();
$s3Client->registerStreamWrapper();

/**
 * @param String $sqlDateTimeString
 * @return DateTime
 */
function toDateTime( $sqlDateTimeString )
{
    return DateTime::createFromFormat( SQL_DATETIME, $sqlDateTimeString );
}

/**
 * @param DateTime $phpDateTimeObject
 * @return string
 */
function toSqlDt( $phpDateTimeObject )
{
    return $phpDateTimeObject->format( SQL_DATETIME );
}

/**
 * @return PmUserInfo
 */
function getPlanManagementUserCredentials( )
{
    if ( isUserLoggedIn() ) {
        global $loggedInUser;
        
        $portalUser = PmUserInfo::get( $loggedInUser->user_id );

        // make sure user has plan management access
        if ($portalUser->valid && $portalUser->has_portal_access && PmUserInfo::isUserAllowed($loggedInUser)) {
            return $portalUser;
        }
    }

    return null;
}

?>