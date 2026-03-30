$(document).on('ready', init);
var q;
/**
 * se activa para inicializar el documento
 */
function init() {
    q = {};
    // No permitir cerrar el modal, click afuera
    $("#myModal").modal({ backdrop: "static", keyboard: false });
}

var return_page = 'jobs.php';
var OFICIOS = {
    deletedata: function(id) {
        Swal.fire({
            title: "Are you sure delete?",
            text: "¿are you sure to continue?",
            type: "warning",
            showCancelButton: true,
            confirmButtonText: "yes",
            cancelButtonText: "Cancel!",
            closeOnConfirm: false,
        }).then((result) => {
            if (result.value) {
                q = {};
                q.op = "pms_job_delete";
                q.id = id;
                UTIL.cursorBusy();
                $.ajax({
                    data: q,
                    type: "POST",
                    dataType: "json",
                    url: "admin/ajax/rqst.php",
                    success: function(data) {
                        q = {};
                        UTIL.cursorNormal();
                        if (data.output.valid) {
                            swal("Request delete successfully. ", "", "success");
                            setTimeout(function() {
                                window.location = return_page;
                            }, 3500);
                        } else {
                            swal("warning", data.output.response.content, "error");
                        }
                    },
                });
            }
        });
    },
    editdata: function(id) {
        q = {};
        q.op = "pms_job_get";
        q.id = id;
        UTIL.callAjaxRqstPOST(q, this.editdatahandler);
    },
    editdatahandler: function(data) {
        UTIL.cursorNormal();
        if (data.output.valid) {
            var res = data.output.response[0];
            $("#id").val(res.id);
            $("#oficio").val(res.oficio).trigger("change");
            $("#descripcion").val(res.descripcion).trigger("change");
            $("#myModal").modal();
        } else {
            swal("warning", data.output.response.content, "error");
        }
    },
    

    validateData: function() {
        var bValid = true;
        var msj = "Fill in all the fields.";
        if ($("#oficio").val() == "" ||
             $("#descripcion").val() == "") {
            swal("warning", msj, "error");
            bValid = false;
            return;
        }
        if (bValid) {
            OFICIOS.savedata();
        }
    },
    successMessage: function() {
        swal("Request sent successfully  ", "", "success");
        setTimeout(function() {
            window.location = return_page;
        }, 3500);
    },
    savedata: function() {
        q = {};
        q.op = "pms_job_save";
        q.id = $("#id").val();
        q.oficio = $("#oficio").val();
        q.descripcion = $("#descripcion").val();

        UTIL.callAjaxRqstPOST(q, OFICIOS.savedataHandler);
    },
    savedataHandler: function(data) {
        UTIL.cursorNormal();
        if (data.output.valid) {
            $("#oficio").val("");
            $("descripcion").val("");

            swal("Request sent successfully ", "", "success");
            window.location = return_page;
        } else {
            swal("Check your information please", data.output.response.content, "error");
        }
    },
};