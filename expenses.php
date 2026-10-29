<?php
require './admin/include/generic_classes.php';
require_once './admin/bootstrap_pgs.php';

$P = \Pgs\Core\Web\PgsPage::principal();

// D-25: Unit admins NEVER have access to expenses
if ($P->isUnitAdmin() && !$P->isSystemAdmin()) {
    header('Location: permiso_denegado.php');
    exit;
}

$canCreate = $P->can('gastos.expenses.create') || $P->isSystemAdmin();
$canViewOwn = $P->can('gastos.expenses.view_own');
$canViewAll = $P->can('gastos.expenses.view_all') || $P->isSystemAdmin();
$canTotals = $P->can('gastos.expenses.totals') || $P->isSystemAdmin();
$canMarkInvoiced = $P->can('gastos.expenses.mark_invoiced') || $P->isSystemAdmin();

if (!$canCreate && !$canViewOwn && !$canViewAll) {
    header('Location: permiso_denegado.php');
    exit;
}

$modulo = 'Expenses';
$pdo = \Pgs\Core\Db::pdo();

// Properties scoped to principal
$units = [];
if ($P->isSystemAdmin() || $canViewAll) {
    $units = $pdo->query('SELECT id, nombre FROM tbl_unidades ORDER BY nombre ASC')->fetchAll();
} else {
    $unitParams = [];
    $filter = \Pgs\Auth\UnitScope::sqlFilter($P, 'id', $unitParams);
    $uStmt = $pdo->prepare("SELECT id, nombre FROM tbl_unidades WHERE {$filter} ORDER BY nombre ASC");
    $uStmt->execute($unitParams);
    $units = $uStmt->fetchAll();
}

