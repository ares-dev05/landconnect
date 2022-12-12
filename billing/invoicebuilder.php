<?php

// loading config.php sets up the paywall and the billing account for the currently logged-in user
require_once(__DIR__."/../models/config.php");
global $loggedInUser;

if ( !isset($loggedInUser) || !$loggedInUser || !isGlobalAdmin($loggedInUser->user_id) )
    exit;

// either the pre-defined billing account, or -> 6 = Burbank Homes
$accountId = isset($_GET['account']) ? intval($_GET['account']) : 6;

$states = array(
    1=>	'NSW',
    2=>	'ACT',
    3=>	'NT',
    4=>	'QLD',
    5=>	'SA',
    6=>	'TAS',
    7=>	'VIC',
    8=>	'WA',
    9=>	'DOC'
);

if ( isset($_POST) && isset($_POST['submit']) ) {
    if ( !isset($_GET['account']) ) {
        echo "can't create invoice - no billing account is selected";
    }   else {
        // add the invoice
        $plansData = array();
        foreach ($_POST['plans'] as $planId) {
            $plansData[$planId] = $_POST["is_new_{$planId}"];
        }

        ob_start();
        addInvoice(
            $accountId,
            $_POST['title'],
            $_POST["details"],
            $plansData
        );
        $invoiceFeedback = ob_get_contents();
        ob_end_clean();
    }
}

    $db = pdoConnect();

    // fetch the company ID for this billing account
    $stmt = $db->prepare("SELECT client FROM billing_account WHERE id=:id");
    if ( $stmt && $stmt->execute(array(":id"=>$accountId)) && ($obj=$stmt->fetchObject()) != null ) {
        $companyId = $obj->client;
    }   else {
        echo "Oops, this company doesn't seem to be set up correctly - this shouldn't happen, contact Mihai to have a look.";
        exit;
    }

    // fetch all billing accounts
    $stmt = $db->prepare("SELECT id, name FROM billing_account ORDER BY name ASC");
    $accounts = "";
    if ( $stmt && $stmt->execute() ) {
        while ( ($obj=$stmt->fetchObject()) != null ) {
            $selected = ($obj->id==$accountId ? "selected='selected'" : "");
            $accounts .= "<option $selected value='{$obj->id}'>{$obj->name}</option>";
        }
    }

    // fetch plans - look back 3! months for un-invoiced plan work
    $earliest = date("Y-m-d H:i:s", strtotime('-3 months'));
    $earliestTS = strtotime($earliest);

    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

    // prepare the long-ass statement that fetches portal tickets that are either created in the last 2 months,
    // or have messages in the last 2 months, and have not been invoiced before; also fetch a couple of util props for them
    $stmt = $db->prepare("
SELECT 
	DISTINCT pt_ticket.id,
	pt_ticket.state_id,
	pt_ticket.created_at,
	pt_ticket.created_at > :earliest as is_new,
	MAX(pt_node.created_at) as updated_at,
        pt_ticket_props.value as floor_name
FROM pt_ticket
INNER JOIN
	pt_ticket_props
ON
	pt_ticket.id = pt_ticket_props.tid
INNER JOIN
	pt_node
ON
	pt_ticket.id = pt_node.tid
WHERE (
	pt_ticket.created_at >= :earliest OR
	pt_ticket.id IN (
		SELECT DISTINCT tid
		FROM pt_node
		WHERE created_at >= :earliest
	)
)	AND (
	pt_ticket.id NOT IN (
		SELECT DISTINCT pid
        FROM billing_invoice_product
        INNER JOIN
            billing_invoice
        ON
            billing_invoice.id=billing_invoice_product.vid
        WHERE
            billing_invoice.created_date>:earliest
	)
)	AND
	pt_ticket_props.name=:floorName
	AND 
	pt_ticket.cid=:company
GROUP BY pt_ticket.id
ORDER BY pt_ticket.state_id, floor_name ASC
    ");

    $floorplans = "";

    if ( $stmt && $stmt->execute(array(
            ":earliest" => $earliest,
            ":floorName" => "floorName",
            ":company" => $companyId
        ))) {
        while ( ($obj=$stmt->fetchObject())!=null ) {
            $id = $obj->id;
            $created = substr($obj->created_at, 0, 10);
            $updated = substr($obj->updated_at, 0, 10);
            if ( strtotime($created) > $earliestTS ) {
                $created = "<b>{$created}</b>";
            }
            if ( strtotime($updated) > $earliestTS ) {
                $updated = "<b>{$updated}</b>";
            }

            if ( $obj->is_new ) {
                $selectedNew = "checked='checked'";
                $selectedUpd = "";
            }   else {
                $selectedNew = "";
                $selectedUpd = "checked='checked'";
            }

            $floorplans .= "
                <div class='row' style=\"vertical-align: middle; margin-bottom:15px;\">
                    <div class=\"col-sm-4\">
                        <input type=\"checkbox\" id=\"plans_{$id}\" name=\"plans[]\" value=\"{$id}\" />
                        
                        <label for=\"plans_{$id}\"><b>
                            {$states[$obj->state_id]}: {$obj->floor_name}
                        </b></label>
                        <a href='/portal/view_ticket.php?id={$id}' target='_blank'>(View)</a>
                        
                        <br/>
                        <input id=\"is_new_{$id}\" type=\"radio\" name=\"is_new_{$id}\" value=\"1\" {$selectedNew} style='margin: 0 0 0 20px;' />
                        <label for=\"is_new_{$id}\">New</label>
                        <input id=\"is_update_{$id}\" type=\"radio\" name=\"is_new_{$id}\" value=\"0\" {$selectedUpd} />
                        <label for=\"is_update_{$id}\">Update</label>
                    </div>
                    <div class=\"col-sm-4\">
                        Created {$created}<br/>
                        Updated {$updated}
                    </div>
                </div>
";
        }
    }
?>
<html>
    <head>
        <title>Landconnect</title>
        <?php require_once("../account/includes.php");  ?>

        <script type="text/javascript">
            function refreshWithNewAccount() {
                console.log($("#account").val());
                window.location = "invoicebuilder.php?account="+$("#account").val();
            }
        </script>
    </head>
    <body>
        <div id="wrapper" style="margin-top: 20px;">
            <?php if ( isset($invoiceFeedback) ): ?>
                <div class='row' style="padding-bottom:20px;">
                    <div class="col-sm-12">
                        <?php echo $invoiceFeedback; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form onsubmit="void()">
                <!-- select billing account -->
                <div class='row'>
                    <div class='col-sm-2'>
                        <label for="account">Billing Account</label>
                    </div>
                    <div class="col-sm-4">
                        <select id="account" name="account" class='form-control' onchange="refreshWithNewAccount()">
                            <?php echo $accounts; ?>
                        </select>
                    </div>
                </div>
            </form>

            <form action="invoicebuilder.php?account=<?php echo $accountId; ?>" method="post">

                <!-- invoice title -->
                <div class='row'>
                    <div class='col-sm-2'>
                        <b>Invoice title</b>
                    </div>
                    <div class="col-sm-4">
                        <input type="text" id="title" name="title" style="width: 100%" />
                    </div>
                </div>
                <div class="row" style="padding-bottom:10px;">
                    <div class="col-sm-2">formats</div>
                    <div class="col-sm-10">
                        New Plans & Updates - [MONTH]<br/>
                        New Plans Provided in [MONTH] for [STATE-e.g. SA, VIC, QLD]<br/>
                        Plan Updates Provided in [MONTH]<br/>
                    </div>
                </div>

                <!-- invoice details -->
                <div class='row' style="padding-bottom:10px;">
                    <div class='col-sm-2'>
                        <b>Details (optional)</b>
                    </div>
                    <div class="col-sm-4">
                        <input type="text" id="details" name="details" style="width: 100%" />
                    </div>
                </div>

                <!-- invoice details -->
                <div class='row' style="padding-bottom:10px;">
                    <div class='col-sm-2'>
                        (select plans before submitting)
                    </div>
                    <div class="col-sm-4">
                        <input type="submit" name="submit" value="Create Invoice" />
                    </div>
                </div>

                <!-- product selection-->
                <div class="row">
                    <div class="col-sm-12">
                        Non-invoiced plans created/updated in the last 2 months. <b>Bold</b> dates are from the last 2 months.
                    </div>
                </div>

                <?php echo $floorplans; ?>
            </form>
        </div>
    </body>
</html>

<?php


if ( $loggedInUser && isGlobalAdmin($loggedInUser->user_id) ) {
    // create billing account for porter davis
    // $account = setupAccountFor( 2 );
    // $account = setupAccountFor( 999 );
    // $account = setupAccountFor(18);
    
    /*
billing accounts legend:
2 - Simonds Homes
4 - porter davis
5 - orbit homes
6 - Burbank Homes
7 - Bold Living
8 - Mega Homes
9 - 8 Homes

    addInvoice(
        // Billing Account ID
        6,  // Burbank
        / @INFO
            New Plans & Updates - [MONTH]
            New Plans Provided in [MONTH] for [STATE-e.g. SA, VIC, QLD]
            Plan Updates Provided in [MONTH]

            new plans   59
            updates     29
         /
        "Plan Updates Provided in February for VIC",
        // products
"Deepdene 225 - 2016
Deepdene 246 - 2016",
        LcInvoiceProduct::PRODUCT_PLAN_UPDATE,
        LcAccount::grossCost( 29 ),
        ""
    );
         */

}

function addInvoice( $accountId, $name, $details, $plansData )
{
    echo "Preparing invoice creation...<br/>";
    global $db;

    /**
     * Setup a merchant account to use for Gross/Net calculations
     * @var $merchantAccount LcMerchantAccount
     */
    $merchantAccount = LcMerchantAccount::$LANDCONNECT_ACCOUNT;

    // calculate due date
    $date = new DateTime("+2 weeks");
    $dateFmt = $date->format(Config::SQL_DATETIME);

    // invoice creation statement
    $invoiceStmt = $db->prepare(
        "INSERT INTO billing_invoice
         SET
            account_id=:account,
            `name`=:invoice_name,
            details=:details,
            due_date=:due_date,
            transaction_id=''"
    );

    // product addition statement
    $productStmt = $db->prepare(
        "INSERT INTO billing_invoice_product
         SET
            vid   = :invoice,
            ptype = :ptype,
            pid   = :pid,
            cost  = :cost,
            message= ''"
    );

    // create the invoice
    if ( $invoiceStmt->execute(array(
        ":account"      => $accountId,
        ":invoice_name" => $name,
        ":details"      => $details,
        ":due_date"     => $dateFmt
    )) ) {
        $vid    = $db->lastInsertId();

        $successCount = 0;
        $updateCount = 0;
        $additionCount = 0;
        $totalCost = 0;

        // insert all the products
        foreach ($plansData as $planId=>$isNew) {
            if ( $productStmt->execute(array(
                ":invoice"  => $vid,
                ":ptype"    => $isNew ? LcInvoiceProduct::PRODUCT_PLAN_ADDITION : LcInvoiceProduct::PRODUCT_PLAN_UPDATE,
                ":pid"      => $planId,
                ":cost"     => $merchantAccount->grossCost( $isNew ? 59 : 29 )
            )) ) {
                $successCount++;
                $totalCost  += $merchantAccount->grossCost( $isNew ? 59 : 29 );

                if ( $isNew )
                    ++$additionCount;
                else
                    ++$updateCount;
            }
        }

        echo "Created invoice with {$successCount} plans: {$additionCount} addition(s), {$updateCount} update(s). Total cost=\${$totalCost}<br/>";
        echo "<a target='_blank' href='/billing/invoice_preview.php?id={$vid}'>View Invoice (opens in new window)</a>";

    }   else {
        echo "There was a DB failure while creating the invoice.";
    }
}

function setupAccountFor( $companyId ) {
    global $loggedInUser, $db;

    echo "setting up account for {$companyId}...<br/>";

    $account = LcAccount::getForCompany( $loggedInUser, $companyId );

    if ( $account ) {
        echo "account found or created<br/>";

        $data = null;

        /**
         * @TODO: instead of setting these here, we should create a function in LcAccount that will also make sure
         * @TODO    that the values are posted to Braintree
         */
        switch ($companyId) {
            case 4:
                $data = array(
                    ":isoCode"              => "AUD",
                    ":price"                => 658.90,
                    ":cname"                => "PDH Group Pty Ltd",
                    ":address_city"         => "Docklands",
                    ":address_postal_code"  => "3008",
                    ":address_street"       => "Level 10, 720 Bourke Street",
                    ":address_region"       => "Victoria",
                    ":address_country"      => "Australia",
                    // September 6th 2017
                    ":agreement_end_date"   => "2017-09-06 00:00:00",
                    ":states"               => "Victoria",
                    ":abn"                  => "60505868854"
                );
                break;

            // Simonds
            case 2:
                $data = array(
                    ":isoCode"              => "AUD",
                    ":price"                => 1755.6,
                    ":cname"                => "Simonds Pty Ltd",
                    ":address_city"         => "Melbourne",
                    ":address_postal_code"  => "3004",
                    ":address_street"       => "Level 1, 570 St Kilda Road",
                    ":address_region"       => "Victoria",
                    ":address_country"      => "Australia",
                    // September 6th 2017
                    ":agreement_end_date"   => "2017-08-13 00:00:00",
                    ":states"               => "Victoria, South Australia, Queensland, New South Wales",
                    ":abn"                  => "35050197610"
                );
                break;

            // Burbank Homes
            case 3:
                $data = array(
                    ":isoCode"              => "AUD",
                    ":price"                => 2194.50,
                    ":cname"                => "Burbank Australia Pty Ltd",
                    ":address_city"         => "Altona",
                    ":address_postal_code"  => "3018",
                    ":address_street"       => "36 Aberdeen Road",
                    ":address_region"       => "Victoria",
                    ":address_country"      => "Australia",
                    // 10th November 2017
                    ":agreement_end_date"   => "2019-11-04 00:00:00",
                    ":states"               => "Victoria, South Australia, ACT, Queensland, New South Wales",
                    ":abn"                  => "91007099872"
                );
                break;

            // Eight Homes
            case 6:
                $data = array(
                    ":isoCode"              => "AUD",
                    ":price"                => 880,
                    ":cname"                => "Mahercorp Pty Ltd",
                    ":address_city"         => "Melbourne, Sunshine West",
                    ":address_postal_code"  => "3020",
                    ":address_street"       => "U 7/180 Fairbairn Rd",
                    ":address_region"       => "Victoria",
                    ":address_country"      => "Australia",
                    // 08/02/2020
                    ":agreement_end_date"   => "2020-02-08 00:00:00",
                    ":states"               => "Victoria",
                    ":abn"                  => "87100090560"
                );
                break;

            // Orbit Homes
            case 9:
                $data = array(
                    ":isoCode"              => "AUD",
                    ":price"                => 845.90,
                    ":cname"                => "Orbit Homes Australia Pty Ltd",
                    ":address_city"         => "Ascot Vale",
                    ":address_postal_code"  => "3032",
                    ":address_street"       => "286 Mt Alexander Road",
                    ":address_region"       => "Victoria",
                    ":address_country"      => "Australia",
                    // 10th November 2017
                    ":agreement_end_date"   => "2017-11-10 00:00:00",
                    ":states"               => "Victoria",
                    ":abn"                  => "11080735771"
                );
                break;

            // Bold Properties
            case 15:
                $data = array(
                    ":isoCode"              => "AUD",
                    ":price"                => 658.90,
                    ":cname"                => "Bold Properties Pty Ltd",
                    ":address_city"         => "North Lakes",
                    ":address_postal_code"  => "4509",
                    ":address_street"       => "49 Flinders Parade",
                    ":address_region"       => "Queensland",
                    ":address_country"      => "Australia",
                    // 10th November 2017
                    ":agreement_end_date"   => "2017-11-10 00:00:00",
                    ":states"               => "Queensland",
                    ":abn"                  => "43127545804"
                );
                break;

            // Mega Homes
            case 17:
                $data = array(
                    ":isoCode"              => "AUD",
                    ":price"                => 880,
                    ":cname"                => "Mega Homes Pty Ltd ",
                    ":address_city"         => "Williamstown",
                    ":address_postal_code"  => "3016",
                    ":address_street"       => "10 Ponting St",
                    ":address_region"       => "Victoria",
                    ":address_country"      => "Australia",
                    // 10th November 2017
                    ":agreement_end_date"   => "2019-07-31 00:00:00",
                    ":states"               => "Victoria",
                    ":abn"                  => "93063797473"
                );
                break;

            // Mimosa Homes
            case 18:
                $data = array(
                    ":isoCode"              => "AUD",
                    ":price"                => 880,
                    ":cname"                => "Mimosa Homes Pty Ltd",
                    ":address_city"         => "Derrimut",
                    ":address_postal_code"  => "3030",
                    ":address_street"       => "1/123 Elgar Road",
                    ":address_region"       => "Victoria",
                    ":address_country"      => "Australia",
                    // 10th November 2017
                    ":agreement_end_date"   => "2019-08-23 00:00:00",
                    ":states"               => "Victoria",
                    ":abn"                  => "12123989408"
                );
                break;

            case 999:
                $data = array(
                    ":isoCode"              => "AUD",
                    ":price"                => 1,
                    ":cname"                => "Billing Test Company",
                    ":address_city"         => "",
                    ":address_postal_code"  => "",
                    ":address_street"       => "",
                    ":address_region"       => "Victoria",
                    ":address_country"      => "Australia",
                    // September 6th 2017
                    ":agreement_end_date"   => "2030-01-01 00:00:00",
                    ":states"               => "Victoria",
                    ":abn"                  => ""
                );
                break;
        }

        if ( $data ) {
            echo "loading account defaults...<br/>";

            $data[":id"] = $account->getId();
            $statement = $db->prepare(
                "UPDATE billing_account
                 SET
                    currencyIsoCode     = :isoCode,
                    price               = :price,
                    start_next_month    = 1,
                    `name`              = :cname,
                    address_city        = :address_city,
                    address_postal_code = :address_postal_code,
                    address_street      = :address_street,
                    address_region      = :address_region,
                    address_country     = :address_country,
                    agreement_end_date  = :agreement_end_date,
                    states              = :states,
                    customer            = '',
                    abn                 = :abn
                WHERE id=:id"
            );
            if ( $statement->execute( $data ) ) {
                echo "loading or creating braintree customer...<br/>";

                // create the braintree customer for this object
                $account->loadCustomerDetails();

                return $account;
            }
        }
    }

    echo "error creating or setting up the account.<br/>";

    return NULL;
}

?>