<?php

include_once(__DIR__."/../models/config.php");

if ( !isUserLoggedIn() ) {
    // redirect to login page
    header('Location: /login.php');
    exit;
}

/* otherwise, fetch the style for the currently logged-in user
no longer used
global $loggedInUser;
$styleMap = array(
    "Simonds" => "simonds.css",
    "Burbank" => "burbank.css",
    "PorterDavis" => "porterdavis.css",
    "SherridonHomes" => "sherridon.css",
    "BoldProperties" => "bold.css",
    "OrbitHomes" => "orbit.css"
);

if ( isset($styleMap[$loggedInUser->builder_id]) ) {
    $cssPath = $styleMap[$loggedInUser->builder_id];
} */
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="initial-scale=1, maximum-scale=1">
    <title>Footprints User Guide</title>

    <!-- Bootstrap -->
    <link href="css/bootstrap.css" rel="stylesheet">
    <link href="css/bootstrap-theme.css" rel="stylesheet">
    <link href="css/docs-theme.css" rel="stylesheet">
    <link href="css/override.css" rel="stylesheet">
    <link href="css/bootstrap-lightbox.css" rel="stylesheet">
    <link href="css/bootstrap-magnify.css" rel="stylesheet">

    <?php
    /*if ( isset($cssPath) ) {
        echo '<link href="css/'.$cssPath.'" rel="stylesheet">';
    }*/
    ?>

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->

<style>
    header .logo {
        width: 160px;
        padding-top:15px;
    }

    header .logo img {
        display: block;
    }
</style>
</head>
<body role="document">


<!-- NAV -->
<header class="navbar navbar-static-top bs-docs-nav" id="top" role="banner">
    <div class="container">
        <div class="navbar-header">
            <div class="logo">
                <img src="/graphics/logo.png" alt="Welcome to Landconnect">
            </div>
        </div>
        <nav class="collapse navbar-collapse bs-navbar-collapse" role="navigation">
            <ul class="nav navbar-nav navbar-right">
                <li><span><a href="#request-support" type="button" class="btn btn-sm btn-danger">Submit Support Request</span></a></li>
            </ul>
        </nav>
    </div>
</header>
<!-- / NAV -->


<!-- HEADER -->
<div class="bs-docs-header" id="content">
    <div class="container">
        <h1>Footprints User Guide</h1>
        <p>An overview of Landconnect Footprints, how to use the application, training examples and support.</p>
    </div>
</div>
<!-- / HEADER -->

