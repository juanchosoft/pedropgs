<?php
require './admin/include/generic_classes.php';
requirePermission('disponibilidad.schedule.view');
require_once './admin/bootstrap_pgs.php';

$P = \Pgs\Core\Web\PgsPage::principal();
$canManage = $P->can('disponibilidad.schedule.manage') || $P->isSystemAdmin();
$modulo = 'Worker Schedules';

$pdo = \Pgs\Core\Db::pdo();

// Units scoped to user
$unitParams = [];
if ($P->isSystemAdmin() || $P->can('tareas.tasks.view_all')) {
    $units = $pdo->query('SELECT id, nombre FROM tbl_unidades ORDER BY nombre ASC')->fetchAll();
} else {
    $filter = \Pgs\Auth\UnitScope::sqlFilter($P, 'id', $unitParams);
    $uStmt = $pdo->prepare("SELECT id, nombre FROM tbl_unidades WHERE {$filter} ORDER BY nombre ASC");
    $uStmt->execute($unitParams);
    $units = $uStmt->fetchAll();
}

$workers = \Pgs\Workers\EmployeeScope::listFor($P, null, $pdo);
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
              <h3>Worker Schedules</h3>
              <p>Weekly recurring work schedules per property and technician.</p>
            </div>
            <?php if ($canManage): ?>
            <div class="mt-2 mt-md-0">
              <button class="btn btn-danger btn-lg shadow-sm" id="btnNewSchedule">
                <i class="fa fa-plus mr-1"></i> Add Schedule
              </button>
            </div>
            <?php endif; ?>
          </div>

          <!-- Filter Bar -->
          <div class="card mb-4 p-3 shadow-sm border-0">
            <div class="row align-items-center">
              <div class="col-md-5 mb-2 mb-md-0">
                <label class="small text-muted font-weight-bold mb-1">Property</label>
                <select class="form-control" id="filterUnit">
                  <option value="">Select a property to view schedule...</option>
                  <?php foreach ($units as $u): ?>
                    <option value="<?php echo (int) $u['id']; ?>"><?php echo htmlspecialchars($u['nombre']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-5 mb-2 mb-md-0">
                <label class="small text-muted font-weight-bold mb-1">Worker (Optional)</label>
                <select class="form-control" id="filterWorker">
                  <option value="">All Workers</option>
                  <?php foreach ($workers as $w): ?>
                    <option value="<?php echo (int) $w['id']; ?>"><?php echo htmlspecialchars($w['nombre']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2 mt-md-4">
                <button class="btn btn-outline-secondary btn-block" id="btnResetFilters">
                  <i class="fa fa-refresh mr-1"></i> Reset
                </button>
              </div>
            </div>
          </div>

          <!-- Schedule Grid / Table Card -->
          <div class="card shadow-sm border-0">
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover mb-0" id="tableSchedule">
                  <thead class="thead-light">
                    <tr>
                      <th style="width: 140px;">Day</th>
                      <th>Worker</th>
                      <th style="width: 120px;">Part</th>
                      <th style="width: 130px;">Valid From</th>
                      <th style="width: 130px;">Valid To</th>
                      <th>Notes</th>
                      <?php if ($canManage): ?>
                      <th style="width: 80px;" class="text-right">Action</th>
                      <?php endif; ?>
                    </tr>
                  </thead>
                  <tbody id="scheduleRows">
                    <tr>
                      <td colspan="<?php echo $canManage ? '7' : '6'; ?>" class="text-center py-5 text-muted">
                        <i class="fa fa-calendar fa-2x mb-2 d-block text-secondary"></i>
                        Please select a property or worker to display the recurring schedule.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>

  <?php if ($canManage): ?>
  <!-- Modal Add Schedule -->
  <div class="modal fade" id="modalSchedule" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
      <div class="modal-content">
        <form id="formSchedule">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
          <div class="modal-header">
            <h5 class="modal-title font-weight-bold"><i class="fa fa-calendar-plus-o text-danger mr-2"></i>Add Worker Schedule</h5>
            <button type="button" class="close" data-dismiss="modal">&times;</button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label class="font-weight-bold">Property <span class="text-danger">*</span></label>
              <select class="form-control" name="unidad_id" id="modalUnit" required>
                <option value="">Select Property...</option>
                <?php foreach ($units as $u): ?>
                  <option value="<?php echo (int) $u['id']; ?>"><?php echo htmlspecialchars($u['nombre']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="font-weight-bold">Worker <span class="text-danger">*</span></label>
              <select class="form-control" name="employee_id" id="modalWorker" required>
                <option value="">Select Worker...</option>
                <?php foreach ($workers as $w): ?>
                  <option value="<?php echo (int) $w['id']; ?>"><?php echo htmlspecialchars($w['nombre']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="font-weight-bold">Days of the Week <span class="text-danger">*</span></label>
              <div class="d-flex flex-wrap" style="gap: 10px;">
                <label class="btn btn-outline-secondary btn-sm"><input type="checkbox" name="weekdays[]" value="1"> Mon</label>
                <label class="btn btn-outline-secondary btn-sm"><input type="checkbox" name="weekdays[]" value="2"> Tue</label>
                <label class="btn btn-outline-secondary btn-sm"><input type="checkbox" name="weekdays[]" value="3"> Wed</label>
                <label class="btn btn-outline-secondary btn-sm"><input type="checkbox" name="weekdays[]" value="4"> Thu</label>
                <label class="btn btn-outline-secondary btn-sm"><input type="checkbox" name="weekdays[]" value="5"> Fri</label>
                <label class="btn btn-outline-secondary btn-sm"><input type="checkbox" name="weekdays[]" value="6"> Sat</label>
                <label class="btn btn-outline-secondary btn-sm"><input type="checkbox" name="weekdays[]" value="7"> Sun</label>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-6">
                <label class="font-weight-bold">Day Part</label>
                <select class="form-control" name="day_part">
                  <option value="full">Full Day</option>
                  <option value="am">Morning (AM)</option>
                  <option value="pm">Afternoon (PM)</option>
                </select>
              </div>
              <div class="form-group col-md-6">
                <label class="font-weight-bold">Valid From</label>
                <input type="date" class="form-control" name="valid_from" value="<?php echo date('Y-m-d'); ?>" required>
              </div>
            </div>

            <div class="form-group">
              <label class="font-weight-bold">Valid To (Optional)</label>
              <input type="date" class="form-control" name="valid_to" placeholder="Leave empty for ongoing">
              <small class="form-text text-muted">Leave empty if recurring indefinitely.</small>
            </div>

            <div class="form-group">
              <label class="font-weight-bold">Notes</label>
              <input type="text" class="form-control" name="notes" placeholder="e.g. Standard maintenance shift">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger" id="btnSaveSchedule">Save Schedule</button>
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
  window.CAN_MANAGE_SCHEDULE = <?php echo $canManage ? 'true' : 'false'; ?>;
</script>
<script src="admin/js/worker_schedule.js?v=<?php echo time(); ?>"></script>
</body>
</html>
