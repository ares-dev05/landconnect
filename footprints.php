<?php

// don't load any billing on Footprints; We'll handle billing issues manually with the builders
define('BILLING_NO_INIT', true);

require_once("./models/config.php");

// User must be logged in
if (!isUserLoggedIn()){
  addAlert("danger", "You must be logged in to access the application.");
  header("Location: login.php");
  exit();
}

/**
 * @var $loggedInUser loggedInUser
 */
global $loggedInUser;

// Fetch Lotmix/Sitings permissions
$stmt = pdoConnect()->prepare(
    "SELECT * FROM lotmix_state_settings
    WHERE company_id=:company_id AND state_id=:state_id"
);

if ($stmt &&
    $stmt->execute(array(
        ":company_id" => $loggedInUser->company_id,
        ":state_id"   => $loggedInUser->state_id
    ))) {
    $lotmixStateSettings = $stmt->fetchObject();
    if ($lotmixStateSettings) {
        if ($lotmixStateSettings->has_siting_access) {
            header("Location: /sitings/drawer/reference-plan");
            exit();
        }
    }
}

// user must have landconnect access to continue
confirmUserHasLandconnectAccess();

// Redirect to password page if this is the first login
if($loggedInUser->ll==0){
	header("Location: account/change_password_first.php");
	exit();
}

// get details on the logged in user's

// fetch url / protocol to build the base URL for API service calls
$serviceBase	= "https://".$_SERVER['HTTP_HOST'];

// register the S3 stream wrapper
getS3Client()->registerStreamWrapper();

/* enable envelopes beta for a specific test account */
// if ( $loggedInUser->builder_id=="Default" || $loggedInUser->builder_id=="AdminBatch" ||
if ( $loggedInUser->user_id==1693 ) {
    $swfAppName = "app/Footprints_env.swf";
}	else {
	$swfAppName = "app/Footprints_v3.swf";
}

// @INFO: Henley/Plantation run a different Footprints version that supports their XML data format
if ($loggedInUser->builder_id=="Henley" || $loggedInUser->builder_id=="Plantation"){
	$swfAppName = "app/Footprints_hen.swf";
}

// add cache-control parameter so the latest updates are always loaded
$swfAppName = S3_BUCKET_PATH . $swfAppName .
	"?version=".filemtime("s3://".STORAGE_BUCKET."/".$swfAppName);

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "//www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="description" content="">
	<meta name="author" content="">

	<title>Landconnect</title>

	<!-- Favicon -->
	<link rel="icon" type="image/x-icon" href="css/favicon.ico" />

	<!-- Core CSS -->
	<link href="//fonts.googleapis.com/css?family=Roboto:400,400i,700,700i" rel="stylesheet">
	<link rel="stylesheet" href="css/bootstrap.css">
	<link rel="stylesheet" href="css/admin.css?v=1">
	<link rel="stylesheet" href="css/sb-admin.css?v=3">
	<link rel="stylesheet" href="css/font-awesome.min.css">

	<!-- Core JavaScript -->
	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
	<script src="js/bootstrap.js"></script>
	<script src="js/userfrosting.js"></script>

	<!-- Page Specific Plugins -->
	<link rel="stylesheet" href="css/bootstrap-switch.min.css" type="text/css" />

<script src="js/consolefix.js"></script>

<link href="/css/logins/<?php echo grab_subdomain(); ?>.css" rel="stylesheet">

<style type="text/css" media="screen">

body {
	margin: 0;
	padding: 0;
	border: 0;
	font-size: 100%;
    font-family: Montserrat, sans-serif;
	vertical-align: baseline;
	line-height: 1;
}

html { height:100%; }

object{
	/* position: absolute; */
	outline: none;
}

#wrapper {
	z-index: 0;
	width:100%;
	height:100%;
}

#discovery-modal {
    position: absolute;
    top: 0;
    left: 0;

    display: none;
    height: 100vh;
    width: 100%;

    padding-top: 110px;
    overflow: hidden;
    z-index: 100;
}

iframe {
    height: 100%;
    width: 100%;
    margin: 0;
    border: none;
}

