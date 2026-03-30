<?php
require './admin/include/generic_classes.php';
include './admin/classes/Zona.php';
include './admin/classes/Requerimiento.php';
include './admin/classes/Unidades.php';

// Permissions
$view   = SessionData::getPermission(7);
$create = SessionData::getPermission(8);
$edit   = SessionData::getPermission(9);
$delete = SessionData::getPermission(10);

// Validation
if (!$view) { require 'permiso_denegado.php'; }
$modulo = 'Activities report';

$arr = Zona::getAll(null);
$arr = $arr['output']['response'];
$option = '<option value="seleccione">Seleccione...</option>';
foreach ($arr as $val) {
  $option .= "<option value='" . $val['id'] . "'>" . $val['zona'] . "</option>";
}

// Units options
$arrRequerimiento = Unidades::getAll(null);
$isvalidUni = $arrRequerimiento['output']['valid'];
$arrRequerimiento = $arrRequerimiento['output']['response'];

$optionRequerimiento = '<option value="selecct">Select...</option>';

$userUnidad = SessionData::getUnidadUser();
$userType   = SessionData::getUserType();

if ($isvalidUni) {
  foreach ($arrRequerimiento as $val) {
    if ($userType == Util::SuperAdmin()) {
      $optionRequerimiento .= "<option value='" . htmlspecialchars($val['id']) . "'>" . htmlspecialchars($val['nombre']) . "</option>";
      continue;
    }
    if (($userType == Util::Manager() || $userType == Util::Staff()) && $userUnidad == $val['id']) {
      $optionRequerimiento .= "<option value='" . htmlspecialchars($val['id']) . "'>" . htmlspecialchars($val['nombre']) . "</option>";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include './admin/include/generic_head.php'; ?>

  <!-- ✅ Premium SaaS UI (RED + BLACK) | Only design -->
  <style>
    :root{
      --pgs-red:#E11D2E;
      --pgs-red-2:#B3121E;
      --pgs-black:#0B0F14;
      --pgs-ink:#0f172a;
      --pgs-muted:#6b7280;
      --pgs-border:rgba(2,6,23,.10);
      --pgs-card:rgba(255,255,255,.92);
      --pgs-shadow:0 18px 45px rgba(2,6,23,.14);
      --pgs-shadow-soft:0 12px 28px rgba(2,6,23,.10);
      --pgs-radius:18px;
      --pgs-radius-lg:22px;
    }

    .content-body .container-fluid{ padding-top: 14px; }
    @media (min-width: 992px){ .content-body .container-fluid{ padding-top: 18px; } }

    /* Page hero */
    .pgs-hero{
      position: relative;
      border-radius: calc(var(--pgs-radius) + 6px);
      padding: 18px 18px;
      overflow: hidden;
      border: 1px solid var(--pgs-border);
      background:
        radial-gradient(1200px 420px at 10% 0%, rgba(225,29,46,.18), transparent 60%),
        radial-gradient(900px 380px at 90% 10%, rgba(11,15,20,.16), transparent 55%),
        linear-gradient(180deg, rgba(255,255,255,.94), rgba(255,255,255,.82));
      box-shadow: var(--pgs-shadow-soft);
      margin-bottom: 16px;
    }
    .pgs-hero:before{
      content:"";
      position:absolute;
      inset:-2px;
      background:
        radial-gradient(420px 260px at 10% 20%, rgba(225,29,46,.18), transparent 70%),
        radial-gradient(360px 260px at 90% 0%, rgba(11,15,20,.12), transparent 70%);
      pointer-events:none;
    }
    .pgs-hero-inner{ position:relative; display:flex; flex-direction: column; gap: 10px; }
    .pgs-hero-title{
      font-weight: 1000;
      letter-spacing: -.02em;
      color: var(--pgs-black);
      margin:0;
      line-height: 1.1;
      font-size: 1.15rem;
    }
    .pgs-hero-sub{ margin:0; color: var(--pgs-muted); font-size: .92rem; font-weight: 600; }

    .pgs-chiprow{ display:flex; flex-wrap: wrap; gap: 8px; align-items:center; }
    .pgs-chip{
      display:inline-flex; align-items:center; gap: 8px;
      padding: 8px 10px;
      border-radius: 999px;
      border: 1px solid rgba(11,15,20,.10);
      background: rgba(255,255,255,.88);
      color: rgba(11,15,20,.86);
      font-weight: 900;
      font-size: .82rem;
      backdrop-filter: blur(10px);
    }
    .pgs-chip i{ color: var(--pgs-red); }

    /* Cards */
    .pgs-card.card{
      border: 1px solid var(--pgs-border) !important;
      border-radius: var(--pgs-radius-lg) !important;
      background: var(--pgs-card) !important;
      box-shadow: var(--pgs-shadow-soft);
      overflow: hidden;
    }
    .pgs-card .card-body{ padding: 16px !important; }
    @media (min-width: 992px){
      .pgs-card .card-body{ padding: 18px !important; }
    }

    /* Section headings */
    .pgs-section-title{
      display:flex;
      align-items:center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 12px;
    }
    .pgs-section-title h5{
      margin:0;
      font-weight: 1000;
      letter-spacing: -.01em;
      color: var(--pgs-black);
      font-size: .98rem;
    }
    .pgs-section-title .pgs-mini{
      display:inline-flex;
      align-items:center;
      gap: 8px;
      padding: 6px 10px;
      border-radius: 999px;
      background: rgba(225,29,46,.08);
      border: 1px solid rgba(225,29,46,.18);
      color: var(--pgs-red-2);
      font-size: .78rem;
      font-weight: 1000;
      white-space: nowrap;
    }

    /* Inputs */
    .pgs-form .form-group label{
      font-weight: 900;
      color: rgba(11,15,20,.88);
      margin-bottom: 6px;
    }
    .pgs-form .form-control{
      border-radius: 14px !important;
      border: 1px solid rgba(2,6,23,.12) !important;
      padding: 11px 12px !important;
      font-weight: 700;
      color: rgba(11,15,20,.90);
      background: rgba(255,255,255,.92);
      transition: box-shadow .18s ease, border-color .18s ease, transform .18s ease;
      min-height: 44px;
    }
    .pgs-form .form-control:focus{
      border-color: rgba(225,29,46,.38) !important;
      box-shadow: 0 0 0 .2rem rgba(225,29,46,.12) !important;
      transform: translateY(-1px);
    }
    .pgs-form .errLbl{ color: var(--pgs-red); font-weight: 1000; }

    /* Radio group */
    .pgs-radio-wrap{
      border-radius: 16px;
      border: 1px dashed rgba(2,6,23,.14);
      background: rgba(11,15,20,.02);
      padding: 12px 12px;
      margin-top: 6px;
    }
    .pgs-radio-wrap .form-check{
      display:flex;
      align-items:center;
      gap: 10px;
      padding: 10px 10px;
      border-radius: 14px;
      background: rgba(255,255,255,.82);
      border: 1px solid rgba(2,6,23,.08);
      margin-bottom: 10px;
    }
    .pgs-radio-wrap .form-check:last-child{ margin-bottom: 0; }
    .pgs-radio-wrap .form-check-input{ margin-top: 0; }
    .pgs-radio-wrap .form-check-label{
      font-weight: 900;
      color: rgba(11,15,20,.86);
      margin-bottom: 0;
    }

    /* Right panel: preview */
    .pgs-preview{
      display:flex;
      flex-direction: column;
      gap: 12px;
      align-items: center;
    }
    .pgs-preview-head{
      width: 100%;
      display:flex;
      align-items:center;
      justify-content: space-between;
      gap: 10px;
      padding: 10px 12px;
      border-radius: 16px;
      background: linear-gradient(180deg, rgba(11,15,20,1), rgba(11,15,20,.92));
      color: #fff;
      border: 1px solid rgba(11,15,20,.12);
      box-shadow: 0 16px 28px rgba(2,6,23,.18);
    }
    .pgs-preview-head .t{
      font-weight: 1000;
      letter-spacing: -.01em;
      font-size: .92rem;
      margin:0;
    }
    .pgs-preview-head .s{
      display:inline-flex;
      align-items:center;
      gap: 6px;
      padding: 6px 10px;
      border-radius: 999px;
      background: rgba(225,29,46,.16);
      border: 1px solid rgba(225,29,46,.28);
      font-weight: 1000;
      font-size: .78rem;
      white-space: nowrap;
    }

    .defaultavatar{
      border-radius: 18px;
      border: 1px solid rgba(2,6,23,.10);
      background: rgba(255,255,255,.9);
      box-shadow: var(--pgs-shadow-soft);
      padding: 10px;
      max-height: 340px;
      object-fit: contain;
    }
    #video{
      border-radius: 18px;
      border: 1px solid rgba(2,6,23,.10);
      box-shadow: var(--pgs-shadow-soft);
      background: #000;
      overflow: hidden;
    }

    /* Save button */
    .pgs-btn{
      border: 1px solid rgba(225,29,46,.26) !important;
      background: linear-gradient(180deg, var(--pgs-red), var(--pgs-red-2)) !important;
      color: #fff !important;
      border-radius: 999px !important;
      padding: 10px 14px !important;
      font-weight: 1000 !important;
      letter-spacing: .01em;
      box-shadow: 0 12px 22px rgba(225,29,46,.22);
      transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
      min-width: 140px;
    }
    .pgs-btn:hover{
      transform: translateY(-1px);
      box-shadow: 0 18px 32px rgba(225,29,46,.26);
      filter: brightness(1.02);
    }

    /* Small helper text */
    .pgs-help{
      margin: 0;
      color: rgba(11,15,20,.62);
      font-weight: 700;
      font-size: .82rem;
    }

    /* Mobile stacking optimization */
    @media (max-width: 991.98px){
      .pgs-preview-head{ position: sticky; top: 10px; z-index: 2; }
    }
  </style>
</head>

<?php date_default_timezone_set('America/Bogota'); ?>

<body class="">
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

        <!-- ✅ Page Hero -->
        <div class="pgs-hero">
          <div class="pgs-hero-inner">
            <div>
              <h1 class="pgs-hero-title">Activities Report</h1>
              <p class="pgs-hero-sub">Register activities and optionally attach a photo from your camera.</p>
            </div>
            <div class="pgs-chiprow">
              <span class="pgs-chip"><i class="fa fa-user"></i> <?php echo htmlspecialchars(SessionData::getUserFullName()); ?></span>
              <span class="pgs-chip"><i class="fa fa-shield"></i> Role: <?php echo htmlspecialchars(SessionData::getUserType()); ?></span>
              <span class="pgs-chip"><i class="fa fa-clock-o"></i> <?php echo date('Y-m-d H:i'); ?></span>
            </div>
          </div>
        </div>

        <form id="frm_foto">
          <input type="hidden" name="op" id="op" />
          <input type="hidden" name="id" id="id" />

          <div class="row">
            <!-- Left: form -->
            <div class="col-lg-7">
              <div class="card pgs-card">
                <div class="card-body pgs-form">
                  <div class="pgs-section-title">
                    <h5>Activity Details</h5>
                    <span class="pgs-mini"><i class="fa fa-edit"></i> Form</span>
                  </div>

                  <div class="row">
                    <div class="col-sm-12">
                      <div class="form-group">
                        <label class="bmd-label-floating">Property <b class="errLbl">*</b></label>
                        <select class="form-control" id="tbl_unidad_id" name="tbl_unidad_id">
                          <?php echo $optionRequerimiento; ?>
                        </select>
                        <p class="pgs-help mt-2">Select the property/unit where the activity took place.</p>
                      </div>
                    </div>

                    <div class="col-md-12">
                      <div class="form-group">
                        <label class="bmd-label-floating">Zone <b class="errLbl">*</b></label>
                        <input type="text" value="" style="text-transform: uppercase" id="zone" name="zone"
                          class="form-control" placeholder="e.g., LOBBY / ROOF / PARKING">
                      </div>
                    </div>

                    <div class="col-md-12">
                      <div class="form-group">
                        <label class="bmd-label-floating">Activities <b class="errLbl">*</b></label>
                        <input type="text" value="" style="text-transform: uppercase" id="actividades" name="actividades"
                          class="form-control" placeholder="Describe the activity performed">
                      </div>
                    </div>

                    <div class="col-md-12">
                      <div class="form-group">
                        <label class="bmd-label-floating">Observations</label>
                        <input type="text" class="form-control" id="observaciones" name="observaciones"
                          placeholder="Write any additional comments (optional)">
                      </div>
                    </div>

                    <div class="col-md-12">
                      <div class="form-group style-check">
                        <p id="estado"></p>

                        <div class="pgs-radio-wrap">
                          <div class="form-check radio_check">
                            <input class="form-check-input" type="radio" name="radio_select" id="radiosfoto" value="1" checked>
                            <label class="form-check-label" for="radiosfoto">No picture</label>
                          </div>

                          <div class="form-check radio_check">
                            <input class="form-check-input" type="radio" name="radio_select" id="radiotfoto" value="0">
                            <label class="form-check-label" for="radiotfoto">Take a picture</label>
                          </div>
                        </div>

                        <p class="pgs-help mt-2">If you select “Take a picture”, the camera will be enabled.</p>
                      </div>
                    </div>

                  </div>
                </div>
              </div>
            </div>

            <!-- Right: camera / preview -->
            <div class="col-lg-5">
              <div class="card pgs-card">
                <div class="card-body">
                  <div class="pgs-preview">
                    <div class="pgs-preview-head">
                      <p class="t mb-0">Photo Evidence</p>
                      <span class="s"><i class="fa fa-camera"></i> Optional</span>
                    </div>

                    <img class="defaultavatar img-fluid" src="assets/images/no-image.png" alt="No image">

                    <video id="video" width="100%" autoplay="autoplay" class="video_container none mb-3"></video>

                    <div id="selectcamdevice" style="display: none; width:100%;">
                      <h3 style="font-weight:1000; color:var(--pgs-black); font-size:1rem; margin:0 0 8px 0;">Select Camera</h3>
                      <select name="listaDeDispositivos" id="listaDeDispositivos" class="form-control"></select>
                    </div>

                    <?php if ($create) { ?>
                      <div class="w-100 text-center">
                        <button class="btn pgs-btn" type="submit" id="btn_save">
                          Save Report
                        </button>
                        <canvas id="canvas" style="display: none;"></canvas>
                        <p class="pgs-help mt-2 mb-0">Your information will be saved with the selected options.</p>
                      </div>
                    <?php } ?>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </form>

      </div>
    </div>
  </div>

  <!-- Scripts -->
  <?php include './admin/include/gerenic_script.php'; ?>

  <script type="text/javascript" src="./admin/js/camara/camara.js"></script>
  <script type="text/javascript" src="./admin/js/camara/inserta.js"></script>
  <script type="text/javascript" src="./admin/js/report.js"></script>
</body>

</html>