$(function () {
  VST.init();
});

var VST = {
  properties: [],
  users: [],
  responders: [],
  esc: PGSA.esc,
  call: PGSA.call,
  fail: PGSA.fail,
  dayNames: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
  reasons: { no_digits: 'No digits', bad_length: 'Wrong length', repeated_digits: 'Repeated digits', sequence: 'Sequence', invalid_nanp: 'Invalid area or exchange' },
  searchTimer: null,

  init: function () {
    VST.loadOverview();
    if (window.PGS_VST.responders) {
      $.when(VST.call('vst_properties'), VST.call('vst_users')).done(function (p, u) {
        VST.properties = p[0].output.response;
        VST.users = u[0].output.response;
        VST.fillProperties();
        VST.fillUsers();
        VST.loadResponders();
      });
      VST.loadPhones();
      VST.loadDiscards();
      $('#btnNewResponder').on('click', function () { VST.editResponder(null); });
      $('#btnSaveResponder').on('click', VST.saveResponder);
      $('#tablaResponders tbody').on('click', '[data-edit]', function () { VST.editResponder($(this).data('edit')); });
      $('#tablaResponders tbody').on('click', '[data-del]', function () { VST.deleteResponder($(this).data('del')); });
      $('#btnAddPhone').on('click', VST.addPhone);
      $('#phSearch').on('input', function () { clearTimeout(VST.searchTimer); VST.searchTimer = setTimeout(VST.loadPhones, 300); });
      $('#phKind').on('change', VST.loadPhones);
      $('#tablaPhones tbody').on('click', '[data-toggle-phone]', function () { VST.togglePhone($(this).data('togglePhone'), $(this).data('active')); });
      $('#tablaPhones tbody').on('click', '[data-del-phone]', function () { VST.deletePhone($(this).data('delPhone')); });
      $('#tablaDiscards tbody').on('click', '[data-fix]', function () { VST.fixDiscard($(this).data('fix')); });
      $('#tablaDiscards tbody').on('click', '[data-dismiss-discard]', function () { VST.dismissDiscard($(this).data('dismissDiscard')); });
    }
    if (window.PGS_VST.settings) {
      VST.call('vst_users').done(function (u) {
        VST.users = u.output.response;
        VST.loadSettings();
      });
      $('#formSettings').on('submit', function (e) { e.preventDefault(); VST.saveSettings(); });
    }
  },

  loadOverview: function () {
    VST.call('vst_overview').done(function (res) {
      var o = res.output.response;
      var chip = function (ok, icon, text) { return '<span class="chip ' + (ok ? 'ok' : 'bad') + '"><i class="fa ' + icon + '"></i> ' + text + '</span>'; };
      var cron = o.cron_age_s === null ? 'Scheduler has never run' : (o.cron_age_s > 300 ? 'Scheduler stopped (' + Math.floor(o.cron_age_s / 60) + ' min ago)' : 'Scheduler running');
      var html = '';
      html += chip(o.voice_enabled, 'fa-phone', o.voice_enabled ? 'Assistant on' : 'Assistant off');
      html += chip(o.cron_age_s !== null && o.cron_age_s <= 300, 'fa-clock-o', cron);
      html += chip(o.alert_targets > 0, 'fa-users', o.alert_targets + ' responder(s) alerted');
      html += chip(o.transfer_targets > 0, 'fa-exchange', o.transfer_targets + ' for transfers');
      html += chip(o.email_enabled, 'fa-envelope', o.email_enabled ? 'Email on' : 'Email off');
      html += chip(true, 'fa-whatsapp', o.whatsapp_enabled ? 'WhatsApp on' : 'WhatsApp off');
      html += chip(o.outbox_failed === 0, 'fa-bell', o.outbox_failed + ' failed notification(s)');
      html += chip(o.discarded_phones === 0, 'fa-question-circle', o.discarded_phones + ' unusable number(s)');
      $('#statusChips').html(html);
    });
  },

  fillProperties: function () {
    var sel = $('#phProperty').empty().append('<option value="">Select property...</option>');
    VST.properties.forEach(function (p) { sel.append($('<option>').val(p.id).text(p.name)); });
  },

  fillUsers: function () {
    var sel = $('#rUser').empty().append('<option value="">None</option>');
    VST.users.forEach(function (u) { sel.append($('<option>').val(u.id).text(u.name)); });
  },

  /* ---------- Responders ---------- */

  loadResponders: function () {
    VST.call('vst_responders').done(function (res) {
      VST.responders = res.output.response;
      var tbody = $('#tablaResponders tbody').empty();
      if (!VST.responders.length) {
        tbody.append('<tr><td colspan="7" class="text-center muted">No responders yet. Add the owner first.</td></tr>');
      }
      VST.responders.forEach(function (r) {
        var roles = '';
        if (parseInt(r.is_owner, 10) === 1) { roles += '<span class="pill owner">Owner</span>'; }
        if (parseInt(r.receives_transfers, 10) === 1) { roles += '<span class="pill">Transfers</span>'; }
        if (parseInt(r.receives_alerts, 10) === 1) { roles += '<span class="pill">Alerts</span>'; }
        if (parseInt(r.is_active, 10) !== 1) { roles += '<span class="pill off">Inactive</span>'; }
        tbody.append(
          '<tr>' +
            '<td>' + r.priority_order + '</td>' +
            '<td><b>' + VST.esc(r.name) + '</b>' + VST.unitsText(r) + '</td>' +
            '<td>' + VST.esc(r.phone_e164 || '-') + '</td>' +
            '<td>' + VST.esc(r.email || '-') + '</td>' +
            '<td>' + roles + '</td>' +
            '<td>' + VST.scheduleText(r) + '</td>' +
            '<td style="white-space:nowrap;">' +
              '<button type="button" class="btn btn-sm btn-outline-primary mr-1" data-edit="' + r.id + '" title="Edit"><i class="fa fa-pencil"></i></button>' +
              '<button type="button" class="btn btn-sm btn-outline-danger" data-del="' + r.id + '" title="Delete"><i class="fa fa-trash"></i></button>' +
            '</td>' +
          '</tr>'
        );
      });
    }).fail(VST.fail);
  },

  unitsText: function (r) {
    var ids = (r.unidad_ids || []).map(function (x) { return parseInt(x, 10); });
    if (!ids.length) { return '<br><small class="muted">All communities</small>'; }
    var names = [];
    (VST.properties || []).forEach(function (p) { if (ids.indexOf(parseInt(p.id, 10)) !== -1) { names.push(p.name); } });
    return '<br><small class="muted">' + VST.esc(names.join(', ') || ids.length + ' community(ies)') + '</small>';
  },

  fillUnits: function (selected) {
    var box = $('#rUnits').empty();
    var ids = (selected || []).map(function (x) { return parseInt(x, 10); });
    (VST.properties || []).forEach(function (p) {
      var id = parseInt(p.id, 10);
      box.append(
        '<div class="col-md-6"><div class="custom-control custom-checkbox">' +
          '<input type="checkbox" class="custom-control-input r-unit" id="rUnit' + id + '" value="' + id + '"' + (ids.indexOf(id) !== -1 ? ' checked' : '') + '>' +
          '<label class="custom-control-label" for="rUnit' + id + '">' + VST.esc(p.name) + '</label>' +
        '</div></div>'
      );
    });
  },

  scheduleText: function (r) {
    var mask = parseInt(r.days_mask, 10);
    var days = [];
    for (var i = 0; i < 7; i++) { if (mask & (1 << i)) { days.push(VST.dayNames[i]); } }
    var dayText = days.length === 7 ? 'Every day' : (days.length ? days.join(', ') : 'No days');
    var time = r.time_from && r.time_to ? r.time_from.substr(0, 5) + '-' + r.time_to.substr(0, 5) : 'All day';
    return VST.esc(dayText) + '<br><small class="muted">' + VST.esc(time) + '</small>';
  },

  editResponder: function (id) {
    var r = null;
    VST.responders.forEach(function (x) { if (parseInt(x.id, 10) === parseInt(id, 10)) { r = x; } });
    r = r || { id: '', name: '', user_id: '', phone_e164: '', email: '', priority_order: 100, receives_transfers: 0, receives_alerts: 1, is_owner: 0, is_active: 1, days_mask: 127, time_from: null, time_to: null };
    $('#modalResponderTitle').text(r.id ? 'Edit responder' : 'New responder');
    $('#rId').val(r.id);
    $('#rName').val(r.name);
    $('#rUser').val(r.user_id ? String(r.user_id) : '');
    $('#rPhone').val(r.phone_e164 || '');
    $('#rEmail').val(r.email || '');
    $('#rOrder').val(r.priority_order);
    $('#rTransfers').prop('checked', parseInt(r.receives_transfers, 10) === 1);
    $('#rAlerts').prop('checked', parseInt(r.receives_alerts, 10) === 1);
    $('#rOwner').prop('checked', parseInt(r.is_owner, 10) === 1);
    $('#rActive').prop('checked', parseInt(r.is_active, 10) === 1);
    $('.r-day').each(function () { $(this).prop('checked', (parseInt(r.days_mask, 10) & parseInt($(this).val(), 10)) !== 0); });
    VST.fillUnits(r.unidad_ids);
    $('#rFrom').val(r.time_from ? r.time_from.substr(0, 5) : '');
    $('#rTo').val(r.time_to ? r.time_to.substr(0, 5) : '');
    $('#modalResponder').modal('show');
  },

  saveResponder: function () {
    var mask = 0;
    $('.r-day:checked').each(function () { mask |= parseInt($(this).val(), 10); });
    var units = [];
    $('.r-unit:checked').each(function () { units.push($(this).val()); });
    var data = {
      id: $('#rId').val(),
      unidad_ids: units.join(','),
      name: $.trim($('#rName').val()),
      user_id: $('#rUser').val(),
      phone: $.trim($('#rPhone').val()),
      email: $.trim($('#rEmail').val()),
      priority_order: $('#rOrder').val(),
      receives_transfers: $('#rTransfers').is(':checked') ? 1 : 0,
      receives_alerts: $('#rAlerts').is(':checked') ? 1 : 0,
      is_owner: $('#rOwner').is(':checked') ? 1 : 0,
      is_active: $('#rActive').is(':checked') ? 1 : 0,
      days_mask: mask,
      time_from: $('#rFrom').val(),
      time_to: $('#rTo').val()
    };
    VST.call('vst_responder_save', data).done(function () {
      $('#modalResponder').modal('hide');
      VST.loadResponders();
      VST.loadOverview();
      PGSA.toast('Responder saved');
    }).fail(VST.fail);
  },

  deleteResponder: function (id) {
    swal({ title: 'Delete responder?', text: 'They will stop receiving calls and alerts.', type: 'warning', showCancelButton: true, confirmButtonText: 'Delete' }, function (ok) {
      if (!ok) { return; }
      VST.call('vst_responder_delete', { id: id }).done(function () {
        VST.loadResponders();
        VST.loadOverview();
      }).fail(VST.fail);
    });
  },

  /* ---------- Property phones ---------- */

  loadPhones: function () {
    VST.call('vst_phones', { q: $.trim($('#phSearch').val()), kind: $('#phKind').val() }).done(function (res) {
      var tbody = $('#tablaPhones tbody').empty();
      var rows = res.output.response;
      if (!rows.length) {
        tbody.append('<tr><td colspan="6" class="text-center muted">No phones found.</td></tr>');
      }
      rows.forEach(function (p) {
        var owner = p.kind === 'staff' ? 'Staff: ' + VST.esc(p.employee_name || '') : VST.esc(p.unit_name || '');
        var shared = parseInt(p.shared_with, 10) > 1 ? ' <span class="pill">shared by ' + p.shared_with + '</span>' : '';
        var active = parseInt(p.is_active, 10) === 1;
        tbody.append(
          '<tr' + (active ? '' : ' class="muted"') + '>' +
            '<td><b>' + VST.esc(p.phone_e164) + '</b>' + shared + '</td>' +
            '<td>' + owner + '</td>' +
            '<td>' + VST.esc(p.label || '') + '</td>' +
            '<td>' + (p.source === 'manual' ? 'Added here' : 'Imported') + '</td>' +
            '<td>' + (active ? 'Yes' : 'No') + '</td>' +
            '<td style="white-space:nowrap;">' +
              '<button type="button" class="btn btn-sm btn-outline-dark mr-1" data-toggle-phone="' + p.id + '" data-active="' + (active ? 0 : 1) + '">' + (active ? 'Disable' : 'Enable') + '</button>' +
              '<button type="button" class="btn btn-sm btn-outline-danger" data-del-phone="' + p.id + '" title="Remove"><i class="fa fa-trash"></i></button>' +
            '</td>' +
          '</tr>'
        );
      });
    }).fail(VST.fail);
  },

  addPhone: function () {
    var data = { unidad_id: $('#phProperty').val(), phone: $.trim($('#phNumber').val()), label: $.trim($('#phLabel').val()) };
    if (!data.unidad_id) { swal('Property', 'Select a property first.', 'warning'); return; }
    VST.call('vst_phone_save', data).done(function () {
      $('#phNumber, #phLabel').val('');
      VST.loadPhones();
      PGSA.toast('Phone added');
    }).fail(VST.fail);
  },

  togglePhone: function (id, active) {
    VST.call('vst_phone_toggle', { id: id, active: active }).done(VST.loadPhones).fail(VST.fail);
  },

  deletePhone: function (id) {
    swal({ title: 'Remove this phone?', text: 'Imported numbers are only disabled; numbers added here are deleted.', type: 'warning', showCancelButton: true, confirmButtonText: 'Remove' }, function (ok) {
      if (!ok) { return; }
      VST.call('vst_phone_delete', { id: id }).done(VST.loadPhones).fail(VST.fail);
    });
  },

  loadDiscards: function () {
    VST.call('vst_discards').done(function (res) {
      var rows = res.output.response;
      VST.discards = rows;
      $('#discardCount').text('(' + rows.length + ')');
      var tbody = $('#tablaDiscards tbody').empty();
      if (!rows.length) {
        tbody.append('<tr><td colspan="5" class="text-center muted">Nothing to fix.</td></tr>');
      }
      rows.forEach(function (d) {
        var isUnit = d.source_table === 'tbl_unidades';
        tbody.append(
          '<tr>' +
            '<td>' + (isUnit ? 'Property' : 'Staff') + '</td>' +
            '<td>' + VST.esc((isUnit ? d.unit_name : d.employee_name) || '') + '</td>' +
            '<td><code>' + VST.esc(d.raw_value) + '</code></td>' +
            '<td>' + VST.esc(VST.reasons[d.reason] || d.reason) + '</td>' +
            '<td style="white-space:nowrap;">' +
              (isUnit ? '<button type="button" class="btn btn-sm btn-outline-primary mr-1" data-fix="' + d.id + '">Fix</button>' : '') +
              '<button type="button" class="btn btn-sm btn-outline-secondary" data-dismiss-discard="' + d.id + '">Dismiss</button>' +
            '</td>' +
          '</tr>'
        );
      });
    }).fail(VST.fail);
  },

  fixDiscard: function (id) {
    var row = null;
    (VST.discards || []).forEach(function (d) { if (parseInt(d.id, 10) === parseInt(id, 10)) { row = d; } });
    if (!row) { return; }
    swal({ title: 'Correct phone number', text: 'Property: ' + (row.unit_name || '') + '. Found "' + row.raw_value + '".', type: 'input', showCancelButton: true, inputPlaceholder: '(407) 555-0123', confirmButtonText: 'Save' }, function (phone) {
      if (phone === false) { return; }
      VST.call('vst_discard_assign', { id: id, phone: $.trim(phone), unidad_id: row.source_id }).done(function () {
        VST.loadDiscards();
        VST.loadPhones();
        VST.loadOverview();
      }).fail(VST.fail);
    });
  },

  dismissDiscard: function (id) {
    VST.call('vst_discard_dismiss', { id: id }).done(function () { VST.loadDiscards(); VST.loadOverview(); }).fail(VST.fail);
  },

  /* ---------- Escalation settings ---------- */

  loadSettings: function () {
    VST.call('vst_settings').done(function (res) {
      var s = res.output.response;
      $('#sVoiceEnabled').prop('checked', !!s.voice_enabled);
      $('#sRemind').val(s['esc.remind_min']);
      $('#sEscalate').val(s['esc.escalate_min']);
      $('#sUrgentRemind').val(s['esc.urgent_remind_min']);
      $('#sUrgentEscalate').val(s['esc.urgent_escalate_min']);
      $('#sRepeat').val(s['esc.repeat_min']);
      $('#sMaxRepeats').val(s['esc.max_repeats']);
      $('#sExtraEmails').val(s['esc.extra_emails']);
      $('#sTimezone').text('(' + s.timezone + ')');
      var selected = {};
      s['esc.users'].forEach(function (u) { selected[u.id] = true; });
      var sel = $('#sUsers').empty();
      VST.users.forEach(function (u) { sel.append($('<option>').val(u.id).text(u.name).prop('selected', !!selected[u.id])); });
      $('.day-row').each(function () {
        var day = $(this).data('day');
        var h = s.hours[day];
        $(this).find('.day-open').prop('checked', h.open);
        $(this).find('.day-from').val(h.from);
        $(this).find('.day-to').val(h.to);
        VST.toggleDay($(this));
      });
      $('.day-open').off('change').on('change', function () { VST.toggleDay($(this).closest('.day-row')); });
    }).fail(VST.fail);
  },

  toggleDay: function (row) {
    var open = row.find('.day-open').is(':checked');
    row.find('.day-from, .day-to').prop('disabled', !open);
  },

  saveSettings: function () {
    var hours = {};
    $('.day-row').each(function () {
      hours[$(this).data('day')] = { open: $(this).find('.day-open').is(':checked'), from: $(this).find('.day-from').val(), to: $(this).find('.day-to').val() };
    });
    var payload = {
      voice_enabled: $('#sVoiceEnabled').is(':checked'),
      'esc.remind_min': $('#sRemind').val(),
      'esc.escalate_min': $('#sEscalate').val(),
      'esc.urgent_remind_min': $('#sUrgentRemind').val(),
      'esc.urgent_escalate_min': $('#sUrgentEscalate').val(),
      'esc.repeat_min': $('#sRepeat').val(),
      'esc.max_repeats': $('#sMaxRepeats').val(),
      'esc.user_ids': $('#sUsers').val() || [],
      'esc.extra_emails': $('#sExtraEmails').val(),
      hours: hours
    };
    var btn = $('#btnSaveSettings').prop('disabled', true);
    VST.call('vst_settings_save', { payload: JSON.stringify(payload) }).done(function () {
      btn.prop('disabled', false);
      VST.loadOverview();
      PGSA.toast('Settings saved');
    }).fail(function (xhr) {
      btn.prop('disabled', false);
      VST.fail(xhr);
    });
  }
};
