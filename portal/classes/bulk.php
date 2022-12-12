<?php

// loading config.php sets up the paywall and the billing account for the currently logged-in user
require_once(__DIR__."/../../models/config.php");

global $loggedInUser;

if ( $loggedInUser && isGlobalAdmin($loggedInUser->user_id) ) {
    // runBulkImport();
    runBulkTransfer();
}   else {
    // runBulkTransfer();
}

function runBulkTransfer()
{
    echo "Starting bulk transfer...<br/>";
/*
Elements Series - Double Storey
Elements Series - Single Storey
Gen Series - Double Storey
Gen Series - Single Storey

IDs:
14
42
46
44
*/

    global $db;
    $companyId = 3;

    $now = new DateTime();
    $creationDate = $now->format(DateTime::ATOM);

    // range fetching
    $rangeStatement = $db->prepare( "SELECT id, name, state_id FROM house_ranges WHERE cid={$companyId}" );

    // prepare the SQL statements for inserting the plan and its properties into the DB
    $ticketStatement = $db->prepare( "INSERT INTO pt_ticket SET cid=:company, state_id=:state, status='closed'" );
    $propsStatement = $db->prepare(
        "INSERT INTO pt_ticket_props ( `tid`, `name`, `value`, `type` )
         VALUES
            ( :id1, 'floorName'  , :planName   , 'string' ),
            ( :id2, 'releaseDate', :releaseDate, 'datetime' ),
            ( :id3, 'targetRange', :targetRange, 'string' )"
    );
    $ownerStatement = $db->prepare( "INSERT INTO pt_watcher SET tid=:id, uid=1, role_owner=1, role_assignee=0" );

    $excludeRanges  = array( 14, 42, 46, 44 );

    $ranges = array();

    // start transferring all the houses from the app into the portal
    if ( $rangeStatement->execute() ) {
        while( ($range=$rangeStatement->fetch(PDO::FETCH_ASSOC)) != NULL ) {
            // fetch all the houses for this range
            if (!in_array(intval($range["id"]), $excludeRanges)) {
                // buffer the range
                $ranges [] = $range;
            }
        }

        // now go over all the ranges and fetch their houses
        foreach ( $ranges as $range ) {
            $rangeId    = intval($range["id"]);
            $rangeName  = $range["name"];
            $rangeState = intval($range["state_id"]);

            echo "adding range {$rangeName}<br/>";
            $houseCount = 0;

            // fetch all the houses in this range
            $houseStatement = $db->prepare( "SELECT id, name FROM house_svgs WHERE range_id={$rangeId}");
            if ( $houseStatement->execute() ) {
                while ( ($house=$houseStatement->fetch(PDO::FETCH_ASSOC)) != NULL ) {
                    $planName   = $house["name"];

                    // insert this plan into the DB
                    if ( $ticketStatement->execute(array(
                        ":company"  => $companyId,
                        ":state"    => $rangeState
                    )) ) {
                        // fetch the ID of the inserted ticket
                        $planId = $db->lastInsertId();

                        // insert the plan's properties into the DB
                        $propsStatement->execute(array(
                            ":id1"          => $planId,
                            ":id2"          => $planId,
                            ":id3"          => $planId,
                            ":planName"     => $planName,
                            ":releaseDate"  => $creationDate,
                            ":targetRange"  => $rangeName
                        ));

                        $ownerStatement->execute(array(
                            ":id"           => $planId
                        ));

                        ++$houseCount;
                    }
                }
            }
            $houseStatement->closeCursor();

            echo " -> complete! added $houseCount houses<br/>";
        }
    }
}

/**
 * inserts a set of predetermined plans, grouped by ranges, grouped by
 * @TODO: does this create the storage folders for the tickets???
 */
