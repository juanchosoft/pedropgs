$(document).on('ready', init);
var q;

function init() {
    q = {};
}

var SALIDA = {
    validate() {
        var bValid = true;
        var msj = 'Recuerde que todos los campos son obligatorios.';
        if ($("#cc").val() == "" ||
            $("#fecha").val() == "" ) {
            bValid = false;
            swal("Error", msj, "error");
            return;
        }
        if (bValid) {
           SALIDA.addSalida();
        }
    },
    
    addSalida: function() {
        q = {};
        q.op = "mzt_savesalida";
        q.cc = $('#cc').val();
		q.fecha = $("#fecha").val();
        UTIL.callAjaxRqst(q, SALIDA.savedataHandler);
    },
    savedataHandler: function(data) {
        UTIL.cursorNormal();
        if (data.output.valid) {
            $("#cc").val('');
            $("fecha").val('');
        swal("Informacion guardada correctamente ", "", "success");
        } else {
            swal("Falta ingresar Informacion", data.output.response.content, "error");
        }
        setTimeout(function() {
            window.location = 'reloj.php';
        }, 2500);
    }
}