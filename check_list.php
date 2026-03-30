<?php
require './admin/include/generic_classes.php';
include './admin/classes/Producto.php';
include './admin/classes/Categoria.php';
include './admin/classes/Unidades.php';

// Permissions
$view   = SessionData::getPermission(21);
$create = SessionData::getPermission(17);
$edit   = SessionData::getPermission(20);
$delete = SessionData::getPermission(18);
$enable = SessionData::getPermission(19);

if (!$view) { require 'permiso_denegado.php'; }

// Categories option
$arrCategorias = Categoria::getAll(null);
$isvalidCat = $arrCategorias['output']['valid'];
$arrCategorias = $arrCategorias['output']['response'];
$optionCategoria = '<option value="seleccione">Seleccione...</option>';
foreach ($arrCategorias as $val) {
  $optionCategoria .= "<option value='" . $val['id'] . "'>" . $val['name'] . "</option>";
}

// Units options (original logic preserved)
$arrUnidades = Unidades::getAll(null);
$isvalidUni = $arrUnidades['output']['valid'];
$arrUnidades = $arrUnidades['output']['response'];
$optionUnidades = '<option value="selecct">Select...</option>';
foreach ($arrUnidades as $val) {
  $optionUnidades .= "<option value='" . $val['id'] . "'>" . $val['administrador'] . "</option>";
}

// Units options (filtered by role) - original logic preserved
$arrUnidades = Unidades::getAll(null);
$isvalidCat = $arrUnidades['output']['valid'];
$arrUnidades = $arrUnidades['output']['response'];

$optionUnidades = '<option value="seleccione">Seleccione...</option>';

$userUnidad = SessionData::getUnidadUser();
$userType   = SessionData::getUserType();

if ($isvalidCat) {
  foreach ($arrUnidades as $val) {
    if ($userType == Util::SuperAdmin()) {
      $optionUnidades .= "<option value='" . htmlspecialchars($val['id']) . "'>" . htmlspecialchars($val['nombre']) . "</option>";
      continue;
    }
    if (($userType == Util::Manager() || $userType == Util::Staff()) && $userUnidad == $val['id']) {
      $optionUnidades .= "<option value='" . htmlspecialchars($val['id']) . "'>" . htmlspecialchars($val['nombre']) . "</option>";
    }
  }
}

