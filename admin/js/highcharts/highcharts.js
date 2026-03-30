savedata: function() {
        var valor = $("#valor").val();
        if ($("#valor").val() != "0" && $("#valor").val() !== "") {
            swal({
                title: '¿Está seguro que desea Guardar esta compra por este valor?',
                text: numeral(valor).format("0,0"),
                type: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#03A9F4',
                cancelButtonColor: '#F44336',
                confirmButtonText: '<i class="zmdi zmdi-run" ></i> Confirmar!',
                cancelButtonText: '<i class="zmdi zmdi-close-circle"></i> No, Cancelar!'
            }).then((result) => {
                if (result.value) {
                    q = {};
                    q.op = "pms_gastosave";
                    q.id = $("#id").val();
                    q.proveedor_id = $("#proveedor_id").val();
                    q.tipo = $("#tipo").val();
                    q.motivo = $("#motivo").val();
                    q.iva = $("#iva").val();
                    q.retencion = $("#retencion").val();
                    q.valor = valor
                    q.ordenado_por = $("#ordenado_por").val();
                    UTIL.callAjaxRqstPOST(q, GASTO.savedataHandler);
                }
            });
        } else {
            swal("warning", "Debes ingresar el valor a recoger", "error");
        }
    },
    savedataHandler: function(data) {
        UTIL.cursorNormal();
        if (data.output.valid) {
            swal("El valor del dinero ingresado fue ingresado correctamente", "", "success");
            setTimeout(function() {
                window.location = 'gastos.php';
            }, 1000);
        } else {
            swal("warning", data.output.response.content, "error");
        }
    },