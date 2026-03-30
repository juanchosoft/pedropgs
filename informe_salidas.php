<?php
require './admin/include/generic_classes.php';
include './admin/classes/InformeTiempo.php';

$arr = InformeTiempo::getAll(null);
$isvalid  = $arr['output']['valid'];
$entradas = $arr['output']['response'];

$modulo = 'Record time Employes';

// Permissions
$view   = SessionData::getPermission(33);
$create = SessionData::getPermission(34);
$edit   = SessionData::getPermission(35);
$delete = SessionData::getPermission(36);
$enable = SessionData::getPermission(37);

if (!$view) { require 'permiso_denegado.php'; exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include './admin/include/generic_head.php'; ?>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>

  <style>
    /* ===========================
       SaaS Premium UI (RED/BLACK)
       UI ONLY - no backend changes
       =========================== */
    .rtp-ui{
      --brand:#e11d48;      /* red */
      --brand2:#fb7185;     /* soft red */
      --black:#0b0f16;      /* deep black */
      --ink:#0b0f16;
      --muted:#6b7280;
      --border:#eceef4;
      --soft:#f6f7fb;
      --shadow: 0 18px 60px rgba(2,6,23,.12);
      --shadow2: 0 10px 26px rgba(2,6,23,.12);
      --r16:16px;
      --r24:24px;
      color: var(--ink) !important;
    }
    .rtp-ui *{ box-sizing:border-box; }

    .rtp-hero{
      border-radius: 26px;
      padding: 14px 14px;
      background:
        radial-gradient(900px 320px at 12% 0%, rgba(225,29,72,.22), transparent 60%),
        radial-gradient(900px 320px at 92% 10%, rgba(0,0,0,.18), transparent 60%),
        linear-gradient(135deg, #ffffff 0%, #fbfbfd 55%, #f7f8fc 100%);
      border: 1px solid var(--border);
      box-shadow: var(--shadow);
      overflow: hidden;
    }

    .rtp-top{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:12px;
      flex-wrap:wrap;
      margin-bottom: 12px;
    }
    .rtp-title{
      margin:0;
      font-weight: 1000;
      letter-spacing:-.3px;
      font-size: 18px;
      line-height:1.2;
    }
    .rtp-sub{
      margin-top: 6px;
      color: var(--muted);
      font-weight: 750;
      font-size: 12px;
    }

    .rtp-chip{
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
    .rtp-dot{
      width:9px;height:9px;border-radius:999px;
      background: linear-gradient(135deg, var(--brand), var(--brand2));
      box-shadow: 0 0 0 4px rgba(225,29,72,.14);
    }

    /* Card polish */
    .card-premium{
      border-radius: 24px !important;
      border: 1px solid var(--border) !important;
      box-shadow: 0 18px 60px rgba(2,6,23,.12) !important;
      overflow:hidden;
    }
    .card-premium .card-header{
      background:
        linear-gradient(180deg,#ffffff,#f8fafc) !important;
      border-bottom: 1px solid var(--border) !important;
    }
    .card-premium .card-title{
      color: var(--black) !important;
      font-weight: 1000 !important;
      letter-spacing:-.2px;
    }

    /* Table polish (DataTables friendly) */
    table.dataTable, .table{
      border-collapse: separate !important;
      border-spacing: 0 !important;
    }
    .table thead th{
      background: #0b0f16;
      color: #fff;
      font-weight: 1000;
      border-bottom: 0 !important;
      vertical-align: middle !important;
      white-space: nowrap;
    }
    .table tbody td{
      vertical-align: middle !important;
      border-top: 1px solid rgba(236,238,244,.90) !important;
      color: #0b0f16;
      font-weight: 650;
    }
    .table tbody tr:hover{
      background: #fff5f7;
    }

    .pill-time{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding: 6px 10px;
      border-radius: 999px;
      border: 1px solid rgba(236,238,244,.95);
      background: #fff;
      font-weight: 950;
      font-size: 12px;
      color:#0b0f16;
      box-shadow: 0 10px 20px rgba(2,6,23,.06);
      white-space: nowrap;
    }
    .pill-hours{
      border: 1px solid rgba(225,29,72,.25);
      background: rgba(225,29,72,.10);
      color: rgba(225,29,72,.95);
    }

    .btn-map{
      border: 0;
      border-radius: 12px;
      padding: 8px 10px;
      font-weight: 950;
      font-size: 12px;
      background: linear-gradient(135deg, var(--brand), #111827);
      color:#fff !important;
      box-shadow: 0 14px 28px rgba(225,29,72,.18);
      transition: transform .12s ease, box-shadow .12s ease, filter .12s ease;
      display:inline-flex;
      align-items:center;
      gap:8px;
      text-decoration:none !important;
    }
    .btn-map:hover{
      transform: translateY(-1px);
      filter: brightness(1.03);
      box-shadow: 0 18px 34px rgba(225,29,72,.24);
    }
    .btn-map i{ color:#fff; }

    /* Modal premium */
    #modalMap .modal-content{
      border-radius: 18px !important;
      border: 1px solid rgba(236,238,244,.95) !important;
      box-shadow: 0 30px 90px rgba(0,0,0,.28) !important;
      overflow:hidden;
    }
    #modalMap .modal-header{
      background: linear-gradient(135deg, #0b0f16, #111827) !important;
      border-bottom: 1px solid rgba(255,255,255,.08) !important;
    }
    #modalMap .modal-title{
      color:#fff !important;
      font-weight: 1000;
      letter-spacing:-.2px;
    }
    #modalMap .close span{ color:#fff; }
    #mapId{
      border-radius: 16px;
      overflow:hidden;
      border: 1px solid rgba(236,238,244,.95);
      box-shadow: 0 10px 24px rgba(2,6,23,.10);
    }

    /* DataTables controls (small polish) */
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
      .rtp-hero{ padding: 12px; border-radius: 22px; }
      .rtp-title{ font-size: 16px; }
      .table thead th, .table tbody td{ font-size: 13px; }
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
        <div class="rtp-ui">

          <div class="page-titles">
            <ol class="breadcrumb">
              <li class="breadcrumb-item"><a href="javascript:void(0)">Home</a></li>
              <li class="breadcrumb-item active"><a href="javascript:void(0)"><?php echo $modulo; ?></a></li>
            </ol>
          </div>

          <div class="row">
            <div class="col-12">

              <div class="rtp-hero mb-3">
                <div class="rtp-top">
                  <div>
                    <div class="rtp-chip"><span class="rtp-dot"></span> Attendance report</div>
                    <h4 class="rtp-title">Record time employees</h4>
                    <div class="rtp-sub">Open entry/exit location on map when available.</div>
                  </div>
                  <div class="rtp-chip">
                    <b>Total:</b> <?php echo (int)count($entradas); ?>
                  </div>
                </div>
              </div>

              <div class="card card-premium">
                <div class="card-header">
                  <h4 class="card-title mb-0">Record Time Employes</h4>
                </div>

                <div class="card-body">
                  <div class="table-responsive">
                    <table id="dynamictable" class="table table-hover table-responsive-sm">
                      <thead>
                        <tr>
                          <th>CC</th>
                          <th>Name</th>
                          <th>Time Entry</th>
                          <th>Map entry</th>
                          <th>Time Exit</th>
                          <th>Map exit</th>
                          <th>Hours</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                        $c = count($entradas);
                        if ($isvalid) {
                          for ($i = 0; $i < $c; $i++) {
                            $minutos = Util::calcularHorasEntreDosFechas($entradas[$i]['entrada'], $entradas[$i]['salida']);
                        ?>
                            <tr>
                              <td><?php echo $entradas[$i]['cc']; ?></td>
                              <td><?php echo $entradas[$i]['nombre']; ?></td>

                              <td>
                                <span class="pill-time">
                                  <i class="fa fa-sign-in" aria-hidden="true"></i>
                                  <?php echo $entradas[$i]['entrada']; ?>
                                </span>
                              </td>

                              <td>
                                <?php if (!empty($entradas[$i]["coords_entrada"])): ?>
                                  <a href="javascript:void(0)"
                                     data-location='<?php echo $entradas[$i]["coords_entrada"] ?>'
                                     class="btn-map classViewMap"
                                     data-type="Entry"
                                     title="View Entry map">
                                    <i class="fa fa-map-marker"></i> View
                                  </a>
                                <?php endif ?>
                              </td>

                              <td>
                                <span class="pill-time">
                                  <i class="fa fa-sign-out" aria-hidden="true"></i>
                                  <?php echo $entradas[$i]['salida']; ?>
                                </span>
                              </td>

                              <td>
                                <?php if (!empty($entradas[$i]["coords_salida"])): ?>
                                  <a href="javascript:void(0)"
                                     data-location='<?php echo $entradas[$i]["coords_salida"] ?>'
                                     class="btn-map classViewMap"
                                     data-type="Exit"
                                     title="View Exit map">
                                    <i class="fa fa-map-marker"></i> View
                                  </a>
                                <?php endif ?>
                              </td>

                              <td>
                                <span class="pill-time pill-hours">
                                  <i class="fa fa-clock-o" aria-hidden="true"></i>
                                  <?php echo $minutos; ?>
                                </span>
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

                <!-- Modal Map -->
                <div class="modal fade" id="modalMap" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                  <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">View <span id="typeEntry"></span> map</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                          <span aria-hidden="true">&times;</span>
                        </button>
                      </div>
                      <div class="modal-body">
                        <div id="mapId" style="width:100%; height: 420px"></div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
                      </div>
                    </div>
                  </div>
                </div>
                <!-- /Modal -->

              </div>

            </div>
          </div>

        </div>
      </div>
    </div>

    <?php include './admin/include/gerenic_footer.php'; ?>
  </div>

  <?php include './admin/include/gerenic_script.php'; ?>
  <?php include './admin/include/generic_dataTables.php'; ?>

  <script>
    let map;

    function initMap(lat,long) {
      map = new google.maps.Map(document.getElementById("mapId"), {
        center: { lat: lat, lng: long },
        zoom: 15,
      });

      var marker = new google.maps.Marker({
        position: {lat: lat, lng: long},
        map: map,
        title: 'Location'
      });
    }

    function initValidation(){
      $("body").on('click', '.classViewMap', function(event) {
        event.preventDefault();
        $("#mapId").html("");
        var coords       = $(this).data("location");
        var type         = $(this).data("type");
        var coords_split = String(coords || "").split(",");
        initMap(parseFloat(coords_split[0]), parseFloat(coords_split[1]));
        $("#typeEntry").html(type);
        $("#modalMap").modal("show");
      });

      $('#modalMap').on('hidden.bs.modal', function () {
        map = null;
        $("#mapId").html("");
      });
    }
  </script>

  <script
    src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAh1U7KiDQUNuf2zzJDllUob73RSKLd8aI&callback=initValidation"
    defer
  ></script>
</body>
</html>