#flashContent {
    height: 100%;
    width: 100%;
}

.activateFlash {
    background-color: white;
    color: black;
    font-family: Montserrat, sans-serif;
    font-size: 15px;
    letter-spacing: 1px;
    width: 100%;
    height: 100%;
    text-align: center;
    display: table;
}

.activateFlash .content {
    display:table-cell;
    vertical-align: middle;
}

.activateFlash .button {
    margin-top: 10px;
    display: inline-block;
    height: 50px;
    line-height: 50px;
    padding: 0;
    width: 222px;
    border-radius: 60px;
    font-family: Montserrat, sans-serif;
    font-weight: 600;
    font-size: 12px;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: #fff;
    background-color: #2B8CFF;
    border-color: #2B8CFF;
    text-align: center;
}

</style>

<meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
<title>Landconnect</title>

<script type="text/javascript" src="swfobject.js"></script><script type="text/javascript">
	// Functions needed for calling Flex ExternalInterface
	function footprints()  {
		var movieName="Footprints";
		if (navigator.appName.indexOf("Microsoft") != -1)  {
			return window[movieName];
		}
		else  {
			return document[movieName];
		}
	}

    function fpLoad()    { footprints().loadSession(); }
    function fpSave()    { footprints().saveSession(); }
    function fpRestart() { footprints().resetSession(); }

    <?php if ($loggedInUser->chas_discovery): ?>
	function launchDiscovery(width, depth, fitArea) {
		$("<iframe frameborder='0' allowtransparency='true' " +
			"src='<?php echo LANDSPOT_ROOT; ?>/footprints?depth="+depth+"&width="+width+"&fit="+fitArea+"' />"
		).appendTo("#discovery-modal");
		$("#discovery-modal").css('display', 'flex');
	}
	function closeDiscovery() {
		$("#discovery-modal").css('display', 'none').html("");
	}

	window.addEventListener('message', function(event) {
		if ( (~event.origin.indexOf("<?php echo LANDSPOT_DOMAIN; ?>")) ) {
			var data=event.data;
			if ( data=="closeDiscovery" ) {
				closeDiscovery();
			}	else
			if ( typeof data == "object" && data.hasOwnProperty("event") ) {
				switch ( data.event ) {
					case "closeDiscovery":
						closeDiscovery();
						break;
					case "selectHouse":
						footprints().selectHouse(data.houseName);
						closeDiscovery();
						break;
				}
			}
		}
	});
