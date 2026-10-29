<?php
require './admin/include/generic_classes.php';
requireAnyPermission(['tareas.tasks.view', 'tareas.tasks.view_all']);
require_once './admin/bootstrap_pgs.php';

$P = \Pgs\Core\Web\PgsPage::principal();
$canCreate = $P->can('tareas.tasks.create');
$canAssign = $P->can('tareas.tasks.assign');
$canServiceRequest = $P->can('servicios.requests.create');
$canViewAll = $P->can('tareas.tasks.view_all') || $P->isSystemAdmin();
$isEmployee = $P->employeeId !== null;
$modulo = 'Tasks';

$pdo = \Pgs\Core\Db::pdo();

// Properties in scope
$unitParams = [];
$unitFilter = \Pgs\Auth\UnitScope::sqlFilter($P, 'id', $unitParams);
$unitsStmt = $pdo->prepare("SELECT id, nombre FROM tbl_unidades WHERE {$unitFilter} ORDER BY nombre ASC");
$unitsStmt->execute($unitParams);
$units = $unitsStmt->fetchAll(\PDO::FETCH_ASSOC);

// Employees for assign filter
$employees = [];
if ($canAssign || $canViewAll) {
    $employees = \Pgs\Workers\EmployeeScope::listFor($P, null, $pdo);
}

