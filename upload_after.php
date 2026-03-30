<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Files</title>
    <!-- SUBIR IMAGEN AJAX -->
    <script language="javascript" src="admin/include/imagen_uploader/js/jquery-1.3.1.min.js"></script>
    <script language="javascript" src="admin/include/imagen_uploader/js/AjaxUpload.2.0.min.js"></script>
    <script language="javascript">
        $(document).ready(function() {
            var button = $('#upload_button'),
                interval;
            new AjaxUpload('#upload_button', {
                action: 'upload_images_after_ajax.php',
                onSubmit: function(file, ext) {
                    extensiones_permitidas = new Array(".jpg", ".png", ".jpeg", ".bmp");
                    if (!(ext && /^(jpg|png|jpeg|bmp)$/.test(ext))) {
                        //Extensiones permitidas
                        alert('Error: Solo se permiten archivos con extenciones:' + extensiones_permitidas);
                        // Cancela upload
                        return false;
                    } else {
                        button.text('Upload File...');
                        this.disable();
                    }
                },

                onComplete: function(file, response) {
                    button.text('Pic uploaded.');
                    $('#valor_iframe').val('1');
                    // nable upload button
                    this.enable();
                    // Agrega archivo a la lista
                    $('#lista').appendTo('.files').text(file);
                }
            });
        });
    </script>
    <link href="admin/include/imagen_uploader/style.css" rel="stylesheet" type="text/css" />
    <!-- FIN IMAGEN AJAX -->
</head>

<body>
    <div style="width: 130px;" id="upload_button">Upload File.</div>
    <input type="hidden" value="0" name="valor_iframe" id="valor_iframe" />
</body>

</html>