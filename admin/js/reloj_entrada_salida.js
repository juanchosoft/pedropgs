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
        var text = label || (status === 'checkout' ? 'Check-out' : (status === 'done' ? 'Completed' : 'Check-in'));

        window.PGS_CLOCK_STATUS = status;
        $btn.attr("data-status", status).text(text);
        if ($title.length) {
            $title.text(text);
        }

        if (status === 'checkout') {
            $hint.text('Press Check-out to end your shift.');
            $btn.prop('disabled', !window.PGS_HAS_EMPLOYEE);
        } else if (status === 'done') {
            $hint.text('You already completed Check-in and Check-out for today.');
            $btn.prop('disabled', true);
        } else if (status === 'checkin') {
            $hint.text('Press Check-in to start your shift.');
            $btn.prop('disabled', !window.PGS_HAS_EMPLOYEE);
        }
    },
    validate() {
        if (!window.PGS_HAS_EMPLOYEE) {
            swal("Employee required", "Your user is not linked to an employee. Ask an administrator to associate your account.", "error");
            return;
        }

        var status = window.PGS_CLOCK_STATUS || $("#btn-send").attr("data-status") || "checkin";
        if (status === "done") {
            swal("Already completed", "You already registered Check-in and Check-out for today.", "info");
            return;
        }
        if (status === "checkout") {
            // Check-out only allowed after Check-in (backend also enforces this)
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
