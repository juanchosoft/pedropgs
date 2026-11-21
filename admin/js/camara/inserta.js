/* PGS report upload - compatible with save_photo.php Base64 API */
(function ($, window) {
  'use strict';
  var busy = false;
  var galleryUrl = null;
  var galleryFile = null;
  var MAX_FILE_BYTES = 30 * 1024 * 1024;
  var $form = $('#frm_foto');
  if (!$form.length) return;
  var $button = $('#btn_save');
  var $status = $('#pgsSaveStatus');
  var $progress = $('#pgsProgress');
  var $bar = $('#pgsProgressBar');
  var $file = $('#pgsGalleryFile');
  var $preview = $('#pgsGalleryPreview');
  var $placeholder = $('.defaultavatar');
  var originalLabel = $button.text().trim() || 'Save Report';
  function notify(title, message, type) {
    if (typeof swal === 'function') swal(title, message, type || 'info');
    else alert(title + ': ' + message);
  }
  function setStatus(message) {
    if ($status.length) $status.text(message).addClass('is-visible');
  }
  function setBusy(value, label) {
    busy = value;
    $button.prop('disabled', value).text(value ? (label || 'Sending…') : originalLabel);
  }
  function setProgress(value) {
    if (!$progress.length) return;
    var percent = Math.max(0, Math.min(100, Math.round(value)));
    $progress.prop('hidden', false).attr('aria-valuenow', percent);
    $bar.css('width', percent + '%');
  }
  function clearGallery() {
    galleryFile = null;
    $file.val('');
    if (galleryUrl) URL.revokeObjectURL(galleryUrl);
    galleryUrl = null;
    $preview.prop('hidden', true).removeAttr('src');
  }
  function syncMode() {
    var mode = $('input[name="radio_select"]:checked').attr('id');
    var gallery = mode === 'radiogaleria';
    $('#pgsGalleryWrap').prop('hidden', !gallery);
    $preview.prop('hidden', !gallery || !galleryFile);
    if (gallery) { $('#video').addClass('none'); $('#selectcamdevice').hide(); }
    if (gallery && galleryFile) $placeholder.addClass('none');
    if (gallery && !galleryFile) $placeholder.removeClass('none');
    if (gallery && typeof turnOffCamera === 'function') turnOffCamera();
    if (!gallery) { clearGallery(); $preview.prop('hidden', true); }
  }
  $('input[name="radio_select"]').on('change', syncMode);
  $file.on('change', function () {
    var file = this.files && this.files[0];
    clearGallery();
    if (!file) { syncMode(); return; }
    if (!file.type.startsWith('image/') && !/\.(jpe?g|png|webp|heic|heif)$/i.test(file.name)) {
      notify('Invalid file', 'Choose an image.', 'error'); return;
    }
    if (file.size > MAX_FILE_BYTES) {
      notify('Photo too large', 'Choose a photo smaller than 30 MB.', 'error'); return;
    }
    galleryFile = file;
    galleryUrl = URL.createObjectURL(file);
    $preview.attr('src', galleryUrl).prop('hidden', false);
    $placeholder.addClass('none');
    setStatus('Photo selected. It will be compressed before sending.');
  });
  function downsizeCamera(video) {
    if (!video || !video.videoWidth || !video.videoHeight) throw new Error('The camera is not ready.');
    var maxEdge = 1280;
    var scale = Math.min(1, maxEdge / Math.max(video.videoWidth, video.videoHeight));
    var canvas = document.getElementById('canvas') || document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(video.videoWidth * scale));
    canvas.height = Math.max(1, Math.round(video.videoHeight * scale));
    var ctx = canvas.getContext('2d', {alpha: false});
    if (!ctx) throw new Error('Could not prepare photo.');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    return canvas.toDataURL('image/jpeg', 0.68);
  }
  function galleryToDataUrl(file) {
    if (!window.PGSPhotoOptimizer) return Promise.reject(new Error('Photo compression is unavailable. Reload this page.'));
    return window.PGSPhotoOptimizer.optimizeFile(file, {maxEdge: 1280, quality: 0.68})
      .then(function (blob) { return new Promise(function (resolve, reject) {
        var reader = new FileReader();
        reader.onload = function () { resolve(reader.result); };
        reader.onerror = function () { reject(new Error('Unable to read photo.')); };
        reader.readAsDataURL(blob);
      }); });
  }
  function validate() {
    var property = $('#tbl_unidad_id').val();
    if (!property || property === 'selecct' || property === 'seleccione') throw new Error('Select a property.');
    if (!$('#zone').val().trim()) throw new Error('Enter the zone.');
    if (!$('#actividades').val().trim()) throw new Error('Describe the activity.');
    if (!navigator.onLine) throw new Error('Your phone appears to be offline. Check your connection before saving.');
  }
  function saveRequest(photo, takePicture) {
    return $.ajax({
      type: 'POST', url: 'admin/js/camara/save_photo.php', dataType: 'json', timeout: 120000,
      data: {
        foto: photo, take_picture: takePicture ? '1' : '0', id: $('#id').val(),
        zone: $('#zone').val().trim(), actividades: $('#actividades').val().trim(),
        observaciones: $('#observaciones').val().trim(), tbl_requerimiento_id: $('#tbl_unidad_id').val()
      },
      xhr: function () {
        var xhr = $.ajaxSettings.xhr();
        if (xhr.upload) xhr.upload.addEventListener('progress', function (event) {
          if (event.lengthComputable) {
            var percent = Math.round(100 * event.loaded / event.total);
            setProgress(percent);
            setStatus(percent < 100 ? 'Uploading: ' + percent + '%' : 'Upload complete. Waiting for server confirmation…');
          }
        });
        return xhr;
      }
    });
  }
  $form.off('submit.pgsOptimized').on('submit.pgsOptimized', function (e) {
    e.preventDefault();
    if (busy) return false;
    try { validate(); } catch (err) { notify('Check your report', err.message, 'warning'); return false; }
    var takePicture = $('#radiotfoto').is(':checked');
    var galleryMode = $('#radiogaleria').is(':checked');
    if (galleryMode && !galleryFile) { notify('Photo required', 'Choose a photo or select No picture.', 'warning'); return false; }
    if (takePicture && (typeof hasActiveCameraStream !== 'function' || !hasActiveCameraStream())) {
      notify('Camera not ready', 'Allow camera access or choose No picture.', 'warning'); return false;
    }
    setBusy(true, 'Preparing…');
    setStatus('Preparing report…');
    var photoPromise;
    if (takePicture) {
      try { photoPromise = Promise.resolve(downsizeCamera(document.getElementById('video'))); }
      catch (error) { photoPromise = Promise.reject(error); }
    } else if (galleryMode) {
      photoPromise = galleryToDataUrl(galleryFile);
    } else {
      photoPromise = Promise.resolve('');
    }
    photoPromise.then(function (dataUrl) {
      setBusy(true, 'Saving…');
      setProgress(0);
      setStatus('Sending report… Keep this screen open.');
      return saveRequest(dataUrl, takePicture || galleryMode);
    }).then(function (response) {
      if (!(response && response.success === true)) {
        notify('Unable to save', response && response.messages || 'The server did not accept the report.', 'error');
        setStatus('Not saved. Review the message and try again.');
        return;
      }
      setProgress(100);
      setStatus('Report successfully saved.');
      notify('Report created', (response.messages || 'Saved') + (window.PGS_CAN_EDIT_REPORT ? ' You can use the Edit button to add the after photo or description.' : ''), 'success');
      $form[0].reset();
      clearGallery();
      $('#radiosfoto').prop('checked', true).trigger('change');
      if (window.PGS_REPORT_QUICK && typeof window.PGS_REPORT_QUICK.showCreated === 'function') {
        window.PGS_REPORT_QUICK.showCreated(response.id || null);
      }
    }).catch(function (error) {
      if (error && error.status !== undefined) {
        var maybeSaved = error.status === 0 || error.statusText === 'timeout' || error.status >= 500;
        notify('Connection problem', maybeSaved
          ? 'The server did not confirm the result. Before sending again, check the report list to avoid duplicates.'
          : 'The server rejected the request. Please verify the report and try again.', 'error');
        setStatus('No confirmation from server. Verify before trying again.');
      } else {
        notify('Photo error', error && error.message || 'Unable to process the photo.', 'error');
        setStatus('Photo could not be processed.');
      }
    }).finally(function () { setBusy(false); });
    return false;
  });
  window.addEventListener('beforeunload', function (event) {
    if (!busy) return;
    event.preventDefault();
    event.returnValue = '';
  });
  window.addEventListener('pagehide', function () { clearGallery(); });
  syncMode();
})(jQuery, window);
