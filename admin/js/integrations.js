/**
 * Integrations & Sync Outbox Management
 */
(function() {
  'use strict';

  var currentTable = 'notification';

  function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function loadStatus() {
    Pgs.ajax('itg_status', {}, {
      onSuccess: function(res) {
        var data = res.data || {};
        var wa = data.whatsapp || {};
        var google = data.google || {};

        // WhatsApp
        var waBadge = document.getElementById('waBadge');
        if (wa.enabled) {
          waBadge.className = 'badge badge-success';
          waBadge.textContent = 'Active / Configured';
        } else {
          waBadge.className = 'badge badge-secondary';
          waBadge.textContent = 'Disabled';
        }
        document.getElementById('waPhoneId').textContent = wa.phone_number_id_masked || 'Not configured';
        document.getElementById('waPendingCount').textContent = wa.pending || 0;
        document.getElementById('waFailedCount').textContent = wa.failed || 0;
        document.getElementById('waDeadCount').textContent = wa.dead || 0;

        // Google
        var gBadge = document.getElementById('googleBadge');
        if (google.enabled) {
          gBadge.className = 'badge badge-success';
          gBadge.textContent = 'Active / Configured';
        } else {
          gBadge.className = 'badge badge-secondary';
          gBadge.textContent = 'Disabled';
        }
        document.getElementById('googleSa').textContent = google.service_account_masked || 'Not configured';
        document.getElementById('gSheetsCount').textContent = google.sheets_synced_records || 0;
        document.getElementById('gCalCount').textContent = google.calendar_synced_records || 0;
      },
      onError: function(err) {
        Pgs.toast(err.message || 'Error loading integration status', 'error');
      }
    });
  }

  function loadOutbox() {
    var statusFilter = document.getElementById('outboxStatusFilter').value;
    var tbodyId = currentTable === 'notification' ? 'notifOutboxBody' : 'syncOutboxBody';
    var tbody = document.getElementById(tbodyId);
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted"><i class="fa fa-spinner fa-spin mr-1"></i> Loading...</td></tr>';

    Pgs.ajax('itg_outbox_list', { table: currentTable, status: statusFilter }, {
      onSuccess: function(res) {
        var items = (res.data && res.data.items) ? res.data.items : [];
        if (!items.length) {
          tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted"><i class="fa fa-check-circle text-success mr-1"></i> No failed or dead outbox records found.</td></tr>';
          return;
        }

        var html = '';
        if (currentTable === 'notification') {
          items.forEach(function(item) {
            var badgeClass = item.status === 'dead' ? 'badge-danger' : 'badge-warning';
            var err = item.last_error ? escapeHtml(item.last_error) : '—';
            var recipient = item.recipient_address ? item.recipient_address : (item.recipient_user_id ? ('User #' + item.recipient_user_id) : '—');
            var tpl = item.template_key ? item.template_key : (item.event_type || '—');
            html += '<tr>' +
              '<td>#' + escapeHtml(item.id) + '</td>' +
              '<td><span class="badge badge-light border">' + escapeHtml(item.channel) + '</span></td>' +
              '<td>' + escapeHtml(recipient) + '</td>' +
              '<td><code>' + escapeHtml(tpl) + '</code></td>' +
              '<td><span class="badge ' + badgeClass + '">' + escapeHtml(item.status) + '</span></td>' +
              '<td>' + escapeHtml(item.attempts) + '</td>' +
              '<td style="max-width:240px; font-size:12px;" class="text-truncate text-danger" title="' + err + '">' + err + '</td>' +
              '<td><small class="text-muted">' + escapeHtml(item.next_attempt_at || '—') + '</small></td>' +
              '<td class="text-right">' +
                '<button type="button" class="btn btn-sm btn-outline-primary btn-retry-item" data-id="' + item.id + '" title="Retry now">' +
                  '<i class="fa fa-refresh mr-1"></i> Retry' +
                '</button>' +
              '</td>' +
            '</tr>';
          });
        } else {
          items.forEach(function(item) {
            var badgeClass = item.status === 'dead' ? 'badge-danger' : 'badge-warning';
            var err = item.last_error ? escapeHtml(item.last_error) : '—';
            html += '<tr>' +
              '<td>#' + escapeHtml(item.id) + '</td>' +
              '<td><span class="badge badge-light border">' + escapeHtml(item.target || item.provider) + '</span></td>' +
              '<td>' + escapeHtml(item.entity_type) + '</td>' +
              '<td>#' + escapeHtml(item.entity_id) + '</td>' +
              '<td><code>' + escapeHtml(item.action) + '</code></td>' +
              '<td><span class="badge ' + badgeClass + '">' + escapeHtml(item.status) + '</span></td>' +
              '<td>' + escapeHtml(item.attempts) + '</td>' +
              '<td style="max-width:240px; font-size:12px;" class="text-truncate text-danger" title="' + err + '">' + err + '</td>' +
              '<td class="text-right">' +
                '<button type="button" class="btn btn-sm btn-outline-primary btn-retry-item" data-id="' + item.id + '" title="Retry now">' +
                  '<i class="fa fa-refresh mr-1"></i> Retry' +
                '</button>' +
              '</td>' +
            '</tr>';
          });
        }

        tbody.innerHTML = html;
      },
      onError: function(err) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-danger">' + escapeHtml(err.message || 'Error loading records') + '</td></tr>';
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function() {
    loadStatus();
    loadOutbox();

    document.getElementById('btnRefreshStatus').addEventListener('click', function() {
      loadStatus();
      loadOutbox();
      Pgs.toast('Status refreshed', 'info');
    });

    // Tab switching
    $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
      if (e.target.id === 'notif-outbox-tab') {
        currentTable = 'notification';
      } else {
        currentTable = 'sync';
      }
      loadOutbox();
    });

    document.getElementById('outboxStatusFilter').addEventListener('change', function() {
      loadOutbox();
    });

    // Retry single
    document.addEventListener('click', function(e) {
      var btn = e.target.closest('.btn-retry-item');
      if (!btn) return;
      var id = btn.getAttribute('data-id');
      btn.disabled = true;
      btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

      Pgs.ajax('itg_retry', { table: currentTable, id: id }, {
        onSuccess: function() {
          Pgs.toast('Outbox record #' + id + ' queued for retry', 'success');
          loadStatus();
          loadOutbox();
        },
        onError: function(err) {
          btn.disabled = false;
          btn.innerHTML = '<i class="fa fa-refresh mr-1"></i> Retry';
          Pgs.toast(err.message || 'Failed to retry record', 'error');
        }
      });
    });

    // Retry all dead
    document.getElementById('btnRetryAllDead').addEventListener('click', function() {
      if (!confirm('Are you sure you want to requeue ALL dead messages in this outbox?')) return;
      var btn = this;
      btn.disabled = true;
      btn.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Retrying...';

      Pgs.ajax('itg_retry_dead', { table: currentTable }, {
        onSuccess: function(res) {
          var count = (res.data && res.data.retried_count) ? res.data.retried_count : 0;
          Pgs.toast('Requeued ' + count + ' dead records', 'success');
          btn.disabled = false;
          btn.innerHTML = '<i class="fa fa-refresh mr-1"></i> Retry All Dead';
          loadStatus();
          loadOutbox();
        },
        onError: function(err) {
          btn.disabled = false;
          btn.innerHTML = '<i class="fa fa-refresh mr-1"></i> Retry All Dead';
          Pgs.toast(err.message || 'Failed to retry dead records', 'error');
        }
      });
    });

    // Run sync all
    var btnSync = document.getElementById('btnSyncAll');
    if (btnSync) {
      btnSync.addEventListener('click', function() {
        if (!confirm('This will enqueue active tasks and expenses to Google Sheets and Calendar. Proceed?')) return;
        btnSync.disabled = true;
        btnSync.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Syncing...';

        Pgs.ajax('itg_sync_all', { include_migrated: 0 }, {
          onSuccess: function(res) {
            var count = (res.data && res.data.enqueued) ? res.data.enqueued : 0;
            Pgs.toast('Enqueued ' + count + ' records for synchronization', 'success');
            btnSync.disabled = false;
            btnSync.innerHTML = '<i class="fa fa-refresh mr-1"></i> Run Sync Now';
            loadStatus();
            loadOutbox();
          },
          onError: function(err) {
            btnSync.disabled = false;
            btnSync.innerHTML = '<i class="fa fa-refresh mr-1"></i> Run Sync Now';
            Pgs.toast(err.message || 'Failed to trigger sync', 'error');
          }
        });
      });
    }

    // Send test WhatsApp
    var btnWa = document.getElementById('btnTestWa');
    if (btnWa) {
      btnWa.addEventListener('click', function() {
        btnWa.disabled = true;
        btnWa.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Sending...';

        Pgs.ajax('itg_wa_test', {}, {
          onSuccess: function(res) {
            var masked = (res.data && res.data.destination_masked) ? res.data.destination_masked : 'your phone';
            Pgs.toast('Test notification queued for ' + masked, 'success');
            btnWa.disabled = false;
            btnWa.innerHTML = '<i class="fa fa-paper-plane mr-1"></i> Send Test Message';
            loadStatus();
            loadOutbox();
          },
          onError: function(err) {
            btnWa.disabled = false;
            btnWa.innerHTML = '<i class="fa fa-paper-plane mr-1"></i> Send Test Message';
            Pgs.toast(err.message || 'Failed to send test message', 'error');
          }
        });
      });
    }
  });
})();
