<?php
require './admin/include/generic_classes.php';
include './admin/classes/Entrada.php';
include './admin/classes/Salida.php';

date_default_timezone_set('America/Bogota');
$fecha_actual = date("Y-m-d H:i:s");

// Permissions
$view   = SessionData::getPermission(33);
$create = SessionData::getPermission(34);
$edit   = SessionData::getPermission(35);
$delete = SessionData::getPermission(36);
$enable = SessionData::getPermission(37);

if (!$view) { require 'permiso_denegado.php'; }

$modulo = 'Record Time';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include './admin/include/generic_head.php'; ?>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>

  <link href="assets/css/reloj.css" rel="stylesheet" />
  <link href="https://www.dafont.com/es/ds-digital.font" rel="stylesheet" type="text/css">
  <link href="https://fonts.googleapis.com/css2?family=Electrolize&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Oswald:400,300,700" rel="stylesheet" type="text/css">

  <style>
    /* ===========================
       Record Time – Premium SaaS UI
       (UI ONLY: no backend changes)
    ============================ */
    .rt-ui{
      --brand:#E10600;
      --brand2:#B30500;
      --ink:#0B0F19;
      --muted:#64748b;
      --border:#e7eaf1;
      --shadow: 0 18px 60px rgba(2,6,23,.12);
      --shadow2: 0 10px 26px rgba(2,6,23,.10);
      --r16:16px;
      --r24:24px;
      --ring: 0 0 0 4px rgba(225,6,0,.18);
      color: var(--ink) !important;
    }
    .rt-ui *{ box-sizing:border-box; }

    .rt-bg{
      position: relative;
      border-radius: 26px;
      padding: 14px;
      background:
        radial-gradient(900px 320px at 14% 0%, rgba(225,6,0,.22), transparent 60%),
        radial-gradient(900px 320px at 92% 10%, rgba(11,15,25,.12), transparent 60%),
        linear-gradient(135deg, #ffffff 0%, #fbfbfd 55%, #f7f8fc 100%);
      border: 1px solid var(--border);
      box-shadow: var(--shadow);
      overflow:hidden;
    }

    .rt-top{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      flex-wrap:wrap;
      margin-bottom: 10px;
    }
    .rt-pill{
      display:inline-flex;
      align-items:center;
      gap:10px;
      padding: 10px 12px;
      border-radius: 999px;
      border: 1px solid var(--border);
      background: rgba(255,255,255,.94);
      font-weight: 950;
      box-shadow: var(--shadow2);
    }
    .rt-pill .dot{
      width:10px; height:10px; border-radius:999px;
      background: linear-gradient(135deg, var(--brand), var(--brand2));
      box-shadow: 0 0 0 4px rgba(225,6,0,.14);
    }
    .rt-title{
      margin:0;
      font-weight: 1000;
      letter-spacing:-.3px;
      font-size: 18px;
      line-height:1.1;
    }
    .rt-sub{
      margin-top: 5px;
      color: var(--muted);
      font-weight: 750;
      font-size: 12px;
    }

    /* Main grid */
    .rt-grid{
      display:grid;
      grid-template-columns: 1.2fr .8fr;
      gap: 14px;
      align-items: stretch;
    }
    .rt-panel{
      border-radius: 22px;
      border: 1px solid rgba(231,234,241,.95);
      background: rgba(255,255,255,.92);
      box-shadow: 0 10px 24px rgba(2,6,23,.06);
      overflow:hidden;
    }
    .rt-panel-head{
      padding: 12px 14px;
      border-bottom: 1px solid rgba(231,234,241,.95);
      background: linear-gradient(180deg, #ffffff, #f8fafc);
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      flex-wrap:wrap;
    }
    .rt-panel-head b{
      font-weight: 1000;
      letter-spacing:-.2px;
    }
    .rt-panel-body{
      padding: 14px;
    }

    /* We respect your .widget/.reloj/.fecha from reloj.css,
       but we wrap it with premium spacing and alignment */
    .rt-widget-wrap{
      display:flex;
      flex-direction:column;
      gap: 10px;
      align-items:center;
      justify-content:center;
      min-height: 380px;
    }

    /* Make the clock look centered and premium even if reloj.css is old */
    .rt-widget-wrap .fecha,
    .rt-widget-wrap .reloj{
      width: 100%;
      display:flex;
      justify-content:center;
      align-items:baseline;
      flex-wrap:wrap;
      gap: 8px;
      padding: 10px 12px;
      border-radius: 18px;
      border: 1px solid rgba(190, 57, 33, 0.95);
      background: rgba(190, 57, 33, 0.95);
      box-shadow: 0 10px 22px rgba(2,6,23,.06);
      margin: 0 !important;
    }

    .rt-widget-wrap .reloj{
      border: 1px solid rgba(225,6,0,.18);
      box-shadow: 0 16px 34px rgba(225,6,0,.10);
    }

    /* Register block */
    .rt-register{
      width: 100%;
      border-radius: 20px;
      border: 1px solid rgba(231,234,241,.95);
      background: rgba(255,255,255,.96);
      box-shadow: 0 10px 26px rgba(2,6,23,.08);
      padding: 14px;
      text-align:left;
    }
    .rt-register h2{
      margin: 0 0 10px 0;
      font-weight: 1000;
      letter-spacing:-.3px;
      font-size: 16px;
      display:flex;
      align-items:center;
      gap:10px;
    }
    .rt-register h2 .rt-badge{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      width: 34px;
      height: 34px;
      border-radius: 12px;
      background: linear-gradient(135deg, var(--brand), var(--brand2));
      color:#fff;
      box-shadow: 0 14px 28px rgba(225,6,0,.22);
      font-weight: 1000;
    }

    .rt-register .hint{
      margin: 0 0 10px 0;
      color: var(--muted);
      font-weight: 750;
      font-size: 12px;
    }

    /* Input premium */
    .rt-register .form-control{
      border-radius: 16px !important;
      border: 1px solid rgba(231,234,241,.95) !important;
      padding: 14px 14px !important;
      font-weight: 1000;
      font-size: 18px;
      letter-spacing: .4px;
      text-transform: uppercase;
      box-shadow: 0 10px 22px rgba(2,6,23,.06);
      transition: box-shadow .15s ease, border-color .15s ease, transform .12s ease;
    }
    .rt-register .form-control:focus{
      border-color: rgba(225,6,0,.55) !important;
      box-shadow: var(--ring), 0 18px 40px rgba(2,6,23,.10) !important;
      transform: translateY(-1px);
    }

    /* Side panel (tips / status) */
    .rt-kpis{
      display:grid;
      gap: 12px;
    }
    .rt-kpi{
      border-radius: 18px;
      border: 1px solid rgba(231,234,241,.95);
      background: rgba(255,255,255,.92);
      box-shadow: 0 10px 24px rgba(2,6,23,.06);
      padding: 12px 12px;
    }
    .rt-kpi .k-title{
      font-weight: 950;
      font-size: 12px;
      color: #334155;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
    }
    .rt-kpi .k-value{
      margin-top: 8px;
      font-weight: 1000;
      font-size: 14px;
      letter-spacing: -.2px;
      color: #0f172a;
    }
    .rt-kpi .k-chip{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding: 6px 10px;
      border-radius: 999px;
      border: 1px solid rgba(225,6,0,.20);
      background: rgba(225,6,0,.10);
      color: rgba(225,6,0,.95);
      font-weight: 1000;
      font-size: 12px;
      white-space: nowrap;
    }

    /* Responsive */
    @media (max-width: 992px){
      .rt-grid{ grid-template-columns: 1fr; }
      .rt-widget-wrap{ min-height: 340px; }
    }
    @media (max-width: 520px){
      .rt-bg{ padding: 12px; border-radius: 22px; }
      .rt-register .form-control{ font-size: 16px; padding: 12px 12px !important; }
      .rt-widget-wrap .fecha, .rt-widget-wrap .reloj{ padding: 8px 10px; }
    }
  </style>
</head>

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
        <div class="copyright">
          <p>POS WEB - Spider Software <br />© 2023 Todos los derechos reservados</p>
          <p class="op5">Made with <i class="fa fa-heart"></i> by SPIDER SOFTWARE</p>
        </div>
      </div>
    </div>

    <div class="content-body">
      <div class="container-fluid">
        <div class="rt-ui">

          <div class="page-titles">
            <ol class="breadcrumb">
              <li class="breadcrumb-item"><a href="javascript:void(0)">Home</a></li>
              <li class="breadcrumb-item active"><a href="javascript:void(0)">Record Time</a></li>
            </ol>
          </div>

          <div class="row">
            <div class="col-12">
              <div class="card" style="border-radius:24px; border:1px solid rgba(231,234,241,.95); box-shadow: 0 18px 60px rgba(2,6,23,.10); overflow:hidden;">
                <div class="card-body">
                  <form id="formcreate" autocomplete="off">
                    <input type="hidden" name="op" id="op" />
                    <input type="hidden" name="id" id="id" />

                    <div class="rt-bg">

                      <div class="rt-top">
                        <div>
                          <span class="rt-pill"><span class="dot"></span> Attendance</span>
                          <h4 class="rt-title">Record Time</h4>
                          <div class="rt-sub">Enter your ID to register entry/exit. The system records time automatically.</div>
                        </div>
                      </div>

                      <div class="rt-grid">

                        <!-- CLOCK PANEL -->
                        <div class="rt-panel">
                          <div class="rt-panel-head">
                            <b>Live Clock</b>
                            <span class="rt-pill" style="padding:7px 10px;">
                              <span class="dot" style="width:8px;height:8px;"></span>
                              Real-time
                            </span>
                          </div>

                          <div class="rt-panel-body">
                            <div class="rt-widget-wrap">

                              <!-- Keep your original widget markup (IDs needed by reloj.js) -->
                              <div class="widget align-center" style="width:100%; margin:0;">
                                <div class="fecha">
                                  <p id="diaSemana" class="diaSemana"></p>
                                  <p id="dia" class="dia"></p>
                                  <p> </p>
                                  <p id="mes" class="mes"></p>
                                  <p> </p>
                                  <p id="year" class="year"></p>
                                </div>

                                <div class="reloj">
                                  <p id="horas" class="horas"></p>
                                  <p>:</p>
                                  <p id="minutos" class="minutos"></p>
                                  <p>:</p>
                                  <div class="caja-segundos">
                                    <p id="segundos" class="segundos">12</p>
                                    <p id="ampm" class="ampm"></p>
                                  </div>
                                </div>

                                <div class="registerid rt-register">
                                  <h2><span class="rt-badge">ID</span> REGISTER YOUR ID</h2>
                                  <p class="hint">Tip: You can scan a barcode/QR or type the ID number.</p>

                                  <input
                                    type="text"
                                    autofocus="yes"
                                    class="form-control"
                                    name="cc"
                                    id="cc"
                                    placeholder="Enter your ID"
                                    onKeyPress="return soloNumeros(event);"
                                  >

                                  <input readonly="yes" class="campo2a" type="hidden" name="fecha" id="fecha" value="<?= $fecha_actual ?>">
                                  <input readonly="yes" class="coords" type="hidden" name="coords" id="coords" value="<?= $fecha_actual ?>">
                                </div>
                              </div>

                            </div>
                          </div>
                        </div>

                        <!-- SIDE PANEL -->
                        <div class="rt-panel">
                          <div class="rt-panel-head">
                            <b>Status</b>
                            <span class="rt-kpi k-chip">Secure</span>
                          </div>
                          <div class="rt-panel-body">
                            <div class="rt-kpis">
                              <div class="rt-kpi">
                                <div class="k-title">
                                  Server Timezone
                                  <span class="k-chip">America/Bogota</span>
                                </div>
                                <div class="k-value" id="tzLabel">America/Bogota</div>
                              </div>

                              <div class="rt-kpi">
                                <div class="k-title">
                                  Last Sync
                                  <span class="k-chip">Auto</span>
                                </div>
                                <div class="k-value" id="syncLabel"><?= htmlspecialchars($fecha_actual); ?></div>
                              </div>

                              <div class="rt-kpi">
                                <div class="k-title">
                                  Input Mode
                                  <span class="k-chip">Fast</span>
                                </div>
                                <div class="k-value">Auto validate after typing/paste</div>
                              </div>

                              <div class="rt-kpi">
                                <div class="k-title">
                                  Instructions
                                  <span class="k-chip">1–2 sec</span>
                                </div>
                                <div class="k-value" style="color:#334155;font-weight:800;">
                                  Enter your ID and wait for confirmation. Do not refresh.
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>

                      </div><!-- /grid -->
                    </div><!-- /rt-bg -->

                  </form>
                </div>
              </div>
            </div>
          </div>

        </div><!-- /rt-ui -->
      </div>
    </div>
  </div>

  <?php include './admin/include/gerenic_footer.php'; ?>
  <?php include './admin/include/gerenic_script.php'; ?>
  <script type="text/javascript" src="./admin/js/reloj.js"></script>
  <script type="text/javascript" src="./admin/js/reloj_entrada_salida.js"></script>

  <script>
    $.fn.delayPasteKeyUp = function(fn, ms) {
      var timer = 0;
      $(this).on("propertychange input", function() {
        clearTimeout(timer);
        timer = setTimeout(fn, ms);
      });
    };

    $(document).ready(function() {
      $("#coords").val("");
      $("#cc").delayPasteKeyUp(function() {
        RELOJENTRADASALIDA.validate();
      }, 200);

      // UI-only labels
      $("#tzLabel").text("America/Bogota");
    });
  </script>
</body>
</html>