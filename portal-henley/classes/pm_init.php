<?php

function denyAccess( $message="You cannot access this resource.") {
    addAlert("danger", $message );
    header("Location: " . getReferralPage());
    exit();
}

// db + config
include_once(__DIR__.'/../../models/config.php');

include_once('PmConfig.php');
include_once('PmUserInfo.php');
include_once('PmPlan.php');

?>