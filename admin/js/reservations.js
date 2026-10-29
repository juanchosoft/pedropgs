/**
 * Worker Reservations Controller
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

  var matrixUnit = document.getElementById('matrixUnit');
  var matrixWorker = document.getElementById('matrixWorker');
  var btnCheckAvailability = document.getElementById('btnCheckAvailability');
  var matrixCard = document.getElementById('matrixCard');
  var matrixContainer = document.getElementById('matrixContainer');
  var btnOpenReserveModal = document.getElementById('btnOpenReserveModal');
  var selectedCount = document.getElementById('selectedCount');

  var sectionMatrix = document.getElementById('sectionMatrix');
  var sectionList = document.getElementById('sectionList');
  var btnToggleView = document.getElementById('btnToggleView');

  var selectedSlots = []; // array of { date, part }

  // Toggle View
  if (btnToggleView) {
    btnToggleView.addEventListener('click', function() {
      if (sectionMatrix.style.display !== 'none') {
        sectionMatrix.style.display = 'none';
        sectionList.style.display = 'block';
        btnToggleView.innerHTML = '<i class="fa fa-calendar mr-1"></i> Availability Matrix';
        loadReservationsList();
      } else {
        sectionMatrix.style.display = 'block';
        sectionList.style.display = 'none';
        btnToggleView.innerHTML = '<i class="fa fa-list mr-1"></i> Reservations List';
      }
    });
  }

  // Load workers when unit changes
  if (matrixUnit) {
    matrixUnit.addEventListener('change', function() {
      var unitId = matrixUnit.value;
      matrixWorker.innerHTML = '<option value="">Select Worker...</option>';
      matrixWorker.disabled = true;
      btnCheckAvailability.disabled = true;
      if (matrixCard) matrixCard.style.display = 'none';

      if (!unitId) return;

      fetch('admin/ajax/rqst.php?op=avl_workers_for_unit&unidad_id=' + encodeURIComponent(unitId))
        .then(function(res) { return res.json(); })
        .then(function(data) {
          if (data && data.ok && data.data) {
            var workers = data.data;
            if (!workers.length) {
              matrixWorker.innerHTML = '<option value="">No scheduled workers found for this property</option>';
              return;
            }
            var html = '<option value="">Select Worker...</option>';
            workers.forEach(function(w) {
              html += '<option value="' + w.id + '">' + escapeHtml(w.nombre) + '</option>';
            });
            matrixWorker.innerHTML = html;
            matrixWorker.disabled = false;
          }
        })
        .catch(function(err) {
          console.error('Error loading workers', err);
        });
    });
  }

  if (matrixWorker) {
    matrixWorker.addEventListener('change', function() {
      btnCheckAvailability.disabled = !matrixWorker.value;
    });
  }

  if (btnCheckAvailability) {
    btnCheckAvailability.addEventListener('click', loadMatrix);
  }

  function loadMatrix() {
    var unitId = matrixUnit.value;
    var workerId = matrixWorker.value;
    if (!unitId || !workerId) return;

    selectedSlots = [];
    updateSelectedCount();

    var today = new Date();
    var fromStr = today.toISOString().slice(0, 10);
    var futureDate = new Date(today.getTime() + (13 * 86400000));
    var toStr = futureDate.toISOString().slice(0, 10);

    matrixContainer.innerHTML = '<div class="p-5 text-center w-100 text-muted"><i class="fa fa-spinner fa-spin mr-2"></i>Loading availability matrix...</div>';
    matrixCard.style.display = 'block';

    var params = new URLSearchParams({
      op: 'avl_availability',
      unidad_id: unitId,
      employee_id: workerId,
      from_date: fromStr,
      to_date: toStr
    });

    fetch('admin/ajax/rqst.php?' + params.toString())
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (!data || !data.ok) {
          matrixContainer.innerHTML = '<div class="p-4 text-center w-100 text-danger">' +
            (data && data.error ? data.error : 'Failed to load availability.') + '</div>';
          return;
        }

        renderMatrix(data.data || {});
      })
      .catch(function(err) {
        matrixContainer.innerHTML = '<div class="p-4 text-center w-100 text-danger">Network error loading availability matrix.</div>';
      });
  }

  function renderMatrix(daysMap) {
    var dates = Object.keys(daysMap);
    if (!dates.length) {
      matrixContainer.innerHTML = '<div class="p-4 text-center w-100 text-muted">No availability data returned.</div>';
      return;
    }

    var html = '';
    dates.forEach(function(dStr) {
      var dObj = new Date(dStr + 'T00:00:00');
      var dayName = dObj.toLocaleDateString('en-US', { weekday: 'short' });
      var dayNum = dObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });

      var info = daysMap[dStr];
      var full = info.full || { free: false, reason: 'not_scheduled' };
      var am = info.am || { free: false, reason: 'not_scheduled' };
      var pm = info.pm || { free: false, reason: 'not_scheduled' };

      html += '<div class="matrix-day-col flex-fill">' +
        '<div class="font-weight-bold text-dark small">' + dayName + '</div>' +
        '<div class="text-muted" style="font-size: 11px; margin-bottom: 8px;">' + dayNum + '</div>' +
        renderSlotBtn(dStr, 'full', full, 'Full Day') +
        renderSlotBtn(dStr, 'am', am, 'AM (Morning)') +
        renderSlotBtn(dStr, 'pm', pm, 'PM (Afternoon)') +
        '</div>';
    });

    matrixContainer.innerHTML = html;

    // Attach click listeners on free slots
    var freeBtns = matrixContainer.querySelectorAll('.slot-btn.slot-free');
    freeBtns.forEach(function(btn) {
      btn.addEventListener('click', function() {
        var d = this.getAttribute('data-date');
        var p = this.getAttribute('data-part');
        toggleSlotSelection(this, d, p);
      });
    });
  }

  function renderSlotBtn(dateStr, part, statusObj, label) {
    if (statusObj.free) {
      return '<button type="button" class="slot-btn slot-free" data-date="' + dateStr + '" data-part="' + part + '">' +
        label + '<br><small>Free</small></button>';
    }

    var reasonClass = 'slot-off';
    var reasonText = 'Off';

    if (statusObj.reason === 'booked') {
      reasonClass = 'slot-booked';
      reasonText = 'Booked';
    } else if (statusObj.reason === 'absence') {
      reasonClass = 'slot-absence';
      reasonText = 'Time Off';
    } else if (statusObj.reason === 'holiday') {
      reasonClass = 'slot-holiday';
      reasonText = 'Holiday';
    } else if (statusObj.reason === 'not_scheduled') {
      reasonClass = 'slot-off';
      reasonText = 'Not Sched';
    }

    return '<div class="slot-btn ' + reasonClass + '" title="' + escapeHtml(statusObj.reason) + '">' +
      label + '<br><small>' + reasonText + '</small></div>';
  }

  function toggleSlotSelection(el, date, part) {
    var idx = selectedSlots.findIndex(function(s) {
      return s.date === date && s.part === part;
    });

    if (idx > -1) {
      selectedSlots.splice(idx, 1);
      el.classList.remove('selected');
    } else {
      selectedSlots.push({ date: date, part: part });
      el.classList.add('selected');
    }

    updateSelectedCount();
  }

  function updateSelectedCount() {
    if (selectedCount) selectedCount.innerText = selectedSlots.length;
    if (btnOpenReserveModal) btnOpenReserveModal.disabled = selectedSlots.length === 0;
  }

  // Reserve modal open
  if (btnOpenReserveModal) {
    btnOpenReserveModal.addEventListener('click', function() {
      if (!selectedSlots.length) return;

      var unitName = matrixUnit.options[matrixUnit.selectedIndex].text;
      var workerName = matrixWorker.options[matrixWorker.selectedIndex].text;

      document.getElementById('rsvUnitName').innerText = unitName;
      document.getElementById('rsvWorkerName').innerText = workerName;
      document.getElementById('rsvEmployeeId').value = matrixWorker.value;
      document.getElementById('rsvUnidadId').value = matrixUnit.value;

      var part = selectedSlots[0].part || 'full';
      document.getElementById('rsvDayPart').value = part;

      var datesHtml = selectedSlots.map(function(s) {
        return '<span class="badge badge-danger mr-1 mb-1">' + s.date + ' (' + s.part.toUpperCase() + ')</span>';
      }).join(' ');

      document.getElementById('rsvDatesList').innerHTML = datesHtml;

      $('#modalReserve').modal('show');
    });
  }

  // Reserve form submit
  var formReserve = document.getElementById('formReserve');
  if (formReserve) {
    formReserve.addEventListener('submit', function(e) {
      e.preventDefault();

      var formData = new FormData(formReserve);
      if (!formData.get('csrf_token')) formData.append('csrf_token', getCsrfToken());

      // Append dates
      var dates = selectedSlots.map(function(s) { return s.date; });
      dates.forEach(function(d) { formData.append('dates[]', d); });

      var btnConfirm = document.getElementById('btnConfirmReserve');
      if (btnConfirm) {
        btnConfirm.disabled = true;
        btnConfirm.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Reserving...';
      }

      fetch('admin/ajax/rqst.php?op=avl_rsv_create', {
        method: 'POST',
        body: formData
      })
        .then(function(res) { return res.json(); })
        .then(function(data) {
          if (btnConfirm) {
            btnConfirm.disabled = false;
            btnConfirm.innerHTML = 'Confirm Reservation';
          }

          if (data && data.ok) {
            $('#modalReserve').modal('hide');
            if (window.Swal) {
              Swal.fire({
                icon: 'success',
                title: 'Reservation confirmed!',
                text: 'Reserved ' + (data.data && data.data.count ? data.data.count : selectedSlots.length) + ' slot(s).',
                timer: 2000,
                showConfirmButton: false
              });
            }
            loadMatrix();
          } else {
            var msg = data && data.error ? data.error : 'Failed to reserve slot';
            if (window.Swal) Swal.fire('Conflict', msg, 'error');
            else PGS.notify(msg);
          }
        })
        .catch(function(err) {
          if (btnConfirm) {
            btnConfirm.disabled = false;
            btnConfirm.innerHTML = 'Confirm Reservation';
          }
          if (window.Swal) Swal.fire('Error', 'Network error creating reservation', 'error');
          else PGS.notify('Network error creating reservation');
        });
    });
  }

  // --- RESERVATIONS LIST ---
  var filterListUnit = document.getElementById('filterListUnit');
  var filterListStatus = document.getElementById('filterListStatus');
  var filterListDate = document.getElementById('filterListDate');
  var btnRefreshList = document.getElementById('btnRefreshList');

  function loadReservationsList() {
    var rows = document.getElementById('reservationsRows');
    if (!rows) return;

    rows.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted"><i class="fa fa-spinner fa-spin mr-2"></i>Loading reservations...</td></tr>';

    var params = new URLSearchParams({ op: 'avl_rsv_list' });
    if (filterListUnit && filterListUnit.value) params.append('unidad_id', filterListUnit.value);
    if (filterListStatus && filterListStatus.value) params.append('status', filterListStatus.value);
    if (filterListDate && filterListDate.value) params.append('from_date', filterListDate.value);

    fetch('admin/ajax/rqst.php?' + params.toString())
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (!data || !data.ok) {
          rows.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-danger">' +
            (data && data.error ? data.error : 'Failed to load reservations.') + '</td></tr>';
          return;
        }

        var items = data.data || [];
        if (!items.length) {
          rows.innerHTML = '<tr><td colspan="9" class="text-center py-5 text-muted">' +
            '<i class="fa fa-info-circle fa-2x mb-2 d-block text-secondary"></i>No reservations found.</td></tr>';
          return;
        }

        var html = '';
        items.forEach(function(item) {
          var statusBadge = 'badge-secondary';
          if (item.status === 'confirmed') statusBadge = 'badge-success';
          else if (item.status === 'fulfilled') statusBadge = 'badge-primary';
          else if (item.status === 'cancelled') statusBadge = 'badge-danger';

          var taskLink = item.task_code
            ? '<a href="task_view.php?id=' + item.task_id + '" class="font-weight-bold text-danger">' + escapeHtml(item.task_code) + '</a>'
            : '<span class="text-muted">-</span>';

          var actions = '';
          if (item.status === 'confirmed' && window.CAN_MANAGE_RSV) {
            actions += '<button class="btn btn-sm btn-outline-primary mr-1 btn-fulfill-rsv" data-id="' + item.id + '" title="Mark Fulfilled"><i class="fa fa-check"></i></button>';
          }
          if (item.status === 'confirmed') {
            actions += '<button class="btn btn-sm btn-outline-danger btn-cancel-rsv" data-id="' + item.id + '" title="Cancel Reservation"><i class="fa fa-times"></i></button>';
          }

          html += '<tr>' +
            '<td><strong>' + escapeHtml(item.code) + '</strong></td>' +
            '<td>' + escapeHtml(item.work_date) + '</td>' +
            '<td>' + escapeHtml(item.unidad_nombre) + '</td>' +
            '<td>' + escapeHtml(item.employee_nombre) + '</td>' +
            '<td><span class="badge badge-light border">' + escapeHtml(item.day_part) + '</span></td>' +
            '<td><span class="badge ' + statusBadge + '">' + escapeHtml(item.status) + '</span></td>' +
            '<td>' + taskLink + '</td>' +
            '<td>' + (item.notes ? escapeHtml(item.notes) : '-') + '</td>' +
            '<td class="text-right">' + (actions || '-') + '</td>' +
            '</tr>';
        });

        rows.innerHTML = html;

        var fulfillBtns = rows.querySelectorAll('.btn-fulfill-rsv');
        fulfillBtns.forEach(function(btn) {
          btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            fulfillReservation(id);
          });
        });

        var cancelBtns = rows.querySelectorAll('.btn-cancel-rsv');
        cancelBtns.forEach(function(btn) {
          btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            promptCancelReservation(id);
          });
        });
      })
      .catch(function(err) {
        rows.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-danger">Network error loading reservations.</td></tr>';
      });
  }

  function fulfillReservation(id) {
    var formData = new FormData();
    formData.append('id', id);
    formData.append('csrf_token', getCsrfToken());

    fetch('admin/ajax/rqst.php?op=avl_rsv_fulfill', {
      method: 'POST',
      body: formData
    })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (data && data.ok) {
          if (window.Swal) Swal.fire({ icon: 'success', title: 'Reservation fulfilled', timer: 1500, showConfirmButton: false });
          loadReservationsList();
        } else {
          var msg = data && data.error ? data.error : 'Failed to fulfill reservation';
          if (window.Swal) Swal.fire('Error', msg, 'error');
          else PGS.notify(msg);
        }
      })
      .catch(function(err) {
        if (window.Swal) Swal.fire('Error', 'Network error', 'error');
        else PGS.notify('Network error');
      });
  }

  function promptCancelReservation(id) {
    if (!window.Swal) {
      var r = prompt('Reason for cancelling reservation:');
      if (r) cancelReservation(id, r);
      return;
    }

    Swal.fire({
      title: 'Cancel reservation?',
      input: 'text',
      inputPlaceholder: 'Reason for cancellation...',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef233c',
      confirmButtonText: 'Yes, cancel'
    }).then(function(result) {
      if (result.isConfirmed) {
        cancelReservation(id, result.value || 'Cancelled by user');
      }
    });
  }

  function cancelReservation(id, reason) {
    var formData = new FormData();
    formData.append('id', id);
    formData.append('reason', reason);
    formData.append('csrf_token', getCsrfToken());

    fetch('admin/ajax/rqst.php?op=avl_rsv_cancel', {
      method: 'POST',
      body: formData
    })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (data && data.ok) {
          if (window.Swal) Swal.fire({ icon: 'success', title: 'Reservation cancelled', timer: 1500, showConfirmButton: false });
          loadReservationsList();
        } else {
          var msg = data && data.error ? data.error : 'Failed to cancel reservation';
          if (window.Swal) Swal.fire('Error', msg, 'error');
          else PGS.notify(msg);
        }
      })
      .catch(function(err) {
        if (window.Swal) Swal.fire('Error', 'Network error', 'error');
        else PGS.notify('Network error');
      });
  }

  if (filterListUnit) filterListUnit.addEventListener('change', loadReservationsList);
  if (filterListStatus) filterListStatus.addEventListener('change', loadReservationsList);
  if (filterListDate) filterListDate.addEventListener('change', loadReservationsList);
  if (btnRefreshList) btnRefreshList.addEventListener('click', loadReservationsList);

})();
