$(document).on('ready', initusuario);
var q;

function initusuario() {
    q = {};
    // No permitir cerrar el modal, click afuera
    $("#myModal").modal({ backdrop: "static", keyboard: false });
    $("#myModalAfterPhoto").modal({ backdrop: "static", keyboard: false });
}

var return_page = 'report-list.php';
var REPORT_PHOTO_BASE = 'admin/js/camara/foto/';
var REPORT_NO_IMAGE = 'assets/images/no-image.png';

var REPORT = {
    photoUrl: function(name) {
        if (!name || name === 'no_image.png' || name === 'null') {
            return null;
        }
        return REPORT_PHOTO_BASE + name;
    },
    setDetailPhoto: function(imgId, emptyId, fileName) {
        var url = REPORT.photoUrl(fileName);
        var $img = $('#' + imgId);
        var $empty = $('#' + emptyId);
        if (url) {
            $img.attr('src', url).removeClass('d-none').show();
            $empty.addClass('d-none');
        } else {
            $img.attr('src', REPORT_NO_IMAGE).addClass('d-none').hide();
            $empty.removeClass('d-none');
        }
    },
    statusLabel: function(estado) {
        if (estado === 'creado') return 'Created / Pending';
        if (estado === 'pendiente') return 'Pending';
        if (estado === 'finalizado') return 'Finalized';
        return estado || '—';
    },
    viewDetail: function(id) {
        q = {};
        q.op = 'get_deport';
        q.id = id;
        UTIL.callAjaxRqstPOST(q, REPORT.viewDetailHandler);
    },
    viewDetailHandler: function(data) {
        UTIL.cursorNormal();
        if (UTIL.handlePermissionResponse(data)) {
            return;
        }
        if (!data.output.valid || !data.output.response || !data.output.response[0]) {
            swal('warning', (data.output.response && data.output.response.content) || 'No data', 'error');
            return;
        }
        var res = data.output.response[0];
        $('#view_detail_item').text('#' + res.id);
        $('#view_detail_estado').text(REPORT.statusLabel(res.estado));
        $('#view_detail_fecha').text(res.dtcreate || '—');
        $('#view_detail_unidad').text(res.unidad_nombre || res.propiedad_nombre || res.unidad_usuario_nombre || '—');
        $('#view_detail_zone').text(res.zone || '—');
        $('#view_detail_actividades').text(res.actividades || '—');
        $('#view_detail_observaciones').text(res.observaciones || '—');
        REPORT.setDetailPhoto('view_detail_foto_antes', 'view_detail_foto_antes_empty', res.foto_antes);
        REPORT.setDetailPhoto('view_detail_foto_despues', 'view_detail_foto_despues_empty', res.foto_despues);
        $('#myModalViewDetail').modal('show');
    },
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
        if (UTIL.handlePermissionResponse(data)) {
            return;
        }
        if (data.output.valid) {
            var res = data.output.response[0];
            $("#id").val(res.id);
            $('#item').empty().append(res.id);
            if (typeof resetAfterPhotoUi === "function") {
                resetAfterPhotoUi();
            } else {
                $("#radiosfoto").prop("checked", true);
                $("#radiotfoto").prop("checked", false);
            }
            $("#myModalAfterPhoto").modal("show");
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
        if (UTIL.handlePermissionResponse(data)) {
            return;
        }
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
        if (UTIL.handlePermissionResponse(data)) {
            return;
        }
        if (data.output.valid) {
            REPORT.successMessage();
        } else {
            swal("warning", data.output.response.content, "error");
        }
    },
    finalize: function(id) {
        Swal.fire({
            title: "Finalize report?",
            text: "The report will be marked as finalized and will no longer be editable.",
            type: "warning",
            showCancelButton: true,
            confirmButtonText: "Finalize",
            cancelButtonText: "Cancel",
        }).then((result) => {
            if (!result.value) {
                return;
            }
            q = {};
            q.op = "report_finalize";
            q.id = id;
            UTIL.callAjaxRqstPOST(q, REPORT.finalizeHandler);
        });
    },
    finalizeHandler: function(data) {
        UTIL.cursorNormal();
        if (UTIL.handlePermissionResponse(data)) {
            return;
        }
        if (data.output.valid) {
            swal("Report finalized", "", "success");
            setTimeout(function() {
                window.location = return_page;
            }, 800);
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