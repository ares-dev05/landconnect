<?php

class StatisticParams {

    // the type of the statistic
    const TYPE_SESSION          = "session";
    const TYPE_EXPORT           = "pdfexport";
    const TYPE_HOUSE            = "house";
    const TYPE_AGGREGATE        = "aggregate";
    const TYPE_HOUSE_BREAKDOWN  = "house.breakdown";
    const TYPE_HOUSE_BREAKDOWN_EXPORT  = "house.breakdown.export";

    // aggregator: the item that groups multiple rows into one metric
    const AGGR_USER     = "user";
    const AGGR_DATE     = "date";
    const AGGR_HOUSE_SESSION    = "house.session";
    const AGGR_HOUSE_EXPORT     = "house.export";
    const AGGR_HOUSE_COMBINED   = "house.combined";
    const AGGR_HOUSE_TYPE = "house.type";

    // division: obtain divided statistics
    const DIV_NONE      = "none";
    const DIV_STATE     = "state";
    const DIV_MONTH     = "month";

    private $type;
    private $aggregator;
    private $division;

    // the company
    private $cid;
    // the states
    // @TODO: support state-division
    private $state;

    // additional filter
    private $houseName;
    private $fromDate;
    private $fromDateStr;
    private $toDateStr;
    private $toDate;

    private $users;
    private $includeAllUsers;


    // the target table
    private $table;
    // the columns that will be included in this statistic
    // metadata on the statistic structure:
    //    * numeric: the name of the column representing the aggregated numeric statistic
    //          @TODO: support multiple numerics (e.g. when we want to display time-divided stats
    //          @TODO: e.g. graph columns for user actions divided by time intervals (months)
    //          <=> the Y axis on horizontal graphs (X on vertical); the totaled variable in tables
    //    * index
    //    * info
    private $columns;
    // the statistic name; e.g. Saved Sessions, PDF Exports,
    private $typeSettings;
    //
    private $statName;

    // all the queries that are built for this statistic
    private $queries;
    // an array of rows containing the results
    private $results;

    private $resultsArray;


    public function getSettings() { return $this->typeSettings; }
    public function getColumns() { return $this->columns; }
    public function getIndexedResults() { return $this->results; }
    public function getResults() { return $this->resultsArray; }


    public function __construct( $type, $aggregator, $cid, $state, $houseName, $fromDate, $toDate, $users )
    {
        $this->type         = $type;
        $this->aggregator   = $aggregator;
        // @TODO: implement time/state division?
        $this->division     = self::DIV_MONTH;

        $this->cid          = $cid;
        $this->state        = $state;

        // save filters
        $this->houseName    = $houseName;
        $this->fromDateStr  = $fromDate;
        $this->fromDate     = DateTime::createFromFormat("Y-m-d", $fromDate);
        $this->toDateStr    = $toDate;
        $this->toDate       = DateTime::createFromFormat("Y-m-d", $toDate);

        if ( $users === TRUE ) {
            $this->includeAllUsers  = true;
            $this->users            = null;
        }   else {
            $this->includeAllUsers  = false;
            $this->users            = $users;
        }

        $this->setup();
        $this->runQueries();
    }

    private function insertColumn( $name, $content, &$columns ) {
        $content["index"] = sizeof($columns);
        $columns[$name] = $content;
    }

    /**
     * @return DateTime
     */
    private function getStartDate() {
        // get the very first date a row was added for a company/state
        $query = "
            SELECT MIN(DATE(date)) as start
            FROM ".$this->table."
            INNER JOIN uf_users ON ".$this->table.".uid=uf_users.id
            WHERE cid=".$this->cid." AND uf_users.state_id=".$this->state;

        $db = pdoConnect();
        $stmt = $db->prepare($query);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $date = DateTime::createFromFormat("Y-m-d", $row['start']);

        if ( !$date ) {
            $date = new DateTime();
        }

        $date->setDate(
            intval($date->format("Y")),
            intval($date->format("m")),
            1
        );
        $date->setTime( 0, 0, 0 );

        return $date < $this->fromDate ? $this->fromDate : $date;
    }

