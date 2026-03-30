$(document).on('ready', init);

var q, nombre, allFields, tips;

/**

 * se activa para inicializar el documento

 */

function init() {

    q = {};

}
var return_page = 'reloj.php';

var ENTRADA = {

    validateData: function() {

        var bValid = true;

        var msj = "Falta ingresar información obligatoria, marcada con asterisco.";

        if ($("#cc_entrada").val() == "") {

            swal("warning", msj, "error");

            bValid = false;

            return;
        }

        if (bValid) {

            ENTRADA.savedata();
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

        q.op = "pms_entradasave";

        q.id = $("#id").val();

        q.cc = $("#cc_entrada").val();

        q.ingreso = $("#ingreso").val();

        UTIL.callAjaxRqstPOST(q, SALIDA.savedataHandler);

    },

    savedataHandler: function(data) {

        UTIL.cursorNormal();

        if (data.output.valid) {

            swal("Ingreso Guardadado Correctamente ", "", "success");

            setTimeout(function() {

                window.location = 'reloj.php';

            }, 2500);

        } else {

            swal("warning", data.output.response.content, "error");

        }

    },

};