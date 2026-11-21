/* PGS Centrum - After photo, mobile-first, compatible with the existing Base64 endpoint.
 * Replace: admin/js/camara/update.js
 * Server endpoint remains: admin/js/camara/update_photo_after.php
 */
(function (window, $) {
    'use strict';
    if (!$) return;

    var MAX_EDGE = 1280;
    var JPEG_QUALITY = 0.68;
    var MAX_INPUT_BYTES = 30 * 1024 * 1024;
    var isSaving = false;
    var selectedFile = null;
    var previewUrl = null;
    var operationToken = 0;
    var $modal, $form, $save, $progress, $progressBar, $progressText, $file, $preview;

    function message(title, body, type) {
        if (typeof window.swal === 'function') {
            window.swal(title, body, type || 'info');
        } else {
            window.alert(title + '\n' + body);
        }
    }

    function setSaving(loading, label) {
        isSaving = loading;
        if (!$save || !$save.length) return;
        $save.prop('disabled', loading)
            .text(loading ? (label || 'Uploading photo...') : 'Save after photo');
    }

    function progress(percentage, text) {
        if (!$progress || !$progress.length) return;
        $progress.show();
        $progressBar.css('width', Math.min(100, Math.max(0, percentage)) + '%');
        $progressBar.attr('aria-valuenow', Math.round(percentage));
        $progressText.text(text || 'Preparing image...');
    }

    function clearPreview() {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = null;
        selectedFile = null;
        if ($file) $file.val('');
        if ($preview) $preview.hide().removeAttr('src');
    }

    function resetAfterPhotoUi() {
        operationToken++;
        clearPreview();
        if (typeof window.turnOffCamera === 'function') window.turnOffCamera();
        else if (typeof window.stopCurrentStream === 'function') window.stopCurrentStream();
        if ($modal && $modal.length) {
            $modal.find('#radiosfoto').prop('checked', true);
            $modal.find('#radiotfoto').prop('checked', false);
            $modal.find('.defaultavatar').removeClass('none').show();
            $modal.find('#video').addClass('none');
            $modal.find('#selectcamdevice').hide();
        }
        if ($progress) $progress.hide();
    }

    // Preserve compatibility with existing REPORT.editPhotoAfterHandler.
    window.resetAfterPhotoUi = resetAfterPhotoUi;
    window.btnSaveLoad = function () { setSaving(true, 'Uploading photo...'); };
    window.btnSave = function () { setSaving(false); };

    function imageFromFile(file) {
        return new Promise(function (resolve, reject) {
            var url = URL.createObjectURL(file);
            var img = new Image();
            img.onload = function () { resolve({ image: img, url: url }); };
            img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('This image cannot be opened. Try another photo.')); };
            img.src = url;
        });
    }

    function scaledCanvas(width, height) {
        var factor = Math.min(1, MAX_EDGE / Math.max(width, height));
        var canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(width * factor));
        canvas.height = Math.max(1, Math.round(height * factor));
        return canvas;
    }

    async function getPhotoData(token) {
        if (selectedFile) {
            var file = selectedFile;
            if (file.size > MAX_INPUT_BYTES) throw new Error('This photo is too large. Please select a smaller photo.');
            if (!file.type.startsWith('image/')) throw new Error('Please select an image.');
            var decoded = await imageFromFile(file);
            try {
                if (token !== operationToken) throw new Error('Photo selection changed.');
                var img = decoded.image;
                var width = img.naturalWidth, height = img.naturalHeight;
                if (!width || !height) throw new Error('Empty image.');
                var canvas = scaledCanvas(width, height);
                var ctx = canvas.getContext('2d', { alpha: false });
                if (!ctx) throw new Error('Photo processing is not available on this device.');
                ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                return canvas.toDataURL('image/jpeg', JPEG_QUALITY);
            } finally {
                URL.revokeObjectURL(decoded.url);
            }
        }

        var $video = $modal.find('#video')[0];
        var cameraOk = typeof window.hasActiveCameraStream === 'function'
            ? window.hasActiveCameraStream()
            : !!($video && $video.srcObject && $video.videoWidth);
        if (!$modal.find('#radiotfoto').is(':checked') || !cameraOk || !$video || !$video.videoWidth) {
            throw new Error('Take a picture or select one from your phone before saving.');
        }
        var canvas = scaledCanvas($video.videoWidth, $video.videoHeight);
        var context = canvas.getContext('2d', { alpha: false });
        if (!context) throw new Error('Cannot capture this photo.');
        context.drawImage($video, 0, 0, canvas.width, canvas.height);
        return canvas.toDataURL('image/jpeg', JPEG_QUALITY);
    }

    function setupGallery() {
        if ($modal.find('#pgsAfterGallery').length) return;
        var $picker = $('<div>', { id: 'pgsAfterGallery', class: 'mt-3 mb-3' });
        $('<label>', { for: 'pgsAfterFile', text: 'Or choose a photo from your phone',
            css: { display: 'block', fontWeight: '700', color: '#152238', marginBottom: '7px' } }).appendTo($picker);
        $file = $('<input>', { id: 'pgsAfterFile', type: 'file', accept: 'image/*', class: 'form-control',
            css: { height: 'auto', minHeight: '48px', padding: '10px', width: '100%' } }).appendTo($picker);
        $('<small>', { text: 'Photos are compressed automatically to upload faster.',
            css: { display: 'block', marginTop: '6px', color: '#475569' } }).appendTo($picker);
        $preview = $('<img>', { alt: 'Selected after photo preview',
            css: { width: '100%', maxHeight: '250px', objectFit: 'contain', borderRadius: '12px', marginTop: '12px', display: 'none' } }).appendTo($picker);
        // Insert inside the after-photo modal only, before the save button.
        $modal.find('#btn_save').first().before($picker);
        $file.on('change', function () {
            clearPreview();
            var candidate = this.files && this.files[0];
            if (!candidate) return;
            if (!candidate.type.startsWith('image/')) {
                message('Invalid photo', 'Select a valid image.', 'warning'); return;
            }
            if (candidate.size > MAX_INPUT_BYTES) {
                message('Photo too large', 'Choose an image smaller than 30 MB.', 'warning'); return;
            }
            selectedFile = candidate;
            previewUrl = URL.createObjectURL(candidate);
            $preview.attr('src', previewUrl).show();
            // Selecting from gallery turns off the video to save battery and CPU.
            $modal.find('#radiosfoto').prop('checked', true).trigger('change');
        });
    }

    $(function () {
        $modal = $('#myModalAfterPhoto');
        $form = $modal.find('#frm_foto');
        $save = $modal.find('#btn_save');
        if (!$modal.length || !$form.length || !$save.length) return;

        setupGallery();
        $progress = $('<div>', { id: 'pgsAfterProgress', role: 'status', 'aria-live': 'polite',
            css: { display: 'none', width: '100%', margin: '12px 0' } });
        $progressText = $('<div>', { text: 'Preparing photo...', css: { fontSize: '13px', marginBottom: '6px', color: '#334155' } });
        var $track = $('<div>', { css: { width: '100%', height: '8px', borderRadius: '6px', background: '#e5e7eb', overflow: 'hidden' } });
        $progressBar = $('<div>', { role: 'progressbar', 'aria-valuemin': 0, 'aria-valuemax': 100, 'aria-valuenow': 0,
            css: { width: 0, height: '100%', background: '#cc1634', transition: 'width .2s ease' } });
        $track.append($progressBar);
        $progress.append($progressText, $track);
        $save.before($progress);
        $save.text('Save after photo');

        $modal.on('hidden.bs.modal', function () {
            if (!isSaving) resetAfterPhotoUi();
        });
        $modal.on('change', '#radiotfoto', function () {
            if (this.checked) clearPreview();
        });

        $form.off('submit.pgsAfter').on('submit.pgsAfter', async function (event) {
            event.preventDefault();
            if (isSaving) return false;
            var id = Number($form.find('input[name="id"]').first().val());
            if (!Number.isSafeInteger(id) || id <= 0) {
                message('No report selected', 'Close this window and select the report again.', 'error');
                return false;
            }
            if (!navigator.onLine) {
                message('No connection', 'Connect to the internet before saving. Your photo has not been sent.', 'warning');
                return false;
            }
            setSaving(true, 'Preparing photo...');
            progress(5, 'Compressing photo on your phone...');
            var token = ++operationToken;
            var data;
            try {
                data = await getPhotoData(token);
                if (token !== operationToken) throw new Error('Photo selection changed. Please try again.');
            } catch (err) {
                setSaving(false);
                $progress.hide();
                message('Photo required', err.message || 'Could not prepare the photo.', 'warning');
                return false;
            }
            progress(10, 'Uploading compressed photo...');
            $.ajax({
                type: 'POST',
                url: 'admin/js/camara/update_photo_after.php',
                data: { foto: data, id: String(id) },
                dataType: 'json',
                timeout: 240000,
                xhr: function () {
                    var xhr = $.ajaxSettings.xhr();
                    if (xhr.upload) xhr.upload.addEventListener('progress', function (e) {
                        if (e.lengthComputable) progress(10 + 85 * e.loaded / e.total,
                            'Uploading photo: ' + Math.round(e.loaded / e.total * 100) + '%');
                    });
                    return xhr;
                },
                success: function (response) {
                    if (response && response.success === true) {
                        progress(100, 'Photo saved successfully.');
                        setSaving(false);
                        resetAfterPhotoUi();
                        message('Photo saved', response.messages || 'After photo saved correctly.', 'success');
                        // Reflect the updated report by loading fresh server data.
                        window.setTimeout(function () { window.location.reload(); }, 1200);
                    } else {
                        setSaving(false);
                        progress(0, 'Photo was not saved.');
                        message('Could not save', (response && response.messages) || 'Please check the selected report.', 'error');
                    }
                },
                error: function (xhr, status) {
                    setSaving(false);
                    progress(0, 'Could not verify whether the server saved the photo.');
                    message('Connection interrupted',
                        status === 'timeout'
                            ? 'The request timed out. Check the report before trying again to avoid duplicate uploads.'
                            : 'Could not confirm the result. Check the report before retrying.', 'warning');
                }
            });
            return false;
        });
    });
})(window, window.jQuery);
