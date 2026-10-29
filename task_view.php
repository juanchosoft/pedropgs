<?php
require './admin/include/generic_classes.php';
requireAnyPermission(['tareas.tasks.view', 'tareas.tasks.view_all']);
require_once './admin/bootstrap_pgs.php';

$P = \Pgs\Core\Web\PgsPage::principal();
$code = trim((string) ($_GET['code'] ?? ($_GET['id'] ?? '')));

if ($code === '') {
    header('Location: tasks.php');
    exit;
}

$taskService = new \Pgs\Tasks\TaskService();
try {
    $task = $taskService->get($P, $code);
} catch (\Pgs\Core\Exceptions\NotFoundException $e) {
    header('Location: tasks.php');
    exit;
} catch (\Pgs\Core\Exceptions\ForbiddenException $e) {
    require __DIR__ . '/permiso_denegado.php';
    exit;
}

$modulo = 'Task ' . $task['code'];
$canAssign = $P->can('tareas.tasks.assign');
$canUpdate = !empty($task['can_update']);
$canDelete = !empty($task['can_delete']);
$canSeeInternal = !empty($task['can_see_internal']);
$allowedTransitions = $task['allowed_transitions'] ?? [];

$pdo = \Pgs\Core\Db::pdo();
$employees = [];
if ($canAssign) {
    $employees = \Pgs\Workers\EmployeeScope::listFor($P, isset($task['unidad_id']) && $task['unidad_id'] !== null ? (int) $task['unidad_id'] : null, $pdo);
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

          <!-- Breadcrumb & Back button -->
          <div class="mb-3 d-flex align-items-center justify-content-between">
            <a href="tasks.php" class="btn btn-outline-secondary btn-sm">
              <i class="fa fa-arrow-left mr-1"></i> Back to Tasks
            </a>
            <div>
              <?php if ($canDelete): ?>
                <button class="btn btn-outline-danger btn-sm" id="btnDeleteTask" title="Delete Task">
                  <i class="fa fa-trash mr-1"></i> Delete
                </button>
              <?php endif; ?>
            </div>
          </div>

          <!-- Hero / Header -->
          <div class="hero mb-4">
            <div class="d-flex align-items-start justify-content-between flex-wrap">
              <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                  <h3 class="mb-0 font-weight-bold mr-3"><?php echo htmlspecialchars($task['code']); ?></h3>
                  <span id="badgeStatus" class="mr-2"></span>
                  <span id="badgePriority"></span>
                </div>
                <h4 class="font-weight-bold text-dark mt-2 mb-1" id="taskTitleHeader"><?php echo htmlspecialchars($task['title']); ?></h4>
                <p class="text-muted mb-0">
                  <i class="fa fa-building-o mr-1"></i> <?php echo htmlspecialchars($task['unidad_nombre'] ?: 'No Property'); ?>
                  <?php if (!empty($task['location_label'])): ?>
                    &bull; <i class="fa fa-map-marker mr-1"></i> <?php echo htmlspecialchars($task['location_label']); ?>
                  <?php endif; ?>
                </p>
              </div>

              <!-- Action buttons for State Machine transitions -->
              <div class="mt-3 mt-md-0 d-flex flex-wrap gap-2" id="actionButtons">
                <?php if (in_array('assigned', $allowedTransitions, true) && $canAssign): ?>
                  <button class="btn btn-primary mr-2 mb-2" id="btnOpenAssign">
                    <i class="fa fa-user mr-1"></i> Assign Employee
                  </button>
                <?php endif; ?>

                <?php if (in_array('in_progress', $allowedTransitions, true)): ?>
                  <button class="btn btn-danger mr-2 mb-2" data-status="in_progress" id="btnStartTask">
                    <i class="fa fa-play mr-1"></i> Start Work
                  </button>
                <?php endif; ?>

                <?php if (in_array('completed', $allowedTransitions, true)): ?>
                  <button class="btn btn-dark mr-2 mb-2" data-status="completed" id="btnCompleteTask">
                    <i class="fa fa-check mr-1"></i> Complete Task
                  </button>
                <?php endif; ?>

                <?php if (in_array('cancelled', $allowedTransitions, true)): ?>
                  <button class="btn btn-outline-danger mr-2 mb-2" id="btnCancelTask">
                    <i class="fa fa-times mr-1"></i> Cancel
                  </button>
                <?php endif; ?>

                <?php if (in_array('pending', $allowedTransitions, true) && $task['status'] === 'cancelled'): ?>
                  <button class="btn btn-outline-primary mr-2 mb-2" data-status="pending" id="btnReopenTask">
                    <i class="fa fa-undo mr-1"></i> Reopen Task
                  </button>
                <?php endif; ?>

                <?php if ($canUpdate): ?>
                  <button class="btn btn-outline-secondary mb-2" id="btnEditTask">
                    <i class="fa fa-edit mr-1"></i> Edit Details
                  </button>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Main Layout: 2 Columns -->
          <div class="row">
            <!-- Left Column: Details, Comments, Attachments -->
            <div class="col-lg-8">

              <!-- Task Details Card -->
              <div class="card mb-4 shadow-sm border-0" style="border-radius:18px;">
                <div class="card-body">
                  <h5 class="card-title font-weight-bold mb-3">Description & Information</h5>
                  <div class="p-3 bg-light rounded mb-3 text-dark" style="white-space:pre-wrap; font-size:14px; min-height:60px;">
                    <?php echo htmlspecialchars($task['description'] ?: 'No detailed description provided.'); ?>
                  </div>

                  <?php if (!empty($task['cancel_reason'])): ?>
                    <div class="alert alert-danger mb-3">
                      <strong>Cancellation Reason:</strong> <?php echo htmlspecialchars($task['cancel_reason']); ?>
                    </div>
                  <?php endif; ?>

                  <div class="row text-muted small">
                    <div class="col-sm-6 mb-2">
                      <strong>Service Offering:</strong>
                      <?php echo htmlspecialchars($task['service_name'] ?: 'None (Custom Request)'); ?>
                    </div>
                    <div class="col-sm-6 mb-2">
                      <strong>Origin:</strong>
                      <span class="badge badge-light border text-capitalize"><?php echo htmlspecialchars($task['origin']); ?></span>
                    </div>
                    <div class="col-sm-6 mb-2">
                      <strong>Required Date:</strong>
                      <?php echo htmlspecialchars($task['required_date'] ?: 'Not specified'); ?>
                    </div>
                    <div class="col-sm-6 mb-2">
                      <strong>Due Date:</strong>
                      <?php echo htmlspecialchars($task['due_date'] ?: 'Not specified'); ?>
                    </div>
                    <div class="col-sm-6 mb-2">
                      <strong>Requested By:</strong>
                      <?php
                        $req = htmlspecialchars($task['requested_by_name'] ?: ($task['requested_by_phone'] ? \Pgs\Core\Phone::mask($task['requested_by_phone']) : 'Unknown'));
                        echo $req . ' <span class="badge badge-secondary ml-1">' . htmlspecialchars($task['requested_verification']) . '</span>';
                      ?>
                    </div>
                    <div class="col-sm-6 mb-2">
                      <strong>Created:</strong>
                      <?php echo htmlspecialchars($task['created_at']); ?>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Attachments Card -->
              <div class="card mb-4 shadow-sm border-0" style="border-radius:18px;">
                <div class="card-header bg-white border-bottom-0 d-flex justify-content-between align-items-center">
                  <h5 class="font-weight-bold mb-0">Attachments (<?php echo count($task['attachments']); ?>)</h5>
                  <?php if ($canUpdate): ?>
                    <button class="btn btn-sm btn-outline-danger" id="btnUploadAttachment">
                      <i class="fa fa-upload mr-1"></i> Upload File
                    </button>
                    <input type="file" id="fileAttachmentInput" style="display:none;" accept="image/jpeg,image/png,application/pdf">
                  <?php endif; ?>
                </div>
                <div class="card-body pt-0">
                  <div id="attachmentsContainer" class="row">
                    <?php if (empty($task['attachments'])): ?>
                      <div class="col-12 text-muted small py-2">No attachments uploaded yet.</div>
                    <?php else: ?>
                      <?php foreach ($task['attachments'] as $att): ?>
                        <div class="col-md-6 mb-2 attachment-item" id="att-<?php echo (int) $att['id']; ?>">
                          <div class="p-2 border rounded d-flex align-items-center justify-content-between bg-light">
                            <div class="d-flex align-items-center text-truncate mr-2">
                              <i class="fa <?php echo str_contains($att['mime'], 'pdf') ? 'fa-file-pdf-o text-danger' : 'fa-file-image-o text-primary'; ?> fa-lg mr-2"></i>
                              <div class="text-truncate">
                                <a href="admin/ajax/file_download.php?type=task_attachment&id=<?php echo (int) $att['id']; ?>" target="_blank" class="font-weight-bold text-dark small text-truncate d-block">
                                  <?php echo htmlspecialchars($att['original_name']); ?>
                                </a>
                                <span class="text-muted" style="font-size:11px;"><?php echo round($att['size_bytes'] / 1024, 1); ?> KB</span>
                              </div>
                            </div>
                            <?php if ($canUpdate): ?>
                              <button type="button" class="btn btn-sm btn-link text-danger p-0 btnDeleteAtt" data-id="<?php echo (int) $att['id']; ?>" title="Remove">
                                <i class="fa fa-trash"></i>
                              </button>
                            <?php endif; ?>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>
                </div>
              </div>

              <!-- Comments Section Card -->
              <div class="card mb-4 shadow-sm border-0" style="border-radius:18px;">
                <div class="card-header bg-white border-bottom-0 pb-0">
                  <h5 class="font-weight-bold mb-0">Comments & Activity Notes</h5>
                </div>
                <div class="card-body">
                  <!-- Comment List -->
                  <div id="commentsList" class="mb-4">
                    <?php if (empty($task['comments'])): ?>
                      <p class="text-muted small" id="noCommentsMsg">No comments posted yet.</p>
                    <?php else: ?>
                      <?php foreach ($task['comments'] as $c): ?>
                        <div class="p-3 mb-2 rounded border <?php echo !empty($c['is_internal']) ? 'border-warning' : 'border-light'; ?>" style="background:<?php echo !empty($c['is_internal']) ? '#fffdf0' : '#f8f9fa'; ?>;">
                          <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="font-weight-bold text-dark small">
                              <?php echo htmlspecialchars($c['author_nickname'] ?: 'User #' . $c['author_user_id']); ?>
                              <?php if (!empty($c['is_internal'])): ?>
                                <span class="badge badge-warning text-dark ml-2">Internal Note</span>
                              <?php endif; ?>
                            </span>
                            <span class="text-muted" style="font-size:11px;"><?php echo htmlspecialchars($c['created_at']); ?></span>
                          </div>
                          <div class="text-dark small" style="white-space:pre-wrap;"><?php echo htmlspecialchars($c['body']); ?></div>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>

                  <!-- New Comment Form -->
                  <form id="formAddComment">
                    <div class="form-group mb-2">
                      <textarea class="form-control" id="commentBody" rows="2" placeholder="Write a comment or update note..." required maxlength="2000"></textarea>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                      <div>
                        <?php if ($canSeeInternal): ?>
                        <div class="custom-control custom-checkbox">
                          <input type="checkbox" class="custom-control-input" id="commentInternal">
                          <label class="custom-control-label small font-weight-bold text-muted" for="commentInternal">Internal only (hidden from HOA administrators)</label>
                        </div>
                        <?php endif; ?>
                      </div>
                      <button type="submit" class="btn btn-danger btn-sm px-3" id="btnSubmitComment">Post Comment</button>
                    </div>
                  </form>
                </div>
              </div>

            </div>

            <!-- Right Column: Assignee, Reservations & Timeline History -->
            <div class="col-lg-4">

              <!-- Assignee Card -->
              <div class="card mb-4 shadow-sm border-0" style="border-radius:18px;">
                <div class="card-body">
                  <h6 class="font-weight-bold text-muted small text-uppercase mb-3">Assigned Technician</h6>
                  <?php if (!empty($task['assignee_nombre'])): ?>
                    <div class="d-flex align-items-center">
                      <div class="mr-3 text-center" style="width:48px; height:48px; border-radius:50%; background:var(--brand); color:#fff; font-size:20px; line-height:48px;">
                        <i class="fa fa-user"></i>
                      </div>
                      <div>
                        <h6 class="mb-0 font-weight-bold"><?php echo htmlspecialchars($task['assignee_nombre']); ?></h6>
                        <span class="text-muted small">Assigned Staff</span>
                      </div>
                    </div>
                    <?php if ($canAssign): ?>
                      <div class="mt-3">
                        <button class="btn btn-outline-secondary btn-sm btn-block" id="btnReassign">Change Assignee</button>
                      </div>
                    <?php endif; ?>
                  <?php else: ?>
                    <div class="text-center py-3 text-muted">
                      <i class="fa fa-user-times fa-2x mb-2 text-muted"></i>
                      <p class="small mb-2">No technician assigned yet.</p>
                      <?php if ($canAssign): ?>
                        <button class="btn btn-primary btn-sm" id="btnAssignFirst">Assign Now</button>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Linked Reservations Card (if any) -->
              <?php if (!empty($task['reservations'])): ?>
              <div class="card mb-4 shadow-sm border-0" style="border-radius:18px;">
                <div class="card-body">
                  <h6 class="font-weight-bold text-muted small text-uppercase mb-3">Linked Schedule Slots</h6>
                  <ul class="list-unstyled mb-0">
                    <?php foreach ($task['reservations'] as $r): ?>
                      <li class="mb-2 p-2 border rounded bg-light small">
                        <strong><?php echo htmlspecialchars($r['work_date']); ?></strong> -
                        <span class="badge badge-info text-uppercase"><?php echo htmlspecialchars($r['day_part']); ?></span>
                        <div class="text-muted mt-1"><?php echo htmlspecialchars($r['employee_nombre']); ?></div>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              </div>
              <?php endif; ?>

              <!-- Audit History Timeline -->
              <div class="card shadow-sm border-0" style="border-radius:18px;">
                <div class="card-header bg-white border-bottom-0">
                  <h6 class="font-weight-bold text-muted small text-uppercase mb-0">History Timeline</h6>
                </div>
                <div class="card-body pt-0">
                  <div class="pgs-timeline">
                    <?php foreach ($task['history'] as $h): ?>
                      <div class="mb-3 pl-3 position-relative" style="border-left:2px solid #e2e8f0;">
                        <div class="small font-weight-bold text-dark text-capitalize">
                          <?php echo htmlspecialchars(str_replace('_', ' ', $h['event'])); ?>
                        </div>
                        <?php if (!empty($h['note'])): ?>
                          <div class="small text-muted"><?php echo htmlspecialchars($h['note']); ?></div>
                        <?php endif; ?>
                        <div class="text-muted" style="font-size:11px;">
                          <?php echo htmlspecialchars($h['actor_nickname'] ?: $h['actor_kind']); ?> &bull; <?php echo htmlspecialchars($h['created_at']); ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>

            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Assign Employee -->
<div class="modal fade" id="modalAssign" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content border-0 shadow" style="border-radius:18px;">
      <div class="modal-header border-bottom-0">
        <h5 class="modal-title font-weight-bold">Assign Task</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <form id="formAssign">
        <div class="modal-body pt-0">
          <div class="form-group">
            <label class="font-weight-bold small">Technician / Employee</label>
            <select class="form-control" id="selectAssignee" required>
              <option value="">Select Employee...</option>
              <?php
                $hasAssigned = false;
                foreach ($employees as $e) { if (!empty($e['assigned'])) { $hasAssigned = true; break; } }
                $preselect = !empty($task['assignee_employee_id']) ? (int) $task['assignee_employee_id'] : null;
                $groups = $hasAssigned
                    ? ['Assigned to this property' => 1, 'Other staff' => 0]
                    : ['Staff' => 0];
                foreach ($groups as $label => $flag):
                    $list = $hasAssigned ? array_filter($employees, static fn ($e) => (int) ($e['assigned'] ?? 0) === $flag) : $employees;
                    if (!$list) { continue; }
              ?>
                <optgroup label="<?php echo htmlspecialchars($label); ?>">
                  <?php foreach ($list as $e): ?>
                    <?php
                      $isSel = $preselect !== null
                          ? $preselect === (int) $e['id']
                          : ($flag === 1 && !isset($firstAssignedDone) && ($firstAssignedDone = true));
                    ?>
                    <option value="<?php echo (int) $e['id']; ?>" <?php echo $isSel ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($e['nombre']); ?>
                    </option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="font-weight-bold small">Assignment Note (Optional)</label>
            <input type="text" class="form-control" id="assignNote" placeholder="Instructions for technician">
          </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger" id="btnSaveAssign">Save Assignment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Cancellation Reason -->
<div class="modal fade" id="modalCancel" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content border-0 shadow" style="border-radius:18px;">
      <div class="modal-header border-bottom-0">
        <h5 class="modal-title font-weight-bold text-danger">Cancel Task</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <form id="formCancel">
        <div class="modal-body pt-0">
          <p class="small text-muted">Please provide a reason for cancelling this task (required):</p>
          <div class="form-group">
            <textarea class="form-control" id="cancelReasonInput" rows="3" placeholder="e.g. Duplicated request, cancelled by property manager..." required minlength="3" maxlength="255"></textarea>
          </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-danger" id="btnConfirmCancel">Confirm Cancellation</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include './admin/include/gerenic_footer.php'; ?>
<?php include './admin/include/gerenic_script.php'; ?>

<script>
window.TASK_DATA = <?php echo json_encode($task, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="./admin/js/task_view.js"></script>
</body>
</html>
