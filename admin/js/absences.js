/**
 * Time Off & Absences Controller
 */
(function() {
  'use strict';

  function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.content) return meta.content;
    var inp = document.querySelector('input[name="csrf_token"]');
    return inp ? inp.value : '';
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // --- MY TIME OFF ---
  function loadMyBalance() {
    if (!window.CAN_REQUEST_LEAVES) return;

    fetch('admin/ajax/rqst.php?op=avl_leave_my_balance')
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (data && data.ok && data.data) {
          var b = data.data;
          var kpiAvail = document.getElementById('kpiAvailable');
          var kpiQuota = document.getElementById('kpiQuota');
          var kpiUsed = document.getElementById('kpiUsed');
          var kpiPending = document.getElementById('kpiPending');

          if (kpiAvail) kpiAvail.innerText = b.available;
          if (kpiQuota) kpiQuota.innerText = b.quota;
          if (kpiUsed) kpiUsed.innerText = b.used;
          if (kpiPending) kpiPending.innerText = b.pending;
        }
      })
      .catch(function(err) {
        console.error('Failed to load my balance', err);
      });
  }

  function loadMyLeaves() {
    if (!window.CAN_REQUEST_LEAVES) return;

    var rows = document.getElementById('myLeavesRows');
    if (!rows) return;

    fetch('admin/ajax/rqst.php?op=avl_absence_list')
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (!data || !data.ok) {
          rows.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">' +
            (data && data.error ? data.error : 'Failed to load requests.') + '</td></tr>';
          return;
        }

        var items = data.data || [];
        if (!items.length) {
          rows.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted">' +
            '<i class="fa fa-info-circle fa-2x mb-2 d-block text-secondary"></i>' +
            'No time off requests found.</td></tr>';
          return;
        }

        var html = '';
        var today = new Date().toISOString().slice(0, 10);

        items.forEach(function(item) {
          var badgeClass = 'badge-secondary';
          if (item.status === 'approved') badgeClass = 'badge-success';
          else if (item.status === 'requested') badgeClass = 'badge-warning';
          else if (item.status === 'rejected') badgeClass = 'badge-danger';
          else if (item.status === 'cancelled') badgeClass = 'badge-secondary';

          var canCancel = (item.status === 'requested' || item.status === 'approved') && item.date_from > today;
          var cancelBtn = canCancel
            ? '<button class="btn btn-sm btn-outline-danger btn-cancel-absence" data-id="' + item.id + '" title="Cancel request"><i class="fa fa-times"></i> Cancel</button>'
            : '-';

          html += '<tr>' +
            '<td><strong>' + escapeHtml(item.date_from) + '</strong> to <strong>' + escapeHtml(item.date_to) + '</strong></td>' +
            '<td><span class="badge badge-light border">' + item.days_count + ' days</span></td>' +
            '<td>' + escapeHtml(item.reason_type) + (item.reason_text ? '<br><small class="text-muted">' + escapeHtml(item.reason_text) + '</small>' : '') + '</td>' +
            '<td><span class="badge ' + badgeClass + '">' + escapeHtml(item.status) + '</span></td>' +
            '<td><small class="text-muted">' + escapeHtml(item.created_at) + '</small></td>' +
            '<td>' + (item.decision_note ? escapeHtml(item.decision_note) : '<span class="text-muted">-</span>') + '</td>' +
            '<td class="text-right">' + cancelBtn + '</td>' +
            '</tr>';
        });

        rows.innerHTML = html;

        var cancelBtns = rows.querySelectorAll('.btn-cancel-absence');
        cancelBtns.forEach(function(btn) {
          btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            confirmCancelAbsence(id);
          });
        });
      })
      .catch(function(err) {
        rows.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Network error loading requests.</td></tr>';
      });
  }

  function confirmCancelAbsence(id) {
    if (!window.Swal) {
      if (confirm('Are you sure you want to cancel this time off request?')) cancelAbsence(id);
      return;
    }

    Swal.fire({
      title: 'Cancel time off?',
      text: 'This request will be cancelled.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef233c',
      confirmButtonText: 'Yes, cancel'
    }).then(function(result) {
      if (result.isConfirmed) cancelAbsence(id);
    });
  }

  function cancelAbsence(id) {
    var formData = new FormData();
    formData.append('id', id);
    formData.append('csrf_token', getCsrfToken());

    fetch('admin/ajax/rqst.php?op=avl_absence_cancel', {
      method: 'POST',
      body: formData
    })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (data && data.ok) {
          if (window.Swal) Swal.fire({ icon: 'success', title: 'Request cancelled', timer: 1500, showConfirmButton: false });
          loadMyBalance();
          loadMyLeaves();
          if (window.CAN_APPROVE_LEAVES) {
            loadApprovals();
            loadBalances();
          }
        } else {
          var msg = data && data.error ? data.error : 'Failed to cancel request';
          if (window.Swal) Swal.fire('Error', msg, 'error');
          else PGS.notify(msg);
        }
      })
      .catch(function(err) {
        if (window.Swal) Swal.fire('Error', 'Network error cancelling request', 'error');
        else PGS.notify('Network error cancelling request');
      });
  }

  // --- LIVE PREVIEW FOR REQUEST FORM ---
  var reqDateFrom = document.getElementById('reqDateFrom');
  var reqDateTo = document.getElementById('reqDateTo');
  var leavePreviewAlert = document.getElementById('leavePreviewAlert');
  var previewDaysCount = document.getElementById('previewDaysCount');
  var previewHolidaysInfo = document.getElementById('previewHolidaysInfo');

  function updateLeavePreview() {
    if (!reqDateFrom || !reqDateTo || !leavePreviewAlert) return;
    var d1 = reqDateFrom.value;
    var d2 = reqDateTo.value;

    if (!d1 || !d2 || d1 > d2) {
      leavePreviewAlert.style.display = 'none';
      return;
    }

    fetch('admin/ajax/rqst.php?op=avl_leave_preview&date_from=' + encodeURIComponent(d1) + '&date_to=' + encodeURIComponent(d2))
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (data && data.ok && data.data) {
          var p = data.data;
          previewDaysCount.innerText = p.days;
          var exclCount = (p.excluded || []).length;
          previewHolidaysInfo.innerText = exclCount > 0 ? '(' + exclCount + ' weekend/holiday dates excluded)' : '';
          leavePreviewAlert.style.display = 'block';
        } else {
          leavePreviewAlert.style.display = 'none';
        }
      })
      .catch(function() {
        leavePreviewAlert.style.display = 'none';
      });
  }

  if (reqDateFrom) reqDateFrom.addEventListener('change', updateLeavePreview);
  if (reqDateTo) reqDateTo.addEventListener('change', updateLeavePreview);

  // --- REQUEST FORM SUBMIT ---
  var formRequestLeave = document.getElementById('formRequestLeave');
  var btnRequestLeave = document.getElementById('btnRequestLeave');
  var modalRequestLeave = $('#modalRequestLeave');

  if (btnRequestLeave) {
    btnRequestLeave.addEventListener('click', function() {
      if (formRequestLeave) {
        formRequestLeave.reset();
        if (leavePreviewAlert) leavePreviewAlert.style.display = 'none';
      }
      modalRequestLeave.modal('show');
    });
  }

  if (formRequestLeave) {
    formRequestLeave.addEventListener('submit', function(e) {
      e.preventDefault();

      var formData = new FormData(formRequestLeave);
      if (!formData.get('csrf_token')) formData.append('csrf_token', getCsrfToken());

      var btnSubmit = document.getElementById('btnSubmitLeave');
      if (btnSubmit) {
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Submitting...';
      }

      fetch('admin/ajax/rqst.php?op=avl_absence_request', {
        method: 'POST',
        body: formData
      })
        .then(function(res) { return res.json(); })
        .then(function(data) {
          if (btnSubmit) {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = 'Submit Request';
          }

          if (data && data.ok) {
            modalRequestLeave.modal('hide');
            if (window.Swal) Swal.fire({ icon: 'success', title: 'Time off requested', timer: 1500, showConfirmButton: false });
            loadMyBalance();
            loadMyLeaves();
            if (window.CAN_APPROVE_LEAVES) {
              loadApprovals();
              loadBalances();
            }
          } else {
            var msg = data && data.error ? data.error : 'Failed to request time off';
            if (window.Swal) Swal.fire('Error', msg, 'error');
            else PGS.notify(msg);
          }
        })
        .catch(function(err) {
          if (btnSubmit) {
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = 'Submit Request';
          }
          if (window.Swal) Swal.fire('Error', 'Network error submitting request', 'error');
          else PGS.notify('Network error submitting request');
        });
    });
  }

  // --- APPROVALS PANE ---
  function loadApprovals() {
    if (!window.CAN_APPROVE_LEAVES) return;

    var rows = document.getElementById('approvalsRows');
    var badge = document.getElementById('pendingApprovalsBadge');
    if (!rows) return;

    fetch('admin/ajax/rqst.php?op=avl_absence_list&status=requested')
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (!data || !data.ok) {
          rows.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-danger">' +
            (data && data.error ? data.error : 'Failed to load approvals.') + '</td></tr>';
          return;
        }

        var items = data.data || [];
        if (badge) {
          badge.innerText = items.length;
          badge.style.display = items.length > 0 ? 'inline-block' : 'none';
        }

        if (!items.length) {
          rows.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted">' +
            '<i class="fa fa-check-circle-o fa-2x mb-2 d-block text-success"></i>' +
            'No pending time off requests awaiting approval.</td></tr>';
          return;
        }

        var html = '';
        items.forEach(function(item) {
          var deductBadge = item.deducts_balance == 1
            ? '<span class="badge badge-warning">Deductible</span>'
            : '<span class="badge badge-light">Non-deductible</span>';

          html += '<tr>' +
            '<td><strong>' + escapeHtml(item.employee_nombre) + '</strong></td>' +
            '<td>' + escapeHtml(item.date_from) + ' to ' + escapeHtml(item.date_to) + '</td>' +
            '<td><span class="badge badge-secondary">' + item.days_count + ' days</span></td>' +
            '<td>' + escapeHtml(item.reason_type) + '</td>' +
            '<td>' + deductBadge + '</td>' +
            '<td>' + (item.reason_text ? escapeHtml(item.reason_text) : '<span class="text-muted">-</span>') + '</td>' +
            '<td><small class="text-muted">' + escapeHtml(item.created_at) + '</small></td>' +
            '<td class="text-right">' +
            '<button class="btn btn-sm btn-success mr-1 btn-approve-absence" data-id="' + item.id + '" title="Approve"><i class="fa fa-check"></i></button>' +
            '<button class="btn btn-sm btn-danger btn-reject-absence" data-id="' + item.id + '" title="Reject"><i class="fa fa-times"></i></button>' +
            '</td>' +
            '</tr>';
        });

        rows.innerHTML = html;

        var approveBtns = rows.querySelectorAll('.btn-approve-absence');
        approveBtns.forEach(function(btn) {
          btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            decideAbsence(id, 'approved');
          });
        });

        var rejectBtns = rows.querySelectorAll('.btn-reject-absence');
        rejectBtns.forEach(function(btn) {
          btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            promptRejectAbsence(id);
          });
        });
      })
      .catch(function(err) {
        rows.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-danger">Network error loading approvals.</td></tr>';
      });
  }

  function decideAbsence(id, decision, note) {
    var formData = new FormData();
    formData.append('id', id);
    formData.append('decision', decision);
    if (note) formData.append('note', note);
    formData.append('csrf_token', getCsrfToken());

    fetch('admin/ajax/rqst.php?op=avl_absence_decide', {
      method: 'POST',
      body: formData
    })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (data && data.ok) {
          var title = decision === 'approved' ? 'Request approved' : 'Request rejected';
          var text = '';
          if (data.data && data.data.conflicts && data.data.conflicts.length > 0) {
            text = 'Notice: Worker had ' + data.data.conflicts.length + ' reservation(s) scheduled during this range.';
          }

          if (window.Swal) {
            Swal.fire({
              icon: decision === 'approved' ? 'success' : 'info',
              title: title,
              text: text,
              timer: text ? 4000 : 1500,
              showConfirmButton: !!text
            });
          }
          loadApprovals();
          loadBalances();
          if (window.CAN_REQUEST_LEAVES) {
            loadMyBalance();
            loadMyLeaves();
          }
        } else {
          var msg = data && data.error ? data.error : 'Failed to decide on request';
          if (window.Swal) Swal.fire('Error', msg, 'error');
          else PGS.notify(msg);
        }
      })
      .catch(function(err) {
        if (window.Swal) Swal.fire('Error', 'Network error processing decision', 'error');
        else PGS.notify('Network error processing decision');
      });
  }

  function promptRejectAbsence(id) {
    if (!window.Swal) {
      var note = prompt('Optional reason for rejection:');
      decideAbsence(id, 'rejected', note);
      return;
    }

    Swal.fire({
      title: 'Reject time off request?',
      input: 'text',
      inputPlaceholder: 'Reason for rejection...',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef233c',
      confirmButtonText: 'Yes, reject'
    }).then(function(result) {
      if (result.isConfirmed) {
        decideAbsence(id, 'rejected', result.value);
      }
    });
  }

  // --- BALANCES & QUOTAS PANE ---
  var selectBalanceYear = document.getElementById('selectBalanceYear');

  function loadBalances() {
    if (!window.CAN_APPROVE_LEAVES) return;

    var rows = document.getElementById('balancesRows');
    if (!rows) return;

    var year = selectBalanceYear ? selectBalanceYear.value : (window.CURRENT_YEAR || new Date().getFullYear());

    fetch('admin/ajax/rqst.php?op=avl_leave_balances&year=' + encodeURIComponent(year))
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (!data || !data.ok) {
          rows.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">' +
            (data && data.error ? data.error : 'Failed to load balances.') + '</td></tr>';
          return;
        }

        var items = data.data || [];
        if (!items.length) {
          rows.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted">' +
            '<i class="fa fa-info-circle fa-2x mb-2 d-block text-secondary"></i>' +
            'No worker records found.</td></tr>';
          return;
        }

        var html = '';
        items.forEach(function(item) {
          html += '<tr>' +
            '<td><strong>' + escapeHtml(item.employee_nombre) + '</strong></td>' +
            '<td><span class="badge badge-light border">' + item.quota + ' days</span></td>' +
            '<td>' + item.used + '</td>' +
            '<td>' + (item.pending > 0 ? '<span class="text-warning font-weight-bold">' + item.pending + '</span>' : '0') + '</td>' +
            '<td>' + item.net_balance + '</td>' +
            '<td><strong class="' + (item.available > 0 ? 'text-success' : 'text-danger') + '">' + item.available + ' days</strong></td>' +
            '<td class="text-right">' +
            '<button class="btn btn-sm btn-outline-primary mr-1 btn-set-quota" data-id="' + item.employee_id + '" data-name="' + escapeHtml(item.employee_nombre) + '" data-quota="' + item.quota + '" data-version="' + item.row_version + '" title="Set annual quota"><i class="fa fa-sliders"></i> Quota</button>' +
            '<button class="btn btn-sm btn-outline-secondary mr-1 btn-adjust-bal" data-id="' + item.employee_id + '" data-name="' + escapeHtml(item.employee_nombre) + '" title="Manual adjustment"><i class="fa fa-pencil"></i> Adjust</button>' +
            '<button class="btn btn-sm btn-outline-dark btn-view-ledger" data-id="' + item.employee_id + '" data-name="' + escapeHtml(item.employee_nombre) + '" title="View movements ledger"><i class="fa fa-history"></i></button>' +
            '</td>' +
            '</tr>';
        });

        rows.innerHTML = html;

        // Attach buttons
        var quotaBtns = rows.querySelectorAll('.btn-set-quota');
        quotaBtns.forEach(function(btn) {
          btn.addEventListener('click', function() {
            openSetQuotaModal(
              this.getAttribute('data-id'),
              this.getAttribute('data-name'),
              this.getAttribute('data-quota'),
              this.getAttribute('data-version')
            );
          });
        });

        var adjustBtns = rows.querySelectorAll('.btn-adjust-bal');
        adjustBtns.forEach(function(btn) {
          btn.addEventListener('click', function() {
            openAdjustModal(
              this.getAttribute('data-id'),
              this.getAttribute('data-name')
            );
          });
        });

        var ledgerBtns = rows.querySelectorAll('.btn-view-ledger');
        ledgerBtns.forEach(function(btn) {
          btn.addEventListener('click', function() {
            openLedgerModal(
              this.getAttribute('data-id'),
              this.getAttribute('data-name')
            );
          });
        });
      })
      .catch(function(err) {
        rows.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Network error loading balances.</td></tr>';
      });
  }

  function openSetQuotaModal(employeeId, name, quota, rowVersion) {
    var year = selectBalanceYear ? selectBalanceYear.value : window.CURRENT_YEAR;
    document.getElementById('quotaEmployeeId').value = employeeId;
    document.getElementById('quotaYear').value = year;
    document.getElementById('quotaRowVersion').value = rowVersion || 1;
    document.getElementById('quotaWorkerName').innerText = name;
    document.getElementById('quotaYearLabel').innerText = year;
    document.getElementById('quotaDays').value = quota || 0;
    $('#modalSetQuota').modal('show');
  }

  function openAdjustModal(employeeId, name) {
    var year = selectBalanceYear ? selectBalanceYear.value : window.CURRENT_YEAR;
    document.getElementById('adjustEmployeeId').value = employeeId;
    document.getElementById('adjustYear').value = year;
    document.getElementById('adjustWorkerName').innerText = name;
    document.getElementById('adjustYearLabel').innerText = year;
    document.getElementById('adjustDays').value = '';
    document.getElementById('adjustReason').value = '';
    $('#modalAdjustBalance').modal('show');
  }

  function openLedgerModal(employeeId, name) {
    var year = selectBalanceYear ? selectBalanceYear.value : window.CURRENT_YEAR;
    var rows = document.getElementById('ledgerRows');
    rows.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted"><i class="fa fa-spinner fa-spin mr-2"></i>Loading ledger...</td></tr>';
    $('#modalLedger').modal('show');

    fetch('admin/ajax/rqst.php?op=avl_leave_ledger&employee_id=' + encodeURIComponent(employeeId) + '&year=' + encodeURIComponent(year))
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (!data || !data.ok) {
          rows.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger">' +
            (data && data.error ? data.error : 'Failed to load ledger.') + '</td></tr>';
          return;
        }

        var items = data.data || [];
        if (!items.length) {
          rows.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No ledger movements recorded for ' + year + '.</td></tr>';
          return;
        }

        var html = '';
        items.forEach(function(item) {
          var dayClass = item.days > 0 ? 'text-success' : 'text-danger';
          var dayPrefix = item.days > 0 ? '+' : '';
          html += '<tr>' +
            '<td><small>' + escapeHtml(item.created_at) + '</small></td>' +
            '<td><span class="badge badge-light border">' + escapeHtml(item.movement) + '</span></td>' +
            '<td><strong class="' + dayClass + '">' + dayPrefix + item.days + '</strong></td>' +
            '<td>' + (item.reason ? escapeHtml(item.reason) : '-') + '</td>' +
            '<td><span class="badge badge-light">' + escapeHtml(item.channel) + '</span></td>' +
            '<td><small class="text-muted">' + (item.actor_nombre ? escapeHtml(item.actor_nombre) : '-') + '</small></td>' +
            '</tr>';
        });

        rows.innerHTML = html;
      })
      .catch(function(err) {
        rows.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger">Network error loading ledger.</td></tr>';
      });
  }

  // --- SET QUOTA FORM SUBMIT ---
  var formSetQuota = document.getElementById('formSetQuota');
  if (formSetQuota) {
    formSetQuota.addEventListener('submit', function(e) {
      e.preventDefault();
      var formData = new FormData(formSetQuota);
      if (!formData.get('csrf_token')) formData.append('csrf_token', getCsrfToken());

      var btnSave = document.getElementById('btnSaveQuota');
      if (btnSave) {
        btnSave.disabled = true;
        btnSave.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Saving...';
      }

      fetch('admin/ajax/rqst.php?op=avl_leave_set_quota', {
        method: 'POST',
        body: formData
      })
        .then(function(res) { return res.json(); })
        .then(function(data) {
          if (btnSave) {
            btnSave.disabled = false;
            btnSave.innerHTML = 'Save Quota';
          }

          if (data && data.ok) {
            $('#modalSetQuota').modal('hide');
            if (window.Swal) Swal.fire({ icon: 'success', title: 'Quota updated', timer: 1500, showConfirmButton: false });
            loadBalances();
            if (window.CAN_REQUEST_LEAVES) loadMyBalance();
          } else {
            var msg = data && data.error ? data.error : 'Failed to update quota';
            if (window.Swal) Swal.fire('Error', msg, 'error');
            else PGS.notify(msg);
          }
        })
        .catch(function(err) {
          if (btnSave) {
            btnSave.disabled = false;
            btnSave.innerHTML = 'Save Quota';
          }
          if (window.Swal) Swal.fire('Error', 'Network error saving quota', 'error');
          else PGS.notify('Network error saving quota');
        });
    });
  }

  // --- ADJUST BALANCE FORM SUBMIT ---
  var formAdjustBalance = document.getElementById('formAdjustBalance');
  if (formAdjustBalance) {
    formAdjustBalance.addEventListener('submit', function(e) {
      e.preventDefault();
      var formData = new FormData(formAdjustBalance);
      if (!formData.get('csrf_token')) formData.append('csrf_token', getCsrfToken());

      var btnSave = document.getElementById('btnSaveAdjust');
      if (btnSave) {
        btnSave.disabled = true;
        btnSave.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Applying...';
      }

      fetch('admin/ajax/rqst.php?op=avl_leave_adjust', {
        method: 'POST',
        body: formData
      })
        .then(function(res) { return res.json(); })
        .then(function(data) {
          if (btnSave) {
            btnSave.disabled = false;
            btnSave.innerHTML = 'Apply Adjustment';
          }

          if (data && data.ok) {
            $('#modalAdjustBalance').modal('hide');
            if (window.Swal) Swal.fire({ icon: 'success', title: 'Adjustment applied', timer: 1500, showConfirmButton: false });
            loadBalances();
            if (window.CAN_REQUEST_LEAVES) loadMyBalance();
          } else {
            var msg = data && data.error ? data.error : 'Failed to apply adjustment';
            if (window.Swal) Swal.fire('Error', msg, 'error');
            else PGS.notify(msg);
          }
        })
        .catch(function(err) {
          if (btnSave) {
            btnSave.disabled = false;
            btnSave.innerHTML = 'Apply Adjustment';
          }
          if (window.Swal) Swal.fire('Error', 'Network error applying adjustment', 'error');
          else PGS.notify('Network error applying adjustment');
        });
    });
  }

  // --- REFRESH BUTTONS & EVENTS ---
  var btnRefreshMyLeaves = document.getElementById('btnRefreshMyLeaves');
  if (btnRefreshMyLeaves) {
    btnRefreshMyLeaves.addEventListener('click', function() {
      loadMyBalance();
      loadMyLeaves();
    });
  }

  var btnRefreshApprovals = document.getElementById('btnRefreshApprovals');
  if (btnRefreshApprovals) {
    btnRefreshApprovals.addEventListener('click', loadApprovals);
  }

  var btnRefreshBalances = document.getElementById('btnRefreshBalances');
  if (btnRefreshBalances) {
    btnRefreshBalances.addEventListener('click', loadBalances);
  }

  if (selectBalanceYear) {
    selectBalanceYear.addEventListener('change', loadBalances);
  }

  // Initial loads
  if (window.CAN_REQUEST_LEAVES) {
    loadMyBalance();
    loadMyLeaves();
  }
  if (window.CAN_APPROVE_LEAVES) {
    loadApprovals();
    loadBalances();
  }

})();
