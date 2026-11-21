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
if (!$view) { http_response_code(403); require 'permiso_denegado.php'; exit; }
$modulo = 'Activities report';

// Zona::getAll() eliminada: en esta pantalla no se usa $option.

// Units options
$arrRequerimiento = Unidades::getAll(null);
$isvalidUni = !empty($arrRequerimiento['output']['valid']);
$arrRequerimiento = is_array($arrRequerimiento['output']['response'] ?? null) ? $arrRequerimiento['output']['response'] : [];

$optionRequerimiento = '<option value="">Select a property</option>'; 

$userUnidades = SessionData::getUnidadesUser();
$userUnidades = is_array($userUnidades) ? array_map('intval', $userUnidades) : [];
$userType     = SessionData::getUserType();

if ($isvalidUni) {
  foreach ($arrRequerimiento as $val) {
    if ($userType == Util::SuperAdmin()) {
      $optionRequerimiento .= "<option value='" . (int)$val['id'] . "'>" . htmlspecialchars((string)($val['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') . "</option>";
      continue;
    }
    if (($userType == Util::Manager() || $userType == Util::Staff()) && in_array((int)$val['id'], $userUnidades, true)) {
      $optionRequerimiento .= "<option value='" . (int)$val['id'] . "'>" . htmlspecialchars((string)($val['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') . "</option>";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include './admin/include/generic_head.php'; ?>

  <!-- Mobile-first PGS report UI -->
  <style>
:root{--pgs-red:#dc243b;--pgs-red2:#ae172b;--pgs-ink:#111827;--pgs-muted:#536174;--pgs-line:#e4e8ee;--pgs-bg:#f3f5f9;--pgs-radius:20px}
.content-body{background:var(--pgs-bg);min-height:100vh}
.content-body .container-fluid{max-width:1180px;padding:12px 14px 100px}
.pgs-hero{background:#fff;border:1px solid var(--pgs-line);border-radius:var(--pgs-radius);padding:19px 20px;margin-bottom:14px;box-shadow:0 5px 22px rgba(10,25,47,.035)}
.pgs-hero-title{margin:0;font-size:clamp(1.3rem,3vw,1.7rem);font-weight:800;color:var(--pgs-ink);letter-spacing:-.035em}
.pgs-hero-sub{margin:6px 0 0;color:var(--pgs-muted);font-size:.9rem;line-height:1.5}
.pgs-chiprow{display:flex;flex-wrap:wrap;gap:7px;margin-top:12px}
.pgs-chip{border-radius:999px;background:#f6f7f9;border:1px solid #e9edf2;color:#384356;font-size:.75rem;font-weight:650;padding:6px 10px}
.pgs-chip i{color:var(--pgs-red);margin-right:4px}
.pgs-card.card{background:#fff;border:1px solid var(--pgs-line)!important;border-radius:var(--pgs-radius)!important;box-shadow:0 5px 22px rgba(10,25,47,.035);overflow:hidden;margin-bottom:14px}
.pgs-card .card-body{padding:20px!important}
.pgs-section-title{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;gap:10px}
.pgs-section-title h5{margin:0;color:var(--pgs-ink);font-size:1.03rem;font-weight:800}
.pgs-mini{border-radius:999px;color:#ac152b;background:#fff1f2;padding:6px 10px;font-size:.77rem;font-weight:750}
.pgs-form .form-group{margin-bottom:17px}
.pgs-form .form-group label{display:block;margin:0 0 7px;color:#252d3a;font-size:.87rem;font-weight:750}
.pgs-form .form-control{width:100%;height:auto;min-height:48px;border:1px solid #d5dce5!important;border-radius:13px!important;padding:11px 13px!important;background:#fff;color:#152033;font-size:16px!important;box-shadow:none!important;transition:border-color .18s,box-shadow .18s}
.pgs-form textarea.form-control{min-height:94px;resize:vertical;line-height:1.45}
.pgs-form .form-control:focus{border-color:#d82a40!important;box-shadow:0 0 0 3px rgba(220,36,59,.11)!important;outline:0}
.pgs-form .errLbl{color:var(--pgs-red)}
.pgs-help{margin:7px 0 0;color:#64748b;font-size:.78rem;line-height:1.45}
.pgs-radio-wrap{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-top:10px}
.pgs-radio-wrap .form-check{position:relative;display:flex;align-items:center;gap:10px;margin:0;padding:12px;border:1px solid #dfe4eb;border-radius:13px;background:#fff;min-height:51px;cursor:pointer}
.pgs-radio-wrap .form-check:has(input:checked){border-color:#df4458;background:#fff3f5}
.pgs-radio-wrap input{position:static!important;margin:0!important;accent-color:var(--pgs-red);width:18px;height:18px;flex:none}
.pgs-radio-wrap .form-check label{font-size:.83rem;margin:0!important;font-weight:700;cursor:pointer;line-height:1.25}
.pgs-preview-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px}
.pgs-preview-head .t{margin:0;color:var(--pgs-ink);font-weight:800;font-size:1rem}
.pgs-preview-head .s{font-size:.75rem;border-radius:999px;padding:6px 10px;background:#f1f4f8;color:#5f6b7a;font-weight:700}
.pgs-preview{display:flex;flex-direction:column;align-items:stretch}
.pgs-media-frame{display:flex;align-items:center;justify-content:center;overflow:hidden;border:1px dashed #cbd4df;border-radius:15px;background:#f9fafc;min-height:170px;max-height:310px;margin-bottom:12px}
.pgs-media-frame .defaultavatar{max-height:260px;max-width:100%;object-fit:contain;display:block}
.pgs-media-frame #video{width:100%;max-height:310px;object-fit:contain;background:#0b0f14;border-radius:12px}
.pgs-media-frame #video.none{display:none!important}
.pgs-camera-select{width:100%;margin-bottom:12px}
.pgs-camera-select select{min-height:44px;width:100%;border-radius:11px;border:1px solid var(--pgs-line);padding:8px 10px}
.pgs-btn{background:linear-gradient(120deg,var(--pgs-red),var(--pgs-red2))!important;color:#fff!important;font-weight:800!important;font-size:.96rem!important;border:0!important;border-radius:13px!important;padding:13px 17px!important;min-height:48px;width:100%;box-shadow:0 9px 20px rgba(180,23,45,.16)}
.pgs-btn:focus-visible{outline:3px solid #f4acba;outline-offset:3px}
.pgs-btn:disabled{opacity:.65;cursor:wait}
.pgs-save-wrap{width:100%}
.pgs-status{display:none;margin-top:10px;padding:10px 12px;border-radius:10px;font-size:.8rem;background:#eff6ff;color:#1e40af}
.pgs-status.is-visible{display:block}
.pgs-connection{display:none;align-items:center;gap:7px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;border-radius:12px;padding:10px 12px;font-size:.8rem;margin-bottom:12px}
.pgs-connection.is-visible{display:flex}
.pgs-save-hint{font-size:.78rem;color:#607086;text-align:center;margin:9px 0 0}
@media(min-width:992px){.pgs-preview-card{position:sticky;top:95px}}
@media(max-width:767px){.content-body .container-fluid{padding:10px 11px 95px}.pgs-card .card-body{padding:16px!important}.pgs-hero{padding:16px}.pgs-chiprow .pgs-chip:last-child{display:none}.pgs-radio-wrap{grid-template-columns:1fr 1fr}.pgs-save-wrap{position:sticky;bottom:calc(8px + env(safe-area-inset-bottom));background:rgba(255,255,255,.96);padding:9px;border:1px solid var(--pgs-line);border-radius:16px;box-shadow:0 10px 25px rgba(17,24,39,.12);z-index:10}}
@media(max-width:360px){.pgs-radio-wrap{grid-template-columns:1fr}}
@media(prefers-reduced-motion:reduce){*,*:before,*:after{transition:none!important;animation:none!important}}
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
              <span class="pgs-chip"><i class="fa fa-user"></i> <?php echo htmlspecialchars((string)SessionData::getUserFullName(), ENT_QUOTES, 'UTF-8'); ?></span>
              <span class="pgs-chip"><i class="fa fa-shield"></i> Role: <?php echo htmlspecialchars((string)SessionData::getUserType(), ENT_QUOTES, 'UTF-8'); ?></span>
              <span class="pgs-chip"><i class="fa fa-clock-o"></i> <?php echo date('Y-m-d H:i'); ?></span>
            </div>
          </div>
        </div>

        <div id="pgsConnection" class="pgs-connection" role="status"><i class="fa fa-wifi" aria-hidden="true"></i> You appear to be offline. Keep this form open until your connection returns.</div>
        <form id="frm_foto" autocomplete="on">
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
                        <label class="bmd-label-floating" for="tbl_unidad_id">Property <b class="errLbl">*</b></label>
                        <select class="form-control" id="tbl_unidad_id" name="tbl_unidad_id" required>
                          <?php echo $optionRequerimiento; ?>
                        </select>
                        <p class="pgs-help mt-2">Select the property/unit where the activity took place.</p>
                      </div>
                    </div>

                    <div class="col-md-12">
                      <div class="form-group">
                        <label class="bmd-label-floating" for="zone">Zone <b class="errLbl">*</b></label>
                        <input type="text" value="" style="text-transform: uppercase" id="zone" name="zone" maxlength="180" autocomplete="off" required
                          class="form-control" placeholder="e.g., LOBBY / ROOF / PARKING">
                      </div>
                    </div>

                    <div class="col-md-12">
                      <div class="form-group">
                        <label class="bmd-label-floating" for="actividades">Activities <b class="errLbl">*</b></label>
                        <textarea id="actividades" name="actividades" class="form-control" rows="3" maxlength="2500" required placeholder="Describe what was done, the location and the outcome"></textarea>
                      </div>
                    </div>

                    <div class="col-md-12">
                      <div class="form-group">
                        <label class="bmd-label-floating" for="observaciones">Observations</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="2" maxlength="2500" placeholder="Any additional notes (optional)"></textarea>
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
              <div class="card pgs-card pgs-preview-card">
                <div class="card-body">
                  <div class="pgs-preview">
                    <div class="pgs-preview-head">
                      <p class="t mb-0">Photo Evidence</p>
                      <span class="s"><i class="fa fa-camera"></i> Optional</span>
                    </div>

                    <div class="pgs-media-frame">
                      <img class="defaultavatar img-fluid" src="assets/images/no-image.png" alt="No photo selected" loading="lazy" decoding="async">
                      <video id="video" width="100%" autoplay playsinline muted class="video_container none"></video>
                    </div>

                    <div id="selectcamdevice" class="pgs-camera-select" style="display: none;">
                      <h3 style="font-weight:1000; color:var(--pgs-black); font-size:1rem; margin:0 0 8px 0;">Select Camera</h3>
                      <select name="listaDeDispositivos" id="listaDeDispositivos" class="form-control"></select>
                    </div>

                    <?php if ($create) { ?>
                      <div class="pgs-save-wrap">
                        <button class="btn pgs-btn" type="submit" id="btn_save">
                          Save Report
                        </button>
                        <canvas id="canvas" style="display: none;"></canvas>
                        <div id="pgsSaveStatus" class="pgs-status" role="status" aria-live="polite"></div>
                        <p class="pgs-save-hint">Please keep this screen open while sending the report.</p>
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

  <script src="./admin/js/pgs_photo_optimizer.js" defer></script>
  <script type="text/javascript" src="./admin/js/camara/camara.js"></script>
  <script type="text/javascript" src="./admin/js/camara/inserta.js"></script>
  <script type="text/javascript" src="./admin/js/report.js"></script>
<script>
(function(){
  var connection = document.getElementById('pgsConnection');
  var form = document.getElementById('frm_foto');
  var status = document.getElementById('pgsSaveStatus');
  function updateConnection(){connection.classList.toggle('is-visible', !navigator.onLine);}
  window.addEventListener('online', updateConnection);
  window.addEventListener('offline', updateConnection);
  updateConnection();
  // This indicator does not assume that legacy report.js saved successfully.
  form.addEventListener('submit', function(){
    if (!form.checkValidity()) return;
    status.textContent = 'Sending report… Keep this screen open until you receive confirmation.';
    status.classList.add('is-visible');
  });
})();
</script>
</body>

</html>