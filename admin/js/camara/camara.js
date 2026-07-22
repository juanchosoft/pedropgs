var localstream, canvas, video, cxt;

const tieneSoporteUserMedia = () =>
    !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia) ||
    !!(navigator.getUserMedia || navigator.mozGetUserMedia || navigator.webkitGetUserMedia || navigator.msGetUserMedia);

let $listaDeDispositivos = document.querySelector("#listaDeDispositivos");
let $video = document.getElementById("video");
let currentStream;
let VIDEO_DATA = false;
let cameraRequestInFlight = false;

function stopMediaTracks(stream) {
    if (!stream) return;
    stream.getTracks().forEach(function (track) {
        track.stop();
    });
}

function stopCurrentStream() {
    if (typeof currentStream !== "undefined" && currentStream) {
        stopMediaTracks(currentStream);
        currentStream = undefined;
    }
    if ($video) {
        try {
            $video.pause();
        } catch (e) {}
        $video.srcObject = null;
    }
    VIDEO_DATA = false;
}

function updateCameraSelectVisibility() {
    if (!$listaDeDispositivos) return;
    var count = $listaDeDispositivos.options.length;
    if (count > 1) {
        $("#selectcamdevice").css("display", "block");
    } else {
        $("#selectcamdevice").css("display", "none");
    }
}

function resetToNoPicture() {
    stopCurrentStream();
    $(".defaultavatar").removeClass("none");
    $("#video").addClass("none");
    $("#selectcamdevice").css("display", "none");
    $("#radiosfoto").prop("checked", true);
    $("#radiotfoto").prop("checked", false);
}

function handleCameraDenied(error) {
    resetToNoPicture();
    var msg =
        "Camera permission is required to take a picture. Please allow camera access and try again.";
    if (error && (error.name === "NotAllowedError" || error.name === "PermissionDeniedError")) {
        msg =
            "Camera permission was denied. Enable camera access for this site in your browser settings, then select “Take a picture” again.";
    } else if (error && error.name === "NotFoundError") {
        msg = "No camera was found on this device.";
    } else if (error && error.message) {
        msg = error.message;
    }
    if (typeof swal === "function") {
        swal("Camera required", msg, "error");
    } else {
        alert(msg);
    }
}

function gotDevices(mediaDevices) {
    if (!$listaDeDispositivos) return;
    $listaDeDispositivos.innerHTML = "";
    var count = 1;
    var preferredId = null;
    var currentId =
        currentStream && currentStream.getVideoTracks && currentStream.getVideoTracks()[0]
            ? currentStream.getVideoTracks()[0].getSettings().deviceId
            : null;

    mediaDevices.forEach(function (mediaDevice) {
        if (mediaDevice.kind !== "videoinput") return;
        var option = document.createElement("option");
        option.value = mediaDevice.deviceId;
        var label = mediaDevice.label || "Camera " + count++;
        option.appendChild(document.createTextNode(label));
        $listaDeDispositivos.appendChild(option);

        var lower = (mediaDevice.label || "").toLowerCase();
        if (lower.indexOf("back") !== -1 || lower.indexOf("trasera") !== -1) {
            preferredId = mediaDevice.deviceId;
        }
    });

    if (currentId) {
        $listaDeDispositivos.value = currentId;
    } else if (preferredId) {
        $listaDeDispositivos.value = preferredId;
    }

    updateCameraSelectVisibility();
}

const mostrarStream = function (idDeDispositivo) {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        handleCameraDenied({ message: "Your browser does not support camera access." });
        return;
    }

    if (typeof currentStream !== "undefined") {
        stopMediaTracks(currentStream);
        currentStream = undefined;
    }

    var videoConstraints = {};
    if (!idDeDispositivo) {
        videoConstraints = { facingMode: { ideal: "environment" } };
    } else {
        videoConstraints = { deviceId: { exact: idDeDispositivo } };
    }

    var constraints = {
        video: videoConstraints,
        audio: false
    };

    cameraRequestInFlight = true;
    navigator.mediaDevices
        .getUserMedia(constraints)
        .catch(function (err) {
            // Si falla con deviceId exacto, reintentar con facingMode para volver a pedir permiso
            if (idDeDispositivo) {
                return navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: "environment" } },
                    audio: false
                });
            }
            throw err;
        })
        .then(function (stream) {
            cameraRequestInFlight = false;
            currentStream = stream;
            localstream = stream;
            VIDEO_DATA = true;
            $video.srcObject = stream;
            return $video.play().catch(function () {
                return null;
            });
        })
        .then(function () {
            return navigator.mediaDevices.enumerateDevices();
        })
        .then(function (devices) {
            gotDevices(devices);
        })
        .catch(function (error) {
            cameraRequestInFlight = false;
            VIDEO_DATA = false;
            handleCameraDenied(error);
        });
};

$("#listaDeDispositivos").on("change", function () {
    var selected = $listaDeDispositivos.value;
    if (selected) {
        mostrarStream(selected);
    }
});

/**
 * Siempre solicita permiso de cámara al elegir “Take a picture”.
 * No depende de enumerateDevices previo (sin permiso a veces no lista cámaras).
 */
function turnOnCamera() {
    if (!tieneSoporteUserMedia()) {
        handleCameraDenied({ message: "Your browser does not support camera access." });
        return;
    }
    // Forzar petición de permiso cada vez que se elige Take a picture
    mostrarStream("");
}

function turnOffCamera() {
    stopCurrentStream();
}

$("#radiotfoto").on("click change", function () {
    if (!$("#radiotfoto").is(":checked")) return;

    $(".defaultavatar").addClass("none");
    $("#subirfoto").addClass("none");
    $("#video").removeClass("none");
    // El select de cámaras solo se muestra si hay más de una (tras permiso)
    $("#selectcamdevice").css("display", "none");
    turnOnCamera();
    if ($("#subirfoto").length) {
        document.getElementById("subirfoto").value = null;
    }
});

$("#radiosfoto").on("click change", function () {
    if (!$("#radiosfoto").is(":checked")) return;
    $("#subirfoto").removeClass("none");
    $("#video").addClass("none");
    $("#selectcamdevice").css("display", "none");
    $(".defaultavatar").removeClass("none");
    turnOffCamera();
});

function hasActiveCameraStream() {
    if (!VIDEO_DATA || !$video || !$video.srcObject) return false;
    var stream = $video.srcObject;
    if (!stream.getVideoTracks) return false;
    var tracks = stream.getVideoTracks();
    return tracks.length > 0 && tracks.some(function (t) {
        return t.readyState === "live";
    });
}
