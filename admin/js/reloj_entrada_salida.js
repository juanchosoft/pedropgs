$(document).ready(function() {
    q = {};
    requestLocation();
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
    // no longer auto-validates; waits for button click
}

var RELOJENTRADASALIDA = {
    updateButtonUI: function (status, label) {
        var $btn = $("#btn-send");
        var $title = $("#rt-action-title");
        var $hint = $("#rt-action-hint");
        var nextStatus = (status === 'checkout') ? 'checkout' : 'checkin';
        var text = label || (nextStatus === 'checkout' ? 'Check-out' : 'Check-in');

        window.PGS_CLOCK_STATUS = nextStatus;
        $btn.attr("data-status", nextStatus).text(text).prop('disabled', !window.PGS_HAS_EMPLOYEE);
        if ($title.length) {
            $title.text(text);
        }

        if (nextStatus === 'checkout') {
            $hint.text('Press Check-out to end this period. You can Check-in again later today.');
        } else {
            $hint.text('Press Check-in to start a new period. Multiple entries/exits are allowed per day.');
        }
    },
    validate() {
        if (!window.PGS_HAS_EMPLOYEE) {
            swal("Employee required", "Your user is not linked to an employee. Ask an administrator to associate your account.", "error");
            return;
        }

        var coords = $("#coords").val();
        if (!coords || coords == '0,0') {
            requestLocation();
            setTimeout(function() {
                var c = $("#coords").val();
                if (c && c != '0,0') {
                    RELOJENTRADASALIDA.validateEntradaSalida();
                } else {
                    requestLocation();
                    setTimeout(function() {
                        var c2 = $("#coords").val();
                        if (c2 && c2 != '0,0') {
                            RELOJENTRADASALIDA.validateEntradaSalida();
                        } else {
                            swal("Location required", "Could not obtain your location. Check that location is enabled and try again.", "error");
                        }
                    }, 3000);
                }
            }, 3000);
            return;
        }

        RELOJENTRADASALIDA.validateEntradaSalida();
    },
    validateEntradaSalida: function () {
        q = {};
        q.op = "pms_saveentradasalida";
        q.coords = $("#coords").val();
        UTIL.callAjaxRqstPOST(q, RELOJENTRADASALIDA.savedataHandler);
    },
    savedataHandler: function (data) {
        UTIL.cursorNormal();
        if (data.output.valid) {
            $("#coords").val('');
            requestLocation();
            if (data.output.next_status) {
                RELOJENTRADASALIDA.updateButtonUI(data.output.next_status, data.output.next_label || '');
            }
            swal("Important", data.output.response, "success");
        } else {
            swal("Missing information", data.output.response.content, "error");
        }
    }
}