function runBulkImport() {
    global $db;
    // @TODO: check if the plans weren't inserted already

    // company = Burbank
    // the plans were inserted in the DB with IDs 922 -> 1076
    $companyId  = 3;

    $batches    = array(
        // South Australia
        array(
            "state" => 5,
            "ranges" => array(
                array(
                    "name"  => "Double Storey",
                    "plans" => array(
                        "Prospect 225",
                        "Highgate 255",
                        "Prospect 275",
                        "Bowden 300",
                        "Glenelg 320",
                        "Glenelg 390",
                        "Beaumont 330",
                        "Beaumont 395",
                        "Bowden 325",
                        "Richmond 325"
                    )
                ),
                array(
                    "name"  => "Single Storey",
                    "plans" => array(
                        "Brompton 160",
                        "Brompton 155",
                        "Mitchell 145",
                        "Mitchell 140",
                        "Mitchell 160",
                        "Mitchell 175",
                        "Mitchell 190",
                        "Henley 145",
                        "Henley 170",
                        "Evandale 160",
                        "Magill 155",
                        "Evandale 175",
                        "Aldgate 205",
                        "Somerton 205",
                        "Magill 175",
                        "Warradale 190",
                        "Evandale 205",
                        "Somerton 255",
                        "Cumberland 205",
                        "Osmond 235",
                        "Tusmore 225",
                        "Warradale 205",
                        "Springfield 235",
                        "Cumberland 230",
                        "Tusmore 245",
                        "Cumberland 250",
                        "Springfield 265",
                        "Grange 220",
                        "Leabrook 240",
                        "Lockley 230",
                        "Hackney 150",
                        "Brooklyn 230",
                        "Brooklyn 255",
                        "Brooklyn 275",
                        "Brooklyn 300",
                        "Woodford 220",
                        "Woodford 285",
                        "Lockley 300",
                        "Belair 240",
                        "Burnside 281"
                    )
                )
            )
        ),
        // New South Wales
        array(
            "state" => 1,
            "ranges" => array(
                array(
                    "name"  => "Single Storey",
                    "plans" => array(
                        "Allington 153",
                        "Allington 158",
                        "Barwon 184",
                        "Benton 276",
                        "Chandler 142",
                        "Chandler 160",
                        "Chandler 174",
                        "Chandler 189",
                        "Cheshire 148",
                        "Cheshire 172",
                        "Clarence 191",
                        "Claxton 234",
                        "Claxton 250",
                        "Claxton 267",
                        "Corby 207",
                        "Corby 231",
                        "Corby 249",
                        "Corby 265",
                        "Crawford 178",
                        "Crawford 206",
                        "Crawford 218",
                        "Crawford 237",
                        "Cresswell 172",
                        "Cresswell 177",
                        "Dartmouth 225",
                        "Dartmouth 246",
                        "Eden 208",
                        "Elton 250",
                        "Elton 265",
                        "Halton 269",
                        "Hazlewood 192",
                        "Hazlewood 205",
                        "Hazlewood 226",
                        "Hornby 281",
                        "Hornby 297",
                        "Hutton 263",
                        "Isabella 179",
                        "Isabella 207",
                        "Kendal 169",
                        "Kentmere 182",
                        "Kentmere 211",
                        "Kentmere 224",
                        "Kentmere 239",
                        "Kingsgate 213",
                        "Lydford 237",
                        "Lydford 263",
                        "Maryland 234",
                        "Maryland 267",
                        "Northborough 238",
                        "Northborough 266",
                        "Northborough 283",
                        "Northborough 299",
                        "Paterson 198",
                        "Paterson 215",
                        "Pembridge 260",
                        "Pembridge 284",
                        "Pembridge 300",
                        "Radcliffe 151",
                        "Rockingham 241",
                        "Somerton 234",
                        "Somerton 251",
                        "Somerton 268",
                        "Thompson 251",
                        "Thompson 265",
                        "Tonbridge 196",
                        "Walton 251",
                        "Whitehall 251",
                        "Whittingham 223",
                        "Whittingham 284"
                    )
                ),
                array(
                    "name"  => "Double Storey",
                    "plans" => array(
                        "Amberley 233",
                        "Amberley 269",
                        "Amberley 292",
                        "Avon 298",
                        "Avon 352",
                        "Avon 384",
                        "Avon 422",
                        "Cartington 259",
                        "Cartington 307",
                        "Cartington 340",
                        "Downton 364",
                        "Embleton 224",
                        "Embleton 239",
                        "Embleton 274",
                        "Embleton 312",
                        "Harlech 333",
                        "Harlech 395",
                        "Harlech 432",
                        "Harlech 476",
                        "Leicester 360",
                        "Norwich 369",
                        "Norwich 403",
                        "Portland 341",
                        "Portland 393",
                        "Portland 438",
                        "Rochford 207",
                        "Rochford 215",
                        "Williams 410",
                        "Williams 437",
                        "Williams 484",
                        "Witton 198"
                    )
                ),
                array(
                    "name"  => "Ranch",
                    "plans" => array(
                        "Stockwood 240",
                        "Hawkesbury 289"
                    )
                )
            )
        ),
        // ACT - Australian Capital Territory
        array(
            "state" => 2,
            "ranges" => array(
                array(
                    "name"  => "Double Storey",
                    "plans" => array(
                        "Amberley 233",
                        "Amberley 269",
                        "Amberley 292",
                        "Avon 298",
                        "Avon 352",
                        "Avon 384",
                        "Avon 422",
                        "Cartington 259",
                        "Cartington 307",
                        "Cartington 340",
                        "Downton 364",
                        "Embleton 224",
                        "Embleton 239",
                        "Embleton 274",
                        "Embleton 312",
                        "Harlech 333",
                        "Harlech 395",
                        "Harlech 432",
                        "Harlech 476",
                        "Leicester 360",
                        "Norwich 369",
                        "Norwich 403",
                        "Portland 341",
                        "Portland 393",
                        "Portland 438",
                        "Rochford 207",
                        "Rochford 215",
                        "Williams 410",
                        "Williams 437",
                        "Williams 484",
                        "Witton 198"
                    )
                ),
                array(
                    "name"  => "Ranch",
                    "plans" => array(
                        "Stockwood 240",
                        "Hawkesbury 289"
                    )
                )
            )
        )
    );

    $now = new DateTime();
    $creationDate = $now->format(DateTime::ATOM);

    $creationStatus = 'closed';

    // prepare the SQL statements for inserting the plan and its properties into the DB
    $ticketStatement = $db->prepare(
        "INSERT INTO pt_ticket
         SET cid=:company, state_id=:state, status='closed'"
    );

    $propsStatement = $db->prepare(
        "INSERT INTO pt_ticket_props ( `tid`, `name`, `value`, `type` )
         VALUES
            ( :id1, 'floorName'  , :planName   , 'string' ),
            ( :id2, 'releaseDate', :releaseDate, 'datetime' ),
            ( :id3, 'targetRange', :targetRange, 'string' )"
    );

    $ownerStatement = $db->prepare(
        "INSERT INTO pt_watcher
         SET tid=:id, uid=1, role_owner=1, role_assignee=0"
    );

    foreach ($batches as $batch) {
        $stateId    = $batch["state"];
        $ranges     = $batch["ranges"];

        foreach ($ranges as $range) {
            $rangeName  = $range["name"];
            $plans      = $range["plans"];

            foreach ($plans as $planName) {
                // insert this plan into the DB
                if ( $ticketStatement->execute(array(
                    ":company"  => $companyId,
                    ":state"    => $stateId
                )) ) {
                    // fetch the ID of the inserted ticket
                    $planId = $db->lastInsertId();

                    // insert the plan's properties into the DB
                    $propsStatement->execute(array(
                        ":id1"          => $planId,
                        ":id2"          => $planId,
                        ":id3"          => $planId,
                        ":planName"     => $planName,
                        ":releaseDate"  => $creationDate,
                        ":targetRange"  => $rangeName
                    ));

                    $ownerStatement->execute(array(
                        ":id"           => $planId
                    ));

                    // @TEMP: only insert one thing!
                    // exit;
                }
            }
        }
    }
}

/*
,
            array(
                "name"  => "",
                "plans" => array(

                )
            )
 */

?>