    private function timeFilterStatement($table) {
        return "{$table}.date>='{$this->fromDateStr}' AND {$table}.date<='{$this->toDateStr}'";
    }

    /**
     * @param Array $columns
     * @param String $table
     */
    private function insertAggregatorColumns( &$columns, $table, $actionName )
    {
        switch ( $this->aggregator ) {
            case self::AGGR_USER:
                $this->insertColumn(
                    "index",
                    array(
                        "type"      => "index",
                        "datatype"  => "text",
                        "name"      => "col_index",
                        "display"   => "User",
                        "fetch"     => "uf_users.display_name as col_index"
                    ),
                    $columns
                );

                if ( $this->division == StatisticParams::DIV_NONE ) {
                    $this->insertColumn(
                        "numeric",
                        array(
                            "type"      => "numeric",
                            "datatype"  => "numeric",
                            "name"      => "col_numeric",
                            "display"   => "Count",
                            "fetch"     => "COUNT({$table}.id) AS col_numeric",
                            "group"     => "uid"
                        ),
                        $columns
                    );
                }
                if ( $this->division == StatisticParams::DIV_MONTH ) {
                    // make sure we are
                    $startDate   = $this->getStartDate();
                    $divInterval = new DateInterval('P1M');
                    $endDate     = clone $this->toDate;
                    $endDate->add( new DateInterval("P1D") );
                    // division index
                    $divIndex    = 0;

                    while ( $startDate < $endDate ) {
                        $startStr = $startDate->format("Y-m-d");
                        $divLabel = $startDate->format("M Y");
                        $startDate->add( $divInterval );
                        // make sure the end date isn't AFTER our filtered endDate
                        if ( $startDate > $endDate ) {
                            $startDate = clone $endDate;
                            $startDate->add( new DateInterval("P1D") );
                        }
                        $endStr   = $startDate->format("Y-m-d");

                        $this->insertColumn(
                            "numeric_{$divIndex}",
                            array(
                                "type"      => "numeric",
                                "datatype"  => "numeric",
                                "name"      => "col_numeric_{$divIndex}",
                                "display"   => $divLabel,
                                "fetch"     => "COUNT({$table}.id) AS col_numeric_{$divIndex}",
                                "group"     => "uid",
                                "where"     => "DATE(date)>='{$startStr}' AND DATE(date)<'{$endStr}'"
                            ),
                            $columns
                        );

                        ++$divIndex;
                    }
                }

                // INSERT info columns
                $this->insertColumn(
                    "info_1",
                    array(
                        "type"      => "info",
                        "datatype"  => "date",
                        "name"      => "info_1",
                        "display"   => "First {$actionName}",
                        "fetch"     => 'MIN(date) as "info_1"'
                    ),
                    $columns
                );
                $this->insertColumn(
                    "info_2",
                    array(
                        "type"      => "info",
                        "datatype"  => "date",
                        "name"      => "info_2",
                        "display"   => "Last {$actionName}",
                        "fetch"     => 'MAX(date) as "info_2"'
                    ),
                    $columns
                );
                break;

            case self::AGGR_DATE:
                $this->insertColumn(
                    "index",
                    array(
                        "type"      => "index",
                        "datatype"  => "date",
                        "name"      => "col_index",
                        "display"   => "Date",
                        "fetch"     => "DATE(date) as col_index"
                    ),
                    $columns
                );
                // @TODO: division is required for multiple states
                $this->insertColumn(
                    "numeric",
                    array(
                        "type"      => "numeric",
                        "datatype"  => "numeric",
                        "name"      => "col_numeric",
                        "display"   => "Count",
                        "fetch"     => "COUNT({$table}.id) AS col_numeric",
                        "group"     => "DATE(date)"
                    ),
                    $columns
                );
                break;

            case self::AGGR_HOUSE_SESSION:
            case self::AGGR_HOUSE_EXPORT:
            case self::AGGR_HOUSE_COMBINED:
                $this->insertColumn(
                    "index",
                    array(
                        "type"      => "index",
                        "datatype"  => "text",
                        "name"      => "col_index",
                        "display"   => "House",
                        "fetch"     => "house as col_index"
                    ),
                    $columns
                );  
                $this->insertColumn(
                    "numeric",
                    array(
                        "type"      => "numeric",
                        "datatype"  => "numeric",
                        "name"      => "col_numeric",
                        "display"   => "Count",
                        "fetch"     => "COUNT({$table}.id) AS col_numeric",
                        "group"     => "house",
                        "where"     => (
                            $this->aggregator == self::AGGR_HOUSE_COMBINED ? "trig!=''" : (
                                $this->aggregator == self::AGGR_HOUSE_SESSION ?
                                "trig='sessionsave'" :
                                "trig='pdfexport'"
                            )
                        )
                    ),
                    $columns
                );
                break;

            case self::AGGR_HOUSE_TYPE:
                $this->insertColumn(
                    "index",
                    array(
                        "type"      => "index",
                        "datatype"  => "text",
                        "name"      => "col_index",
                        "display"   => "Facade/Option",
                        "fetch"     => "name as col_index"
                    ),
                    $columns
                );
                $this->insertColumn(
                    "numeric_1",
                    array(
                        "type"      => "numeric",
                        "datatype"  => "numeric",
                        "name"      => "col_numeric_1",
                        "display"   => "Facades",
                        "fetch"     => "COUNT({$table}.id) AS col_numeric_1",
                        "group"     => "name",
                        "where"     => (
                            "type='facade'"
                        )
                    ),
                    $columns
                );
                $this->insertColumn(
                    "numeric_2",
                    array(
                        "type"      => "numeric",
                        "datatype"  => "numeric",
                        "name"      => "col_numeric_2",
                        "display"   => "Options",
                        "fetch"     => "COUNT({$table}.id) AS col_numeric_2",
                        "group"     => "name",
                        "where"     => (
                            "type='option' AND name!=''"
                        )
                    ),
                    $columns
                );
                break;
        }
    }

