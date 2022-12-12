<?php

class StatisticView {

    // different display modes
    const MODE_TABLE    = "table";
    const MODE_CHART    = "dxChart";

    /**
     * @var String type the statistic type; takes values of type StatisticParams:TYPE_
     * @var String $viewMode    table | dxChart
     * @var Array $users
     * @var String $fromDate
     * @var String $toDate
     */
    private $page;
    private $houseName;
    private $viewMode;
    private $users;
    private $fromDate;
    private $toDate;

    private $printMode;

    /**
     * @var Boolean $globalAdmin
     * @var int $cid the ID of the company
     * @var int $state the state ID
     * @var int $companyStates all the states that this company is installed in
     */
    private $globalAdmin;
    private $cid;
    private $state;
    private $companies;
    private $companyStates;
    private $companyHouses;


    public function __construct() {
        // load the page settings
        $this->loadPageSettings();
    }

    /**
     * displayPage
     * output the entire statistics page
     */
    public function displayPage() {
        // first make sure that the user has access to the statistics page
        $this->verifyPermissions();

        // output the HTML head (scripts, stylesheets, etc.)
        $this->getHeader();

        // output the HTML content/body of the page
        $this->getContent();

        // output the STAT scripts
        $this->getPageScript();

        // output the closing tags for the page
        $this->endPage();
    }

    /**
     * loadPageSettings
     * initialize the page settings from the available GET parameters
     */
    private function loadPageSettings()
    {
        // load user status
        global $loggedInUser;
        $this->globalAdmin = isGlobalAdmin($loggedInUser->user_id);

        // default page = StatisticParams::TYPE_SESSION
        $this->page = StatisticParams::TYPE_SESSION;
        // check if we have another page to display
        if ( isset($_GET['page']) ) {
            $pageTypes = array(
                StatisticParams::TYPE_SESSION,
                StatisticParams::TYPE_EXPORT,
                StatisticParams::TYPE_HOUSE_BREAKDOWN,
                StatisticParams::TYPE_HOUSE
            );

            if ( $this->globalAdmin ) {
                // allow aggregate view for the entire company
                array_push( $pageTypes, StatisticParams::TYPE_AGGREGATE );
            }

            if ( in_array($_GET['page'], $pageTypes) ) {
                $this->page = $_GET['page'];
            }
        }

        // default viewMode = TABLE
        if ( isset($_GET['mode']) && $_GET['mode']==self::MODE_CHART )
            $this->viewMode = self::MODE_CHART;
        else
            $this->viewMode = self::MODE_TABLE;

        // load users; default=none selected <=> all users selected
        if ( isset($_GET['users']) && strlen($_GET['users'])>0 ) {
            $this->users = explode(",", $_GET['users']);
        }   else {
            $this->users = array();
        }

        // load the house name, if it exists
        if ( isset($_GET['house']) ) {
            $this->houseName = $_GET['house'];
        }

        if ( isset($_GET['print']) && $_GET['print']=="yes" ) {
            $this->printMode = true;
        }   else {
            $this->printMode = false;
        }

        // load start date; default=start of this year
        $this->fromDate   = $this->getDateString( isset($_GET['from'])?$_GET['from']:null, "yearstart" );
        // load end date; default=end of this year
        $this->toDate     = $this->getDateString( isset($_GET['to'])?$_GET['to']:null, "now" );

        // load current company and state
        if ( $this->globalAdmin ) {
            // global admins can see any stats
            // load all the companies of the app
            $this->companies = fetchAllCompanies();
            // default company = Simonds
            $this->cid   = 2;

            // do we have a valid company passed in through the page parameters?
            if ( isset($_GET['company']) ) {
                $getCid = intval( $_GET['company'] );
                if ( isset($this->companies[$getCid]) ) {
                    // company exists, override default
                    $this->cid = $getCid;
                }
            }
        }   else {
            // builder admins can only see their stats
            $this->cid   = $loggedInUser->company_id;
        }

        // fetch all the states of this company
        $this->companyStates = fetchCompanyStates( $this->cid );

        // set the default state to Victoria
        $this->state = 7;

        // state is passed through as a page parameter
        if ( isset($_GET['state']) ) {
            $this->state = intval( $_GET['state'] );
        }   else {
            // default state = Victoria, if the builder is installed in it
            if ( !isset($this->companyStates[7]) && sizeof($this->companyStates) ) {
                // set it to the first available state
                $this->state = current( array_keys( $this->companyStates ) );
            }
        }

        // if we're in aggregated mode, load info on all the existing houses
        if ( $this->globalAdmin && $this->page == StatisticParams::TYPE_AGGREGATE ) {
            //
            $stats = new StatisticParams(
                StatisticParams::TYPE_HOUSE,
                StatisticParams::AGGR_HOUSE_COMBINED,
                $this->cid,
                $this->state,
                "",
                $this->fromDate,
                $this->toDate,
                true
            );

            // build the houses table
            $this->companyHouses = array();
            foreach ( $stats->getResults() as $row ) {
                array_push( $this->companyHouses, $row["col_index"] );
            }
        }
    }

