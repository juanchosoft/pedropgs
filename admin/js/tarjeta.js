$(document).on('ready', init);
var q, nombre, allFields, tips;
/**
 * se activa para inicializar el documento
 */
function init() {
    q = {};
}

var return_page = 'tarjetas.php';
var TARJETA = {

    validateData: function() {
        var bValid = true;
        var msj = "Falta ingresar información obligatoria, marcada con asterisco.";
        if (
            $("#no_tarjeta").val() == "" ||
            $("#valor").val() == "") {
            swal("warning", msj, "error");
            bValid = false;
            return;
        }

        if (bValid) {
            TARJETA.savedata();

        }
    },
    /**
     * Funcion que me indica que se han guardado o editado el registro correctamente
     */

    successMessage: function() {
        swal("Información guardada correctamente ", "", "success");
        setTimeout(function() {
            window.location = return_page;
        }, 2000);
    },
    savedata: function() {
        q = {};
        q.op = "pms_tarjetasave";
        q.id = $("#id").val();
        q.no_tarjeta = $("#no_tarjeta").val();
        q.valor = $("#valor").val();


        UTIL.callAjaxRqstPOST(q, TARJETA.savedataHandler);
    },
    savedataHandler: function(data) {
        UTIL.cursorNormal();
        if (data.output.valid) {
            swal("Ingreso Guardadado Correctamente ", "", "success");
            setTimeout(function() {
                window.location = 'tarjetas.php';
            }, 2500);
        } else {
            swal("warning", data.output.response.content, "error");
        }
    },
};