$(function(){
    //setup the date picker
    generate_year_select_options(
        $("#tp_year_selector"), earliest_available, latest_available,
        parseInt(Cookies.get("selected_year"), 10));
    generate_month_select_options(
        $("#tp_month_selector"), earliest_available, latest_available,
        parseInt(Cookies.get("selected_year"), 10), parseInt(Cookies.get("selected_month"), 10));

    $("#tp_year_selector").select2({minimumResultsForSearch: -1});
    $("#tp_month_selector").select2({minimumResultsForSearch: -1});

    $("#tp_year_selector").on("change", function(event){
        var el = $(event.target);
        Cookies.set("selected_year", el.val());
        generate_month_select_options(
            $("#tp_month_selector"), earliest_available, latest_available,
            parseInt(Cookies.get("selected_year"), 10), parseInt(Cookies.get("selected_month"), 10));
    });
    $("#tp_month_selector").on("change", function(event){
        var el = $(event.target);
        Cookies.set("selected_month", el.val());
    });

    $(".time_period_options select").on("change", function(event){
        if($("#export_csv_button").length == 0){
            $("#time_period_selector").append("<input type=\"button\" value=\"Export as CSV\" class=\"search_button export_csv_button\" id=\"export_csv_button\"/>");
        }
        $("#export_csv_button").off("click").on("click", function(){
            var csv_url = generate_report_url();
            csv_url += "/csv";
            location.href= csv_url;
        }).show();
    });

    $("#generate_report_button").click(function(){
        load_compliance_report(
            $("#search_results_display"),
            $("#tp_month_selector").val(),
            $("#tp_year_selector").val()
        );
    });
    $("#tp_month_selector").change();
    $.ajaxSetup({timeout:30000});


});

var generate_report_url = function(event){
    var month = $("#tp_month_selector").val();
    var year = $("#tp_year_selector").val();
    var start_date = moment().year(year).month(month - 1).date(1).hour(0).minute(0).seconds(0);
    var end_date = moment(start_date);
    end_date.add(1, "months").subtract(1, "days");
    var report_url = "/compliance/get_booking_report/project/";
    report_url += start_date.format("YYYY-MM-DD") + "/";
    report_url += end_date.format("YYYY-MM-DD");
    return report_url;
};

