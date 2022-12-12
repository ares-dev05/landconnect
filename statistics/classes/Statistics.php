<?php


class Statistics {

    // statistic types

    /**
     * @param $type         String the statistic type (e.g. sessions, usage, portal)
     * @param $aggregator   String the aggregator (e.g. user, time).
     * @param $company      int The companyID for which statistics are fetched
     * @param $state        int The stateId for which statistics are fetched
     */
    public static function fetch( $type, $aggregator, $company, $state )
    {
        /*
            - iterate over all states
            - return metadata containing:
                the states information
                the columns to be represented
                data on how to build the charts/tables
        */
    }

    private static function buildQuery( $type, $aggregator, $company, $state ) {
        /*
        DATE query:
        SELECT DATE(date) as "Date", COUNT(statistics.id) as "Export Count" FROM statistics
INNER JOIN uf_users ON statistics.uid = uf_users.id
WHERE cid=3 AND s_value='pdfExportStarted' AND uf_users.state_id=7 GROUP BY DATE(date)
        USER query:
        SELECT uf_users.display_name,  COUNT(sitting_sessions.id), MIN(date), MAX(date) FROM sitting_sessions
INNER JOIN uf_users ON sitting_sessions.uid=uf_users.id
WHERE cid=3 GROUP BY uid
        */
    }
}

?>