
function getStatusLabel( status ) {
    var statusLabel = "label-info";
    var statusText = "";

    switch ( status ) {
        case 'open':
            statusLabel = "label-warning";
            statusText = "working";
            break;
        case 'problem':
            statusLabel = "label-danger";
            statusText = "attention";
            break;
        case 'closed':
            // $statusLabel = "label-default";
            statusLabel = "label-success";
            statusText = "active";
            break;
    }

    return "<span class='label "+statusLabel+"'>" + statusText + "</span>";
}

function getBillingLabel( data ) {
	return "";

    if ( data && data.outstandingAmount > 0 ) {
        return " <span class='label label-danger'>$" + data.outstandingAmount + " outstanding</span>";
    }

    return "";
}

function PORTAL_HENLEY_APIPATH() {
    return PORTAL_APIPATH.replace("portal", "portal-henley");
}

// Load a list of all tickets
function planManageWidget(widget_id, options) {
    var widget = $('#' + widget_id);
    widget.html("Loading Plans...");

    var sort = "asc";
    if (options['sort'] && options['sort'] == 'asc') {
        sort = [[0,0]];
    }
    else {
        sort = [[0,1]];
    }

    var title = "<i class='fa fa-folder-open-o'></i> Floorplans";
    if (options['title'])
        title = "<i class='fa fa-folder-open-o'></i> " + options['title'];
    var limit = 10;
    if (options['limit'])
        limit = options['limit'];
    // Default columns to display:
    var columns = { };
    if (options['columns'])
        columns = options['columns'];

    var headers = { };
    if (options['headers'])
        headers = options['headers'];

    // Load the tickets
    var current_user = loadCurrentUser();
    csrf_token = current_user['csrf_token'];

    // Ok, set up the widget with its columns
    var html = "<input type='hidden' name='csrf_token' value='" + csrf_token + "' />";

    html += "<div class='row'><div class='col-md-12 text-right'><p class='well well-sm'>" +
            "<button type='button' class='btn btn-warning deleteAllTickets' data-toggle='modal'>" +
            "<i class='fa fa-plus-square'></i>&nbsp;&nbsp;Delete All Plans</button></p></div></div>";

	html += "<div>";
	html += "<div>";

    var url = PORTAL_HENLEY_APIPATH() + "pm_load_plans.php";

    $.getJSON( url, { limit: limit, ajax:true, state: options.state })
        .done(function( data ) {
            data = data.data;
            // Don't bother unless there are some records found
            if (Object.keys(data).length > 0) {
                html+= "<div class='table-responsive'><table class='table table-hover table-bordered table-striped tablesorter'>" +
                "<thead><tr>";
                jQuery.each(columns, function(name, header) {
                    html += "<th>" + header + " <i class='fa fa-sort'></th>";
                });
                html += "</tr></thead><tbody></tbody></table>";
            } else {
                html += "<div class='alert alert-info'>No floorplan found.</div>";
            }

            var planIndex=1;
            $('#' + widget_id).html(html);
            if (Object.keys(data).length > 0) {
                // Don't bother unless there are some records found
                jQuery.each(data, function(idx, record) {
                    var row, isMeta, style = "";

                    if (record['isMetaDataFile']=='1') {
                        style = "style='background-color: #DDEEFF;'";
                        isMeta = true;
                    }   else {
                        isMeta = false;
                    }

                    row = "<tr id='plan-row-"+record['id']+"'>";

                    jQuery.each(columns, function(name, header) {
                        if (name == 'file_name') {
                            var formattedRowData = {};
                            formattedRowData['file_id'] = record['id'];

                            if (isMeta) {
                                formattedRowData['file_name']      = record["name"];
                                formattedRowData['data_file_name'] = padZeroes(0)+'_metadata_record';
                            }   else {
                                formattedRowData['file_name']      = planIndex+". "+record["name"];
                                formattedRowData['data_file_name'] = padZeroes(planIndex)+". "+record["name"];
                                ++planIndex;
                            }

                            var template = Handlebars.compile(
                                "<td "+style+" data-text='{{data_file_name}}'>" +
                                    "<div class='h5'>" +
                                        "{{file_name}}" +
                                    "</div>" +
                                "</td>"
                            );
                            row += template(formattedRowData);
                        }
                        if (name=='owner') {
                            var formattedRowData = {};
                            formattedRowData['owner_name'] = record['owner_name'];
                            var template = Handlebars.compile(
                                "<td "+style+" data-text='{{owner_name}}'>" +
                                    "<div class='h5'>" +
                                        "{{owner_name}}" +
                                    "</div>" +
                                "</td>"
                            );
                            row += template(formattedRowData);
                        }
                        if (name == 'last_update') {
                            var formattedRowData = {};
                            formattedRowData = formatDate1(record['updatedAtTs']*1000);
                            var template = Handlebars.compile("<td "+style+" data-date='{{stamp}}'><div class='h5'>{{day}}, {{date}} at {{time}} </div></td>");
                            row += template(formattedRowData);
                        }
                        if (name == 'action') {
                            var template = Handlebars.compile("<td "+style+"><div class='btn-group'>" +
                                (isMeta?"":"<button type='button' data-id='{{ticket-id}}' class='btn {{btn-class}} deleteTicket'>{{btn-name}}</button>") +
                                "</div></td>");

                            var formattedRowData = {};

                            formattedRowData['btn-class'] = 'btn-error';
                            formattedRowData['btn-name'] = 'Delete';
                            formattedRowData['ticket-id'] = record['id'];
                            row += template(formattedRowData);
                        }
                    });

                    // Add the row to the table
                    row += "</tr>";
                    $('#' + widget_id + ' .table > tbody:last').append(row);
                });

                // Initialize the tablesorter
                $('#' + widget_id + ' .table').tablesorter({
                    debug: false,
                    sortList: sort,
                    headers: headers
                });
            }

            // delete buttons
            widget.on('click', '.deleteTicket', function () {
                var btn = $(this);
                deletePlan(btn.data('id'))
            });

            widget.on('click', '.deleteAllTickets', function () {
                deleteAllPlans();
            });

            return false;
        }).fail(function() {
        })
        .always(function() {
        });
}