<div class="container bs-docs-container" role="main">
    <div class="row">

        <div class="col-md-9" role="main">

            <h1 id="intro" class="page-header">Footprints Overview</h1>


            <p class="lead">Footprints is an online application that enables users to create sitings faster and with greater accuracy than the traditional manual method. This page is a user guide to help you get to know the application.</p>

            <h1 id="interface" class="page-header">The Footprints Interface</h1>

            <img data-toggle="magnify" src="img/interface.png" alt="The Footprints Interface">

            <p>This is the Footprints interface. From start to finish you will be on this screen. The three areas of the application are:</p>

            <ol>
                <li>Main Navigation bar. This contains:</li>
                <ol>
                    <li><span class="label label-default">SAVE</span> and <span class="label label-default">LOAD</span> controls. You can store an in-progress siting at any time by using this function. Sitings are stored for 30 days before being automatically removed</li>
                    <li><span class="label label-default">CLEAR STEP</span> - This button will clear the current step of your siting.</li>
                    <!--<li><span class="label label-default">BILLING</span> - You will find all your invoices and billing details here.</li>-->
                    <li><span class="label label-default">ACCOUNT SETTINGS</span> - Your individual account details can be updated here.</li>
                    <li><span class="label label-default">RESTART</span> - This button will clear the entire current siting.</li>
                    <li><span class="label label-default">CONTACT SUPPORT</span> - Access this user guide or lodge a <a href="#request-support">support request</a></li>
                    <li><span class="label label-default">LOGOUT</span> - Exits Landconnect Footprints</a></li>
                </ol>
                <li>Controls Sidebar. This is where you will find the the tools for each step. The options in this area will change as you progress through the steps of a siting.</li>
                <li>The work area that contains the house and lot. This area contains zoom and rotation tools for the lot and house plan as well as the <span class="label label-default">NEXT</span> and <span class="label label-default">PREVIOUS</span> arrow buttons that allow you to navigate between steps.</li>

            </ol>

            <p class=""><span class="label label-info">Tip</span> Instead of clicking and dragging the rotation handle you can enter a value in the small grey box beside the slider the get precise control over the viewing angle.</p>

            <p class="">
                <span class="label label-warning">Note</span> The design and overall features of the interface may change from builder to builder.
            </p>

            <h1 id="step1" class="page-header">Step 1. Add Lot Details</h1>

            <p class="lead">In the first step you will input the data for your block of land. Footprints supports curved boundaries as well as metric and imperial measurements.</p>

            <h3 id="pdf-viewer" class="page-header">PDF Viewer</h3>

            <p>If you have a copy of the <abbr title="Plan of Subdivision">POS</abbr> in PDF format you can upload it into the preview window, which will allow you to have an on-screen reference to aid the input of the lot dimensions.</p>
            <p>To do this, click on the <span class="label label-default">UPLOAD PDF</span> button in the top left of the large white preview area. A popup will appear that allows you to upload a PDF file.</p>

            <p>Once uploaded, you can select which page of the <abbr title="Plan of Subdivision">POS</abbr> document you wish to work from.</p>

            <img data-toggle="magnify" src="img/upload-pdf.png" alt="Select a page">

            <p>The page will then appear in the preview area of the sidebar. You can pan by dragging the image and zoom using the controls in the top left</p>

            <p class=""><span class="label label-warning">Note</span> Uploading and converting a PDF can time several seconds, particular with larger files. Even after the progress bar is full, you may need to wait a further 20-30 seconds for it complete loading</p>

            <h3 id="add-boundaries" class="page-header">Add Boundaries</h3>

            <p>With your preview in place, you can now easily enter the details for each border. Start with the top-most boundary, add the angle and length into the first row of the boundary table below the preview window. Then take note of which end of the line the small circle marker appears. This will tell you if you need to go clockwise or counter-clockwise as you make your way through entering each boundary of the lot.</p>

            <p>If your plan has more than four boundaries, click the <span class="label label-default">ADD BOUNDARY</span> button to add more.</p>

            <p>To switch the measurement mode, click the <span class="label label-default">METRIC</span> button to toggle it to <span class="label label-default">IMPERIAL</span>.</p>

            <img data-toggle="magnify" src="img/borders-added.png" alt="Borders Added">

            <p class=""><span class="label label-info">Tip</span> If you enter a boundary but find it is facing the wrong direction, click on the <span class="label label-default">FLIP</span> button (found next to the angle field) to reverse the direction</p>

            <p class=""><span class="label label-info">Tip</span> In certain situations you may want to reverse the direction of the <span class="label label-default">NORTH MARKER</span>. To do this, click on the marker icon in the upper left work area.</p>

            <h3 id="curved-boundaries" class="page-header">Curved Boundaries</h3>

            <p>Many lots feature curved boundaries. By clicking on the boundary type toggle you can change the type of each boundary from <span class="label label-default">STRAIGHT</span> to <span class="label label-default">CURVED</span>.</p>

            <p>In the below example, we have changed boundary 2 of our plan to a curve.</p>

            <img data-toggle="magnify" src="img/curve-added.png" alt="Curves Added">

            <p>Curves require more data to be entered than straight boundaries. In addition the the angle, you will need to enter the values for:</p>

            <ul class="list-unstyled">
                <li><span class="label label-primary">C</span> The Chord of the curve</li>
                <li><span class="label label-primary">R</span> The Radius of the curve</li>
            </ul>

            <p class=""><span class="label label-warning">Note</span> Plans that use imperial measurements may sometimes change the value <span class="label label-primary">C</span> <span class="label label-primary">A</span> & <span class="label label-primary">R</span> represent. If you notice that any of these are written as an angle, enter it into the angle field instead.</p>

            <p class=""><span class="label label-warning">Note</span> Occasionally (most notable on older plans) you may find certain values are missing from the boundary data. In those cases you will need to estimate the values for the curve/boundary. If you get stuck, lodge a <a href="#request-support">support ticket</a> and we will assist you.</p>



            <h1 id="step2" class="page-header">Step 2. Add Lot Elements</h1>

            <p class="lead">
                This step will allow you to add lot elements including easements, envelopes and crossovers.
            </p>

            <h3 id="easements" class="page-header">Types of Easements</h3>

            <p>Footprints allows for three different types of easements to be added to a lot. To add an easement, click on the button for the type you want to add and then click on the boundary you wish to attach it to.</p>

            <img data-toggle="magnify" src="img/easements.png" alt="Easement Types">

            <h4>1. Parallel Easements</h4>

            <p>This is the most common type of easement you will encounter. It is a simple easement that runs parallel to a boundary. To add this type of easement click on the <span class="label label-default">PARALLEL</span> easement button and then click on the boundary which it will run along. Then enter the width of the easement in the easement box that appears in the sidebar</p>

            <h4>2. Angled Easements</h4>

            <p>Angles easements run perpendicular to a boundary and can run through a property. To add this type, click on the <span class="label label-default">ANGLED</span> easement button, then click on the boundary which it will start from. In the sidebar enter the width and angle. The <span class="label label-default">INTERSECT</span> point is how far along the boundary the easement originates. Eg: To start an easement in the middle of a boundary that is 30m wide, the intersect point will be 15m.</p>

            <h4>3. Block Easements</h4>

            <p>Block easements are similar to parallel easements because they are fixed to a boundary but have two sides. Too add a block easement click on the <span class="label label-default">BLOCK</span> easement button and then click on the boundary you wish to attach it to. In the field that appear in the sidebar, enter the width and height of the easement. To change the corner the easement is attached to, click on the <span class="label label-default">FLIP</span> button</p>

            <h3 id="envelopes" class="page-header">Envelopes</h3>

            <p>The envelopes tool allows you to add setback lines to your lot. To use this tool click on the <span class="label label-default">ENVELOPES</span> button and then click on each boundary that requires a setback line. Adjust the width of each line in the sidebar.</p>

            <img data-toggle="magnify" src="img/envelopes.png" alt="Envelopes">

            <!-- <p class=""><span class="label label-warning">Note</span> If you need to add envelope lines to </p> -->

            <h3 id="crossovers" class="page-header">Crossovers</h3>

            <p>To add a crossover graphic click on the <span class="label label-default">CROSSOVER</span> button and then click anywhere in the work area to place it. The icon will snap to the nearest boundary. You can reposition it by clicking and dragging to a new location.</p>

            <img data-toggle="magnify" src="img/crossover.png" alt="Crossover">

            <h1 id="step3" class="page-header">Step 3. Select a House Plan</h1>

            <p>Simply select the range, followed by your floorplan and your chosen facade. You can also select any structural combination as you see fit. </p>

                <img data-toggle="magnify" src="img/select-house.png" alt="Select House">

            <p class="">To mirror the plan click <span class="label label-default">MIRROR PLAN</span></p>

            <h1 id="step4" class="page-header">Step 4. Extend and Reduce</h1>

            <p class="lead">This set of tools will allow you to make customisations to the house design. i.e a garage extension or lengthen the whole house.</p>

            <h3 id="extend" class="page-header">Extend House Plan</h3>

            <p>To make an extension enter in the a the width and height of the extension you wish to make.
                A green bar will appear in the work area, signifying the area that will be extended. Make sure it is wide enough to cover the the walls of the area you wish to extend. If the size is wrong, adjust the <span class="label label-default">WIDTH</span> and <span class="label label-default">HEIGHT</span>. Drag the green bar over the house plan and use the arrows to select the direction you wish for the extension to take place. When in position, click the <span class="label label-default">ON</span> button and the extension will be applied.</p>

            <img data-toggle="magnify" src="img/extend.png" alt="Extend">

            <p class=""><span class="label label-danger">Important</span> For this tool to work correctly, the resize bar must  cover parallel walls. See the graphic below for examples of usage.</p>

            <img data-toggle="magnify" src="img/extend-usage.png" alt="Extend">

            <h3 id="reduce" class="page-header">Reduce House Plan</h3>

            <p>The reduction tool works the same way as the extension feature. Click the <span class="label label-default">REDUCTION</span> button and follow the steps above.</p>

            <h3 id="add-on" class="page-header">Add-Ons</h3>

            <p>This tool allows you to create features not in the house standard options like custom alfrescos. Click on the <span class="label label-default">ADD ON</span> button and enter in your desired size for the custom alfresco. The add-on and two measurements will appear in the work area. These display the distance from each side of the boundary. You can click on either of the measurements and change the value to place the add on precisely.</p>

            <img data-toggle="magnify" src="img/add-on.png" alt="Add On">

            <h1 id="step5" class="page-header">Step 5. Add Measurements</h1>

            <p class="lead">This step gives you precise control over positioning of the house plan, allows you to add measurements and correct alignment of the house and siting for the final output</p>

            <h3 id="measurements" class="page-header">Add Measurements</h3>

            <p>To add a measurement, first select the <span class="label label-default">ADD MEASUREMENT</span> tool. Then click on the boundary you wish to measure from and then click the closest point of the floorplan. The measurement will snap into place. To change a measurement. Click on the number & entered the desired set-back. This will automatically reposition the house.</p>

            <img data-toggle="magnify" src="img/measurements.png" alt="Add Measurements">

            <p class=""><span class="label label-info">Tip</span> You can span a measurement to a corner of a floor plan by holding CTRL after you select your boundary. This will ensure you pinpoint the corner of a floor plan.</p>

            <h3 id="align-wall" class="page-header">Align Wall to Boundary</h3>

            <p>To align a wall to a boundary, select the <span class="label label-default">ALIGN WALL TO BOUNDARY</span> tool, click on the boundary to which you wish the house to align to and then click on the wall. This is step is crucial to use this to ensure the alignment of the house is correct</p>

            <h3 id="align-page" class="page-header">Align Siting to Page</h3>

            <p>To get correct alignment on your PDF output. Click on the <span class="label label-default">ALIGN SITING TO PAGE</span> button. Then click on a boundary that you wish to align to page edge. 4 greyed out arrows will appear. Select which side you wish to align your selected boundary to. Click on the arrow. It will then adjust your siting to be aligned correctly on your PDF output.</p>

            <img data-toggle="magnify" src="img/align-to-page.png" alt="Align To Page">

            <h1 id="step6" class="page-header">Step 6. Export PDF</h1>

            <p class="lead">In this last step you will add the details for the client, including their name, lot address and any modifications that have been done to the house in your siting. You can also select the page settings for the output PDF.</p>

            <img data-toggle="magnify" src="img/export.png" alt="E
