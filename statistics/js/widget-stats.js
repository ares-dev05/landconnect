
const MODE_CHART = "dxChart";
const MODE_TABLE = "table";

function showAsChart()
{
    viewMode = MODE_CHART;
    applyFilters();
}
function showAsTable()
{
    viewMode = MODE_TABLE;
    applyFilters();
}

function selectCompany( v )
{
    cId = v;
    applyFilters();
}
function selectState( v )
{
    stateId = v;
    // reset users as they become invalidated in another state
    users = [];
    applyFilters();
}
function selectHouse( v )
{
    page = "house.breakdown";
    houseName = v;
    applyFilters();
}

function getCookedURL()
{
    var url = "?page="+page+"&state="+stateId+"&mode="+viewMode+"&from="+fromDate+"&to="+toDate+"&users="+users.join()+"&company="+cId;
    if ( typeof houseName !== "undefined" ) {
        url += "&house=" + houseName;
    }

    return url;
}

function applyFilters()
{
    document.location.href = getCookedURL();
}

function statsDisplay(
    displayType,
    tableTitle,
    container,
    graphType,
    statType,
    statAggr,
    company,
    state,
    labelGlue,
    houseName
)
{
    // Load the data and generate the rows.
    var url = STATS_APIPATH + "fetch_stats.php";

    var data = {
        type:   statType,
        aggr:   statAggr,
        company:company,
        state:  state,
        house:  houseName,
        // additional query filters
        users:  users.join(),
        fromDate: fromDate,
        toDate: toDate,
        ajax:   true
    };

    $.ajax({
        type: "POST",
        url: url,
        data: data,
        dataType: 'json',
        cache: false
    })
        .done(function( data ) {
            $("#"+container).html("");
            if ( displayType == "dxChart" ) {
                displayDXChart( tableTitle, container, graphType, labelGlue, statAggr, statType, data );
            }   else
            if ( displayType == "table" ) {
                displayTable( tableTitle, container, data, statType );
            }
            return false;
        }).fail(function( jqXHR, textStatus, errorThrown ) {
            // @TEMP
            $("#display-alerts").html(jqXHR.responseText);

            console.log( "error " );
            console.log(jqXHR);
            console.log(textStatus);
            console.log(errorThrown);
        })
        .always(function() {
        });
}