    private function insertTypeColumns( &$columns, $table )
    {
        switch ( $this->type ) {
            case self::TYPE_SESSION:
                // @TODO
                break;

            case self::TYPE_EXPORT:
                // @TODO
                break;

            case self::TYPE_HOUSE:
                // @TODO
                break;

            case self::TYPE_HOUSE_BREAKDOWN:
            case self::TYPE_HOUSE_BREAKDOWN_EXPORT:
                // TODO
                break;
        }
    }

    private function buildColumns( $table, $actionName )
    {
        $columns = array();

        $this->insertAggregatorColumns( $columns, $table, $actionName );
        $this->insertTypeColumns( $columns, $table );

        return $columns;
    }

    private function setup()
    {
        $db = pdoConnect();
        // define settings
        $globalTypeSettings = array(
            self::TYPE_SESSION => array(
                // DB table
                "table"     => "sitting_sessions",
                // graph / table ID name
                "name"      => "saved_sessions",
                // display name of the counted action
                "action"    => "Session",
                // display name of the table
                "display"   => "Saved Sessions",
                // query parameters
                "query"     => array(
                    // @TODO: add any needed parameters
                )
            ),
            self::TYPE_EXPORT => array(
                // DB table
                "table"     => "statistics",
                // graph / table ID name
                "name"      => "pdf_exports",
                // display name of the counted action
                "action"    => "Export",
                // display name of the table
                "display"   => "PDF Exports",
                // query parameters
                "query"     => array(
                    "where" => "s_value='pdfExportStarted'"
                )
            ),
            self::TYPE_HOUSE => array(
                // DB table
                "table"     => "statistics_houses",
                // graph / table ID name
                "name"      => "house_usage",
                // display name of the counted action
                "action"    => "House",
                // display name of the table
                "display"   => "House Usage",
                // query parameters
                "query"     => array(
                    // @TODO: add any needed parameters
                )
            ),
            self::TYPE_HOUSE_BREAKDOWN => array(
                // DB table
                "table"     => "statistics_breakdown",
                // graph / table ID name
                "name"      => "house_usage",
                // display name of the counted action
                "action"    => "House",
                // display name of the table
                "display"   => "House Breakdown",
                // query parameters
                "query"     => array(
                    // "where" => "house='".$this->houseName."'"
                    "where" => "name!='' AND house=".$db->quote($this->houseName)
                )
            ),
            self::TYPE_HOUSE_BREAKDOWN_EXPORT => array(
                // DB table
                "table"     => "statistics_breakdown",
                // graph / table ID name
                "name"      => "house_usage",
                // display name of the counted action
                "action"    => "House",
                // display name of the table
                "display"   => "House Breakdown",
                // query parameters
                "query"     => array(
                    "where" => "trig='pdfexport' AND name!='' AND house=".$db->quote($this->houseName)
                )
            )
        );

        $this->typeSettings = $globalTypeSettings[$this->type];

        // build information based on the parameters
        $this->table    = $this->typeSettings["table"];
        $this->statName = $this->typeSettings["name"];
        $this->columns  = $this->buildColumns($this->table, $this->typeSettings["action"] );
    }

