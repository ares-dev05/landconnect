<?php

// no billing required on Statistics
define('BILLING_NO_INIT', true);

require_once("../models/config.php");
require_once("classes/stats_init.php");

$pageView = new StatisticView();
$pageView->displayPage();

?>