xport">

            <p class=""><span class="label label-info">Tip</span> At this step you can quickly select another house to site without needing to enter the lot data again. Click the <span class="label label-default">CHANGE HOUSE</span> button to clear the current plan.</p>

            <h1 id="faq" class="page-header">FAQ</h1>

            <p><span class="label label-primary">Q</span> There appears to be a house type missing? <br>

                <span class="label label-success">A</span> Please kindly send your management an e-mail regarding this and they will get in touch with us.</p>

            <p><span class="label label-primary">Q</span> I click the login button nothing happens? <br>

                <span class="label label-success">A</span>  First, refresh your browser and try again. If this fails, please clear your cache. Close your browser and try again. If it does not work after following these steps, please contact our support. </p>

            <p><span class="label label-primary">Q</span> I am having issues reading a plan of subdivision? <br>

                <span class="label label-success">A</span> Please send us an e-mail at support@landconnect.com.au  and we will happily point you in the right direction.</p>

            <p><span class="label label-primary">Q</span> I print a siting and its not to scale? <br>

                <span class="label label-success">A</span> When printing please ensure you are printing to Actual Size or 100% scale. This is found in your printer dialogue before printing the document. </p>

            <p><span class="label label-primary">Q</span> How do I change my password?<br>

                <span class="label label-success">A</span> On our login page you can click forgot password, enter your details and you will be sent a link to reset your password. This e-mail may go to your spam/junk folder so please check this. </p>

            <p><span class="label label-primary">Q</span> The boundaries on a lot do not appear to matchup. What do I do? <br>

                <span class="label label-success">A</span> Please thoroughly check the lot details and ensure you have all the correct details. If there are no errors, feel free to send it through to us and we will take a look at it for you. </p>

            <p><span class="label label-primary">Q</span> What is the envelope on this block? <br>

                <span class="label label-success">A</span> Please discuss this with management, we do not provide advice on envelopes or actual positioning of a floorplan. </p>

            <p><span class="label label-primary">Q</span> I have an idea for a feature. <br>

                <span class="label label-success">A</span> Great, we love ideas! Feel free to send it through to us and we will take it onboard. </p>

            <h1 id="request-support" class="page-header">Request Support</h1>

            <p class="lead">If you are having trouble accessing Footprints or just need some help using it you can create a support ticket by emailing us at <a href="mailto:support@landconnect.com.au">support@landconnect.com.au</a></p>


            <!-- <p class="lead">If you are having trouble accessing Footprints or just need some help using it you can create a support ticket by filling out the contact form below to create a support ticket. If you need to attach files to your ticket you can email us directly at <a href="mailto:support@landconnect.com.au">support@landconnect.com.au</a></p>

            <form role="form">

                <div class="row">

              <div class="form-group col-xs-6">
                <label for="InputName">Name</label>
                <input type="text" class="form-control" id="InputName" placeholder="Your Name">
              </div>
              <div class="form-group col-xs-6">
                <label for="InputEmail">Email address</label>
                <input type="email" class="form-control" id="InputEmail" placeholder="Enter email">
              </div>

                </div>

              <div class="form-group">
                <label for="InputSubject">Subject</label>
                <input type="text" class="form-control" id="InputSubject" placeholder="Enter email">
              </div>
              <div class="form-group">
                <label for="InputMessage">Your Message</label>
                <textarea class="form-control" id="InputMessage" rows="4"></textarea>
              </div>

              <button type="submit" class="btn btn-default">Submit</button>
            </form> -->

        </div>

        <!-- SIDE NAV -->
        <div class="col-md-3">
            <div class="bs-docs-sidebar hidden-print hidden-xs hidden-sm affix" role="complementary">
                <ul class="nav bs-docs-sidenav">
                    <li role="presentation" class="active"><a href="#intro">Overview</a></li>
                    <li role="presentation"><a href="#interface">The Footprints Interface</a></li>
                    <li role="presentation"><a href="#step1">Step 1. Add Lot Details</a>
                        <ul class="nav">
                            <li><a href="#pdf-viewer">PDF Viewer</a></li>
                            <li><a href="#add-boundaries">Add Boundaries</a></li>
                            <li><a href="#curved-boundaries">Curved Boundaries</a></li>
                        </ul>
                    </li>
                    <li role="presentation"><a href="#step2">Step 2. Add Lot Elements</a>
                        <ul class="nav">
                            <li><a href="#easements">Easements</a></li>
                            <li><a href="#envelopes">Envelopes</a></li>
                            <li><a href="#crossovers">Crossovers</a></li>
                        </ul>
                    </li>
                    <li role="presentation"><a href="#step3">Step 3. Select a House Plan</a></li>
                    <li role="presentation"><a href="#step4">Step 4. Extend And Reduce</a>
                        <ul class="nav">
                            <li><a href="#extend">Extend</a></li>
                            <li><a href="#reduce">Reduce</a></li>
                            <li><a href="#add-on">Add-On</a></li>
                        </ul>
                    </li>
                    <li role="presentation"><a href="#step5">Step 5. Add Measurements</a>
                        <ul class="nav">
                            <li><a href="#measurements">Add Measurements</a></li>
                            <li><a href="#align-wall">Align Wall To Boundary</a></li>
                            <li><a href="#align-page">Align Siting To Page</a></li>
                        </ul>
                    </li>

                    <li role="presentation"><a href="#step6">Step 6. Export PDF</a></li>
                    <li role="presentation"><a href="#faq">FAQ</a></li>

                    <li role="presentation"><a href="#request-support">Request Support</a></li>

                </ul>
                <a class="back-to-top" href="#top">Back to top</a>
            </div>
        </div>



        <!-- / SIDE NAV -->

    </div>
