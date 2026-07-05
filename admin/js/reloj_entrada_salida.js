$(document).ready(function() {
    q = {};
    $("#cc").on("click touchstart focus", function() {
        requestLocation();
    });
});
var q;
var locationPending = false;

function requestLocation() {
    if (locationPending) return;
    locationPending = true;
    $("#coords").val("");
    if (!navigator.geolocation) {
        getLocationByIP();
        return;
    }
    navigator.geolocation.getCurrentPosition(function(position) {
        locationPending = false;
        $("#coords").val(position.coords.latitude+","+position.coords.longitude);
        tryAutoValidate();
    }, function() {
        locationPending = false;
        getLocationByIP();
    }, {
        enableHighAccuracy: true,
        timeout: 3000,
        maximumAge: 60000
    });
    setTimeout(function() {
        if (locationPending) {
            locationPending = false;
            getLocationByIP();
        }
    }, 2000);
}

function getLocationByIP() {
    $.getJSON('./admin/ajax/get_location.php', function(data) {
        if (data.lat && data.lon) {
            $("#coords").val(data.lat+","+data.lon);
            tryAutoValidate();
        }
    });
}

function tryAutoValidate() {
    if ($("#cc").val() != "" && $("#coords").val() != "" && $("#coords").val() != "0,0") {
        RELOJENTRADASALIDA.validateEntradaSalida();
    }
}

var RELOJENTRADASALIDA = {
    validate() {
        var coords = $("#coords").val();
        if ($("#cc").val() == "" || $("#fecha").val() == "") {
            swal("Error", 'Recuerde que todos los campos son obligatorios.', "error");
            return;
        }
        if (!coords || coords == '0,0') {
            if ($("#cc").val() != "") {
                requestLocation();
                var check = function() {
                    var c = $("#coords").val();
                    if (c && c != '0,0') {
                        RELOJENTRADASALIDA.validateEntradaSalida();
                    } else {
                        setTimeout(function() {
                            var c2 = $("#coords").val();
                            if (c2 && c2 != '0,0') {
                                RELOJENTRADASALIDA.validateEntradaSalida();
                            } else {
                                requestLocation();
                                setTimeout(function() {
                                    var c3 = $("#coords").val();
                                    if (c3 && c3 != '0,0') {
                                        RELOJENTRADASALIDA.validateEntradaSalida();
                                    } else {
                                        swal("Location required", "Could not obtain your location. Check that location is enabled and try again.", "error");
                                    }
                                }, 3000);
                            }
                        }, 3000);
                    }
                };
                setTimeout(check, 1000);
            }
            return;
        }
        RELOJENTRADASALIDA.validateEntradaSalida();
    },
    validateEntradaSalida: function () {
        q = {};
        q.op = "pms_saveentradasalida";
        q.cc = $('#cc').val();
        q.fecha = $("#fecha").val();
        q.coords = $("#coords").val();
        UTIL.callAjaxRqst(q, RELOJENTRADASALIDA.savedataHandler);
    },
    savedataHandler: function (data) {
        UTIL.cursorNormal();
        if (data.output.valid) {
            $("#cc").val('');
            $("#fecha").val('');
            $("#coords").val('');
            swal("Important", data.output.response, "success");
        } else {
            swal("Missing information", data.output.response.content, "error");
        }
    }
}
