<?php
require './admin/include/generic_classes.php';
requireAnyPermission(['solicitudes.responders.manage', 'solicitudes.settings.manage']);
require_once './admin/bootstrap_pgs.php';
$isSuper = SessionData::superAdministrador();
$canResponders = SessionData::hasPermission('solicitudes.responders.manage') || $isSuper;
$canSettings = SessionData::hasPermission('solicitudes.settings.manage') || $isSuper;
$modulo = 'Voice Settings';
$days = ['mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday', 'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include './admin/include/generic_head.php'; ?>
  <meta name="csrf-token" content="<?php echo htmlspecialchars(\Pgs\Core\Csrf::token()); ?>">
  <title>Voice Settings</title>
  <style>
    .pgs-ui{ --brand:#E10600; --brand-dark:#B30500; --ink:#0B0F19; --muted:#64748b; --bg:#F6F7FB; --card:#fff; --border:#e7eaf1; }
    .pgs-ui .page-wrap{ padding: 6px 0 18px; }
    .pgs-ui .hero{
      border-radius: 24px; padding: 18px 20px; margin-bottom: 16px;
      background: linear-gradient(135deg, #0B0F19 0%, #1a1f2e 60%, #E10600 160%);
      color:#fff; box-shadow: 0 18px 60px rgba(2,6,23,.12);
    }
    .pgs-ui .hero h3{ margin:0; font-weight:1000; color:#fff; }
    .pgs-ui .hero p{ margin:6px 0 0; opacity:.85; }
    .pgs-ui .card{ border:1px solid var(--border); border-radius:18px; box-shadow:0 10px 26px rgba(2,6,23,.06); }
    .pgs-ui .chip{ display:inline-flex; align-items:center; gap:6px; border:1px solid var(--border); background:#fff; border-radius:999px; padding:6px 12px; margin:0 8px 8px 0; font-weight:700; font-size:13px; color:var(--ink); }
    .pgs-ui .chip.ok i{ color:#0B0F19; }
    .pgs-ui .chip.bad{ border-color:#B30500; color:#B30500; background:rgba(225,6,0,.08); }
    .pgs-ui .nav-tabs .nav-link{ font-weight:800; color:var(--muted); }
    .pgs-ui .nav-tabs .nav-link.active{ color:var(--brand); border-color:var(--border) var(--border) #fff; }
    .pgs-ui .pill{ display:inline-block; padding:2px 9px; border-radius:999px; font-size:11px; font-weight:800; margin:0 3px 3px 0; background:#F6F7FB; color:var(--ink); }
    .pgs-ui .pill.owner{ background:#0B0F19; color:#fff; }
    .pgs-ui .pill.off{ background:#F6F7FB; color:var(--muted); }
    .pgs-ui .muted{ color:var(--muted); }
    .pgs-ui label.lbl{ font-weight:800; font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); margin-bottom:4px; }
    .pgs-ui .help{ font-size:12px; color:var(--muted); }
    .pgs-ui .day-row{ display:flex; align-items:center; gap:10px; margin-bottom:8px; }
    .pgs-ui .day-row .name{ width:110px; font-weight:800; }
    .pgs-ui .day-row input[type=time]{ width:130px; }
    .pgs-ui .is-invalid-msg{ color:#B30500; font-size:12px; font-weight:700; }
  </style>
</head>
<body>
<div id="main-wrapper">
  <?php include './admin/include/generic_header.php'; ?>

  <div class="deznav">
    <div class="deznav-scroll">
      <?php include './admin/include/generic_navbar.php'; ?>
    </div>
  </div>

  <div class="content-body">
    <div class="container-fluid">
      <div class="pgs-ui">
        <div class="page-wrap">
          <div class="page-titles">
            <ol class="breadcrumb">
              <li class="breadcrumb-item"><a href="javascript:void(0)">Home</a></li>
              <li class="breadcrumb-item active"><a href="javascript:void(0)"><?php echo htmlspecialchars($modulo); ?></a></li>
            </ol>
          </div>

          <div class="hero">
            <h3><?php echo htmlspecialchars($modulo); ?></h3>
            <p>Who answers urgent calls, who is alerted, and when requests are escalated.</p>
          </div>

          <div id="statusChips" class="mb-2"></div>

          <div class="card">
            <div class="card-body">
              <ul class="nav nav-tabs mb-3" role="tablist">
                <?php if ($canResponders): ?>
                <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tabResponders" role="tab">Responders</a></li>
                <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tabPhones" role="tab">Property phones</a></li>
                <?php endif; ?>
                <?php if ($canSettings): ?>
                <li class="nav-item"><a class="nav-link <?php echo $canResponders ? '' : 'active'; ?>" data-toggle="tab" href="#tabSettings" role="tab">Escalation &amp; hours</a></li>
                <?php endif; ?>
              </ul>

              <div class="tab-content">
                <?php if ($canResponders): ?>
                <div class="tab-pane fade show active" id="tabResponders" role="tabpanel">
                  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                    <p class="muted mb-2 mb-md-0" style="max-width:640px;">Responders receive transferred calls and request alerts. Lower priority numbers are tried first. The owner is always alerted and is the fallback for urgent calls.</p>
                    <button type="button" class="btn btn-danger" id="btnNewResponder" style="font-weight:900;">New responder</button>
                  </div>
                  <div class="table-responsive">
                    <table class="table table-hover mb-0" id="tablaResponders">
                      <thead><tr><th>Order</th><th>Name</th><th>Phone</th><th>Email</th><th>Receives</th><th>Schedule</th><th></th></tr></thead>
                      <tbody></tbody>
                    </table>
                  </div>
                </div>

                <div class="tab-pane fade" id="tabPhones" role="tabpanel">
                  <div class="form-row align-items-end mb-3">
                    <div class="col-md-4 mb-2"><label class="lbl">Property</label><select class="form-control" id="phProperty"></select></div>
                    <div class="col-md-3 mb-2"><label class="lbl">Phone</label><input type="text" class="form-control" id="phNumber" placeholder="(407) 555-0123"></div>
                    <div class="col-md-3 mb-2"><label class="lbl">Label</label><input type="text" class="form-control" id="phLabel" maxlength="100" placeholder="Office, manager..."></div>
                    <div class="col-md-2 mb-2"><button type="button" class="btn btn-danger btn-block" id="btnAddPhone" style="font-weight:900;">Add phone</button></div>
                  </div>
                  <div class="form-row mb-2">
                    <div class="col-md-5 mb-2"><input type="text" class="form-control" id="phSearch" placeholder="Search phone, property or label"></div>
                    <div class="col-md-3 mb-2">
                      <select class="form-control" id="phKind"><option value="">Properties and staff</option><option value="property">Properties</option><option value="staff">Staff</option></select>
                    </div>
                  </div>
                  <div class="table-responsive">
                    <table class="table table-hover mb-0" id="tablaPhones">
                      <thead><tr><th>Phone</th><th>Belongs to</th><th>Label</th><th>Source</th><th>Active</th><th></th></tr></thead>
                      <tbody></tbody>
                    </table>
                  </div>

                  <h6 class="mt-4" style="font-weight:900;">Numbers that could not be used <span class="muted" id="discardCount"></span></h6>
                  <p class="help">These legacy numbers were not valid phone numbers, so callers cannot be matched to them. Fix a number to add it to its property.</p>
                  <div class="table-responsive">
                    <table class="table table-sm mb-0" id="tablaDiscards">
                      <thead><tr><th>Source</th><th>Belongs to</th><th>Value found</th><th>Reason</th><th></th></tr></thead>
                      <tbody></tbody>
                    </table>
                  </div>
                </div>
                <?php endif; ?>

                <?php if ($canSettings): ?>
                <div class="tab-pane fade <?php echo $canResponders ? '' : 'show active'; ?>" id="tabSettings" role="tabpanel">
                  <form id="formSettings" autocomplete="off">
                    <div class="row">
                      <div class="col-lg-6">
                        <h6 style="font-weight:900;">Voice assistant</h6>
                        <div class="custom-control custom-switch mb-3">
                          <input type="checkbox" class="custom-control-input" id="sVoiceEnabled">
                          <label class="custom-control-label" for="sVoiceEnabled" style="font-weight:700;">Assistant enabled</label>
                          <div class="help">When off, the assistant only takes a generic message and nothing is registered.</div>
                        </div>

                        <h6 style="font-weight:900;" class="mt-4">Escalation timing (minutes)</h6>
                        <div class="form-row">
                          <div class="col-6 mb-2"><label class="lbl">Reminder after</label><input type="number" min="1" max="1440" class="form-control" id="sRemind"></div>
                          <div class="col-6 mb-2"><label class="lbl">Escalate after</label><input type="number" min="1" max="1440" class="form-control" id="sEscalate"></div>
                          <div class="col-6 mb-2"><label class="lbl">Urgent: reminder after</label><input type="number" min="1" max="1440" class="form-control" id="sUrgentRemind"></div>
                          <div class="col-6 mb-2"><label class="lbl">Urgent: escalate after</label><input type="number" min="1" max="1440" class="form-control" id="sUrgentEscalate"></div>
                          <div class="col-6 mb-2"><label class="lbl">Repeat every</label><input type="number" min="1" max="1440" class="form-control" id="sRepeat"></div>
                          <div class="col-6 mb-2"><label class="lbl">Maximum alerts</label><input type="number" min="0" max="20" class="form-control" id="sMaxRepeats"></div>
                        </div>
                        <p class="help">A request nobody acknowledges is reminded to the responders, then escalated to the people below. Alerts stop as soon as someone acknowledges or changes its status.</p>

                        <h6 style="font-weight:900;" class="mt-4">Escalate to</h6>
                        <div class="form-group">
                          <label class="lbl">System users</label>
                          <select multiple class="form-control" id="sUsers" size="6"></select>
                          <div class="help">Hold Ctrl (or Cmd) to select several. They are emailed using the address in their login.</div>
                        </div>
                        <div class="form-group">
                          <label class="lbl">Extra email addresses</label>
                          <textarea class="form-control" id="sExtraEmails" rows="2" placeholder="owner@example.com, backup@example.com"></textarea>
                          <div class="help">Also used when no responder is configured.</div>
                        </div>
                      </div>

                      <div class="col-lg-6">
                        <h6 style="font-weight:900;">Business hours <span class="muted" id="sTimezone"></span></h6>
                        <p class="help">Outside these hours non-urgent reminders wait for the next opening. Urgent requests are never delayed.</p>
                        <?php foreach ($days as $key => $label): ?>
                        <div class="day-row" data-day="<?php echo $key; ?>">
                          <div class="name"><?php echo $label; ?></div>
                          <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input day-open" id="open_<?php echo $key; ?>">
                            <label class="custom-control-label" for="open_<?php echo $key; ?>">Open</label>
                          </div>
                          <input type="time" class="form-control day-from" aria-label="Opens">
                          <span>to</span>
                          <input type="time" class="form-control day-to" aria-label="Closes">
                        </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                    <div class="mt-3">
                      <button type="submit" class="btn btn-danger" id="btnSaveSettings" style="font-weight:900;">Save settings</button>
                    </div>
                  </form>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($canResponders): ?>
<div class="modal fade" id="modalResponder" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content pgs-ui">
      <div class="modal-header">
        <h5 class="modal-title" id="modalResponderTitle">Responder</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="rId">
        <div class="form-row">
          <div class="col-md-6 form-group"><label class="lbl">Name *</label><input type="text" class="form-control" id="rName" maxlength="120"></div>
          <div class="col-md-6 form-group"><label class="lbl">System user (optional)</label><select class="form-control" id="rUser"></select></div>
          <div class="col-md-4 form-group"><label class="lbl">Phone</label><input type="text" class="form-control" id="rPhone" placeholder="(407) 555-0123"></div>
          <div class="col-md-5 form-group"><label class="lbl">Email</label><input type="email" class="form-control" id="rEmail" maxlength="190"></div>
          <div class="col-md-3 form-group"><label class="lbl">Order</label><input type="number" class="form-control" id="rOrder" value="100"><div class="help">Lower is tried first</div></div>
        </div>
        <div class="form-group">
          <div class="custom-control custom-checkbox custom-control-inline"><input type="checkbox" class="custom-control-input" id="rTransfers"><label class="custom-control-label" for="rTransfers">Receives transferred calls</label></div>
          <div class="custom-control custom-checkbox custom-control-inline"><input type="checkbox" class="custom-control-input" id="rAlerts" checked><label class="custom-control-label" for="rAlerts">Receives alerts</label></div>
          <div class="custom-control custom-checkbox custom-control-inline"><input type="checkbox" class="custom-control-input" id="rOwner"><label class="custom-control-label" for="rOwner">Is the owner</label></div>
          <div class="custom-control custom-checkbox custom-control-inline"><input type="checkbox" class="custom-control-input" id="rActive" checked><label class="custom-control-label" for="rActive">Active</label></div>
        </div>
        <label class="lbl">Communities in charge</label>
        <div class="help mb-1">Urgent calls and alerts for a community go first to the people assigned to it. Leave all unchecked to cover every community.</div>
        <div class="form-row mb-3" id="rUnits" style="max-height:150px;overflow:auto;"></div>
        <label class="lbl">Available on</label>
        <div class="form-group" id="rDays">
          <?php $bit = 1; foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $d): ?>
          <div class="custom-control custom-checkbox custom-control-inline"><input type="checkbox" class="custom-control-input r-day" id="rDay<?php echo $bit; ?>" value="<?php echo $bit; ?>" checked><label class="custom-control-label" for="rDay<?php echo $bit; ?>"><?php echo $d; ?></label></div>
          <?php $bit *= 2; endforeach; ?>
        </div>
        <div class="form-row">
          <div class="col-6 col-md-3 form-group"><label class="lbl">From</label><input type="time" class="form-control" id="rFrom"></div>
          <div class="col-6 col-md-3 form-group"><label class="lbl">Until</label><input type="time" class="form-control" id="rTo"></div>
          <div class="col-md-6 help d-flex align-items-end pb-2">Leave both empty to be available all day. An end earlier than the start means the shift crosses midnight.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-dark" data-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="btnSaveResponder" style="font-weight:900;">Save</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php include './admin/include/gerenic_footer.php'; ?>
<?php include './admin/include/gerenic_script.php'; ?>
<script>
  window.PGS_VST = { responders: <?php echo $canResponders ? 'true' : 'false'; ?>, settings: <?php echo $canSettings ? 'true' : 'false'; ?> };
</script>
<script src="./admin/js/pgs_admin.js"></script>
<script src="./admin/js/voice_settings.js"></script>
</body>
</html>
