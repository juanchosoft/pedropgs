var UTIL = {
    /**
     * Metodo para validar si la impresión es Termica
     */
    impresoraPosTermica: function() {
        if ($("#config_impresion_termica").val() == "si") {
            return true;
        } else {
            return false;
        }
    },

    /**----------------------------------------------------------------------------------------
     * Metodo para redireccionar a descagar factura en el link  de la DIAN
     *------------------------------------------------------------------------------------------**/
    rutaCatalogoVpfeDIAN: function() {
        return "https://catalogo-vpfe-hab.dian.gov.co/document/searchqr?documentkey=";
    },

    /**
     * Carga datepicker de jQueryUI
     * @param {String} id, id del campo
     */
    applyDatepicker: function(id) {
        $("#" + id).datepicker($.datepicker.regional["es"]);
        $("#" + id).datepicker({ changeMonth: true, changeYear: true });
        $("#" + id).datepicker("option", "dateFormat", "yy-mm-dd");
    },

    getPorcentajeIva: function(porcentaje) {
        if (parseInt(porcentaje) > 0) {
            switch (parseInt(porcentaje)) {
                case 19:
                    iva = 1.19;
                    break;
                case 10:
                    iva = 1.1;
                    break;
                case 5:
                    iva = 1.05;
                    break;
            }
            return iva;
        }
    },

    /**
     * Hace request por AJAX
     * @param {JSON} ladata, paramétros del request
     * @param {function} successCallBackFn, función que captura la respuesta onSuccess
     */
    callAjaxRqst: function(data, successCallBackFn, options) {
        options = options || {};
        if (!options.silent) {
            this.cursorBusy();
        }
        $.ajax({
            data: data,
            type: "GET",
            dataType: "json",
            url: "admin/ajax/rqst.php",
            success: function(resp) {
                if (!options.silent) {
                    UTIL.cursorNormal();
                }
                if (UTIL.handlePermissionResponse(resp)) {
                    return;
                }
                if (typeof successCallBackFn === "function") {
                    successCallBackFn(resp);
                }
            },
            error: function(xhr) {
                UTIL.handleAjaxTransportError(xhr);
            },
            complete: function() {
                UTIL.cursorNormal();
            }
        });
    },
    /**
     * Hace request por AJAX
     * @param {JSON} ladata, paramétros del request
     * @param {function} successCallBackFn, función que captura la respuesta onSuccess
     * @param {Object} [options] silent: true evita cursor wait (consultas en background)
     */
    callAjaxRqstPOST: function(data, successCallBackFn, options) {
        options = options || {};
        if (!options.silent) {
            this.cursorBusy();
        }
        $.ajax({
            data: data,
            type: "POST",
            dataType: "json",
            url: "admin/ajax/rqst.php",
            success: function(resp) {
                if (!options.silent) {
                    UTIL.cursorNormal();
                }
                if (UTIL.handlePermissionResponse(resp)) {
                    return;
                }
                if (typeof successCallBackFn === "function") {
                    successCallBackFn(resp);
                }
            },
            error: function(xhr) {
                UTIL.handleAjaxTransportError(xhr);
            },
            complete: function() {
                UTIL.cursorNormal();
            }
        });
    },

    isPermissionDeniedPayload: function(resp) {
        if (!resp || !resp.output) return false;
        if (resp.output.valid === true) return false;
        var content = "";
        if (resp.output.response && typeof resp.output.response === "object") {
            content = String(resp.output.response.content || "");
        } else if (typeof resp.output.response === "string") {
            content = resp.output.response;
        }
        var lower = content.toLowerCase();
        return lower.indexOf("permission") !== -1 || lower.indexOf("permiso") !== -1 || lower.indexOf("do not have") !== -1;
    },

    handlePermissionResponse: function(resp) {
        if (!UTIL.isPermissionDeniedPayload(resp)) {
            return false;
        }
        var msg = "You do not have permission to perform this operation.";
        if (resp.output && resp.output.response && resp.output.response.content) {
            msg = resp.output.response.content;
        }
        var now = Date.now();
        if (UTIL._lastPermAlertAt && now - UTIL._lastPermAlertAt < 1500 && UTIL._lastPermAlertMsg === msg) {
            return true;
        }
        UTIL._lastPermAlertAt = now;
        UTIL._lastPermAlertMsg = msg;
        if (typeof swal === "function") {
            swal("Permission denied", msg, "error");
        } else {
            alert(msg);
        }
        return true;
    },

    handleAjaxTransportError: function(xhr) {
        UTIL.cursorNormal();
        var resp = null;
        try {
            resp = JSON.parse(xhr.responseText);
        } catch (e) {}
        if (xhr.status === 403 || UTIL.isPermissionDeniedPayload(resp)) {
            UTIL.handlePermissionResponse(resp || {
                output: {
                    valid: false,
                    response: { content: "You do not have permission to perform this operation." }
                }
            });
            return;
        }
        if (typeof swal === "function") {
            swal("Error", "Request failed. Please try again.", "error");
        }
    },
    /**
     * Limppia un formulario
     * @param {String} id, id del formulario
     */
    clearForm: function(id) {
        $("#" + id + " :input").each(function() {
            if ('button' != $(this).attr('type')) {
                $(this).val('');
            }
        });
        $('select').val('seleccione');

        //Removing the error elements from the from-group,For bootstrapvalidator, this might useful when the form being display via bootstrap modal,
        // Extracción de los elementos de error de un grupo
        $('.form-group').removeClass('has-error has-feedback');
        $('.form-group').find('small.help-block').hide();
        $('.form-group').find('i.form-control-feedback').hide();
    },
    clearForm2: function(id) {
        $("#" + id + " :input").each(function() {
            if ('button' != $(this).attr('type')) {
                $(this).val('');
            }
        });
    },
    /**
     * Pone el cursor ocupado
     */
    cursorBusy: function() {
        $('body').css('cursor', 'wait');
    },
    /**
     * Pone el cursor normal
     */
    cursorNormal: function() {
        $('body').css('cursor', '');
    },
    /**
     * Verifica que un correo esta bien escrito
     * @param {String} email
     * @returns {bool},
     */
    isEmail: function(email) {
        var regex = /^([a-zA-Z0-9_\.\-\+])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/;
        return regex.test(email);
    },
    parcejson: function() {
        var str = '{\"registro_fecha\":\"2013-02-10\",\"registro_sistema\":\"nombre sistema\",\"registro_actividad\":\"4\",\"undefined\":\"CARGAR\",\"registro_campo01\":\"2 horas\",\"registro_campo02\":\"0\",\"registro_campo03\":\"2013-02-12\",\"registro_campo04\":\"1\",\"registro_campo05\":\"0\",\"registro_campo06\":\"cerrado autom","nota":"las notas van aqu","fecha":"2013-02-10","tsfecha":"2013-02-10 08:23:13"}';
        var obj = JSON.parse(str);
        alert('registro_fecha1 = ' + obj.registro_fecha);
        alert('registro_fecha2 = ' + obj["registro_fecha"]);
        $("#registro :input").each(function() {
            var idprop = $(this).attr('id');
            if (obj.hasOwnProperty(idprop)) {
                $(this).val(obj[idprop]);
            }
        });
    },
    /**
     * Llena un formulario con un objeto JSON
     * @param {String} id
     * @param {JSON} jo
     */
    populateForm: function(id, jo) {
        $("#" + id + " :input").each(function() {
            var p = $(this).attr('id');
            if (jo.hasOwnProperty(p)) {
                $(this).val(jo[p]);
            }
        });
    },
    /**
     * Carga un estilo a un campo
     * @param {type} id
     */
    setrequirefield: function(id) {
        $("#" + id).addClass("requirefield");
    },
    /**
     * convierte los campos de un formulario en StringJSON
     * @param {type} id, id del formulario
     * @returns {String}, JSON en forma de String
     */
    stringifyFormJson: function(id) {
        var jo = {};
        $("#" + id + " :input").each(function() {
            jo[$(this).attr('id')] = $(this).val();
        });
        return JSON.stringify(jo);
    }
}

