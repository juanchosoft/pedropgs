/**
 * PGS Centrum - My Profile & Voice PIN Management
 */
$(document).ready(function() {
  'use strict';

  loadProfile();

  function loadProfile() {
    $.ajax({
      url: 'admin/ajax/rqst.php?op=prf_get',
      type: 'GET',
      dataType: 'json',
      success: function(resp) {
        if (!resp || !resp.output || !resp.output.valid) {
          PGS.notify('Could not load profile data: ' + (resp?.output?.response || 'Unknown error'));
          return;
        }
        var d = resp.output.response;
        $('#profFullName').val(d.full_name || (d.nombre + ' ' + d.apellido));
        $('#profNickname').val(d.nickname || '');
        $('#profPhone').val(d.phone_e164 || '');
        $('#profEmail').val(d.notify_email || '');
        $('#profLang').val(d.lang || 'en');
        $('#profWhatsappOptIn').prop('checked', !!d.whatsapp_opt_in);
        $('#badgeUserType').text(d.tipo + (d.is_internal_staff ? ' (PGS Staff)' : ''));

        if (d.whatsapp_opt_in && d.whatsapp_opt_in_at) {
          $('#optInTimestampLabel').text('Opt-in confirmed on: ' + d.whatsapp_opt_in_at + ' UTC');
        } else {
          $('#optInTimestampLabel').text('');
        }

        renderPinStatus(d.voice_pin);
      },
      error: function() {
        PGS.notify('Server connection error while loading profile.');
      }
    });
  }

  function renderPinStatus(pin) {
    if (!pin) return;
    var status = pin.status || 'not_set';
    var label = pin.label || 'Not set';

    $('#voicePinStatusLabel').text(label);

    var $badge = $('#voicePinBadge');
    $badge.removeClass('badge-success badge-warning badge-danger badge-secondary');

    if (status === 'active') {
      $badge.addClass('badge-success').text('Active');
    } else if (status === 'not_set') {
      $badge.addClass('badge-secondary').text('Not Configured');
    } else if (status === 'locked_temp') {
      $badge.addClass('badge-warning').text('Temporarily Locked');
    } else if (status === 'locked_admin') {
      $badge.addClass('badge-danger').text('Locked (Admin Reset Required)');
    } else {
      $badge.addClass('badge-warning').text(label);
    }
  }

  // Save profile form
  $('#formProfile').on('submit', function(e) {
    e.preventDefault();
    var btn = $('#btnSaveProfile');
    btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i> Saving...');

    var payload = {
      phone: $('#profPhone').val().trim(),
      notify_email: $('#profEmail').val().trim(),
      lang: $('#profLang').val(),
      whatsapp_opt_in: $('#profWhatsappOptIn').is(':checked') ? 1 : 0
    };

    if (typeof Pgs !== 'undefined' && Pgs.api) {
      Pgs.api.post('prf_save', payload)
        .then(function(res) {
          PGS.notify('Profile preferences updated successfully.');
          loadProfile();
        })
        .catch(function(err) {
          PGS.notify('Error saving profile: ' + (err.message || 'Validation failed. Check phone format.'));
        })
        .finally(function() {
          btn.prop('disabled', false).html('<i class="fa fa-check mr-1"></i> Save Changes');
        });
    } else {
      // Fallback direct AJAX
      var csrf = $('meta[name="csrf-token"]').attr('content') || '';
      $.ajax({
        url: 'admin/ajax/rqst.php?op=prf_save',
        type: 'POST',
        data: payload,
        headers: { 'X-CSRF-Token': csrf },
        dataType: 'json',
        success: function(r) {
          if (r && r.output && r.output.valid) {
            PGS.notify('Profile preferences updated successfully.');
            loadProfile();
          } else {
            PGS.notify('Error saving profile: ' + (r?.output?.response || 'Validation failed'));
          }
        },
        error: function(xhr) {
          var msg = 'Error saving profile.';
          try {
            var j = JSON.parse(xhr.responseText);
            if (j.error) msg = j.error;
            else if (j.output && j.output.response) msg = j.output.response;
          } catch(e) {}
          PGS.notify(msg);
        },
        complete: function() {
          btn.prop('disabled', false).html('<i class="fa fa-check mr-1"></i> Save Changes');
        }
      });
    }
  });

  // Open PIN Modal
  $('#btnOpenPinModal').on('click', function() {
    $('#pinCurrentPassword').val('');
    $('#pinNew').val('');
    $('#pinConfirm').val('');
    $('#modalPin').modal('show');
  });

  // Submit PIN Form
  $('#formPin').on('submit', function(e) {
    e.preventDefault();
    var curPwd = $('#pinCurrentPassword').val();
    var p1 = $('#pinNew').val().trim();
    var p2 = $('#pinConfirm').val().trim();

    if (!curPwd) {
      PGS.notify('Please enter your current account login password.');
      return;
    }
    if (!/^\d{6}$/.test(p1)) {
      PGS.notify('PIN must consist of exactly 6 numerical digits.');
      return;
    }
    if (p1 !== p2) {
      PGS.notify('New PIN and confirmation PIN do not match.');
      return;
    }

    var btn = $('#btnSubmitPin');
    btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i> Saving PIN...');

    var payload = {
      current_password: curPwd,
      pin: p1,
      confirm_pin: p2
    };

    var csrf = $('meta[name="csrf-token"]').attr('content') || '';
    $.ajax({
      url: 'admin/ajax/rqst.php?op=prf_pin_set',
      type: 'POST',
      data: payload,
      headers: { 'X-CSRF-Token': csrf },
      dataType: 'json',
      success: function(r) {
        if (r && r.output && r.output.valid) {
          PGS.notify('Voice PIN updated successfully! You can now use it when calling PGS Centrum.');
          $('#modalPin').modal('hide');
          loadProfile();
        } else {
          var msg = (r && r.output && r.output.response) ? r.output.response : 'Could not set Voice PIN';
          PGS.notify(msg);
        }
      },
      error: function(xhr) {
        var msg = 'Failed to set Voice PIN.';
        try {
          var j = JSON.parse(xhr.responseText);
          if (j.error) msg = j.error;
          else if (j.output && j.output.response) msg = j.output.response;
        } catch(e) {}
        PGS.notify(msg);
      },
      complete: function() {
        btn.prop('disabled', false).html('Save PIN');
      }
    });
  });
});
