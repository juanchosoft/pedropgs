$(function () {
  'use strict';

  var currentPage = 1;
  var perPage = 25;
  var searchTimeout = null;

  // Initialize URL params
  var urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('status')) {
    $('#filterStatus').val(urlParams.get('status'));
  }
  if (urlParams.get('new') === '1' && window.PGS_TASKS.canCreate) {
    openCreateModal();
  }

  loadTasks(1);

  // Filters bindings
  $('#filterStatus, #filterProperty, #filterPriority, #filterAssignee, #filterOrigin, #filterCreatedFrom, #filterCreatedTo').on('change', function () {
    loadTasks(1);
  });

  $('#filterOnlyMine').on('change', function () {
    loadTasks(1);
  });

  $('#filterSearch').on('input', function () {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(function () {
      loadTasks(1);
    }, 350);
  });

  $('#btnResetFilters').on('click', function () {
    $('#filterStatus').val('');
    $('#filterProperty').val('');
    $('#filterPriority').val('');
    $('#filterAssignee').val('');
    $('#filterOrigin').val('');
    $('#filterSearch').val('');
    $('#filterCreatedFrom').val('');
    $('#filterCreatedTo').val('');
    $('#filterOnlyMine').prop('checked', false);
    loadTasks(1);
  });

  var assigneeDefaultHtml = $('#taskAssignee').html();

  function fillAssignees(rows, selectedId) {
    var $sel = $('#taskAssignee');
    var assigned = rows.filter(function (r) { return String(r.assigned) === '1'; });
    var others = rows.filter(function (r) { return String(r.assigned) !== '1'; });
    $sel.empty().append($('<option>').val('').text('Unassigned'));
    function group(label, list) {
      if (!list.length) { return; }
      var $g = $('<optgroup>').attr('label', label);
      list.forEach(function (r) { $g.append($('<option>').val(r.id).text(r.nombre)); });
      $sel.append($g);
    }
    if (assigned.length) {
      group('Assigned to this property', assigned);
      group('Other staff', others);
    } else {
      group('Staff', others);
    }
    if (selectedId) {
      $sel.val(String(selectedId));
    } else if (assigned.length) {
      $sel.val(String(assigned[0].id));
    }
  }

  $('#taskUnit').on('change', function () {
    var unit = $(this).val();
    if (!$('#taskAssignee').length || !unit) { return; }
    PGS.call('tsk_employees_for_unit', { unit_id: unit }, function (res) {
      if (res && res.output && res.output.valid && $.isArray(res.output.response)) {
        fillAssignees(res.output.response, null);
      }
    }, { silent: true });
  });

  $('#btnNewTask').on('click', function () {
    openCreateModal();
  });

  function openCreateModal() {
    $('#formTask')[0].reset();
    if ($('#taskAssignee').length) { $('#taskAssignee').html(assigneeDefaultHtml); }
    $('#taskId').val('');
    $('#taskRowVersion').val('1');
    $('#modalTaskTitle').text('New Task');
    $('#modalTask').modal('show');
  }

  // Submit task form
  $('#formTask').on('submit', function (e) {
    e.preventDefault();
    var id = $('#taskId').val();
    var data = {
      id: id,
      row_version: $('#taskRowVersion').val(),
      unidad_id: $('#taskUnit').val(),
      location_label: $('#taskLocation').val(),
      title: $('#taskTitle').val(),
      description: $('#taskDescription').val(),
      priority: $('#taskPriority').val(),
      required_date: $('#taskRequiredDate').val(),
      due_date: $('#taskDueDate').val(),
      service_id: $('#taskService').val()
    };

    if (window.PGS_TASKS.canAssign && $('#taskAssignee').length) {
      data.assignee_employee_id = $('#taskAssignee').val();
    }

    var $btn = $('#btnSaveTask');
    $btn.prop('disabled', true).text('Saving...');

    PGS.call('tsk_save', data, function (res) {
      $btn.prop('disabled', false).text('Save Task');
      if (res && res.output && res.output.valid) {
        $('#modalTask').modal('hide');
        swal('Success', 'Task saved successfully.', 'success');
        loadTasks(currentPage);
      } else {
        PGS.error(res);
      }
    }, {
      error: function (xhr) {
        $btn.prop('disabled', false).text('Save Task');
        var err = xhr.responseJSON;
        PGS.error(err || { output: { response: { content: 'Error saving task.' } } });
      }
    });
  });

  function loadTasks(page) {
    currentPage = page || 1;
    var params = {
      page: currentPage,
      per_page: perPage,
      status: $('#filterStatus').val(),
      unidad_id: $('#filterProperty').val(),
      priority: $('#filterPriority').val(),
      assignee_employee_id: $('#filterAssignee').val(),
      origin: $('#filterOrigin').val(),
      created_from: $('#filterCreatedFrom').val(),
      created_to: $('#filterCreatedTo').val(),
      only_mine: $('#filterOnlyMine').is(':checked') ? 1 : '',
      q: $('#filterSearch').val()
    };

    $('#tblTasksBody').html('<tr><td colspan="10" class="text-center py-4 text-muted"><i class="fa fa-spinner fa-spin mr-1"></i> Loading tasks...</td></tr>');

    PGS.call('tsk_list', params, function (res) {
      if (res && res.rows) {
        renderTasks(res.rows, res.total);
        updateKpis(res.rows);
      } else if (res && res.output && res.output.response && res.output.response.rows) {
        renderTasks(res.output.response.rows, res.output.response.total);
        updateKpis(res.output.response.rows);
      } else {
        $('#tblTasksBody').html('<tr><td colspan="10" class="text-center py-4 text-muted">No tasks found.</td></tr>');
      }
    }, {
      error: function () {
        $('#tblTasksBody').html('<tr><td colspan="10" class="text-center py-4 text-danger">Failed to load tasks.</td></tr>');
      }
    });
  }

  function renderTasks(rows, total) {
    var $tbody = $('#tblTasksBody');
    $tbody.empty();

    if (!rows || rows.length === 0) {
      $tbody.html('<tr><td colspan="10" class="text-center py-4 text-muted">No tasks matching filters.</td></tr>');
      $('#paginationInfo').text('Showing 0 of 0 tasks');
      $('#paginationList').empty();
      return;
    }

    var start = (currentPage - 1) * perPage + 1;
    var end = Math.min(start + rows.length - 1, total);
    $('#paginationInfo').text('Showing ' + start + '-' + end + ' of ' + total + ' tasks');

    $.each(rows, function (i, t) {
      var assignee = t.assignee_nombre ? PGS.esc(t.assignee_nombre) : '<span class="text-muted font-italic">Unassigned</span>';
      var property = t.unidad_nombre ? PGS.esc(t.unidad_nombre) : '<span class="text-muted font-italic">-</span>';
      var loc = t.location_label ? PGS.esc(t.location_label) : '-';
      var due = t.due_date ? t.due_date : (t.required_date ? '<span class="text-muted">Req: ' + t.required_date + '</span>' : '-');
      var created = t.created_at ? t.created_at.substring(0, 10) : '-';

      var tr = '<tr>' +
        '<td><a href="task_view.php?code=' + encodeURIComponent(t.code) + '" class="font-weight-bold" style="color:var(--brand);">' + PGS.esc(t.code) + '</a></td>' +
        '<td><a href="task_view.php?code=' + encodeURIComponent(t.code) + '" class="font-weight-bold text-dark">' + PGS.esc(t.title) + '</a></td>' +
        '<td>' + property + '</td>' +
        '<td><small>' + loc + '</small></td>' +
        '<td>' + PGS.prio(t.priority) + '</td>' +
        '<td>' + PGS.badge(t.status) + '</td>' +
        '<td><small>' + assignee + '</small></td>' +
        '<td><small>' + due + '</small></td>' +
        '<td><small class="text-muted">' + created + '</small></td>' +
        '<td class="text-right">' +
          '<a href="task_view.php?code=' + encodeURIComponent(t.code) + '" class="btn btn-sm btn-outline-secondary" title="View Details"><i class="fa fa-eye"></i></a> ' +
        '</td>' +
      '</tr>';
      $tbody.append(tr);
    });

    renderPagination(total);
  }

  function renderPagination(total) {
    var $ul = $('#paginationList');
    $ul.empty();
    var totalPages = Math.ceil(total / perPage);
    if (totalPages <= 1) return;

    var prevDisabled = currentPage === 1 ? ' disabled' : '';
    $ul.append('<li class="page-item' + prevDisabled + '"><a class="page-link" href="#" data-page="' + (currentPage - 1) + '">&laquo;</a></li>');

    var startPage = Math.max(1, currentPage - 2);
    var endPage = Math.min(totalPages, currentPage + 2);

    for (var p = startPage; p <= endPage; p++) {
      var active = p === currentPage ? ' active' : '';
      $ul.append('<li class="page-item' + active + '"><a class="page-link" href="#" data-page="' + p + '">' + p + '</a></li>');
    }

    var nextDisabled = currentPage === totalPages ? ' disabled' : '';
    $ul.append('<li class="page-item' + nextDisabled + '"><a class="page-link" href="#" data-page="' + (currentPage + 1) + '">&raquo;</a></li>');

    $ul.find('a.page-link').on('click', function (e) {
      e.preventDefault();
      var target = parseInt($(this).data('page'), 10);
      if (target >= 1 && target <= totalPages && target !== currentPage) {
        loadTasks(target);
      }
    });
  }

  function updateKpis(rows) {
    // Quick summary counter estimation from loaded list or dashboard counts
    var counts = { pending: 0, assigned: 0, in_progress: 0, completed: 0, cancelled: 0 };
    $.each(rows, function (i, r) {
      if (counts[r.status] !== undefined) counts[r.status]++;
    });
    $('#kpiPending').text(counts.pending);
    $('#kpiAssigned').text(counts.assigned);
    $('#kpiInProgress').text(counts.in_progress);
    $('#kpiCompleted').text(counts.completed);
    $('#kpiCancelled').text(counts.cancelled);
    $('#kpiOpenTotal').text(counts.pending + counts.assigned + counts.in_progress);
  }
});
