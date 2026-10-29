<?php
require './admin/include/generic_classes.php';
requirePermission('servicios.requests.create');
require_once './admin/bootstrap_pgs.php';

$P = \Pgs\Core\Web\PgsPage::principal();
$modulo = 'Service Request';

$pdo = \Pgs\Core\Db::pdo();

// Properties in user scope
$unitParams = [];
$unitFilter = \Pgs\Auth\UnitScope::sqlFilter($P, 'id', $unitParams);
$unitsStmt = $pdo->prepare("SELECT id, nombre FROM tbl_unidades WHERE {$unitFilter} ORDER BY nombre ASC");
$unitsStmt->execute($unitParams);
$units = $unitsStmt->fetchAll(\PDO::FETCH_ASSOC);

$preselectedUnitId = isset($_GET['unit_id']) ? (int) $_GET['unit_id'] : ($units[0]['id'] ?? 0);
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
              <h3>New Service Request</h3>
              <p>Request property maintenance or scheduled service packages.</p>
            </div>
            <div>
              <a href="tasks.php" class="btn btn-outline-secondary btn-sm">
                <i class="fa fa-list mr-1"></i> View My Tasks
              </a>
            </div>
          </div>

          <!-- Step 1: Select Property -->
          <div class="card mb-4 p-4 shadow-sm border-0" style="border-radius:18px;">
            <div class="row align-items-center">
              <div class="col-md-6">
                <label class="font-weight-bold text-dark mb-1">1. Select Property / Community</label>
                <select class="form-control form-control-lg" id="selectProperty" style="border-radius:12px; font-weight:700;">
                  <?php foreach ($units as $u): ?>
                    <option value="<?php echo (int) $u['id']; ?>" <?php echo $u['id'] == $preselectedUnitId ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($u['nombre']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6 mt-3 mt-md-0">
                <span class="text-muted small">
                  Select the property where the service is requested. Only services approved for this property will be available below.
                </span>
              </div>
            </div>
          </div>

          <!-- Step 2: Available Services Grid -->
          <div class="mb-3 d-flex justify-content-between align-items-center">
            <h5 class="font-weight-bold text-dark mb-0">2. Choose a Service Offering</h5>
            <span class="text-muted small" id="servicesCount">Loading catalog...</span>
          </div>

          <div class="row mb-4" id="servicesGrid">
            <div class="col-12 text-center py-4 text-muted">
              <i class="fa fa-spinner fa-spin mr-1"></i> Loading offerings...
            </div>
          </div>

          <!-- Step 3: Request Form (Shown upon selecting a service) -->
          <div class="card p-4 shadow border-0" id="cardRequestForm" style="display:none; border-radius:18px; border-top:4px solid var(--brand) !important;">
            <h5 class="font-weight-bold text-dark mb-3">3. Request Details</h5>

            <!-- Selected Service Summary Banner -->
            <div class="alert alert-light border d-flex align-items-center justify-content-between p-3 mb-4 rounded">
              <div>
                <span class="badge badge-danger text-uppercase mr-2" id="selectedServiceCode">SVC</span>
                <strong class="text-dark" id="selectedServiceName">Service Name</strong>
                <div class="text-muted small mt-1" id="selectedServicePricing">Fixed price</div>
              </div>
              <button type="button" class="btn btn-sm btn-link text-muted" id="btnChangeService">Change Selection</button>
            </div>

            <form id="formSubmitRequest">
              <input type="hidden" id="reqServiceId">

              <div class="row">
                <div class="col-md-6 form-group">
                  <label class="font-weight-bold small">Location within Property <span class="text-danger">*</span></label>
                  <input type="text" class="form-control" id="reqLocation" placeholder="e.g. Building B Main Hall, Pool Area, Unit 201" required maxlength="120">
                </div>
                <div class="col-md-3 form-group">
                  <label class="font-weight-bold small">Required Date</label>
                  <input type="date" class="form-control" id="reqDate" min="<?php echo date('Y-m-d'); ?>">
                  <small class="text-muted" id="leadTimeNote"></small>
                </div>
                <div class="col-md-3 form-group">
                  <label class="font-weight-bold small">Priority</label>
                  <select class="form-control" id="reqPriority">
                    <option value="low">Low</option>
                    <option value="normal" selected>Normal</option>
                    <option value="high">High</option>
                    <option value="urgent">Urgent</option>
                  </select>
                </div>
              </div>

              <div class="form-group mb-4">
                <label class="font-weight-bold small">Special Instructions / Observations</label>
                <textarea class="form-control" id="reqNotes" rows="3" placeholder="Describe the specific problem or requirements..." maxlength="2000"></textarea>
              </div>

              <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-danger btn-lg px-4 shadow-sm" id="btnSubmitRequest">
                  <i class="fa fa-paper-plane mr-1"></i> Submit Request
                </button>
              </div>
            </form>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<?php include './admin/include/gerenic_footer.php'; ?>
<?php include './admin/include/gerenic_script.php'; ?>

<script src="./admin/js/service_request.js"></script>
</body>
</html>
