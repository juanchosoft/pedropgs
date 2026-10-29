<?php
require './admin/include/generic_classes.php';
requirePermission('notificaciones.inbox.view');
require_once './admin/bootstrap_pgs.php';

$P = \Pgs\Core\Web\PgsPage::principal();
$modulo = 'Notifications';
$canManageDefaults = $P->isSystemAdmin() || $P->can('notificaciones.prefs.manage');
$eventTypes = [
  'task.created' => 'Task Created',
  'task.assigned' => 'Task Assigned to Worker',
  'task.status_changed' => 'Task Status Updated',
  'task.due_soon' => 'Task Due Soon Reminder',
  'service_request.created' => 'Service Request Submitted',
  'reservation.confirmed' => 'Worker Reservation Confirmed',
  'reservation.cancelled' => 'Worker Reservation Cancelled',
  'absence.requested' => 'Time Off Requested',
  'absence.decided' => 'Time Off Request Decided',
  'invoice.issued' => 'Invoice Issued',
  'invoice.overdue' => 'Invoice Overdue Notice',
  'document.delivery' => 'Document Sent / Delivered',
  'phone.message' => 'Phone Message Taken',
  'voice.pin_locked' => 'Voice PIN Locked',
  'expense.registered' => 'Expense Registered',
];
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

          <div class="hero d-flex align-items-center justify-content-between flex-wrap">
            <div>
              <h3>Notifications</h3>
              <p>Your inbox and the channels used to reach you.</p>
            </div>
            <div class="mt-2 mt-md-0">
              <span class="badge badge-danger" id="inboxUnreadBadge" style="font-size:14px;">0</span>
            </div>
          </div>

          <ul class="nav nav-tabs mb-4" id="ntfTabs" role="tablist">
            <li class="nav-item">
              <a class="nav-link active font-weight-bold" id="tab-inbox-link" data-toggle="tab" href="#pane-inbox" role="tab">
                <i class="fa fa-inbox mr-1"></i> Inbox
              </a>
            </li>
            <li class="nav-item">
              <a class="nav-link font-weight-bold" id="tab-prefs-link" data-toggle="tab" href="#pane-prefs" role="tab">
                <i class="fa fa-sliders mr-1"></i> My Preferences
              </a>
            </li>
            <?php if ($canManageDefaults): ?>
            <li class="nav-item">
              <a class="nav-link font-weight-bold" id="tab-defaults-link" data-toggle="tab" href="#pane-defaults" role="tab">
                <i class="fa fa-cogs mr-1"></i> Defaults by User Type
              </a>
            </li>
            <?php endif; ?>
          </ul>

          <div class="tab-content">

            <div class="tab-pane fade show active" id="pane-inbox" role="tabpanel">
              <div class="card border-0 shadow-sm" style="border-radius:16px;">
                <div class="card-body">
                  <div class="d-flex flex-wrap align-items-center mb-3">
                    <select id="filterStatus" class="form-control mr-2 mb-2" style="max-width:180px;">
                      <option value="">All</option>
                      <option value="1">Unread only</option>
                    </select>
                    <select id="filterEventType" class="form-control mr-2 mb-2" style="max-width:260px;">
                      <option value="">All events</option>
                      <?php foreach ($eventTypes as $key => $label): ?>
                      <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-outline-secondary mr-2 mb-2" id="btnRefreshInbox">
                      <i class="fa fa-refresh mr-1"></i> Refresh
                    </button>
                    <button type="button" class="btn btn-danger mb-2 ml-auto" id="btnMarkAllRead">
                      <i class="fa fa-check mr-1"></i> Mark all as read
                    </button>
                  </div>
                  <div class="table-responsive">
                    <table class="table table-hover mb-0">
                      <thead>
                        <tr>
                          <th style="width:56px;"></th>
                          <th>Notification</th>
                          <th style="width:170px;">Date</th>
                          <th style="width:90px;"></th>
                        </tr>
                      </thead>
                      <tbody id="inboxListBody"></tbody>
                    </table>
                  </div>
                  <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap">
                    <small class="text-muted" id="inboxPaginationInfo"></small>
                    <nav><ul class="pagination mb-0" id="inboxPaginationNav"></ul></nav>
                  </div>
                </div>
              </div>
            </div>

            <div class="tab-pane fade" id="pane-prefs" role="tabpanel">
              <div class="card border-0 shadow-sm" style="border-radius:16px;">
                <div class="card-body">
                  <p class="text-muted">Choose how you want to be notified for each event.</p>
                  <div class="table-responsive">
                    <table class="table mb-0">
                      <thead>
                        <tr>
                          <th>Event</th>
                          <th class="text-center">In-app</th>
                          <th class="text-center">Email</th>
                          <th class="text-center">WhatsApp</th>
                        </tr>
                      </thead>
                      <tbody id="prefsTableBody"></tbody>
                    </table>
                  </div>
                  <div class="text-right mt-3">
                    <button type="button" class="btn btn-danger" id="btnSavePrefs">
                      <i class="fa fa-save mr-1"></i> Save Preferences
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <?php if ($canManageDefaults): ?>
            <div class="tab-pane fade" id="pane-defaults" role="tabpanel">
              <div class="card border-0 shadow-sm" style="border-radius:16px;">
                <div class="card-body">
                  <p class="text-muted">Default channels applied to users who have not set their own preferences.</p>
                  <?php
                  $groups = ['defaultsSuperBody' => 'System Administrators', 'defaultsAdminBody' => 'Property Administrators', 'defaultsStaffBody' => 'Staff'];
                  foreach ($groups as $bodyId => $title): ?>
                  <h5 class="mt-4 mb-2"><?php echo htmlspecialchars($title); ?></h5>
                  <div class="table-responsive">
                    <table class="table mb-0">
                      <thead>
                        <tr>
                          <th>Event</th>
                          <th class="text-center">In-app</th>
                          <th class="text-center">Email</th>
                          <th class="text-center">WhatsApp</th>
                        </tr>
                      </thead>
                      <tbody id="<?php echo $bodyId; ?>"></tbody>
                    </table>
                  </div>
                  <?php endforeach; ?>
                  <div class="text-right mt-3">
                    <button type="button" class="btn btn-danger" id="btnSaveDefaults">
                      <i class="fa fa-save mr-1"></i> Save Defaults
                    </button>
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

  <?php include './admin/include/gerenic_footer.php'; ?>
</div>
<?php include './admin/include/gerenic_script.php'; ?>
<script src="admin/js/notifications.js?v=<?php echo time(); ?>"></script>
</body>
</html>
