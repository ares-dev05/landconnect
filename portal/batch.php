<?php

// @TODO: there's already a similar and more detailed implementation in /portal/classes/bulk.php

require_once("../models/config.php");
require_once("classes/pt_init.php");

global $loggedInUser;
if ( !$loggedInUser || !isGlobalAdmin($loggedInUser->user_id) ) {
    echo "1";
    exit;
}
$userInfo = getPlanPortalUserCredentials();
if ( !$userInfo ) {
    echo "2";
    exit;
}

$data = array(
    array(1609,	"PRINCETON 28.1"),
    array(1610,	"Princeton 25.1 - 25.2"),
    array(1611,	"Princeton 28.2"),
    array(1612,	"Princeton 28.3"),
    array(1613,	"Princeton 28.4"),
    array(1614,	"Princeton 30.1"),
    array(1615,	"Princeton 30.2"),
    array(1616,	"Princeton 30.3"),
    array(1617,	"Princeton 30.4"),
    array(1618,	"Princeton 32.1"),
    array(1619,	"Princeton 32.2"),
    array(1620,	"Princeton 32.3"),
    array(1621,	"Carnegie 28.1 - 28.2 - 28.3"),
    array(1622,	"Carnegie 28.4"),
    array(1623,	"Sanoma 28.1"),
    array(1624,	"Sanoma 28.2 - 28.3"),
    array(1625,	"Monterey 25.1"),
    array(1626,	"Monterey 25.2"),
    array(1627,	"Monterey 28.1 - 28.2"),
    array(1628,	"Monterey 28.3"),
    array(1629,	"Monterey 28.4"),
    array(1630,	"Monterey 30.2 - 30.3 - 30.4"),
    array(1631,	"Monterey 30.1"),
    array(1632,	"Monterey 32.1 - 32.2"),
    array(1633,	"Monterey 32.3"),
    array(1634,	"Monterey 32.4"),
    array(1635,	"Portland 16.1"),
    array(1636,	"bainbridge 21.1"),
    array(1637,	"bainbridge 21.2 - 21.3"),
    array(1638,	"bainbridge 25.1 - 25.2 - 25.3"),
    array(1639,	"bainbridge 28.1 - 28.2 - 28.3"),
    array(1640,	"bainbridge 28.4"),
    array(1642,	"bainbridge 30.1 - 30.2 - 30.3"),
    array(1643,	"bainbridge 30.4"),
    array(1644,	"bainbridge 25.4"),
    array(1645,	"Idaho 16.1"),
    array(1646,	"hartford 21.1"),
    array(1647,	"hartford 21.2"),
    array(1648,	"hartford 25.1 - 25.2 - 25.3"),
    array(1649,	"hartford 25.4"),
    array(1650,	"hartford 28.1 - 28.2 - 28.3"),
    array(1651,	"hartford 28.4"),
    array(1652,	"hartford 30.1 - 30.2 - 30.3"),
    array(1653,	"hartford 30.4"),
    array(1654,	"hartford 32.1"),
    array(1655,	"hartford 32.2"),
    array(1656,	"hartford 32.3"),
    array(1657,	"hartford 32.4"),
    array(1658,	"Auburn 25.1 - 25.2"),
    array(1659,	"Auburn 28.1"),
    array(1660,	"Auburn 28.2"),
    array(1661,	"Auburn 28.3"),
    array(1662,	"Auburn 28.4"),
    array(1663,	"Auburn 30.1"),
    array(1664,	"Auburn 30.2"),
    array(1665,	"Auburn 30.3"),
    array(1666,	"Auburn 30.4"),
    array(1667,	"Wentworth 25.1"),
    array(1668,	"Wentworth 25.2"),
    array(1669,	"Wentworth 25.3"),
    array(1670,	"Wentworth 28.1"),
    array(1671,	"Wentworth 28.2"),
    array(1673,	"Wentworth 28.3 - 28.4"),
    array(1675,	"Wentworth 30.1"),
    array(1676,	"Wentworth 30.2"),
    array(1677,	"Wentworth 30.3"),
    array(1678,	"Wentworth 30.4"),
    array(1679,	"franklin 30.1")
);

$releaseDate    = DateTime::createFromFormat('d/m/Y', "01/03/2018");
$targetRange    = "Wholesale";

foreach ( $data as $item ) {
    $id   = $item[0];
    $name = $item[1];

    $ticket=PtTicket::create($userInfo, array("company"=>17, "state"=>7));
    $ticket->props->setReleaseDate( $releaseDate );
    $ticket->props->setTargetRange( $targetRange );
    $ticket->props->setFloorName( $name );
    $ticket->setStatus(PtTicket::CLOSED);
}

?>