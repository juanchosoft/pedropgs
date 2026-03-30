$(document).on('ready', initusuario);
var q;

function initusuario() {
    q = {};
    // No permitir cerrar el modal, click afuera
    $("#myModal").modal({ backdrop: "static", keyboard: false });
    $("#myModalAfterPhoto").modal({ backdrop: "static", keyboard: false });
}

var return_page = 'report-list.php';
var REPORT = {
    deletedata: function(id) {
        Swal.fire({
            title: "Are you sure delete?",
            text: "¿are you sure to continue?",
            type: "warning",
            showCancelButton: true,
            confirmButtonText: "Si",
            cancelButtonText: "Cancelar!",
            closeOnConfirm: false,
        }).then((result) => {
            if (result.value) {
                q = {};
                q.op = "delete_deport";
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
                            setTimeout(function() {
                                window.location = return_page;
                            }, 1000);
                        } else {
                            swal("warning", data.output.response.content, "error");
                        }
                    },
                });
            }
        });
    },
    editPhotoAfter: function(id) {
        q = {};
        q.op = "get_deport";
        q.id = id;
        UTIL.callAjaxRqstPOST(q, this.editPhotoAfterHandler);
    },
    editPhotoAfterHandler: function(data) {
        UTIL.cursorNormal();
        if (data.output.valid) {
            var res = data.output.response[0];
            $("#id").val(res.id);
            $('#item').empty().append(res.id);
            $("#myModalAfterPhoto").modal();
        } else {
            swal("warning", data.output.response.content, "error");
        }
    },
    editdata: function(id) {
        q = {};
        q.op = "get_deport";
        q.id = id;
        UTIL.callAjaxRqstPOST(q, this.editdatahandler);
    },
    editdatahandler: function(data) {
        UTIL.cursorNormal();
        if (data.output.valid) {
            var res = data.output.response[0];
            $("#id").val(res.id);      
            $('#actividades').val(res.actividades);
            $('#observaciones').val(res.observaciones);     
            $("#myModal").modal();
        } else {
            swal("warning", data.output.response.content, "error");
        }
    },
    updateFields() {
        q = {};
        q.op = "updateFields";
        q.id = $("#id").val();
        q.actividades = $("#actividades").val();
        q.observaciones = $("#observaciones").val(); 
        UTIL.callAjaxRqstPOST(q, REPORT.updateFieldsHandler);
    },
    updateFieldsHandler: function(data) {
        UTIL.cursorNormal();
        if (data.output.valid) {
            REPORT.successMessage();
        } else {
            swal("warning", data.output.response.content, "error");
        }
    },
    successMessage: function() {
        swal("Information saved correctly ", "", "success");
        setTimeout(function() {
            window.location = return_page;
        }, 1000);
    },
};