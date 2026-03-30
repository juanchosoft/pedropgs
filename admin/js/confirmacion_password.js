$(document).on('ready', init);
var q;
/**
 * se activa para inicializar el documento
 */
function init() {
    q = {};
}

var PASSWORD = {
    confirmarPasswordAdminCancelar: function(){
        $( "#radioPrecio1").prop('checked', true);
    },
    /**
     * Método para consultar contraseña del
     */
    confirmarPasswordAdmin: function() {
        console.log('pss', $("#passAdmin").val());
        if ($("#passAdmin").val() == null || $("#passAdmin").val() == "") {
            swal("warning", "Para realizar este proceso, debe ingresar la contraseña administrador", "error");
            return;
        } else {
            q = {};
            q.op = "pms_validate_passwordsadmin";
            q.password = $("#passAdmin").val();;
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
                        $("#passAdmin").val("");
                        $("#myModalConfirmarContraseña").modal("hide");
                    } else {
                        $("#passAdmin").val("");
                        swal("warning", data.output.response.content, "error");
                    }
                },
            });
        }
    },
};