<?php
require './admin/include/generic_classes.php';
include './admin/classes/Empleado.php';

// Permissions
$view   = SessionData::getPermission(33);
$create = SessionData::getPermission(34);
$edit   = SessionData::getPermission(35);
$delete = SessionData::getPermission(36);
$enable = SessionData::getPermission(37);

if (!$view) { require 'permiso_denegado.php'; exit; }

// Search
if (isset($_POST['search']) && $_POST['search'] != "") {
  $rqs = array('search' => $_POST['search']);
  $arr = Empleado::search($rqs);
} else {
  $arr = Empleado::getAll(null);
}

$isvalid = $arr['output']['valid'];
$arr     = $arr['output']['response'];

$modulo = 'Uniforms';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include './admin/include/generic_head.php'; ?>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>

  <style>
    /* ===========================
       Premium SaaS (RED/BLACK)
       UI ONLY - no backend changes
       =========================== */
    .uni-ui{
      --brand:#e11d48;     /* red */
      --brand2:#fb7185;    /* soft red */
      --black:#0b0f16;     /* deep black */
      --muted:#6b7280;
      --border:#eceef4;
      --soft:#f7f8fc;
      --shadow: 0 18px 60px rgba(2,6,23,.12);
      --shadow2: 0 10px 26px rgba(2,6,23,.10);
      --r18:18px;
      --r24:24px;
      color: var(--black) !important;
    }
    .uni-ui *{ box-sizing:border-box; }

    .uni-hero{
      border-radius: 26px;
      padding: 14px 14px;
      background:
        radial-gradient(900px 320px at 12% 0%, rgba(225,29,72,.22), transparent 60%),
        radial-gradient(900px 320px at 92% 10%, rgba(0,0,0,.18), transparent 60%),
        linear-gradient(135deg, #ffffff 0%, #fbfbfd 55%, #f7f8fc 100%);
      border: 1px solid var(--border);
      box-shadow: var(--shadow);
      overflow: hidden;
      margin-bottom: 12px;
    }
    .uni-top{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:12px;
      flex-wrap:wrap;
    }
    .uni-title{
      margin:0;
      font-weight: 1000;
      letter-spacing:-.3px;
      font-size: 18px;
      line-height:1.2;
    }
    .uni-sub{
      margin-top: 6px;
      color: var(--muted);
      font-weight: 750;
      font-size: 12px;
    }
    .uni-chip{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding: 8px 10px;
      border-radius: 999px;
      border: 1px solid rgba(225,29,72,.18);
      background: rgba(225,29,72,.10);
      color: rgba(225,29,72,.95);
      font-weight: 950;
      font-size: 12px;
      white-space: nowrap;
    }
    .uni-dot{
      width:9px;height:9px;border-radius:999px;
      background: linear-gradient(135deg, var(--brand), var(--brand2));
      box-shadow: 0 0 0 4px rgba(225,29,72,.14);
    }

    /* Card polish */
    .card-premium{
      border-radius: 24px !important;
      border: 1px solid var(--border) !important;
      box-shadow: var(--shadow) !important;
      overflow:hidden;
    }
    .card-premium .card-header{
      background: linear-gradient(180deg,#ffffff,#f8fafc) !important;
      border-bottom: 1px solid var(--border) !important;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      flex-wrap:wrap;
    }
    .card-premium .card-title{
      color: var(--black) !important;
      font-weight: 1000 !important;
      letter-spacing:-.2px;
      margin:0;
    }

    /* Search bar premium */
    .search-wrap{
      display:flex;
      align-items:center;
      gap:10px;
      flex-wrap:wrap;
    }
    .searchbar{
      display:flex;
      align-items:center;
      gap:10px;
      padding: 8px 10px;
      border-radius: 16px;
      border: 1px solid rgba(236,238,244,.95);
      background: #fff;
      box-shadow: var(--shadow2);
      min-width: 320px;
    }
    .searchbar i{
      color: rgba(225,29,72,.95);
    }
    .searchbar input{
      border: 0 !important;
      outline: none !important;
      box-shadow:none !important;
      width: 240px;
      font-weight: 750;
      color: var(--black);
      background: transparent;
    }
    .btn-search{
      border: 0;
      border-radius: 14px;
      padding: 10px 12px;
      font-weight: 950;
      font-size: 12px;
      background: linear-gradient(135deg, var(--brand), #111827);
      color:#fff !important;
      box-shadow: 0 14px 28px rgba(225,29,72,.18);
      transition: transform .12s ease, box-shadow .12s ease, filter .12s ease;
      display:inline-flex;
      align-items:center;
      gap:8px;
    }
    .btn-search:hover{
      transform: translateY(-1px);
      filter: brightness(1.03);
      box-shadow: 0 18px 34px rgba(225,29,72,.24);
    }

    /* Table polish */
    .table thead th{
      background: #0b0f16;
      color: #fff;
      font-weight: 1000;
      border-bottom: 0 !important;
      white-space: nowrap;
      vertical-align: middle !important;
    }
    .table tbody td{
      vertical-align: middle !important;
      border-top: 1px solid rgba(236,238,244,.90) !important;
      color: #0b0f16;
      font-weight: 650;
    }
    .table tbody tr:hover{ background:#fff5f7; }

    .badge-size{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding: 6px 10px;
      border-radius: 999px;
      font-weight: 950;
      font-size: 12px;
      border: 1px solid rgba(236,238,244,.95);
      background: #fff;
      box-shadow: 0 10px 20px rgba(2,6,23,.06);
      white-space: nowrap;
      min-width: 56px;
    }
    .badge-shirt{ border-color: rgba(225,29,72,.20); background: rgba(225,29,72,.10); color: rgba(225,29,72,.95); }
    .badge-pant{ border-color: rgba(17,24,39,.18); background: rgba(17,24,39,.06); color: #111827; }
    .badge-shoes{ border-color: rgba(0,0,0,.18); background: rgba(0,0,0,.05); color: #0b0f16; }

    /* DataTables small polish */
    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select{
      border-radius: 12px !important;
      border: 1px solid rgba(236,238,244,.95) !important;
      padding: 8px 10px !important;
      outline: none !important;
      box-shadow: none !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button{
      border-radius: 12px !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current{
      background: linear-gradient(135deg, var(--brand), #111827) !important;
      color:#fff !important;
      border: none !important;
    }

    @media (max-width: 576px){
      .searchbar{ min-width: 100%; width:100%; }
      .searchbar input{ width: 100%; }
      .btn-search{ width:100%; justify-content:center; }
      .uni-hero{ border-radius: 22px; padding: 12px; }
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
        <div class="uni-ui">

          <div class="page-titles">
            <ol class="breadcrumb">
              <li class="breadcrumb-item"><a href="javascript:void(0)">Home</a></li>
              <li class="breadcrumb-item active"><a href="javascript:void(0)"><?php echo $modulo; ?></a></li>
            </ol>
          </div>

          <div class="uni-hero">
            <div class="uni-top">
              <div>
                <div class="uni-chip"><span class="uni-dot"></span> Uniform tracking</div>
                <h4 class="uni-title">Uniforms Employes</h4>
                <div class="uni-sub">
                  Showing <?php echo (int)count($arr); ?> records
                  <?php if (!empty($_POST['search'])): ?>
                    • Filter: <b><?php echo htmlspecialchars((string)$_POST['search']); ?></b>
                  <?php endif; ?>
                </div>
              </div>

              <div class="search-wrap">
                <form name="form" action="uniformes.php" class="navbar-form" method="POST" style="margin:0;">
                  <div class="searchbar">
                    <i class="fa fa-search"></i>
                    <input type="text" id="search" name="search" class="form-control"
                           placeholder="Search by name / ID..." value="<?php echo isset($_POST['search']) ? htmlspecialchars((string)$_POST['search']) : ''; ?>">
                    <button type="submit" class="btn-search" title="Search">
                      <i class="fa fa-arrow-right"></i> Search
                    </button>
                  </div>
                </form>
              </div>

            </div>
          </div>

          <div class="row">
            <div class="col-12">
              <div class="card card-premium">
                <div class="card-header">
                  <h4 class="card-title">Uniforms list</h4>
                  <span class="uni-chip"><span class="uni-dot"></span> Red & Black theme</span>
                </div>

                <div class="card-body">
                  <div class="table-responsive">
                    <table id="dynamictable" class="table table-hover table-responsive-sm">
                      <thead>
                        <tr>
                          <th>Name</th>
                          <th>Shirt Size</th>
                          <th>Pant Size</th>
                          <th>Shoes Size</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                          $c = count($arr);
                          if ($isvalid) {
                            for ($i = 0; $i < $c; $i++) {
                        ?>
                              <tr>
                                <td class="text-primary"><?php echo $arr[$i]['nombre']; ?></td>
                                <td>
                                  <span class="badge-size badge-shirt"><?php echo htmlspecialchars((string)$arr[$i]['camisa']); ?></span>
                                </td>
                                <td>
                                  <span class="badge-size badge-pant"><?php echo htmlspecialchars((string)$arr[$i]['pantalon']); ?></span>
                                </td>
                                <td>
                                  <span class="badge-size badge-shoes"><?php echo htmlspecialchars((string)$arr[$i]['calzado']); ?></span>
                                </td>
                              </tr>
                        <?php
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

        </div>
      </div>
    </div>

    <?php include './admin/include/gerenic_footer.php'; ?>
  </div>

  <?php include './admin/include/gerenic_script.php'; ?>
  <?php include './admin/include/generic_search.php'; ?>
  <?php include './admin/include/generic_dataTables.php'; ?>

  <!-- keep your script (even if not used, we don't break anything) -->
  <script type="text/javascript" src="./admin/js/cuadre_caja.js"></script>
</body>
</html>