// Active services for creation modal
$services = $pdo->query("SELECT id, code, name_en FROM tec_services WHERE is_active = 1 AND deleted_at IS NULL ORDER BY name_en ASC")->fetchAll(\PDO::FETCH_ASSOC);
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
              <h3>Tasks</h3>
              <p>Work orders, maintenance requests, and field assignments.</p>
            </div>
            <div class="mt-2 mt-md-0 d-flex gap-2">
              <?php if ($canServiceRequest): ?>
              <a href="service_request.php" class="btn btn-outline-secondary btn-lg mr-2">
                <i class="fa fa-concierge-bell mr-1"></i> New Service Request
              </a>
              <?php endif; ?>
              <?php if ($canCreate): ?>
              <button class="btn btn-danger btn-lg shadow-sm" id="btnNewTask">
                <i class="fa fa-plus mr-1"></i> New Task
              </button>
              <?php endif; ?>
            </div>
          </div>

          <!-- KPI summary counters -->
          <div class="row mb-4" id="taskKpis">
            <div class="col-md-2 col-sm-4 col-6 mb-2">
              <div class="card p-3 text-center border-0 shadow-sm" style="background:#fff; border-radius:16px;">
                <span class="text-muted small font-weight-bold">PENDING</span>
                <h3 class="mb-0 mt-1 font-weight-bold" id="kpiPending" style="color:var(--brand);">-</h3>
              </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6 mb-2">
              <div class="card p-3 text-center border-0 shadow-sm" style="background:#fff; border-radius:16px;">
                <span class="text-muted small font-weight-bold">ASSIGNED</span>
                <h3 class="mb-0 mt-1 font-weight-bold" id="kpiAssigned" style="color:#0B0F19;">-</h3>
              </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6 mb-2">
              <div class="card p-3 text-center border-0 shadow-sm" style="background:#fff; border-radius:16px;">
                <span class="text-muted small font-weight-bold">IN PROGRESS</span>
                <h3 class="mb-0 mt-1 font-weight-bold" id="kpiInProgress" style="color:#ef233c;">-</h3>
              </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6 mb-2">
              <div class="card p-3 text-center border-0 shadow-sm" style="background:#fff; border-radius:16px;">
                <span class="text-muted small font-weight-bold">COMPLETED</span>
                <h3 class="mb-0 mt-1 font-weight-bold" id="kpiCompleted" style="color:#2b2d42;">-</h3>
              </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6 mb-2">
              <div class="card p-3 text-center border-0 shadow-sm" style="background:#fff; border-radius:16px;">
                <span class="text-muted small font-weight-bold">CANCELLED</span>
                <h3 class="mb-0 mt-1 font-weight-bold" id="kpiCancelled" style="color:#8d99ae;">-</h3>
              </div>
            </div>
            <div class="col-md-2 col-sm-4 col-6 mb-2">
              <div class="card p-3 text-center border-0 shadow-sm" style="background:#fff; border-radius:16px;">
                <span class="text-muted small font-weight-bold">OPEN TOTAL</span>
                <h3 class="mb-0 mt-1 font-weight-bold" id="kpiOpenTotal" style="color:var(--brand2);">-</h3>
              </div>
            </div>
          </div>

          <!-- Filters Section -->
          <div class="card mb-4 p-3 shadow-sm border-0" style="border-radius:18px;">
            <div class="row">
              <div class="col-md-2 col-sm-6 mb-2">
                <label class="small text-muted font-weight-bold mb-1">Status</label>
                <select class="form-control" id="filterStatus">
                  <option value="">All Statuses</option>
                  <option value="pending">Pending</option>
                  <option value="assigned">Assigned</option>
                  <option value="in_progress">In Progress</option>
                  <option value="completed">Completed</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </div>
              <div class="col-md-2 col-sm-6 mb-2">
                <label class="small text-muted font-weight-bold mb-1">Property</label>
                <select class="form-control" id="filterProperty">
                  <option value="">All Properties</option>
                  <?php foreach ($units as $u): ?>
                    <option value="<?php echo (int) $u['id']; ?>"><?php echo htmlspecialchars($u['nombre']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2 col-sm-6 mb-2">
                <label class="small text-muted font-weight-bold mb-1">Priority</label>
                <select class="form-control" id="filterPriority">
                  <option value="">All Priorities</option>
                  <option value="low">Low</option>
                  <option value="normal">Normal</option>
                  <option value="high">High</option>
                  <option value="urgent">Urgent</option>
                </select>
              </div>
              <?php if (!empty($employees)): ?>
              <div class="col-md-2 col-sm-6 mb-2">
                <label class="small text-muted font-weight-bold mb-1">Assignee</label>
                <select class="form-control" id="filterAssignee">
                  <option value="">All Assignees</option>
                  <?php foreach ($employees as $e): ?>
                    <option value="<?php echo (int) $e['id']; ?>"><?php echo htmlspecialchars($e['nombre']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php endif; ?>
              <div class="col-md-2 col-sm-6 mb-2">
                <label class="small text-muted font-weight-bold mb-1">Origin</label>
                <select class="form-control" id="filterOrigin">
                  <option value="">All Origins</option>
                  <option value="manual">Manual</option>
                  <option value="service_request">Service Request</option>
                  <option value="phone">Phone (Voice)</option>
                  <option value="assistant">AI Assistant</option>
                  <option value="migration">Migration</option>
                </select>
              </div>
              <div class="col-md-2 col-sm-6 mb-2 d-flex align-items-end">
                <?php if ($isEmployee): ?>
                <div class="custom-control custom-checkbox mb-2">
                  <input type="checkbox" class="custom-control-input" id="filterOnlyMine">
                  <label class="custom-control-label font-weight-bold" for="filterOnlyMine">Only Mine</label>
                </div>
                <?php endif; ?>
              </div>
            </div>
            <div class="row mt-2">
              <div class="col-md-6 col-sm-12 mb-2">
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text bg-white border-right-0"><i class="fa fa-search text-muted"></i></span>
                  </div>
                  <input type="text" class="form-control border-left-0" id="filterSearch" placeholder="Search by Code (TSK-...), Title, or Location...">
                </div>
              </div>
              <div class="col-md-3 col-sm-6 mb-2">
                <input type="date" class="form-control" id="filterCreatedFrom" placeholder="From Date">
              </div>
              <div class="col-md-3 col-sm-6 mb-2 d-flex">
                <input type="date" class="form-control mr-2" id="filterCreatedTo" placeholder="To Date">
                <button class="btn btn-outline-secondary" id="btnResetFilters" title="Reset Filters"><i class="fa fa-refresh"></i></button>
              </div>
            </div>
          </div>

          <!-- Tasks Table -->
          <div class="card p-0 shadow-sm border-0" style="border-radius:18px; overflow:hidden;">
            <div class="table-responsive">
              <table class="table table-hover mb-0" id="tblTasks">
                <thead style="background:#f8f9fa;">
                  <tr>
                    <th style="width:110px;">Code</th>
                    <th>Title</th>
                    <th>Property</th>
                    <th>Location</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Assignee</th>
                    <th>Due</th>
                    <th>Created</th>
                    <th class="text-right" style="width:100px;">Actions</th>
                  </tr>
                </thead>
                <tbody id="tblTasksBody">
                  <tr>
                    <td colspan="10" class="text-center py-4 text-muted">Loading tasks...</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <!-- Pagination Controls -->
            <div class="p-3 d-flex justify-content-between align-items-center flex-wrap" style="background:#fafafa; border-top:1px solid #eee;">
              <span class="small text-muted" id="paginationInfo">Showing 0 of 0 tasks</span>
              <ul class="pagination pagination-sm mb-0" id="paginationList"></ul>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<!-- Task Create/Edit Modal -->
<div class="modal fade" id="modalTask" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content border-0 shadow" style="border-radius:20px;">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title font-weight-bold" id="modalTaskTitle">New Task</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form id="formTask">
        <input type="hidden" id="taskId" name="id">
        <input type="hidden" id="taskRowVersion" name="row_version" value="1">
        <div class="modal-body pt-3">
          <div class="row">
            <div class="col-md-6 form-group">
              <label class="font-weight-bold small">Property <span class="text-danger">*</span></label>
              <select class="form-control" id="taskUnit" name="unidad_id" required>
                <option value="">Select Property...</option>
                <?php foreach ($units as $u): ?>
                  <option value="<?php echo (int) $u['id']; ?>"><?php echo htmlspecialchars($u['nombre']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 form-group">
              <label class="font-weight-bold small">Location Label</label>
              <input type="text" class="form-control" id="taskLocation" name="location_label" placeholder="e.g. Clubhouse Pool, Apt 104, Roof AC" maxlength="120">
            </div>
          </div>

          <div class="form-group">
            <label class="font-weight-bold small">Task Title <span class="text-danger">*</span></label>
            <input type="text" class="form-control font-weight-bold" id="taskTitle" name="title" placeholder="Brief summary of required work" required minlength="3" maxlength="160">
          </div>

          <div class="form-group">
            <label class="font-weight-bold small">Description</label>
            <textarea class="form-control" id="taskDescription" name="description" rows="3" placeholder="Detailed maintenance instructions or observations" maxlength="5000"></textarea>
          </div>

          <div class="row">
            <div class="col-md-4 form-group">
              <label class="font-weight-bold small">Priority</label>
              <select class="form-control" id="taskPriority" name="priority">
                <option value="low">Low</option>
                <option value="normal" selected>Normal</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
              </select>
            </div>
            <div class="col-md-4 form-group">
              <label class="font-weight-bold small">Required Date</label>
              <input type="date" class="form-control" id="taskRequiredDate" name="required_date">
            </div>
            <div class="col-md-4 form-group">
              <label class="font-weight-bold small">Due Date</label>
              <input type="date" class="form-control" id="taskDueDate" name="due_date">
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 form-group">
              <label class="font-weight-bold small">Service (Optional)</label>
              <select class="form-control" id="taskService" name="service_id">
                <option value="">None / Custom Task</option>
                <?php foreach ($services as $s): ?>
                  <option value="<?php echo (int) $s['id']; ?>">[<?php echo htmlspecialchars($s['code']); ?>] <?php echo htmlspecialchars($s['name_en']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php if ($canAssign): ?>
            <div class="col-md-6 form-group">
              <label class="font-weight-bold small">Assign Employee</label>
              <select class="form-control" id="taskAssignee" name="assignee_employee_id">
                <option value="">Unassigned</option>
                <?php foreach ($employees as $e): ?>
                  <option value="<?php echo (int) $e['id']; ?>"><?php echo htmlspecialchars($e['nombre']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger px-4" id="btnSaveTask">Save Task</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include './admin/include/gerenic_footer.php'; ?>
<?php include './admin/include/gerenic_script.php'; ?>

<script>
window.PGS_TASKS = {
  canCreate: <?php echo $canCreate ? 'true' : 'false'; ?>,
  canAssign: <?php echo $canAssign ? 'true' : 'false'; ?>,
  canViewAll: <?php echo $canViewAll ? 'true' : 'false'; ?>,
  isEmployee: <?php echo $isEmployee ? 'true' : 'false'; ?>
};
</script>
<script src="./admin/js/tasks.js"></script>
</body>
</html>
