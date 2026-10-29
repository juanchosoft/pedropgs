<?php
require './admin/include/generic_classes.php';
requireAnyPermission(['facturas.invoices.view_own_units', 'facturas.invoices.manage']);
require_once './admin/bootstrap_pgs.php';

$P = \Pgs\Core\Web\PgsPage::principal();
$canManage = \Pgs\Auth\Policy\InvoicePolicy::canManage($P);
$modulo = 'Invoices';

$pdo = \Pgs\Core\Db::pdo();

// Properties scoped to principal
$units = [];
if ($P->isSystemAdmin() || $P->can('tareas.tasks.view_all')) {
    $units = $pdo->query('SELECT id, nombre FROM tbl_unidades ORDER BY nombre ASC')->fetchAll();
} else {
    $unitParams = [];
    $filter = \Pgs\Auth\UnitScope::sqlFilter($P, 'id', $unitParams);
    $uStmt = $pdo->prepare("SELECT id, nombre FROM tbl_unidades WHERE {$filter} ORDER BY nombre ASC");
    $uStmt->execute($unitParams);
    $units = $uStmt->fetchAll();
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
              <h3>Invoices</h3>
              <p>Manage HOA property invoices, payments, and linked expense items.</p>
            </div>
            <div>
              <?php if ($canManage): ?>
              <button class="btn btn-outline-danger btn-lg shadow-sm mr-2" id="btnPrefillAi" title="Prefill invoice from uploaded PDF using AI Assistant">
                <i class="fa fa-magic mr-1"></i> Prefill from AI
              </button>
              <button class="btn btn-danger btn-lg shadow-sm" id="btnNewInvoice">
                <i class="fa fa-upload mr-1"></i> Upload Invoice
              </button>
              <?php endif; ?>
            </div>
          </div>

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
                <label class="small text-muted font-weight-bold mb-1">Payment Status</label>
                <select class="form-control form-control-sm" id="filterStatus">
                  <option value="">All Statuses</option>
                  <option value="pending">Pending</option>
                  <option value="partially_paid">Partially Paid</option>
                  <option value="paid">Paid</option>
                  <option value="overdue">Overdue</option>
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
                <label class="small text-muted font-weight-bold mb-1">Search Number</label>
                <div class="input-group input-group-sm">
                  <input type="text" class="form-control" id="filterSearch" placeholder="Invoice # or location...">
                  <div class="input-group-append">
                    <button class="btn btn-outline-secondary" id="btnSearchInvoices" type="button"><i class="fa fa-search"></i></button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Invoices Table -->
          <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover mb-0" id="tableInvoices">
                  <thead class="thead-light">
                    <tr>
                      <th>Invoice #</th>
                      <th>Property</th>
                      <th>Location</th>
                      <th>Issue Date</th>
                      <th>Due Date</th>
                      <th>Amount ($)</th>
                      <th>Paid ($)</th>
                      <th>Status</th>
                      <?php if ($canManage): ?>
                      <th>Linked Expenses</th>
                      <?php endif; ?>
                      <th class="text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody id="invoicesRows">
                    <tr><td colspan="<?php echo $canManage ? '10' : '9'; ?>" class="text-center py-5 text-muted"><i class="fa fa-spinner fa-spin mr-2"></i>Loading invoices...</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2" id="invoicesPagination">
              <span class="small text-muted" id="invoicesCountInfo">Showing 0 of 0</span>
              <div>
                <button class="btn btn-sm btn-outline-secondary mr-1" id="btnPrevPage" disabled><i class="fa fa-chevron-left"></i></button>
                <button class="btn btn-sm btn-outline-secondary" id="btnNextPage" disabled><i class="fa fa-chevron-right"></i></button>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>

  <?php if ($canManage): ?>
  <!-- Modal Upload / Edit Invoice -->
  <div class="modal fade" id="modalInvoiceForm" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content">
        <form id="formInvoice" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
          <input type="hidden" name="id" id="invId">
          <input type="hidden" name="row_version" id="invRowVersion" value="1">
          <div class="modal-header">
            <h5 class="modal-title font-weight-bold" id="modalInvoiceTitle"><i class="fa fa-upload text-danger mr-2"></i>Upload Invoice</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <div class="form-row">
              <div class="form-group col-md-6">
                <label class="font-weight-bold">Invoice Number <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="invoice_number" id="invNumber" placeholder="e.g. INV-2026-0042" required>
              </div>
              <div class="form-group col-md-6">
                <label class="font-weight-bold">Property <span class="text-danger">*</span></label>
                <select class="form-control" name="unidad_id" id="invUnit" required>
                  <option value="">Select Property...</option>
                  <?php foreach ($units as $u): ?>
                    <option value="<?php echo (int) $u['id']; ?>"><?php echo htmlspecialchars($u['nombre']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-6">
                <label class="font-weight-bold">Location Label</label>
                <input type="text" class="form-control" name="location_label" id="invLocation" placeholder="e.g. Common areas, Clubhouse">
              </div>
              <div class="form-group col-md-6">
                <label class="font-weight-bold">Amount ($) <span class="text-danger">*</span></label>
                <input type="number" class="form-control" step="0.01" min="0.01" name="amount" id="invAmount" placeholder="0.00" required>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-6">
                <label class="font-weight-bold">Issue Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control" name="issue_date" id="invIssueDate" value="<?php echo date('Y-m-d'); ?>" required>
              </div>
              <div class="form-group col-md-6">
                <label class="font-weight-bold">Due Date (Optional)</label>
                <input type="date" class="form-control" name="due_date" id="invDueDate">
              </div>
            </div>

            <div class="form-group">
              <label class="font-weight-bold">Invoice PDF File <span class="text-danger" id="pdfRequiredStar">*</span></label>
              <input type="file" class="form-control-file" name="pdf_file" id="invPdfFile" accept=".pdf">
              <small class="form-text text-muted" id="invCurrentPdfHelp">Must be a valid PDF file up to 15MB.</small>
            </div>

            <!-- Optional: Link Pending Expenses -->
            <div class="card bg-light border p-3 mb-3" id="linkExpensesSection" style="display: none;">
              <label class="font-weight-bold mb-2">Link Pending Expenses for this Property</label>
              <div id="pendingExpensesCheckboxList" class="small" style="max-height: 150px; overflow-y: auto;">
                <!-- Populated dynamically when unit is selected -->
              </div>
            </div>

            <div class="form-group">
              <label class="font-weight-bold">Notes</label>
              <textarea class="form-control" name="notes" id="invNotes" rows="2" placeholder="Optional notes..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger" id="btnSaveInvoice">Save Invoice</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal Register Payment -->
  <div class="modal fade" id="modalPaymentForm" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <form id="formPayment">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
          <input type="hidden" name="id" id="payInvId">
          <div class="modal-header">
            <h5 class="modal-title font-weight-bold"><i class="fa fa-money text-success mr-2"></i>Register Payment</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <p class="mb-2">Invoice: <strong id="payInvNumber">-</strong></p>
            <p class="mb-3">Remaining Balance: <strong class="text-danger" id="payRemaining">-</strong></p>
            <div class="form-group">
              <label class="font-weight-bold">Payment Amount ($) <span class="text-danger">*</span></label>
              <input type="number" class="form-control" step="0.01" min="0.01" name="amount" id="payAmount" required>
            </div>
            <div class="form-group">
              <label class="font-weight-bold">Payment Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" name="paid_date" id="payDate" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success" id="btnSubmitPayment">Register Payment</button>
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
  window.CAN_MANAGE_INV = <?php echo $canManage ? 'true' : 'false'; ?>;
</script>
<script src="admin/js/invoices.js?v=<?php echo time(); ?>"></script>
</body>
</html>
