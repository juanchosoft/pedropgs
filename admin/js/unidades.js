$(document).on('ready', init);
var q;


function init() {
    q = {};
    $("#myModal").modal({ backdrop: "static", keyboard: false });
}

var return_page = 'places_customers.php';
var UNIDADES = {
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
                q.op = "pms_unidelete";
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
                            }, 4500);
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
        q.op = "pms_uniget";
        q.id = id;
        UTIL.callAjaxRqstPOST(q, this.editdatahandler);
    },
    editdatahandler: function(data) {
        UTIL.cursorNormal();
        if (data.output.valid) {
            var res = data.output.response[0];
            $("#id").val(res.id);
            $("#nombre").val(res.nombre).trigger("change");
            $("#ubicacion").val(res.ubicacion).trigger("change");
            $("#administrador").val(res.administrador).trigger("change");
            $("#celular").val(res.celular).trigger("change");
            $("#email").val(res.email).trigger("change");
            $("#contact1").val(res.contact1).trigger("change");
            $("#email1").val(res.email1).trigger("change");
            $("#contact2").val(res.contact2).trigger("change");
            $("#email2").val(res.email2).trigger("change");
            $("#contact3").val(res.contact3).trigger("change");
            $("#email3").val(res.email3).trigger("change");
            $("#contact4").val(res.contact4).trigger("change");
            $("#email4").val(res.email4).trigger("change");
            $("#enable").val(res.enable).trigger("change");
            $("#telefono_emergencia").val(res.telefono_emergencia).trigger("change");
            $("#myModal").modal();
        } else {
            swal("warning", data.output.response.content, "error");
        }
    },
    validateData: function() {
        var bValid = true;
        var msj = "Fill in all the fields.";
        if ($("#nombre").val() == "" ||
            $("#administrador").val() == "" ||
            $("#celular").val() == "" ||
            $("#email").val() == "" ||
            $("#ubicacion").val() == "") {
            swal("warning", msj, "error");
            bValid = false;
            return;
        }
        if (bValid) {
            UNIDADES.savedata();
        }
    },
    successMessage: function() {
        swal("HOA Saved Sucessfully  ", "", "success");
        setTimeout(function() {
            window.location = return_page;
        }, 4500);
    },
    savedata: function() {
        q = {};
        q.op = "pms_unisave";
        q.id = $("#id").val();
        q.nombre = $("#nombre").val();
        q.celular = $("#celular").val();
        q.email = $("#email").val();
        q.ubicacion = $("#ubicacion").val();
        q.administrador = $("#administrador").val();
        q.contact1 = $("#contact1").val();
        q.email1 = $("#email1").val();
        q.contact2 = $("#contact2").val();
        q.email2 = $("#email2").val();
        q.contact3 = $("#contact3").val();
        q.email3 = $("#email3").val();
        q.contact4 = $("#contact4").val();
        q.email4 = $("#email4").val();
        q.enable = $("#enable").val();
        q.telefono_emergencia = $("#telefono_emergencia").val();
        UTIL.callAjaxRqstPOST(q, UNIDADES.savedataHandler);
    },
    savedataHandler: function(data) {
        UTIL.cursorNormal();
        if (data.output.valid) {
            $("#nombre").val("");
            $("ubicacion").val("");
            $("celular").val("");
            $("email").val("");
            $("administrador").val("");
            $("contact1").val("");
            $("email1").val("");
            $("contact2").val("");
            $("email2").val("");
            $("contact3").val("");
            $("email3").val("");
            $("contact4").val("");
            $("email4").val("");
            swal("HOA Saved Sucessfully  ", "", "success");
            window.location = return_page;
        } else {
            swal("Check your information please", data.output.response.content, "error");
        }
    },
};