var load_compliance_report = function(destination_object, month, year){
    $("#compliance_loading_screen").show();
    $("#booking_results_error").hide();
    $("#search_results_display").fadeOut();
    $("#booking_results_display").empty();
    $(".time_period_options select, .time_period_options input").disable();
    $("#report_loading_status").spin();

    report_url = generate_report_url();
    report_url += "/json";

    var booking_report_return = $.get(report_url, function(response) {
        var reference_period = moment(response.start_time).format("MMMM YYYY");
        var error_message = "";
        if(response.booking_results.length == 0){
            error_message = "No Results Located for Reporting Period<br />" + reference_period;
        }else{
            error_message = "";
        }
        $("#booking_results_error").html(error_message);
        if(error_message.length > 0){
            return;
        }
        $("#compliance_loading_screen").hide();
        $(".search_results_display").show();
        $(".booking_results_header").show();
        $("#booking_results_display").jsGrid({
            height: "auto",
            width: "100%",
            sorting: true,
            paging: false,
            filtering: true,
            data: response.booking_results,
            controller : {
                loadData : function(filter) {
                    result = response.booking_results;
                    for (var prop in filter) {
                        if (filter[prop] && filter[prop].length > 0) {
                            result = $.grep(response.booking_results, function (item) {
                                var regexp = new RegExp(filter[prop], "gi");
                                if (String(item[prop]).match(regexp)) {
                                    return item;
                                }
                            });
                            break;
                        }
                    }
                    return result;
                },
            },
            fields: [
                {
                    name: "project_id", title: "Project ID", width: "8%", type: "text", headercss: "compliance_table_header",
                    cellRenderer: function(value, item) {
                        return $("<td>", {
                            "class": "project_id_container " + item.project_color_class,
                            "text": value,
                            "title": item.project_title
                        });
                    }
                },
                {
                    name: "instrument_id", title: "Instrument ID", width: "9%", type: "text", headercss: "compliance_table_header",
                    cellRenderer: function(value, item) {
                        return $("<td>", {
                            "class": "instrument_id_container " + item.instrument_color_class,
                            "text": value
                        });
                    }
                },
                {
                    name: "project_type", title: "Project Type", type: "text", width: "14%"
                },
                {
                    name: "project_pi", title: "Principal Investigator",
                    type: "text", headercss: "compliance_table_header", width: "12%"
                },
                {
                    name: "instrument_name", title: "Instrument", type: "text",
                    headercss: "compliance_table_header", width: "25%"
                },
                {
                    name: "booked_by", title: "Booked By", type: "text",
                    headercss: "compliance_table_header", width: "12%"
                },
                {
                    name: "booking_count", title: "Number of Bookings",
                    headercss: "compliance_table_header", width: "10%", align: "center"
                },
                {
                    name: "upload_count", title: "Data Uploads Count",
                    headercss: "compliance_table_header", width: "10%", align: "center"
                }
            ]
        });
        $(".no_booking_results_header").show();
        $("#no_booking_results_display").jsGrid({
            height: "auto",
            width: "100%",
            sorting: true,
            paging: false,
            data: response.no_booking_results,
            fields: [
                { name: "project_id", title: "Project ID", type: "text" },
                { name: "project_type", title: "Project Type", type: "text" },
                { name: "project_pi", title: "Principal Investigator", type: "text" },
                { name: "actual_start_date", title: "Actual Start Date", type: "complianceDateField" },
                { name: "actual_end_date", title: "Actual End Date", type: "complianceDateField" },
                // { name: "closed_date", title: "Closing Date", type: "complianceDateField" },
                // { name: "last_change_date", title: "Last Updated", type: "complianceDateField" }
            ]
        });

    });
    booking_report_return.always(function(){
        $(".time_period_options select, .time_period_options input").enable();
        if($("#booking_results_error").text().length > 0){
            $("#compliance_loading_screen").hide();
            $("#booking_results_error").slideDown("400");
        }
    });
};

var generate_year_select_options = function(parent_obj, min_date, max_date, selected_year){
    var today = moment();
    var min_date_obj = moment(min_date);
    // var max_date_obj = moment(max_date);
    var min_year = min_date_obj.year();
    // var max_year = max_date_obj.year();
    var current_year = today.year();
    if(!selected_year){
        selected_year = current_year;
    }
    // var year_list = {};
    parent_obj.empty();
    while(current_year >= min_year){
        var options = {value: current_year};
        if(current_year == selected_year){
            options["selected"] = "selected";
        }
        $("<option/>", options).text(current_year).appendTo(parent_obj);
        current_year--;
    }
    return parent_obj;
};

var generate_month_select_options = function(parent_obj, min_date, max_date, selected_year, selected_month){
    var today = moment();
    if(!selected_year){
        selected_year = today.year();
    }
    if(!selected_month){
        selected_month = parseInt(today.format("M"),10);
    }
    var min_date_obj = moment(min_date);
    var max_date_obj = moment(max_date);
    var earliest_month = 1;
    var latest_month = 12;
    if(selected_year == min_date_obj.year()){
        earliest_month = parseInt(min_date_obj.format("M"), 10);
        selected_month = earliest_month;
    }
    if(selected_year == max_date_obj.year()){
        latest_month = parseInt(max_date_obj.format("M"), 10);
        selected_month = latest_month < selected_month ? latest_month : selected_month;
    }
    // var is_selected = "";
    // var this_month = earliest_month;
    var earliest_month_obj = moment().year(selected_year).month(earliest_month - 1).date(1).hour(1).minute(0).second(0);
    var latest_month_obj = moment().year(selected_year).month(latest_month - 1).date(1).hour(23).minute(59).second(59);
    var this_month_obj = earliest_month_obj;
    parent_obj.empty();
    while(this_month_obj < latest_month_obj){
        var options = {
            value: this_month_obj.format("M")
        };
        if(parseInt(this_month_obj.format("M"), 10) == selected_month){
            options["selected"] = "selected";
        }
        $("<option/>", options).text(this_month_obj.format("MMMM")).appendTo(parent_obj);
        this_month_obj.add(1, "months");
    }
    return parent_obj;
};
