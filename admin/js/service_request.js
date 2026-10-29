$(function () {
  'use strict';

  var currentServices = [];
  var selectedService = null;

  var $selectProperty = $('#selectProperty');
  var $servicesGrid = $('#servicesGrid');
  var $cardForm = $('#cardRequestForm');

  loadServicesForUnit($selectProperty.val());

  $selectProperty.on('change', function () {
    loadServicesForUnit($(this).val());
  });

  function loadServicesForUnit(unitId) {
    if (!unitId) {
      $servicesGrid.html('<div class="col-12 text-muted py-3">Please select a property.</div>');
      return;
    }

    $servicesGrid.html('<div class="col-12 text-center py-4 text-muted"><i class="fa fa-spinner fa-spin mr-1"></i> Loading offerings...</div>');
    $cardForm.hide();
    selectedService = null;

    PGS.call('svc_for_unit', { unit_id: unitId, lang: 'en' }, function (res) {
      var services = (res && res.output && res.output.response) ? res.output.response : (res || []);
      currentServices = services;
      renderServices(services);
    }, {
      error: function () {
        $servicesGrid.html('<div class="col-12 text-danger py-3">Failed to load services for this property.</div>');
      }
    });
  }

  function renderServices(services) {
    $servicesGrid.empty();
    if (!services || services.length === 0) {
      $servicesGrid.html('<div class="col-12 text-muted py-3">No active services offered for this property.</div>');
      $('#servicesCount').text('0 available');
      return;
    }

    $('#servicesCount').text(services.length + ' available');

    $.each(services, function (i, s) {
      var priceText = s.price_unit === 'quote' ? 'Upon Quote' : (PGS.money(s.price_amount, s.currency) + ' / ' + s.price_unit);
      var leadText = s.lead_time_days > 0 ? ('<i class="fa fa-clock-o mr-1"></i> ' + s.lead_time_days + 'd lead time') : '<i class="fa fa-bolt mr-1"></i> Same day / Immediate';

      var card = '<div class="col-md-4 col-sm-6 mb-4">' +
        '<div class="card h-100 p-3 shadow-sm border-0 service-card" data-id="' + s.id + '" style="border-radius:18px; transition:transform .2s, box-shadow .2s; cursor:pointer;">' +
          '<div class="d-flex justify-content-between align-items-start mb-2">' +
            '<span class="badge badge-light border text-muted">' + PGS.esc(s.category || 'General') + '</span>' +
            '<span class="font-weight-bold" style="color:var(--brand);">' + priceText + '</span>' +
          '</div>' +
          '<h5 class="font-weight-bold text-dark mb-1">' + PGS.esc(s.name) + '</h5>' +
          '<p class="text-muted small mb-3 flex-grow-1" style="min-height:38px;">' + PGS.esc(s.description || 'Standard service maintenance.') + '</p>' +
          '<div class="d-flex justify-content-between align-items-center pt-2 border-top">' +
            '<small class="text-muted font-weight-bold">' + leadText + '</small>' +
            '<button type="button" class="btn btn-sm btn-outline-danger btnSelectService" data-id="' + s.id + '">Select</button>' +
          '</div>' +
        '</div>' +
      '</div>';

      $servicesGrid.append(card);
    });

    $('.service-card, .btnSelectService').on('click', function (e) {
      var sid = $(this).data('id');
      selectService(sid);
    });
  }

  function selectService(serviceId) {
    var svc = null;
    $.each(currentServices, function (i, s) {
      if (parseInt(s.id, 10) === parseInt(serviceId, 10)) {
        svc = s;
        return false;
      }
    });

    if (!svc) return;
    selectedService = svc;

    $('.service-card').removeClass('border-danger shadow').addClass('border-0');
    $('.service-card[data-id="' + svc.id + '"]').addClass('border-danger shadow');

    $('#reqServiceId').val(svc.id);
    $('#selectedServiceCode').text(svc.code);
    $('#selectedServiceName').text(svc.name);

    var priceText = svc.price_unit === 'quote' ? 'Upon Quote' : (PGS.money(svc.price_amount, svc.currency) + ' / ' + svc.price_unit);
    $('#selectedServicePricing').text('Pricing: ' + priceText);

    if (svc.lead_time_days > 0) {
      $('#leadTimeNote').text('Requires ~' + svc.lead_time_days + ' days lead time.');
    } else {
      $('#leadTimeNote').text('');
    }

    $cardForm.slideDown(250);
    $('html, body').animate({
      scrollTop: $cardForm.offset().top - 80
    }, 400);
  }

  $('#btnChangeService').on('click', function () {
    selectedService = null;
    $cardForm.slideUp(200);
    $('.service-card').removeClass('border-danger shadow').addClass('border-0');
    $('html, body').animate({
      scrollTop: $servicesGrid.offset().top - 80
    }, 300);
  });

  $('#formSubmitRequest').on('submit', function (e) {
    e.preventDefault();
    if (!selectedService) {
      swal('Select Service', 'Please choose a service from the offerings above.', 'warning');
      return;
    }

    var data = {
      unidad_id: $selectProperty.val(),
      service_id: selectedService.id,
      location_label: $('#reqLocation').val().trim(),
      required_date: $('#reqDate').val(),
      priority: $('#reqPriority').val(),
      notes: $('#reqNotes').val().trim()
    };

    var $btn = $('#btnSubmitRequest');
    $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin mr-1"></i> Submitting...');

    PGS.call('svc_request_create', data, function (res) {
      $btn.prop('disabled', false).html('<i class="fa fa-paper-plane mr-1"></i> Submit Request');

      if (res && res.output && res.output.valid) {
        var resp = res.output.response;
        var code = resp.task_code;
        var warn = resp.warning ? ('\n\nNote: ' + resp.warning) : '';

        swal({
          title: 'Request Submitted!',
          text: 'Work order code: ' + code + warn,
          type: 'success',
          showCancelButton: true,
          confirmButtonText: 'View Task (' + code + ')',
          cancelButtonText: 'Submit Another'
        }, function (isConfirm) {
          if (isConfirm) {
            window.location.href = 'task_view.php?code=' + encodeURIComponent(code);
          } else {
            $('#formSubmitRequest')[0].reset();
            $cardForm.slideUp(200);
          }
        });
      } else {
        PGS.error(res);
      }
    }, {
      error: function (xhr) {
        $btn.prop('disabled', false).html('<i class="fa fa-paper-plane mr-1"></i> Submit Request');
        PGS.error(xhr.responseJSON || { output: { response: { content: 'Error submitting service request.' } } });
      }
    });
  });
});
