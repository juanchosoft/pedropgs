<?php
require './admin/include/generic_classes.php';
requireAnyPermission(['disponibilidad.reservations.create', 'disponibilidad.reservations.manage']);
require_once './admin/bootstrap_pgs.php';

$P = \Pgs\Core\Web\PgsPage::principal();
$canCreate = $P->can('disponibilidad.reservations.create');
$canManage = $P->can('disponibilidad.reservations.manage') || $P->isSystemAdmin();
$modulo = 'Worker Reservations';

$pdo = \Pgs\Core\Db::pdo();

// Scoped units
$unitParams = [];
if ($P->isSystemAdmin() || $P->can('tareas.tasks.view_all')) {
    $units = $pdo->query('SELECT id, nombre FROM tbl_unidades ORDER BY nombre ASC')->fetchAll();
} else {
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
  <style>
    .matrix-day-col {
      min-width: 90px;
      text-align: center;
      padding: 8px 4px;
      border: 1px solid #eef2f6;
    }
    .slot-btn {
      display: block;
      width: 100%;
      margin-bottom: 4px;
      font-size: 11px;
      font-weight: 600;
      padding: 4px 2px;
      border-radius: 4px;
      border: 1px solid transparent;
      cursor: pointer;
      text-align: center;
    }
    .slot-free {
      background-color: #e8f5e9;
      color: #2e7d32;
      border-color: #c8e6c9;
    }
    .slot-free:hover {
      background-color: #c8e6c9;
    }
    .slot-free.selected {
      background-color: #ef233c !important;
      color: #fff !important;
      border-color: #d90429 !important;
      box-shadow: 0 0 0 2px rgba(239, 35, 60, 0.4);
    }
    .slot-booked {
      background-color: #ffebee;
      color: #c62828;
      border-color: #ffcdd2;
      cursor: not-allowed;
      opacity: 0.7;
    }
    .slot-absence {
      background-color: #fff8e1;
      color: #f57f17;
      border-color: #ffecb3;
      cursor: not-allowed;
      opacity: 0.7;
    }
    .slot-holiday {
      background-color: #f3e5f5;
      color: #6a1b9a;
      border-color: #e1bee7;
      cursor: not-allowed;
      opacity: 0.7;
    }
    .slot-off {
      background-color: #f5f5f5;
      color: #9e9e9e;
      border-color: #eeeeee;
      cursor: not-allowed;
      opacity: 0.6;
    }
  </style>
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
              <h3>Worker Reservations</h3>
              <p>Reserve technician shifts for specific properties and maintenance tasks.</p>
            </div>
            <div>
              <button class="btn btn-outline-secondary mr-2" id="btnToggleView">
                <i class="fa fa-list mr-1"></i> Reservations List
              </button>
            </div>
          </div>

          <!-- Section 1: 14-Day Availability Matrix -->
          <div id="sectionMatrix">
            <!-- Property & Worker Selector Card -->
            <div class="card mb-4 p-3 shadow-sm border-0">
              <div class="row align-items-center">
                <div class="col-md-5 mb-2 mb-md-0">
                  <label class="small text-muted font-weight-bold mb-1">Property (Unit) <span class="text-danger">*</span></label>
                  <select class="form-control" id="matrixUnit" required>
                    <option value="">Select Property...</option>
                    <?php foreach ($units as $u): ?>
                      <option value="<?php echo (int) $u['id']; ?>"><?php echo htmlspecialchars($u['nombre']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-5 mb-2 mb-md-0">
                  <label class="small text-muted font-weight-bold mb-1">Worker (Technician) <span class="text-danger">*</span></label>
                  <select class="form-control" id="matrixWorker" disabled>
                    <option value="">Select a property first...</option>
                  </select>
                </div>
                <div class="col-md-2 mt-md-4">
                  <button class="btn btn-danger btn-block" id="btnCheckAvailability" disabled>
                    <i class="fa fa-search mr-1"></i> Check
                  </button>
                </div>
              </div>
            </div>

            <!-- Matrix Card -->
            <div class="card shadow-sm border-0 mb-4" id="matrixCard" style="display: none;">
              <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 flex-wrap">
                <div>
                  <h5 class="mb-0 font-weight-bold"><i class="fa fa-calendar-check-o text-danger mr-2"></i>14-Day Availability Matrix</h5>
                  <small class="text-muted" id="matrixSubtitle">Select available slots to reserve</small>
                </div>
                <div class="mt-2 mt-sm-0">
                  <span class="mr-3 small"><span class="badge badge-success">&nbsp;</span> Free</span>
                  <span class="mr-3 small"><span class="badge badge-danger">&nbsp;</span> Booked</span>
                  <span class="mr-3 small"><span class="badge badge-warning">&nbsp;</span> Time Off</span>
                  <span class="mr-3 small"><span class="badge badge-secondary">&nbsp;</span> Not Scheduled</span>
                  <?php if ($canCreate): ?>
                  <button class="btn btn-sm btn-danger ml-2" id="btnOpenReserveModal" disabled>
                    <i class="fa fa-check mr-1"></i> Reserve Selected (<span id="selectedCount">0</span>)
                  </button>
                  <?php endif; ?>
                </div>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <div class="d-flex" id="matrixContainer" style="overflow-x: auto; min-width: 100%;">
                    <!-- Rendered dynamically -->
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Section 2: Reservations List -->
          <div id="sectionList" style="display: none;">
            <div class="card shadow-sm border-0 mb-4">
              <div class="card-header bg-white py-3">
                <div class="row align-items-center">
                  <div class="col-md-3 mb-2 mb-md-0">
                    <label class="small text-muted font-weight-bold mb-1">Filter Property</label>
                    <select class="form-control form-control-sm" id="filterListUnit">
                      <option value="">All Properties</option>
                      <?php foreach ($units as $u): ?>
                        <option value="<?php echo (int) $u['id']; ?>"><?php echo htmlspecialchars($u['nombre']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-3 mb-2 mb-md-0">
                    <label class="small text-muted font-weight-bold mb-1">Status</label>
                    <select class="form-control form-control-sm" id="filterListStatus">
                      <option value="">All Statuses</option>
                      <option value="confirmed">Confirmed</option>
                      <option value="fulfilled">Fulfilled</option>
                      <option value="cancelled">Cancelled</option>
                    </select>
                  </div>
                  <div class="col-md-3 mb-2 mb-md-0">
                    <label class="small text-muted font-weight-bold mb-1">From Date</label>
                    <input type="date" class="form-control form-control-sm" id="filterListDate" value="<?php echo date('Y-m-d'); ?>">
                  </div>
                  <div class="col-md-3 d-flex justify-content-end align-items-end mt-2 mt-md-0">
                    <button class="btn btn-sm btn-outline-secondary" id="btnRefreshList">
                      <i class="fa fa-refresh mr-1"></i> Refresh
                    </button>
                  </div>
                </div>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-hover mb-0" id="tableReservations">
                    <thead class="thead-light">
                      <tr>
                        <th>Code</th>
                        <th>Work Date</th>
                        <th>Property</th>
                        <th>Worker</th>
                        <th>Part</th>
                        <th>Status</th>
                        <th>Linked Task</th>
                        <th>Notes</th>
                        <th class="text-right">Actions</th>
                      </tr>
                    </thead>
                    <tbody id="reservationsRows">
                      <tr><td colspan="9" class="text-center py-4 text-muted"><i class="fa fa-spinner fa-spin mr-2"></i>Loading reservations...</td></tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>

  <?php if ($canCreate): ?>
  <!-- Modal Reserve Slots -->
  <div class="modal fade" id="modalReserve" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <form id="formReserve">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
          <input type="hidden" name="employee_id" id="rsvEmployeeId">
          <input type="hidden" name="unidad_id" id="rsvUnidadId">
          <input type="hidden" name="day_part" id="rsvDayPart" value="full">
          <div class="modal-header">
            <h5 class="modal-title font-weight-bold"><i class="fa fa-calendar-check-o text-danger mr-2"></i>Confirm Reservation</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <p class="mb-2">Property: <strong id="rsvUnitName">-</strong></p>
            <p class="mb-3">Worker: <strong id="rsvWorkerName">-</strong></p>

            <div class="alert alert-light border py-2 mb-3">
              <span class="small font-weight-bold text-muted d-block mb-1">Selected Dates:</span>
              <div id="rsvDatesList" class="small text-dark font-weight-bold"></div>
            </div>

            <div class="form-group">
              <label class="font-weight-bold">Link Task ID (Optional)</label>
              <input type="number" class="form-control" name="task_id" id="rsvTaskId" placeholder="e.g. 104">
              <small class="form-text text-muted">Optional task to link this reservation with.</small>
            </div>

            <div class="form-group">
              <label class="font-weight-bold">Notes</label>
              <textarea class="form-control" name="notes" rows="2" placeholder="e.g. Electrical maintenance shift"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger" id="btnConfirmReserve">Confirm Reservation</button>
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
  window.CAN_CREATE_RSV = <?php echo $canCreate ? 'true' : 'false'; ?>;
  window.CAN_MANAGE_RSV = <?php echo $canManage ? 'true' : 'false'; ?>;
</script>
<script src="admin/js/reservations.js?v=<?php echo time(); ?>"></script>
</body>
</html>