</div>

<footer class="bs-docs-footer footer" role="contentinfo">
    <div class="container">

        <p>Copyright &copy; 2014-2017 Landconnect Pty Ltd. <br>
            <a href="mailto:support@landconnect.com.au">support@landconnect.com.au</a></p>

    </div>
</footer>

<div class="no-access">Access to this page is restricted. To view the Landconnect Footprints User Guide please log in via the application. If you are seeing this page in error, please contact us at <a href="mailto:support@landconnect.com.au">support@landconnect.com.au</a><br></div>

<!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
<!-- Include all compiled plugins (below), or include individual files as needed -->
<script src="js/bootstrap.min.js"></script>
<script src="js/bootstrap-lightbox.js"></script>
<script src="js/bootstrap-magnify.js"></script>
<script>
    $('.affix').affix({
        offset: {
            top: 375,
            bottom: function () {
                return (this.bottom = $('.footer').outerHeight(true))
            }
        }
    })
</script>
<script>
    $('body').scrollspy({ target: '.bs-docs-sidebar' })
</script>

<script>
    $("a[href^='#']").on('click', function(e) {

        // prevent default anchor click behavior
        e.preventDefault();

        // store hash
        var hash = this.hash;

        // animate
        $('html, body').animate({
            scrollTop: $(this.hash).offset().top
        }, 300, function(){

            // when done, add hash to url
            // (default click behaviour)
            window.location.hash = hash;
        });

    });
</script>

</body>
</html>