$creators = [];
if ($canViewAll) {
    $creators = $pdo->query('SELECT id, nombre FROM tec_usuarios WHERE habilitado = "1" ORDER BY nombre ASC')->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include './admin/include/generic_head.php'; ?>
  <title><?php echo htmlspecialchars($modulo); ?> - PGS Centrum</title>
</head>
<body>
<div id="main-wrapper">
  <?php include './admin/include/generic_header.php'; ?>
  <div class="deznav"><div class="deznav-scroll"><?php include './admin/include/generic_navbar.php'; ?></div></div>

  <div class="content-body">
    <div class="container-fluid">
      <div class="pgs-ui">
        <div class="page-wrap">

          <!-- Hero Section -->
          <div class="hero d-flex align-items-center justify-content-between flex-wrap">
            <div>
              <h3>Property Expenses</h3>
              <p>Track maintenance and property expenses with itemized receipts and billing statuses.</p>
            </div>
            <div>
              <?php if ($canCreate): ?>
              <button class="btn btn-danger btn-lg shadow-sm" id="btnNewExpense">
                <i class="fa fa-plus mr-1"></i> Register Expense
              </button>
              <?php endif; ?>
            </div>
          </div>

          <!-- Nav Tabs -->
          <ul class="nav nav-tabs mb-4" id="expenseTabs" role="tablist">
            <li class="nav-item">
              <a class="nav-link active font-weight-bold" id="tab-expenses" data-toggle="tab" href="#pane-expenses" role="tab">
                <i class="fa fa-list mr-1"></i> Expenses
              </a>
            </li>
            <?php if ($canTotals): ?>
            <li class="nav-item">
              <a class="nav-link font-weight-bold" id="tab-totals" data-toggle="tab" href="#pane-totals" role="tab">
                <i class="fa fa-bar-chart mr-1"></i> Totals & Summaries
              </a>
            </li>
            <?php endif; ?>
            <?php if ($canMarkInvoiced): ?>
            <li class="nav-item">
              <a class="nav-link font-weight-bold" id="tab-pending-billing" data-toggle="tab" href="#pane-pending-billing" role="tab">
                <i class="fa fa-file-text-o mr-1"></i> Pending Billing
              </a>
            </li>
            <?php endif; ?>
          </ul>

          <div class="tab-content" id="expenseTabsContent">

            <!-- Pane 1: Expenses List -->
            <div class="tab-pane fade show active" id="pane-expenses" role="tabpanel">
              <!-- Filter Bar -->
              <div class="card mb-4 p-3 shadow-sm border-0">
                <div class="row align-items-center">
                  <div class="col-md-3 mb-2">
                    <label class="small text-muted font-weight-bold mb-1">Property</label>
                    <select class="form-control form-control-sm" id="filterUnit">
                      <option value="">All Properties</option>
                      <?php foreach ($units as $u): ?>
                        <option value="<?php echo (int) $u['id']; ?>"><?php echo htmlspecialchars($u['nombre']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-2 mb-2">
                    <label class="small text-muted font-weight-bold mb-1">Status</label>
                    <select class="form-control form-control-sm" id="filterStatus">
                      <option value="">All Statuses</option>
                      <option value="pending">Pending</option>
                      <option value="invoiced">Invoiced</option>
                      <option value="void">Void</option>
                    </select>
                  </div>
                  <div class="col-md-2 mb-2">
                    <label class="small text-muted font-weight-bold mb-1">From Date</label>
                    <input type="date" class="form-control form-control-sm" id="filterFromDate">
                  </div>
                  <div class="col-md-2 mb-2">
                    <label class="small text-muted font-weight-bold mb-1">To Date</label>
                    <input type="date" class="form-control form-control-sm" id="filterToDate">
                  </div>
                  <div class="col-md-3 mb-2">
                    <label class="small text-muted font-weight-bold mb-1">Search</label>
                    <div class="input-group input-group-sm">
                      <input type="text" class="form-control" id="filterSearch" placeholder="Code, vendor, receipt...">
                      <div class="input-group-append">
                        <button class="btn btn-outline-secondary" id="btnSearchExpenses" type="button"><i class="fa fa-search"></i></button>
                      </div>
                    </div>
                  </div>
                  <?php if ($canViewAll): ?>
                  <div class="col-md-3 mb-2">
                    <label class="small text-muted font-weight-bold mb-1">Registered By</label>
                    <select class="form-control form-control-sm" id="filterCreator">
                      <option value="">All Users</option>
                      <?php foreach ($creators as $c): ?>
                        <option value="<?php echo (int) $c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Expenses Table -->
              <div class="card shadow-sm border-0 mb-4">
                <div class="card-body p-0">
                  <div class="table-responsive">
                    <table class="table table-hover mb-0" id="tableExpenses">
                      <thead class="thead-light">
                        <tr>
                          <th>Code</th>
                          <th>Purchase Date</th>
                          <th>Property</th>
                          <th>Location</th>
                          <th>Vendor</th>
                          <th>Receipt #</th>
                          <th>Items</th>
                          <?php if ($canTotals): ?>
                          <th>Total Amount</th>
                          <?php endif; ?>
                          <th>Status</th>
                          <th>Channel</th>
                          <th class="text-right">Actions</th>
                        </tr>
                      </thead>
                      <tbody id="expensesRows">
                        <tr><td colspan="<?php echo $canTotals ? '11' : '10'; ?>" class="text-center py-5 text-muted"><i class="fa fa-spinner fa-spin mr-2"></i>Loading expenses...</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>
                <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2" id="expensesPagination">
                  <span class="small text-muted" id="expensesCountInfo">Showing 0 of 0</span>
                  <div>
                    <button class="btn btn-sm btn-outline-secondary mr-1" id="btnPrevPage" disabled><i class="fa fa-chevron-left"></i></button>
                    <button class="btn btn-sm btn-outline-secondary" id="btnNextPage" disabled><i class="fa fa-chevron-right"></i></button>
                  </div>
                </div>
              </div>
            </div>

            <?php if ($canTotals): ?>
            <!-- Pane 2: Totals -->
            <div class="tab-pane fade" id="pane-totals" role="tabpanel">
              <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                  <h5 class="mb-0 font-weight-bold"><i class="fa fa-pie-chart text-danger mr-2"></i>Expense Summaries by Property</h5>
                  <button class="btn btn-sm btn-outline-secondary" id="btnExportTotals">
                    <i class="fa fa-download mr-1"></i> Export CSV
                  </button>
                </div>
                <div class="card-body p-0">
                  <div class="table-responsive">
                    <table class="table table-hover mb-0" id="tableTotals">
                      <thead class="thead-light">
                        <tr>
                          <th>Property</th>
                          <th>Expenses Count</th>
                          <th>Total Amount</th>
                          <th>Pending Billing</th>
                          <th>Invoiced Amount</th>
                        </tr>
                      </thead>
                      <tbody id="totalsRows">
                        <tr><td colspan="5" class="text-center py-4 text-muted"><i class="fa fa-spinner fa-spin mr-2"></i>Loading totals...</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
            <?php endif; ?>

            <?php if ($canMarkInvoiced): ?>
            <!-- Pane 3: Pending Billing -->
            <div class="tab-pane fade" id="pane-pending-billing" role="tabpanel">
              <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                  <div class="row align-items-center">
                    <div class="col-md-5">
                      <label class="small text-muted font-weight-bold mb-1">Select Property to Bill</label>
                      <select class="form-control form-control-sm" id="billingUnitSelect">
                        <option value="">Select Property...</option>
                        <?php foreach ($units as $u): ?>
                          <option value="<?php echo (int) $u['id']; ?>"><?php echo htmlspecialchars($u['nombre']); ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-7 d-flex justify-content-md-end align-items-end mt-2 mt-md-0">
                      <button class="btn btn-danger btn-sm" id="btnOpenBillModal" disabled>
                        <i class="fa fa-link mr-1"></i> Mark as Invoiced (<span id="billingSelectedCount">0</span>)
                      </button>
                    </div>
                  </div>
                </div>
                <div class="card-body p-0">
                  <div class="table-responsive">
                    <table class="table table-hover mb-0" id="tablePendingBilling">
                      <thead class="thead-light">
                        <tr>
                          <th style="width: 40px;"><input type="checkbox" id="selectAllPending"></th>
                          <th>Code</th>
                          <th>Date</th>
                          <th>Vendor</th>
                          <th>Receipt #</th>
                          <th>Total</th>
                          <th>Registered By</th>
                        </tr>
                      </thead>
                      <tbody id="pendingBillingRows">
                        <tr><td colspan="7" class="text-center py-4 text-muted">Select a property above to view pending expenses.</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
            <?php endif; ?>

          </div>

        </div>
      </div>
    </div>
  </div>

  <?php if ($canCreate): ?>
  <!-- Modal Expense Form (New / Edit) -->
  <div class="modal fade" id="modalExpenseForm" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content">
        <form id="formExpense" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
          <input type="hidden" name="id" id="expId">
          <input type="hidden" name="row_version" id="expRowVersion" value="1">
          <div class="modal-header">
            <h5 class="modal-title font-weight-bold" id="modalExpenseTitle"><i class="fa fa-file-text text-danger mr-2"></i>Register Property Expense</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <div class="form-row">
              <div class="form-group col-md-6">
                <label class="font-weight-bold">Property <span class="text-danger">*</span></label>
                <select class="form-control" name="unidad_id" id="expUnit" required>
                  <option value="">Select Property...</option>
                  <?php foreach ($units as $u): ?>
                    <option value="<?php echo (int) $u['id']; ?>"><?php echo htmlspecialchars($u['nombre']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group col-md-6">
                <label class="font-weight-bold">Location Label</label>
                <input type="text" class="form-control" name="location_label" id="expLocation" placeholder="e.g. Clubhouse roof, Pump room">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-4">
                <label class="font-weight-bold">Purchase Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control" name="purchase_date" id="expDate" value="<?php echo date('Y-m-d'); ?>" required>
              </div>
              <div class="form-group col-md-4">
                <label class="font-weight-bold">Vendor / Supplier</label>
                <input type="text" class="form-control" name="vendor" id="expVendor" placeholder="e.g. Home Depot, Grainger">
              </div>
              <div class="form-group col-md-4">
                <label class="font-weight-bold">Receipt / Invoice #</label>
                <input type="text" class="form-control" name="receipt_number" id="expReceiptNumber" placeholder="e.g. REC-99482">
              </div>
            </div>

            <div class="form-group">
              <label class="font-weight-bold">Receipt Attachment (PDF/JPG/PNG up to 10MB)</label>
              <input type="file" class="form-control-file" name="receipt_file" id="expReceiptFile" accept=".pdf,.jpg,.jpeg,.png">
              <small class="form-text text-muted" id="receiptCurrentFileHelp"></small>
            </div>

            <!-- Dynamic Items Section -->
            <div class="card bg-light border p-3 mb-3">
              <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="font-weight-bold mb-0">Itemized Items <span class="text-danger">*</span></label>
                <button type="button" class="btn btn-sm btn-outline-danger" id="btnAddItemRow">
                  <i class="fa fa-plus mr-1"></i> Add Item
                </button>
              </div>
              <div class="table-responsive">
                <table class="table table-sm table-bordered bg-white mb-2" id="tableExpenseItems">
                  <thead class="thead-light">
                    <tr>
                      <th>Description</th>
                      <th style="width: 100px;">Qty</th>
                      <th style="width: 120px;">Unit Price ($)</th>
                      <th style="width: 120px;">Total ($)</th>
                      <th style="width: 40px;"></th>
                    </tr>
                  </thead>
                  <tbody id="expenseItemRows">
                    <!-- Dynamic rows -->
                  </tbody>
                  <tfoot>
                    <tr>
                      <th colspan="3" class="text-right">Grand Total:</th>
                      <th colspan="2" class="text-danger font-weight-bold" id="expenseGrandTotal">$0.00</th>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>

            <div class="form-group">
              <label class="font-weight-bold">Notes</label>
              <textarea class="form-control" name="notes" id="expNotes" rows="2" placeholder="Optional notes..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger" id="btnSaveExpense">Save Expense</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Modal View Expense -->
  <div class="modal fade" id="modalViewExpense" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title font-weight-bold" id="viewExpenseCode">-</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body" id="viewExpenseBody">
          <!-- Populated dynamically -->
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <?php if ($canMarkInvoiced): ?>
  <!-- Modal Link to Invoice -->
  <div class="modal fade" id="modalLinkInvoice" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <form id="formLinkInvoice">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
          <div class="modal-header">
            <h5 class="modal-title font-weight-bold"><i class="fa fa-link text-danger mr-2"></i>Link to Property Invoice</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <p class="mb-2">Property: <strong id="linkInvoiceUnitName">-</strong></p>
            <p class="mb-3">Selected Expenses: <strong id="linkInvoiceCount">-</strong></p>
            <div class="form-group">
              <label class="font-weight-bold">Target Invoice <span class="text-danger">*</span></label>
              <select class="form-control" id="selectTargetInvoice" required>
                <option value="">Loading invoices...</option>
              </select>
              <small class="form-text text-muted">Select an existing invoice for this property.</small>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger" id="btnConfirmLink">Confirm Link</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php include './admin/include/gerenic_footer.php'; ?>
  <?php include './admin/include/gerenic_script.php'; ?>
</div>

<script>
  window.CAN_CREATE_EXP = <?php echo $canCreate ? 'true' : 'false'; ?>;
  window.CAN_VIEW_ALL_EXP = <?php echo $canViewAll ? 'true' : 'false'; ?>;
  window.CAN_TOTALS_EXP = <?php echo $canTotals ? 'true' : 'false'; ?>;
  window.CAN_MARK_INVOICED_EXP = <?php echo $canMarkInvoiced ? 'true' : 'false'; ?>;
</script>
<script src="admin/js/expenses.js?v=<?php echo time(); ?>"></script>
</body>
</html>
