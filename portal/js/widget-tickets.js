/* Widget for modifying tickets*/
/*
 // List group info
 var group_id = record['id'];
 var ticket_name = '@TODO';
 var date_added = record['createdAt'];
 var last_update = '@TODO';
 var status = record['status'];
 */

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

// Load a list of all tickets
function ticketsWidget(widget_id, options) {
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
    var show_add_button = 'true';
    if (options['show_add_button'])
        show_add_button = options['show_add_button'];
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

    if (show_add_button == 'true') {
        html += "<div class='row'><div class='col-md-12 text-right'><p class='well well-sm'>" +
        "<button type='button' class='btn btn-success createTicketTop' data-toggle='modal' data-target='#user-create-dialog'>" +
        "<i class='fa fa-plus-square'></i>  Create New Floorplan</button></p></div></div>";
    }

	html += "<div>";
	html += "<div>";

    // Load the data and generate the rows.
    var url = PORTAL_APIPATH + "pt_load_all_tickets.php";
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

            if (show_add_button == 'true') {
                html += "<div class='row'><div class='col-md-6'>" +
                "<button type='button' class='btn btn-success createTicket' data-toggle='modal' data-target='#user-create-dialog'>" +
                "<i class='fa fa-plus-square'></i>  Create New Floorplan</button></div><div class='col-md-6 text-right'>" +
                "<a href='index.php'>View All Floorplans <i class='fa fa-arrow-circle-right'></i></a>" +
                "</div></div></div></div>";
            } else {
                html += "<div class='row'><div class='col-md-12 text-right'>" +
                "<a href='index.php'>View All Floorplans <i class='fa fa-arrow-circle-right'></i></a>" +
                "</div></div></div></div>";
            }

            $('#' + widget_id).html(html);
            if (Object.keys(data).length > 0) {
                // Don't bother unless there are some records found
                jQuery.each(data, function(idx, record) {
                    var row = "<tr>";
                    jQuery.each(columns, function(name, header) {
                        if (name == 'ticket_name') {
                            var formattedRowData = {};
                            formattedRowData['ticket_id'] = record['id'];
                            // formattedRowData['ticket_name'] = record['ticketName'];
                            if ( record['floorName'] != null && record['floorName'] != '' )
                                formattedRowData['ticket_name'] = record['floorName'];
                            else
                                formattedRowData['ticket_name'] = record['ticketName'];

                            var template = Handlebars.compile(
                                "<td data-text='{{ticket_name}}'>" +
                                    "<div class='h5'>" +
                                        " <a href='view_ticket.php?id={{ticket_id}}'>{{ticket_name}}</a> "+
                                        getStatusLabel( record['status'] ) +
                                        // getBillingLabel( record['billingData'] ) +
                                    "</div>" +
                                "</td>"
                            );
                            row += template(formattedRowData);
                        }
                        if (name == 'company') {
                            var formattedRowData = {};
                            formattedRowData['company_name'] = record['companyName'];

                            var template = Handlebars.compile(
                                "<td data-text='{{company_name}}'>" +
                                "<div class='h5'>" +
                                " {{company_name}} "+
                                "</div>" +
                                "</td>"
                            );
                            row += template(formattedRowData);
                        }
                        if (name == 'state') {
                            var formattedRowData = {};
                            formattedRowData['state_name'] = record['stateName'];

                            var template = Handlebars.compile(
                                "<td data-text='{{state_name}}'>" +
                                "<div class='h5'>" +
                                " {{state_name}} "+
                                "</div>" +
                                "</td>"
                            );
                            row += template(formattedRowData);
                        }
                        if (name == 'range') {
                            var formattedRowData = {};
                            formattedRowData['range_name'] = record['rangeName'];

                            var template = Handlebars.compile(
                                "<td data-text='{{range_name}}'>" +
                                "<div class='h5'>" +
                                " {{range_name}} "+
                                "</div>" +
                                "</td>"
                            );
                            row += template(formattedRowData);
                        }

                        if (name == 'status') {
                            var formattedRowData = {};
                            formattedRowData['status'] = record['status'];
                            var template = Handlebars.compile(
                                "<td>" +
                                    "<div class='h5'>" +
                                        "{{status}}" +
                                    "</div>" +
                                "</td>"
                            );
                            row += template(formattedRowData);
                        }
                        if (name == 'date_added') {
                            var formattedRowData = {};
                            formattedRowData = formatDate1(record['createdAtTs']*1000);
                            var template = Handlebars.compile("<td data-date='{{stamp}}'><div class='h5'>{{day}}, {{date}} </div></td>");
                            row += template(formattedRowData);
                        }
                        if (name == 'last_update') {
                            var formattedRowData = {};
                            formattedRowData = formatDate1(record['updatedAtTs']*1000);
                            var template = Handlebars.compile("<td data-date='{{stamp}}'><div class='h5'>{{day}}, {{date}} </div></td>");
                            row += template(formattedRowData);
                        }
                        if (name == 'action') {
                            var template = Handlebars.compile("<td><div class='btn-group'>" +
                            "<button type='button' data-id='{{ticket-id}}' class='btn {{btn-class}} editTicket'>{{btn-name}}</button>" +
                            "</div></td>");
                            var formattedRowData = {};

                            // " <a href='view_ticket.php?id={{ticket_id}}'>{{ticket_name}}</a> "+

                            formattedRowData['btn-class'] = 'btn-primary';
                            formattedRowData['btn-name'] = 'Open';
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

            // Link the "Create User" buttons
            widget.on('click', '.createTicket', function () {
                ticketCreateForm('ticket-create-dialog');
            });
            widget.on('click', '.createTicketTop', function () {
                ticketCreateForm('ticket-create-dialog');
            });

            // Link the dropdown buttons from table of users
            widget.on('click', '.editTicket', function () {
                var btn = $(this);
                var ticket_id = btn.data('id');
                // redirect to ticket
                window.location.href = "view_ticket.php?id="+ticket_id;
            });

            return false;
        }).fail(function() {
        })
        .always(function() {
        });
}

function ticketCreateForm(box_id) {
    // Delete any existing instance of the form with the same name
    if( $('#' + box_id).length ) {
        $('#' + box_id).remove();
    }
    var data = {
        box_id: box_id,
        render_mode: 'modal'
    };

    // Generate the form
    $.ajax({
        type: "GET",
        url: PORTAL_FORMSPATH + "form_create_ticket.php",
        data: data,
        dataType: 'json',
        cache: false
    })
        .fail(function(result) {
            addAlert("danger", "Oops, looks like our server might have goofed.  If you're an admin, please check the PHP error logs.");
            alertWidget('display-alerts');
        })
        .done(function(result) {
            // Append the form as a modal dialog to the body
            $( "body" ).append(result['data']);
            $('#' + box_id).modal('show');
        });
}

// Display ticket info in a panel
function ticketDisplay(box_id, ticket_id) {
    // Generate the form
    $.ajax({
        type: "GET",
        url: PORTAL_FORMSPATH + "form_ticket.php",
        data: {
            box_id: box_id,
            render_mode: 'panel',
            ticket_id: ticket_id,
            disabled: true,
            button_submit: false,
            button_edit: true,
            button_disable: true,
            button_activate: true,
            button_delete: true
        },
        dataType: 'json',
        cache: false
    })
        .fail(function(result) {
            addAlert("danger", "Oops, looks like our server might have goofed.  If you're an admin, please check the PHP error logs.");
            alertWidget('display-alerts');
        })
        .done(function(result) {
            $('#' + box_id).html(result['data']);

            // events for status changing
            var btn;
            if ( ( btn = $('#'+box_id+' #markResolvedBtn') ) != null ) {
                btn.click(function(e) {
                    e.preventDefault();
                    setTicketStatus('closed');
                    $("#labelclosed").addClass('active');
                    selectTicketForm( 'file-upload-form' );
                    $('#' + box_id + ' #textMessage').focus();
                });
            }
            if ( ( btn = $('#' + box_id + ' #reportIssueBtn') ) != null ) {
                btn.click(function (e) {
                    e.preventDefault();
                    setTicketStatus('problem');
                    $("#labelproblem").addClass('active');
                    selectTicketForm( 'file-upload-form' );
                    $('#' + box_id + ' #textMessage').focus();
                });
            }
            if ( ( btn = $('#' + box_id + ' #deletePlanBtn') ) != null ) {
                btn.click(function (e) {
                    e.preventDefault();
                    if (confirm("Are you sure you want to delete this plan?")) {
                        deleteTicket(ticket_id);
                    }
                });
            }
            if ( ( btn = $('#' + box_id + ' #reOpenTicket') ) != null ) {
                btn.click(function (e) {
                    // setTicketStatus(ticket_id, 'open');
                });
            }

            $("#labelproblem").click(function(e) {
                e.preventDefault();
                setTicketStatus('problem');
            });
            $("#labelclosed").click(function(e) {
                e.preventDefault();
                setTicketStatus('closed');
            });

            /**
             * FORMS
             *   form-controls
             *   // message-add-form
             *   file-upload-form
             *
             * BUTTONS
             *   // showCommentForm
             *   showUploadForm
             */
            // events for file / message forms
            $('#' + box_id + ' .showUploadForm').click(function(e){
                e.preventDefault();
                selectTicketForm( 'file-upload-form' );
                $('#' + box_id + ' #textMessage').focus();
                $('html, body').animate({
                    scrollTop: $('#uploadFileForm').offset().top-100
                }, 300);
            });
            $('#' + box_id + ' .btnCancelForm').click(function(e){
                e.preventDefault();
                selectTicketForm( 'form-controls' );
            });

            // show the controls form by default
            selectTicketForm( "form-controls" );

            // add event for uploading files
            $('#' + box_id + ' #form-upload').submit(function(e) {
                // e.preventDefault();
                var errorMessages = validateFormFields(box_id);
                if (errorMessages.length > 0) {
                    $('#' + box_id + ' .dialog-alert').html("");
                    $.each(errorMessages, function (idx, msg) {
                        $('#' + box_id + ' .dialog-alert').append("<div class='alert alert-danger'>" + msg + "</div>");
                    });
                    // stop the submit
                    e.preventDefault();
                } else {
                    // do nothing; the form will be submitted
                }
            });
        });
}

function selectTicketForm( type ) {
    var forms = [
        "form-controls",
        // "message-add-form",
        "file-upload-form"
        ], i;

    for (i=0; i<forms.length; ++i) {
        if ( type == forms[i] )
            $( "#"+forms[i] ).show();
        else
            $( "#"+forms[i] ).hide();
    }
}

function createTicket(dialog_id) {
    var data = {
        // csrf_token  : $('#' + dialog_id + ' input[name="csrf_token"]' ).val(),
        ajaxMode    : "true"
    };
    var url = PORTAL_APIPATH + "pt_create_ticket.php";

    $.ajax({
        type: "POST",
        url: url,
        data: data
    }).done(function(result) {
        resultJSON = processJSONResult(result);
        // display alerts
        alertWidgetResponse('display-alerts', resultJSON.alerts);
        // reload the table instead of the entire page
        loadTicketsWidget();
    });
}

function deleteTicket(ticket_id) {
    var data = {
        ticketId    : ticket_id,
        ajaxMode    : "true"
    };

    $.ajax({
        type: "POST",
        url: PORTAL_APIPATH + "pt_delete_ticket.php",
        data: data
    }).done(function(result) {
        resultJSON = processJSONResult(result);
        // display alerts
        alertWidgetResponse('display-alerts', resultJSON.alerts);
        // reload the table instead of the entire page
        window.location = '/portal';
    });
}

// Update user with specified data from the dialog
function addTicketMessage(dialog_id, ticket_id) {
    var errorMessages = validateFormFields(dialog_id);
    if (errorMessages.length > 0) {
        $('#' + dialog_id + ' .dialog-alert').html("");
        $.each(errorMessages, function (idx, msg) {
            $('#' + dialog_id + ' .dialog-alert').append("<div class='alert alert-danger'>" + msg + "</div>");
        });
        return false;
    }

    var data = {
        ticketId    : ticket_id,
        textMessage : $('#' + dialog_id + ' textarea[name="textMessage"]' ).val(),
        csrf_token  : $('#' + dialog_id + ' input[name="csrf_token"]' ).val(),
        ajaxMode    : "true"
    };

    var url = PORTAL_APIPATH + "pt_add_node.php";
    $.ajax({
        type: "POST",
        url: url,
        data: data
    }).done(function(result) {
        resultJSON = processJSONResult(result);
        // display alerts
        alertWidgetResponse('display-alerts', resultJSON.alerts);
        // reload the ticket instead of the entire page
        displayCurrentTicket();
    });
}

function setTicketStatus(status) {
    var stats = [ 'problem', 'closed'], i;
    var classes = [ 'btn-danger', 'btn-success' ];

    for (i=0; i<stats.length; ++i) {
        // activate the label visually
        if ( status == stats[i] ) {
            $("#label"+stats[i]).removeClass('btn-default');
            $("#label"+stats[i]).removeClass('active');
            $("#label"+stats[i]).addClass(classes[i]);
        }
        else {
            $("#label"+stats[i]).removeClass('active');
            $("#label"+stats[i]).removeClass(classes[i]);
            $("#label"+stats[i]).addClass('btn-default');
        }
    }

    $("#status").val(status);
}