    private function getDateString( $dateStr, $default, $format="Y-m-d" )
    {
        if ( isset( $dateStr ) && $dateStr != null && ( $validate = DateTime::createFromFormat($format, $dateStr) ) != FALSE ) {
            return $validate->format($format);
        }   else {
            $now = new DateTime();

            if ( $default == "yearstart" ) {
                $now->setDate($now->format("Y"), 1, 1);
                return $now->format($format);
            }   else if ( $default == "now" ) {
                return $now->format($format);
            }   else {
                // @INFO: it's up to the user to make sure that the default date has the correct format
                return $default;
            }
        }
    }

    /**
     * verifyPermissions
     * make sure that a user is logged-in and that he can access this page
     */
    private function verifyPermissions()
    {
        //
        if (!securePage(__FILE__)){
            // Forward to index page
            addAlert("danger", "Whoops, looks like you don't have permission to view that page.");
            header("Location: ../account/index.php");
            exit();
        }
    }

    private function getHeader()
    {
        echo '
<!DOCTYPE html><html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Landconnect</title>

    <!-- Page Specific Plugins -->
	<link rel="stylesheet" href="../css/bootstrap-switch.min.css" type="text/css" />
    <!-- <link rel="stylesheet" href="css/portal.css" type="text/css" /> -->
    ';

        require_once("../account/includes.php");

        echo '
	<script src="../js/date.min.js"></script>
    <script src="../js/handlebars-v1.2.0.js"></script>
    <script src="../js/bootstrap-switch.min.js"></script>
    <script src="../js/jquery.tablesorter.js"></script>
    <script src="../js/tables.js"></script>

    <!-- <script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script> -->
    <script type="text/javascript" src="//ajax.aspnetcdn.com/ajax/globalize/0.1.1/globalize.min.js"></script>
    <!-- <script type="text/javascript" src="//cdn3.devexpress.com/jslib/15.1.3/js/dx.chartjs.js"></script> -->
    <script type="text/javascript" src="../core/assets/js/devexpress-web-14.1/js/dx.chartjs.js"></script>

    <!-- Include selectize -->
    <script src="../js/standalone/selectize.js"></script>
    <link rel="stylesheet" href="../css/selectize.bootstrap2.css">

    <!-- include bootstrap daterange picker -->
    <link rel="stylesheet" type="text/css" media="all" href="../js/daterange/daterangepicker-bs3.css" />
    <script type="text/javascript" src="../js/daterange/moment.min.js"></script>
    <script type="text/javascript" src="../js/daterange/daterangepicker.js"></script>

    <!-- include bootstrap multiselect -->
    <script type="text/javascript" src="../js/bootstrap-multiselect.js"></script>
    <link rel="stylesheet" href="../css/bootstrap-multiselect.css" type="text/css"/>

    <!-- Page Plugin -->
    <script src="js/widget-stats.js"></script>';

        // output the page settings
        $usersJSArr = implode(",", $this->users);

        if (isset($this->houseName)) {
            $houseVar = "var houseName = '{$this->houseName}';";
        }   else {
            $houseVar = "var houseName;";
        }

        if ($this->printMode) {
            echo '
<style type="text/css">
#page-wrapper {
    display: block;
    height: 100%;
    padding-top: 0px;
    overflow: visible;
    margin: 15px;
}
</style>
';
        }

