<?php
require './admin/include/generic_classes.php';
include './admin/classes/Configuracion.php';

//Permisos
$view   = SessionData::getPermission(40);
$create = SessionData::getPermission(41);
$edit   = SessionData::getPermission(42);
$delete = SessionData::getPermission(43);
$enable = SessionData::getPermission(44);

$modulo = 'Configuración';

//Validación
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
       Premium Settings UI (RED/BLACK)
       UI ONLY - keep backend intact
       =========================== */
    .cfg-ui{
      --brand:#e11d48;
      --brand2:#fb7185;
      --black:#0b0f16;
      --muted:#6b7280;
      --border:#eceef4;
      --soft:#f7f8fc;
      --shadow: 0 18px 60px rgba(2,6,23,.12);
      --shadow2: 0 10px 26px rgba(2,6,23,.10);
      --r16:16px;
      --r20:20px;
      --r24:24px;
      color: var(--black) !important;
    }
    .cfg-ui *{ box-sizing:border-box; }

    .cfg-hero{
      border-radius: 26px;
      padding: 14px 14px;
      background:
        radial-gradient(900px 340px at 12% 0%, rgba(225,29,72,.22), transparent 60%),
        radial-gradient(900px 340px at 92% 10%, rgba(0,0,0,.18), transparent 60%),
        linear-gradient(135deg, #ffffff 0%, #fbfbfd 55%, #f7f8fc 100%);
      border: 1px solid var(--border);
      box-shadow: var(--shadow);
      overflow:hidden;
      margin-bottom: 12px;
    }
    .cfg-hero-top{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:12px;
      flex-wrap:wrap;
    }
    .cfg-title{
      margin:0;
      font-weight: 1000;
      letter-spacing:-.3px;
      font-size: 18px;
      line-height:1.2;
    }
    .cfg-sub{
      margin-top: 6px;
      color: var(--muted);
      font-weight: 750;
      font-size: 12px;
    }
    .cfg-chip{
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
    .cfg-dot{
      width:9px;height:9px;border-radius:999px;
      background: linear-gradient(135deg, var(--brand), var(--brand2));
      box-shadow: 0 0 0 4px rgba(225,29,72,.14);
    }

    /* Card */
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

    /* Two-column settings layout */
    .cfg-grid{
      display:grid;
      grid-template-columns: 1.25fr .75fr;
      gap: 14px;
      align-items:start;
    }
    @media (max-width: 992px){
      .cfg-grid{ grid-template-columns: 1fr; }
    }

    .cfg-section{
      border: 1px solid rgba(236,238,244,.95);
      border-radius: 18px;
      background:#fff;
      box-shadow: var(--shadow2);
      overflow:hidden;
    }
    .cfg-section-head{
      padding: 12px 14px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      background: linear-gradient(180deg,#ffffff,#f8fafc);
      border-bottom: 1px solid rgba(236,238,244,.95);
    }
    .cfg-section-head h5{
      margin:0;
      font-weight: 1000;
      letter-spacing:-.2px;
      font-size: 13px;
      color: var(--black);
    }
    .cfg-section-body{ padding: 14px; }

    .help-mini{
      color: var(--muted);
      font-weight: 750;
      font-size: 12px;
      line-height: 1.35;
    }

    /* Form fields */
    .form-group label{
      font-weight: 900;
      color: #111827;
      font-size: 12px;
      margin-bottom: 6px;
    }
    .form-control{
      border-radius: 14px !important;
      border: 1px solid rgba(236,238,244,.95) !important;
      padding: 11px 12px !important;
      font-weight: 750;
      color: var(--black);
      box-shadow:none !important;
      outline:none !important;
    }
    .form-control:focus{
      border-color: rgba(225,29,72,.45) !important;
      box-shadow: 0 0 0 .2rem rgba(225,29,72,.10) !important;
    }
    .errLbl{ color: var(--brand); }

    /* Upload block */
    .upload-box{
      border: 1px dashed rgba(225,29,72,.35);
      background: rgba(225,29,72,.06);
      border-radius: 18px;
      padding: 12px;
    }
    .upload-top{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      flex-wrap:wrap;
      margin-bottom: 10px;
    }
    .upload-title{
      display:flex;
      align-items:center;
      gap:10px;
      font-weight: 1000;
      color:#111827;
    }
    .upload-ico{
      width:36px;height:36px;border-radius:14px;
      display:grid;place-items:center;
      background: linear-gradient(135deg, var(--brand), #111827);
      color:#fff;
      box-shadow: 0 16px 26px rgba(225,29,72,.20);
    }
    .upload-frame{
      width: 240px;
      height: 62px;
      max-width: 100%;
      border:0;
      background:transparent;
    }

    /* Sticky footer actions */
    .cfg-actions{
      position: sticky;
      bottom: 10px;
      z-index: 20;
      margin-top: 14px;
      background: rgba(255,255,255,.88);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(236,238,244,.95);
      box-shadow: var(--shadow2);
      border-radius: 18px;
      padding: 10px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      flex-wrap:wrap;
    }
    .btn-premium{
      border: 0;
      border-radius: 14px;
      padding: 10px 14px;
      font-weight: 950;
      font-size: 12px;
      display:inline-flex;
      align-items:center;
      gap:8px;
      transition: transform .12s ease, filter .12s ease, box-shadow .12s ease;
      white-space: nowrap;
    }
    .btn-save{
      background: linear-gradient(135deg, var(--brand), #111827);
      color:#fff !important;
      box-shadow: 0 16px 30px rgba(225,29,72,.20);
    }
    .btn-save:hover{ transform: translateY(-1px); filter: brightness(1.03); }
    .btn-cancel{
      background:#fff;
      border:1px solid rgba(236,238,244,.95);
      color:#111827 !important;
      box-shadow: 0 10px 22px rgba(2,6,23,.06);
    }
    .btn-cancel:hover{ transform: translateY(-1px); }

    /* Modal header (keep your classes) */
    .modal .card-header.card-header-spider{
      background: linear-gradient(135deg, var(--brand), #111827) !important;
      color:#fff !important;
      border:0 !important;
    }

    @media (max-width: 576px){
      .cfg-hero{ border-radius: 22px; padding: 12px; }
      .cfg-actions{ bottom: 6px; }
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
        <div class="cfg-ui">

          <div class="page-titles">
            <ol class="breadcrumb">
              <li class="breadcrumb-item"><a href="javascript:void(0)">Home</a></li>
              <li class="breadcrumb-item active"><a href="javascript:void(0)">Configuration</a></li>
            </ol>
          </div>

          <div class="cfg-hero">
            <div class="cfg-hero-top">
              <div>
                <div class="cfg-chip"><span class="cfg-dot"></span> Settings</div>
                <h4 class="cfg-title">Configuration</h4>
                <div class="cfg-sub">
                  Manage your company details and branding. Changes apply system-wide.
                </div>
              </div>
              <div class="cfg-chip" title="Secure configuration">
                <span class="cfg-dot"></span> Red & Black theme
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-12">
              <div class="card card-premium">
                <div class="card-header">
                  <h4 class="card-title">Company configuration</h4>
                </div>

                <div class="card-body">
                  <!-- NOTE: Keep IDs/names exactly as your JS expects -->
                  <input type="hidden" name="op" id="op" />
                  <input type="hidden" name="id" id="id" />

                  <div class="cfg-grid">

                    <!-- LEFT: Main company data -->
                    <div class="cfg-section">
                      <div class="cfg-section-head">
                        <h5>Company details</h5>
                        <span class="cfg-chip" style="padding:6px 10px;"><span class="cfg-dot"></span> Required fields</span>
                      </div>
                      <div class="cfg-section-body">
                        <div class="row">
                          <div class="col-sm-6 col-lg-4">
                            <div class="form-group">
                              <label>Company name <b class="errLbl">*</b></label>
                              <input type="text" class="form-control" id="empresa" name="empresa" placeholder="Company name">
                            </div>
                          </div>

                          <div class="col-sm-6 col-lg-4">
                            <div class="form-group">
                              <label>Social Name <b class="errLbl">*</b></label>
                              <input type="text" class="form-control" id="razon_social" name="razon_social" placeholder="Legal name">
                            </div>
                          </div>

                          <div class="col-sm-6 col-lg-4">
                            <div class="form-group">
                              <label>ID Company <b class="errLbl">*</b></label>
                              <input type="text" class="form-control" id="nit" name="nit" placeholder="NIT / ID">
                            </div>
                          </div>

                          <div class="col-sm-6 col-lg-4">
                            <div class="form-group">
                              <label>Email Company <b class="errLbl">*</b></label>
                              <input type="email" class="form-control" id="email" name="email" placeholder="company@mail.com">
                            </div>
                          </div>

                          <div class="col-sm-6 col-lg-4">
                            <div class="form-group">
                              <label>Cellphone <b class="errLbl">*</b></label>
                              <input type="text" class="form-control" id="telefono" name="telefono" placeholder="Phone number">
                            </div>
                          </div>

                          <div class="col-sm-6 col-lg-4">
                            <div class="form-group">
                              <label>Address <b class="errLbl">*</b></label>
                              <input type="text" class="form-control" id="direccion" name="direccion" placeholder="Address">
                            </div>
                          </div>
                        </div>

                        <div class="help-mini">
                          Tip: keep the legal name and ID exactly as in your tax registration.
                        </div>
                      </div>
                    </div>

                    <!-- RIGHT: Branding / Logo -->
                    <div class="cfg-section">
                      <div class="cfg-section-head">
                        <h5>Branding</h5>
                        <span class="cfg-chip" style="padding:6px 10px;"><span class="cfg-dot"></span> Logo</span>
                      </div>
                      <div class="cfg-section-body">
                        <div class="upload-box">
                          <div class="upload-top">
                            <div class="upload-title">
                              <div class="upload-ico">
                                <i class="fa fa-cloud-upload"></i>
                              </div>
                              <div>
                                Upload image logo
                                <div class="help-mini">Recommended: PNG / transparent background</div>
                              </div>
                            </div>
                          </div>

                          <div class="controls">
                            <!-- keep upload.php exactly -->
                            <iframe
                              id="ifm"
                              name="ifm"
                              src="upload.php"
                              class="upload-frame"
                              scrolling="no"
                              frameborder="0"
                            ></iframe>
                          </div>

                          <div class="help-mini" style="margin-top:10px;">
                            After uploading, click <b>Save</b> to apply changes system-wide.
                          </div>
                        </div>
                      </div>
                    </div>

                  </div>

                  <!-- Sticky actions -->
                  <div class="cfg-actions">
                    <div class="help-mini">
                      <b>Note:</b> This updates the global company configuration.
                    </div>
                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                      <button type="button"
                        class="btn-premium btn-cancel"
                        onclick="UTIL.clearForm('formcreate');"
                        data-dismiss="modal">
                        <i class="fa fa-times"></i> Cancel
                      </button>

                      <button type="button"
                        class="btn-premium btn-save"
                        onclick="CONFIGURACION.savedata();">
                        <i class="fa fa-save"></i> Save
                      </button>
                    </div>
                  </div>

                </div> <!-- card-body -->
              </div>
            </div>
          </div>

          <!-- The Modal (kept intact, just styled via CSS above) -->
          <div class="modal" id="myModal">
            <div class="modal-dialog modal-lg">
              <div class="modal-content">

                <div class="card-header card-header-spider">
                  <h4 class="modal-title">Creación de resoluciones</h4>
                  <button type="button" onclick="UTIL.clearForm('formcreate');" class="close" data-dismiss="modal">&times;</button>
                </div>

                <div class="modal-body">
                  <form id="formcreate" autocomplete="off">
                    <input type="hidden" name="op" id="op" />
                    <input type="hidden" name="id" id="id" />

                    <div class="row justify-content-md-center">
                      <div class="col-sm-12-">
                        <p class="validateTips"></p>
                      </div>

                      <div class="col-sm-6">
                        <div class="form-group">
                          <label class="bmd-label-floating">No Resolucion <b class="errLbl">*</b></label>
                          <input type="text" class="form-control" id="resolucion" name="resolucion" onKeyPress="return soloNumeros(event);">
                        </div>
                      </div>

                      <div class="col-sm-6">
                        <div class="form-group">
                          <label>Fecha <b class="errLbl">*</b></label>
                          <input type="date" class="form-control" id="fecha" name="fecha">
                        </div>
                      </div>

                      <div class="col-sm-6">
                        <div class="form-group">
                          <label class="bmd-label-floating">Vigencia en Meses <b class="errLbl">*</b></label>
                          <input type="text" class="form-control" id="vigencia" name="vigencia">
                        </div>
                      </div>

                      <div class="col-sm-6">
                        <div class="form-group">
                          <label class="bmd-label-floating">Desde La Factura NO <b class="errLbl">*</b></label>
                          <input type="text" class="form-control" id="desde_fac" name="desde_fac">
                        </div>
                      </div>

                      <div class="col-sm-6">
                        <div class="form-group">
                          <label class="bmd-label-floating">Hasta La Factura NO <b class="errLbl">*</b></label>
                          <input type="text" class="form-control" id="hasta_fac" name="hasta_fac">
                        </div>
                      </div>

                      <div class="col-sm-6">
                        <div class="form-group">
                          <label class="bmd-label-floating">Caja No <b class="errLbl">*</b></label>
                          <input type="text" class="form-control" id="tec_cashier_id" name="tec_cashier_id" onKeyPress="return soloNumeros(event);" maxlength="2">
                        </div>
                      </div>

                      <div class="col-sm-6">
                        <div class="form-group">
                          <label for="enable" class="bmd-label-floating">Habilitado</label>
                          <select class="form-control" id="enable" name="enable">
                            <option value="si">Sí</option>
                            <option value="no">No</option>
                          </select>
                        </div>
                      </div>
                    </div>

                    <div class="modal-footer" style="border-top:1px solid rgba(236,238,244,.95);">
                      <button type="button" class="btn btn-outline-dark btn-rounded" onclick="UTIL.clearForm('formcreate');" data-dismiss="modal">Cancelar</button>
                      <button type="button" onclick="RESOLUCION.validateData();" class="btn btn-primary btn-rounded">Guardar</button>
                    </div>

                  </form>
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

  <script type="text/javascript" src="./admin/js/configuracion.js"></script>
  <script type="text/javascript" src="./admin/js/resolucion.js"></script>
  <script>
    CONFIGURACION.editdata();
  </script>
</body>
</html>