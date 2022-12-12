<?php

$start = microtime(true);

// include API first so we declare that BILLING doesn't need to initialize
require_once("inc_api.php");
require_once("../classes/pt_init.php");

// make sure the user is logged-in, and has plan portal access
if ( !( $userInfo = getPlanPortalUserCredentials() ) ) {
    $apiErrors[] = "You do not have plan portal access.";
    exitWithError( );
}

$state = -1;
if ( isset( $_GET['state'] ) ) {
    $state = intval($_GET['state']);
}

if ( !( $tickets = PtTicket::all( $userInfo, $state, false ) ) ) {
    $apiErrors[] = "You have no plans.";
    // exitWithError();
    exitWithSuccess( array() );
}   else {
    exitWithSuccess( copyRequiredKeys($tickets) );
}

function copyRequiredKeys( $list )
{
    $keys = array("id","floorName","ticketName","status","companyName","stateName","rangeName","createdAtTs","updatedAtTs");
    $result = array();
    global $start;
    
    // $result[] = array("benchmarking"=>microtime(true)-$start);

    foreach ( $list as $ticket ) {
        $props = array();
        foreach ($keys as $key) {
            $props[$key] = $ticket->$key;
        }
        $result[] = $props;
    }
    return $result;
}

?>