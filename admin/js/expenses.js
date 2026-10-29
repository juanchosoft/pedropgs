/**
 * Expenses Controller
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

  // --- EXPENSES LIST & PAGINATION ---
  var filterUnit = document.getElementById('filterUnit');
  var filterStatus = document.getElementById('filterStatus');
  var filterFromDate = document.getElementById('filterFromDate');
  var filterToDate = document.getElementById('filterToDate');
  var filterSearch = document.getElementById('filterSearch');
  var filterCreator = document.getElementById('filterCreator');
  var btnSearchExpenses = document.getElementById('btnSearchExpenses');
  var expensesRows = document.getElementById('expensesRows');
  var expensesCountInfo = document.getElementById('expensesCountInfo');
  var btnPrevPage = document.getElementById('btnPrevPage');
  var btnNextPage = document.getElementById('btnNextPage');

  var currentPage = 1;
  var totalPages = 1;

  function loadExpenses(page) {
    currentPage = page || 1;
    if (!expensesRows) return;

    expensesRows.innerHTML = '<tr><td colspan="' + (window.CAN_TOTALS_EXP ? '11' : '10') + '" class="text-center py-5 text-muted"><i class="fa fa-spinner fa-spin mr-2"></i>Loading expenses...</td></tr>';

    var params = new URLSearchParams({
      op: 'exp_list',
      page: currentPage,
      per_page: 25
    });

    if (filterUnit && filterUnit.value) params.append('unidad_id', filterUnit.value);
    if (filterStatus && filterStatus.value) params.append('billing_status', filterStatus.value);
    if (filterFromDate && filterFromDate.value) params.append('from_date', filterFromDate.value);
    if (filterToDate && filterToDate.value) params.append('to_date', filterToDate.value);
    if (filterSearch && filterSearch.value) params.append('q', filterSearch.value);
    if (filterCreator && filterCreator.value) params.append('created_by_user_id', filterCreator.value);

    fetch('admin/ajax/rqst.php?' + params.toString())
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (!data || !data.ok) {
          expensesRows.innerHTML = '<tr><td colspan="' + (window.CAN_TOTALS_EXP ? '11' : '10') + '" class="text-center py-4 text-danger">' +
            (data && data.error ? data.error : 'Failed to load expenses.') + '</td></tr>';
          return;
        }

        renderExpensesTable(data.data || {});
      })
      .catch(function(err) {
        expensesRows.innerHTML = '<tr><td colspan="' + (window.CAN_TOTALS_EXP ? '11' : '10') + '" class="text-center py-4 text-danger">Network error loading expenses.</td></tr>';
      });
  }

  function renderExpensesTable(result) {
    var items = result.items || [];
    var total = result.total || 0;
    totalPages = result.pages || 1;

    if (expensesCountInfo) {
      expensesCountInfo.innerText = 'Showing ' + items.length + ' of ' + total + ' expense(s) (Page ' + currentPage + ' of ' + totalPages + ')';
    }
    if (btnPrevPage) btnPrevPage.disabled = currentPage <= 1;
    if (btnNextPage) btnNextPage.disabled = currentPage >= totalPages;

    if (!items.length) {
      expensesRows.innerHTML = '<tr><td colspan="' + (window.CAN_TOTALS_EXP ? '11' : '10') + '" class="text-center py-5 text-muted">' +
        '<i class="fa fa-info-circle fa-2x mb-2 d-block text-secondary"></i>No expenses found matching criteria.</td></tr>';
      return;
    }

    var html = '';
    items.forEach(function(item) {
      var statusBadge = 'badge-secondary';
      if (item.billing_status === 'pending') statusBadge = 'badge-warning';
      else if (item.billing_status === 'invoiced') statusBadge = 'badge-success';
      else if (item.billing_status === 'void') statusBadge = 'badge-secondary';

      var actions = '<button class="btn btn-sm btn-outline-info mr-1 btn-view-exp" data-id="' + item.id + '" title="View Details"><i class="fa fa-eye"></i></button>';

      if (item.billing_status === 'pending') {
        actions += '<button class="btn btn-sm btn-outline-secondary mr-1 btn-edit-exp" data-id="' + item.id + '" title="Edit Expense"><i class="fa fa-pencil"></i></button>';
        actions += '<button class="btn btn-sm btn-outline-danger btn-void-exp" data-id="' + item.id + '" title="Void Expense"><i class="fa fa-ban"></i></button>';
      }

      html += '<tr>' +
        '<td><strong>' + escapeHtml(item.code) + '</strong></td>' +
        '<td>' + escapeHtml(item.purchase_date) + '</td>' +
        '<td>' + escapeHtml(item.unidad_nombre) + '</td>' +
        '<td>' + (item.location_label ? escapeHtml(item.location_label) : '<span class="text-muted">-</span>') + '</td>' +
        '<td>' + (item.vendor ? escapeHtml(item.vendor) : '<span class="text-muted">-</span>') + '</td>' +
        '<td>' + (item.receipt_number ? escapeHtml(item.receipt_number) : '<span class="text-muted">-</span>') + '</td>' +
        '<td><span class="badge badge-light border">' + item.items_count + '</span></td>';

      if (window.CAN_TOTALS_EXP) {
        html += '<td><strong class="text-dark">' + formatMoney(item.total_amount) + '</strong></td>';
      }

      html += '<td><span class="badge ' + statusBadge + '">' + escapeHtml(item.billing_status) + '</span></td>' +
        '<td><span class="badge badge-light">' + escapeHtml(item.channel) + '</span></td>' +
        '<td class="text-right">' + actions + '</td>' +
        '</tr>';
    });

    expensesRows.innerHTML = html;

    // Attach listeners
    expensesRows.querySelectorAll('.btn-view-exp').forEach(function(btn) {
      btn.addEventListener('click', function() { viewExpense(this.getAttribute('data-id')); });
    });
    expensesRows.querySelectorAll('.btn-edit-exp').forEach(function(btn) {
      btn.addEventListener('click', function() { editExpense(this.getAttribute('data-id')); });
    });
    expensesRows.querySelectorAll('.btn-void-exp').forEach(function(btn) {
      btn.addEventListener('click', function() { promptVoidExpense(this.getAttribute('data-id')); });
    });
  }

  // --- DYNAMIC ITEMS IN EXPENSE FORM ---
  var expenseItemRows = document.getElementById('expenseItemRows');
  var expenseGrandTotal = document.getElementById('expenseGrandTotal');
  var btnAddItemRow = document.getElementById('btnAddItemRow');

  function addItemRow(desc, qty, price) {
    if (!expenseItemRows) return;
    var tr = document.createElement('tr');
    tr.className = 'expense-item-row';
    tr.innerHTML = '<td><input type="text" class="form-control form-control-sm item-desc" value="' + escapeHtml(desc || '') + '" placeholder="Item description..." required></td>' +
      '<td><input type="number" class="form-control form-control-sm item-qty" step="0.001" min="0.001" value="' + (qty || 1) + '" required></td>' +
      '<td><input type="number" class="form-control form-control-sm item-price" step="0.01" min="0" value="' + (price || 0) + '" required></td>' +
      '<td class="align-middle font-weight-bold text-right item-line-total">$0.00</td>' +
      '<td class="text-center align-middle"><button type="button" class="btn btn-sm btn-link text-danger p-0 btn-remove-item"><i class="fa fa-trash"></i></button></td>';

    expenseItemRows.appendChild(tr);

    var qtyInp = tr.querySelector('.item-qty');
    var priceInp = tr.querySelector('.item-price');
    var removeBtn = tr.querySelector('.btn-remove-item');

    function recalcLine() {
      var q = parseFloat(qtyInp.value) || 0;
      var p = parseFloat(priceInp.value) || 0;
      var lt = tr.querySelector('.item-line-total');
      if (lt) lt.innerText = formatMoney(q * p);
      recalcGrandTotal();
    }

    qtyInp.addEventListener('input', recalcLine);
    priceInp.addEventListener('input', recalcLine);

    removeBtn.addEventListener('click', function() {
      if (expenseItemRows.querySelectorAll('.expense-item-row').length <= 1) {
        if (window.Swal) Swal.fire('Notice', 'At least one item row is required.', 'info');
        else PGS.notify('At least one item row is required.');
        return;
      }
      tr.remove();
      recalcGrandTotal();
    });

    recalcLine();
  }

  function recalcGrandTotal() {
    if (!expenseItemRows || !expenseGrandTotal) return;
    var total = 0;
    expenseItemRows.querySelectorAll('.expense-item-row').forEach(function(row) {
      var q = parseFloat(row.querySelector('.item-qty').value) || 0;
      var p = parseFloat(row.querySelector('.item-price').value) || 0;
      total += (q * p);
    });
    expenseGrandTotal.innerText = formatMoney(total);
  }

  if (btnAddItemRow) {
    btnAddItemRow.addEventListener('click', function() {
      addItemRow('', 1, 0);
    });
  }

  // --- REGISTER & EDIT EXPENSE ---
  var formExpense = document.getElementById('formExpense');
  var btnNewExpense = document.getElementById('btnNewExpense');
  var modalExpenseForm = $('#modalExpenseForm');
  var modalExpenseTitle = document.getElementById('modalExpenseTitle');

  if (btnNewExpense) {
    btnNewExpense.addEventListener('click', function() {
      if (formExpense) {
        formExpense.reset();
        document.getElementById('expId').value = '';
        document.getElementById('expRowVersion').value = '1';
        document.getElementById('expDate').value = new Date().toISOString().slice(0, 10);
        document.getElementById('receiptCurrentFileHelp').innerText = '';
        if (expenseItemRows) expenseItemRows.innerHTML = '';
        addItemRow('', 1, 0);
      }
      if (modalExpenseTitle) modalExpenseTitle.innerHTML = '<i class="fa fa-file-text text-danger mr-2"></i>Register Property Expense';
      modalExpenseForm.modal('show');
    });
  }

  function editExpense(id) {
    fetch('admin/ajax/rqst.php?op=exp_get&id=' + encodeURIComponent(id))
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (!data || !data.ok || !data.data) {
          PGS.notify('Failed to load expense details');
          return;
        }
        var exp = data.data;
        document.getElementById('expId').value = exp.id;
        document.getElementById('expRowVersion').value = exp.row_version || 1;
        document.getElementById('expUnit').value = exp.unidad_id;
        document.getElementById('expLocation').value = exp.location_label || '';
        document.getElementById('expDate').value = exp.purchase_date;
        document.getElementById('expVendor').value = exp.vendor || '';
        document.getElementById('expReceiptNumber').value = exp.receipt_number || '';
        document.getElementById('expNotes').value = exp.notes || '';

        var help = document.getElementById('receiptCurrentFileHelp');
        if (help) {
          help.innerText = exp.receipt_path ? 'Current receipt attached. Upload new file only to replace.' : '';
        }

        if (expenseItemRows) expenseItemRows.innerHTML = '';
        var items = exp.items || [];
        if (!items.length) addItemRow('', 1, 0);
        else {
          items.forEach(function(it) {
            addItemRow(it.description, it.quantity, it.unit_price);
          });
        }

        if (modalExpenseTitle) modalExpenseTitle.innerHTML = '<i class="fa fa-pencil text-danger mr-2"></i>Edit Expense (' + escapeHtml(exp.code) + ')';
        modalExpenseForm.modal('show');
      })
      .catch(function(err) {
        PGS.notify('Error loading expense');
      });
  }

  if (formExpense) {
    formExpense.addEventListener('submit', function(e) {
      e.preventDefault();

      var formData = new FormData(formExpense);
      if (!formData.get('csrf_token')) formData.append('csrf_token', getCsrfToken());

      // Collect item rows
      var itemRows = expenseItemRows ? expenseItemRows.querySelectorAll('.expense-item-row') : [];
      if (!itemRows.length) {
        PGS.notify('Please add at least one line item.');
        return;
      }

      var items = [];
      itemRows.forEach(function(row) {
        items.push({
          description: row.querySelector('.item-desc').value,
          quantity: row.querySelector('.item-qty').value,
          unit_price: row.querySelector('.item-price').value
        });
      });

      items.forEach(function(it, idx) {
        formData.append('items[' + idx + '][description]', it.description);
        formData.append('items[' + idx + '][quantity]', it.quantity);
        formData.append('items[' + idx + '][unit_price]', it.unit_price);
      });

      var btnSave = document.getElementById('btnSaveExpense');
      if (btnSave) {
        btnSave.disabled = true;
        btnSave.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Saving...';
      }

      fetch('admin/ajax/rqst.php?op=exp_save', {
        method: 'POST',
        body: formData
      })
        .then(function(res) { return res.json(); })
        .then(function(data) {
          if (btnSave) {
            btnSave.disabled = false;
            btnSave.innerHTML = 'Save Expense';
          }

          if (data && data.ok) {
            modalExpenseForm.modal('hide');
            if (window.Swal) Swal.fire({ icon: 'success', title: 'Expense saved successfully', timer: 1500, showConfirmButton: false });
            loadExpenses(currentPage);
            if (window.CAN_TOTALS_EXP) loadTotals();
          } else {
            var msg = data && data.error ? data.error : 'Failed to save expense';
            if (window.Swal) Swal.fire('Error', msg, 'error');
            else PGS.notify(msg);
          }
        })
        .catch(function(err) {
          if (btnSave) {
            btnSave.disabled = false;
            btnSave.innerHTML = 'Save Expense';
          }
          if (window.Swal) Swal.fire('Error', 'Network error saving expense', 'error');
          else PGS.notify('Network error saving expense');
        });
    });
  }

  // --- VOID EXPENSE ---
  function promptVoidExpense(id) {
    if (!window.Swal) {
      var r = prompt('Reason for voiding expense:');
      if (r) voidExpense(id, r);
      return;
    }

    Swal.fire({
      title: 'Void expense?',
      text: 'This expense will be permanently marked as void.',
      input: 'text',
      inputPlaceholder: 'Reason for voiding...',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#ef233c',
      confirmButtonText: 'Yes, void'
    }).then(function(result) {
      if (result.isConfirmed) {
        voidExpense(id, result.value || 'Voided by user');
      }
    });
  }

  function voidExpense(id, reason) {
    var formData = new FormData();
    formData.append('id', id);
    formData.append('reason', reason);
    formData.append('csrf_token', getCsrfToken());

    fetch('admin/ajax/rqst.php?op=exp_void', {
      method: 'POST',
      body: formData
    })
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (data && data.ok) {
          if (window.Swal) Swal.fire({ icon: 'success', title: 'Expense voided', timer: 1500, showConfirmButton: false });
          loadExpenses(currentPage);
          if (window.CAN_TOTALS_EXP) loadTotals();
        } else {
          var msg = data && data.error ? data.error : 'Failed to void expense';
          if (window.Swal) Swal.fire('Error', msg, 'error');
          else PGS.notify(msg);
        }
      })
      .catch(function(err) {
        if (window.Swal) Swal.fire('Error', 'Network error', 'error');
        else PGS.notify('Network error');
      });
  }

  // --- VIEW EXPENSE MODAL ---
  function viewExpense(id) {
    fetch('admin/ajax/rqst.php?op=exp_get&id=' + encodeURIComponent(id))
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (!data || !data.ok || !data.data) {
          PGS.notify('Failed to load expense');
          return;
        }
        var exp = data.data;
        document.getElementById('viewExpenseCode').innerText = exp.code + ' (' + exp.unidad_nombre + ')';

        var body = '<div class="row mb-3">' +
          '<div class="col-md-6"><small class="text-muted d-block">Purchase Date</small><strong>' + escapeHtml(exp.purchase_date) + '</strong></div>' +
          '<div class="col-md-6"><small class="text-muted d-block">Location</small><strong>' + (exp.location_label ? escapeHtml(exp.location_label) : '-') + '</strong></div>' +
          '</div>' +
          '<div class="row mb-3">' +
          '<div class="col-md-6"><small class="text-muted d-block">Vendor</small><strong>' + (exp.vendor ? escapeHtml(exp.vendor) : '-') + '</strong></div>' +
          '<div class="col-md-6"><small class="text-muted d-block">Receipt #</small><strong>' + (exp.receipt_number ? escapeHtml(exp.receipt_number) : '-') + '</strong>' + (exp.receipt_path ? ' <a class="btn btn-xs btn-outline-secondary ml-2" href="admin/ajax/file_download.php?type=expense_receipt&id=' + encodeURIComponent(exp.id) + '"><i class="fa fa-download"></i> Receipt</a>' : '') + '</div>' +
          '</div>' +
          '<div class="table-responsive mb-3"><table class="table table-sm table-bordered mb-0"><thead class="thead-light"><tr><th>Description</th><th>Qty</th><th>Price</th><th class="text-right">Total</th></tr></thead><tbody>';

        (exp.items || []).forEach(function(it) {
          body += '<tr><td>' + escapeHtml(it.description) + '</td><td>' + it.quantity + '</td><td>' + formatMoney(it.unit_price) + '</td><td class="text-right font-weight-bold">' + formatMoney(it.line_total) + '</td></tr>';
        });

        body += '</tbody><tfoot><tr><th colspan="3" class="text-right">Total:</th><th class="text-right text-danger">' + formatMoney(exp.total_amount) + '</th></tr></tfoot></table></div>';

        if (exp.notes) {
          body += '<div class="mb-2"><small class="text-muted d-block">Notes:</small><p class="mb-0 small">' + escapeHtml(exp.notes) + '</p></div>';
        }

        if (exp.billing_status === 'invoiced' && exp.linked_invoice_number) {
          body += '<div class="alert alert-success py-2 small mb-0"><i class="fa fa-check-circle mr-1"></i> Invoiced in #' + escapeHtml(exp.linked_invoice_number) + '</div>';
        }

        document.getElementById('viewExpenseBody').innerHTML = body;
        $('#modalViewExpense').modal('show');
      })
      .catch(function(err) {
        PGS.notify('Network error loading expense details');
      });
  }

  // --- TOTALS PANE ---
  function loadTotals() {
    if (!window.CAN_TOTALS_EXP) return;
    var rows = document.getElementById('totalsRows');
    if (!rows) return;

    fetch('admin/ajax/rqst.php?op=exp_totals')
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (!data || !data.ok) {
          rows.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-danger">Failed to load totals.</td></tr>';
          return;
        }

        var items = data.data || [];
        if (!items.length) {
          rows.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No expense totals recorded.</td></tr>';
          return;
        }

        var html = '';
        var sumTotal = 0, sumPending = 0, sumInvoiced = 0, sumCount = 0;

        items.forEach(function(item) {
          var t = parseFloat(item.total_amount) || 0;
          var p = parseFloat(item.pending_amount) || 0;
          var inv = parseFloat(item.invoiced_amount) || 0;
          var cnt = parseInt(item.expenses_count, 10) || 0;

          sumTotal += t;
          sumPending += p;
          sumInvoiced += inv;
          sumCount += cnt;

          html += '<tr>' +
            '<td><strong>' + escapeHtml(item.unidad_nombre) + '</strong></td>' +
            '<td>' + cnt + '</td>' +
            '<td><strong>' + formatMoney(t) + '</strong></td>' +
            '<td><span class="text-warning font-weight-bold">' + formatMoney(p) + '</span></td>' +
            '<td><span class="text-success font-weight-bold">' + formatMoney(inv) + '</span></td>' +
            '</tr>';
        });

        html += '<tr class="font-weight-bold bg-light">' +
          '<td>Total Summary</td>' +
          '<td>' + sumCount + '</td>' +
          '<td>' + formatMoney(sumTotal) + '</td>' +
          '<td class="text-warning">' + formatMoney(sumPending) + '</td>' +
          '<td class="text-success">' + formatMoney(sumInvoiced) + '</td>' +
          '</tr>';

        rows.innerHTML = html;
      })
      .catch(function(err) {
        rows.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-danger">Network error loading totals.</td></tr>';
      });
  }

  // --- PENDING BILLING PANE ---
  var billingUnitSelect = document.getElementById('billingUnitSelect');
  var pendingBillingRows = document.getElementById('pendingBillingRows');
  var selectAllPending = document.getElementById('selectAllPending');
  var btnOpenBillModal = document.getElementById('btnOpenBillModal');
  var billingSelectedCount = document.getElementById('billingSelectedCount');
  var selectedPendingIds = [];

  function loadPendingBilling() {
    if (!billingUnitSelect || !pendingBillingRows) return;
    var unitId = billingUnitSelect.value;
    selectedPendingIds = [];
    updatePendingSelectedCount();

    if (!unitId) {
      pendingBillingRows.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Select a property above to view pending expenses.</td></tr>';
      return;
    }

    pendingBillingRows.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa fa-spinner fa-spin mr-2"></i>Loading pending expenses...</td></tr>';

    fetch('admin/ajax/rqst.php?op=exp_pending_by_unit&unidad_id=' + encodeURIComponent(unitId))
      .then(function(res) { return res.json(); })
      .then(function(data) {
        if (!data || !data.ok) {
          pendingBillingRows.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">' +
            (data && data.error ? data.error : 'Failed to load pending expenses.') + '</td></tr>';
          return;
        }

        var items = data.data || [];
        if (!items.length) {
          pendingBillingRows.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-success"><i class="fa fa-check-circle mr-1"></i>No pending expenses for this property. All are invoiced.</td></tr>';
          return;
        }

        var html = '';
        items.forEach(function(item) {
          html += '<tr>' +
            '<td><input type="checkbox" class="pending-chk" value="' + item.id + '"></td>' +
            '<td><strong>' + escapeHtml(item.code) + '</strong></td>' +
            '<td>' + escapeHtml(item.purchase_date) + '</td>' +
            '<td>' + (item.vendor ? escapeHtml(item.vendor) : '-') + '</td>' +
            '<td>' + (item.receipt_number ? escapeHtml(item.receipt_number) : '-') + '</td>' +
            '<td><strong>' + formatMoney(item.total_amount) + '</strong></td>' +
            '<td>' + escapeHtml(item.created_by_nombre) + '</td>' +
            '</tr>';
        });

        pendingBillingRows.innerHTML = html;

        pendingBillingRows.querySelectorAll('.pending-chk').forEach(function(chk) {
          chk.addEventListener('change', function() {
            var val = parseInt(this.value, 10);
            if (this.checked) selectedPendingIds.push(val);
            else {
              var idx = selectedPendingIds.indexOf(val);
              if (idx > -1) selectedPendingIds.splice(idx, 1);
            }
            updatePendingSelectedCount();
          });
        });
      })
      .catch(function(err) {
        pendingBillingRows.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Network error.</td></tr>';
      });
  }

  function updatePendingSelectedCount() {
    if (billingSelectedCount) billingSelectedCount.innerText = selectedPendingIds.length;
    if (btnOpenBillModal) btnOpenBillModal.disabled = selectedPendingIds.length === 0;
  }

  if (selectAllPending) {
    selectAllPending.addEventListener('change', function() {
      var chks = pendingBillingRows ? pendingBillingRows.querySelectorAll('.pending-chk') : [];
      selectedPendingIds = [];
      chks.forEach(function(chk) {
        chk.checked = selectAllPending.checked;
        if (selectAllPending.checked) {
          selectedPendingIds.push(parseInt(chk.value, 10));
        }
      });
      updatePendingSelectedCount();
    });
  }

  if (billingUnitSelect) {
    billingUnitSelect.addEventListener('change', loadPendingBilling);
  }

  // --- MARK INVOICED MODAL ---
  var modalLinkInvoice = $('#modalLinkInvoice');
  var selectTargetInvoice = document.getElementById('selectTargetInvoice');
  var formLinkInvoice = document.getElementById('formLinkInvoice');

  if (btnOpenBillModal) {
    btnOpenBillModal.addEventListener('click', function() {
      if (!selectedPendingIds.length) return;
      var unitId = billingUnitSelect.value;
      var unitName = billingUnitSelect.options[billingUnitSelect.selectedIndex].text;

      document.getElementById('linkInvoiceUnitName').innerText = unitName;
      document.getElementById('linkInvoiceCount').innerText = selectedPendingIds.length + ' expense(s)';

      selectTargetInvoice.innerHTML = '<option value="">Loading invoices...</option>';
      modalLinkInvoice.modal('show');

      fetch('admin/ajax/rqst.php?op=inv_list&unidad_id=' + encodeURIComponent(unitId) + '&per_page=100')
        .then(function(res) { return res.json(); })
        .then(function(data) {
          var items = (data && data.ok && data.data && data.data.items) ? data.data.items : [];
          items = items.filter(function(x) { return x.payment_status !== 'void'; });

          if (!items.length) {
            selectTargetInvoice.innerHTML = '<option value="">No active invoices found for this property</option>';
            return;
          }

          var html = '<option value="">Select Invoice...</option>';
          items.forEach(function(inv) {
            html += '<option value="' + inv.id + '">#' + escapeHtml(inv.invoice_number) + ' - ' + formatMoney(inv.amount) + ' (' + inv.payment_status + ')</option>';
          });
          selectTargetInvoice.innerHTML = html;
        })
        .catch(function(err) {
          selectTargetInvoice.innerHTML = '<option value="">Error loading invoices</option>';
        });
    });
  }

  if (formLinkInvoice) {
    formLinkInvoice.addEventListener('submit', function(e) {
      e.preventDefault();
      var invoiceId = selectTargetInvoice.value;
      if (!invoiceId) {
        PGS.notify('Please select an invoice.');
        return;
      }

      var formData = new FormData();
      formData.append('invoice_id', invoiceId);
      selectedPendingIds.forEach(function(id) { formData.append('expense_ids[]', id); });
      formData.append('csrf_token', getCsrfToken());

      var btnConfirm = document.getElementById('btnConfirmLink');
      if (btnConfirm) {
        btnConfirm.disabled = true;
        btnConfirm.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Linking...';
      }

      fetch('admin/ajax/rqst.php?op=exp_mark_invoiced', {
        method: 'POST',
        body: formData
      })
        .then(function(res) { return res.json(); })
        .then(function(data) {
          if (btnConfirm) {
            btnConfirm.disabled = false;
            btnConfirm.innerHTML = 'Confirm Link';
          }

          if (data && data.ok) {
            modalLinkInvoice.modal('hide');
            if (window.Swal) Swal.fire({ icon: 'success', title: 'Expenses marked as invoiced', timer: 1500, showConfirmButton: false });
            loadPendingBilling();
            loadExpenses(currentPage);
            if (window.CAN_TOTALS_EXP) loadTotals();
          } else {
            var msg = data && data.error ? data.error : 'Failed to link expenses';
            if (window.Swal) Swal.fire('Error', msg, 'error');
            else PGS.notify(msg);
          }
        })
        .catch(function(err) {
          if (btnConfirm) {
            btnConfirm.disabled = false;
            btnConfirm.innerHTML = 'Confirm Link';
          }
          if (window.Swal) Swal.fire('Error', 'Network error linking expenses', 'error');
          else PGS.notify('Network error linking expenses');
        });
    });
  }

  // --- EVENTS & INIT ---
  if (filterUnit) filterUnit.addEventListener('change', function() { loadExpenses(1); });
  if (filterStatus) filterStatus.addEventListener('change', function() { loadExpenses(1); });
  if (filterFromDate) filterFromDate.addEventListener('change', function() { loadExpenses(1); });
  if (filterToDate) filterToDate.addEventListener('change', function() { loadExpenses(1); });
  if (filterCreator) filterCreator.addEventListener('change', function() { loadExpenses(1); });
  if (btnSearchExpenses) btnSearchExpenses.addEventListener('click', function() { loadExpenses(1); });
  if (filterSearch) {
    filterSearch.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') { e.preventDefault(); loadExpenses(1); }
    });
  }

  if (btnPrevPage) btnPrevPage.addEventListener('click', function() { if (currentPage > 1) loadExpenses(currentPage - 1); });
  if (btnNextPage) btnNextPage.addEventListener('click', function() { if (currentPage < totalPages) loadExpenses(currentPage + 1); });

  // Initial loads
  loadExpenses(1);
  if (window.CAN_TOTALS_EXP) loadTotals();

})();

(function () {
  var btnExport = document.getElementById('btnExportTotals');
  if (!btnExport) return;
  btnExport.addEventListener('click', function () {
    var params = new URLSearchParams({ op: 'exp_export' });
    var map = { filterUnit: 'unidad_id', filterStatus: 'billing_status', filterFromDate: 'from_date', filterToDate: 'to_date', filterSearch: 'q' };
    Object.keys(map).forEach(function (id) {
      var el = document.getElementById(id);
      if (el && el.value) params.append(map[id], el.value);
    });
    window.location.href = 'admin/ajax/rqst.php?' + params.toString();
  });
})();
