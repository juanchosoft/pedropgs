/**
 * AI Tool Execution Logs & Token Consumption
 */
(function() {
  'use strict';

  var currentPage = 1;
  var perPage = 30;
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

  function formatNumber(n) {
    return Number(n || 0).toLocaleString();
  }

  function loadLogs() {
    var tool = document.getElementById('filterTool').value;
    var success = document.getElementById('filterSuccess').value;
    var from = document.getElementById('filterFrom').value;
    var to = document.getElementById('filterTo').value;

    var tbody = document.getElementById('iaLogsBody');
    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted"><i class="fa fa-spinner fa-spin mr-1"></i> Loading logs...</td></tr>';

    var params = {
      tool_name: tool,
      success: success,
      from: from,
      to: to,
      page: currentPage,
      per_page: perPage
    };

    Pgs.ajax('iax_logs', params, {
      onSuccess: function(res) {
        var data = res.data || {};
        var rows = data.rows || [];
        totalRecords = data.total || 0;

        // Render token stats (only once or on page 1)
        if (data.token_stats && data.token_stats.length) {
          renderTokenStats(data.token_stats);
        }

        document.getElementById('logsTotalBadge').textContent = totalRecords + ' records';
        renderRows(rows);
        updatePagination();
      },
      onError: function(err) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-danger">' + escapeHtml(err.message || 'Error loading logs') + '</td></tr>';
      }
    });
  }

  function renderTokenStats(stats) {
    var tbody = document.getElementById('tokenStatsBody');
    if (!tbody) return;
    var html = '';
    stats.forEach(function(s) {
      var total = (parseInt(s.total_in, 10) || 0) + (parseInt(s.total_out, 10) || 0);
      html += '<tr>' +
        '<td><strong>' + escapeHtml(s.log_date) + '</strong></td>' +
        '<td>' + formatNumber(s.total_messages) + '</td>' +
        '<td>' + formatNumber(s.total_in) + '</td>' +
        '<td>' + formatNumber(s.total_out) + '</td>' +
        '<td><span class="badge badge-primary px-2 py-1 font-weight-bold">' + formatNumber(total) + '</span></td>' +
      '</tr>';
    });
    tbody.innerHTML = html;
  }

  function renderRows(rows) {
    var tbody = document.getElementById('iaLogsBody');
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No tool logs matching your search.</td></tr>';
      return;
    }

    var html = '';
    rows.forEach(function(r) {
      var statusBadge = r.success == 1 ? '<span class="badge badge-success">Success</span>' : '<span class="badge badge-danger">Failed</span>';
      var duration = r.execution_time_ms ? r.execution_time_ms + ' ms' : '—';
      var userName = r.user_name ? escapeHtml(r.user_name) : (r.nickname ? escapeHtml(r.nickname) : 'User #' + r.user_id);

      html += '<tr>' +
        '<td>#' + escapeHtml(r.id) + '</td>' +
        '<td><small class="text-muted">' + escapeHtml(r.created_at) + '</small></td>' +
        '<td>' + userName + '</td>' +
        '<td><code>' + escapeHtml(r.tool_name) + '</code></td>' +
        '<td>' + duration + '</td>' +
        '<td>' + statusBadge + '</td>' +
        '<td>' +
          '<button type="button" class="btn btn-xs btn-outline-secondary btn-view-payload" data-type="Arguments" data-content="' + encodeURIComponent(r.tool_arguments || '{}') + '">' +
            '<i class="fa fa-code mr-1"></i> View args' +
          '</button>' +
        '</td>' +
        '<td>' +
          (r.success == 1 ?
            '<button type="button" class="btn btn-xs btn-outline-success btn-view-payload" data-type="Result" data-content="' + encodeURIComponent(r.tool_result || '{}') + '"><i class="fa fa-check mr-1"></i> View result</button>' :
            '<button type="button" class="btn btn-xs btn-outline-danger btn-view-payload" data-type="Error" data-content="' + encodeURIComponent(r.error_message || '{}') + '"><i class="fa fa-exclamation mr-1"></i> View error</button>') +
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

  document.addEventListener('DOMContentLoaded', function() {
    loadLogs();

    document.getElementById('btnRefreshLogs').addEventListener('click', function() {
      loadLogs();
      Pgs.toast('Logs refreshed', 'info');
    });

    document.getElementById('filterForm').addEventListener('submit', function(e) {
      e.preventDefault();
      currentPage = 1;
      loadLogs();
    });

    document.getElementById('btnClearFilters').addEventListener('click', function() {
      document.getElementById('filterTool').value = '';
      document.getElementById('filterSuccess').value = '';
      document.getElementById('filterFrom').value = '';
      document.getElementById('filterTo').value = '';
      currentPage = 1;
      loadLogs();
    });

    document.getElementById('btnPrevPage').addEventListener('click', function() {
      if (currentPage > 1) {
        currentPage--;
        loadLogs();
      }
    });

    document.getElementById('btnNextPage').addEventListener('click', function() {
      var totalPages = Math.ceil(totalRecords / perPage);
      if (currentPage < totalPages) {
        currentPage++;
        loadLogs();
      }
    });

    // View payload modal
    document.addEventListener('click', function(e) {
      var btn = e.target.closest('.btn-view-payload');
      if (!btn) return;
      var type = btn.getAttribute('data-type');
      var raw = decodeURIComponent(btn.getAttribute('data-content') || '');
      var formatted = raw;
      try {
        var parsed = JSON.parse(raw);
        formatted = JSON.stringify(parsed, null, 2);
      } catch (err) {}

      document.getElementById('payloadModalTitle').textContent = type + ' Payload';
      document.getElementById('payloadModalContent').textContent = formatted;
      $('#payloadModal').modal('show');
    });
  });
})();
