$(document).on('ready', init);

var q;
function init() {
    q = {};
}
var return_page = 'inventario.php';
var INVENTARIO = {
    /**
     * Método para abrir el modal x
     */
    abrirModal: function(producto_id, modal) {
        switch (modal) {
            case '#myModalSalida':
                $("#id_prod_salida").val(producto_id);
                break;
            case '#myModalAjuste':
                $("#id_prod_ajuste").val(producto_id);
                break;
            case '#myModalMovimiento':
                $("#id_prod_movimiento").val(producto_id);
                break;
        }
        $(modal).modal();
    },
    /**
     * Funcion que me indica que se han guardado o editado el registro correctamente
     */
    successMessage: function() {
        swal("Information saved succesfully  ", "", "success");
        setTimeout(function() {
            window.location = return_page;
        }, 1000);
    },

    saveSalidaInventario: function() {
        var bValid = true;
        var msj = "Missing Information, please check.";
        if ($("#autorizado_salida").val() == "" || $("#cantidad").val() == "" || $("#motivo_salida").val() == "" || $("#id_prod_salida").val() == "") {
            swal("warning", msj, "error");
            bValid = false;
            return;
        }
        q = {};
        q.op = "pms_inventario_salida";
        q.autoriza = $("#autorizado_salida").val();
        q.motivo = $("#motivo_salida").val();
        q.cantidad = $("#cantidad").val();
        q.tec_product_id = $("#id_prod_salida").val();
        UTIL.callAjaxRqstPOST(q, INVENTARIO.savedataHandler);
    },
    savedataHandler: function(data) {
        UTIL.cursorNormal();
        if (data.output.valid) {
            INVENTARIO.successMessage();
        } else {
            swal("warning", data.output.response.content, "error");
        }
    },
    saveAjuste: function() {
        var bValid = true;
        var msj = "Missing Information, please check.";
        if ($("#accion").val() == "" || $("#cantidad_ajuste").val() == "" || $("#motivo_ajuste").val() == "" || $("#id_prod_ajuste").val() == "") {
            swal("warning", msj, "error");
            bValid = false;
            return;
        }

        q = {};
        q.op = "pms_inventario_ajuste";
        q.motivo = $("#motivo_ajuste").val();
        q.cantidad = $("#cantidad_ajuste").val();
        q.accion = $("#accion").val();
        q.tec_product_id = $("#id_prod_ajuste").val();
        UTIL.callAjaxRqstPOST(q, INVENTARIO.saveAjusteHandler);
    },
    saveAjusteHandler: function(data) {
        UTIL.cursorNormal();
        if (data.output.valid) {
            INVENTARIO.successMessage();
        } else {
            swal("warning", data.output.response.content, "error");
        }
    },

    getInventarioDetalladaSalidas: function(id) {
        q = {};
        q.op = "pms_inventario_detallado";
        q.tec_product_id = id;
        UTIL.callAjaxRqstPOST(q, INVENTARIO.getInventarioDetalladaSalidasHandler);
    },
    getInventarioDetalladaSalidasHandler: function(data) {
        UTIL.cursorNormal();

        if (data.output.valid) {
            $("#myModalMovimiento").modal();
            var producto = data.output.producto[0];
            var response = data.output.arr; // Movimientos de los productos por ventas (SALIDAS)
            var response1 = data.output.arr1; // Movimientos de las salidas de inventario
            var response2 = data.output.arr2; // Ajustes de inventario
            var response3 = data.output.arr3; // Compras

            $("#prodName").empty().append(producto.nombre_prod);
            $("#cantidadActualProd").empty().append(producto.cant_actual);
          
            // Movimientos de las salidas de inventario

            var table1 = "";
            var cantidadSalidas = 0;
            for (var i in response1) {
                table1 += "<tr>";
                table1 += "<td>SALIDAS</td>";
                table1 += "<td>" + response1[i].id + "</td>";
                table1 += "<td>" + response1[i].cantidad + "</td>";
                table1 += "<td>" + response1[i].autoriza + "</td>";
                table1 += "<tr>";
                cantidadSalidas += parseFloat(response1[i].cantidad);
            }

            $("#cantidadSalidas").empty().append(cantidadSalidas);
            // Ajustes de inventario
            var table2 = "";
            for (var i in response2) {
                table2 += "<tr>";
                table2 += "<td>AJUSTES</td>";
                table2 += "<td>" + response2[i].id + "</td>";
                table2 += "<td>" + response2[i].cantidad + "</td>";
                table2 += "<td>" + response2[i].motivo + "</td>";
                table2 += "<td>" + response2[i].accion + "</td>";
                table2 += "<tr>";
            }
                   
            $("#tbodyProductos").empty().append(table);
            $("#tbodyProductosSalidas").empty().append(table1);
            $("#tbodyProductosAjustes").empty().append(table2);
           
        } else {
            swal("warning", data.output.response.content, "error");
        }

    },

};