$(document).ready(function() {
    init();    
});
var q;

const regexLat = /^(-?[1-8]?\d(?:\.\d{1,18})?|90(?:\.0{1,18})?)$/;
const regexLon = /^(-?(?:1[0-7]|[1-9])?\d(?:\.\d{1,18})?|180(?:\.0{1,18})?)$/;

function check_lat_lon(lat, lon) {
  let validLat = regexLat.test(lat);
  let validLon = regexLon.test(lon);
  return validLat && validLon;
}

function init() {
    q = {};
    navigator.geolocation.getCurrentPosition(successCallback, errorCallback);
}

var RELOJENTRADASALIDA = {
    validate() {
        var bValid = true;
        var msj = 'Recuerde que todos los campos son obligatorios.';

        var coords = $("#coords").val();

        if (coords == '') {
            messageErrorLocation();
            bValid = false;
            return;
        }else{
            const arrCoords = coords.split(',');
            if (arrCoords.length < 2 || arrCoords.length > 2) {
                swal("Error", 'La ubicación es erronea.', "error");
                bValid = false;
                return;
            }else if(!check_lat_lon(arrCoords[0],arrCoords[1])){
                swal("Error", 'La ubicación es erronea.', "error");  
                bValid = false; 
                return;             
            }
        }

        if ($("#cc").val() == "" ||
            $("#fecha").val() == "") {
            bValid = false;
            swal("Error", msj, "error");
            return;
        }
        if (bValid) {
            RELOJENTRADASALIDA.validateEntradaSalida();
        }
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
            $("fecha").val('');
            $("coords").val('');
            swal("Important", data.output.response, "success");
                    // setTimeout(function () {
                    //     window.location = 'reloj.php';
                    // }, 4500);
        } else {
            swal("Missing information", data.output.response.content, "error");
        }
    }
}

function messageErrorLocation(){
    swal("Error", 'La ubicación es requerida, por favor permita acceder a su ubicación.', "error");
}

const successCallback = (position) => {
    var coordsFinal = position.coords.latitude+","+position.coords.longitude;
    $("#coords").val(coordsFinal);
    console.log(position)
};

const errorCallback = (error) => {
    $("#coords").val(""); 
    messageErrorLocation();
};