<?php endif; ?>

	var swfVersionStr = "10.2.153";
	var xiSwfUrlStr = "";
	var flashvars = {};

    <?php
        // default state = Victoria
        $stateId = 7;
        if ( isset($loggedInUser->state_id) ) {
            $stateId = $loggedInUser->state_id;
        }

        // default theme = Landconnect (Green)
        $themeId = 1;
        if ( isset($loggedInUser->theme_id) ) {
            if ( is_numeric($loggedInUser->theme_id) ) {
                $themeId = $loggedInUser->theme_id;
            }   else {
                try {
                    $themeInfo = json_decode($loggedInUser->theme_id, true);
                    if ( isset($themeInfo[$stateId]) && is_numeric($themeInfo[$stateId]) ) {
                        $themeId = $themeInfo[$stateId];
                    }
                }   catch( Exception $e ) {
                }
            }
        }

        // User Properties; @TODO: fetch these in the user object instead of here.
        $discovery_tooltip  = 0;
        $stmt = pdoConnect()->prepare("SELECT * FROM user_tutorials WHERE uid=:uid");
        if ($stmt->execute(array("uid"=>$loggedInUser->user_id)) && $stmt->rowCount()>0) {
            while (($obj=$stmt->fetchObject()) != NULL) {
                if ($obj->tutorial=='tip.discovery') {
                    $discovery_tooltip = $obj->state;
                }
            }
        }

        // Envelopes
		$has_envelope		= 0;
		$envelope_admin		= 0;
		$envelope_managed	= 0;

		// company-wide envelopes access
		include_once("./portal/classes/PtCompanyInfo.php");
		$company			= new PtCompanyInfo( $loggedInUser->company_id );
		if ( $company && $company->chas_envelopes > 0 ) {
			$has_envelope		= 1;
			$envelope_admin		= $loggedInUser->is_envelope_admin ? 1 : 0;
			$envelope_managed	= ( $company->chas_envelopes == PtCompanyInfo::ENVELOPES_MANAGED ) ? 1 : 0;
		}
    ?>

    <?php // User Stats ?>
	flashvars.companyKey		= "<?php echo $loggedInUser->ckey; ?>";
	flashvars.userId			=  <?php echo $loggedInUser->user_id; ?>;
	flashvars.userName			= "<?php echo $loggedInUser->displayname; ?>";
	flashvars.userStateId		=  <?php echo $stateId; ?>;
	flashvars.has_multihouse	=  <?php echo isset($loggedInUser->has_multihouse) ? $loggedInUser->has_multihouse : 0; ?>;
	flashvars.has_exclusive 	=  <?php echo isset($loggedInUser->has_exclusive) ? $loggedInUser->has_exclusive : 0; ?>;
	flashvars.themeId			=  <?php echo $themeId; ?>;
	flashvars.builderId			= '<?php echo $loggedInUser->builder_id; ?>';

	<?php // Envelope Permissions ?>
	flashvars.has_envelope		= <?php echo $has_envelope; ?>;
	flashvars.envelope_managed	= <?php echo $envelope_managed; ?>;
	flashvars.envelope_admin	= <?php echo $envelope_admin; ?>;

	<?php // Discovery Permissions ?>
	flashvars.has_discovery 	= <?php echo (isset($loggedInUser->chas_discovery)&&$loggedInUser->chas_discovery) ? $loggedInUser->chas_discovery : 0; ?>;

	<?php // User Properties ?>
    flashvars.discovery_tooltip = <?php echo $discovery_tooltip ? $discovery_tooltip : '0'; ?>;
    flashvars.inapp_menu        = 0;

	// API Endpoints
	flashvars.serviceDomain		= "<?php echo $serviceBase; ?>/lcapi";
	flashvars.pdfApiUrl			= "<?php echo $serviceBase; ?>/scripts/pdfApi/";

	// flash object parameters
	var params = {};
	params.quality = "high";
	params.play = "true";
	params.loop = "true";
	params.wmode = "window";
	params.scale = "showall";
	params.menu = "true";
	params.devicefont = "false";
	params.salign = "";
	params.allowscriptaccess = "always";

	// parameters for the 'flashContent' DIV. These are needed because swfobject replaces the div with a new one
	var attributes = {};
	attributes.id = "Footprints";
	attributes.name = "Footprints";
	attributes.align = "middle";
	// the width, height and position attributes are needed for proper displaying in IE9 and IE10
	attributes.style = "width: 100%; height: 100%; ";	// display:block;

	swfobject.embedSWF(
		"<?php echo $swfAppName; ?>",
		"flashContent",
		"100%",
		"100%",
		swfVersionStr,
		xiSwfUrlStr,
		flashvars,
		params,
		attributes
	);
</script><!-- SWFObject's dynamic embed method replaces this alternative HTML content for Flash content when enough JavaScript and Flash plug-in support is available. -->
</head>

<body>
	<div id="wrapper">
		<!-- Sidebar -->
		<nav class="app-nav" role="navigation">
		</nav>

        <div id="page-wrapper">
            <div id="flashContent">
                <div class="activateFlash">
                    <div class="content">
                        To run Footprints you need to<br/>
                        <a href="//www.adobe.com/go/getflash" class="button">
                            Activate Flash
                        </a>
                    </div>
                </div>
            </div>
        </div>
	</div><!-- /#wrapper -->

	<div id="discovery-modal">
	</div>

    <?php
        // load chat
        include_once(__DIR__."/forms/chat.php")
    ?>
</body>

<script>
	$(document).ready(function() {
		// Load the header
		$('.app-nav').load("/account/header.php?area=sitingsApp&FP=1", function() {
			$('.navitem-footprints').addClass('active');
		});
	});
</script>

</html>