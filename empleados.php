<?php
require './admin/include/generic_classes.php';
include './admin/classes/Empleado.php';
include './admin/classes/Unidades.php';

// Permissions
$view   = SessionData::getPermission(27);
$create = SessionData::getPermission(28);
$edit   = SessionData::getPermission(29);
$delete = SessionData::getPermission(30);
$enable = SessionData::getPermission(31);

// Validation
if (!$view) { require 'permiso_denegado.php'; }

$arr = Empleado::getAll(NULL);
$isvalid = $arr['output']['valid'];
$arr = $arr['output']['response'];
$modulo = 'Employees';

// Units options
$arrUnidades = Unidades::getAll(null);
$isvalidUni  = $arrUnidades['output']['valid'];
$arrUnidades = $arrUnidades['output']['response'];

$optionUnidades = '<option value="selecct">Select...</option>';
foreach ($arrUnidades as $val) {
  $optionUnidades .= "<option value='" . $val['id'] . "'>" . $val['nombre'] . "</option>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include './admin/include/generic_head.php'; ?>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>

  <style>
    /* ==========================================================
       PGS CENTRUM – Employees (SaaS Premium Red/Black)
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
    .pgs-ui .breadcrumb .breadcrumb-item.active a{ color: var(--ink); }

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

    /* Card */
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

    /* Action buttons compact */
    .pgs-ui .btn-outline-primary.btn-sm,
    .pgs-ui .btn-outline-danger.btn-sm,
    .pgs-ui .btn-outline-warning.btn-sm,
    .pgs-ui .btn-outline-info.btn-sm{
      border-radius: 12px !important;
      font-weight: 900 !important;
    }

    /* Modal premium */
    .pgs-ui .modal-content{
      border-radius: 22px !important;
      border: 1px solid var(--border) !important;
      box-shadow: var(--shadow) !important;
      overflow:hidden;
    }
    .pgs-ui .modal-header.card-header.card-header-spider{
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

    /* Accordion premium */
    .pgs-ui .hojadevida .card,
    .pgs-ui .hojadevidaDocumentos .card{
      border-radius: 16px !important;
      border: 1px solid var(--border) !important;
      box-shadow: 0 10px 24px rgba(2,6,23,.06);
      overflow:hidden;
      margin-bottom: 10px !important;
    }
    .pgs-ui .hojadevida .card-header{
      background: linear-gradient(180deg, #ffffff, #f8fafc) !important;
      padding: 12px 14px !important;
      border-bottom: 1px solid var(--border) !important;
    }
    .pgs-ui .hojadevida .card-header a{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      font-weight: 1000;
      color: #0f172a;
      width:100%;
    }
    .pgs-ui .hojadevida .card-header a:hover{ color: var(--brand); }

    .pgs-ui .section-chip{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding: 8px 10px;
      border-radius: 999px;
      border: 1px solid var(--border);
      background: rgba(255,255,255,.95);
      font-weight: 950;
      box-shadow: 0 8px 18px rgba(2,6,23,.06);
    }
    .pgs-ui .section-chip .mini-dot{
      width:9px; height:9px; border-radius:999px;
      background: linear-gradient(135deg, var(--brand), var(--brand2));
    }

    .pgs-ui .modal-footer{
      border-top: 1px solid var(--border) !important;
      background: linear-gradient(180deg, #fff, #fafbff) !important;
      padding: 12px 16px !important;
      gap: 10px;
    }

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

    /* Keep your existing arrow logic but nicer spacing */
    h5.mb-0{ width:100%; margin:0; }
    .mb-0 > a{ display:block; position:relative; padding-right: 24px; }
    .mb-0 > a:after{
      content: "\f078";
      font-family: 'FontAwesome';
      position: absolute;
      right: 0;
      top: 2px;
      color: rgba(15,23,42,.65);
    }
    .mb-0 > a[aria-expanded="true"]:after{
      content: "\f077";
      color: rgba(225,6,0,.9);
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
                <li class="breadcrumb-item active"><a href="javascript:void(0)">Employees</a></li>
              </ol>
            </div>

            <!-- HERO -->
            <div class="hero">
              <div class="hero-top">
                <div>
                  <span class="pill"><span class="dot"></span> Human Resources</span>
                  <h3 class="h-title">Employees</h3>
                  <div class="h-sub">Create and manage employee profiles, uniforms, and required documents.</div>
                </div>

                <div class="d-flex mt-2 mt-sm-0" style="gap:10px; flex-wrap:wrap;">
                  <?php if ($create) { ?>
                    <button class="btn btn-saas btn-brand" data-target="#myModal" data-toggle="modal" data-backdrop="static" data-keyboard="false">
                      + New Employee
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
                    <h4 class="card-title">Employees</h4>
                  </div>

                  <div class="card-body">
                    <div class="table-wrap">
                      <table id="dynamictable" class="table table-hover table-responsive-sm mb-0">
                        <thead>
                          <tr>
                            <th style="width:90px;">Item</th>
                            <th style="width:160px;">ID</th>
                            <th>Name</th>
                            <th style="width:160px;">Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                          $c = count($arr);
                          if ($isvalid) {
                            for ($i = 0; $i < $c; $i++) {
                          ?>
                              <tr>
                                <td class="text-primary"><b><?php echo $arr[$i]['id']; ?></b></td>
                                <td class="text-primary"><?php echo $arr[$i]['cc']; ?></td>
                                <td class="text-primary"><?php echo $arr[$i]['nombre']; ?></td>
                                <td class="td-actions text-left" style="white-space:nowrap;">
                                  <?php if ($edit) { ?>
                                  <button type="button" rel="tooltip" class="btn btn-outline-primary btn-sm"
                                    onclick="EMPLEADO.editdata(<?php echo $arr[$i]['id']; ?>);" title="Edit Employee">
                                    <i class="fa fa-pencil"></i>
                                  </button>
                                  <?php } ?>
                                  <?php if ($delete) { ?>
                                  <button type="button" rel="tooltip" class="btn btn-outline-danger btn-sm"
                                    onclick="EMPLEADO.deletedata(<?php echo $arr[$i]['id']; ?>);" title="Delete Employee">
                                    <i class="fa fa-trash"></i>
                                  </button>
                                  <?php } ?>
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

          </div><!-- /page-wrap -->
        </div><!-- /pgs-ui -->
      </div>
    </div>
  </div>

  <!-- MODAL: Employee -->
  <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="lbcondiciones_rgpd" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content pgs-ui">

        <div class="modal-header card-header card-header-spider">
          <h4 class="modal-title">Personal Data</h4>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>

        <div class="modal-body">
          <form id="formcreate" autocomplete="off">
            <input type="hidden" name="op" id="op" />
            <input type="hidden" name="id" id="id" />

            <div class="bg-light mb-3" style="border-radius:18px; border:1px solid rgba(231,234,241,.9); padding: 12px 10px;">
              <div class="col-sm-12 pt-2">
                <div class="row">

                  <div class="col-sm-3">
                    <div class="form-group">
                      <label class="bmd-label-floating">Id <b class="errLbl">*</b></label>
                      <input type="text" class="form-control" id="cc" name="cc" onKeyPress="return soloNumeros(event);">
                    </div>
                  </div>

                  <div class="col-sm-3">
                    <div class="form-group">
                      <label class="bmd-label-floating">Name <b class="errLbl">*</b></label>
                      <input type="text" class="form-control" id="nombre" name="nombre">
                    </div>
                  </div>

                  <div class="col-sm-3">
                    <div class="form-group">
                      <label class="bmd-label-floating">Date Hired <b class="errLbl">*</b></label>
                      <input type="date" class="form-control" id="fecha_ingreso" name="fecha_ingreso">
                    </div>
                  </div>

                  <div class="col-sm-3">
                    <div class="form-group">
                      <label class="bmd-label-floating">HOA designated (Name) <b class="errLbl">*</b></label>
                      <select class="form-control" id="tbl_unidad_id" name="tbl_unidad_id">
                        <?php echo $optionUnidades; ?>
                      </select>
                    </div>
                  </div>

                  <div class="col-sm-3">
                    <div class="form-group">
                      <label class="bmd-label-floating">Rest Days</label>
                      <input type="number" class="form-control" id="dias_descanso" name="dias_descanso" min="0">
                    </div>
                  </div>

                </div>
              </div>
            </div>

            <div id="accordion" class="hojadevida">

              <!-- General Data -->
              <div class="card">
                <div class="card-header" id="heading-3">
                  <h5 class="mb-0">
                    <a class="collapsed" role="button" data-toggle="collapse" href="#collapse-3" aria-expanded="false" aria-controls="collapse-3">
                      <span class="section-chip"><span class="mini-dot"></span> General Data</span>
                    </a>
                  </h5>
                </div>
                <div id="collapse-3" class="collapse" data-parent="#accordion" aria-labelledby="heading-3">
                  <div class="card-body">
                    <div class="row">
                      <div class="col-sm-3">
                        <div class="form-group">
                          <label class="bmd-label-floating">Address</label>
                          <input type="text" class="form-control" id="direccion" name="direccion">
                        </div>
                      </div>

                      <div class="col-sm-3">
                        <div class="form-group">
                          <label class="bmd-label-floating">CellPhone*</label>
                          <input type="text" class="form-control" id="celular" name="celular" onKeyPress="return soloNumeros(event);">
                        </div>
                      </div>

                      <div class="col-sm-3">
                        <div class="form-group">
                          <span class="input-group-addon" style="font-weight:900;">Gender</span>
                          <select class="form-control input-lg" name="genero" id="genero">
                            <option value="seleccione">Select a option</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                          </select>
                        </div>
                      </div>

                      <div class="col-sm-3">
                        <div class="form-group">
                          <span class="input-group-addon" style="font-weight:900;">Email</span>
                          <input type="email" class="form-control" name="email" id="email">
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Uniforms -->
              <div class="card">
                <div class="card-header" id="heading-4">
                  <h5 class="mb-0">
                    <a class="collapsed" role="button" data-toggle="collapse" href="#collapse-4" aria-expanded="false" aria-controls="collapse-4">
                      <span class="section-chip"><span class="mini-dot"></span> Uniforms</span>
                    </a>
                  </h5>
                </div>
                <div id="collapse-4" class="collapse" data-parent="#accordion" aria-labelledby="heading-4">
                  <div class="card-body">
                    <div class="row">

                      <div class="col-sm-3">
                        <div class="form-group">
                          <label class="bmd-label-floating">Shirt</label>
                          <select class="form-control input-lg" name="camisa" id="camisa">
                            <option value="seleccione">Select a option</option>
                            <option value="XS">XS</option>
                            <option value="S">S</option>
                            <option value="M">M</option>
                            <option value="L">L</option>
                            <option value="XL">XL</option>
                            <option value="XXL">XXL</option>
                            <option value="XXXL">XXXL</option>
                          </select>
                        </div>
                      </div>

                      <div class="col-sm-2">
                        <div class="form-group">
                          <label class="bmd-label-floating">Pants</label>
                          <select class="form-control input-lg" name="pantalon" id="pantalon">
                            <option value="seleccione">Select a option</option>
                            <option value="28">28</option>
                            <option value="30">30</option>
                            <option value="32">32</option>
                            <option value="34">34</option>
                            <option value="36">36</option>
                            <option value="38">38</option>
                          </select>
                        </div>
                      </div>

                      <div class="col-sm-2">
                        <div class="form-group">
                          <label class="bmd-label-floating">Shoes</label>
                          <select class="form-control input-lg" name="calzado" id="calzado">
                            <option value="seleccione">Select a option</option>
                            <option value="34">34</option>
                            <option value="35">35</option>
                            <option value="36">36</option>
                            <option value="37">37</option>
                            <option value="38">38</option>
                            <option value="39">39</option>
                            <option value="40">40</option>
                            <option value="41">41</option>
                            <option value="42">42</option>
                            <option value="43">43</option>
                            <option value="44">44</option>
                          </select>
                        </div>
                      </div>

                      <div class="col-sm-2">
                        <div class="form-group">
                          <label class="bmd-label-floating">Date Uniforms</label>
                          <input type="date" class="form-control" name="entrega_uniforme" id="entrega_uniforme">
                        </div>
                      </div>

                    </div>
                  </div>
                </div>
              </div>

            </div><!-- /accordion -->
          </form>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-saas" onclick="UTIL.clearForm('formcreate');" data-dismiss="modal">Cancel</button>
          <button type="button" onclick="EMPLEADO.validateData();" class="btn btn-saas btn-brand">Save</button>
        </div>

      </div>
    </div>
  </div>

  <!-- MODAL: Documents -->
  <div class="modal fade" id="myModalDocumentos" tabindex="-1" role="dialog" aria-labelledby="lbcondiciones_rgpd" aria-hidden="true">
    <div class="modal-dialog modal-xl special modal-dialog-scrollable">
      <div class="modal-content pgs-ui">

        <div class="modal-header card-header card-header-spider">
          <h4 class="modal-title">Employee Documents: <span id="empleadonombre"></span></h4>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>

        <div class="modal-body">
          <form id="formcreate" role="form" autocomplete="false" enctype="multipart/form-data">
            <input type="hidden" name="tbl_documentos_empleado_id" id="tbl_documentos_empleado_id" />
            <input type="hidden" name="tbl_empleado_id" id="tbl_empleado_id" />

            <div id="accordion" class="hojadevidaDocumentos">
              <div class="row">
                <!-- Mantengo tu estructura tal cual (solo estética por CSS general) -->

                <div class="col-lg-4 col-sm-12 mb-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Photocopy of ID 150%</label>
                    <select class="form-control input-lg" name="fotocopia_cc" id="fotocopia_cc">
                      <option value="no">no</option>
                      <option value="si">si</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <input type="file" id="fotocopia_img" accept="application/pdf">
                  </div>
                </div>

                <div class="col-lg-4 col-sm-12 mb-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Resume / CV</label>
                    <select class="form-control input-lg" name="hoja_vida" id="hoja_vida">
                      <option value="no">no</option>
                      <option value="si">si</option>
                    </select>
                    <input name="hoja_vida_img" id="hoja_vida_img" type="file" />
                  </div>
                </div>

                <div class="col-lg-4 col-sm-12 mb-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">RUT</label>
                    <select class="form-control input-lg" name="rut" id="rut">
                      <option value="no">no</option>
                      <option value="si">si</option>
                    </select>
                  </div>
                  <input name="rut_img" id="rut_img" type="file" />
                </div>

                <div class="col-lg-4 col-sm-12 mb-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">High School Diploma</label>
                    <select class="form-control input-lg" name="diploma_bachiller" id="diploma_bachiller">
                      <option value="no">no</option>
                      <option value="si">si</option>
                    </select>
                  </div>
                  <input name="diploma_bachiller_img" id="diploma_bachiller_img" type="file" accept="application/pdf">
                </div>

                <div class="col-lg-4 col-sm-12 mb-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Graduation Certificate</label>
                    <select class="form-control input-lg" name="acta_grado_bachiller" id="acta_grado_bachiller">
                      <option value="no">no</option>
                      <option value="si">si</option>
                    </select>
                  </div>
                  <input name="acta_grado_bachiller_img" id="acta_grado_bachiller_img" type="file" accept="application/pdf">
                </div>

                <div class="col-lg-4 col-sm-12 mb-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Undergraduate Diploma</label>
                    <select class="form-control input-lg" name="pregado" id="pregado">
                      <option value="no">no</option>
                      <option value="si">si</option>
                    </select>
                  </div>
                  <input name="pregado_img" id="pregado_img" type="file" accept="application/pdf">
                </div>

                <div class="col-lg-4 col-sm-12 mb-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Undergraduate Certificate</label>
                    <select class="form-control input-lg" name="acta_pregado" id="acta_pregado">
                      <option value="no">no</option>
                      <option value="si">si</option>
                    </select>
                  </div>
                  <input name="acta_pregado_img" id="acta_pregado_img" type="file" accept="application/pdf">
                </div>

                <div class="col-lg-4 col-sm-12 mb-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Specialization Diploma</label>
                    <select class="form-control input-lg" name="diploma_especialista" id="diploma_especialista">
                      <option value="no">no</option>
                      <option value="si">si</option>
                    </select>
                  </div>
                  <input name="especialista_img" id="especialista_img" type="file" accept="application/pdf">
                </div>

                <div class="col-lg-4 col-sm-12 mb-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Degree Validation</label>
                    <select class="form-control input-lg" name="convalidacion_titulos" id="convalidacion_titulos">
                      <option value="no">no</option>
                      <option value="si">si</option>
                    </select>
                  </div>
                  <input name="convalidacion_titulos_img" id="convalidacion_titulos_img" type="file" accept="application/pdf">
                </div>

                <div class="col-lg-4 col-sm-12 mb-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Health Dept. Resolution</label>
                    <select class="form-control input-lg" name="resolucion_secretaria" id="resolucion_secretaria">
                      <option value="no">no</option>
                      <option value="si">si</option>
                    </select>
                  </div>
                  <input name="resolucion_secretaria_salud_img" id="resolucion_secretaria_salud_img" type="file" accept="application/pdf">
                </div>

                <div class="col-lg-4 col-sm-12 mb-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Professional License</label>
                    <select class="form-control input-lg" name="tarjeta_profesional" id="tarjeta_profesional">
                      <option value="no">no</option>
                      <option value="si">si</option>
                    </select>
                  </div>
                  <input name="tarjeta_profesional_img" id="tarjeta_profesional_img" type="file" accept="application/pdf">
                </div>

                <div class="col-lg-4 col-sm-12 mb-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Ethics Certificate</label>
                    <select class="form-control input-lg" name="certificado_etica" id="certificado_etica">
                      <option value="no">no</option>
                      <option value="si">si</option>
                    </select>
                  </div>
                  <input name="certificado_etica_img" id="certificado_etica_img" type="file" accept="application/pdf">
                </div>

                <div class="col-lg-4 col-sm-12 mb-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Military Card Copy</label>
                    <select class="form-control input-lg" name="libreta_militar" id="libreta_militar">
                      <option value="no">no</option>
                      <option value="si">si</option>
                    </select>
                  </div>
                  <input name="libreta_militar_img" id="libreta_militar_img" type="file" accept="application/pdf">
                </div>

                <div class="col-lg-4 col-sm-12 mb-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Vaccination Card</label>
                    <select class="form-control input-lg" name="vacunas" id="vacunas">
                      <option value="no">no</option>
                      <option value="si">si</option>
                    </select>
                  </div>
                  <input name="vacunas_img" id="vacunas_img" type="file" accept="application/pdf">
                </div>

                <div class="col-lg-4 col-sm-12 mb-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Prosecutor's Office</label>
                    <select class="form-control input-lg" name="procuraduria" id="procuraduria">
                      <option value="no">no</option>
                      <option value="si">si</option>
                    </select>
                  </div>
                  <input name="procuraduria_img" id="procuraduria_img" type="file" accept="application/pdf">
                </div>

                <div class="col-lg-4 col-sm-12 mb-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Comptroller</label>
                    <select class="form-control input-lg" name="contraloria" id="contraloria">
                      <option value="no">no</option>
                      <option value="si">si</option>
                    </select>
                  </div>
                  <input name="contraloria_img" id="contraloria_img" type="file" accept="application/pdf">
                </div>

                <div class="col-lg-4 col-sm-12 mb-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Police</label>
                    <select class="form-control input-lg" name="policia" id="policia">
                      <option value="no">no</option>
                      <option value="si">si</option>
                    </select>
                  </div>
                  <input name="policia_img" id="policia_img" type="file" accept="application/pdf">
                </div>

                <div class="col-lg-4 col-sm-12 mb-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Corrective Measures</label>
                    <select class="form-control input-lg" name="medidas_correctivas" id="medidas_correctivas">
                      <option value="no">no</option>
                      <option value="si">si</option>
                    </select>
                  </div>
                  <input name="medidas_correctivas_img" id="medidas_correctivas_img" type="file" accept="application/pdf">
                </div>

                <div class="col-lg-4 col-sm-12 mb-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Bank Certificate</label>
                    <select class="form-control input-lg" name="certificado_bancario" id="certificado_bancario">
                      <option value="no">no</option>
                      <option value="si">si</option>
                    </select>
                  </div>
                  <input name="certificado_bancario_img" id="certificado_bancario_img" type="file" accept="application/pdf">
                </div>

              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-saas" onclick="UTIL.clearForm('formcreate');" data-dismiss="modal">Cancel</button>
              <button type="button" class="btn btn-saas btn-brand" onclick="EMPLEADO.saveDocumentos();">Save</button>
            </div>
          </form>
        </div>

      </div>
    </div>
  </div>

  <?php include './admin/include/gerenic_footer.php'; ?>

  <?php include './admin/include/gerenic_script.php'; ?>
  <?php include './admin/include/generic_dataTables.php'; ?>

  <script type="text/javascript" src="./admin/js/empleado.js"></script>
</body>
</html>