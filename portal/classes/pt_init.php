<?php

function denyAccess( $message="You cannot access this resource.") {
    addAlert("danger", $message );
    header("Location: " . getReferralPage());
    exit();
}

// db + config
include_once(__DIR__.'/../../models/config.php');
include_once('PtConfig.php');

// static info classes
include_once('PtUserInfo.php');
include_once('PtCompanyInfo.php');
include_once('PtStateInfo.php');

// portal system objects
include_once('PtTicketProps.php');
include_once('PtFootprintsProps.php');
include_once('PtWatcher.php');
include_once('PtNode.php');
include_once('PtTicket.php');

include_once('PtEmailer.php');

?>