function displayTable( tableTitle, container, tableData, statType ) {
    var widget = $('#' + container),
        sort = [[0,0]], // ???
        title = "<i class='fa fa-folder-open-o'></i> " + tableTitle,
        limit = 1000,
        columns = {},
        headers = {};

    // Ok, set up the widget with its columns
    widget.css("height", "auto");

    var html = "";

    html += "<div class='panel panel-default'><div class='panel-heading'><h3 class='panel-title'>" + title + "</h3></div>";
    html += "<div class='panel-body'>";

    // build the columns & headers
    var colIndx = 0;
    jQuery.each(tableData.columns, function(idx, column) {
        columns[ column.name ] = column.display;

        if ( column.datatype == "text" ) {
            headers[ colIndx ] = { sorter: 'text' };
        }   else
        if ( column.datatype == 'numeric' ) {
            headers[ colIndx ] = { sorter: 'digit' };
        }   else
        if ( column.datatype == 'date' ) {
            headers[ colIndx ] = { sorter: 'metadate' };
        }
        ++colIndx;
    });

    var data = tableData.results;

    // build the header of the table; do this only of data is available
    if (Object.keys(data).length > 0) {
        html+= "<div class='table-responsive'><table class='table table-bordered table-hover table-striped tablesorter'>" +
        "<thead><tr>";
        jQuery.each(columns, function(name, header) {
            html += "<th>" + header + " <i class='fa fa-sort'></th>";
        });
        html += "</tr></thead><tbody></tbody></table>";
    } else {
        html += "<div class='alert alert-info'>No stats available.</div>";
    }

    // build the table
    $('#' + container).html(html);
    if (Object.keys(data).length > 0) {
        // Don't bother unless there are some records found
        jQuery.each(data, function(idx, record) {
            var row = "<tr>";

            jQuery.each(tableData.columns, function(idx, column) {
                if ( column.type == "index" && statType == "house" ) {
                    // add hyperlinks for house stat tables
                    var formattedRowData = {data: record[column.name] };
                    var template = Handlebars.compile(
                        "<td>" +
                        "<div class='h5'>" +
                        "   <a href='#' onclick='selectHouse(\""+record[column.name]+"\");'>{{data}}</a>" +
                        "</div>" +
                        "</td>"
                    );
                    row += template( formattedRowData );
                }   else
                if ( column.datatype == "text" || column.datatype == "numeric" ) {
                    if ( typeof record[column.name] !== "undefined" ) {
                        var formattedRowData = {data: record[column.name] };
                        var template = Handlebars.compile(
                            "<td>" +
                            "<div class='h5'>" +
                            " {{data}} " +
                            "</div>" +
                            "</td>"
                        );
                    }   else {
                        var formattedRowData = {data: "N/A" };
                        var template = Handlebars.compile(
                            "<td>" +
                            "<div class='h5'>" +
                            "<span style='color:#CCCCCC;'>{{data}}</span>" +
                            "</div>" +
                            "</td>"
                        );
                    }
                    row += template( formattedRowData );
                }   else
                if ( column.datatype == "date" ) {
                    var formattedRowData = {
                        stamp : sqlDateToMilliseconds( record[ column.name ] ),
                        date  : record[ column.name ]
                    };
                    var template = Handlebars.compile(
                        "<td data-date='{{stamp}}'>" +
                        "{{date}}" +
                        "</td>"
                    );
                    row += template( formattedRowData );
                }
            });

            // Add the row to the table
            row += "</tr>";
            $('#' + container + ' .table > tbody:last').append(row);
        });

        // Initialize the tablesorter
        $('#' + container + ' .table').tablesorter({
            debug: false,
            sortList: sort,
            headers: headers
        });
    }
}

function valOrNA( value ) {
    return typeof value !== "undefined" ? value : "N/A";
}

function displayDXChart( tableTitle, container, graphType, labelGlue, statAggr, statType, data ) {
    var series = [],
        valueAxis = {
            position: "left",
            title: {
                text: data.settings.display
            },
            valueType: "numeric"
        },
        legend = {
            verticalAlignment: "bottom",
            horizontalAlignment: "center",
            itemTextPosition: 'top'
        },
        rotated = false;

    // prepare the colors for the graph
    if ( graphType=="line" ) {
        var colors = ["#494FC9"];
    }   else {
        var colors = ["#039be5","#f44336","#ff9800","#673ab7","#00e676","#ffeb3b"];
    }

    var cIndx=0;
    // build the series array
    jQuery.each(data.columns, function(idx, column) {
        if ( column.type == "numeric" ) {
            series.push({
                valueField: column.name,
                name: column.display,
                color: colors[cIndx%colors.length]
            });
            ++cIndx;
        }
    });

    if ( statAggr == "user" ||
        statType == "house" ||
        statType == "house.breakdown" ) {
        // rotate the axes
        rotated = true;
        $("#"+container).height(
            205 + 25 * data.results.length
        );
    }

    /*
    console.log("-------------------------------------------");
    console.log("dxChart.dataSource");
    console.log(data.results);
    console.log("dxChart.series");
    console.log(series);
    console.log("dxChart.legend");
    console.log(legend);
    console.log("dxChart.valueAxis");
    console.log(valueAxis);
    console.log("-------------------------------------------");
    */

    $("#"+container).dxChart({
        dataSource: data.results,
        commonSeriesSettings: {
            argumentField: "col_index",
            type: graphType
        },
        series: series,
        legend: legend,
        valueAxis: valueAxis,
        // title: data.settings.display,
        title: tableTitle,
        tooltip: {
            enabled: true,
            location: "edge",
            customizeTooltip: function (arg) {
                return {
                    text: arg.valueText+labelGlue+arg.argument
                };
            }
        },
        rotated: rotated,

        onArgumentAxisClick: function(info) {
            page = "house.breakdown";
            houseName = info.argument;
            applyFilters();
        }
    });
}