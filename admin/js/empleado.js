$(document).on('ready', init);
var q;

function init() {
    q = {};
    // No permitir cerrar el modal, click afuera
    $("#myModal").modal({ backdrop: "static", keyboard: false });

}
var return_page = 'empleados.php';
var EMPLEADO = {
    deletedata: function(id) {

        Swal.fire({

            title: "Are you sure delete this !",

            text: "¿Do you wish continue?",

            type: "warning",

            showCancelButton: true,

            confirmButtonText: "Yes",

            cancelButtonText: "Cancel!",

            closeOnConfirm: false,

        }).then((result) => {

            if (result.value) {

                q = {};

                q.op = "pms_empleadodelete";

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

                            swal("Delete successfully. ", "", "success");

                            setTimeout(function() {

                                window.location = return_page;

                            }, 2000);

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
        q.op = "pms_empleadoget";
        q.id = id;
        UTIL.callAjaxRqstPOST(q, this.editdatahandler);
    },
    editdatahandler: function(data) {

        UTIL.cursorNormal();

        if (data.output.valid) {

            var res = data.output.response[0];

            $("#id").val(res.id);
            $("#nombre").val(res.nombre).trigger("change");
            $("#cc").val(res.cc).trigger("change");
            $("#fecha_ingreso").val(res.fecha_ingreso).trigger("change");
            $("#celular").val(res.celular).trigger("change");
            $("#email").val(res.email).trigger("change");
            $("#genero").val(res.genero).trigger("change");
            $("#direccion").val(res.direccion).trigger("change");
            $("#camisa").val(res.camisa).trigger("change");
            $("#pantalon").val(res.pantalon).trigger("change");
            $("#calzado").val(res.calzado).trigger("change");
            $("#entrega_uniforme").val(res.entrega_uniforme).trigger("change");
            $("#enable").val(res.enable).trigger("change");
            $("#tbl_unidad_id").val(res.tbl_unidad_id).trigger("change");
            $("#genero").val(res.genero).trigger("change");
            $("#myModal").modal();

        } else {

            swal("warning", data.output.response.content, "error");

        }

    },
    validateData: function() {
        var bValid = true;
        var msj = "You need to add all obligatory data";
        if (
            $("#tbl_unidad_id").val() == "seleccione" ||
            $("#tbl_unidad_id").val() == "" ||
            $("#nombre").val() == "" ||
            $("#cc").val() == "" ||
            $("#celular").val() == ""          
        ) {
            swal("warning", msj, "error");
            bValid = false;
            return;
        }
        if (bValid) {
            EMPLEADO.savedata();
        }
    },
    enabledata: function(id, enable) {
        q = {};
        q.op = "pms_empleadoenable";
        q.id = id;
        q.enable = enable == "yes" ? "no" : "yes";
        UTIL.callAjaxRqstPOST(q, this.enabledatahandler);
    },
    enabledatahandler: function(data) {

        UTIL.cursorNormal();

        if (data.output.valid) {

            md.showNotificationAdmin(
                "top",
                "right",
                "success",
                "<b>Updated successfully.<b>",
                "success",
                2000
            );
            window.location = return_page;
        } else {
            swal("warning", data.output.response.content, "error");

        }
    },
    successMessage: function() {
        swal("Information saved successfully ", "", "success");
        setTimeout(function() {
            window.location = return_page;
        }, 1000);
    },

    savedata: function() {
        q = {};
        q.op = "pms_empleadosave";
        q.id = $("#id").val();
        q.nombre = $("#nombre").val();
        q.cc = $("#cc").val();
        q.fecha_ingreso = $("#fecha_ingreso").val();
        q.celular = $("#celular").val();
        q.genero = $("#genero").val();
        q.email = $("#email").val();
        q.fecha_nacimiento = $("#fecha_nacimiento").val();
        q.lugar_nacimiento = $("#lugar_nacimiento").val();
        q.estado_civil = $("#estado_civil").val();
        q.direccion = $("#direccion").val();
        q.rh = $("#rh").val();
        q.camisa = $("#camisa").val();
        q.pantalon = $("#pantalon").val();
        q.calzado = $("#calzado").val();
        q.entrega_uniforme = $("#entrega_uniforme").val();
        q.enable = $("#enable").val();
        q.fileToUpload = $("#fileToUpload").val();
        q.tbl_unidad_id = $("#tbl_unidad_id").val();
        UTIL.callAjaxRqstPOST(q, EMPLEADO.savedataHandler);
    },

    savedataHandler: function(data) {
        UTIL.cursorNormal();
        if (data.output.valid) {
            swal("information saved successfully", "", "success");
            setTimeout(function() {
                window.location = return_page;
            }, 1000);
        } else {
            swal("Information missing", data.output.response.content, "error");
        }
    },

};