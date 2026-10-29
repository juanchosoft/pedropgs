<?php
require './admin/include/generic_classes.php';
requirePermission('servicios.catalog.view');
require_once './admin/bootstrap_pgs.php';

$P = \Pgs\Core\Web\PgsPage::principal();
$canManage = $P->can('servicios.catalog.manage');
$modulo = 'Services Catalog';

$pdo = \Pgs\Core\Db::pdo();
$assignees = [];
$units = [];
if ($canManage) {
    $assignees = $pdo->query(
        "SELECT id, nickname FROM tec_usuarios
         WHERE habilitado IN ('yes','si') AND (tipo = 'SuperAdministrador' OR is_internal_staff = 1)
         ORDER BY nickname ASC"
    )->fetchAll();
    $units = $pdo->query("SELECT id, nombre FROM tbl_unidades ORDER BY nombre ASC")->fetchAll();
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

          <!-- Hero Section -->
          <div class="hero d-flex align-items-center justify-content-between flex-wrap">
            <div>
              <h3>Services Catalog</h3>
              <p>Manage standardized maintenance and property service offerings.</p>
            </div>
            <?php if ($canManage): ?>
            <div class="mt-2 mt-md-0">
              <button class="btn btn-danger btn-lg shadow-sm" id="btnNewService">
                <i class="fa fa-plus mr-1"></i> New Service
              </button>
            </div>
            <?php endif; ?>
          </div>

          <!-- Filter Bar -->
          <div class="card mb-4 p-3">
            <div class="row align-items-center">
              <div class="col-md-4 mb-2 mb-md-0">
                <label class="small text-muted font-weight-bold mb-1">Category</label>
                <select class="form-control" id="filterCategory">
                  <option value="">All Categories</option>
                </select>
              </div>
              <div class="col-md-3 mb-2 mb-md-0">
                <label class="small text-muted font-weight-bold mb-1">Status</label>
                <select class="form-control" id="filterActive">
                  <option value="">All Statuses</option>
                  <option value="1">Active</option>
                  <option value="0">Inactive</option>
                </select>
              </div>
              <div class="col-md-5">
                <label class="small text-muted font-weight-bold mb-1">Search</label>
                <div class="input-group">
                  <input type="text" class="form-control" id="filterSearch" placeholder="Search by code, title...">
                  <div class="input-group-append">
                    <button class="btn btn-outline-secondary" type="button" onclick="loadServices()"><i class="fa fa-search"></i></button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Table Card -->
          <div class="card p-3 shadow-sm">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0" id="servicesTable" style="width:100%;">
                <thead class="thead-light">
                  <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Lead Time</th>
                    <th>Public</th>
                    <th>Status</th>
                    <th style="width: 90px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Filled via JS -->
                </tbody>
              </table>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Create / Edit -->
<div class="modal fade pgs-ui" id="serviceModal" tabindex="-1" role="dialog" aria-labelledby="serviceModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content border-0 shadow-lg" style="border-radius:20px;">
      <div class="modal-header border-bottom">
        <h5 class="modal-title font-weight-bold" id="serviceModalLabel">New Service</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form id="serviceForm">
        <input type="hidden" id="serviceId" name="id">
        <div class="modal-body p-4">

          <div class="row">
            <div class="col-md-4 form-group">
              <label class="font-weight-bold small">Service Code *</label>
              <input type="text" class="form-control" id="serviceCode" name="code" placeholder="SRV-PLUMB" required pattern="^[A-Z0-9_-]{2,30}$">
              <small class="text-muted">Uppercase alphanumeric, 2-30 chars</small>
            </div>
            <div class="col-md-5 form-group">
              <label class="font-weight-bold small">Category *</label>
              <input type="text" class="form-control" id="serviceCategory" name="category" placeholder="Plumbing, Electrical, etc." required maxlength="60">
            </div>
            <div class="col-md-3 form-group">
              <label class="font-weight-bold small">Sort Order</label>
              <input type="number" class="form-control" id="sortOrder" name="sort_order" value="0">
            </div>
          </div>

          <!-- Language Tabs -->
          <ul class="nav nav-pills mb-3 mt-2" id="langTabs" role="tablist">
            <li class="nav-item">
              <a class="nav-link active font-weight-bold px-4" id="tab-en" data-toggle="pill" href="#pane-en" role="tab">English</a>
            </li>
            <li class="nav-item">
              <a class="nav-link font-weight-bold px-4" id="tab-es" data-toggle="pill" href="#pane-es" role="tab">Español</a>
            </li>
          </ul>

          <div class="tab-content border rounded p-3 mb-3 bg-light" id="langTabContent">
            <!-- English Pane -->
            <div class="tab-pane fade show active" id="pane-en" role="tabpanel">
              <div class="form-group">
                <label class="font-weight-bold small">Name (English) *</label>
                <input type="text" class="form-control" id="serviceNameEn" name="name_en" required maxlength="150">
              </div>
              <div class="form-group">
                <label class="font-weight-bold small">Description (English)</label>
                <textarea class="form-control" id="serviceDescEn" name="description_en" rows="2" maxlength="5000"></textarea>
              </div>
              <div class="form-group mb-0">
                <label class="font-weight-bold small">Terms / Conditions (English)</label>
                <textarea class="form-control" id="serviceCondEn" name="conditions_en" rows="2" maxlength="5000"></textarea>
              </div>
            </div>

            <!-- Spanish Pane -->
            <div class="tab-pane fade" id="pane-es" role="tabpanel">
              <div class="form-group">
                <label class="font-weight-bold small">Nombre (Español) *</label>
                <input type="text" class="form-control" id="serviceNameEs" name="name_es" required maxlength="150">
              </div>
              <div class="form-group">
                <label class="font-weight-bold small">Descripción (Español)</label>
                <textarea class="form-control" id="serviceDescEs" name="description_es" rows="2" maxlength="5000"></textarea>
              </div>
              <div class="form-group mb-0">
                <label class="font-weight-bold small">Términos / Condiciones (Español)</label>
                <textarea class="form-control" id="serviceCondEs" name="conditions_es" rows="2" maxlength="5000"></textarea>
              </div>
            </div>
          </div>

          <!-- Pricing & Lead Time -->
          <div class="row">
            <div class="col-md-3 form-group">
              <label class="font-weight-bold small">Price Unit *</label>
              <select class="form-control" id="priceUnit" name="price_unit" required>
                <option value="fixed">Fixed</option>
                <option value="hour">Per hour</option>
                <option value="visit">Per visit</option>
                <option value="sqft">Per sq ft</option>
                <option value="quote">Quote</option>
              </select>
            </div>
            <div class="col-md-3 form-group">
              <label class="font-weight-bold small">Price Amount</label>
              <input type="number" step="0.01" min="0" class="form-control" id="priceAmount" name="price_amount" placeholder="0.00">
            </div>
            <div class="col-md-3 form-group">
              <label class="font-weight-bold small">Currency</label>
              <input type="text" class="form-control" id="currency" name="currency" value="USD" maxlength="3" required>
            </div>
            <div class="col-md-3 form-group">
              <label class="font-weight-bold small">Lead Time (Days)</label>
              <input type="number" min="0" max="365" class="form-control" id="leadTimeDays" name="lead_time_days" value="1">
            </div>
          </div>

          <div class="row pgs-internal-field">
            <div class="col-md-6 form-group">
              <label class="font-weight-bold small">Default Assignee</label>
              <select class="form-control" id="defaultAssignee" name="default_assignee_user_id">
                <option value="">None (Unassigned)</option>
                <?php foreach ($assignees as $a): ?>
                  <option value="<?php echo (int) $a['id']; ?>"><?php echo htmlspecialchars($a['nickname']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6 form-group">
              <label class="font-weight-bold small">Available in Properties (Empty = All)</label>
              <select class="form-control" id="serviceUnitIds" name="unit_ids[]" multiple>
                <?php foreach ($units as $u): ?>
                  <option value="<?php echo (int) $u['id']; ?>"><?php echo htmlspecialchars($u['nombre']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="row mt-2 pgs-internal-field">
            <div class="col-md-6 form-group mb-0">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="isPublic" name="is_public" checked>
                <label class="custom-control-label font-weight-bold small" for="isPublic">Public (visible to non-admin callers / clients)</label>
              </div>
            </div>
            <div class="col-md-6 form-group mb-0">
              <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="isActive" name="is_active" checked>
                <label class="custom-control-label font-weight-bold small" for="isActive">Active for requests</label>
              </div>
            </div>
          </div>

        </div>
        <div class="modal-footer border-top p-3">
          <button type="button" class="btn btn-secondary px-4" data-dismiss="modal">Cancel</button>
          <?php if ($canManage): ?>
          <button type="submit" class="btn btn-danger px-4" id="btnSaveService">Save Service</button>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include './admin/include/gerenic_footer.php'; ?>
<?php include './admin/include/gerenic_script.php'; ?>
<script>
  window.PGS_SERVICES = {
    canManage: <?php echo $canManage ? 'true' : 'false'; ?>
  };
</script>
<script src="./admin/js/services_catalog.js"></script>
</body>
</html>
