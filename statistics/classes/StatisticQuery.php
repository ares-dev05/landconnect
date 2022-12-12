<?php

class StatisticQuery {

    private $table;
    private $cid;

    private $columns;
    private $conditions;
    private $group;
    private $join;

    public function __construct( $table, $cid ) {
        $this->table      = $table;
        $this->cid        = $cid;
        // @TODO: do we need dynamic JOINS ?
        $this->join       = "INNER JOIN uf_users ON {$table}.uid = uf_users.id";

        $this->columns    = array();
        $this->conditions = array();
    }

    public function copy() {
        $query = new StatisticQuery( $this->table, $this->cid );
        $query->columns     = $this->columns;
        $query->conditions  = $this->conditions;
        $query->group       = $this->group;
        return $query;
    }

    public function addConfiguration( $config ) {
        // fetch - group - where
        if ( isset($config["fetch"]) ) {
            $this->columns[] = $config["fetch"];
        }
        if ( isset($config["group"]) ) {
            $this->group = $config["group"];
        }
        if ( isset($config["where"]) ) {
            $this->conditions[] = $config["where"];
        }
    }

    public function build( $extraConfig=null ) {
        if ( $extraConfig!=null ) {
            $conditionedQuery = $this->copy();
            $conditionedQuery->addConfiguration($extraConfig);
            return $conditionedQuery->build();
        }

        // otherwise, run the local query
        // fill in some extra conditions
        $this->conditions[] = "cid=".$this->cid;

        // build the WHERE clause
        if ( sizeof($this->conditions)) {
            $whereClause = " WHERE " . implode(" AND ", $this->conditions);
        }   else {
            $whereClause = " ";
        }
        // build the GROUP BY clause, if needed
        $groupClause = $this->group ? " GROUP BY ".$this->group : "";
        // build the JOIN, if needed
        $joinClause = " ".$this->join." ";

        $query =
            "SELECT ".
            implode(",", $this->columns).
            " FROM ".$this->table.
            $joinClause.
            $whereClause.
            $groupClause;

        return $query;
    }
}

?>