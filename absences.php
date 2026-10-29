<?php
require './admin/include/generic_classes.php';
require_once './admin/bootstrap_pgs.php';

$P = \Pgs\Core\Web\PgsPage::principal();

// D-25: UnitAdmins never see time off or balances
if ($P->isUnitAdmin() && !$P->isSystemAdmin()) {
    header('Location: permiso_denegado.php');
    exit;
}

$canApprove = $P->can('disponibilidad.absences.approve') || $P->isSystemAdmin();
$canRequest = $P->can('disponibilidad.absences.request') && $P->employeeId !== null;

if (!$canApprove && !$canRequest) {
    header('Location: permiso_denegado.php');
    exit;
}

$modulo = 'Time Off & Leaves';
$currentYear = (int) \Pgs\Core\Clock::todayLocal()->format('Y');
$pdo = \Pgs\Core\Db::pdo();

$employees = [];
if ($canApprove) {
    $employees = $pdo->query("SELECT id, nombre FROM tec_employee WHERE enable = 'si' ORDER BY nombre ASC")->fetchAll();
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
              <h3>Time Off & Absence Management</h3>
              <p>Request time off, manage vacation quotas, and review absence approvals.</p>
            </div>
            <div>
              <?php if ($canRequest): ?>
              <button class="btn btn-danger btn-lg shadow-sm mr-2" id="btnRequestLeave">
                <i class="fa fa-calendar-plus-o mr-1"></i> Request Time Off
              </button>
              <?php endif; ?>
            </div>
          </div>

          <!-- Nav Tabs -->
          <ul class="nav nav-tabs mb-4" id="leaveTabs" role="tablist">
            <?php if ($canRequest): ?>
            <li class="nav-item">
              <a class="nav-link active font-weight-bold" id="tab-my-leaves" data-toggle="tab" href="#pane-my-leaves" role="tab">
                <i class="fa fa-user mr-1"></i> My Time Off
              </a>
            </li>
            <?php endif; ?>

            <?php if ($canApprove): ?>
            <li class="nav-item">
              <a class="nav-link font-weight-bold <?php echo !$canRequest ? 'active' : ''; ?>" id="tab-approvals" data-toggle="tab" href="#pane-approvals" role="tab">
                <i class="fa fa-check-circle mr-1"></i> Approvals
                <span class="badge badge-danger ml-1" id="pendingApprovalsBadge" style="display: none;">0</span>
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link font-weight-bold" id="tab-balances" data-toggle="tab" href="#pane-balances" role="tab">
                <i class="fa fa-calculator mr-1"></i> Balances & Quotas
              </a>
            </li>
            <?php endif; ?>
          </ul>

          <div class="tab-content" id="leaveTabsContent">

            <?php if ($canRequest): ?>
            <!-- Pane 1: My Time Off -->
            <div class="tab-pane fade show active" id="pane-my-leaves" role="tabpanel">
              <!-- KPI Balance Cards -->
              <div class="row mb-4" id="myBalanceKpis">
                <div class="col-md-3 col-sm-6 mb-3">
                  <div class="card p-3 shadow-sm border-0 border-left-danger" style="border-left: 4px solid #ef233c !important;">
                    <span class="text-muted small text-uppercase font-weight-bold">Available Days</span>
                    <h3 class="font-weight-bold text-danger mt-1 mb-0" id="kpiAvailable">-</h3>
                    <small class="text-muted">Current year</small>
                  </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                  <div class="card p-3 shadow-sm border-0">
                    <span class="text-muted small text-uppercase font-weight-bold">Annual Quota</span>
                    <h3 class="font-weight-bold text-dark mt-1 mb-0" id="kpiQuota">-</h3>
                    <small class="text-muted">Allocated for <?php echo $currentYear; ?></small>
                  </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                  <div class="card p-3 shadow-sm border-0">
                    <span class="text-muted small text-uppercase font-weight-bold">Used Days</span>
                    <h3 class="font-weight-bold text-secondary mt-1 mb-0" id="kpiUsed">-</h3>
                    <small class="text-muted">Approved & consumed</small>
                  </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                  <div class="card p-3 shadow-sm border-0">
                    <span class="text-muted small text-uppercase font-weight-bold">Pending Approval</span>
                    <h3 class="font-weight-bold text-warning mt-1 mb-0" id="kpiPending">-</h3>
                    <small class="text-muted">Awaiting decision</small>
                  </div>
                </div>
              </div>

              <!-- My Requests Table -->
              <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                  <h5 class="mb-0 font-weight-bold"><i class="fa fa-list text-danger mr-2"></i>My Absence Requests</h5>
                  <button class="btn btn-sm btn-outline-secondary" id="btnRefreshMyLeaves">
                    <i class="fa fa-refresh mr-1"></i> Refresh
                  </button>
                </div>
                <div class="card-body p-0">
                  <div class="table-responsive">
                    <table class="table table-hover mb-0" id="tableMyLeaves">
                      <thead class="thead-light">
                        <tr>
                          <th>Date Range</th>
                          <th>Working Days</th>
                          <th>Reason</th>
                          <th>Status</th>
                          <th>Requested On</th>
                          <th>Decision Note</th>
                          <th class="text-right">Action</th>
                        </tr>
                      </thead>
                      <tbody id="myLeavesRows">
                        <tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa fa-spinner fa-spin mr-2"></i>Loading requests...</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
            <?php endif; ?>

            <?php if ($canApprove): ?>
            <!-- Pane 2: Approvals -->
            <div class="tab-pane fade <?php echo !$canRequest ? 'show active' : ''; ?>" id="pane-approvals" role="tabpanel">
              <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                  <div>
                    <h5 class="mb-0 font-weight-bold"><i class="fa fa-clock-o text-danger mr-2"></i>Pending Time Off Requests</h5>
                    <small class="text-muted">Review, approve or reject worker absence requests</small>
                  </div>
                  <button class="btn btn-sm btn-outline-secondary" id="btnRefreshApprovals">
                    <i class="fa fa-refresh mr-1"></i> Refresh
                  </button>
                </div>
                <div class="card-body p-0">
                  <div class="table-responsive">
                    <table class="table table-hover mb-0" id="tableApprovals">
                      <thead class="thead-light">
                        <tr>
                          <th>Worker</th>
                          <th>Date Range</th>
                          <th>Days</th>
                          <th>Type</th>
                          <th>Deducts Balance</th>
                          <th>Notes</th>
                          <th>Requested On</th>
                          <th class="text-right">Decision</th>
                        </tr>
                      </thead>
                      <tbody id="approvalsRows">
                        <tr><td colspan="8" class="text-center py-4 text-muted"><i class="fa fa-spinner fa-spin mr-2"></i>Loading approvals...</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <!-- Pane 3: Balances & Quotas -->
            <div class="tab-pane fade" id="pane-balances" role="tabpanel">
              <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3">
                  <div class="row align-items-center">
                    <div class="col-md-6">
                      <h5 class="mb-0 font-weight-bold"><i class="fa fa-users text-danger mr-2"></i>Annual Worker Leave Quotas</h5>
                      <small class="text-muted">Set annual leave quotas and review consumption ledgers</small>
                    </div>
                    <div class="col-md-6 d-flex justify-content-md-end align-items-center mt-2 mt-md-0">
                      <label class="mr-2 mb-0 font-weight-bold text-muted small">Year:</label>
                      <select class="form-control form-control-sm mr-2" id="selectBalanceYear" style="width: 100px;">
                        <?php for ($y = $currentYear + 1; $y >= $currentYear - 2; $y--): ?>
                          <option value="<?php echo $y; ?>" <?php echo $y === $currentYear ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endfor; ?>
                      </select>
                      <button class="btn btn-sm btn-outline-secondary" id="btnRefreshBalances">
                        <i class="fa fa-refresh mr-1"></i> Refresh
                      </button>
                    </div>
                  </div>
                </div>
                <div class="card-body p-0">
                  <div class="table-responsive">
                    <table class="table table-hover mb-0" id="tableBalances">
                      <thead class="thead-light">
                        <tr>
                          <th>Worker</th>
                          <th>Annual Quota</th>
                          <th>Used</th>
                          <th>Pending</th>
                          <th>Net Balance</th>
                          <th>Available</th>
                          <th class="text-right">Actions</th>
                        </tr>
                      </thead>
                      <tbody id="balancesRows">
                        <tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa fa-spinner fa-spin mr-2"></i>Loading balances...</td></tr>
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

  <?php if ($canRequest): ?>
  <!-- Modal Request Leave -->
  <div class="modal fade" id="modalRequestLeave" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <form id="formRequestLeave">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
          <div class="modal-header">
            <h5 class="modal-title font-weight-bold"><i class="fa fa-calendar-plus-o text-danger mr-2"></i>Request Time Off</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <?php if ($canApprove): ?>
            <div class="form-group">
              <label class="font-weight-bold">Worker (Leave empty for self)</label>
              <select class="form-control" name="employee_id" id="reqEmployeeId">
                <option value="">Myself</option>
                <?php foreach ($employees as $e): ?>
                  <option value="<?php echo (int) $e['id']; ?>"><?php echo htmlspecialchars($e['nombre']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>

            <div class="form-row">
              <div class="form-group col-md-6">
                <label class="font-weight-bold">Start Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control" name="date_from" id="reqDateFrom" required>
              </div>
              <div class="form-group col-md-6">
                <label class="font-weight-bold">End Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control" name="date_to" id="reqDateTo" required>
              </div>
            </div>

            <!-- Preview Card -->
            <div class="alert alert-info py-2 px-3 small" id="leavePreviewAlert" style="display: none;">
              <div class="d-flex justify-content-between align-items-center">
                <span>Working Days to deduct: <strong id="previewDaysCount">0</strong></span>
                <span class="text-muted" id="previewHolidaysInfo"></span>
              </div>
            </div>

            <div class="form-group">
              <label class="font-weight-bold">Reason <span class="text-danger">*</span></label>
              <select class="form-control" name="reason_type" id="reqReasonType" required>
                <option value="vacation">Vacation (Deductible)</option>
                <option value="personal">Personal leave (Deductible)</option>
                <option value="sick">Sick leave (Non-deductible)</option>
                <option value="other">Other absence (Non-deductible)</option>
              </select>
            </div>

            <div class="form-group">
              <label class="font-weight-bold">Notes / Comments</label>
              <textarea class="form-control" name="reason_text" rows="2" placeholder="Optional notes..."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger" id="btnSubmitLeave">Submit Request</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($canApprove): ?>
  <!-- Modal Set Quota -->
  <div class="modal fade" id="modalSetQuota" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <form id="formSetQuota">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
          <input type="hidden" name="employee_id" id="quotaEmployeeId">
          <input type="hidden" name="year" id="quotaYear">
          <input type="hidden" name="row_version" id="quotaRowVersion">
          <div class="modal-header">
            <h5 class="modal-title font-weight-bold"><i class="fa fa-sliders text-danger mr-2"></i>Set Annual Quota</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <p class="mb-3">Worker: <strong id="quotaWorkerName">-</strong> | Year: <strong id="quotaYearLabel">-</strong></p>
            <div class="form-group">
              <label class="font-weight-bold">Annual Quota (Days) <span class="text-danger">*</span></label>
              <input type="number" class="form-control" name="days" id="quotaDays" min="0" max="365" required>
            </div>
            <div class="form-group">
              <label class="font-weight-bold">Notes / Rationale</label>
              <input type="text" class="form-control" name="notes" placeholder="e.g. Standard 15 vacation days">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger" id="btnSaveQuota">Save Quota</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal Adjust Balance -->
  <div class="modal fade" id="modalAdjustBalance" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <form id="formAdjustBalance">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
          <input type="hidden" name="employee_id" id="adjustEmployeeId">
          <input type="hidden" name="year" id="adjustYear">
          <div class="modal-header">
            <h5 class="modal-title font-weight-bold"><i class="fa fa-pencil-square-o text-danger mr-2"></i>Adjust Leave Balance</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <p class="mb-3">Worker: <strong id="adjustWorkerName">-</strong> | Year: <strong id="adjustYearLabel">-</strong></p>
            <div class="form-group">
              <label class="font-weight-bold">Adjustment Days (+/-) <span class="text-danger">*</span></label>
              <input type="number" class="form-control" name="days" id="adjustDays" placeholder="+2 or -1" required>
              <small class="form-text text-muted">Use positive number to add days, negative to deduct days.</small>
            </div>
            <div class="form-group">
              <label class="font-weight-bold">Reason for adjustment <span class="text-danger">*</span></label>
              <input type="text" class="form-control" name="reason" id="adjustReason" placeholder="Mandatory reason for audit..." required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger" id="btnSaveAdjust">Apply Adjustment</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Modal Ledger / Kardex -->
  <div class="modal fade" id="modalLedger" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title font-weight-bold"><i class="fa fa-history text-danger mr-2"></i>Time Off Movements Ledger</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
              <thead class="thead-light">
                <tr>
                  <th>Date</th>
                  <th>Movement</th>
                  <th>Days</th>
                  <th>Reason</th>
                  <th>Channel</th>
                  <th>Actor</th>
                </tr>
              </thead>
              <tbody id="ledgerRows">
                <tr><td colspan="6" class="text-center py-4 text-muted"><i class="fa fa-spinner fa-spin mr-2"></i>Loading ledger...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php include './admin/include/gerenic_footer.php'; ?>
  <?php include './admin/include/gerenic_script.php'; ?>
</div>

<script>
  window.CAN_APPROVE_LEAVES = <?php echo $canApprove ? 'true' : 'false'; ?>;
  window.CAN_REQUEST_LEAVES = <?php echo $canRequest ? 'true' : 'false'; ?>;
  window.CURRENT_YEAR = <?php echo $currentYear; ?>;
</script>
<script src="admin/js/absences.js?v=<?php echo time(); ?>"></script>
</body>
</html>
