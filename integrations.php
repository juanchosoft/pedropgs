<?php

declare(strict_types=1);

require_once './admin/include/generic_classes.php';
require_once './admin/bootstrap_pgs.php';

requireAnyPermission(['integraciones.sync.manage', 'integraciones.whatsapp.manage']);

$p = \Pgs\Core\Web\PgsPage::principal();
$modulo = 'Integrations & Sync';
$canSync = $p->hasPermission('integraciones.sync.manage');
$canWa = $p->hasPermission('integraciones.whatsapp.manage');
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($p->language() ?? 'en'); ?>">
<head>
  <?php include './admin/include/generic_head.php'; ?>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Integrations & Sync - PGS Centrum</title>
</head>
<body>
  <div id="preloader">
    <div class="sk-three-bounce">
      <div class="sk-child sk-bounce1"></div>
      <div class="sk-child sk-bounce2"></div>
      <div class="sk-child sk-bounce3"></div>
    </div>
  </div>

  <div id="main-wrapper">
    <?php include './admin/include/generic_header.php'; ?>

    <div class="deznav">
      <div class="deznav-scroll">
        <?php include './admin/include/generic_navbar.php'; ?>
      </div>
    </div>

    <div class="content-body">
      <div class="container-fluid">
        <div class="page-titles">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="main.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="javascript:void(0)">Configuration</a></li>
            <li class="breadcrumb-item active"><a href="javascript:void(0)">Integrations & Sync</a></li>
          </ol>
        </div>

        <div class="pgs-ui">
          <!-- HERO -->
          <div class="pgs-hero mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
              <div>
                <h2 class="pgs-hero-title mb-1">
                  <i class="fa fa-plug text-danger mr-2"></i> External Integrations & Sync Outbox
                </h2>
                <p class="text-muted mb-0">
                  Monitor Meta WhatsApp Cloud API status, Google Sheets/Calendar sync, outbox retry queues, and trigger synchronization routines.
                </p>
              </div>
              <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" id="btnRefreshStatus">
                  <i class="fa fa-refresh mr-1"></i> Refresh
                </button>
                <?php if ($canSync): ?>
                <button type="button" class="btn btn-danger" id="btnSyncAll">
                  <i class="fa fa-refresh mr-1"></i> Run Sync Now
                </button>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- STATUS CARDS -->
          <div class="row mb-4">
            <!-- WhatsApp Cloud API -->
            <div class="col-xl-6 col-lg-12 mb-3">
              <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                  <h5 class="mb-0 font-weight-bold text-dark">
                    <i class="fa fa-whatsapp text-success mr-2 font-size-20"></i> Meta WhatsApp Cloud API
                  </h5>
                  <span id="waBadge" class="badge badge-secondary">Checking...</span>
                </div>
                <div class="card-body">
                  <div class="row align-items-center">
                    <div class="col-sm-7">
                      <p class="text-muted mb-1"><small>Phone Number ID:</small></p>
                      <p class="font-weight-bold text-dark mb-3" id="waPhoneId">--</p>

                      <div class="d-flex gap-3 text-muted">
                        <div>
                          <small class="d-block">Pending in Outbox</small>
                          <span class="h5 font-weight-bold text-primary" id="waPendingCount">0</span>
                        </div>
                        <div class="ml-4">
                          <small class="d-block">Failed Attempts</small>
                          <span class="h5 font-weight-bold text-warning" id="waFailedCount">0</span>
                        </div>
                        <div class="ml-4">
                          <small class="d-block">Dead Messages</small>
                          <span class="h5 font-weight-bold text-danger" id="waDeadCount">0</span>
                        </div>
                      </div>
                    </div>
                    <div class="col-sm-5 text-sm-right mt-3 mt-sm-0">
                      <?php if ($canWa): ?>
                      <button type="button" class="btn btn-outline-success btn-sm btn-block" id="btnTestWa">
                        <i class="fa fa-paper-plane mr-1"></i> Send Test Message
                      </button>
                      <small class="text-muted d-block mt-1">Sends test WhatsApp to your phone</small>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Google Cloud Integrations -->
            <div class="col-xl-6 col-lg-12 mb-3">
              <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                  <h5 class="mb-0 font-weight-bold text-dark">
                    <i class="fa fa-google text-primary mr-2 font-size-20"></i> Google Workspace (Sheets & Calendar)
                  </h5>
                  <span id="googleBadge" class="badge badge-secondary">Checking...</span>
                </div>
                <div class="card-body">
                  <div class="row align-items-center">
                    <div class="col-sm-7">
                      <p class="text-muted mb-1"><small>Service Account:</small></p>
                      <p class="font-weight-bold text-dark mb-3" id="googleSa">--</p>

                      <div class="d-flex gap-3 text-muted">
                        <div>
                          <small class="d-block">Sheets Synced Links</small>
                          <span class="h5 font-weight-bold text-dark" id="gSheetsCount">0</span>
                        </div>
                        <div class="ml-4">
                          <small class="d-block">Calendar Synced Links</small>
                          <span class="h5 font-weight-bold text-dark" id="gCalCount">0</span>
                        </div>
                      </div>
                    </div>
                    <div class="col-sm-5 text-sm-right mt-3 mt-sm-0">
                      <div class="alert alert-light border py-2 px-3 mb-0 text-left">
                        <small class="text-muted"><i class="fa fa-info-circle mr-1"></i> Sync schedules run via cron runner every minute.</small>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- OUTBOX TABS CARD -->
          <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
              <ul class="nav nav-tabs card-header-tabs" id="outboxTabs" role="tablist">
                <li class="nav-item">
                  <a class="nav-link active font-weight-bold" id="notif-outbox-tab" data-toggle="tab" href="#notifOutboxPane" role="tab">
                    <i class="fa fa-bell-o mr-1"></i> Notification Outbox
                  </a>
                </li>
                <li class="nav-item">
                  <a class="nav-link font-weight-bold" id="sync-outbox-tab" data-toggle="tab" href="#syncOutboxPane" role="tab">
                    <i class="fa fa-exchange mr-1"></i> External Sync Outbox
                  </a>
                </li>
              </ul>
              <div class="d-flex align-items-center gap-2">
                <select class="form-control form-control-sm" id="outboxStatusFilter" style="width: 140px;">
                  <option value="all">Failed & Dead</option>
                  <option value="failed">Failed only</option>
                  <option value="dead">Dead only</option>
                </select>
                <button type="button" class="btn btn-outline-danger btn-sm" id="btnRetryAllDead">
                  <i class="fa fa-refresh mr-1"></i> Retry All Dead
                </button>
              </div>
            </div>
            <div class="card-body p-0">
              <div class="tab-content" id="outboxTabContent">
                <!-- Notifications Outbox Pane -->
                <div class="tab-pane fade show active" id="notifOutboxPane" role="tabpanel">
                  <div class="table-responsive">
                    <table class="table table-hover mb-0" id="notifOutboxTable">
                      <thead class="thead-light">
                        <tr>
                          <th>ID</th>
                          <th>Channel</th>
                          <th>Recipient</th>
                          <th>Template / Event</th>
                          <th>Status</th>
                          <th>Attempts</th>
                          <th>Last Error</th>
                          <th>Next Attempt</th>
                          <th class="text-right">Action</th>
                        </tr>
                      </thead>
                      <tbody id="notifOutboxBody">
                        <tr><td colspan="9" class="text-center py-4 text-muted">Loading outbox records...</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>

                <!-- Sync Outbox Pane -->
                <div class="tab-pane fade" id="syncOutboxPane" role="tabpanel">
                  <div class="table-responsive">
                    <table class="table table-hover mb-0" id="syncOutboxTable">
                      <thead class="thead-light">
                        <tr>
                          <th>ID</th>
                          <th>Provider</th>
                          <th>Entity Type</th>
                          <th>Entity ID</th>
                          <th>Action</th>
                          <th>Status</th>
                          <th>Attempts</th>
                          <th>Last Error</th>
                          <th class="text-right">Action</th>
                        </tr>
                      </thead>
                      <tbody id="syncOutboxBody">
                        <tr><td colspan="9" class="text-center py-4 text-muted">Loading outbox records...</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div><!-- /.pgs-ui -->
      </div>
    </div>

    <?php include './admin/include/gerenic_footer.php'; ?>
  <?php include './admin/include/gerenic_script.php'; ?>
  </div>

  <?php include './admin/include/gerenic_script.php'; ?>
  <script src="./admin/js/integrations.js"></script>
</body>
</html>
