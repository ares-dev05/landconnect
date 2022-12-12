<?php

$inApp = false;
if (isset($_GET['FP'])) {
    $inApp = true;
}

//
include('../models/config.php');

// User must be logged in
if (!isUserLoggedIn()){
  addAlert("danger", "You must be logged in to access the account page.");
  header("Location: ../login.php");
  exit();
}

defined('AREA_SITINGS_APP') or define('AREA_SITINGS_APP', 'sitingsApp');
defined('AREA_SITINGS'  ) or define('AREA_SITINGS'  , 'sitings'  );
defined('AREA_MANAGE' ) or define('AREA_MANAGE' , 'manage' );
defined('AREA_PORTAL' ) or define('AREA_PORTAL' , 'portal' );

if (isset($_GET['area'])) {
    $currentArea = $_GET['area'];
}   else {
    $currentArea = AREA_SITINGS_APP;
}

global $loggedInUser, $billingAccount, $websiteName;

if (!isset($loggedInUser)) {
    exit;
}

$hasNewSitings  = false;
$hasLotmix      = false;
// Fetch Lotmix/Sitings permissions
$stmt = pdoConnect()->prepare("
    SELECT * FROM lotmix_state_settings
    WHERE company_id=:company_id AND state_id=:state_id
");

if ($stmt &&
    $stmt->execute(array(
        ":company_id" => $loggedInUser->company_id,
        ":state_id"   => $loggedInUser->state_id
    ))) {
    $lotmixStateSettings = $stmt->fetchObject();
    if ($lotmixStateSettings) {
        $hasLotmix     = $lotmixStateSettings->has_lotmix;
        $hasNewSitings = $lotmixStateSettings->has_siting_access;
    }
}

$hooks = array(
    /////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // User info
    "#USERNAME#"                => $loggedInUser->username,
    "#WEBSITENAME#"             => $websiteName,
    "#CHAT_ICON#"                => '',

    /////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Main Menus
    '#AREA_LANDSPOT#'           => '',
    '#AREA_SITINGS#'            => '',
    '#AREA_MANAGEMENT#'         => '',
    '#AREA_MYCLIENTS#'          => '',
    '#AREA_PORTAL#'             => '',

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Secondary Menus
    '#MENU_SECONDARY#'          => '',
    '#COMPANY_LOGO#'            => ''
);

/** Special case for root account
if ($loggedInUser->user_id == $master_account){
    $hooks['#HEADERMESSAGE#'] = "<span class='navbar-center navbar-brand'>YOU ARE CURRENTLY LOGGED IN AS ROOT USER</span>";
}
*/

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// 0. Link Building Utils

function mainLink($url, $name, $active=false) {
    return '
        <li class="nav-item">
            <a '.($active?'class="active"':'').' href="'.$url.'" title="'.$name.'">'.$name.'</a>
        </li>';
}

function getPortalUrl() {
    global $loggedInUser;
    if ($loggedInUser->has_portal_access) {
        // for Henley, use their portal management
        include_once("../portal-henley/classes/PmUserInfo.php");
        if (PmUserInfo::isUserAllowed($loggedInUser)) {
            return '/portal-henley/index.php';
        }   else {
            return '/portal/index.php';
        }
    }
    return '';
}

function getUserManagementUrl() {
    return '/account/users.php';
}

function getBillingManagementUrl() {
    return '/billing';
}

function getStatisticsDropdown($target="") {
    global $loggedInUser;

    if (isGlobalAdmin($loggedInUser->user_id)) {
        $aggregate = '
                <li class="nav-item">
                    <a class="navitem-aggregate-s" href="/statistics/index.php?page=aggregate" '.$target.'>
                         Aggregated by Builder
                    </a>
                </li>';
    }   else {
        $aggregate = '';
    }

    return '
        <li class="nav-item dropdown">
            <a class="nav-item-statistics" onclick="event.preventDefault();" href="/statistics">
                Statistics
                <i class="landspot-icon angle-down" aria-hidden="true"></i>
            </a>

            <ul class="dropdown-content">
                <li class="nav-item">
                    <a class="navitem-pdfexport-s" href="/statistics/index.php?page=pdfexport" '.$target.'>
                        PDF Exports
                    </a>
                </li>
                <li class="nav-item">
                    <a class="navitem-session-s" href="/statistics/index.php?page=session" '.$target.'>
                        Saved Sessions
                    </a>
                </li>
                <li class="nav-item">
                    <a class="navitem-house-s" href="/statistics/index.php?page=house" '.$target.'>
                        House Usage
                    </a>
                </li>'.
                $aggregate
                .'
            </ul>
        </li>';
}

// 0. Chat for Sales Consultants

if (hasChatAccess()) {
    $hooks['#CHAT_ICON#'] = '
        <li id="minimized-chat" class="nav-item messenger">
            <a href="#" id="chaticon" onclick="toggleClick()">
                <i class="landspot-icon comment"></i>
                <span class="counter">0</span>
            </a>
        </li>
    ';
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// 1. Build Main Area Options

// show landspot for companies that have it
if ($loggedInUser->chas_discovery) {
    if ($loggedInUser->chas_estates_access) {
        $hooks['#AREA_LANDSPOT#'] = mainLink(
            LANDSPOT_ROOT."/landspot/my-estates",
            "Landspot",
            false
        );
    }   else {
        $hooks['#AREA_LANDSPOT#'] = mainLink(
            LANDSPOT_ROOT."/discovery",
            "Discovery",
            false
        );
    }
}

// show Sitings for companies that have it (i.e. everyone on landconnect.com.au)
if (true || $loggedInUser->chas_footprints) {
    $hooks['#AREA_SITINGS#'] = mainLink(
        $hasNewSitings ? '/sitings/drawer/reference-plan' : '/footprints.php',
        'Sitings',
        AREA_SITINGS_APP==$currentArea || AREA_SITINGS==$currentArea
    );
}

// Include the Plan Portal for users with access to it
if ($loggedInUser->has_portal_access || isGlobalAdmin($loggedInUser->user_id)) {
    $hooks['#AREA_PORTAL#'] = mainLink(
        getPortalUrl(),
        'Plan Portal',
        AREA_PORTAL==$currentArea
    );
}

// Include Lotmix, if enabled
if ($hasLotmix) {
    $hooks['#AREA_MYCLIENTS#'] = mainLink(
        LANDSPOT_ROOT."/landspot/my-clients",
        "My Clients",
        false
    );
}

// Management
if (userInGroup($loggedInUser->user_id, BUILDER_ADMINS_GROUP) ||
    $loggedInUser->billing_access_level==2 ||
    $loggedInUser->is_discovery_manager ||
    isGlobalAdmin($loggedInUser->user_id)
) {
    if (userInGroup($loggedInUser->user_id, BUILDER_ADMINS_GROUP) || isGlobalAdmin($loggedInUser->user_id)) {
        // go to user management otherwise
        $hooks['#AREA_MANAGEMENT#'] = mainLink(
            getUserManagementUrl(),
            'Management',
            AREA_MANAGE == $currentArea
        );
    }   else if ($loggedInUser->billing_access_level==2) {
        // go to portal directly for users that have access to it
        $hooks['#AREA_MANAGEMENT#'] = mainLink(
            getBillingManagementUrl(),
            'Management',
            AREA_MANAGE==$currentArea
        );
    }   else {
        // go to user management otherwise
        $hooks['#AREA_MANAGEMENT#'] = mainLink(
            LANDSPOT_ROOT.'/manager',
            'Management',
            AREA_MANAGE==$currentArea
        );
    }
}


/////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// 2. Build Secondary Menus depending on current Site Area

switch ($currentArea) {
    // options for Footprints main page
    case AREA_SITINGS_APP:
        // start the Sitings menu;
        $hooks['#MENU_SECONDARY#'] = '
        <li class="nav-item">
            <a class="active" onclick="event.preventDefault(); fpRestart();" href="#">
                New Siting
            </a>
        </li>
        <li class="nav-item">
            <a onclick="event.preventDefault(); fpLoad();" href="#">
                Load
            </a>
        </li>
        <li class="nav-item">
            <a onclick="event.preventDefault(); fpSave();" href="#">
                Save
            </a>
        </li>';

        // Include the Statistics Dropdown for Builder Managers
        if (userInGroup($loggedInUser->user_id, BUILDER_ADMINS_GROUP) || isGlobalAdmin($loggedInUser->user_id)) {
            $hooks['#MENU_SECONDARY#'] .= getStatisticsDropdown();
        }
        break;

    // options for Statistics page
    case AREA_SITINGS:
        // still on Sitings, main menu, but without Load/Save options
        $hooks['#MENU_SECONDARY#'] = '
        <li class="nav-item">
            <a class="" href="/footprints.php">
                New Siting
            </a>
        </li>';

        // Include the Statistics Dropdown for Builder Managers
        if (userInGroup($loggedInUser->user_id, BUILDER_ADMINS_GROUP)) {
            $hooks['#MENU_SECONDARY#'] .= getStatisticsDropdown();
        }
        break;

    case AREA_PORTAL:
        $hooks['#MENU_SECONDARY#'] = '
        <li class="nav-item">
            <a class="" href="'.getPortalUrl().'">
                Floorplans
            </a>
        </li>';
        break;

    // options for Management
    case AREA_MANAGE:
        // User Manager
        if ( userInGroup($loggedInUser->user_id, BUILDER_ADMINS_GROUP) || isGlobalAdmin($loggedInUser->user_id)) {
            $hooks['#MENU_SECONDARY#'] .= '<li class="nav-item"><a class="navitem-users" href="/account/users.php">User Manager</a></li>';
        }
        // Sales Locations
        if (isGlobalAdmin($loggedInUser->user_id)) {
            $hooks['#MENU_SECONDARY#'] .= "<li class='nav-item'><a class='navitem-sale-locations' href='/account/sales_locations.php'>Sales Locations</a></li>";
        }
        // Discovery Manager
        if (userInGroup($loggedInUser->user_id, BUILDER_ADMINS_GROUP) || $loggedInUser->is_discovery_manager) {
            $hooks['#MENU_SECONDARY#'] .= '<li class="nav-item"><a class="navitem-discovery-manager" href="'.LANDSPOT_ROOT.'/manager">Discovery Manager</a></li>';
        }
        // PDF Manager
        if (userInGroup($loggedInUser->user_id, BUILDER_ADMINS_GROUP) && $loggedInUser->chas_estates_access) {
            $hooks['#MENU_SECONDARY#'] .= '<li class="nav-item"><a class="navitem-pdf-manager" href="'.LANDSPOT_ROOT.'/landspot/pdf-manager/company/'.$loggedInUser->company_id.'">PDF Manager</a></li>';
        }
        // Billing
        if ($billingAccount && $billingAccount->getUserRole()->canManageBilling()) {
            $hooks['#MENU_SECONDARY#'] .= "<li class='nav-item'><a class='navitem-billing' href='/billing/index.php'>Billing</a></li>";
        }

        // Root Admin
        if (isGlobalAdmin($loggedInUser->user_id)) {
            $hooks['#MENU_SECONDARY#'] .= "<li class='nav-item'><a class='navitem-settings' href='/account/site_settings.php'>Site Settings</a></li>";
        }

        break;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// 3. Logo / Colors

// Default Logo/Color
$builderLogo  = S3_LANDSPOT_BUCKET_PATH . 'company_logos/landconnect-logo.png';
$builderColor = '#2B8CFF';
$builderName  = 'Landconnect';

// Fetch the builder's Logo and Color
$stmt = pdoConnect()->prepare(
"SELECT companies.expanded_logo_path, companies.name, color FROM companies
INNER JOIN theme_colors ON theme_colors.tid=companies.theme_id
WHERE companies.id=:company_id AND theme_colors.name=:theme_color"
);

if ( $stmt->execute(array(
        ":company_id"=>$loggedInUser->company_id,
        ":theme_color"=>'color_class_2')
    ) ) {
    $builder = $stmt->fetch(PDO::FETCH_OBJ);
    if ( $builder ) {
        $builderLogo  = S3_LANDSPOT_BUCKET_PATH . $builder->expanded_logo_path;
        $builderColor = "#".str_pad(dechex($builder->color), 6, '0', STR_PAD_LEFT);
        $builderName  = $builder->name;
    }
}

// replace the company logo
$hooks['#COMPANY_LOGO#'] = '<img src="'.$builderLogo.'" alt="'.$builderName.'">';

/*
.navbar-inverse .submenu .active > a:hover {
    border-right: 0px solid {$builderColor};
}
.navbar-inverse .navbar-nav > .active > a:after,
.navbar-inverse .navbar-nav > .active > a:hover:after,
.navbar-inverse .submenu .active > a:after,
.navbar-inverse .submenu .active > a:hover:after {
    border-right: 9px solid {$builderColor};
}

.navbar-inverse .navbar-nav > li > a:hover, .navbar-inverse .navbar-nav > li > a:focus {
    border-right: 5px solid {$builderColor};
}
*/

// override the default colors
echo "
<style type=\"text/css\" media=\"screen\">

.btn-success {
    background-color: {$builderColor};
    border-color: {$builderColor};
}

.panel-green {
    border-color: {$builderColor};
}

.panel-green > .panel-heading {
    border-color: {$builderColor};
    background-color: {$builderColor};
}

.panel-green > a {
    color: {$builderColor};
}

.fpLinks a {
    background: {$builderColor};
}

nav.app-nav a.active,
nav.app-nav a:hover {
    color: {$builderColor};
}

nav.app-nav>ul.nav-items .nav-item>a.active {
    border-bottom: 2px solid {$builderColor};
}

</style>";


echo fetchUserMenu($loggedInUser->user_id, $hooks);

?>