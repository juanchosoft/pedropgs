$(document).on('ready', init);

var q, nombre, allFields, tips;



function init() {

    q = {};

}



var return_page = 'configuracion.php';

var CONFIGURACION = {

    /**

     * Método para editar y mostrar los datos de un configuracion

     */

    editdata: function() {

        q = {};

        q.op = "pms_getconf";

        UTIL.callAjaxRqstPOST(q, this.editdatahandler);

    },

    editdatahandler: function(data) {

        UTIL.cursorNormal();

        if (data.output.valid) {

            var res = data.output.response[0];

            $("#id").val(res.id);

            $("#empresa").val(res.empresa).trigger("change");

            $("#pass1").val(res.pass1).trigger("change");

            $("#pass2").val(res.pass2).trigger("change");

            $("#pass3").val(res.pass3).trigger("change");

            $("#email").val(res.email).trigger("change");

            $("#encabezado_fac").val(res.encabezado_fac).trigger("change");

            $("#pie_fac").val(res.pie_fac).trigger("change");



            $("#nit").val(res.nit).trigger("change");

            $("#razon_social").val(res.razon_social).trigger("change");

            $("#telefono").val(res.telefono).trigger("change");

            $("#direccion").val(res.direccion).trigger("change");

            $("#comentarios").val(res.comentarios).trigger("change");

            $("#config_precio_productos").val(res.config_precio_productos).trigger("change");

            $("#caja_recibe_pagos").val(res.caja_recibe_pagos).trigger("change");

            $("#impresion_termica").val(res.impresion_termica).trigger("change");

            $("#valor_bolsa").val(res.valor_bolsa).trigger("change");

            $("#texto_descripcion_larga_pie_pagina").val(res.texto_descripcion_larga_pie_pagina).trigger("change");

            $("#texto_resolucion").val(res.texto_resolucion).trigger("change");

            $("#ciudad").val(res.ciudad).trigger("change");

        } else {

            swal("warning", data.output.response.content, "error");

        }

    },

    /**



     /**

     * Funcion que me indica que se han guardado o editado el registro correctamente

     */

    successMessage: function() {

        swal("Información guardada correctamente ", "", "success");

        setTimeout(function() {

            window.location = return_page;

        }, 1000);

    },

    savedata: function() {

        q = {};

        q.op = "pms_confsave";

        q.id = $("#id").val();

        q.empresa = $("#empresa").val();

        q.pass1 = $("#pass1").val();

        q.pass2 = $("#pass2").val();

        q.pass3 = $("#pass3").val();

        q.email = $("#email").val();

        q.encabezado_fac = $("#encabezado_fac").val();

        q.pie_fac = $("#pie_fac").val();

        q.fileToUpload = $("#fileToUpload").val();

        q.nit = $("#nit").val();

        q.razon_social = $("#razon_social").val();

        q.telefono = $("#telefono").val();

        q.direccion = $("#direccion").val();

        q.comentarios = $("#comentarios").val();

        q.config_precio_productos = $("#config_precio_productos").val();

        q.caja_recibe_pagos = $("#caja_recibe_pagos").val();

        q.impresion_termica = $("#impresion_termica").val();

        q.valor_bolsa = $("#valor_bolsa").val();

        q.texto_descripcion_larga_pie_pagina = $("#texto_descripcion_larga_pie_pagina").val();

        q.texto_resolucion = $("#texto_resolucion").val();

        q.ciudad = $("#ciudad").val();

        UTIL.callAjaxRqstPOST(q, CONFIGURACION.savedataHandler);

    },

    savedataHandler: function(data) {

        UTIL.cursorNormal();

        if (data.output.valid) {

            swal("Configuración guardada correctamente ", "", "success");

            setTimeout(function() {

                window.location = 'configuracion.php';

            }, 1000);

        } else {

            swal("warning", data.output.response.content, "error");

        }

    },



};