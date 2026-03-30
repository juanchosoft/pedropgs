$(document).on('ready', init);
var q;

function init() {
    q = {};
    // No permitir cerrar el modal, click afuera
    $("#myModal").modal({ backdrop: "static", keyboard: false });
}


var return_page = 'productos.php';

var PRODUCTO = {
    editdata: function(id) {
        q = {};
        q.op = "pms_prodget";
        q.id = id;
        UTIL.callAjaxRqstPOST(q, this.editdatahandler);
    },
    editdatahandler: function(data) {

        UTIL.cursorNormal();

        if (data.output.valid) {
            var res = data.output.response[0];
            $("#id").val(res.id);
            $("#tipo").val(res.tipo).trigger("change");
            $("#codigo").val(res.codigo).trigger("change");
            $("#nombre_prod").val(res.nombre_prod).trigger("change");
            $("#presentacion").val(res.presentacion).trigger("change");
            $("#tbl_unidad_id").val(res.tbl_unidad_id).trigger("change");
            $("#tec_category_id").val(res.tec_category_id).trigger("change");
            $("#costo").val(res.costo).trigger("change");
            $("#descripcion").val(res.descripcion).trigger("change");
            $("#quantity").val(res.quantity).trigger("change");
            $("#cant_minima").val(res.cant_minima).trigger("change");
            $("#cant_ini").val(res.cant_ini).trigger("change");
            $("#myModal").modal();
        } else {
            swal("warning", data.output.response.content, "error");
        }
    },

    validateData: function() {
        var bValid = true;
        var msj = "information is missing, please check.";

        if (
            $("#tipo").val() == "" ||
            $("#codigo").val() == "" ||
            $("#nombre_prod").val() == "" ||
            $("#presentacion").val() == "" ||
            $("#tbl_unidad_id").val() == "" ||
            $("#proveedor_id").val() == "" ||
            $("#tec_category_id").val() == "" ||
            $("#quantity").val() == "" ||
            $("#cant_ini").val() == ""
        ) {
            swal("warning", msj, "error");
            bValid = false;
            return;
        }
        if (bValid) {
            PRODUCTO.savedata();
        }
    },

    enabledata: function(id, enable) {
        q = {};
        q.op = "pms_prodenable";
        q.id = id;
        q.enable = enable == "si" ? "no" : "si";
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
    savedata: function() {
        q = {};
        q.op = "pms_prodsave";
        q.id = $("#id").val();
        q.tipo = $("#tipo").val();
        q.codigo = $("#codigo").val();
        q.nombre_prod = $("#nombre_prod").val();
        q.presentacion = $("#presentacion").val();
        q.tbl_unidad_id = $("#tbl_unidad_id").val();
        q.tec_category_id = $("#tec_category_id").val();
        q.costo = $("#costo").val();
        q.descripcion = $("#descripcion").val();
        q.quantity = $("#quantity").val();
        q.cant_ini = $("#cant_ini").val();
        q.cant_minima = $("#cant_minima").val();
        q.enable = $("#enable").val();
        q.fileToUpload = $("#fileToUpload").val();
        UTIL.callAjaxRqstPOST(q, PRODUCTO.savedataHandler);
    },

    savedataHandler: function(data) {

        UTIL.cursorNormal();

        if (data.output.valid) {

            swal("Information saved successfully ", "", "success");

            $("#myModal").modal('hide');

            window.location = return_page;

        } else {

            swal("warning", data.output.response.content, "error");

        }

    },



    calcularRentabilidad: function(rentabilidad, input) {

        if (rentabilidad > 99) {

            swal("warning", 'Rentabilidad debe ser menor que 100%', "error");

        } else {

            if (rentabilidad >= 1 && rentabilidad <= 99) {

                var costo = $("#costo").val();

                var ren = rentabilidad / 100;

                var precio = costo / (1 - ren);

                $(input).val(parseFloat(precio.toFixed(0)));

            }

        }

    },

};