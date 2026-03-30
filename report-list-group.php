<?php
require './admin/include/generic_classes.php';
include './admin/classes/Producto.php';
include './admin/classes/Unidades.php';

// Permissions
$view   = SessionData::getPermission(21);
$create = SessionData::getPermission(17);
$edit   = SessionData::getPermission(20);
$delete = SessionData::getPermission(18);
$enable = SessionData::getPermission(19);

if (!$view) { require 'permiso_denegado.php'; }

// Units options
$arrUnidades = Unidades::getAll(null);
$isvalidCat  = $arrUnidades['output']['valid'];
$arrUnidades = $arrUnidades['output']['response'];

$optionUnidades = '<option value="seleccione">Select...</option>';

$userUnidad = intval(SessionData::getUnidadUser());
$userType   = SessionData::getUserType();

foreach ($arrUnidades as $val) {
  $unidadId = intval($val['id']);

  $mostrarUnidad = (
    $userType == Util::SuperAdmin() ||
    ($userType == Util::Manager() && $userUnidad === $unidadId) ||
    ($userType == Util::Staff()   && $userUnidad === $unidadId)
  );

  if ($mostrarUnidad) {
    $optionUnidades .= "<option value='{$unidadId}'>{$unidadId} - " . htmlspecialchars($val['nombre']) . "</option>";
  }
}

