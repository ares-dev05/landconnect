<?php

define( 'DB_PREFIX', 'pt_' );
define( 'SQL_DATETIME', 'Y-m-d H:i:s');
define( 'PORTAL_STORAGE', "s3://".STORAGE_BUCKET."/storage/" );

// installation-specific settings
define( 'PROPERTIES_CLASS', 'PtFootprintsProps' );

// true if permissions are given based on the watcher system
define( 'WATCHER_SYSTEM_ENABLED', false );

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

function getPlanPortalUserCredentials( )
{
    // make sure the currently logged-in user has plan portal credentials
    if ( isUserLoggedIn() ) {
        global $loggedInUser;

        // get the PtUserInfo
        $portalUser = PtUserInfo::get( $loggedInUser->user_id );

        // check that this is not a Henley user
        include_once(__DIR__.'/../../portal-henley/classes/PmUserInfo.php');

        // make sure user has portal access (but is not from Henley
        if ( ($portalUser->has_portal_access && !PmUserInfo::isUserAllowed($loggedInUser)) ||
              PtTicket::isGlobalAdmin($portalUser->id) ) {
            return $portalUser;
        }
    }

    return NULL;
}

?>