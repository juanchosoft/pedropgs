$(function () {
  'use strict';

  var task = window.TASK_DATA || {};

  // Initialize badges
  $('#badgeStatus').html(PGS.badge(task.status));
  $('#badgePriority').html(PGS.prio(task.priority));

  // State transitions
  $('#btnStartTask').on('click', function () {
    PGS.confirm('Start Task', 'Mark this task as in progress?', function () {
      changeStatus('in_progress', 'Started work on task.');
    });
  });

  $('#btnCompleteTask').on('click', function () {
    PGS.confirm('Complete Task', 'Are you sure you want to mark this task as completed?', function () {
      changeStatus('completed', 'Task completed.');
    });
  });

  $('#btnCancelTask').on('click', function () {
    $('#cancelReasonInput').val('');
    $('#modalCancel').modal('show');
  });

  $('#formCancel').on('submit', function (e) {
    e.preventDefault();
    var reason = $('#cancelReasonInput').val().trim();
    if (reason.length < 3) {
      swal('Error', 'Please enter a valid cancellation reason.', 'error');
      return;
    }
    $('#modalCancel').modal('hide');
    changeStatus('cancelled', 'Task cancelled: ' + reason, reason);
  });

  $('#btnReopenTask').on('click', function () {
    swal({
      title: 'Reopen Task',
      text: 'Enter a note explaining why this task is being reopened:',
      type: 'input',
      showCancelButton: true,
      closeOnConfirm: false,
      inputPlaceholder: 'Reason for reopening...'
    }, function (inputValue) {
      if (inputValue === false) return false;
      if ($.trim(inputValue) === '') {
        swal.showInputError('You must write a note to reopen the task.');
        return false;
      }
      changeStatus('pending', inputValue);
    });
  });

  function changeStatus(newStatus, note, cancelReason) {
    var data = {
      id: task.id,
      status: newStatus,
      note: note || '',
      cancel_reason: cancelReason || ''
    };

    PGS.call('tsk_status', data, function (res) {
      if (res && res.output && res.output.valid) {
        swal({
          title: 'Status Updated',
          text: 'The task status has been updated.',
          type: 'success'
        }, function () {
          window.location.reload();
        });
      } else {
        PGS.error(res);
      }
    }, {
      error: function (xhr) {
        var err = xhr.responseJSON;
        PGS.error(err || { output: { response: { content: 'Could not update status.' } } });
      }
    });
  }

  // Assignee modal
  $('#btnOpenAssign, #btnReassign, #btnAssignFirst').on('click', function () {
    $('#modalAssign').modal('show');
  });

  $('#formAssign').on('submit', function (e) {
    e.preventDefault();
    var empId = $('#selectAssignee').val();
    var note = $('#assignNote').val();

    var $btn = $('#btnSaveAssign');
    $btn.prop('disabled', true).text('Assigning...');

    PGS.call('tsk_assign', { id: task.id, assignee_employee_id: empId, note: note }, function (res) {
      $btn.prop('disabled', false).text('Save Assignment');
      if (res && res.output && res.output.valid) {
        $('#modalAssign').modal('hide');
        swal({
          title: 'Assigned',
          text: 'Technician assigned successfully.',
          type: 'success'
        }, function () {
          window.location.reload();
        });
      } else {
        PGS.error(res);
      }
    }, {
      error: function (xhr) {
        $btn.prop('disabled', false).text('Save Assignment');
        var err = xhr.responseJSON;
        PGS.error(err || { output: { response: { content: 'Error assigning technician.' } } });
      }
    });
  });

  // Comments
  $('#formAddComment').on('submit', function (e) {
    e.preventDefault();
    var body = $('#commentBody').val().trim();
    if (!body) return;
    var isInternal = $('#commentInternal').is(':checked') ? 1 : 0;

    var $btn = $('#btnSubmitComment');
    $btn.prop('disabled', true).text('Posting...');

    PGS.call('tsk_comment_add', { id: task.id, body: body, is_internal: isInternal }, function (res) {
      $btn.prop('disabled', false).text('Post Comment');
      if (res && res.output && res.output.valid) {
        var c = res.output.response;
        $('#commentBody').val('');
        $('#noCommentsMsg').remove();

        var commentHtml = '<div class="p-3 mb-2 rounded border ' + (c.is_internal ? 'border-warning' : 'border-light') + '" style="background:' + (c.is_internal ? '#fffdf0' : '#f8f9fa') + ';">' +
          '<div class="d-flex justify-content-between align-items-center mb-1">' +
            '<span class="font-weight-bold text-dark small">' + PGS.esc(c.author_nickname || 'You') +
              (c.is_internal ? ' <span class="badge badge-warning text-dark ml-2">Internal Note</span>' : '') +
            '</span>' +
            '<span class="text-muted" style="font-size:11px;">Just now</span>' +
          '</div>' +
          '<div class="text-dark small" style="white-space:pre-wrap;">' + PGS.esc(c.body) + '</div>' +
        '</div>';

        $('#commentsList').append(commentHtml);
      } else {
        PGS.error(res);
      }
    }, {
      error: function (xhr) {
        $btn.prop('disabled', false).text('Post Comment');
        PGS.error(xhr.responseJSON || { output: { response: { content: 'Error posting comment.' } } });
      }
    });
  });

  // Attachments upload
  $('#btnUploadAttachment').on('click', function () {
    $('#fileAttachmentInput').click();
  });

  $('#fileAttachmentInput').on('change', function () {
    var file = this.files[0];
    if (!file) return;

    if (file.size > 10 * 1024 * 1024) {
      swal('File too large', 'Maximum attachment size is 10 MB.', 'warning');
      return;
    }

    var formData = new FormData();
    formData.append('op', 'tsk_attach_upload');
    formData.append('task_id', task.id);
    formData.append('file', file);
    formData.append('csrf_token', PGS.csrf());

    PGS.upload('tsk_attach_upload', formData, function (res) {
      if (res && res.output && res.output.valid) {
        swal('Uploaded', 'Attachment uploaded successfully.', 'success');
        window.location.reload();
      } else {
        PGS.error(res);
      }
    });
  });

  // Attachment delete
  $(document).on('click', '.btnDeleteAtt', function () {
    var attId = $(this).data('id');
    PGS.confirm('Delete Attachment', 'Are you sure you want to remove this file?', function () {
      PGS.call('tsk_attach_delete', { id: attId }, function (res) {
        if (res && res.output && res.output.valid) {
          $('#att-' + attId).remove();
        } else {
          PGS.error(res);
        }
      });
    });
  });

  // Soft delete task
  $('#btnDeleteTask').on('click', function () {
    PGS.confirm('Delete Task', 'Are you sure you want to delete this task? This cannot be undone.', function () {
      PGS.call('tsk_delete', { id: task.id }, function (res) {
        if (res && res.output && res.output.valid) {
          swal({
            title: 'Deleted',
            text: 'Task has been deleted.',
            type: 'success'
          }, function () {
            window.location.href = 'tasks.php';
          });
        } else {
          PGS.error(res);
        }
      });
    });
  });
});