function padZeroes(val) {
    if (val<10)  return "000"+val;
    if (val<100) return  "00"+val;
    if (val<1000)return   "0"+val;
    return ""+val;
}

function deletePlan(id) {
    var url = PORTAL_HENLEY_APIPATH() + "pm_delete_plans.php";

    $.ajax({
        type: "POST",
        url: url,
        data: { planId: id, ajaxMode:true }
    }).done(function(result) {
        resultJSON = processJSONResult(result);
        // display alerts?
        if (resultJSON.hasOwnProperty("alerts") && resultJSON.alerts.length>0) {
            alertWidgetResponse('display-alerts', resultJSON.alerts);
        }
        
        // done!
        if (resultJSON.hasOwnProperty("success")) {
            // delete this row from the list
            $('#plan-row-'+id).remove();
        }
    });
}

function deleteAllPlans() {
    if (confirm("Are you sure you want to delete all the plans?")) {
        var url = PORTAL_HENLEY_APIPATH() + "pm_delete_plans.php";

        $.ajax({
            type: "POST",
            url: url,
            data: {planId:-1, deleteEverything:true, ajaxMode: true}
        }).done(function (result) {
            resultJSON = processJSONResult(result);
            // display alerts?
            if (resultJSON.hasOwnProperty("alerts") && resultJSON.alerts.length > 0) {
                alertWidgetResponse('display-alerts', resultJSON.alerts);
            }

            // done!
            if (resultJSON.hasOwnProperty("success")) {
                // delete this row from the list
                $('#plan-row-' + id).remove();
            }
        });
    }
}