$(document).ready(function () {
  var allServices = [];

  function loadServices() {
    var f = {
      category: $('#filterCategory').val() || '',
      active: $('#filterActive').val() || '',
      search: $('#filterSearch').val() || ''
    };

    PGS.call('svc_list', f, function (resp) {
      allServices = Array.isArray(resp) ? resp : (resp.data || []);
      renderTable(allServices);
      populateCategoryFilter(allServices);
    });
  }

  function populateCategoryFilter(services) {
    var categories = {};
    services.forEach(function (s) {
      if (s.category) categories[s.category] = true;
    });
    var current = $('#filterCategory').val();
    var html = '<option value="">All Categories</option>';
    Object.keys(categories).sort().forEach(function (c) {
      html += '<option value="' + PGS.esc(c) + '"' + (current === c ? ' selected' : '') + '>' + PGS.esc(c) + '</option>';
    });
    $('#filterCategory').html(html);
  }

  function renderTable(services) {
    var tbody = $('#servicesTable tbody');
    tbody.empty();

    if (!services || services.length === 0) {
      tbody.append('<tr><td colspan="8" class="text-center text-muted py-4">No services found</td></tr>');
      return;
    }

    services.forEach(function (s) {
      var priceDisplay = s.price_unit === 'quote'
        ? '<span class="text-muted">Quote</span>'
        : PGS.money(s.price_amount, s.currency) + ' <small class="text-muted">/' + PGS.esc(s.price_unit) + '</small>';

      var activeBadge = s.is_active == 1
        ? '<span class="badge badge-success" style="background:#0B0F19;">Active</span>'
        : '<span class="badge badge-secondary" style="background:#64748b;">Inactive</span>';

      var publicBadge = s.is_public == 1
        ? '<span class="badge badge-info" style="background:var(--brand);">Public</span>'
        : '<span class="badge badge-light">Internal</span>';

      var actions = '';
      if (window.PGS_SERVICES && window.PGS_SERVICES.canManage) {
        actions = '<button class="btn btn-sm btn-outline-dark btn-edit mr-1" data-id="' + s.id + '" title="Edit"><i class="fa fa-pencil"></i></button>' +
                  '<button class="btn btn-sm btn-outline-danger btn-delete" data-id="' + s.id + '" title="Delete"><i class="fa fa-trash"></i></button>';
      } else {
        actions = '<button class="btn btn-sm btn-outline-dark btn-view" data-id="' + s.id + '" title="View"><i class="fa fa-eye"></i></button>';
      }

      var tr = '<tr>' +
        '<td><strong>' + PGS.esc(s.code) + '</strong></td>' +
        '<td><div><strong>' + PGS.esc(s.name_en) + '</strong></div><small class="text-muted">' + PGS.esc(s.name_es) + '</small></td>' +
        '<td><span class="pgs-chip">' + PGS.esc(s.category) + '</span></td>' +
        '<td>' + priceDisplay + '</td>' +
        '<td>' + s.lead_time_days + ' d</td>' +
        '<td>' + publicBadge + '</td>' +
        '<td>' + activeBadge + '</td>' +
        '<td>' + actions + '</td>' +
        '</tr>';

      tbody.append(tr);
    });

  }

  // Filter events
  $('#filterCategory, #filterActive').on('change', function () {
    loadServices();
  });
  $('#filterSearch').on('keyup', function () {
    loadServices();
  });

  // Open modal for new service
  $('#btnNewService').on('click', function () {
    $('#serviceForm')[0].reset();
    $('#serviceId').val('');
    $('#serviceCode').prop('readonly', false);
    $('#serviceUnitIds').val([]).trigger('change');
    $('#serviceModalLabel').text('New Service');
    $('#priceUnit').trigger('change');
    $('#serviceModal').modal('show');
  });

  // Price unit toggle
  $('#priceUnit').on('change', function () {
    var val = $(this).val();
    if (val === 'quote') {
      $('#priceAmount').val('').prop('disabled', true).prop('required', false);
    } else {
      $('#priceAmount').prop('disabled', false).prop('required', true);
    }
  });

  // Edit / View
  $(document).on('click', '.btn-edit, .btn-view', function () {
    var id = $(this).data('id');
    PGS.call('svc_get', { id: id }, function (resp) {
      var s = resp.output ? resp.output.response : resp;
      if (!s) return;

      $('#serviceId').val(s.id);
      $('#serviceCode').val(s.code).prop('readonly', true);
      $('#serviceCategory').val(s.category);
      $('#serviceNameEn').val(s.name_en);
      $('#serviceNameEs').val(s.name_es);
      $('#serviceDescEn').val(s.description_en || '');
      $('#serviceDescEs').val(s.description_es || '');
      $('#serviceCondEn').val(s.conditions_en || '');
      $('#serviceCondEs').val(s.conditions_es || '');
      $('#priceUnit').val(s.price_unit).trigger('change');
      $('#priceAmount').val(s.price_amount !== null ? s.price_amount : '');
      $('#currency').val(s.currency);
      $('#leadTimeDays').val(s.lead_time_days);
      $('#sortOrder').val(s.sort_order);
      $('#isPublic').prop('checked', s.is_public == 1);
      $('#isActive').prop('checked', s.is_active == 1);
      $('#defaultAssignee').val(s.default_assignee_user_id || '');

      var unitIds = s.unit_ids || [];
      $('#serviceUnitIds').val(unitIds).trigger('change');

      var manage = !!(window.PGS_SERVICES && window.PGS_SERVICES.canManage);
      $('#serviceModalLabel').text((manage ? 'Edit Service: ' : 'Service: ') + s.code);
      $('#serviceForm :input').not('[data-dismiss]').prop('disabled', !manage);
      $('#serviceForm .pgs-internal-field').toggle(manage);
      $('#serviceModal').modal('show');
    });
  });

  // Save
  $('#serviceForm').on('submit', function (e) {
    e.preventDefault();
    if (!(window.PGS_SERVICES && window.PGS_SERVICES.canManage)) {
      return;
    }
    var data = {
      id: $('#serviceId').val() || 0,
      code: $('#serviceCode').val(),
      category: $('#serviceCategory').val(),
      name_en: $('#serviceNameEn').val(),
      name_es: $('#serviceNameEs').val(),
      description_en: $('#serviceDescEn').val(),
      description_es: $('#serviceDescEs').val(),
      conditions_en: $('#serviceCondEn').val(),
      conditions_es: $('#serviceCondEs').val(),
      price_unit: $('#priceUnit').val(),
      price_amount: $('#priceAmount').val(),
      currency: $('#currency').val(),
      lead_time_days: $('#leadTimeDays').val(),
      sort_order: $('#sortOrder').val(),
      is_public: $('#isPublic').is(':checked') ? 1 : 0,
      is_active: $('#isActive').is(':checked') ? 1 : 0,
      default_assignee_user_id: $('#defaultAssignee').val() || null,
      unit_ids: $('#serviceUnitIds').val() || []
    };

    PGS.call('svc_save', data, function (resp) {
      if (resp.output && resp.output.valid) {
        $('#serviceModal').modal('hide');
        swal('Success', 'Service saved successfully.', 'success');
        loadServices();
      } else {
        PGS.error(resp);
      }
    });
  });

  // Delete
  $(document).on('click', '.btn-delete', function () {
    var id = $(this).data('id');
    PGS.confirm('Delete Service', 'Are you sure you want to remove this service from the catalog?', function () {
      PGS.call('svc_delete', { id: id }, function (resp) {
        if (resp.output && resp.output.valid) {
          swal('Deleted', 'Service removed from catalog.', 'success');
          loadServices();
        } else {
          PGS.error(resp);
        }
      });
    });
  });

  // Initialize select2 if available
  if ($.fn.select2) {
    $('#serviceUnitIds').select2({
      dropdownParent: $('#serviceModal'),
      width: '100%'
    });
  }

  // Initial load
  loadServices();
});