// funciones para usar con jQueryUI

function updateTips(t) {
    tips.text(t).addClass("ui-state-highlight");
    setTimeout(function() {
        tips.removeClass("ui-state-highlight", 1500);
    }, 500);
}

function checkLength(o, n, min, max) {
    if (o.val().length > max || o.val().length < min) {
        o.addClass("ui-state-error");
        updateTips("Longitud de " + n + " debe estar entre " +
            min + " y " + max + ".");
        return false;
    } else {
        return true;
    }
}

function checkRegexp(o, regexp, n) {
    if (!(regexp.test(o.val()))) {
        o.addClass("ui-state-error");
        updateTips(n);
        return false;
    } else {
        return true;
    }
}

function IsNumberOnly(element) {
    var value = $(element).val();
    if (value === undefined) {
        return null;
    }
    var regExp = "^\\d+$";
    return value.match(regExp);
}

function noPuntoComa(event) {
    var e = event || window.event;
    var key = e.keyCode || e.which;
    if (key === 44 || key === 110 || key === 190 || key === 188) {
        e.preventDefault();
    }
}

/** Aviso global ante denegaciones AJAX (403 / payload de permisos) en rqst.php */
$(document).ajaxError(function(event, xhr, settings) {
    if (!settings || !xhr) return;
    var url = String(settings.url || "");
    if (url.indexOf("admin/ajax/rqst.php") === -1) return;
    if (xhr.status === 403) {
        UTIL.handleAjaxTransportError(xhr);
        return;
    }
    var resp = null;
    try {
        resp = xhr.responseJSON || JSON.parse(xhr.responseText);
    } catch (e) {}
    if (UTIL.isPermissionDeniedPayload(resp)) {
        UTIL.handlePermissionResponse(resp);
    }
});

$(document).ajaxSuccess(function(event, xhr, settings) {
    if (!settings || !xhr) return;
    var url = String(settings.url || "");
    if (url.indexOf("admin/ajax/rqst.php") === -1) return;
    // Solo para $.ajax crudos que no pasan por UTIL.callAjax*
    if (settings.dataType !== "json" && settings.dataTypes && settings.dataTypes.indexOf("json") === -1) {
        return;
    }
    var resp = xhr.responseJSON;
    if (!resp) {
        try {
            resp = JSON.parse(xhr.responseText);
        } catch (e) {
            return;
        }
    }
    if (UTIL.isPermissionDeniedPayload(resp)) {
        UTIL.handlePermissionResponse(resp);
    }
});
