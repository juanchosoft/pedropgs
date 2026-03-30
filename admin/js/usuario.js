$(document).on('ready', initusuario);
var q;

function initusuario() {
    q = {};
    // No permitir cerrar el modal, click afuera
    $("#myModal").modal({ backdrop: "static", keyboard: false });
    $("#myModalPermisos").modal({ backdrop: "static", keyboard: false });
}

var return_page = 'usuarios.php';
var USUARIO = {
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
                q.op = "pms_usrdelete";
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
                                                        md.showNotificationAdmin(
                                                            "top",
                                                            "right",
                                                            "success",
                                                            "<b>Registro eliminado correctamente.<b>",
                                                            "success",
                                                            2000
                                                        ); 
                            setTimeout(function() {
                                window.location = return_page;
                            }, 1500);
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
        q.op = "pms_usrget";
        q.id = id;
        UTIL.callAjaxRqstPOST(q, this.editdatahandler);
    },
    editdatahandler: function(data) {
        UTIL.cursorNormal();
        if (data.output.valid) {
            var res = data.output.response[0];
            $("#id").val(res.id);
            $("#nombre").val(res.nombre).trigger("change");
            $("#apellido").val(res.apellido).trigger("change");
            $("#celular").val(res.celular).trigger("change");
            $("#tipo").val(res.tipo).trigger("change");
            $("#nickname").val(res.nickname).trigger("change");
            $("#nickname2").val(res.nickname).trigger("change");
            $("#hashpass").val("").trigger("change");
            $("#hashpass1").val("").trigger("change");
            $("#tbl_unidad_id").val(res.tbl_unidad_id).trigger("change");
            $("#habilitado").val(res.habilitado).trigger("change");
            $("#tipo").val(res.tipo).trigger("change");
            $("#myModal").modal();
        } else {
            swal("warning", data.output.response.content, "error");
        }
    },
    validateData: function() {
        var bValid = true;
        var msj = "Missing obligatory information, marked with an asterisk.";
        if (
            $("#nombre").val() == "" ||
            $("#nickname").val() == "" ||
            $("#apellido").val() == "" ||
            $("#identificacion_num").val() == ""
        ) {
            swal("warning", msj, "error");
            bValid = false;
            return;
        }
        if ($("#hashpass").val() == "" && $("#id").val() == "") {
            bValid = false;
            swal("warning", "Enter Your Password", "error");
            return;
        }
        if ($("#hashpass1").val() == "" && $("#id").val() == "") {
            bValid = false;
            swal("warning", "Debe confirmar su contraseña", "error");
            return;
        }
        //Validamos el email que sea válido
        if ($("#nickname").val() != "") {
            var nickname = UTIL.isEmail($("#nickname").val());
            if (!nickname) {
                swal(
                    "warning",
                    "Username must be a valid email.",
                    "error"
                );
                bValid = false;
                return;
            }
        }
        if (bValid) {
            USUARIO.savedata();
        }
    },
    savedata: function() {
        var hashpass = $("#hashpass").val();
        var hashpass1 = $("#hashpass1").val();
        if (hashpass.length > 1) {
            if (hashpass == hashpass1) {
                $("#hashpass").val(hex_md5(hashpass));
                $("#hashpass1").val(hex_md5(hashpass1));
            } else {
                swal(
                    "warning",
                    "Password dont match. Please try again",
                    "Mistake"
                );
                return;
            }
        }
        var nickname = $("#nickname").val();
        var nickname2 = $("#nickname2").val();
        if (nickname.length > 0 && nickname != nickname2) {
            //se verifica que el nombre de usuario este disponible si se ingresa nuevamente
            q = {};
            q.op = "pms_usravailable";
            q.nickname = $("#nickname").val();
            UTIL.cursorBusy();
            $.ajax({
                data: q,
                type: "GET",
                dataType: "json",
                url: "admin/ajax/rqst.php",
                success: function(data) {
                    q = {};
                    UTIL.cursorNormal();
                    if (data.output.valid) {
                        USUARIO.sendDataSave();
                    } else {
                        swal(
                            "warning",
                            "The user *" +
                            $("#nickname").val() +
                            "* User is in use, please try another nickname.",
                            "Mistake"
                        );
                        $("#hashpass").val("");
                        $("#hashpass1").val("");
                    }
                },
            });
        } else {
            USUARIO.sendDataSave();
        }
    },
    sendDataSave() {
        q = {};
        q.op = "pms_usrsave";
        q.id = $("#id").val();
        q.nombre = $("#nombre").val();
        q.apellido = $("#apellido").val();
        q.celular = $("#celular").val();
        q.nickname = $("#nickname").val();
        q.nickname2 = $("#nickname2").val();
        q.hashpass = $("#hashpass").val();
        q.hashpass1 = $("#hashpass1").val();
        q.tbl_unidad_id = $("#tbl_unidad_id").val();
        q.habilitado = $("#habilitado").val();
        q.tipo = $("#tipo").val();
        UTIL.callAjaxRqstPOST(q, USUARIO.savedatahandler);
    },
    savedatahandler: function(data) {
        UTIL.cursorNormal();
        if (data.output.valid) {
            USUARIO.successMessage();
        } else {
            swal("warning", data.output.response.content, "error");
        }
    },
    enabledata: function(id, habilitado) {
        q = {};
        q.op = "pms_usrenable";
        q.id = id;
        q.habilitado = habilitado == "yes" ? "no" : "yes";
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
    getSeleccionarPermisos: function() {
        if ($("#check_permisos").is(":checked")) {
            $("#formpermission :input").each(function() {
                this.checked = true;
            });
        } else {
            $("#formpermission :input").each(function() {
                this.checked = false;
            });
        }
    },
    successMessage: function() {
        swal("Saved Successfully ", "", "success");
        setTimeout(function() {
            window.location = return_page;
        }, 1000);
    },
};