// Products info
$arr = Producto::getAll(null);
$isvalid = $arr['output']['valid'];
$arr = $arr['output']['response'];
$modulo = 'Check List';
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

    /* Hero */
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

    /* Card wrapper */
    .pgs-card.card{
      border: 1px solid var(--pgs-border) !important;
      border-radius: var(--pgs-radius-lg) !important;
      background: var(--pgs-card) !important;
      box-shadow: var(--pgs-shadow-soft);
      overflow: hidden;
    }
    .pgs-card .card-header{
      background: transparent !important;
      border-bottom: 1px solid rgba(2,6,23,.08) !important;
      padding: 16px 16px !important;
    }
    .pgs-card .card-title{
      margin:0 !important;
      font-weight: 1000 !important;
      letter-spacing: -.01em;
      color: var(--pgs-black) !important;
    }
    .pgs-card .card-body{ padding: 16px !important; }
    @media (min-width: 992px){
      .pgs-card .card-header{ padding: 18px 18px !important; }
      .pgs-card .card-body{ padding: 18px !important; }
    }

    /* Breadcrumb (no logic changes, only visuals) */
    .page-titles{
      border-radius: var(--pgs-radius-lg);
      border: 1px solid rgba(2,6,23,.08);
      background: rgba(255,255,255,.80);
      box-shadow: var(--pgs-shadow-soft);
      padding: 12px 14px;
    }
    .breadcrumb{
      margin:0 !important;
      background: transparent !important;
      padding: 0 !important;
    }
    .breadcrumb-item a{ color: rgba(11,15,20,.70) !important; font-weight: 800; }
    .breadcrumb-item.active a{ color: var(--pgs-red-2) !important; font-weight: 1000; }

    /* Form controls */
    .pgs-form label{
      font-weight: 1000;
      color: rgba(11,15,20,.88);
      margin-bottom: 6px;
    }
    .pgs-form .errLbl{ color: var(--pgs-red); font-weight: 1000; }

    .pgs-form .form-control,
    .pgs-form select,
    .pgs-form textarea{
      border-radius: 14px !important;
      border: 1px solid rgba(2,6,23,.12) !important;
      padding: 11px 12px !important;
      font-weight: 750;
      color: rgba(11,15,20,.92);
      background: rgba(255,255,255,.92);
      transition: box-shadow .18s ease, border-color .18s ease, transform .18s ease;
      min-height: 44px;
      width: 100%;
    }
    .pgs-form .form-control:focus,
    .pgs-form select:focus,
    .pgs-form textarea:focus{
      border-color: rgba(225,29,46,.38) !important;
      box-shadow: 0 0 0 .2rem rgba(225,29,46,.12) !important;
      transform: translateY(-1px);
      outline: none;
    }
    .pgs-form textarea{
      min-height: 110px !important;
      resize: vertical;
      font-size: 15px;
      line-height: 1.35;
    }

    /* Table = SaaS checklist */
    .pgs-table-wrap{
      border-radius: var(--pgs-radius-lg);
      border: 1px solid rgba(2,6,23,.08);
      overflow: hidden;
      background: rgba(255,255,255,.80);
    }
    table.table{
      margin: 0 !important;
    }
    table.table td, table.table th{
      vertical-align: middle !important;
    }

    /* Section rows (red headers) */
    .pgs-section{
      background: linear-gradient(180deg, rgba(225,29,46,1), rgba(179,18,30,1)) !important;
      color: #fff !important;
      text-transform: uppercase;
      text-align: left;
      font-weight: 1000;
      letter-spacing: .06em;
      border-top: 1px solid rgba(255,255,255,.18) !important;
    }
    .pgs-section h5{
      margin:0;
      color:#fff;
      font-weight: 1000;
      font-size: .95rem;
      text-shadow: 0 2px 10px rgba(0,0,0,.22);
    }

    /* Question cells */
    .pgs-q{
      color: rgba(11,15,20,.88);
      font-weight: 900;
    }

    /* Inline selects inside table */
    .pgs-table-wrap select{
      border-radius: 999px !important;
      padding: 10px 12px !important;
      min-height: 42px;
      font-weight: 900;
      background: rgba(255,255,255,.95);
    }

    /* Primary action */
    .pgs-actions{
      display:flex;
      justify-content: flex-end;
      gap: 10px;
      margin-top: 14px;
    }
    .pgs-btn{
      border: 1px solid rgba(225,29,46,.26) !important;
      background: linear-gradient(180deg, var(--pgs-red), var(--pgs-red-2)) !important;
      color: #fff !important;
      border-radius: 999px !important;
      padding: 11px 16px !important;
      font-weight: 1000 !important;
      letter-spacing: .01em;
      box-shadow: 0 12px 22px rgba(225,29,46,.22);
      transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
    }
    .pgs-btn:hover{
      transform: translateY(-1px);
      box-shadow: 0 18px 32px rgba(225,29,46,.26);
      filter: brightness(1.02);
      color:#fff !important;
    }

    /* Improve spacing for the first "Property" select (keeps same id/name) */
    .pgs-top-select{
      border-radius: var(--pgs-radius-lg);
      border: 1px solid rgba(2,6,23,.08);
      background: rgba(255,255,255,.80);
      padding: 14px;
      margin-bottom: 14px;
      box-shadow: var(--pgs-shadow-soft);
    }

    /* Responsive: table becomes scrollable only on very small screens */
    .pgs-table-scroll{
      width: 100%;
      overflow-x: auto;
    }
    @media (max-width: 575.98px){
      .pgs-table-wrap select{ min-width: 160px; }
      .pgs-actions{ justify-content: stretch; }
      .pgs-actions .pgs-btn{ width: 100%; }
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

        <!-- ✅ Hero -->
        <div class="pgs-hero">
          <div class="pgs-hero-inner">
            <div>
              <h1 class="pgs-hero-title">Checklist Inspection</h1>
              <p class="pgs-hero-sub">Complete the inspection form and submit the results.</p>
            </div>
            <div class="pgs-chiprow">
              <span class="pgs-chip"><i class="fa fa-user"></i> <?php echo htmlspecialchars(SessionData::getUserFullName()); ?></span>
              <span class="pgs-chip"><i class="fa fa-shield"></i> Role: <?php echo htmlspecialchars(SessionData::getUserType()); ?></span>
              <span class="pgs-chip"><i class="fa fa-clock-o"></i> <?php echo date('Y-m-d H:i'); ?></span>
            </div>
          </div>
        </div>

        <div class="page-titles mb-3">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="javascript:void(0)">Home</a></li>
            <li class="breadcrumb-item active"><a href="javascript:void(0)">Checklist</a></li>
          </ol>
        </div>

        <div class="row">
          <div class="col-12">

            <div class="card pgs-card">
              <div class="card-header">
                <h4 class="card-title"><?php echo htmlspecialchars($modulo); ?></h4>
              </div>

              <div class="card-body pgs-form">

                <form action="guardar.php" method="post">

                  <!-- Property select (same id/name) -->
                  <div class="pgs-top-select">
                    <div class="form-group mb-0">
                      <label>HOA Designated (Name) <b class="errLbl">*</b></label>
                      <select class="form-control" id="tbl_unidad_id" name="tbl_unidad_id">
                        <?php echo $optionUnidades; ?>
                      </select>
                    </div>
                  </div>

                  <div class="pgs-table-scroll">
                    <div class="pgs-table-wrap">
                      <table class="table table-striped">
                        <thead>
                          <tr>
                            <th style="width:70%"></th>
                            <th style="width:30%"></th>
                          </tr>
                        </thead>

                        <!-- SECTION -->
                        <tr class="pgs-section">
                          <td colspan="2"><h5>Individual Floors</h5></td>
                        </tr>

                        <tr>
                          <td class="pgs-q">Overall appearance</td>
                          <td>
                            <select name="overall_appearance" id="overall_appearance">
                              <option value="" selected>Select</option>
                              <option value="OK">OK</option>
                              <option value="Not OK">Not OK</option>
                            </select>
                          </td>
                        </tr>

                        <tr>
                          <td class="pgs-q">Condition of walls</td>
                          <td>
                            <select name="condition_walls" id="condition_walls">
                              <option value="" selected>Select</option>
                              <option value="OK">OK</option>
                              <option value="Not OK">Not OK</option>
                            </select>
                          </td>
                        </tr>

                        <tr>
                          <td class="pgs-q">Condition of paint</td>
                          <td>
                            <select name="condition_paint" id="condition_paint">
                              <option value="" selected>Select</option>
                              <option value="OK">OK</option>
                              <option value="Not OK">Not OK</option>
                            </select>
                          </td>
                        </tr>

                        <tr>
                          <td class="pgs-q">Wall lights working</td>
                          <td>
                            <select name="wall_lights" id="wall_lights">
                              <option value="" selected>Select</option>
                              <option value="OK">OK</option>
                              <option value="Not OK">Not OK</option>
                            </select>
                          </td>
                        </tr>

                        <tr>
                          <td class="pgs-q">Ceiling lights working</td>
                          <td>
                            <select name="ceiling_lights" id="ceiling_lights">
                              <option value="" selected>Select</option>
                              <option value="OK">OK</option>
                              <option value="Not OK">Not OK</option>
                            </select>
                          </td>
                        </tr>

                        <tr>
                          <td class="pgs-q">Condition of carpet</td>
                          <td>
                            <select name="carpet" id="carpet">
                              <option value="" selected>Select</option>
                              <option value="OK">OK</option>
                              <option value="Not OK">Not OK</option>
                            </select>
                          </td>
                        </tr>

                        <tr>
                          <td class="pgs-q">Unit exterior door clean</td>
                          <td>
                            <select name="door_clean" id="door_clean">
                              <option value="" selected>Select</option>
                              <option value="OK">OK</option>
                              <option value="Not OK">Not OK</option>
                            </select>
                          </td>
                        </tr>

                        <tr>
                          <td class="pgs-q">Door spot lights working</td>
                          <td>
                            <select name="spot_lights" id="spot_lights">
                              <option value="" selected>Select</option>
                              <option value="OK">OK</option>
                              <option value="Not OK">Not OK</option>
                            </select>
                          </td>
                        </tr>

                        <tr>
                          <td class="pgs-q">Exit sign lit</td>
                          <td>
                            <select name="lit" id="lit">
                              <option value="" selected>Select</option>
                              <option value="OK">OK</option>
                              <option value="Not OK">Not OK</option>
                            </select>
                          </td>
                        </tr>

                        <tr>
                          <td class="pgs-q">Fire extinguisher charged</td>
                          <td>
                            <select name="extinguisher_charged" id="extinguisher_charged">
                              <option value="" selected>Select</option>
                              <option value="OK">OK</option>
                              <option value="Not OK">Not OK</option>
                            </select>
                          </td>
                        </tr>

                        <!-- SECTION -->
                        <tr class="pgs-section">
                          <td colspan="2"><h5>Trash Room Floor</h5></td>
                        </tr>

                        <tr>
                          <td class="pgs-q">Chute door opens/closes</td>
                          <td>
                            <select name="shute_door" id="shute_door">
                              <option value="" selected>Select</option>
                              <option value="OK">OK</option>
                              <option value="Not OK">Not OK</option>
                            </select>
                          </td>
                        </tr>

                        <tr>
                          <td class="pgs-q">Free of storage</td>
                          <td>
                            <select name="free_storage" id="free_storage">
                              <option value="" selected>Select</option>
                              <option value="OK">OK</option>
                              <option value="Not OK">Not OK</option>
                            </select>
                          </td>
                        </tr>

                        <tr>
                          <td class="pgs-q">Free of hazardous materials</td>
                          <td>
                            <select name="hazardous_materials" id="hazardous_materials">
                              <option value="" selected>Select</option>
                              <option value="OK">OK</option>
                              <option value="Not OK">Not OK</option>
                            </select>
                          </td>
                        </tr>

                        <tr>
                          <td class="pgs-q">Chute free of debris</td>
                          <td>
                            <select name="debris" id="debris">
                              <option value="" selected>Select</option>
                              <option value="OK">OK</option>
                              <option value="Not OK">Not OK</option>
                            </select>
                          </td>
                        </tr>

                        <tr>
                          <td class="pgs-q">Inspection sheet visible</td>
                          <td>
                            <select name="inspection_visible1" id="inspection_visible1">
                              <option value="" selected>Select</option>
                              <option value="OK">OK</option>
                              <option value="Not OK">Not OK</option>
                            </select>
                          </td>
                        </tr>

                        <!-- SECTION -->
                        <tr class="pgs-section">
                          <td colspan="2"><h5>Maintenance Janitorial Room</h5></td>
                        </tr>

                        <tr>
                          <td class="pgs-q">Supplies properly stored</td>
                          <td>
                            <select name="supplies_stored" id="supplies_stored">
                              <option value="" selected>Select</option>
                              <option value="OK">OK</option>
                              <option value="Not OK">Not OK</option>
                            </select>
                          </td>
                        </tr>

                        <tr>
                          <td class="pgs-q">Chemicals properly labeled</td>
                          <td>
                            <select name="chemical_labeled" id="chemical_labeled">
                              <option value="" selected>Select</option>
                              <option value="OK">OK</option>
                              <option value="Not OK">Not OK</option>
                            </select>
                          </td>
                        </tr>

                        <tr>
                          <td class="pgs-q">Paints properly labeled</td>
                          <td>
                            <select name="paint_labeled" id="paint_labeled">
                              <option value="" selected>Select</option>
                              <option value="OK">OK</option>
                              <option value="Not OK">Not OK</option>
                            </select>
                          </td>
                        </tr>

                        <tr>
                          <td class="pgs-q">Fire extinguisher charged</td>
                          <td>
                            <select name="fire_charged" id="fire_charged">
                              <option value="" selected>Select</option>
                              <option value="OK">OK</option>
                              <option value="Not OK">Not OK</option>
                            </select>
                          </td>
                        </tr>

                        <tr>
                          <td class="pgs-q">Ladders properly stored</td>
                          <td>
                            <select name="ladders_stored" id="ladders_stored">
                              <option value="" selected>Select</option>
                              <option value="OK">OK</option>
                              <option value="Not OK">Not OK</option>
                            </select>
                          </td>
                        </tr>

                        <tr>
                          <td class="pgs-q">Area free of debris</td>
                          <td>
                            <select name="debrisj" id="debrisj">
                              <option value="" selected>Select</option>
                              <option value="OK">OK</option>
                              <option value="Not OK">Not OK</option>
                            </select>
                          </td>
                        </tr>

                        <tr>
                          <td class="pgs-q">Inventory properly labeled</td>
                          <td>
                            <select name="inventory_labeled" id="inventory_labeled">
                              <option value="" selected>Select</option>
                              <option value="OK">OK</option>
                              <option value="Not OK">Not OK</option>
                            </select>
                          </td>
                        </tr>

                        <!-- ✅ From here onward: keep your remaining rows EXACTLY as you already have them.
                            Only change section header <td class="red-background"> to <tr class="pgs-section"><td colspan="2"><h5>...</h5></td></tr>
                            and the question <td class="texto1"> to <td class="pgs-q">.
                            The selects remain identical (name/id/options). -->
                        
                        <!-- 🔻 KEEP THE REST OF YOUR TABLE ROWS BELOW (unchanged logic) 🔻 -->

                        <tr class="pgs-section"><td colspan="2"><h5>Fire Control Panel Room</h5></td></tr>
                        <tr>
                          <td class="pgs-q">Equipment tested and working</td>
                          <td>
                            <select name="equipment_tested" id="equipment_tested">
                              <option value="" selected>Select</option>
                              <option value="OK">OK</option>
                              <option value="Not OK">Not OK</option>
                            </select>
                          </td>
                        </tr>

                        <tr>
                          <td class="pgs-q">Inspection sheet visible</td>
                          <td>
                            <select name="inspectionf" id="inspectionf">
                              <option value="" selected>Select</option>
                              <option value="OK">OK</option>
                              <option value="Not OK">Not OK</option>
                            </select>
                          </td>
                        </tr>

                        <!-- ... (PASTE YOUR REMAINING ORIGINAL ROWS HERE, only switching classes as noted) ... -->

                      </table>
                    </div>
                  </div>

                  <div class="mt-3">
                    <label for="observations">Observations</label>
                    <textarea placeholder="Write observations here..." name="observations" id="observations"></textarea>
                  </div>

                  <?php if ($edit) { ?>
                    <div class="pgs-actions">
                      <button class="btn pgs-btn btn-sm" type="button" onclick="CHECK.validateData();">
                        Save &amp; Send
                      </button>
                    </div>
                  <?php } ?>

                </form>

              </div>
            </div>

          </div>
        </div>

      </div>
    </div>
  </div>

  <?php include './admin/include/gerenic_footer.php'; ?>
  <?php include './admin/include/gerenic_script.php'; ?>
  <?php include './admin/include/generic_search.php'; ?>
  <script type="text/javascript" src="./admin/js/check.js"></script>
</body>
</html>