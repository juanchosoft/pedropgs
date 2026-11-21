function btnSaveLoad() {
    $("#btn_save").html('Saving ...');
    $("#btn_save").attr("disabled", true);
}

function btnSave() {
    $("#btn_save").html('Save Report');
    $("#btn_save").attr("disabled", false);
}

$(document).ready(function() {

    $(".textoGlo").keypress(function(key) {
        if ((key.charCode < 97 || key.charCode > 122) &&
            (key.charCode < 65 || key.charCode > 90) &&
            (key.charCode != 45) &&
            (key.charCode != 241) &&
            (key.charCode != 209) &&
            (key.charCode != 32)
        )
            return false;
    });
    $(".numeroDni").keypress(function(key) {
        if ((key.charCode < 48 || key.charCode > 57))
            return false;
    });
    $('.numeroDni').on('keydown keypress', function(e) {
        if (e.key.length === 1) {
            if ($(this).val().length < 8 && !isNaN(parseFloat(e.key))) {
                $(this).val($(this).val() + e.key);
            }
            return false;
        }
    });

    $("#frm_foto").unbind('submit').bind('submit', function(event) {
        event.preventDefault();

        var id = $('#id').val();
        var zone = $('#zone').val();
        var actividades = $('#actividades').val();
        var observaciones = $('#observaciones').val();
        var tbl_requerimiento_id = $('#tbl_unidad_id').val();
        var takePicture = $('#radiotfoto').is(':checked');
        var $canvas = document.getElementById('canvas');
        var $video = document.getElementById('video');
        var foto = '';

        if (takePicture) {
            var cameraOk = (typeof hasActiveCameraStream === 'function')
                ? hasActiveCameraStream()
                : (typeof VIDEO_DATA !== 'undefined' && VIDEO_DATA && $video && $video.srcObject);
            if (!cameraOk || !$video.videoWidth) {
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
            foto = $canvas.toDataURL('image/jpeg', 0.5);
        }

        $.ajax({
            type: "POST",
            url: "admin/js/camara/save_photo.php",
            data: {
                foto: foto,
                take_picture: takePicture ? '1' : '0',
                id: id,
                zone: zone,
                actividades: actividades,
                observaciones: observaciones,
                tbl_requerimiento_id: tbl_requerimiento_id,
            },
            dataType: 'json',
            beforeSend: function() {
                btnSaveLoad();
            },
            success: function(response) {
                btnSave();
                if (response && response.success == true) {
                    swal(
                        "Report created",
                        (response.messages || "Saved") +
                            (window.PGS_CAN_EDIT_REPORT
                                ? " Use the floating Edit button to add the after photo or description."
                                : ""),
                        "success"
                    );
                    $("#frm_foto")[0].reset();
                    $("#radiosfoto").prop("checked", true).trigger("change");
                    if (window.PGS_REPORT_QUICK && typeof window.PGS_REPORT_QUICK.showCreated === "function") {
                        window.PGS_REPORT_QUICK.showCreated(response.id || null);
                    }
                } else {
                    swal("MENSAJE", (response && response.messages) ? response.messages : "Error saving", "error");
                }
            },
            error: function() {
                btnSave();
                swal("MENSAJE", "Error saving the report. Please try again.", "error");
            },
            complete: function() {
                btnSave();
            }
        });

        return false;
    });

});
