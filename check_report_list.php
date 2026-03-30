<?php
require './admin/include/generic_classes.php';
include './admin/classes/Check.php';

// Permissions
$view   = SessionData::getPermission(21);
$create = SessionData::getPermission(17);
$edit   = SessionData::getPermission(20);
$delete = SessionData::getPermission(18);
$enable = SessionData::getPermission(19);

if (!$view) { require 'permiso_denegado.php'; }

$arr = Check::getAll(null);
$isvalid = $arr['output']['valid'];
$arr = $arr['output']['response'];

// ✅ IMPORTANT: generic_header.php uses $modulo
$modulo = 'Checklist Reports';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <?php include './admin/include/generic_head.php'; ?>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>

  <style>
    /* ==========================================================
      PGS – Reports List (SaaS Premium) | Red + Black
      UI ONLY — no backend changes
    ========================================================== */
    .pgs-reports{
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

      color: var(--text);
    }
    .pgs-reports *{ box-sizing:border-box; }

    /* Page spacing */
    .pgs-reports .wrap{ padding: 8px 0 18px; }

    /* Hero */
    .pgs-reports .hero{
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
      position:relative;
    }
    .pgs-reports .hero-top{
      display:flex;
      gap:12px;
      align-items:flex-start;
      justify-content:space-between;
      flex-wrap:wrap;
    }
    .pgs-reports .pill{
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
    .pgs-reports .pill .dot{
      width:10px; height:10px; border-radius:999px;
      background: linear-gradient(135deg, var(--pgs-red), var(--pgs-red2));
      box-shadow: 0 0 0 4px rgba(225,29,46,.14);
    }
    .pgs-reports .h-title{
      font-weight: 1000;
      letter-spacing: -.3px;
      font-size: 20px;
      line-height: 1.1;
      color: var(--pgs-black);
      margin:0;
    }
    .pgs-reports .h-sub{
      margin-top: 6px;
      font-weight: 800;
      font-size: 12px;
      color: var(--muted);
    }

    /* Card */
    .pgs-reports .card-saas{
      border-radius: var(--r24);
      background: linear-gradient(180deg, #fff, #fbfcfe);
      border: 1px solid var(--border);
      box-shadow: var(--shadow);
      overflow:hidden;
    }
    .pgs-reports .card-head{
      padding: 14px 16px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      flex-wrap:wrap;
      background: linear-gradient(180deg, #ffffff, #f8fafc);
      border-bottom: 1px solid var(--border);
    }
    .pgs-reports .card-head h4, .pgs-reports .card-head h5{
      margin:0;
      font-weight: 1000;
      letter-spacing:-.25px;
      color: var(--pgs-black);
    }
    .pgs-reports .badge{
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
    .pgs-reports .body{ padding: 14px 16px; }

    /* Table container */
    .pgs-reports .table-wrap{
      border-radius: 18px;
      border: 1px solid rgba(2,6,23,.08);
      overflow: hidden;
      background: #fff;
      box-shadow: 0 10px 22px rgba(2,6,23,.06);
    }

    /* Table */
    .pgs-reports table.dataTable,
    .pgs-reports #dynamictable{
      width: 100% !important;
      margin: 0 !important;
      border-collapse: collapse !important;
    }

    .pgs-reports #dynamictable thead th{
      background: #0B0F14;
      color: #fff !important;
      font-weight: 1000 !important;
      letter-spacing: .2px;
      border-bottom: 1px solid rgba(255,255,255,.10) !important;
      padding: 12px 12px !important;
      font-size: 12px !important;
      text-transform: uppercase;
      white-space: nowrap;
    }

    .pgs-reports #dynamictable tbody td{
      padding: 12px 12px !important;
      border-bottom: 1px solid rgba(2,6,23,.06) !important;
      vertical-align: middle !important;
      font-weight: 800;
      font-size: 13px;
      color: #0f172a;
    }
    .pgs-reports #dynamictable tbody tr:hover{
      background: rgba(225,29,46,.04) !important;
    }

    /* Column emphasis */
    .pgs-reports td.col-id{
      font-weight: 1000;
      color: var(--pgs-red2);
    }
    .pgs-reports .muted{
      color: var(--muted) !important;
      font-weight: 800;
      font-size: 12px;
    }

    /* Action button */
    .pgs-reports .btn-action{
      border: 1px solid rgba(2,6,23,.12) !important;
      background: #fff !important;
      border-radius: 12px !important;
      padding: 8px 10px !important;
      font-weight: 1000 !important;
      cursor: pointer;
      transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
      box-shadow: 0 6px 14px rgba(2,6,23,.06);
    }
    .pgs-reports .btn-action:hover{
      transform: translateY(-1px);
      border-color: rgba(225,29,46,.30) !important;
      box-shadow: 0 10px 18px rgba(2,6,23,.10);
    }
    .pgs-reports .btn-action a{
      text-decoration:none !important;
      color: inherit !important;
      display:inline-flex;
      align-items:center;
      gap:8px;
    }
    .pgs-reports .btn-action svg{ opacity:.95; }

    /* DataTables polish (works with your generic_dataTables.php) */
    .pgs-reports .dataTables_wrapper .dataTables_filter input{
      border: 1px solid rgba(2,6,23,.12) !important;
      border-radius: 14px !important;
      padding: 10px 12px !important;
      font-weight: 900 !important;
      outline: none !important;
      box-shadow: 0 6px 14px rgba(2,6,23,.06);
    }
    .pgs-reports .dataTables_wrapper .dataTables_length select{
      border: 1px solid rgba(2,6,23,.12) !important;
      border-radius: 12px !important;
      padding: 8px 10px !important;
      font-weight: 900 !important;
    }
    .pgs-reports .dataTables_wrapper .dataTables_info,
    .pgs-reports .dataTables_wrapper .dataTables_paginate{
      font-weight: 900;
      color: #334155;
    }
    .pgs-reports .dataTables_wrapper .paginate_button{
      border-radius: 12px !important;
      font-weight: 1000 !important;
    }
    .pgs-reports .dataTables_wrapper .paginate_button.current{
      background: linear-gradient(135deg, var(--pgs-red), var(--pgs-red2)) !important;
      color: #fff !important;
      border: none !important;
    }

    /* Mobile tweaks */
    @media (max-width: 576px){
      .pgs-reports .hero{ padding: 14px; }
      .pgs-reports .h-title{ font-size: 18px; }
      .pgs-reports #dynamictable thead th{ font-size: 11px !important; }
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
        <div class="page-titles">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="javascript:void(0)">Reports</a></li>
            <li class="breadcrumb-item active"><a href="javascript:void(0)"><?php echo htmlspecialchars($modulo); ?></a></li>
          </ol>
        </div>

        <div class="pgs-reports">
          <div class="wrap">

            <!-- HERO -->
            <div class="hero">
              <div class="hero-top">
                <div style="display:flex; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                  <span class="pill"><span class="dot"></span> Report History</span>
                  <div>
                    <h3 class="h-title">Checklist Reports History</h3>
                    <div class="h-sub">
                      Browse saved reports and open a full printable view.
                    </div>
                  </div>
                </div>

                <div style="display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end;">
                  <span class="badge">
                    Records: <span id="uiCount"><?php echo is_array($arr) ? count($arr) : 0; ?></span>
                  </span>
                </div>
              </div>
            </div>

            <!-- TABLE CARD -->
            <div class="card-saas">
              <div class="card-head">
                <h4>History Report List</h4>
                <span class="badge">Actions: View Report</span>
              </div>

              <div class="body">
                <div class="muted" style="margin-bottom:10px;">
                  Tip: Use the search box to filter by HOA, employee, or date.
                </div>

                <div class="table-wrap">
                  <div class="table-responsive" style="margin:0;">
                    <table id="dynamictable" class="table table-hover table-responsive-sm">
                      <thead>
                        <tr>
                          <th>ITEM</th>
                          <th>HOA</th>
                          <th>EMPLOYEE</th>
                          <th>DATE</th>
                          <th style="width:120px;">ACTIONS</th>
                        </tr>
                      </thead>

                      <tbody>
                        <?php
                          $c = is_array($arr) ? count($arr) : 0;

                          $userUnidad = SessionData::getUnidadUser();
                          $userType   = SessionData::getUserType();

                          if ($isvalid && $c > 0) {
                            for ($i = 0; $i < $c; $i++) {
                              $unidadId = isset($arr[$i]['tbl_unidad_id']) ? intval($arr[$i]['tbl_unidad_id']) : null;
                              $userUnidadInt = intval($userUnidad);

                              $mostrarUnidad = (
                                $userType == Util::SuperAdmin() ||
                                ($userType == Util::Manager() && $userUnidadInt === $unidadId) ||
                                ($userType == Util::Staff()   && $userUnidadInt === $unidadId)
                              );

                              if ($mostrarUnidad):
                        ?>
                          <tr>
                            <td class="col-id"><?php echo $arr[$i]['id']; ?></td>
                            <td><?php echo $arr[$i]['hoa']; ?></td>
                            <td><?php echo $arr[$i]['employee']; ?></td>
                            <td class="muted"><?php echo $arr[$i]['dtcreate']; ?></td>
                            <td>
                              <button class="btn-action" type="button" title="View report">
                                <a href="report_check_ok.php?report=<?php echo $arr[$i]['id']; ?>" target="_blank">
                                  <span>View</span>
                                  <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M21.938 5.25H19.5V19.5a1.5 1.5 0 1 0 3 0V5.812a.563.563 0 0 0-.563-.562Z"></path>
                                    <path d="M20.21 22.395A3.004 3.004 0 0 1 18 19.5V2.062a.563.563 0 0 0-.563-.562H2.063a.563.563 0 0 0-.562.563v17.812A2.625 2.625 0 0 0 4.125 22.5h16.071a.053.053 0 0 0 .014-.105ZM4.5 9.75v-4.5H9v4.5H4.5Zm10.5 9H4.5v-1.5H15v1.5Zm0-3H4.5v-1.5H15v1.5Zm0-3H4.5v-1.5H15v1.5Zm0-3h-4.5v-1.5H15v1.5Zm0-3h-4.5v-1.5H15v1.5Z"></path>
                                  </svg>
                                </a>
                              </button>
                            </td>
                          </tr>
                        <?php
                              endif;
                            }
                          }
                        ?>
                      </tbody>
                    </table>
                  </div>
                </div>

              </div>
            </div>

          </div><!-- wrap -->
        </div><!-- pgs-reports -->

      </div>
    </div>
  </div>

  <?php include './admin/include/gerenic_footer.php'; ?>
  <?php include './admin/include/generic_search.php'; ?>

  <?php include './admin/include/gerenic_script.php'; ?>
  <?php include './admin/include/generic_dataTables.php'; ?>
  <script type="text/javascript" src="./admin/js/check.js"></script>

  <script>
    // Small UX: update records count from visible rows (after DataTables loads)
    window.addEventListener('load', function(){
      try{
        const t = document.querySelectorAll('#dynamictable tbody tr').length;
        const el = document.getElementById('uiCount');
        if(el) el.textContent = String(t);
      }catch(e){}
    });
  </script>
</body>
</html>