$(function () {
  REQ.init();
});

var REQ = {
  page: 1,
  perPage: 25,
  total: 0,
  currentId: null,
  searchTimer: null,
  loaded: { users: false, properties: false },

  labels: {
    status: { received: 'Received', assigned: 'Assigned', in_progress: 'In progress', completed: 'Completed', cancelled: 'Cancelled' },
    priority: { urgent: 'Urgent', high: 'High', normal: 'Normal', low: 'Low' },
    event: { created: 'Received', status: 'Status', assigned: 'Assigned', ack: 'Acknowledged', alert: 'Reminder sent', escalated: 'Escalated', exhausted: 'Alerts stopped', merged: 'Updated', edited: 'Edited', note: 'Note' }
  },

  esc: PGSA.esc,
  call: PGSA.call,
  fail: PGSA.fail,

  filters: function () {
    return {
      status: $('#fStatus').val(),
      priority: $('#fPriority').val(),
      q: $.trim($('#fSearch').val()),
      from: $('#fFrom').val(),
      to: $('#fTo').val(),
      review: $('#fReview').is(':checked') ? 1 : 0,
      page: REQ.page,
      per_page: REQ.perPage
    };
  },

  init: function () {
    $('#btnRefresh').on('click', function () { REQ.load(); });
    $('#fStatus, #fPriority, #fFrom, #fTo, #fReview').on('change', function () { REQ.page = 1; REQ.syncKpi(); REQ.load(); });
    $('#fSearch').on('input', function () {
      clearTimeout(REQ.searchTimer);
      REQ.searchTimer = setTimeout(function () { REQ.page = 1; REQ.load(); }, 350);
    });
    $('#btnPrev').on('click', function () { if (REQ.page > 1) { REQ.page--; REQ.load(); } });
    $('#btnNext').on('click', function () { if (REQ.page * REQ.perPage < REQ.total) { REQ.page++; REQ.load(); } });
    $('.kpi').on('click', function () {
      var status = $(this).data('status');
      if ($(this).find('#kpiReview').length) {
        $('#fReview').prop('checked', true);
        $('#fStatus').val('open');
      } else {
        $('#fReview').prop('checked', false);
        $('#fStatus').val(status);
      }
      REQ.page = 1;
      REQ.syncKpi();
      REQ.load();
    });
    $('#tablaRequests tbody').on('click', 'tr[data-id]', function () { REQ.open($(this).data('id')); });

    if (window.PGS_REQ_CAN_MANAGE) {
      $('#btnAssign').on('click', REQ.assign);
      $('#btnNote').on('click', REQ.addNote);
      $('#btnSetProperty').on('click', REQ.setProperty);
      $('#btnReviewed').on('click', REQ.markReviewed);
      $('#statusButtons').on('click', 'button[data-status]', function () { REQ.changeStatus($(this).data('status')); });
    }

    var params = new URLSearchParams(window.location.search);
    if (params.get('code')) {
      $('#fSearch').val(params.get('code'));
      $('#fStatus').val('all');
    }
    REQ.load();
    setInterval(function () {
      if (!$('#modalRequest').hasClass('show')) {
        REQ.load(true);
      }
    }, 30000);
  },

  syncKpi: function () {
    var status = $('#fStatus').val();
    $('.kpi').removeClass('active');
    $('.kpi').filter(function () { return $(this).data('status') === status && !$(this).find('#kpiReview').length; }).addClass('active');
    if ($('#fReview').is(':checked')) {
      $('#kpiReview').closest('.kpi').addClass('active');
    }
  },

  load: function (silent) {
    if (!silent) {
      UTIL.cursorBusy();
    }
    REQ.call('req_list', REQ.filters()).done(function (res) {
      UTIL.cursorNormal();
      var data = res.output.response;
      REQ.total = data.total;
      REQ.renderRows(data.rows);
      REQ.renderCounts(data.counts, data.system);
    }).fail(function (xhr) {
      UTIL.cursorNormal();
      if (!silent) {
        REQ.fail(xhr);
      }
    });
  },

  renderCounts: function (counts, system) {
    $('#kpiOpen').text(counts.open);
    $('#kpiReceived').text(counts.received);
    $('#kpiAssigned').text(counts.assigned);
    $('#kpiProgress').text(counts.in_progress);
    $('#kpiCompleted').text(counts.completed);
    REQ.syncKpi();

    var banner = '';
    if (system.cron_age_s === null || system.cron_age_s > 300) {
      banner += '<div class="banner warn"><i class="fa fa-exclamation-triangle"></i> The scheduler has not run in the last 5 minutes. Reminders and escalations are paused.</div>';
    }
    if (!system.responders) {
      banner += '<div class="banner warn"><i class="fa fa-exclamation-triangle"></i> No responders are configured to receive alerts. Add them in Voice Settings.</div>';
    }
    $('#systemBanner').html(banner);
  },

  renderRows: function (rows) {
    var tbody = $('#tablaRequests tbody').empty();
    if (!rows.length) {
      tbody.append('<tr><td colspan="9" class="text-center muted">No requests found.</td></tr>');
    }
    rows.forEach(function (r) {
      var property = r.unit_name ? REQ.esc(r.unit_name) : (r.property_text ? REQ.esc(r.property_text) + ' <span class="muted">(unconfirmed)</span>' : '<span class="muted">Not identified</span>');
      var review = parseInt(r.needs_review, 10) === 1 ? '<span class="review-tag">REVIEW</span>' : '';
      var caller = REQ.esc(r.caller_name || '') + (r.caller_e164 ? '<br><small class="muted">' + REQ.esc(r.caller_e164) + '</small>' : '');
      tbody.append(
        '<tr data-id="' + r.id + '" style="cursor:pointer;">' +
          '<td><b>' + REQ.esc(r.code) + '</b>' + review + '</td>' +
          '<td><span class="pill pri-' + REQ.esc(r.priority) + '">' + REQ.esc(REQ.labels.priority[r.priority]) + '</span></td>' +
          '<td>' + property + '</td>' +
          '<td class="summary-cell">' + REQ.esc(r.summary) + '</td>' +
          '<td>' + caller + '</td>' +
          '<td><span class="pill st-' + REQ.esc(r.status) + '">' + REQ.esc(REQ.labels.status[r.status]) + '</span></td>' +
          '<td>' + (r.assigned_name ? REQ.esc(r.assigned_name) : '<span class="muted">-</span>') + '</td>' +
          '<td>' + REQ.esc(r.received_local) + '<br><small class="muted">' + REQ.age(r.age_minutes) + '</small></td>' +
          '<td><i class="fa fa-chevron-right muted"></i></td>' +
        '</tr>'
      );
    });
    var from = REQ.total === 0 ? 0 : (REQ.page - 1) * REQ.perPage + 1;
    var to = Math.min(REQ.total, REQ.page * REQ.perPage);
    $('#pageInfo').text(from + '-' + to + ' of ' + REQ.total);
    $('#btnPrev').prop('disabled', REQ.page <= 1);
    $('#btnNext').prop('disabled', REQ.page * REQ.perPage >= REQ.total);

    REQ.call('req_summary').done(function (res) {
      $('#kpiReview').text(res.output.response.review);
    });
  },

  age: function (minutes) {
    minutes = parseInt(minutes, 10);
    if (minutes < 1) { return 'just now'; }
    if (minutes < 60) { return minutes + ' min ago'; }
    if (minutes < 1440) { return Math.floor(minutes / 60) + ' h ago'; }
    return Math.floor(minutes / 1440) + ' d ago';
  },

  open: function (id) {
    REQ.currentId = id;
    REQ.call('req_get', { id: id }).done(function (res) {
      REQ.renderDetail(res.output.response);
      $('#modalRequest').modal('show');
    }).fail(REQ.fail);
  },

  renderDetail: function (d) {
    var r = d.request;
    $('#modalRequestTitle').text(r.code);
    $('#dSummary').text(r.summary);
    $('#dDetailsWrap').toggle(!!r.details);
    $('#dDetails').text(r.details || '');
    $('#dProperty').html(r.unit_name ? REQ.esc(r.unit_name) : (r.property_text ? REQ.esc(r.property_text) + ' <span class="muted">(unconfirmed)</span>' : '<span class="muted">Not identified</span>'));
    $('#dPriority').html('<span class="pill pri-' + REQ.esc(r.priority) + '">' + REQ.esc(REQ.labels.priority[r.priority]) + '</span>');
    $('#dCaller').html(REQ.esc(r.caller_name || '-') + (r.caller_e164 ? '<br><small class="muted">' + REQ.esc(r.caller_e164) + '</small>' : ''));
    $('#dReceived').text(r.received_local);
    $('#dStatus').html('<span class="pill st-' + REQ.esc(r.status) + '">' + REQ.esc(REQ.labels.status[r.status]) + '</span>');
    $('#dAssigned').text(r.assigned_name || '-');

    var timeline = $('#dTimeline').empty();
    d.timeline.forEach(function (e) {
      var who = e.actor_name || e.actor_label || '';
      var change = e.from_status && e.to_status && e.to_status !== e.from_status ? ' → ' + REQ.esc(REQ.labels.status[e.to_status] || e.to_status) : '';
      timeline.append('<li><b>' + REQ.esc(REQ.labels.event[e.event] || e.event) + '</b>' + change +
        (e.note ? '<br>' + REQ.esc(e.note) : '') +
        '<br><small class="muted">' + REQ.esc(e.created_local) + (who ? ' · ' + REQ.esc(who) : '') + '</small></li>');
    });

    var deliveries = $('#dDeliveries').empty();
    $('#dDeliveriesWrap').toggle(d.deliveries.length > 0);
    d.deliveries.forEach(function (m) {
      deliveries.append('<li><b>' + REQ.esc(m.channel) + '</b> to ' + REQ.esc(m.recipient) + '<br><small class="muted">' + REQ.esc(m.status) +
        (m.last_error ? ' · ' + REQ.esc(m.last_error) : '') + '</small></li>');
    });

    if (!window.PGS_REQ_CAN_MANAGE) {
      return;
    }
    var buttons = $('#statusButtons').empty();
    if (!d.next.length) {
      buttons.append('<span class="muted">No further changes available.</span>');
    }
    d.next.forEach(function (s) {
      var danger = s === 'cancelled' ? 'btn-outline-danger' : 'btn-danger';
      buttons.append('<button type="button" class="btn btn-sm ' + danger + ' mr-1 mb-1" data-status="' + s + '" style="font-weight:800;">' + REQ.esc(REQ.labels.status[s]) + '</button>');
    });
    $('#boxProperty').toggle(!r.unidad_id);
    $('#btnReviewed').toggle(parseInt(r.needs_review, 10) === 1);
    $('#inNote').val('');
    REQ.loadPickers(r);
  },

  loadPickers: function (r) {
    var fillUsers = function (users) {
      var sel = $('#selUser').empty().append('<option value="">Unassigned</option>');
      users.forEach(function (u) { sel.append($('<option>').val(u.id).text(u.name)); });
      sel.val(r.assigned_user_id ? String(r.assigned_user_id) : '');
    };
    var fillProps = function (props) {
      var sel = $('#selProperty').empty().append('<option value="">Select property...</option>');
      props.forEach(function (p) { sel.append($('<option>').val(p.id).text(p.name)); });
    };
    if (REQ.users) { fillUsers(REQ.users); } else {
      REQ.call('req_users').done(function (res) { REQ.users = res.output.response; fillUsers(REQ.users); });
    }
    if (!r.unidad_id) {
      if (REQ.properties) { fillProps(REQ.properties); } else {
        REQ.call('req_properties').done(function (res) { REQ.properties = res.output.response; fillProps(REQ.properties); });
      }
    }
  },

  apply: function (promise, success) {
    promise.done(function (res) {
      REQ.renderDetail(res.output.response);
      REQ.load(true);
      if (success) { swal({ title: success, type: 'success', timer: 1200, showConfirmButton: false }); }
    }).fail(REQ.fail);
  },

  changeStatus: function (status) {
    REQ.apply(REQ.call('req_status', { id: REQ.currentId, status: status, note: $.trim($('#inNote').val()) }), 'Status updated');
  },

  assign: function () {
    REQ.apply(REQ.call('req_assign', { id: REQ.currentId, user_id: $('#selUser').val() }), 'Assignment saved');
  },

  addNote: function () {
    var note = $.trim($('#inNote').val());
    if (!note) { swal('Note', 'Write a note first.', 'warning'); return; }
    REQ.apply(REQ.call('req_note', { id: REQ.currentId, note: note }), 'Note added');
  },

  setProperty: function () {
    var unit = $('#selProperty').val();
    if (!unit) { swal('Property', 'Select a property first.', 'warning'); return; }
    REQ.apply(REQ.call('req_property', { id: REQ.currentId, unidad_id: unit }), 'Property saved');
  },

  markReviewed: function () {
    REQ.apply(REQ.call('req_review', { id: REQ.currentId }), 'Marked as reviewed');
  }
};
