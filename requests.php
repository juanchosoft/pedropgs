<?php
require './admin/include/generic_classes.php';
requireAnyPermission(['solicitudes.requests.view', 'solicitudes.requests.manage']);
require_once './admin/bootstrap_pgs.php';
$canManage = SessionData::hasPermission('solicitudes.requests.manage') || SessionData::superAdministrador();
$modulo = 'Service Requests';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include './admin/include/generic_head.php'; ?>
  <meta name="csrf-token" content="<?php echo htmlspecialchars(\Pgs\Core\Csrf::token()); ?>">
  <title>Service Requests</title>
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
    .pgs-ui .kpi{ cursor:pointer; transition:transform .12s ease; }
    .pgs-ui .kpi:hover{ transform:translateY(-2px); }
    .pgs-ui .kpi .card-body{ padding:14px 16px; }
    .pgs-ui .kpi .n{ font-size:28px; font-weight:900; line-height:1; color:var(--ink); }
    .pgs-ui .kpi .l{ color:var(--muted); font-weight:700; font-size:12px; text-transform:uppercase; letter-spacing:.04em; }
    .pgs-ui .kpi.active{ border-color:var(--brand); box-shadow:0 10px 26px rgba(225,6,0,.16); }
    .pgs-ui .kpi.attention .n{ color:var(--brand); }
    .pgs-ui .pill{ display:inline-block; padding:3px 10px; border-radius:999px; font-size:12px; font-weight:800; }
    .pgs-ui .pri-urgent{ background:#B30500; color:#fff; }
    .pgs-ui .pri-high{ background:rgba(225,6,0,.08); color:#B30500; }
    .pgs-ui .pri-normal{ background:#F6F7FB; color:#0B0F19; }
    .pgs-ui .pri-low{ background:#F6F7FB; color:#64748b; }
    .pgs-ui .st-received{ background:#E10600; color:#fff; }
    .pgs-ui .st-assigned{ background:#0B0F19; color:#fff; }
    .pgs-ui .st-in_progress{ background:rgba(225,6,0,.08); color:#B30500; }
    .pgs-ui .st-completed{ background:#e7eaf1; color:#0B0F19; }
    .pgs-ui .st-cancelled{ background:#F6F7FB; color:#64748b; }
    .pgs-ui .review-tag{ font-size:11px; font-weight:800; color:#B30500; border:1px solid #B30500; border-radius:6px; padding:1px 6px; margin-left:6px; }
    .pgs-ui .muted{ color:var(--muted); }
    .pgs-ui .summary-cell{ max-width:340px; }
    .pgs-ui .timeline{ list-style:none; margin:0; padding:0; }
    .pgs-ui .timeline li{ border-left:3px solid var(--border); padding:2px 0 10px 12px; margin-left:4px; }
    .pgs-ui .timeline li b{ color:var(--ink); }
    .pgs-ui .banner{ border-radius:12px; padding:10px 14px; margin-bottom:12px; font-weight:700; }
    .pgs-ui .banner.warn{ background:rgba(225,6,0,.08); color:#B30500; border:1px solid rgba(225,6,0,.3); }
    .pgs-ui .detail-label{ color:var(--muted); font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; margin:0; }
    .pgs-ui .detail-value{ font-weight:700; color:var(--ink); margin-bottom:10px; word-break:break-word; }
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

          <div class="hero d-flex justify-content-between align-items-center flex-wrap">
            <div>
              <h3><?php echo htmlspecialchars($modulo); ?></h3>
              <p>Every call becomes a request with a status. Nothing gets lost: unattended requests are escalated automatically.</p>
            </div>
            <button type="button" class="btn btn-light" id="btnRefresh" style="font-weight:900;"><i class="fa fa-refresh"></i> Refresh</button>
          </div>

          <div id="systemBanner"></div>

          <div class="row" id="kpis">
            <div class="col-6 col-md-2 mb-3"><div class="card kpi" data-status="open"><div class="card-body"><div class="n" id="kpiOpen">0</div><div class="l">Open</div></div></div></div>
            <div class="col-6 col-md-2 mb-3"><div class="card kpi attention" data-status="received"><div class="card-body"><div class="n" id="kpiReceived">0</div><div class="l">Received</div></div></div></div>
            <div class="col-6 col-md-2 mb-3"><div class="card kpi" data-status="assigned"><div class="card-body"><div class="n" id="kpiAssigned">0</div><div class="l">Assigned</div></div></div></div>
            <div class="col-6 col-md-2 mb-3"><div class="card kpi" data-status="in_progress"><div class="card-body"><div class="n" id="kpiProgress">0</div><div class="l">In progress</div></div></div></div>
            <div class="col-6 col-md-2 mb-3"><div class="card kpi" data-status="completed"><div class="card-body"><div class="n" id="kpiCompleted">0</div><div class="l">Completed</div></div></div></div>
            <div class="col-6 col-md-2 mb-3"><div class="card kpi" data-status="all"><div class="card-body"><div class="n" id="kpiReview">0</div><div class="l">Need review</div></div></div></div>
          </div>

          <div class="card">
            <div class="card-body">
              <div class="form-row mb-3">
                <div class="col-md-2 mb-2">
                  <select class="form-control" id="fStatus">
                    <option value="open">Open</option>
                    <option value="all">All statuses</option>
                    <option value="received">Received</option>
                    <option value="assigned">Assigned</option>
                    <option value="in_progress">In progress</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                  </select>
                </div>
                <div class="col-md-2 mb-2">
                  <select class="form-control" id="fPriority">
                    <option value="">All priorities</option>
                    <option value="urgent">Urgent</option>
                    <option value="high">High</option>
                    <option value="normal">Normal</option>
                    <option value="low">Low</option>
                  </select>
                </div>
                <div class="col-md-3 mb-2"><input type="text" class="form-control" id="fSearch" placeholder="Search code, property, caller or text"></div>
                <div class="col-6 col-md-2 mb-2"><input type="date" class="form-control" id="fFrom" title="From"></div>
                <div class="col-6 col-md-2 mb-2"><input type="date" class="form-control" id="fTo" title="To"></div>
                <div class="col-md-1 mb-2 d-flex align-items-center">
                  <label class="mb-0" style="font-weight:700;"><input type="checkbox" id="fReview"> Review</label>
                </div>
              </div>

              <div class="table-responsive">
                <table class="table table-hover mb-0" id="tablaRequests">
                  <thead>
                    <tr>
                      <th>Request</th>
                      <th>Priority</th>
                      <th>Property</th>
                      <th>Summary</th>
                      <th>Caller</th>
                      <th>Status</th>
                      <th>Assigned</th>
                      <th>Received</th>
                      <th></th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>

              <div class="d-flex justify-content-between align-items-center mt-3">
                <span class="muted" id="pageInfo"></span>
                <div>
                  <button type="button" class="btn btn-sm btn-outline-dark" id="btnPrev">Previous</button>
                  <button type="button" class="btn btn-sm btn-outline-dark" id="btnNext">Next</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalRequest" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content pgs-ui">
      <div class="modal-header">
        <h5 class="modal-title" id="modalRequestTitle">Request</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-7">
            <p class="detail-label">Summary</p>
            <p class="detail-value" id="dSummary"></p>
            <div id="dDetailsWrap" style="display:none;">
              <p class="detail-label">Details</p>
              <p class="detail-value" id="dDetails"></p>
            </div>
            <div class="row">
              <div class="col-6"><p class="detail-label">Property</p><p class="detail-value" id="dProperty"></p></div>
              <div class="col-6"><p class="detail-label">Priority</p><p class="detail-value" id="dPriority"></p></div>
              <div class="col-6"><p class="detail-label">Caller</p><p class="detail-value" id="dCaller"></p></div>
              <div class="col-6"><p class="detail-label">Received</p><p class="detail-value" id="dReceived"></p></div>
              <div class="col-6"><p class="detail-label">Status</p><p class="detail-value" id="dStatus"></p></div>
              <div class="col-6"><p class="detail-label">Assigned to</p><p class="detail-value" id="dAssigned"></p></div>
            </div>

            <?php if ($canManage): ?>
            <hr>
            <div id="manageBox">
              <div class="form-group" id="boxProperty" style="display:none;">
                <label class="detail-label">Set property</label>
                <div class="input-group">
                  <select class="form-control" id="selProperty"></select>
                  <div class="input-group-append"><button type="button" class="btn btn-outline-dark" id="btnSetProperty">Save</button></div>
                </div>
              </div>
              <div class="form-group">
                <label class="detail-label">Assign to</label>
                <div class="input-group">
                  <select class="form-control" id="selUser"></select>
                  <div class="input-group-append"><button type="button" class="btn btn-outline-dark" id="btnAssign">Assign</button></div>
                </div>
              </div>
              <div class="form-group">
                <label class="detail-label">Change status</label>
                <div id="statusButtons"></div>
              </div>
              <div class="form-group">
                <label class="detail-label">Add note</label>
                <div class="input-group">
                  <input type="text" class="form-control" id="inNote" maxlength="500" placeholder="Visible in the history">
                  <div class="input-group-append"><button type="button" class="btn btn-outline-dark" id="btnNote">Add</button></div>
                </div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="btnReviewed" style="display:none;">Mark as reviewed</button>
            </div>
            <?php endif; ?>
          </div>
          <div class="col-md-5">
            <p class="detail-label">History</p>
            <ul class="timeline" id="dTimeline"></ul>
            <div id="dDeliveriesWrap" style="display:none;">
              <p class="detail-label mt-3">Notifications sent</p>
              <ul class="timeline" id="dDeliveries"></ul>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-dark" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php include './admin/include/gerenic_footer.php'; ?>
<?php include './admin/include/gerenic_script.php'; ?>
<script>
  window.PGS_REQ_CAN_MANAGE = <?php echo $canManage ? 'true' : 'false'; ?>;
</script>
<script src="./admin/js/pgs_admin.js"></script>
<script src="./admin/js/requests.js"></script>
</body>
</html>