        echo "
<script>
    var page        = '{$this->page}';
    var stateId     = {$this->state};
    var cId         = {$this->cid};
    var viewMode    = '{$this->viewMode}';
    var users       = [{$usersJSArr}];
    var fromDate    = '{$this->fromDate}';
    var toDate      = '{$this->toDate}';
    var printMode   = ".($this->printMode?"true":"false").";
    {$houseVar}
</script>
    ";
        echo '</head>';
    }

    /**
     * getContent
     * output the HTML body of the page
     * @return string
     */
    private function getContent()
    {
        $containers = '
            <div id="stats_1" class="containers" style="height: 500px; width: 100%;">
            <div id="stats_2" class="containers" style="height: 440px; width: 100%;"></div>
        ';

        if ( $this->page == StatisticParams::TYPE_AGGREGATE ) {
            if ( !$this->printMode ) {
                $containers = "
                    Please switch to Print Mode to see the full statistics for the selected builder
                ";
            }   else {
                $containers = '
                    <div id="stats_combined" class="containers" style="height: 500px; width: 100%;">
                        Loading all houses...
                    </div>
                </div>
                <div class="col-lg-12">
                ';
                // we are going to want to display the full statistics; fetch all the houses for this builder
                foreach ( $this->companyHouses as $id => $houseName ) {
                    $containers .= '
                        <div id="stats_'.$id.'" class="containers" style="height: 500px; width: 100%;">
                            Loading for house '.$houseName.'
                        </div>
                    </div>
                    <div class="col-lg-12">
                    ';
                }
            }
        }

        if ( $this->printMode ) {
            // bare mode: no top bar, menu or settings bar
            echo '
<body>
    <div id="wrapper" style="padding-left: 0;">
        <div id="page-wrapper">
            <div class="row" id="stats-content">
                <div class="col-lg-12">
                    '.$containers.'
                </div>
            </div>
        </div><!-- /#page-wrapper -->
    </div><!-- /#wrapper -->
            ';
        }   else {
            echo '
<body>
    <div id="wrapper">
        <!-- Sidebar -->
        <nav class="app-nav" role="navigation">
        </nav>

        <div id="page-wrapper">
            <div class="container-fluid">
                <div class="page-header">
                    <h1>Statistics</h1>
                </div>
                <div class="row">
                  <div id="display-alerts" class="col-lg-12"></div>
                </div>
    
                <div class="row" id="settingsBar">
                    <div class="col-lg-12">
                        ' . $this->getSettingsBar() . '
                    </div>
                </div>
    
                <div class="row" id="stats-content">
                    <div class="col-lg-12">
                        '.$containers.'
                    </div>
                </div>
            </div>
        </div><!-- /#page-wrapper -->
    </div><!-- /#wrapper -->
            ';
        }
    }

    /**
     * getSettingsBar
     * return the settings bar for the statistics page (view mode, date interval, users)
     * @return string
     */
    private function getSettingsBar( )
    {
        // build the company selection if we are a global admin
        $companySelect = "";
        if ( $this->globalAdmin ) {
            $companySelect = "
                <div class='row' style='margin-top:8px;'>
                    <div class='col-sm-2' style='height:29px; padding-top:6px;'>
                        <span class='glyphicon glyphicon-copyright-mark'></span>
                        Builder
                    </div>
                    <div class='col-sm-10'>
                        ".$this->getCompanySelect()."
                    </div>
                </div>
            ";
        }
        // build the state selection as a list of buttons
        $statesSelect = $this->getStateSelect();

        // build a settings panel
        return "
    <div class='row'>
    <div class='col-sm-12'>
         <div class='panel panel-default'>
            <div class='panel-heading'>
                <span class='pull-left'>
                    Settings
                </span>
                <div class='clearfix'></div>
            </div>
            <div class='panel-body'>
                <div class='row'>
                    <div class='col-sm-2' style='height:29px; padding-top:6px;'>
                        <!--<span class='glyphicon glyphicon-blackboard'></span>-->
                        <span class='glyphicon glyphicon-adjust'></span>
                        View As
                    </div>
                    <div class='col-sm-10'>
                        <button type='button' class='btn btn-".($this->viewMode==self::MODE_TABLE?"primary":"link")."' onclick='showAsTable();'>
                            <span class='glyphicon glyphicon-list-alt'></span>
                            Table
                        </button>
                        <button type='button' class='btn btn-".($this->viewMode!=self::MODE_TABLE?"primary":"link")."' onclick='showAsChart();'>
                            <span class='glyphicon glyphicon-stats'></span>
                            Chart
                        </button>

                        <a id='btn-print' class='btn btn-info pull-right' href='#' target='_blank' role='button'>
                            <span class='glyphicon glyphicon-print'></span>
                            Print
                        </a>

                    </div>
                </div>
                {$companySelect}
                <div class='row' style='margin-top:8px;'>
                    <div class='col-sm-2' style='height:29px; padding-top:6px;'>
                        <span class='glyphicon glyphicon-road'></span>
                        State
                    </div>
                    <div class='col-sm-10'>
                        $statesSelect
                    </div>
                </div>

                <div class='row' style='margin-top:8px;'>
                    <div class='col-sm-2' style='height:29px; padding-top:6px;'>
                        <span class='glyphicon glyphicon-dashboard'></span>
                        Date Range
                    </div>
                    <div class='col-sm-10'>
                        <div id='daterange' class='pull-left' style='background: #fff; cursor: pointer; padding: 5px 10px; border: 1px solid #ccc'>
                            <i class='glyphicon glyphicon-calendar'></i>
                            <span></span> <b class='caret'></b>
                        </div>

                        <script type='text/javascript'>
                        $(document).ready(function() {
                            var cb = function(start, end, label) {
                                console.log(start.toISOString(), end.toISOString(), label);
                                $('#daterange span').html(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));

                                fromDate    = start.format('YYYY-MM-DD');
                                toDate      = end.format('YYYY-MM-DD');
                            };

                            var optionSet = {
                                // startDate: moment().subtract(29, 'days'),
                                startDate: moment(fromDate, 'YYYY-MM-DD'),
                                endDate: moment(toDate, 'YYYY-MM-DD'),
                                opens: 'right',
                                ranges: {
                                   'Today': [moment(), moment()],
                                   'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                                   'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                                   'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                                   'This Month': [moment().startOf('month'), moment().endOf('month')],
                                   'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                                   'This Year':  [moment().startOf('year'), moment()],
                                   'Last Year':  [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')]
                                }
                            };

                            // fill the default value
                            $('#daterange span').html(
                                optionSet.startDate.format('MMMM D, YYYY') + ' - ' +
                                optionSet.endDate.format('MMMM D, YYYY')
                            );

                            $('#daterange').daterangepicker(optionSet, cb);
                            $('#daterange').on('apply.daterangepicker', function(ev, picker) {
                                fromDate    = picker.startDate.format('YYYY-MM-DD');
                                toDate      = picker.endDate.format('YYYY-MM-DD');

                                applyFilters();
                            });
                       });
                       </script>
                    </div>
                </div>

                <div class='row' style='margin-top:8px;'>
                    <div class='col-sm-2' style='height:29px; padding-top:6px;'>
                        <span class='fa fa-users'></span>
                        Users
                    </div>
                    <div class='col-sm-10'>
                        <script type='text/javascript'>
                            $(document).ready(function() {
                                $('#selected-users').multiselect({
                                    includeSelectAllOption: true,
                                    onChange: function(option, checked) { },
                                    onDropdownHide: function(event) {
                                        users = $('#selected-users').val();
                                    }
                                });
                            });
                        </script>
                        <select id='selected-users' multiple='multiple'>
                            ".$this->listCompanyUsers()."
                        </select>
                    </div>
                </div>

                <div class='row' style='margin-top:8px;'>
                    <div class='col-sm-2'>
                    </div>
                    <div class='col-sm-10'>
                        <button type='button' class='btn btn-success' onclick='applyFilters();'>
                            <span class='glyphicon glyphicon-ok'></span>
                            Apply
                        </button>
                    </div>
                </div>
            </div>
         </div>
    </div>
    </div>
";
    }

    private function getStateSelect()
    {
        $stateSelect = "";

        foreach ( $this->companyStates as $id => $name )
        {
            if ( $this->state == $id ) {
                $class = "primary";
                $check = "fa-check-square";
            }   else {
                $class = "link";
                $check = "fa-square-o";
            }
            // $class = ( $this->state == $id ) ? "primary" : "link";
            $stateSelect .= "
                <button type='button' class='btn btn-{$class}' onclick='selectState({$id});'>
                    <!--<span class='glyphicon glyphicon-list-alt'></span>-->
                    <!--<span class='glyphicon glyphicon-map-marker'></span>-->
                    <i class='fa {$check}'></i>
                    {$name}
                </button>
            ";
        }

        return $stateSelect;
    }

    private function getCompanySelect()
    {
        $cSelect = "";

        foreach ( $this->companies as $id => $name )
        {
            if ( $this->cid == $id ) {
                $class = "primary";
                $check = "fa-check-square";
            }   else {
                $class = "link";
                $check = "fa-square-o";
            }

            $cSelect .= "
                <button type='button' class='btn btn-{$class}' onclick='selectCompany({$id});'>
                    <i class='fa {$check}'></i>
                    {$name['name']}
                </button>
            ";
        }

        return $cSelect;
    }

    /**
     * listCompanyUsers
     * output the users in this company in the form of a <select>'s options
     * @return string
     */
    private function listCompanyUsers( )
    {
        $preselected    = $this->users;
        $list   = "";
        $query  = "
            SELECT id, display_name
            FROM uf_users
            WHERE company_id={$this->cid} AND state_id={$this->state}
            ORDER BY display_name ASC";

        $db = pdoConnect();
        $res = $db->query($query);

        while ( $obj = $res->fetch(PDO::FETCH_OBJ) ) {
            $id = $obj->id;
            $name = $obj->display_name;
            $selected = ( ( !sizeof($preselected) || in_array($id, $preselected) ) ) ? "selected='selected'" : "";
            $list .= "\t\t<option {$selected} value='{$id}'>$name</option>\n";
        }

        return $list;
    }

    /**
     * getPageScript
     * output the JS code that creates the stats
     */
    private function getPageScript()
    {
        // prepare the settings depending on the page
        $pages = array(
            StatisticParams::TYPE_SESSION => array(
                "stats_1"   => array(
                    "title" => "Saved sessions by date",
                    "type"  => "line",
                    "aggr"  => StatisticParams::AGGR_DATE,
                    "label" => " saved on "
                ),
                "stats_2"   => array(
                    "title" => "Saved sessions by user",
                    "type"  => "stackedBar",
                    "aggr"  => StatisticParams::AGGR_USER,
                    "label" => " saved by "
                )
            ),
            StatisticParams::TYPE_EXPORT => array(
                "stats_1"   => array(
                    "title" => "PDF Exports by Date",
                    "type"  => "line",
                    "aggr"  => StatisticParams::AGGR_DATE,
                    "label" => " exports on "
                ),
                "stats_2"   => array(
                    "title" => "PDF Exports by User",
                    "type"  => "stackedBar",
                    "aggr"  => StatisticParams::AGGR_USER,
                    "label" => " exports by "
                )
            ),
            StatisticParams::TYPE_HOUSE => array(
                "stats_1"   => array(
                    "title" => "House usage in saved sessions",
                    "type"  => "stackedBar",
                    "aggr"  => StatisticParams::AGGR_HOUSE_SESSION,
                    "label" => " session(s) saved with "
                ),
                "stats_2"   => array(
                    "title" => "House usage in PDF Exports",
                    "type"  => "stackedBar",
                    "aggr"  => StatisticParams::AGGR_HOUSE_EXPORT,
                    "label" => " export(s) with "
                )
            ),
            StatisticParams::TYPE_HOUSE_BREAKDOWN => array(
                "stats_1"   => array(
                    "title" => "Usage of Facades and Options for {$this->houseName}",
                    "type"  => "stackedBar",
                    "aggr"  => StatisticParams::AGGR_HOUSE_TYPE,
                    "label" => " houses built with "
                )
            )
        );

        // add builder aggregated statistics, if user is admin
        if ( $this->globalAdmin && $this->page == StatisticParams::TYPE_AGGREGATE ) {
            // construct all the views for the builder
            $builderViews = array();

            // add the overall view for all the house designs
            $builderViews["stats_combined"] = array(
                "title"     => "Overall House Usage Count",
                "type"      => "stackedBar",
                // "aggr"      => StatisticParams::AGGR_HOUSE_COMBINED,
                "aggr"      => StatisticParams::AGGR_HOUSE_EXPORT,
                "label"     => " times for ",
                "stat_type" => StatisticParams::TYPE_HOUSE
            );

            // add the breakdown views for each single house
            foreach ( $this->companyHouses as $id => $houseName ) {
                $builderViews["stats_".$id] = array(
                    "title"     => "Usage of Facades and Options for {$houseName}",
                    "type"      => "stackedBar",
                    "aggr"      => StatisticParams::AGGR_HOUSE_TYPE,
                    "label"     => " houses built with ",
                    "stat_type" => StatisticParams::TYPE_HOUSE_BREAKDOWN_EXPORT,
                    "house_name"=> $houseName
                );
            }

            // add the aggregated settings to the pages table;
            $pages[StatisticParams::TYPE_AGGREGATE] = $builderViews;
        }

        if ( $this->page == StatisticParams::TYPE_HOUSE_BREAKDOWN && !isset($this->houseName) ) {
            // fallback to the houses page
            $this->page = StatisticParams::TYPE_HOUSE;
        }

        $pageScripts = $pages[ $this->page ];
        $pagePieces = explode(".", $this->page);
        $highlightPage = $pagePieces[0];
        echo "
    <script>
        $(document).ready(function() {
            // Load the header
            $('.app-nav').load('../account/header.php?area=sitings', function() {
                $('.navitem-{$highlightPage}').addClass('active');
                $('.nav-item-statistics').addClass('active');
            });

            // create the PRINT url
            $('#btn-print').prop('href', getCookedURL()+'&print=yes');
        ";

        foreach ($pageScripts as $div=>$script) {
            $title  = $script["title"];
            $type   = $script["type"];
            $aggr   = $script["aggr"];
            $label  = $script["label"];

            if ( isset($script["house_name"]) ) {
                $houseParam = ", '" . $script["house_name"] . "'";
            }   elseif ( isset( $this->houseName ) ) {
                $houseParam = ", '" . $this->houseName . "'";
            }   else {
                $houseParam = "";
            }

            /*
            $houseParam = isset( $this->houseName ) ? ",
                '{$this->houseName}'
            "   : ""; */

            if ( isset($script["stat_type"]) ) {
                $statType = $script["stat_type"];
            }   else {
                $statType = $this->page;
            }

            echo "
            statsDisplay(
                '{$this->viewMode}',
                '{$title}',
                '{$div}',
                '{$type}',
                '{$statType}',
                '{$aggr}',
                 {$this->cid},
                 {$this->state},
                '{$label}'{$houseParam}
            );
            ";
        }

        echo "
            alertWidget('display-alerts');
        });
    </script>
        ";
    }

    private function endPage() {
        // load chat
        include_once(__DIR__."/../../forms/chat.php");

        echo "
    </body>
</html>
        ";
    }
}
?>