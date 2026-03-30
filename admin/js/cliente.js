$(document).on('ready', init);
var q, nombre, allFields, tips;
/**
 * se activa para inicializar el documento
 */
function init() {
    q = {};
}

var return_page = 'clientes.php';
var CLIENTE = {
    /**
     * Método para eliminar un usuario
     * @param {*} id identificador del Usuario
     */
    deletedata: function(id) {
        Swal.fire({
            title: "Va a eliminar información de forma irreversible!",
            text: "¿Desea continuar?",
            type: "warning",
            showCancelButton: true,
            confirmButtonText: "Si",
            cancelButtonText: "Cancelar!",
            closeOnConfirm: false,
        }).then((result) => {
            if (result.value) {
                q = {};
                q.op = "pms_clidelete";
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
                            /*                             md.showNotificationAdmin(
                                                            "top",
                                                            "right",
                                                            "success",
                                                            "<b>Registro eliminado correctamente.<b>",
                                                            "success",
                                                            2000
                                                        ); */
                            setTimeout(function() {
                                window.location = 'clientes.php';
                            }, 1500);
                        } else {
                            swal("warning", data.output.response.content, "error");
                        }
                    },
                });
            }
        });
    },
    /**
     * Método para editar y mostrar los datos de un usuario
     * @param {*} id identificador del Usuario
     */
    editdata: function(id) {
        q = {};
        q.op = "pms_cliget";
        q.id = id;
        UTIL.callAjaxRqstPOST(q, this.editdatahandler);
    },

    editdatahandler: function(data) {
        UTIL.cursorNormal();
        if (data.output.valid) {
            var res = data.output.response[0];
            $("#id").val(res.id);
            $("#identificacion_tipo").val(res.identificacion_tipo).trigger("change");
            $("#identificacion_num").val(res.identificacion_num).trigger("change");
            $("#nombre").val(res.nombre).trigger("change");
            $("#direccion").val(res.direccion).trigger("change");
            $("#telefono").val(res.telefono).trigger("change");
            $("#celular").val(res.celular).trigger("change");
            $("#email").val(res.email).trigger("change");
            $("#cupo").val(res.cupo).trigger("change");
            $("#saldo").val(res.saldo).trigger("change");
            $("#ubicacion").val(res.ubicacion).trigger("change");
            $("#contacto").val(res.contacto).trigger("change");
            $("#tel_contacto").val(res.tel_contacto).trigger("change");
            $("#enable").val(res.enable).trigger("change");

            $("#dv").val(res.dv).trigger("change");
            $("#autoretenedor").val(res.autoretenedor).trigger("change");
            $("#reteica").val(res.reteica).trigger("change");
            $("#departamento").val(res.departamento).trigger("change");
            PROVEEDOR.getMunicipios();
            $("#ciudad").val(res.ciudad).trigger("change");

            $("#myModal").modal();
        } else {
            swal("warning", data.output.response.content, "error");
        }
    },
    /**
     * Método para validar los datos del formulario de categorias
     * Datos obligatorios
     */
    validateData: function(url = 'clientes.php') {
        return_page = url;
        var bValid = true;
        var msj = "Falta ingresar información obligatoria, marcada con asterisco.";
        if (
            $("#identificacion_tipo").val() == "" ||
            $("#identificacion_num").val() == "" ||
            $("#nombre").val() == "" ||
            $("#direccion").val() == "" ||
            $("#ubicacion").val() == "" ||
            $("#telefono").val() == "" ||
            $("#celular").val() == "" ||
            $("#cupo").val() == "" ||
            $("#ciudad").val() == "seleccione" ||
            $("#departamento").val() == "seleccione" ||
            $("#dv").val() == "" ||
            $("#autoretenedor").val() == "" ||
            $("#reteica").val() == "" ||
            $("#saldo").val() == ""
        ) {
            swal("warning", msj, "error");
            bValid = false;
            return;
        }

        if (bValid) {
            CLIENTE.savedata();
        }
    },

    /**
     * Método para habilitar y desabilitar categoria
     */
    enabledata: function(id, enable) {
        q = {};
        q.op = "pms_clienable";
        q.id = id;
        q.enable = enable == "si" ? "no" : "si";
        UTIL.callAjaxRqstPOST(q, this.enabledatahandler);
    },
    enabledatahandler: function(data) {
        UTIL.cursorNormal();
        if (data.output.valid) {
            /*             md.showNotificationAdmin(
                            "top",
                            "right",
                            "success",
                            "<b>Estado actualizado correctamente.<b>",
                            "success",
                            2000
                        ); */
            window.location = 'clientes.php';
        } else {
            swal("warning", data.output.response.content, "error");
        }
    },
    savedata: function() {
        q = {};
        q.op = "pms_clisave";
        q.id = $("#id").val();
        q.identificacion_tipo = $("#identificacion_tipo").val();
        q.identificacion_num = $("#identificacion_num").val();
        q.nombre = $("#nombre").val();
        q.vendedor = $("#vendedor").val();
        q.direccion = $("#direccion").val();
        q.ciudad = $("#ciudad").val();
        q.telefono = $("#telefono").val();
        q.celular = $("#celular").val();
        q.email = $("#email").val();
        q.cupo = $("#cupo").val();
        q.saldo = $("#saldo").val();
        q.contacto = $("#contacto").val();
        q.tel_contacto = $("#tel_contacto").val();
        q.ubicacion = $("#ubicacion").val();
        q.enable = $("#enable").val();
        q.autoretenedor = $("#autoretenedor").val();
        q.reteica = $("#reteica").val();
        q.ciudad = $("#ciudad").val();
        q.departamento = $("#departamento").val();
        q.dv = $("#dv").val();
        UTIL.callAjaxRqstPOST(q, CLIENTE.savedataHandler);
    },
    savedataHandler: function(data) {
        UTIL.cursorNormal();
        if (data.output.valid) {
            swal("Información guardada correctamente ", "", "success");
            setTimeout(function() {
                window.location = return_page;
            }, 1500);

        } else {
            swal("warning", data.output.response.content, "error");
        }
    },
};