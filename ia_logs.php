<?php

declare(strict_types=1);

require_once './admin/include/generic_classes.php';
require_once './admin/bootstrap_pgs.php';

requirePermission('ia.logs.view');

$p = \Pgs\Core\Web\PgsPage::principal();
$modulo = 'AI Logs';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($p->language() ?? 'en'); ?>">
<head>
  <?php include './admin/include/generic_head.php'; ?>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>AI Tool Execution Logs - PGS Centrum</title>
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
            <li class="breadcrumb-item active"><a href="javascript:void(0)">AI Logs</a></li>
          </ol>
        </div>

        <div class="pgs-ui">
          <!-- HERO -->
          <div class="pgs-hero mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
              <div>
                <h2 class="pgs-hero-title mb-1">
                  <i class="fa fa-magic text-danger mr-2"></i> AI Assistant Tool Execution & Token Logs
                </h2>
                <p class="text-muted mb-0">
                  Inspect autonomous AI tool invocations, execution latencies, outcome statuses, and daily token consumption breakdown.
                </p>
              </div>
              <div>
                <button type="button" class="btn btn-outline-secondary" id="btnRefreshLogs">
                  <i class="fa fa-refresh mr-1"></i> Refresh
                </button>
              </div>
            </div>
          </div>

          <!-- TOKEN CONSUMPTION STATS -->
          <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom py-3">
              <h5 class="mb-0 font-weight-bold text-dark">
                <i class="fa fa-bar-chart text-danger mr-2"></i> Token Consumption (Recent 14 Days)
              </h5>
            </div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-sm table-bordered text-center mb-0" id="tokenStatsTable">
                  <thead class="thead-light">
                    <tr>
                      <th>Date</th>
                      <th>Messages Count</th>
                      <th>Input Tokens</th>
                      <th>Output Tokens</th>
                      <th>Total Tokens</th>
                    </tr>
                  </thead>
                  <tbody id="tokenStatsBody">
                    <tr><td colspan="5" class="py-3 text-muted">Loading token statistics...</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- FILTERS CARD -->
          <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
              <form id="filterForm" class="row align-items-end">
                <div class="col-md-3 col-sm-6 mb-2">
                  <label class="small text-muted font-weight-bold mb-1">Tool Name</label>
                  <input type="text" class="form-control form-control-sm" id="filterTool" placeholder="e.g. consultar_disponibilidad">
                </div>
                <div class="col-md-2 col-sm-6 mb-2">
                  <label class="small text-muted font-weight-bold mb-1">Status</label>
                  <select class="form-control form-control-sm" id="filterSuccess">
                    <option value="">All statuses</option>
                    <option value="1">Success</option>
                    <option value="0">Failure</option>
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
                <div class="col-md-3 col-sm-6 mb-2 d-flex gap-2">
                  <button type="submit" class="btn btn-danger btn-sm flex-fill">
                    <i class="fa fa-filter mr-1"></i> Apply Filters
                  </button>
                  <button type="button" class="btn btn-outline-secondary btn-sm" id="btnClearFilters">
                    Clear
                  </button>
                </div>
              </form>
            </div>
          </div>

          <!-- TOOL LOGS TABLE -->
          <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
              <h5 class="mb-0 font-weight-bold text-dark">
                <i class="fa fa-terminal text-danger mr-2"></i> Tool Invocations
              </h5>
              <span id="logsTotalBadge" class="badge badge-light border">0 records</span>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover mb-0" id="iaLogsTable">
                  <thead class="thead-light">
                    <tr>
                      <th>ID</th>
                      <th>Timestamp</th>
                      <th>User</th>
                      <th>Tool Name</th>
                      <th>Duration</th>
                      <th>Status</th>
                      <th>Arguments</th>
                      <th>Result / Error</th>
                    </tr>
                  </thead>
                  <tbody id="iaLogsBody">
                    <tr><td colspan="8" class="text-center py-4 text-muted">Loading logs...</td></tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="card-footer bg-white border-top py-2 d-flex justify-content-between align-items-center" id="paginationBar">
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
  <div class="modal fade" id="payloadModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title font-weight-bold" id="payloadModalTitle">Tool Payload</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <pre class="bg-dark text-light p-3 rounded" style="max-height: 400px; overflow: auto; font-size: 13px;" id="payloadModalContent"></pre>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <?php include './admin/include/gerenic_script.php'; ?>
  <script src="./admin/js/ia_logs.js"></script>
</body>
</html>
