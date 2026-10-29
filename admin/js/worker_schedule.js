/**
 * Worker Schedules Controller
 */
(function() {
  'use strict';

  var WEEKDAYS = {
    1: 'Monday',
    2: 'Tuesday',
    3: 'Wednesday',
    4: 'Thursday',
    5: 'Friday',
    6: 'Saturday',
    7: 'Sunday'
  };

  var filterUnit = document.getElementById('filterUnit');
  var filterWorker = document.getElementById('filterWorker');
  var btnResetFilters = document.getElementById('btnResetFilters');
  var scheduleRows = document.getElementById('scheduleRows');
  var btnNewSchedule = document.getElementById('btnNewSchedule');
  var formSchedule = document.getElementById('formSchedule');
  var modalSchedule = $('#modalSchedule');

  function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.content) return meta.content;
    var inp = document.querySelector('input[name="csrf_token"]');
    return inp ? inp.value : '';
  }

  function loadSchedules() {
    var unitId = filterUnit ? filterUnit.value : '';
    var workerId = filterWorker ? filterWorker.value : '';

    if (!unitId && !workerId) {
      scheduleRows.innerHTML = '<tr><td colspan="' + (window.CAN_MANAGE_SCHEDULE ? '7' : '6') + '" class="text-center py-5 text-muted">' +
        '<i class="fa fa-calendar fa-2x mb-2 d-block text-secondary"></i>' +
        'Please select a property or worker to display the recurring schedule.</td></tr>';
      return;
    }

    scheduleRows.innerHTML = '<tr><td colspan="' + (window.CAN_MANAGE_SCHEDULE ? '7' : '6') + '" class="text-center py-4 text-muted">' +
      '<i class="fa fa-spinner fa-spin mr-2"></i>Loading schedules...</td></tr>';

    var params = new URLSearchParams();
    if (unitId) params.append('unidad_id', unitId);
    if (workerId) params.append('employee_id', workerId);

    fetch('admin/ajax/rqst.php?op=avl_schedule_list&' + params.toString())
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (!data || !data.ok) {
          scheduleRows.innerHTML = '<tr><td colspan="' + (window.CAN_MANAGE_SCHEDULE ? '7' : '6') + '" class="text-center py-4 text-danger">' +
            (data && data.error ? data.error : 'Failed to load schedule.') + '</td></tr>';
          return;
        }

        var items = data.data || [];
        if (workerId && unitId) {
          items = items.filter(function(x) { return String(x.employee_id) === String(workerId); });
        }

        renderSchedule(items);
      })
      .catch(function(err) {
        scheduleRows.innerHTML = '<tr><td colspan="' + (window.CAN_MANAGE_SCHEDULE ? '7' : '6') + '" class="text-center py-4 text-danger">' +
          'Network error loading schedule.</td></tr>';
      });
  }

  function renderSchedule(items) {
    if (!items.length) {
      scheduleRows.innerHTML = '<tr><td colspan="' + (window.CAN_MANAGE_SCHEDULE ? '7' : '6') + '" class="text-center py-5 text-muted">' +
        '<i class="fa fa-info-circle fa-2x mb-2 d-block text-secondary"></i>' +
        'No active recurring schedules configured for this selection.</td></tr>';
      return;
    }

    // Sort by weekday ASC, then worker name
    items.sort(function(a, b) {
      if (a.weekday !== b.weekday) return a.weekday - b.weekday;
      return (a.employee_nombre || '').localeCompare(b.employee_nombre || '');
    });

    var html = '';
    items.forEach(function(item) {
      var dayName = WEEKDAYS[item.weekday] || ('Day ' + item.weekday);
      var partBadge = '<span class="badge badge-primary">Full day</span>';
      if (item.day_part === 'am') partBadge = '<span class="badge badge-info">Morning (AM)</span>';
      else if (item.day_part === 'pm') partBadge = '<span class="badge badge-warning">Afternoon (PM)</span>';

      var workerName = item.employee_nombre || ('Worker #' + item.employee_id);
      var validTo = item.valid_to || '<span class="text-muted">Ongoing</span>';
      var notes = item.notes ? escapeHtml(item.notes) : '<span class="text-muted">-</span>';

      html += '<tr>' +
        '<td><strong class="text-dark">' + dayName + '</strong></td>' +
        '<td>' + escapeHtml(workerName) + '</td>' +
        '<td>' + partBadge + '</td>' +
        '<td>' + escapeHtml(item.valid_from) + '</td>' +
        '<td>' + validTo + '</td>' +
        '<td>' + notes + '</td>';

      if (window.CAN_MANAGE_SCHEDULE) {
        html += '<td class="text-right">' +
          '<button class="btn btn-sm btn-outline-danger btn-delete-sched" data-id="' + item.id + '" title="Remove schedule">' +
          '<i class="fa fa-trash"></i></button></td>';
      }

      html += '</tr>';
    });

    scheduleRows.innerHTML = html;

    // Attach delete handlers
    var deleteBtns = scheduleRows.querySelectorAll('.btn-delete-sched');
    deleteBtns.forEach(function(btn) {
      btn.addEventListener('click', function() {
        var id = this.getAttribute('data-id');
        confirmDelete(id);
      });
    });
  }

  function confirmDelete(id) {
    if (!window.Swal) {
      if (confirm('Are you sure you want to remove this recurring schedule?')) {
        deleteSchedule(id);
      }
      return;
    }

    Swal.fire({
      title: 'Remove schedule?',
      text: 'This recurring shift will be deleted.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef233c',
      confirmButtonText: 'Yes, remove'
    }).then(function(result) {
      if (result.isConfirmed) {
        deleteSchedule(id);
      }
    });
  }

  function deleteSchedule(id) {
    var formData = new FormData();
    formData.append('id', id);
    formData.append('csrf_token', getCsrfToken());

    fetch('admin/ajax/rqst.php?op=avl_schedule_delete', {
      method: 'POST',
      body: formData
    })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (data && data.ok) {
          if (window.Swal) {
            Swal.fire({ icon: 'success', title: 'Schedule removed', timer: 1500, showConfirmButton: false });
          }
          loadSchedules();
        } else {
          var msg = data && data.error ? data.error : 'Failed to delete schedule';
          if (window.Swal) Swal.fire('Error', msg, 'error');
          else PGS.notify(msg);
        }
      })
      .catch(function(err) {
        if (window.Swal) Swal.fire('Error', 'Network error deleting schedule', 'error');
        else PGS.notify('Network error deleting schedule');
      });
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // Events
  if (filterUnit) filterUnit.addEventListener('change', loadSchedules);
  if (filterWorker) filterWorker.addEventListener('change', loadSchedules);
  if (btnResetFilters) {
    btnResetFilters.addEventListener('click', function() {
      if (filterUnit) filterUnit.value = '';
      if (filterWorker) filterWorker.value = '';
      loadSchedules();
    });
  }

  if (btnNewSchedule) {
    btnNewSchedule.addEventListener('click', function() {
      if (formSchedule) {
        formSchedule.reset();
        // Prefill property if one is selected in filter
        if (filterUnit && filterUnit.value) {
          var modalUnit = document.getElementById('modalUnit');
          if (modalUnit) modalUnit.value = filterUnit.value;
        }
        if (filterWorker && filterWorker.value) {
          var modalWorker = document.getElementById('modalWorker');
          if (modalWorker) modalWorker.value = filterWorker.value;
        }
      }
      modalSchedule.modal('show');
    });
  }

  if (formSchedule) {
    formSchedule.addEventListener('submit', function(e) {
      e.preventDefault();

      var formData = new FormData(formSchedule);
      // Ensure csrf_token is present
      if (!formData.get('csrf_token')) {
        formData.append('csrf_token', getCsrfToken());
      }

      var checkedDays = formSchedule.querySelectorAll('input[name="weekdays[]"]:checked');
      if (!checkedDays.length) {
        if (window.Swal) Swal.fire('Warning', 'Please select at least one day of the week.', 'warning');
        else PGS.notify('Please select at least one day of the week.');
        return;
      }

      var btnSave = document.getElementById('btnSaveSchedule');
      if (btnSave) {
        btnSave.disabled = true;
        btnSave.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Saving...';
      }

      fetch('admin/ajax/rqst.php?op=avl_schedule_save', {
        method: 'POST',
        body: formData
      })
        .then(function(res) { return res.json(); })
        .then(function(data) {
          if (btnSave) {
            btnSave.disabled = false;
            btnSave.innerHTML = 'Save Schedule';
          }

          if (data && data.ok) {
            modalSchedule.modal('hide');
            if (window.Swal) {
              Swal.fire({ icon: 'success', title: 'Schedule saved', timer: 1500, showConfirmButton: false });
            }
            // Sync filterUnit if needed
            var savedUnit = formData.get('unidad_id');
            if (filterUnit && savedUnit) {
              filterUnit.value = savedUnit;
            }
            loadSchedules();
          } else {
            var msg = data && data.error ? data.error : 'Failed to save schedule';
            if (window.Swal) Swal.fire('Error', msg, 'error');
            else PGS.notify(msg);
          }
        })
        .catch(function(err) {
          if (btnSave) {
            btnSave.disabled = false;
            btnSave.innerHTML = 'Save Schedule';
          }
          if (window.Swal) Swal.fire('Error', 'Network error saving schedule', 'error');
          else PGS.notify('Network error saving schedule');
        });
    });
  }

})();
