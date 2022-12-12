<?php

require_once("../../models/config.php");
require_once("../classes/stats_init.php");
// require_once("inc_api.php");

// @TODO: check that the current user has access to these stats!
$startTs    = microtime(true);

$type    = requiredPostVar( "type" );
$aggr    = requiredPostVar( "aggr" );
$house   = requiredPostVar( "house" );
$company = intval( requiredPostVar("company") );
$state   = intval( requiredPostVar("state") );

$usersList = requiredPostVar( "users" );
$fromDate = requiredPostVar( "fromDate" );
$toDate = requiredPostVar( "toDate" );

if ( $usersList && strlen($usersList) ) {
    $users = explode( ',', $usersList );
}   else {
    $users = array();
}

if ( !$type || !$aggr || !$company || !$state || !$fromDate || !$toDate ||
    DateTime::createFromFormat("Y-m-d", $fromDate)===FALSE ||
    DateTime::createFromFormat("Y-m-d", $toDate)===FALSE ) {
    $apiErrors[] = "Missing parameters.";
    echo json_encode(array("errors" => 1, "successes" => 0));
    exit;
}

// fetch the statistics
$stats = new StatisticParams( $type, $aggr, $company, $state, $house, $fromDate, $toDate, $users );

$result = array(
    "debug.timing"  => (microtime(true)-$startTs),
    "settings" => $stats->getSettings(),
    "columns"  => $stats->getColumns(),
    "results"  => $stats->getResults()
);

echo json_encode($result);

?>