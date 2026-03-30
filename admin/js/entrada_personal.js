$(document).on('ready', init);
var q;

function init() {
    q = {};
}
    
var ENTRADAPERSONAL = {
    validate() {
        var bValid = true;
        var msj = 'Recuerde que todos los campos son obligatorios para poder ingresar a la empresa.';
        if ( $("#cc").val() == "" ||
             $("#fecha").val() == "" ||
             $("#temperatura").val() == "" ||
             $("#fiebre").val() == "" ||
             $("#tos").val() == "" ||
             $("#garganta").val() == "" ||
             $("#respiracion").val() == "" ) {
            bValid = false;
            swal("Error", msj, "error");
            return;
        }
        if (bValid) {
            ENTRADAPERSONAL.addEntrada();
        }
    },

    addEntrada: function() {
        q = {};
        q.op = "mzt_saveentradapersonal";
        q.cc = $('#cc').val();
        q.fecha = $("#fecha").val();
        q.temperatura = $("#temperatura").val();
        q.fiebre = $("#fiebre").val();
        q.tos = $("#tos").val();
        q.garganta = $("#garganta").val();
        q.respiracion = $("#respiracion").val();
        UTIL.callAjaxRqst(q, ENTRADAPERSONAL.savedataHandler);
    },
    savedataHandler: function(data) {
        UTIL.cursorNormal();
        if (data.output.valid) { 
                $("#cc").val('');
                $("fecha").val('');
                $("temperatura").val('');
                $("fiebre").val('');
                $("tos").val('');
                $("garganta").val('');
                $("respiracion").val('');

            swal("Informacion guardada correctamente ", "", "success");
        } else {
            swal("Informacion Importante!", data.output.response.content, "error");
        }
        setTimeout(function() {
            window.location = 'ingreso_screen.php';
        },5500);
    }
}