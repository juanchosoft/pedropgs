function btnSaveLoad() {
    $("#btn_save").html("Saving ...");
    $("#btn_save").attr("disabled", true);
}

function btnSave() {
    $("#btn_save").html("Save");
    $("#btn_save").attr("disabled", false);
}

function resetAfterPhotoUi() {
    if (typeof turnOffCamera === "function") {
        turnOffCamera();
    } else if (typeof stopCurrentStream === "function") {
        stopCurrentStream();
    }
    $("#radiosfoto").prop("checked", true);
    $("#radiotfoto").prop("checked", false);
    $(".defaultavatar").removeClass("none");
    $("#video").addClass("none");
    $("#selectcamdevice").css("display", "none");
}

$(document).ready(function () {
    $("#myModalAfterPhoto").on("shown.bs.modal", function () {
        resetAfterPhotoUi();
    });

    $("#myModalAfterPhoto").on("hidden.bs.modal", function () {
        resetAfterPhotoUi();
    });

    $("#frm_foto")
        .unbind("submit")
        .bind("submit", function (event) {
            event.preventDefault();

            var id = $("#id").val();
            var takePicture = $("#radiotfoto").is(":checked");
            var $canvas = document.getElementById("canvas");
            var $video = document.getElementById("video");

            if (!takePicture) {
                swal(
                    "Picture required",
                    "Select “Take a Picture”, allow camera access, and capture the after photo.",
                    "warning"
                );
                btnSave();
                return false;
            }

            var cameraOk =
                typeof hasActiveCameraStream === "function"
                    ? hasActiveCameraStream()
                    : typeof VIDEO_DATA !== "undefined" && VIDEO_DATA && $video && $video.srcObject;

            if (!cameraOk || !$video || !$video.videoWidth) {
                swal(
                    "Camera required",
                    "Camera permission is required to take a picture. Allow access and select “Take a picture” again.",
                    "error"
                );
                btnSave();
                return false;
            }

            var contexto = $canvas.getContext("2d");
            $canvas.width = $video.videoWidth;
            $canvas.height = $video.videoHeight;
            contexto.drawImage($video, 0, 0, $canvas.width, $canvas.height);
            var data = $canvas.toDataURL("image/jpeg", 0.5);

            $.ajax({
                type: "POST",
                url: "admin/js/camara/update_photo_after.php",
                data: {
                    foto: data,
                    id: id,
                },
                dataType: "json",
                beforeSend: function () {
                    btnSaveLoad();
                },
                success: function (response) {
                    btnSave();
                    if (response && response.success == true) {
                        resetAfterPhotoUi();
                        swal("MENSAJE", response.messages, "success");
                        setTimeout(function () {
                            location.reload();
                        }, 1200);
                    } else {
                        swal(
                            "MENSAJE",
                            (response && response.messages) || "Error saving photo",
                            "error"
                        );
                    }
                },
                error: function () {
                    btnSave();
                    swal("MENSAJE", "Error saving the photo. Please try again.", "error");
                },
                complete: function () {
                    btnSave();
                },
            });

            return false;
        });
});
