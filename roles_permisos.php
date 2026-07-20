<?php
require './admin/include/generic_classes.php';
requireAnyPermission(['configuracion.roles.view', 'configuracion.roles.manage']);
$canManage = SessionData::hasPermission('configuracion.roles.manage') || SessionData::superAdministrador();
$modulo = 'Roles & Permissions';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include './admin/include/generic_head.php'; ?>
  <title>Roles & Permissions</title>
  <style>
    .pgs-ui{ --brand:#E10600; --ink:#0B0F19; --muted:#64748b; --bg:#F6F7FB; --card:#fff; --border:#e7eaf1; }
    .pgs-ui .page-wrap{ padding: 6px 0 18px; }
    .pgs-ui .hero{
      border-radius: 24px; padding: 18px 20px; margin-bottom: 16px;
      background: linear-gradient(135deg, #0B0F19 0%, #1a1f2e 60%, #E10600 160%);
      color:#fff; box-shadow: 0 18px 60px rgba(2,6,23,.12);
    }
    .pgs-ui .hero h3{ margin:0; font-weight:1000; }
    .pgs-ui .hero p{ margin:6px 0 0; opacity:.85; }
    .pgs-ui .card{ border:1px solid var(--border); border-radius:18px; box-shadow:0 10px 26px rgba(2,6,23,.06); }
    .pgs-ui .perm-module{ border:1px solid var(--border); border-radius:12px; padding:10px 12px; margin-bottom:10px; background:#fbfcfe; }
    .pgs-ui .perm-module h6{ font-weight:900; margin:0 0 8px; color:var(--ink); text-transform:capitalize; }
    .pgs-ui .perm-item{ display:flex; align-items:center; gap:8px; margin:4px 0; font-weight:600; }
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
              <p>Manage roles and their permission matrix. Editing a role never clears permissions by accident.</p>
            </div>
            <?php if ($canManage): ?>
            <button type="button" class="btn btn-light" id="btnNewRole" style="font-weight:900;">New Role</button>
            <?php endif; ?>
          </div>

          <div class="card">
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-hover mb-0" id="tablaRoles">
                  <thead>
                    <tr>
                      <th>Name</th>
                      <th>Permissions</th>
                      <th>Users</th>
                      <th>System</th>
                      <th style="width:140px;">Actions</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalRole" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content pgs-ui">
      <div class="modal-header">
        <h5 class="modal-title" id="modalRoleTitle">Role</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="role_id" value="">
        <div class="form-group">
          <label>Name *</label>
          <input type="text" class="form-control" id="role_name">
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea class="form-control" id="role_description" rows="2"></textarea>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-2">
          <strong>Permissions</strong>
          <div>
            <button type="button" class="btn btn-sm btn-outline-dark" id="btnCheckAll">Select all</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnUncheckAll">Clear</button>
          </div>
        </div>
        <div id="permCatalog" style="max-height:380px; overflow:auto;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-dark" data-dismiss="modal">Cancel</button>
        <?php if ($canManage): ?>
        <button type="button" class="btn btn-danger" id="btnSaveRole" style="font-weight:900;">Save</button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include './admin/include/gerenic_footer.php'; ?>
<?php include './admin/include/gerenic_script.php'; ?>
<script>
  window.PGS_ROLES_CAN_MANAGE = <?php echo $canManage ? 'true' : 'false'; ?>;
</script>
<script src="./admin/js/roles_permisos.js"></script>
</body>
</html>
