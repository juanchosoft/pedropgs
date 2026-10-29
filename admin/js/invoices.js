/**
 * Invoices Controller
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

  function formatMoney(n) {
    return '$' + parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  var filterUnit = document.getElementById('filterUnit');
  var filterStatus = document.getElementById('filterStatus');
  var filterFromDate = document.getElementById('filterFromDate');
  var filterToDate = document.getElementById('filterToDate');
  var filterSearch = document.getElementById('filterSearch');
  var btnSearchInvoices = document.getElementById('btnSearchInvoices');
  var invoicesRows = document.getElementById('invoicesRows');
  var invoicesCountInfo = document.getElementById('invoicesCountInfo');
  var btnPrevPage = document.getElementById('btnPrevPage');
  var btnNextPage = document.getElementById('btnNextPage');

  var currentPage = 1;
  var totalPages = 1;

  function loadInvoices(page) {
    currentPage = page || 1;
    if (!invoicesRows) return;

    invoicesRows.innerHTML = '<tr><td colspan="' + (window.CAN_MANAGE_INV ? '10' : '9') + '" class="text-center py-5 text-muted"><i class="fa fa-spinner fa-spin mr-2"></i>Loading invoices...</td></tr>';

    var params = new URLSearchParams({
      op: 'inv_list',
      page: currentPage,
      per_page: 25
    });

    if (filterUnit && filterUnit.value) params.append('unidad_id', filterUnit.value);
    if (filterStatus && filterStatus.value) params.append('payment_status', filterStatus.value);
    if (filterFromDate && filterFromDate.value) params.append('from_date', filterFromDate.value);
    if (filterToDate && filterToDate.value) params.append('to_date', filterToDate.value);
    if (filterSearch && filterSearch.value) params.append('q', filterSearch.value);

    fetch('admin/ajax/rqst.php?' + params.toString())
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (!data || !data.ok) {
          invoicesRows.innerHTML = '<tr><td colspan="' + (window.CAN_MANAGE_INV ? '10' : '9') + '" class="text-center py-4 text-danger">' +
            (data && data.error ? data.error : 'Failed to load invoices.') + '</td></tr>';
          return;
        }

        renderInvoicesTable(data.data || {});
      })
      .catch(function(err) {
        invoicesRows.innerHTML = '<tr><td colspan="' + (window.CAN_MANAGE_INV ? '10' : '9') + '" class="text-center py-4 text-danger">Network error loading invoices.</td></tr>';
      });
  }

  function renderInvoicesTable(result) {
    var items = result.items || [];
    var total = result.total || 0;
    totalPages = result.pages || 1;

    if (invoicesCountInfo) {
      invoicesCountInfo.innerText = 'Showing ' + items.length + ' of ' + total + ' invoice(s) (Page ' + currentPage + ' of ' + totalPages + ')';
    }
    if (btnPrevPage) btnPrevPage.disabled = currentPage <= 1;
    if (btnNextPage) btnNextPage.disabled = currentPage >= totalPages;

    if (!items.length) {
      invoicesRows.innerHTML = '<tr><td colspan="' + (window.CAN_MANAGE_INV ? '10' : '9') + '" class="text-center py-5 text-muted">' +
        '<i class="fa fa-info-circle fa-2x mb-2 d-block text-secondary"></i>No invoices found matching criteria.</td></tr>';
      return;
    }

    var html = '';
    items.forEach(function(item) {
      var statusBadge = 'badge-secondary';
      if (item.payment_status === 'pending') statusBadge = 'badge-warning';
      else if (item.payment_status === 'partially_paid') statusBadge = 'badge-info';
      else if (item.payment_status === 'paid') statusBadge = 'badge-success';
      else if (item.payment_status === 'overdue') statusBadge = 'badge-danger';
      else if (item.payment_status === 'void') statusBadge = 'badge-secondary';

      var actions = '';
      if (item.pdf_path) {
        actions += '<a href="admin/ajax/file_download.php?type=invoice&id=' + item.id + '" class="btn btn-sm btn-outline-danger mr-1" title="Download PDF" target="_blank"><i class="fa fa-file-pdf-o"></i></a>';
      }

      if (window.CAN_MANAGE_INV && item.payment_status !== 'void') {
        if (item.payment_status !== 'paid') {
          actions += '<button class="btn btn-sm btn-outline-success mr-1 btn-pay-inv" data-id="' + item.id + '" data-num="' + escapeHtml(item.invoice_number) + '" data-amount="' + item.amount + '" data-paid="' + item.amount_paid + '" title="Register Payment"><i class="fa fa-money"></i></button>';
        }
        actions += '<button class="btn btn-sm btn-outline-secondary mr-1 btn-edit-inv" data-id="' + item.id + '" title="Edit Invoice"><i class="fa fa-pencil"></i></button>';
        actions += '<button class="btn btn-sm btn-outline-danger btn-void-inv" data-id="' + item.id + '" title="Void Invoice"><i class="fa fa-ban"></i></button>';
      }

      html += '<tr>' +
        '<td><strong>#' + escapeHtml(item.invoice_number) + '</strong></td>' +
        '<td>' + escapeHtml(item.unidad_nombre) + '</td>' +
        '<td>' + (item.location_label ? escapeHtml(item.location_label) : '<span class="text-muted">-</span>') + '</td>' +
        '<td>' + escapeHtml(item.issue_date) + '</td>' +
        '<td>' + (item.due_date ? escapeHtml(item.due_date) : '<span class="text-muted">-</span>') + '</td>' +
        '<td><strong>' + formatMoney(item.amount) + '</strong></td>' +
        '<td>' + formatMoney(item.amount_paid) + '</td>' +
        '<td><span class="badge ' + statusBadge + '">' + escapeHtml(item.payment_status) + '</span></td>';

      if (window.CAN_MANAGE_INV) {
        html += '<td><span class="badge badge-light border">' + item.linked_expenses_count + ' expense(s)</span></td>';
      }

      html += '<td class="text-right">' + (actions || '-') + '</td></tr>';
    });

    invoicesRows.innerHTML = html;

    // Attach listeners
    invoicesRows.querySelectorAll('.btn-pay-inv').forEach(function(btn) {
      btn.addEventListener('click', function() {
        openPaymentModal(
          this.getAttribute('data-id'),
          this.getAttribute('data-num'),
          this.getAttribute('data-amount'),
          this.getAttribute('data-paid')
        );
      });
    });

    invoicesRows.querySelectorAll('.btn-edit-inv').forEach(function(btn) {
      btn.addEventListener('click', function() { editInvoice(this.getAttribute('data-id')); });
    });

    invoicesRows.querySelectorAll('.btn-void-inv').forEach(function(btn) {
      btn.addEventListener('click', function() { promptVoidInvoice(this.getAttribute('data-id')); });
    });
  }

  // --- NEW & EDIT INVOICE MODAL ---
  var formInvoice = document.getElementById('formInvoice');
  var btnNewInvoice = document.getElementById('btnNewInvoice');
  var modalInvoiceForm = $('#modalInvoiceForm');
  var modalInvoiceTitle = document.getElementById('modalInvoiceTitle');
  var invUnit = document.getElementById('invUnit');
  var linkExpensesSection = document.getElementById('linkExpensesSection');
  var pendingExpensesCheckboxList = document.getElementById('pendingExpensesCheckboxList');
  var pdfRequiredStar = document.getElementById('pdfRequiredStar');
  var invCurrentPdfHelp = document.getElementById('invCurrentPdfHelp');

  if (btnNewInvoice) {
    btnNewInvoice.addEventListener('click', function() {
      if (formInvoice) {
        formInvoice.reset();
        document.getElementById('invId').value = '';
        document.getElementById('invRowVersion').value = '1';
        document.getElementById('invIssueDate').value = new Date().toISOString().slice(0, 10);
        if (pdfRequiredStar) pdfRequiredStar.style.display = 'inline';
        if (invCurrentPdfHelp) invCurrentPdfHelp.innerText = 'Must be a valid PDF file up to 15MB.';
        if (linkExpensesSection) linkExpensesSection.style.display = 'none';
      }
      if (modalInvoiceTitle) modalInvoiceTitle.innerHTML = '<i class="fa fa-upload text-danger mr-2"></i>Upload Invoice';
      modalInvoiceForm.modal('show');
    });
  }

  // Load pending expenses when unit changes in invoice form
  if (invUnit) {
    invUnit.addEventListener('change', function() {
      var unitId = invUnit.value;
      if (!unitId || !linkExpensesSection || !pendingExpensesCheckboxList) return;

      pendingExpensesCheckboxList.innerHTML = '<span class="text-muted"><i class="fa fa-spinner fa-spin mr-1"></i>Checking pending expenses...</span>';
      linkExpensesSection.style.display = 'block';

      fetch('admin/ajax/rqst.php?op=exp_pending_by_unit&unidad_id=' + encodeURIComponent(unitId))
        .then(function(res) { return res.json(); })
        .then(function(data) {
          var items = (data && data.ok && data.data) ? data.data : [];
          if (!items.length) {
            pendingExpensesCheckboxList.innerHTML = '<span class="text-muted">No pending expenses found for this property.</span>';
            return;
          }

          var html = '';
          items.forEach(function(exp) {
            html += '<div class="form-check py-1">' +
              '<input class="form-check-input" type="checkbox" name="link_expense_ids[]" value="' + exp.id + '" id="chkExp' + exp.id + '">' +
              '<label class="form-check-label" for="chkExp' + exp.id + '">' +
              '<strong>' + escapeHtml(exp.code) + '</strong> (' + escapeHtml(exp.purchase_date) + ') - ' + formatMoney(exp.total_amount) + ' - ' + escapeHtml(exp.vendor || 'No vendor') +
              '</label>' +
              '</div>';
          });
          pendingExpensesCheckboxList.innerHTML = html;
        })
        .catch(function(err) {
          pendingExpensesCheckboxList.innerHTML = '<span class="text-danger">Error loading pending expenses.</span>';
        });
    });
  }

  function editInvoice(id) {
    fetch('admin/ajax/rqst.php?op=inv_get&id=' + encodeURIComponent(id))
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (!data || !data.ok || !data.data) {
          PGS.notify('Failed to load invoice details');
          return;
        }
        var inv = data.data;
        document.getElementById('invId').value = inv.id;
        document.getElementById('invRowVersion').value = inv.row_version || 1;
        document.getElementById('invNumber').value = inv.invoice_number;
        document.getElementById('invUnit').value = inv.unidad_id;
        document.getElementById('invLocation').value = inv.location_label || '';
        document.getElementById('invAmount').value = inv.amount;
        document.getElementById('invIssueDate').value = inv.issue_date;
        document.getElementById('invDueDate').value = inv.due_date || '';
        document.getElementById('invNotes').value = inv.notes || '';

        if (pdfRequiredStar) pdfRequiredStar.style.display = 'none';
        if (invCurrentPdfHelp) {
          invCurrentPdfHelp.innerText = 'Current PDF: ' + (inv.pdf_original_name || 'attached') + '. Upload new file only to replace.';
        }
        if (linkExpensesSection) linkExpensesSection.style.display = 'none';

        if (modalInvoiceTitle) modalInvoiceTitle.innerHTML = '<i class="fa fa-pencil text-danger mr-2"></i>Edit Invoice (#' + escapeHtml(inv.invoice_number) + ')';
        modalInvoiceForm.modal('show');
      })
      .catch(function(err) {
        PGS.notify('Error loading invoice');
      });
  }

  if (formInvoice) {
    formInvoice.addEventListener('submit', function(e) {
      e.preventDefault();

      var formData = new FormData(formInvoice);
      if (!formData.get('csrf_token')) formData.append('csrf_token', getCsrfToken());

      var btnSave = document.getElementById('btnSaveInvoice');
      if (btnSave) {
        btnSave.disabled = true;
        btnSave.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Saving...';
      }

      fetch('admin/ajax/rqst.php?op=inv_save', {
        method: 'POST',
        body: formData
      })
        .then(function(res) { return res.json(); })
        .then(function(data) {
          if (btnSave) {
            btnSave.disabled = false;
            btnSave.innerHTML = 'Save Invoice';
          }

          if (data && data.ok) {
            modalInvoiceForm.modal('hide');
            if (window.Swal) Swal.fire({ icon: 'success', title: 'Invoice saved successfully', timer: 1500, showConfirmButton: false });
            loadInvoices(currentPage);
          } else {
            var msg = data && data.error ? data.error : 'Failed to save invoice';
            if (window.Swal) Swal.fire('Error', msg, 'error');
            else PGS.notify(msg);
          }
        })
        .catch(function(err) {
          if (btnSave) {
            btnSave.disabled = false;
            btnSave.innerHTML = 'Save Invoice';
          }
          if (window.Swal) Swal.fire('Error', 'Network error saving invoice', 'error');
          else PGS.notify('Network error saving invoice');
        });
    });
  }

  // --- REGISTER PAYMENT ---
  var modalPaymentForm = $('#modalPaymentForm');
  var formPayment = document.getElementById('formPayment');

  function openPaymentModal(id, num, amount, paid) {
    var remaining = Math.max(0, (parseFloat(amount) || 0) - (parseFloat(paid) || 0));
    document.getElementById('payInvId').value = id;
    document.getElementById('payInvNumber').innerText = '#' + num;
    document.getElementById('payRemaining').innerText = formatMoney(remaining);
    document.getElementById('payAmount').value = remaining.toFixed(2);
    document.getElementById('payDate').value = new Date().toISOString().slice(0, 10);
    modalPaymentForm.modal('show');
  }

  if (formPayment) {
    formPayment.addEventListener('submit', function(e) {
      e.preventDefault();

      var formData = new FormData(formPayment);
      if (!formData.get('csrf_token')) formData.append('csrf_token', getCsrfToken());

      var btnSub = document.getElementById('btnSubmitPayment');
      if (btnSub) {
        btnSub.disabled = true;
        btnSub.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Registering...';
      }

      fetch('admin/ajax/rqst.php?op=inv_payment', {
        method: 'POST',
        body: formData
      })
        .then(function(res) { return res.json(); })
        .then(function(data) {
          if (btnSub) {
            btnSub.disabled = false;
            btnSub.innerHTML = 'Register Payment';
          }

          if (data && data.ok) {
            modalPaymentForm.modal('hide');
            if (window.Swal) Swal.fire({ icon: 'success', title: 'Payment registered', timer: 1500, showConfirmButton: false });
            loadInvoices(currentPage);
          } else {
            var msg = data && data.error ? data.error : 'Failed to register payment';
            if (window.Swal) Swal.fire('Error', msg, 'error');
            else PGS.notify(msg);
          }
        })
        .catch(function(err) {
          if (btnSub) {
            btnSub.disabled = false;
            btnSub.innerHTML = 'Register Payment';
          }
          if (window.Swal) Swal.fire('Error', 'Network error', 'error');
          else PGS.notify('Network error');
        });
    });
  }

  // --- VOID INVOICE ---
  function promptVoidInvoice(id) {
    if (!window.Swal) {
      var r = prompt('Reason for voiding invoice:');
      if (r) voidInvoice(id, r);
      return;
    }

    Swal.fire({
      title: 'Void invoice?',
      text: 'This will void the invoice and unlink any associated expenses.',
      input: 'text',
      inputPlaceholder: 'Reason for voiding...',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef233c',
      confirmButtonText: 'Yes, void'
    }).then(function(result) {
      if (result.isConfirmed) {
        voidInvoice(id, result.value || 'Voided by user');
      }
    });
  }

  function voidInvoice(id, reason) {
    var formData = new FormData();
    formData.append('id', id);
    formData.append('reason', reason);
    formData.append('csrf_token', getCsrfToken());

    fetch('admin/ajax/rqst.php?op=inv_void', {
      method: 'POST',
      body: formData
    })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (data && data.ok) {
          if (window.Swal) Swal.fire({ icon: 'success', title: 'Invoice voided', timer: 1500, showConfirmButton: false });
          loadInvoices(currentPage);
        } else {
          var msg = data && data.error ? data.error : 'Failed to void invoice';
          if (window.Swal) Swal.fire('Error', msg, 'error');
          else PGS.notify(msg);
        }
      })
      .catch(function(err) {
        if (window.Swal) Swal.fire('Error', 'Network error', 'error');
        else PGS.notify('Network error');
      });
  }

  var btnPrefillAi = document.getElementById('btnPrefillAi');
  if (btnPrefillAi) {
    btnPrefillAi.addEventListener('click', function() {
      if (window.PGS_IA) {
        window.PGS_IA.open({
          prefill: 'Read the attached invoice and prepare it for upload.',
          attach: true
        });
      } else if (window.openAiChatWidget) {
        window.openAiChatWidget('Extract invoice details from PDF');
      } else if (window.Swal) {
        Swal.fire({
          icon: 'info',
          title: 'AI Assistant',
          text: 'Upload your invoice PDF in the AI Assistant chat widget to automatically prefill this invoice.'
        });
      } else {
        PGS.notify('Upload your invoice PDF in the AI Assistant chat widget to prefill.');
      }
    });
  }

  // --- EVENTS & INIT ---
  if (filterUnit) filterUnit.addEventListener('change', function() { loadInvoices(1); });
  if (filterStatus) filterStatus.addEventListener('change', function() { loadInvoices(1); });
  if (filterFromDate) filterFromDate.addEventListener('change', function() { loadInvoices(1); });
  if (filterToDate) filterToDate.addEventListener('change', function() { loadInvoices(1); });
  if (btnSearchInvoices) btnSearchInvoices.addEventListener('click', function() { loadInvoices(1); });
  if (filterSearch) {
    filterSearch.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') { e.preventDefault(); loadInvoices(1); }
    });
  }

  if (btnPrevPage) btnPrevPage.addEventListener('click', function() { if (currentPage > 1) loadInvoices(currentPage - 1); });
  if (btnNextPage) btnNextPage.addEventListener('click', function() { if (currentPage < totalPages) loadInvoices(currentPage + 1); });

  // Initial load
  loadInvoices(1);

})();
