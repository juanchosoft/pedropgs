/**
 * System Audit Log
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

  function channelBadge(ch) {
    var c = (ch || '').toLowerCase();
    if (c === 'web') return '<span class="badge badge-light border"><i class="fa fa-globe mr-1"></i> Web</span>';
    if (c === 'api') return '<span class="badge badge-dark"><i class="fa fa-plug mr-1"></i> API</span>';
    if (c === 'voice') return '<span class="badge badge-success"><i class="fa fa-phone mr-1"></i> Voice</span>';
    if (c === 'assistant') return '<span class="badge badge-primary"><i class="fa fa-magic mr-1"></i> Assistant</span>';
    if (c === 'cron') return '<span class="badge badge-secondary"><i class="fa fa-clock-o mr-1"></i> Cron</span>';
    return '<span class="badge badge-light border">' + escapeHtml(ch) + '</span>';
  }

  function loadAudit() {
    var action = document.getElementById('filterAction').value;
    var entity = document.getElementById('filterEntity').value;
    var channel = document.getElementById('filterChannel').value;
    var from = document.getElementById('filterFrom').value;
    var to = document.getElementById('filterTo').value;

    var tbody = document.getElementById('auditBody');
    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted"><i class="fa fa-spinner fa-spin mr-1"></i> Loading audit entries...</td></tr>';

    var params = {
      action: action,
      entity_type: entity,
      channel: channel,
      from: from,
      to: to,
      page: currentPage,
      per_page: perPage
    };

    Pgs.ajax('aud_list', params, {
      onSuccess: function(res) {
        var data = res.data || {};
        var rows = data.rows || [];
        totalRecords = data.total || 0;

        document.getElementById('auditTotalBadge').textContent = totalRecords + ' events';
        renderRows(rows);
        updatePagination();
      },
      onError: function(err) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-danger">' + escapeHtml(err.message || 'Error loading audit logs') + '</td></tr>';
      }
    });
  }

  function renderRows(rows) {
    var tbody = document.getElementById('auditBody');
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No audit events found matching your search.</td></tr>';
      return;
    }

    var html = '';
    rows.forEach(function(r) {
      var actor = 'System';
      if (r.actor_name) {
        actor = escapeHtml(r.actor_name);
      } else if (r.actor_nickname) {
        actor = escapeHtml(r.actor_nickname);
      } else if (r.actor_user_id) {
        actor = 'User #' + r.actor_user_id;
      }

      var entity = escapeHtml(r.entity_type);
      if (r.entity_id) {
        entity += ' #' + escapeHtml(r.entity_id);
      }

      var payloadJson = r.changes || r.metadata || '{}';

      html += '<tr>' +
        '<td>#' + escapeHtml(r.id) + '</td>' +
        '<td><small class="text-muted">' + escapeHtml(r.created_at) + '</small></td>' +
        '<td><strong>' + actor + '</strong></td>' +
        '<td>' + channelBadge(r.channel) + '</td>' +
        '<td><code>' + escapeHtml(r.action) + '</code></td>' +
        '<td>' + entity + '</td>' +
        '<td><small class="text-muted">' + escapeHtml(r.ip_address || '—') + '</small></td>' +
        '<td class="text-right">' +
          '<button type="button" class="btn btn-xs btn-outline-secondary btn-view-payload" data-action="' + escapeHtml(r.action) + '" data-content="' + encodeURIComponent(payloadJson) + '">' +
            '<i class="fa fa-eye mr-1"></i> Changes' +
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

  document.addEventListener('DOMContentLoaded', function() {
    loadAudit();

    document.getElementById('btnRefreshAudit').addEventListener('click', function() {
      loadAudit();
      Pgs.toast('Audit log refreshed', 'info');
    });

    document.getElementById('filterForm').addEventListener('submit', function(e) {
      e.preventDefault();
      currentPage = 1;
      loadAudit();
    });

    document.getElementById('btnClearFilters').addEventListener('click', function() {
      document.getElementById('filterAction').value = '';
      document.getElementById('filterEntity').value = '';
      document.getElementById('filterChannel').value = '';
      document.getElementById('filterFrom').value = '';
      document.getElementById('filterTo').value = '';
      currentPage = 1;
      loadAudit();
    });

    document.getElementById('btnPrevPage').addEventListener('click', function() {
      if (currentPage > 1) {
        currentPage--;
        loadAudit();
      }
    });

    document.getElementById('btnNextPage').addEventListener('click', function() {
      var totalPages = Math.ceil(totalRecords / perPage);
      if (currentPage < totalPages) {
        currentPage++;
        loadAudit();
      }
    });

    // View payload
    document.addEventListener('click', function(e) {
      var btn = e.target.closest('.btn-view-payload');
      if (!btn) return;
      var action = btn.getAttribute('data-action');
      var raw = decodeURIComponent(btn.getAttribute('data-content') || '');
      var formatted = raw;
      try {
        var parsed = JSON.parse(raw);
        formatted = JSON.stringify(parsed, null, 2);
      } catch (err) {}

      document.getElementById('auditModalTitle').textContent = 'Audit Event: ' + action;
      document.getElementById('auditModalContent').textContent = formatted;
      $('#auditModal').modal('show');
    });
  });
})();