// ✅ IMPORTANT: generic_header.php uses $modulo
$modulo = 'Grouped Report Download';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include './admin/include/generic_head.php'; ?>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>

  <style>
    /* ==========================================================
      PGS – Grouped Report Download (SaaS Premium) | Red + Black
      UI ONLY — no backend changes
    ========================================================== */
    .pgs-group{
      --pgs-red:#E11D2E;
      --pgs-red2:#B3121E;
      --pgs-black:#0B0F14;

      --bg:#f6f7fb;
      --card:#ffffff;
      --text:#0f172a;
      --muted:#64748b;

      --border: rgba(2,6,23,.10);
      --border2: rgba(2,6,23,.06);

      --shadow: 0 18px 60px rgba(2,6,23,.10);
      --shadow2: 0 10px 26px rgba(2,6,23,.08);

      --r14:14px;
      --r18:18px;
      --r24:24px;

      --ring: 0 0 0 4px rgba(225,29,46,.14);

      color: var(--text);
    }
    .pgs-group *{ box-sizing:border-box; }

    .pgs-group .wrap{ padding: 8px 0 18px; }

    /* HERO */
    .pgs-group .hero{
      border-radius: var(--r24);
      padding: 16px;
      background:
        radial-gradient(900px 260px at 10% 0%, rgba(225,29,46,.16), transparent 60%),
        radial-gradient(780px 240px at 95% 10%, rgba(11,15,20,.14), transparent 60%),
        linear-gradient(135deg, #ffffff 0%, #fbfdff 55%, #f7f9ff 100%);
      border: 1px solid var(--border);
      box-shadow: var(--shadow);
      overflow:hidden;
      margin-bottom: 14px;
    }
    .pgs-group .hero-top{
      display:flex;
      gap:12px;
      align-items:flex-start;
      justify-content:space-between;
      flex-wrap:wrap;
    }
    .pgs-group .pill{
      display:inline-flex;
      align-items:center;
      gap:10px;
      padding: 10px 12px;
      border-radius: 999px;
      background: rgba(255,255,255,.9);
      border: 1px solid var(--border);
      font-weight: 950;
      box-shadow: var(--shadow2);
      color: var(--pgs-black);
      white-space: nowrap;
    }
    .pgs-group .pill .dot{
      width:10px; height:10px; border-radius:999px;
      background: linear-gradient(135deg, var(--pgs-red), var(--pgs-red2));
      box-shadow: 0 0 0 4px rgba(225,29,46,.14);
    }
    .pgs-group .h-title{
      font-weight: 1000;
      letter-spacing: -.3px;
      font-size: 20px;
      line-height: 1.1;
      color: var(--pgs-black);
      margin:0;
    }
    .pgs-group .h-sub{
      margin-top: 6px;
      font-weight: 800;
      font-size: 12px;
      color: var(--muted);
    }

    /* Card SaaS */
    .pgs-group .card-saas{
      border-radius: var(--r24);
      background: linear-gradient(180deg, #fff, #fbfcfe);
      border: 1px solid var(--border);
      box-shadow: var(--shadow);
      overflow:hidden;
    }
    .pgs-group .card-head{
      padding: 14px 16px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      flex-wrap:wrap;
      background: linear-gradient(180deg, #ffffff, #f8fafc);
      border-bottom: 1px solid var(--border);
    }
    .pgs-group .card-head h4{
      margin:0;
      font-weight: 1000;
      letter-spacing:-.25px;
      color: var(--pgs-black);
    }
    .pgs-group .badge{
      display:inline-flex;
      align-items:center;
      gap:8px;
      font-weight: 950;
      font-size: 12px;
      padding: 7px 10px;
      border-radius: 999px;
      background: rgba(225,29,46,.08);
      border:1px solid rgba(225,29,46,.18);
      color: var(--pgs-red2);
      white-space: nowrap;
    }
    .pgs-group .body{ padding: 14px 16px; }

    /* Form grid */
    .pgs-group .form-grid{
      display:grid;
      grid-template-columns: 1fr;
      gap: 12px;
    }
    @media (min-width: 992px){
      .pgs-group .form-grid{ grid-template-columns: repeat(3, 1fr); }
    }

    /* Controls */
    .pgs-group .control-label{
      font-weight: 950;
      font-size: 12px;
      color: #334155;
      margin-bottom: 6px;
      display:flex;
      align-items:center;
      gap:8px;
      text-transform: uppercase;
      letter-spacing: .25px;
    }
    .pgs-group .req{
      color: var(--pgs-red);
      font-weight: 1000;
    }

    .pgs-group .control{
      width:100% !important;
      border-radius: 14px !important;
      border: 1px solid rgba(2,6,23,.12) !important;
      background: #fff !important;
      padding: 12px 12px !important;
      font-weight: 900 !important;
      color: #0f172a !important;
      outline: none !important;
      box-shadow: 0 8px 18px rgba(2,6,23,.06);
      transition: box-shadow .12s ease, border-color .12s ease, transform .12s ease;
    }
    .pgs-group .control:focus{
      border-color: rgba(225,29,46,.45) !important;
      box-shadow: var(--ring) !important;
    }

    /* Actions */
    .pgs-group .actions{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      align-items:center;
      justify-content:flex-end;
      margin-top: 12px;
    }
    @media (max-width: 991px){
      .pgs-group .actions{ justify-content:stretch; }
      .pgs-group .actions .btnx{ flex:1 1 auto; }
    }

    .pgs-group .btnx{
      border-radius: 14px !important;
      padding: 12px 14px !important;
      font-weight: 1000 !important;
      letter-spacing: .2px;
      border: 1px solid rgba(2,6,23,.12) !important;
      background: #fff !important;
      color: #0f172a !important;
      box-shadow: 0 10px 22px rgba(2,6,23,.10);
      transition: transform .12s ease, filter .12s ease, box-shadow .12s ease, border-color .12s ease;
    }
    .pgs-group .btnx:hover{
      transform: translateY(-1px);
      border-color: rgba(225,29,46,.30) !important;
      box-shadow: 0 14px 26px rgba(2,6,23,.14);
    }

    .pgs-group .btnx.primary{
      border: none !important;
      color: #fff !important;
      background: linear-gradient(135deg, var(--pgs-red), var(--pgs-red2)) !important;
      box-shadow: 0 18px 40px rgba(225,29,46,.22);
    }
    .pgs-group .btnx.primary:hover{
      filter: brightness(1.03);
      box-shadow: 0 22px 46px rgba(225,29,46,.28);
    }

    .pgs-group .helper{
      margin-top: 10px;
      font-size: 12px;
      color: var(--muted);
      font-weight: 800;
      line-height: 1.45;
    }

    /* Breadcrumb tweak without breaking theme */
    .pgs-group .breadcrumb .breadcrumb-item a{ font-weight: 900; }

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
        <div class="page-titles">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="javascript:void(0)">Reports</a></li>
            <li class="breadcrumb-item active"><a href="javascript:void(0)"><?php echo htmlspecialchars($modulo); ?></a></li>
          </ol>
        </div>

        <div class="pgs-group">
          <div class="wrap">

            <!-- HERO -->
            <div class="hero">
              <div class="hero-top">
                <div style="display:flex; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                  <span class="pill"><span class="dot"></span> Group Download</span>
                  <div>
                    <h3 class="h-title">Download Reports by HOA and Date Range</h3>
                    <div class="h-sub">
                      Select a property (HOA) and a date range to generate the grouped report list.
                    </div>
                  </div>
                </div>
                <span class="badge">Secure • Filtered by role</span>
              </div>
            </div>

            <!-- FORM CARD -->
            <div class="card-saas">
              <div class="card-head">
                <h4><?php echo htmlspecialchars($modulo); ?></h4>
                <span class="badge">Search & Generate</span>
              </div>

              <div class="body">
                <!-- ✅ Do not change form attributes -->
                <form name="form" action="daily_report_view_group.php" class="navbar-form" method="POST">

                  <div class="form-grid">
                    <div>
                      <div class="control-label">HOA / Property <span class="req">*</span></div>
                      <select class="form-control control" id="hoa" name="hoa">
                        <?php echo $optionUnidades; ?>
                      </select>
                    </div>

                    <div>
                      <div class="control-label">Start date <span class="req">*</span></div>
                      <input type="date" id="f1" name="f1" class="form-control control" placeholder="Start Date">
                    </div>

                    <div>
                      <div class="control-label">End date <span class="req">*</span></div>
                      <input type="date" id="f2" name="f2" class="form-control control" placeholder="End Date">
                    </div>
                  </div>

                  <div class="actions">
                    <button type="submit" class="btn btnx primary">Search</button>
                    <button type="button" class="btn btnx" onclick="document.getElementById('hoa').value='seleccione'; document.getElementById('f1').value=''; document.getElementById('f2').value='';">
                      Clear
                    </button>
                  </div>

                  <div class="helper">
                    • Results will be generated in the next screen. <br>
                    • Your access is automatically filtered by your role (Super Admin / Manager / Staff).
                  </div>

                </form>
              </div>
            </div>

          </div><!-- wrap -->
        </div><!-- pgs-group -->

      </div>
    </div>
  </div>

  <?php include './admin/include/gerenic_footer.php'; ?>
  <?php include './admin/include/gerenic_script.php'; ?>
</body>
</html>