    private function runQueries() {
        $this->results = array();

        // fetch - group - where
        $db = pdoConnect();
        $query = new StatisticQuery( $this->table, $this->cid );

        // make sure non-public accounts are not visible
        $query->addConfiguration(array(
            "where" => "uf_users.user_name NOT LIKE '%@landconnect.com.au%'"
        ));

        // if this is not a state-divided statistic, add the state
        // clause to the query
        if ( $this->division != self::DIV_STATE ) {
            $query->addConfiguration(array(
                "where" => "uf_users.state_id=".$this->state
            ));
        }

        // add the date filter
        $query->addConfiguration(array(
            "where" => $this->timeFilterStatement($this->table)
        ));

        // add the users filter;
        // @MD 29NOV17 - allowing for a 'includeAllUsers' flag to skip this
        if ( !$this->includeAllUsers && sizeof($this->users) ) {
            $query->addConfiguration(array(
                "where" => "uf_users.id IN (".implode($this->users, ",").")"
            ));
        }

        // add the table-level configuration
        $query->addConfiguration( $this->typeSettings["query"] );

        // first build the query parameters without the numeric columns
        foreach ( $this->columns as $name=>$col ) {
            // don't include the numeric columns
            if ( $col["type"] == "numeric" ) continue;
            // add this column's configuration to the query
            $query->addConfiguration( $col );
        }

        // run the query for each numeric column, and add it to the results
        foreach ( $this->columns as $name=>$col ) {
            // skip non-numeric columns as they have already
            // been added to the query configuration
            if ( $col['type'] != "numeric" ) continue;

            $sqlQuery = $query->build( $col );

            // echo "<i>Running:</i><br/>{$sqlQuery}<br/>";
            // run query and insert the results into the results array
            if ( !$stmt = $db->prepare($sqlQuery) ) {
                // @DEBUG
                // echo "<i>SQL error preparing</i><br/>{$sqlQuery}<br/>";
                continue;
            }   else {
                // @DEBUG
                // echo "<i>Running:</i><br/>{$sqlQuery}<br/>";
            }

            if ( !$stmt->execute() ) {
                // @DEBUG
                // echo "<i>SQL error executing</i><br/>{$sqlQuery}<br/>";
                continue;
            }

            // @TODO: support col_index merger for divided Statistics
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $index = $r["col_index"];
                if ( !isset($this->results[$index]) ) {
                    $this->results[$index] = array();
                }

                foreach ($r as $key=>$value) {
                    $this->results[$index][$key] = $value;
                }
                // $name = $r['name'];
                // $value = $r['value'];
                // $results[$name] = $value;
            }
        }

        // ksort if the aggregator is user
        if ( $this->aggregator == StatisticParams::AGGR_USER ) {
            ksort( $this->results, SORT_DESC );
        }

        // build the results array
        $this->resultsArray = array();

        foreach ($this->results as $value) {
            $this->resultsArray[] = $value;
        }
    }
}

?>