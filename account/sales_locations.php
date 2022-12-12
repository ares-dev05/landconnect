<?php
// no billing required here
define('BILLING_NO_INIT', true);

require_once("../models/config.php");

// make sure an admin is logged in to view this page
if (!isUserLoggedIn()){
    header('Location: /account');
    exit();
}

global $loggedInUser;
if (!$loggedInUser || !isGlobalAdmin($loggedInUser->user_id)) {
    header('Location: /account');
    exit();
}

// load current builder
$currentBuilderId = isset($_GET['builder']) ? intval($_GET['builder']) : 1;

// process actions
if (isset($_GET['delete'])) {
    deleteSalesLocation(intval($_GET['delete']));
}
if (isset($_POST['new_location'])) {
    addSalesLocation($currentBuilderId, $_POST['new_location']);
}

// prepare page data
$currentLocations = fetchBuilderSalesLocations($currentBuilderId);
$currentBuilder   = null;
$currentPage      = '/account/sales_locations.php?builder='.$currentBuilderId;

// helper function for listing all the builder companies in the DB
function listCompanies() {
    global $currentBuilderId, $currentBuilder;

    $db       = pdoConnect();
    $stmt     = $db->prepare("SELECT * FROM companies WHERE `type`='builder'");
    $builders = [];

    if ($stmt->execute()) {
        while (($obj = $stmt->fetchObject()) != false) {
            $builders[$obj->id] = $obj;

            if ($obj->id == $currentBuilderId) {
                $currentBuilder = $obj;
            }
        }
    }

    // prepare the select
    $items = [];
    foreach ($builders as $builder) {
        $builderName = $builder->name;
        if ($builder->id==$currentBuilderId) {
            $items []= "
            <a class='btn btn-primary' href='/account/sales_locations.php?builder={$builder->id}'>
                {$builderName}
            </a> ";
        }   else {
            $items []= "
            <a class='btn btn-default' href='/account/sales_locations.php?builder={$builder->id}'>
                {$builderName}
            </a> ";
        }
    }

    return implode("", $items);
}

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="author" content="">

      <title>Landconnect</title>

    <?php require_once("includes.php");  ?>
 
    <!-- Page Specific Plugins -->
    <link rel="stylesheet" href="../css/bootstrap-switch.min.css" type="text/css" />
  
    <script src="../js/date.min.js"></script>
    <script src="../js/handlebars-v1.2.0.js"></script> 
    <script src="../js/bootstrap-switch.min.js"></script>
	<script src="../js/jquery.tablesorter.js"></script>
	<script src="../js/tables.js"></script>    
    <script src="../js/widget-users.js?v=1"></script>
      <style>
          span.delete {
              font-size: 16px;
              color: #999999;
          }
          .btn {
              margin-bottom:10px;
          }
      </style>
  </head>

  <body>

    <div id="wrapper">

      <!-- Sidebar -->
      <nav class="app-nav" role="navigation">
      </nav>

      <div id="page-wrapper" class="user-manager">
      <div class="container-fluid">
      <div class="page-header">
		  <h1>Sales Locations</h1>
		</div>
	  	<div class="row">
            <div class="col-sm-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <?php
                            echo listCompanies();
                        ?>
                        <div class="clearfix"></div>
                    </div>
                    <div class="panel-body" style="border-bottom: 1px solid #ddd">
                        <b>Current Sales Locations</b><br/>
                        <?php
                            foreach ($currentLocations as $location) {
                                $id   = $location["id"];
                                $name = $location["name"];
                                $href = $currentPage . "&delete=" . $id;

                                echo "<a href='{$href}' class='btn btn-default'>
                                    {$name} <span class='delete'>X</span>
                                </a>";
                            }

                            if (!sizeof($currentLocations)) {
                                echo "This builder has no sales locations yet";
                            }
                        ?>
                    </div>
                    <div class="panel-body">
                        <b>Add New Location</b>
                        <form method="post" target="_self" action="<?php echo $currentPage; ?>">
                            <input type="text" name="new_location" class="form-control" style="width:300px; display:inline;" placeholder="Location Name" />
                            <button type="submit" class="btn btn-primary">Add</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
          <div id='widget-users' class="col-lg-12">          

          </div>
        </div><!-- /.row -->
        
		</div>  
      </div><!-- /#page-wrapper -->

    </div><!-- /#wrapper -->
  </body>

  <script>
      $(document).ready(function() {
          // Load the header
          $('.app-nav').load('/account/header.php?area=manage', function() {
              $('.navitem-sale-locations').addClass('active');
          });
      });
  </script>

</html>
