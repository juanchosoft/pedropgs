/**
 * Phone Call Sessions & Traces
 */
(function() {
  'use strict';

  var currentPage = 1;
  var perPage = 25;
  var totalRecords = 0;

  function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function formatDuration(sec) {
    var s = parseInt(sec, 10);
    if (isNaN(s) || s < 0) return '—';
    var mins = Math.floor(s / 60);
    var remaining = s % 60;
    return mins + 'm ' + (remaining < 10 ? '0' : '') + remaining + 's';
  }

  function loadCalls() {
    var status = document.getElementById('filterStatus').value;
    var callerType = document.getElementById('filterCallerType').value;
    var from = document.getElementById('filterFrom').value;
    var to = document.getElementById('filterTo').value;

    var tbody = document.getElementById('callsBody');
    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted"><i class="fa fa-spinner fa-spin mr-1"></i> Loading call sessions...</td></tr>';

    var params = {
      status: status,
      caller_type: callerType,
      from: from,
      to: to,
      page: currentPage,
      per_page: perPage
    };

    Pgs.ajax('aud_calls', params, {
      onSuccess: function(res) {
        var data = res.data || {};
        var rows = data.rows || [];
        totalRecords = data.total || 0;

        document.getElementById('callsTotalBadge').textContent = totalRecords + ' sessions';
        renderRows(rows);
        updatePagination();
      },
      onError: function(err) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-danger">' + escapeHtml(err.message || 'Error loading sessions') + '</td></tr>';
      }
    });
  }

  function renderRows(rows) {
    var tbody = document.getElementById('callsBody');
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No call sessions found matching your filters.</td></tr>';
      return;
    }

    var html = '';
    rows.forEach(function(r) {
      var statusClass = 'badge-secondary';
      if (r.status === 'ended') statusClass = 'badge-success';
      else if (r.status === 'active') statusClass = 'badge-primary';
      else if (r.status === 'expired') statusClass = 'badge-warning';

      var statusBadge = '<span class="badge ' + statusClass + '">' + escapeHtml(r.status) + '</span>';
      var verifBadge = r.verification === 'pin' ?
        '<span class="badge badge-success"><i class="fa fa-shield mr-1"></i> PIN Verified</span>' :
        (r.verification === 'phone' ? '<span class="badge badge-info">Phone matched</span>' : '<span class="badge badge-light border">None</span>');

      html += '<tr>' +
        '<td>#' + escapeHtml(r.id) + '</td>' +
        '<td>' + statusBadge + '</td>' +
        '<td><strong>' + escapeHtml(r.caller_phone_masked) + '</strong></td>' +
        '<td><code>' + escapeHtml(r.caller_type || 'unknown') + '</code></td>' +
        '<td>' + verifBadge + '</td>' +
        '<td>' + formatDuration(r.duration_secs) + '</td>' +
        '<td><small class="text-muted">' + escapeHtml(r.started_at || '—') + '</small></td>' +
        '<td><small class="text-muted">' + escapeHtml(r.ended_at || '—') + '</small></td>' +
        '<td class="text-right">' +
          '<button type="button" class="btn btn-sm btn-outline-danger btn-view-traces" data-id="' + r.id + '" title="View tool traces">' +
            '<i class="fa fa-list-ol mr-1"></i> Traces' +
          '</button>' +
        '</td>' +
      '</tr>';
    });
    tbody.innerHTML = html;
  }

  function updatePagination() {
    var totalPages = Math.max(1, Math.ceil(totalRecords / perPage));
    document.getElementById('pageIndicator').textContent = 'Page ' + currentPage + ' of ' + totalPages;
    document.getElementById('btnPrevPage').disabled = currentPage <= 1;
    document.getElementById('btnNextPage').disabled = currentPage >= totalPages;
  }

  function loadTraces(sessionId) {
    var tbody = document.getElementById('tracesBody');
    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted"><i class="fa fa-spinner fa-spin mr-1"></i> Loading tool execution traces...</td></tr>';
    document.getElementById('tracesModalTitle').textContent = 'Traces for Call Session #' + sessionId;
    $('#tracesModal').modal('show');

    Pgs.ajax('aud_call_traces', { session_id: sessionId }, {
      onSuccess: function(res) {
        var traces = (res.data && res.data.traces) ? res.data.traces : [];
        if (!traces.length) {
          tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-muted">No tool executions were logged during this call session.</td></tr>';
          return;
        }

        var html = '';
        traces.forEach(function(t) {
          var latency = t.latency_ms ? t.latency_ms + ' ms' : '—';
          var statusBadge = (t.result_code === '200' || t.result_code === 'ok' || t.result_code === 'success') ?
            '<span class="badge badge-success">' + escapeHtml(t.result_code) + '</span>' :
            '<span class="badge badge-info">' + escapeHtml(t.result_code || '—') + '</span>';

          var argsFormatted = t.input_redacted || '{}';

          html += '<tr>' +
            '<td>#' + escapeHtml(t.id) + '</td>' +
            '<td><small>' + escapeHtml(t.created_at) + '</small></td>' +
            '<td><code>' + escapeHtml(t.name) + '</code> <small class="text-muted">(' + escapeHtml(t.kind) + ')</small></td>' +
            '<td>' + latency + '</td>' +
            '<td>' + statusBadge + '</td>' +
            '<td style="max-width:260px; font-size:11px;" class="text-truncate" title="' + escapeHtml(argsFormatted) + '"><code>' + escapeHtml(argsFormatted) + '</code></td>' +
            '<td><span class="badge badge-light border">' + escapeHtml(t.result_code) + '</span></td>' +
          '</tr>';
        });
        tbody.innerHTML = html;
      },
      onError: function(err) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-3 text-danger">' + escapeHtml(err.message || 'Error loading traces') + '</td></tr>';
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function() {
    loadCalls();

    document.getElementById('btnRefreshCalls').addEventListener('click', function() {
      loadCalls();
      Pgs.toast('Call sessions refreshed', 'info');
    });

    document.getElementById('filterForm').addEventListener('submit', function(e) {
      e.preventDefault();
      currentPage = 1;
      loadCalls();
    });

    document.getElementById('btnClearFilters').addEventListener('click', function() {
      document.getElementById('filterStatus').value = '';
      document.getElementById('filterCallerType').value = '';
      document.getElementById('filterFrom').value = '';
      document.getElementById('filterTo').value = '';
      currentPage = 1;
      loadCalls();
    });

    document.getElementById('btnPrevPage').addEventListener('click', function() {
      if (currentPage > 1) {
        currentPage--;
        loadCalls();
      }
    });

    document.getElementById('btnNextPage').addEventListener('click', function() {
      var totalPages = Math.ceil(totalRecords / perPage);
      if (currentPage < totalPages) {
        currentPage++;
        loadCalls();
      }
    });

    // View traces
    document.addEventListener('click', function(e) {
      var btn = e.target.closest('.btn-view-traces');
      if (!btn) return;
      var sid = btn.getAttribute('data-id');
      loadTraces(sid);
    });
  });
})();
