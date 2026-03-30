<?php
require './admin/include/generic_classes.php';
include './admin/classes/Usuario.php';
include './admin/classes/Unidades.php';

// Permissions
$view    = SessionData::getPermission(1);
$create  = SessionData::getPermission(2);
$edit    = SessionData::getPermission(3);
$delete  = SessionData::getPermission(4);
$enable  = SessionData::getPermission(5);
$permits = SessionData::getPermission(6);

// Validation
if (!$view) { require 'permiso_denegado.php'; }

// HOA Units options
$arrUnidades = Unidades::getAll(null);
$isvalidUni  = $arrUnidades['output']['valid'];
$arrUnidades = $arrUnidades['output']['response'];

$optionUnidades = '<option value="selecct">Select...</option>';
foreach ($arrUnidades as $val) {
  $optionUnidades .= "<option value='" . $val['id'] . "'>" . $val['nombre'] . "</option>";
}

$arr = Usuario::getAll(null);
$isvalid = $arr['output']['valid'];
$arr = $arr['output']['response'];

$modulo = 'Users';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include './admin/include/generic_head.php'; ?>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>

  <style>
    /* ==========================================================
       PGS CENTRUM – Users (SaaS Premium Red/Black)
       UI ONLY (no back / no ids / no logic)
    ========================================================== */
    .pgs-ui{
      --brand:#E10600;
      --brand2:#B30500;
      --ink:#0B0F19;
      --muted:#64748b;
      --bg:#F6F7FB;
      --card:#ffffff;
      --border:#e7eaf1;
      --border2:#eef2f7;
      --shadow: 0 18px 60px rgba(2,6,23,.10);
      --shadow2: 0 10px 26px rgba(2,6,23,.08);
      --r12:12px;
      --r16:16px;
      --r20:20px;
      --r24:24px;
      --ring: 0 0 0 4px rgba(225,6,0,.14);
      color: var(--ink) !important;
    }
    .pgs-ui *{ box-sizing:border-box; }
    .pgs-ui a{ color:inherit; text-decoration:none; }

    .pgs-ui .page-wrap{ padding: 6px 0 18px 0; }

    /* Breadcrumb */
    .pgs-ui .page-titles{ margin-bottom: 12px; }
    .pgs-ui .breadcrumb{
      background: transparent !important;
      padding: 0 !important;
      margin: 0 !important;
    }
    .pgs-ui .breadcrumb .breadcrumb-item a{
      font-weight: 800;
      color: #475569;
    }
    .pgs-ui .breadcrumb .breadcrumb-item.active a{
      color: var(--ink);
    }

    /* Hero */
    .pgs-ui .hero{
      border-radius: var(--r24);
      background:
        radial-gradient(900px 260px at 12% 0%, rgba(225,6,0,.22), transparent 60%),
        radial-gradient(900px 260px at 92% 10%, rgba(11,15,25,.10), transparent 60%),
        linear-gradient(135deg, #ffffff 0%, #fbfbfd 55%, #f7f8fc 100%);
      border: 1px solid var(--border);
      box-shadow: var(--shadow);
      padding: 16px;
      margin-bottom: 12px;
      position: relative;
      overflow:hidden;
    }
    .pgs-ui .hero-top{
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:12px;
      flex-wrap:wrap;
    }
    .pgs-ui .pill{
      display:inline-flex;
      align-items:center;
      gap:10px;
      padding: 10px 12px;
      border-radius: 999px;
      border: 1px solid var(--border);
      background: rgba(255,255,255,.92);
      font-weight: 950;
      box-shadow: var(--shadow2);
    }
    .pgs-ui .pill .dot{
      width:10px; height:10px; border-radius:999px;
      background: linear-gradient(135deg, var(--brand), var(--brand2));
      box-shadow: 0 0 0 4px rgba(225,6,0,.14);
    }
    .pgs-ui .h-title{
      margin: 10px 0 0 0;
      font-weight: 1000;
      letter-spacing: -.3px;
      font-size: 20px;
      line-height: 1.1;
    }
    .pgs-ui .h-sub{
      margin-top: 6px;
      color: var(--muted);
      font-weight: 750;
      font-size: 12px;
    }

    /* Buttons premium */
    .pgs-ui .btn-saas{
      border: 1px solid var(--border) !important;
      background: rgba(255,255,255,.92) !important;
      color: var(--ink) !important;
      padding: 10px 14px !important;
      border-radius: var(--r12) !important;
      font-weight: 900 !important;
      transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease, filter .15s ease;
      box-shadow: 0 8px 18px rgba(2,6,23,.06);
      white-space: nowrap;
    }
    .pgs-ui .btn-saas:hover{
      transform: translateY(-1px);
      border-color: rgba(225,6,0,.25) !important;
      box-shadow: 0 12px 24px rgba(2,6,23,.10);
      filter: brightness(1.01);
    }
    .pgs-ui .btn-brand{
      background: linear-gradient(135deg, var(--brand), var(--brand2)) !important;
      color:#fff !important;
      border: none !important;
      box-shadow: 0 16px 34px rgba(225,6,0,.20);
    }
    .pgs-ui .btn-brand:hover{
      box-shadow: 0 18px 40px rgba(225,6,0,.26);
      filter: brightness(1.03);
    }

    /* Card table */
    .pgs-ui .card.pgs-card{
      border-radius: var(--r24) !important;
      border: 1px solid var(--border) !important;
      box-shadow: var(--shadow) !important;
      overflow:hidden;
    }
    .pgs-ui .card-header{
      background: linear-gradient(180deg, #fff, #f8fafc) !important;
      border-bottom: 1px solid var(--border) !important;
      padding: 14px 16px !important;
    }
    .pgs-ui .card-title{
      margin:0 !important;
      font-weight: 1000 !important;
      letter-spacing: -.3px;
    }

    /* Table wrapper */
    .pgs-ui .table-wrap{
      border: 1px solid var(--border2);
      border-radius: 18px;
      overflow: auto;
      background: #fff;
    }
    .pgs-ui #dynamictable{
      width:100% !important;
      border-collapse: collapse !important;
    }
    .pgs-ui #dynamictable thead th{
      position: sticky;
      top: 0;
      z-index: 2;
      background: #f8fafc !important;
      color: #111827 !important;
      font-weight: 1000 !important;
      font-size: 12px !important;
      letter-spacing: .25px;
      text-transform: uppercase;
      border-bottom: 1px solid var(--border2) !important;
      padding: 12px 12px !important;
      white-space: nowrap;
    }
    .pgs-ui #dynamictable tbody td{
      padding: 12px 12px !important;
      border-bottom: 1px solid var(--border2) !important;
      vertical-align: middle !important;
      font-weight: 750;
      color: #0f172a;
      font-size: 13px;
      white-space: nowrap;
    }
    .pgs-ui #dynamictable tbody tr:hover{ background:#fbfbfd !important; }

    /* Avatar */
    .pgs-ui .avatar{
      width: 52px;
      height: 52px;
      border-radius: 14px;
      overflow:hidden;
      border: 1px solid var(--border);
      background: #fff;
      box-shadow: 0 10px 18px rgba(2,6,23,.08);
      display:grid;
      place-items:center;
    }
    .pgs-ui .avatar img{
      width:100%;
      height:100%;
      object-fit: cover;
      display:block;
    }
    /* If logo placeholder should be centered small (safe) */
    .pgs-ui .avatar img.is-logo{
      width: 60%;
      height: 60%;
      object-fit: contain;
    }

    /* Badges */
    .pgs-ui .badge-pillx{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding: 6px 10px;
      border-radius: 999px;
      font-weight: 950;
      font-size: 12px;
      border: 1px solid var(--border);
      background: #fff;
    }
    .pgs-ui .badge-on{
      border-color: rgba(22,163,74,.25);
      background: rgba(22,163,74,.10);
      color: rgba(22,163,74,.95);
    }
    .pgs-ui .badge-off{
      border-color: rgba(225,6,0,.25);
      background: rgba(225,6,0,.08);
      color: rgba(225,6,0,.95);
    }
    .pgs-ui .badge-role{
      border-color: rgba(15,23,42,.15);
      background: rgba(15,23,42,.04);
      color: rgba(15,23,42,.95);
    }

    /* Action buttons compact */
    .pgs-ui .btn-outline-primary.btn-sm,
    .pgs-ui .btn-outline-danger.btn-sm,
    .pgs-ui .btn-outline-warning.btn-sm,
    .pgs-ui .btn-outline-info.btn-sm{
      border-radius: 12px !important;
      font-weight: 900 !important;
    }
    .pgs-ui .btn-outline-warning.btn-sm{
      border-color: rgba(245,158,11,.35) !important;
      color: rgba(180,83,9,1) !important;
    }

    /* Modal premium */
    .pgs-ui .modal-content{
      border-radius: 22px !important;
      border: 1px solid var(--border) !important;
      box-shadow: var(--shadow) !important;
      overflow:hidden;
    }
    .pgs-ui .modal-header.card-header.card-header-danger{
      background:
        radial-gradient(800px 220px at 12% 0%, rgba(225,6,0,.26), transparent 60%),
        linear-gradient(135deg, #111827 0%, #0b0f19 55%, #0b0f19 100%) !important;
      color:#fff !important;
      border-bottom: 1px solid rgba(255,255,255,.10) !important;
      padding: 16px 18px !important;
    }
    .pgs-ui .modal-title{ font-weight: 1000 !important; letter-spacing:-.2px; }
    .pgs-ui .close{ opacity: 1 !important; color:#fff !important; text-shadow:none !important; }
    .pgs-ui .close:hover{ filter: brightness(1.08); }

    /* Inputs */
    .pgs-ui .form-group label{ font-weight: 900; color:#0f172a; }
    .pgs-ui .form-control{
      border-radius: 14px !important;
      border: 1px solid var(--border) !important;
      padding: 10px 12px !important;
      font-weight: 800;
      background: #fff !important;
      color: #0f172a !important;
      transition: box-shadow .15s ease, border-color .15s ease;
    }
    .pgs-ui .form-control:focus{
      border-color: rgba(225,6,0,.45) !important;
      box-shadow: var(--ring) !important;
    }
    .pgs-ui .errLbl{ color: var(--brand); font-weight: 1000; }
    .pgs-ui .validateTips{ margin:0; font-weight: 800; color: var(--muted); }

    .pgs-ui .modal-footer{
      border-top: 1px solid var(--border) !important;
      background: linear-gradient(180deg, #fff, #fafbff) !important;
      padding: 12px 16px !important;
      gap: 10px;
    }

    /* Permissions modal cards */
    .pgs-ui #myModalPermisos .card{
      border-radius: 18px !important;
      border: 1px solid var(--border) !important;
      box-shadow: 0 10px 26px rgba(2,6,23,.08) !important;
      overflow:hidden;
    }
    .pgs-ui #myModalPermisos .card-header.card-header-tabs.card-header.card-header-spider{
      background: linear-gradient(135deg, #0b0f19, #111827) !important;
      color:#fff !important;
      border-bottom: 1px solid rgba(255,255,255,.10) !important;
    }
    .pgs-ui #myModalPermisos .nav-tabs-title{ font-weight: 950; }

    /* Responsive */
    @media (max-width: 768px){
      .pgs-ui .hero{ padding: 14px; }
      .pgs-ui .h-title{ font-size: 18px; }
      .pgs-ui .btn-saas{ width: 100%; justify-content:center; }
      .pgs-ui #dynamictable tbody td{ white-space: normal; }
      .pgs-ui .table-wrap{ border-radius: 16px; }
    }

    /* DataTables polish */
    .pgs-ui .dataTables_wrapper .dataTables_filter input,
    .pgs-ui .dataTables_wrapper .dataTables_length select{
      border-radius: 12px !important;
      border: 1px solid var(--border) !important;
      padding: 8px 10px !important;
      font-weight: 900;
      outline: none !important;
    }
    .pgs-ui .dataTables_wrapper .dataTables_paginate .paginate_button{
      border-radius: 12px !important;
      margin: 0 3px !important;
      border: 1px solid var(--border) !important;
      font-weight: 900;
    }
    .pgs-ui .dataTables_wrapper .dataTables_paginate .paginate_button.current{
      background: linear-gradient(135deg, var(--brand), var(--brand2)) !important;
      color:#fff !important;
      border: none !important;
    }
  </style>
</head>

<?php date_default_timezone_set('America/Bogota'); ?>

<body>
  <div id="preloader">
    <div class="sk-three-bounce">
      <div class="sk-child sk-bounce1"></div>
      <div class="sk-child sk-bounce2"></div>
      <div class="sk-child sk-bounce3"></div>
    </div>
  </div>

  <?php include './admin/include/menu_movil_vistas.php'; ?>
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
                <li class="breadcrumb-item active"><a href="javascript:void(0)"><?php echo $modulo ?></a></li>
              </ol>
            </div>

            <!-- HERO -->
            <div class="hero">
              <div class="hero-top">
                <div>
                  <span class="pill"><span class="dot"></span> Access Control</span>
                  <h3 class="h-title">Users</h3>
                  <div class="h-sub">Manage accounts, enable/disable access, and assign permissions.</div>
                </div>

                <div class="d-flex mt-2 mt-sm-0" style="gap:10px; flex-wrap:wrap;">
                  <?php if ($create) { ?>
                    <button class="btn btn-saas btn-brand" data-target="#myModal" data-toggle="modal" data-backdrop="static" data-keyboard="false">
                      + New User
                    </button>
                  <?php } ?>
                </div>
              </div>
            </div>

            <!-- TABLE CARD -->
            <div class="row">
              <div class="col-12">
                <div class="card pgs-card">
                  <div class="card-header">
                    <h4 class="card-title">Users registered in the system</h4>
                  </div>

                  <div class="card-body">
                    <div class="table-wrap">
                      <table id="dynamictable" class="table table-hover table-responsive-sm mb-0">
                        <thead>
                          <tr>
                            <th style="width:90px;">Picture</th>
                            <th>User</th>
                            <th>Name</th>
                            <th>Last Name</th>
                            <th>Type</th>
                            <th>Enable</th>
                            <th style="width:190px;">Action</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                          $c = count($arr);
                          if ($isvalid) {
                            for ($i = 0; $i < $c; $i++) {
                              if ($arr[$i]["tipo"] !== "SuperAdministrador") {

                                $imgRaw = $arr[$i]["img"];
                                $hasImg = ($imgRaw !== null && $imgRaw !== "" && $imgRaw !== "no_image.png");

                                $img = $hasImg ? ("assets/img/admin/" . $imgRaw) : 'assets/img/logo-spiderP.png';
                          ?>
                                <tr id="prod<?php echo $arr[$i]['id'] ?>">
                                  <td class="text-primary">
                                    <div class="avatar">
                                      <img
                                        src="<?php echo $img; ?>"
                                        alt="User picture"
                                        <?php echo $hasImg ? '' : 'class="is-logo"'; ?>
                                      />
                                    </div>
                                  </td>
                                  <td class="text-primary"><b><?php echo $arr[$i]['nickname']; ?></b></td>
                                  <td class="text-primary"><?php echo $arr[$i]['nombre']; ?></td>
                                  <td class="text-primary"><?php echo $arr[$i]['apellido']; ?></td>
                                  <td>
                                    <span class="badge-pillx badge-role"><?php echo $arr[$i]['tipo']; ?></span>
                                  </td>
                                  <td>
                                    <?php
                                      $en = strtolower(trim((string)$arr[$i]['habilitado']));
                                      $isOn = ($en === 'yes');
                                    ?>
                                    <span class="badge-pillx <?php echo $isOn ? 'badge-on' : 'badge-off'; ?>">
                                      <?php echo $isOn ? 'Enabled' : 'Disabled'; ?>
                                    </span>
                                  </td>
                                  <td style="white-space:nowrap;">
                                    <?php if ($edit) { ?>
                                      <button type="button" class="btn btn-outline-primary btn-sm"
                                        onclick="USUARIO.editdata(<?php echo $arr[$i]['id']; ?>);" title="Edit">
                                        <i class="fa fa-pencil"></i>
                                      </button>
                                    <?php } ?>

                                    <?php if ($delete) { ?>
                                      <button type="button" class="btn btn-outline-danger btn-sm"
                                        onclick="USUARIO.deletedata(<?php echo $arr[$i]['id']; ?>);" title="Delete">
                                        <i class="fa fa-times"></i>
                                      </button>
                                    <?php } ?>

                                    <?php if ($enable) { ?>
                                      <button type="button" class="btn btn-outline-warning btn-sm"
                                        onclick="USUARIO.enabledata(<?php echo $arr[$i]['id']; ?>, '<?php echo $arr[$i]['habilitado']; ?>');" title="Enable/Disable">
                                        <i class="fa fa-unlock"></i>
                                      </button>
                                    <?php } ?>

                                    <?php if ($permits) { ?>
                                      <button type="button" class="btn btn-outline-info btn-sm"
                                        onclick="PERMISOS.editpermission(<?php echo $arr[$i]['id']; ?>);" title="Permits">
                                        <i class="fa fa-check"></i>
                                      </button>
                                    <?php } ?>
                                  </td>
                                </tr>
                          <?php
                              }
                            }
                          }
                          ?>
                        </tbody>
                      </table>
                    </div>
                  </div>

                </div>
              </div>
            </div>

          </div><!-- /page-wrap -->
        </div><!-- /pgs-ui -->
      </div>
    </div>
  </div>

  <!-- MODAL: Create/Edit User -->
  <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="lbcondiciones_rgpd" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content pgs-ui">
        <div class="modal-header card-header card-header-danger">
          <h4 class="modal-title">Create User</h4>
          <button type="button" onclick="UTIL.clearForm('formcreate');" class="close" data-dismiss="modal">&times;</button>
        </div>

        <div class="modal-body">
          <form id="formcreate" autocomplete="off">
            <input type="hidden" name="op" id="op" />
            <input type="hidden" name="id" id="id" />

            <div class="row">
              <div class="col-sm-12">
                <p class="validateTips"></p>
              </div>

              <div class="col-sm-6">
                <div class="form-group">
                  <label class="bmd-label-floating">Name <b class="errLbl">*</b></label>
                  <input type="text" class="form-control" id="nombre" name="nombre">
                </div>
              </div>

              <div class="col-sm-6">
                <div class="form-group">
                  <label class="bmd-label-floating">Last Name <b class="errLbl">*</b></label>
                  <input type="text" class="form-control" id="apellido" name="apellido">
                </div>
              </div>

              <div class="col-sm-6">
                <div class="form-group">
                  <label class="bmd-label-floating">Type</label>
                  <select class="form-control" id="tipo" name="tipo">
                    <option value="Administrador">Manager</option>
                    <option value="Staff">Staff</option>
                    <?php if (SessionData::superAdministrador()) { ?>
                      <option value="SuperAdministrador">Super Manager</option>
                    <?php } ?>
                  </select>
                </div>
              </div>

              <div class="col-sm-6">
                <div class="form-group">
                  <label for="habilitado" class="bmd-label-floating">Enable</label>
                  <select class="form-control" id="habilitado" name="habilitado">
                    <option value="yes">Yes</option>
                    <option value="no">No</option>
                  </select>
                </div>
              </div>

              <div class="col-sm-6">
                <div class="form-group">
                  <label class="bmd-label-floating">HOA designated (Name) <b class="errLbl">*</b></label>
                  <select class="form-control" id="tbl_unidad_id" name="tbl_unidad_id">
                    <?php echo $optionUnidades; ?>
                  </select>
                </div>
              </div>

              <div class="col-sm-6">
                <div class="form-group">
                  <label class="bmd-label-floating">User name <b class="errLbl">*</b></label>
                  <input type="email" class="form-control" id="nickname" name="nickname" value="">
                  <input type="hidden" class="form-control" name="nickname2" id="nickname2">
                </div>
              </div>

              <div class="col-sm-6">
                <div class="form-group">
                  <label class="bmd-label-floating">Password <b class="errLbl">*</b></label>
                  <input type="password" class="form-control" id="hashpass" name="hashpass">
                </div>
              </div>

              <div class="col-sm-6">
                <div class="form-group">
                  <label class="bmd-label-floating">Retype password <b class="errLbl">*</b></label>
                  <input type="password" class="form-control" id="hashpass1" name="hashpass1">
                </div>
              </div>

              <div class="col-sm-6">
                <div class="form-group">
                  <label class="col-sm-4 control-label" for="exampleInputName2">File</label>
                  <div class="col-sm-8">
                    <div class="controls">
                      <iframe id='ifm' name='ifm' src="upload.php" width="200" height="60" scrolling="no" frameborder="0"></iframe>
                    </div>
                  </div>
                </div>
              </div>

            </div>
          </form>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-saas" onclick="UTIL.clearForm('formcreate');" data-dismiss="modal">Cancel</button>
          <button type="button" onclick="USUARIO.validateData();" class="btn btn-saas btn-brand">Save</button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL: Permission Assignment -->
  <div class="modal fade" id="myModalPermisos" tabindex="-1" role="dialog" aria-labelledby="lbcondiciones_rgpd" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content pgs-ui">

        <div class="modal-header card-header card-header-danger">
          <h4 class="modal-title">Permission Assignment</h4>
          <button type="button" onclick="UTIL.clearForm('formpermission');" class="close" data-dismiss="modal">&times;</button>
        </div>

        <div class="modal-body">
          <form id="formpermission" autocomplete="off">
            <div class="container-fluid">
              <div class="row">
                <div class="card w-100">
                  <div class="card-header card-header-tabs card-header card-header-spider">
                    <div class="nav-tabs-navigation">
                      <div class="nav-tabs-wrapper">
                        <span class="nav-tabs-title">Permissions</span>
                      </div>
                    </div>
                  </div>

                  <div class="card-body">
                    <div class="table-responsive">
                      <table class="table mb-0">
                        <thead class="text-primary">
                          <tr>
                            <th style="width:60px;">
                              <div class="form-check">
                                <label class="form-check-label">
                                  <input class="form-check-input" onChange="PERMISOS.checkAll();" name="check_permisos" id="check_permisos" type="checkbox" value="">
                                  <span class="form-check-sign">
                                    <span class="check"></span>
                                  </span>
                                </label>
                              </div>
                            </th>
                            <th>Permissions</th>
                          </tr>
                        </thead>
                        <tbody id="permission"></tbody>
                      </table>
                    </div>
                  </div>

                </div>
              </div>
            </div>
          </form>

          <div class="modal-footer">
            <button type="button" class="btn btn-saas" onclick="UTIL.clearForm('formpermission');" data-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-saas btn-brand" onclick="PERMISOS.savepermission();">Save</button>
          </div>

        </div>
      </div>
    </div>
  </div>

  <?php include './admin/include/gerenic_footer.php'; ?>
  <?php include './admin/include/generic_search.php'; ?>

  <?php include './admin/include/gerenic_script.php'; ?>
  <?php include './admin/include/generic_dataTables.php'; ?>

  <script type="text/javascript" src="./admin/js/lib/data-md5.js"></script>
  <script type="text/javascript" src="./admin/js/usuario.js"></script>
  <script type="text/javascript" src="./admin/js/permisos.js"></script>

</body>
</html>