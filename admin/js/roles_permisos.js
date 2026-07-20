$(function () {
  initRolesPermisos();
});

var ROLES = {
  catalog: {},
  catalogHasIds: false,

  loadList: function () {
    UTIL.cursorBusy();
    $.ajax({
      data: { op: 'roleslist' },
      type: 'POST',
      dataType: 'json',
      url: 'admin/ajax/rqst.php',
      success: function (data) {
        UTIL.cursorNormal();
        var tbody = $('#tablaRoles tbody');
        tbody.empty();
        if (!data || !data.output || !data.output.valid) {
          swal('Error', (data && data.output && data.output.response && data.output.response.content) || 'Could not load roles', 'error');
          return;
        }
        var rows = data.output.response || [];
        if (!rows.length) {
          tbody.append('<tr><td colspan="5" class="text-muted text-center">No roles found.</td></tr>');
          return;
        }
        rows.forEach(function (r) {
          var sys = parseInt(r.is_system, 10) === 1 ? 'Yes' : 'No';
          var actions = '';
          if (window.PGS_ROLES_CAN_MANAGE) {
            actions += '<button type="button" class="btn btn-sm btn-outline-primary mr-1" onclick="ROLES.edit(' + r.id + ')"><i class="fa fa-pencil"></i></button>';
            if (parseInt(r.is_system, 10) !== 1) {
              actions += '<button type="button" class="btn btn-sm btn-outline-danger" onclick="ROLES.remove(' + r.id + ')"><i class="fa fa-trash"></i></button>';
            }
          }
          tbody.append(
            '<tr>' +
              '<td><b>' + $('<div>').text(r.name || '').html() + '</b></td>' +
              '<td>' + (r.permisos_count || 0) + '</td>' +
              '<td>' + (r.usuarios_count || 0) + '</td>' +
              '<td>' + sys + '</td>' +
              '<td>' + actions + '</td>' +
            '</tr>'
          );
        });
      },
      error: function (xhr) {
        UTIL.cursorNormal();
        var msg = 'Could not load roles';
        try {
          var parsed = JSON.parse(xhr.responseText);
          if (parsed && parsed.output && parsed.output.response && parsed.output.response.content) {
            msg = parsed.output.response.content;
          }
        } catch (e) {}
        swal('Error', msg + ' (HTTP ' + xhr.status + ')', 'error');
      }
    });
  },

  loadCatalog: function (done) {
    UTIL.callAjaxRqstPOST({ op: 'rolepermissionscatalog' }, function (data) {
      if (!data.output || !data.output.valid) {
        swal('Error', 'Permission catalog failed to load', 'error');
        return;
      }
      ROLES.catalog = data.output.response || {};
      ROLES.catalogHasIds = false;
      var html = '';
      Object.keys(ROLES.catalog).forEach(function (mod) {
        html += '<div class="perm-module"><h6>' + $('<div>').text(mod).html() + '</h6>';
        (ROLES.catalog[mod] || []).forEach(function (p) {
          var id = parseInt(p.id, 10) || 0;
          if (id > 0) ROLES.catalogHasIds = true;
          html +=
            '<label class="perm-item">' +
            '<input type="checkbox" class="perm-check" value="' + id + '"> ' +
            $('<div>').text(p.name || p.key || '').html() +
            ' <small class="text-muted">(' + $('<div>').text(p.key || '').html() + ')</small>' +
            '</label>';
        });
        html += '</div>';
      });
      $('#permCatalog').html(html);
      if (typeof done === 'function') done();
    });
  },

  openModal: function (role) {
    role = role || {};
    $('#role_id').val(role.id || '');
    $('#role_name').val(role.name || '');
    $('#role_description').val(role.description || '');
    $('#modalRoleTitle').text(role.id ? 'Edit Role' : 'New Role');
    ROLES.loadCatalog(function () {
      $('.perm-check').prop('checked', false);
      (role.permission_ids || []).forEach(function (id) {
        $('.perm-check[value="' + id + '"]').prop('checked', true);
      });
      $('#modalRole').modal({ backdrop: 'static', keyboard: false });
    });
  },

  edit: function (id) {
    UTIL.cursorBusy();
    UTIL.callAjaxRqstPOST({ op: 'roleget', id: id }, function (data) {
      UTIL.cursorNormal();
      if (!data.output || !data.output.valid) {
        swal('Error', 'Could not load role', 'error');
        return;
      }
      ROLES.openModal(data.output.response);
    });
  },

  remove: function (id) {
    Swal.fire({
      title: 'Delete this role?',
      text: 'This cannot be undone.',
      type: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes',
      cancelButtonText: 'Cancel'
    }).then(function (result) {
      if (!result.value) return;
      UTIL.cursorBusy();
      UTIL.callAjaxRqstPOST({ op: 'roledelete', id: id }, function (data) {
        UTIL.cursorNormal();
        if (data.output && data.output.valid) {
          ROLES.loadList();
        } else {
          swal('Error', (data.output && data.output.response && data.output.response.content) || 'Delete failed', 'error');
        }
      });
    });
  },

  save: function () {
    if (!window.PGS_ROLES_CAN_MANAGE) return;
    var name = $.trim($('#role_name').val() || '');
    if (!name) {
      swal('Warning', 'Name is required.', 'warning');
      return;
    }
    if (!ROLES.catalogHasIds) {
      swal('Error', 'Permission catalog has no IDs. Reload the page and try again.', 'error');
      return;
    }
    var ids = [];
    $('.perm-check:checked').each(function () {
      var v = parseInt($(this).val(), 10);
      if (v > 0) ids.push(v);
    });
    var roleId = $('#role_id').val();
    if (roleId && ids.length === 0) {
      swal('Warning', 'Select at least one permission before saving an existing role (anti-wipe protection).', 'warning');
      return;
    }
    var q = {
      op: 'rolesave',
      id: roleId,
      name: name,
      description: $('#role_description').val(),
      permission_ids: ids.join(',')
    };
    UTIL.cursorBusy();
    UTIL.callAjaxRqstPOST(q, function (data) {
      UTIL.cursorNormal();
      if (data.output && data.output.valid) {
        $('#modalRole').modal('hide');
        ROLES.loadList();
        swal('Saved', '', 'success');
      } else {
        swal('Error', (data.output && data.output.response && data.output.response.content) || 'Save failed', 'error');
      }
    });
  }
};

function initRolesPermisos() {
  ROLES.loadList();
  $('#btnNewRole').on('click', function () { ROLES.openModal({}); });
  $('#btnSaveRole').on('click', ROLES.save);
  $('#btnCheckAll').on('click', function () { $('.perm-check').prop('checked', true); });
  $('#btnUncheckAll').on('click', function () { $('.perm-check').prop('checked', false); });
}
