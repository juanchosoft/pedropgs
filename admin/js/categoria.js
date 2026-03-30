$(document).on('ready', init);
var q, nombre, allFields, tips;
/**
 * se activa para inicializar el documento
 */
function init() {
    q = {};
    // No permitir cerrar el modal, click afuera
    $("#myModal").modal({ backdrop: "static", keyboard: false });
}

var return_page = 'categorias.php';
var CATEGORIA = {
    editdata: function(id) {
        q = {};
        q.op = "pms_catget";
        q.id = id;
        UTIL.callAjaxRqstPOST(q, this.editdatahandler);
    },
    editdatahandler: function(data) {
        UTIL.cursorNormal();
        if (data.output.valid) {
            var res = data.output.response[0];
            $("#id").val(res.id);
            $("#code").val(res.code).trigger("change");
            $("#name").val(res.name).trigger("change");
            $("#group_category").val(res.group_category).trigger("change");
            $("#myModal").modal();
        } else {
            swal("warning", data.output.response.content, "error");
        }
    },
    validateData: function() {
        var bValid = true;
        var msj = "Missing Information, plese check.";
        if ($("#code").val() == "" ||
            $("#group_category").val() == "" ||
            $("#name").val() == "") {
            swal("warning", msj, "error");
            bValid = false;
            return;
        }

        if (bValid) {
            CATEGORIA.savedata();
        }
    },
    enabledata: function(id, enable) {
        q = {};
        q.op = "pms_catenable";
        q.id = id;
        q.enable = enable == "yes" ? "no" : "yes";
        UTIL.callAjaxRqstPOST(q, this.enabledatahandler);
    },
    enabledatahandler: function(data) {
        UTIL.cursorNormal();
        if (data.output.valid) {
            window.location = return_page;
        } else {
            swal("warning", data.output.response.content, "error");
        }
    },
    successMessage: function() {
        swal("Information Saved Successfully ", "", "success");
        setTimeout(function() {
            window.location = return_page;
        }, 1000);
    },
    savedata: function() {
        q = {};
        q.op = "pms_catsave";
        q.id = $("#id").val();
        q.code = $("#code").val();
        q.name = $("#name").val();
        q.group_category = $("#group_category").val();
        q.enable = $("#enable").val();

        UTIL.callAjaxRqstPOST(q, CATEGORIA.savedataHandler);
    },
    savedataHandler: function(data) {
        UTIL.cursorNormal();
        if (data.output.valid) {
            $("#code").val("");
            $("name").val("");
            $("group_category").val("");
            $("#enable").val("");

            swal("Information Saved Successfully ", "", "success");
            window.location = return_page;
        } else {
            swal("Missing Information, plese check", data.output.response.content, "error");
        }
    },
};