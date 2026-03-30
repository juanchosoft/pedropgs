$(document).on('ready', init);
var q;
function init() {
    q = {};
    $("#myModal").modal({ backdrop: "static", keyboard: false });
}
var return_page = 'daily_report.php';
var DAILYREPORT = {
    validateData: function() {
            var bValid = true;
            var msj = "You need to add all obligatory data";
            if (
            $("#tbl_unidades_id").val() == "select" ||
            $("#tbl_employees_id").val() == "select" ||
            $("#tbl_lugar_id").val() == "select" ||
            $("#tec_oficios_id").val() == "select" 
        ) {
            swal("warning", msj, "error");
            bValid = false;
            return;
        }
        if (bValid) {
            DAILYREPORT.savedata();
        }
    },
    successMessage: function() {
        swal("Daily Report Saved Sucessfully  ", "", "success");
        setTimeout(function() {
            window.location = return_page;
        }, 1000);
    },
    savedata: function() {
        q = {};
        q.op = "pms_daily_report_save";
        q.id = $("#id").val();
        q.tbl_unidades_id = $("#tbl_unidades_id").val();
        q.tbl_lugar_id = $("#tbl_lugar_id").val();
        q.tbl_employees_id = $("#tbl_employees_id").val();
        q.tec_oficios_id = $("#tec_oficios_id").val();
        UTIL.callAjaxRqstPOST(q, DAILYREPORT.savedataHandler);
    },
    savedataHandler: function(data) {
        UTIL.cursorNormal();
        if (data.output.valid) {
            $("#tec_oficios_id").val("select").trigger("change");
            document.getElementById('ifm').contentWindow.location.reload();
            document.getElementById('ifm2').contentWindow.location.reload();
            swal("Daily Report Saved Sucessfully  ", "", "success");
        } else {
            swal("Check your information please", data.output.response.content, "error");
        }
    },
};