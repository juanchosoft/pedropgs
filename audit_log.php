<?php

declare(strict_types=1);

require_once './admin/include/generic_classes.php';
require_once './admin/bootstrap_pgs.php';

requirePermission('auditoria.logs.view');

$p = \Pgs\Core\Web\PgsPage::principal();
$modulo = 'Audit Log';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($p->language() ?? 'en'); ?>">
<head>
  <?php include './admin/include/generic_head.php'; ?>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>System Audit Log - PGS Centrum</title>
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
            <li class="breadcrumb-item active"><a href="javascript:void(0)">Audit Log</a></li>
          </ol>
        </div>

        <div class="pgs-ui">
          <!-- HERO -->
          <div class="pgs-hero mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
              <div>
                <h2 class="pgs-hero-title mb-1">
                  <i class="fa fa-history text-danger mr-2"></i> System Audit Trail
                </h2>
                <p class="text-muted mb-0">
                  Comprehensive audit trail of system events, role updates, financial transactions, task state changes, and automated background jobs across all channels.
                </p>
              </div>
              <div>
                <button type="button" class="btn btn-outline-secondary" id="btnRefreshAudit">
                  <i class="fa fa-refresh mr-1"></i> Refresh
                </button>
              </div>
            </div>
          </div>

          <!-- FILTERS CARD -->
          <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
              <form id="filterForm" class="row align-items-end">
                <div class="col-md-2 col-sm-6 mb-2">
                  <label class="small text-muted font-weight-bold mb-1">Action</label>
                  <input type="text" class="form-control form-control-sm" id="filterAction" placeholder="e.g. task.status">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                  <label class="small text-muted font-weight-bold mb-1">Entity Type</label>
                  <input type="text" class="form-control form-control-sm" id="filterEntity" placeholder="e.g. task, expense">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                  <label class="small text-muted font-weight-bold mb-1">Channel</label>
                  <select class="form-control form-control-sm" id="filterChannel">
                    <option value="">All channels</option>
                    <option value="web">Web</option>
                    <option value="api">API</option>
                    <option value="voice">Voice</option>
                    <option value="assistant">Assistant</option>
                    <option value="cron">Cron</option>
                  </select>
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                  <label class="small text-muted font-weight-bold mb-1">From Date</label>
                  <input type="date" class="form-control form-control-sm" id="filterFrom">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                  <label class="small text-muted font-weight-bold mb-1">To Date</label>
                  <input type="date" class="form-control form-control-sm" id="filterTo">
                </div>
                <div class="col-md-2 col-sm-12 mb-2 d-flex gap-2">
                  <button type="submit" class="btn btn-danger btn-sm flex-fill">
                    <i class="fa fa-filter mr-1"></i> Filter
                  </button>
                  <button type="button" class="btn btn-outline-secondary btn-sm" id="btnClearFilters">
                    Clear
                  </button>
                </div>
              </form>
            </div>
          </div>

          <!-- AUDIT TABLE -->
          <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
              <h5 class="mb-0 font-weight-bold text-dark">
                <i class="fa fa-list text-danger mr-2"></i> Audit Records
              </h5>
              <span id="auditTotalBadge" class="badge badge-light border">0 events</span>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover mb-0" id="auditTable">
                  <thead class="thead-light">
                    <tr>
                      <th>ID</th>
                      <th>Timestamp</th>
                      <th>Actor</th>
                      <th>Channel</th>
                      <th>Action</th>
                      <th>Target Entity</th>
                      <th>IP Address</th>
                      <th class="text-right">Payload</th>
                    </tr>
                  </thead>
                  <tbody id="auditBody">
                    <tr><td colspan="8" class="text-center py-4 text-muted">Loading audit entries...</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="card-footer bg-white border-top py-2 d-flex justify-content-between align-items-center">
              <button type="button" class="btn btn-outline-secondary btn-sm" id="btnPrevPage" disabled>
                <i class="fa fa-chevron-left mr-1"></i> Previous
              </button>
              <span class="small text-muted" id="pageIndicator">Page 1 of 1</span>
              <button type="button" class="btn btn-outline-secondary btn-sm" id="btnNextPage" disabled>
                Next <i class="fa fa-chevron-right ml-1"></i>
              </button>
            </div>
          </div>

        </div><!-- /.pgs-ui -->
      </div>
    </div>

    <?php include './admin/include/gerenic_footer.php'; ?>
  <?php include './admin/include/gerenic_script.php'; ?>
  </div>

  <!-- Detail Modal -->
  <div class="modal fade" id="auditModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title font-weight-bold" id="auditModalTitle">Audit Event Payload</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <pre class="bg-dark text-light p-3 rounded" style="max-height: 420px; overflow: auto; font-size: 13px;" id="auditModalContent"></pre>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <?php include './admin/include/gerenic_script.php'; ?>
  <script src="./admin/js/audit_log.js"></script>
</body>
</html>
