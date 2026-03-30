function btnSaveLoad() {
    $("#btn_save").html('Saving ...');
    $("#btn_save").attr("disabled", true);
}

function btnSave() {
    $("#btn_save").html('Save');
    $("#btn_save").attr("disabled", false);
}

$(document).ready(function () {
    
    $("#frm_foto").unbind('submit').bind('submit', function(event) {
        event.preventDefault();
        var id = $('#id').val();
        var $canvas      = document.getElementById('canvas');
        var $video      = document.getElementById('video');
        var contexto    = $canvas.getContext("2d");

        $canvas.width = $video.videoWidth;
        $canvas.height = $video.videoHeight;

        contexto.drawImage($video, 0, 0, $canvas.width, $canvas.height);
        var data = $canvas.toDataURL();
        var info = data.split(",", 2);

        $.ajax({
            type: "POST",
            url: "admin/js/camara/update_photo_after.php",
            data: {
                foto: data,
                id: id,
            },
            dataType: 'json',
            beforeSend: function() {
                btnSaveLoad();
            },
            success: function(response) {
                btnSave();
                if (response.success == true) {
                    setTimeout(function () {
                        location.reload();
                    }, 1500);
                    swal("MENSAJE", response.messages, "success");
                    // $("#frm_foto")[0].reset();
                    // $("#radiosfoto").click();

                    
                } else {
                    swal("MENSAJE", response.messages, "error");
                }
            }
        });

        return false;

    });


});