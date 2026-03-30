<?php
require './admin/include/generic_classes.php';
include './admin/classes/Unidades.php';

// Permissions
$view   = SessionData::getPermission(12);
$create = SessionData::getPermission(13);
$edit   = SessionData::getPermission(15);
$delete = SessionData::getPermission(14);
$enable = SessionData::getPermission(16);

// Validation
if (!$view) { require 'permiso_denegado.php'; }

$arr = Unidades::getAll(null);
$isvalid = $arr['output']['valid'];
$arr = $arr['output']['response'];

// ✅ UI text in English
$modulo = 'HOA Association';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include './admin/include/generic_head.php'; ?>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>

  <style>
    /* ==========================================================
       PGS CENTRUM – HOA Association (SaaS Premium Red/Black)
       UI ONLY (no back / no ids / no logic)
    ========================================================== */
    .pgs-ui{
      --brand:#E10600;      /* red */
      --brand2:#B30500;     /* dark red */
      --ink:#0B0F19;        /* near black */
      --muted:#64748b;
      --bg:#F6F7FB;
      --card:#ffffff;
      --card2:#fbfcfe;
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
    .pgs-ui a{ color:inherit; text-decoration:none; }

    /* Page spacing */
    .pgs-ui .page-wrap{ padding: 6px 0 18px 0; }

    /* Breadcrumb styling (keep existing markup) */
    .pgs-ui .page-titles{
      margin-bottom: 12px;
    }
    .pgs-ui .breadcrumb{
      background: transparent !important;
      padding: 0 !important;
      margin: 0 !important;
    }
    .pgs-ui .breadcrumb .breadcrumb-item a{
      font-weight: 800;
      color: #475569;
    }
    .pgs-ui .breadcrumb .breadcrumb-item.active a{
      color: var(--ink);
    }

    /* Hero header card */
    .pgs-ui .hero{
      border-radius: var(--r24);
      background:
        radial-gradient(900px 260px at 12% 0%, rgba(225,6,0,.22), transparent 60%),
        radial-gradient(900px 260px at 92% 10%, rgba(11,15,25,.10), transparent 60%),
        linear-gradient(135deg, #ffffff 0%, #fbfbfd 55%, #f7f8fc 100%);
      border: 1px solid var(--border);
      box-shadow: var(--shadow);
      padding: 16px;
      overflow: hidden;
      margin-bottom: 12px;
      position: relative;
    }
    .pgs-ui .hero-top{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
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
      margin: 0;
      font-weight: 1000;
      letter-spacing: -.3px;
      font-size: 20px;
      line-height: 1.1;
    }
    .pgs-ui .h-sub{
      margin-top: 4px;
      color: var(--muted);
      font-weight: 700;
      font-size: 12px;
    }

    /* Buttons */
    .pgs-ui .btn-saas{
      border: 1px solid var(--border) !important;
      background: rgba(255,255,255,.92) !important;
      color: var(--ink) !important;
      padding: 10px 14px !important;
      border-radius: var(--r12) !important;
      font-weight: 900 !important;
      transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease, filter .15s ease;
      box-shadow: 0 8px 18px rgba(2,6,23,.06);
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
    .pgs-ui .btn-outline-danger.btn-sm,
    .pgs-ui .btn-outline-primary.btn-sm{
      border-radius: 12px !important;
      font-weight: 900 !important;
    }

    /* Card (table container) */
    .pgs-ui .card.pgs-card{
      border-radius: var(--r24) !important;
      border: 1px solid var(--border) !important;
      box-shadow: var(--shadow) !important;
      overflow: hidden;
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

    /* Table premium wrapper */
    .pgs-ui .table-wrap{
      border: 1px solid var(--border2);
      border-radius: 18px;
      overflow: auto;
      background: #fff;
    }
    .pgs-ui table.dataTable,
    .pgs-ui #dynamictable{
      width: 100% !important;
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
      font-weight: 700;
      color: #0f172a;
      font-size: 13px;
      white-space: nowrap;
    }
    .pgs-ui #dynamictable tbody tr:hover{
      background: #fbfbfd !important;
    }
    .pgs-ui .text-primary{
      color: #0f172a !important; /* keep clean (not bootstrap blue) */
    }

    /* Enable badge */
    .pgs-ui .badge-pillx{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding: 6px 10px;
      border-radius: 999px;
      font-weight: 950;
      font-size: 12px;
      border: 1px solid var(--border);
      background: #fff;
    }
    .pgs-ui .badge-pillx.yes{
      border-color: rgba(22,163,74,.25);
      background: rgba(22,163,74,.10);
      color: rgba(22,163,74,.95);
    }
    .pgs-ui .badge-pillx.no{
      border-color: rgba(225,6,0,.25);
      background: rgba(225,6,0,.08);
      color: rgba(225,6,0,.95);
    }

    /* Modal premium */
    .pgs-ui .modal-content{
      border-radius: 22px !important;
      border: 1px solid var(--border) !important;
      box-shadow: var(--shadow) !important;
      overflow: hidden;
    }
    .pgs-ui .modal-dialog.modal-xl{ max-width: 1100px; }
    .pgs-ui .modal-header,
    .pgs-ui .card-header.card-header-spider{
      background:
        radial-gradient(800px 220px at 12% 0%, rgba(225,6,0,.26), transparent 60%),
        linear-gradient(135deg, #111827 0%, #0b0f19 55%, #0b0f19 100%) !important;
      color: #fff !important;
      border-bottom: 1px solid rgba(255,255,255,.10) !important;
      padding: 16px 18px !important;
    }
    .pgs-ui .modal-title{ font-weight: 1000 !important; letter-spacing: -.2px; }
    .pgs-ui .close{ opacity: 1 !important; color:#fff !important; text-shadow:none !important; }
    .pgs-ui .close:hover{ filter: brightness(1.08); }

    /* Inputs */
    .pgs-ui .form-group label{
      font-weight: 900;
      color:#0f172a;
    }
    .pgs-ui .form-control{
      border-radius: 14px !important;
      border: 1px solid var(--border) !important;
      padding: 10px 12px !important;
      font-weight: 750;
      background: #fff !important;
      color: #0f172a !important;
      transition: box-shadow .15s ease, border-color .15s ease;
    }
    .pgs-ui .form-control:focus{
      border-color: rgba(225,6,0,.45) !important;
      box-shadow: var(--ring) !important;
    }
    .pgs-ui .errLbl{ color: var(--brand); font-weight: 1000; }

    .pgs-ui .modal-footer{
      border-top: 1px solid var(--border) !important;
      background: linear-gradient(180deg, #fff, #fafbff) !important;
      padding: 12px 16px !important;
      gap: 10px;
    }

    /* Mobile improvements */
    @media (max-width: 768px){
      .pgs-ui .hero{ padding: 14px; }
      .pgs-ui .h-title{ font-size: 18px; }
      .pgs-ui #dynamictable tbody td{ white-space: normal; }
      .pgs-ui .table-wrap{ border-radius: 16px; }
      .pgs-ui .btn-saas{ width: 100%; justify-content:center; }
    }

    /* DataTables tweaks (if loaded) */
    .pgs-ui .dataTables_wrapper .dataTables_filter input,
    .pgs-ui .dataTables_wrapper .dataTables_length select{
      border-radius: 12px !important;
      border: 1px solid var(--border) !important;
      padding: 8px 10px !important;
      font-weight: 800;
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
                <li class="breadcrumb-item active"><a href="javascript:void(0)"><?php echo $modulo; ?></a></li>
              </ol>
            </div>

            <!-- HERO -->
            <div class="hero">
              <div class="hero-top">
                <div>
                  <span class="pill"><span class="dot"></span> Directory</span>
                  <div style="margin-top:10px;">
                    <h3 class="h-title"><?php echo $modulo; ?></h3>
                    <div class="h-sub">Manage HOA records, contacts, and availability settings.</div>
                  </div>
                </div>

                <div class="d-flex mt-2 mt-sm-0" style="gap:10px; flex-wrap:wrap;">
                  <?php if ($create) { ?>
                    <button
                      class="btn btn-saas btn-brand"
                      data-target="#myModal"
                      data-toggle="modal"
                      data-backdrop="static"
                      data-keyboard="false">
                      + New HOA Association
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
                    <h4 class="card-title"><?php echo $modulo; ?></h4>
                  </div>

                  <div class="card-body">
                    <div class="table-wrap">
                      <table id="dynamictable" class="table table-hover table-responsive-sm mb-0">
                        <thead>
                          <tr>
                            <th style="width:90px;">ID</th>
                            <th>HOA Association</th>
                            <th>Address</th>
                            <th>General Manager</th>
                            <th>Cell Phone</th>
                            <th>Email</th>
                            <th style="width:120px;">Enabled</th>
                            <th style="width:140px;">Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                          $c = count($arr);
                          if ($isvalid) {
                            for ($i = 0; $i < $c; $i++) {
                              $enabled = strtolower(trim((string)$arr[$i]['enable'])) === 'yes' ? 'yes' : 'no';
                          ?>
                              <tr>
                                <td class="text-primary"><b><?php echo $arr[$i]['id']; ?></b></td>
                                <td class="text-primary"><?php echo $arr[$i]['nombre']; ?></td>
                                <td class="text-primary"><?php echo $arr[$i]['ubicacion']; ?></td>
                                <td class="text-primary"><?php echo $arr[$i]['administrador']; ?></td>
                                <td class="text-primary"><?php echo $arr[$i]['celular']; ?></td>
                                <td class="text-primary"><?php echo $arr[$i]['email']; ?></td>
                                <td>
                                  <span class="badge-pillx <?php echo $enabled; ?>">
                                    <?php echo ($enabled === 'yes') ? 'Yes' : 'No'; ?>
                                  </span>
                                </td>
                                <td class="td-actions text-left" style="white-space:nowrap;">
                                  <?php if ($edit) { ?>
                                    <button
                                      type="button"
                                      rel="tooltip"
                                      class="btn btn-outline-primary btn-sm"
                                      onclick="UNIDADES.editdata(<?php echo $arr[$i]['id']; ?>);"
                                      title="Edit">
                                      <i class="fa fa-pencil"></i>
                                    </button>
                                  <?php } ?>
                                  <?php if ($delete) { ?>
                                    <button
                                      type="button"
                                      rel="tooltip"
                                      class="btn btn-outline-danger btn-sm"
                                      onclick="UNIDADES.deletedata(<?php echo $arr[$i]['id']; ?>);"
                                      title="Delete">
                                      <i class="fa fa-trash-o"></i>
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

  <!-- MODAL -->
  <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="lbcondiciones_rgpd" aria-hidden="true">
    <div class="modal-dialog modal-xl">
      <div class="modal-content pgs-ui">

        <!-- Modal Header (keeps your classes for compatibility) -->
        <div class="card-header card-header-spider">
          <h4 class="modal-title">HOA Association</h4>
          <button type="button" onclick="UTIL.clearForm('formcreate');" class="close" data-dismiss="modal">&times;</button>
        </div>

        <div class="modal-body">
          <form id="formcreate" autocomplete="off">
            <div class="container-fluid">
              <input type="hidden" name="op" id="op" />
              <input type="hidden" name="id" id="id" />

              <div class="row">
                <div class="col-md-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">HOA Association <b class="errLbl">*</b></label>
                    <input type="text" value="" maxlength="90" id="nombre" name="nombre" class="form-control" placeholder="HOA Association name">
                  </div>
                </div>

                <div class="col-md-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Address <b class="errLbl">*</b></label>
                    <input type="text" value="" maxlength="90" id="ubicacion" name="ubicacion" class="form-control" placeholder="Address">
                  </div>
                </div>

                <div class="col-md-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Cell Phone <b class="errLbl">*</b></label>
                    <input type="text" value="" maxlength="12" id="celular" name="celular" class="form-control" placeholder="Example: 4075555564">
                  </div>
                </div>

                <div class="col-md-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Emergency Call</label>
                    <input type="text" value="" maxlength="12" id="telefono_emergencia" name="telefono_emergencia" class="form-control" placeholder="Example: 4075555564">
                  </div>
                </div>

                <div class="col-md-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">General Manager <b class="errLbl">*</b></label>
                    <input type="text" value="" maxlength="90" id="administrador" name="administrador" class="form-control" placeholder="General manager full name">
                  </div>
                </div>

                <div class="col-md-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Email <b class="errLbl">*</b></label>
                    <input type="email" value="" maxlength="90" id="email" name="email" class="form-control" placeholder="manager@mail.com">
                  </div>
                </div>

                <div class="col-md-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Contact 1</label>
                    <input type="text" value="" maxlength="90" id="contact1" name="contact1" class="form-control" placeholder="Contact name">
                  </div>
                </div>

                <div class="col-md-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Email</label>
                    <input type="email" value="" maxlength="90" id="email1" name="email1" class="form-control" placeholder="contact@mail.com">
                  </div>
                </div>

                <div class="col-md-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Contact 2</label>
                    <input type="text" value="" maxlength="90" id="contact2" name="contact2" class="form-control" placeholder="Contact name">
                  </div>
                </div>

                <div class="col-md-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Email</label>
                    <input type="email" value="" maxlength="90" id="email2" name="email" class="form-control" placeholder="contact@mail.com">
                  </div>
                </div>

                <div class="col-md-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Contact 3</label>
                    <input type="text" value="" maxlength="90" id="contact3" name="contact3" class="form-control" placeholder="Contact name">
                  </div>
                </div>

                <div class="col-md-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Email</label>
                    <input type="email" value="" maxlength="90" id="email3" name="email" class="form-control" placeholder="contact@mail.com">
                  </div>
                </div>

                <div class="col-md-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Contact 4</label>
                    <input type="text" value="" maxlength="90" id="contact4" name="contact4" class="form-control" placeholder="Contact name">
                  </div>
                </div>

                <div class="col-md-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Email</label>
                    <input type="email" value="" maxlength="90" id="email4" name="email" class="form-control" placeholder="contact@mail.com">
                  </div>
                </div>

                <div class="form-group col-md-4">
                  <label for="enable" class="bmd-label-floating">Enabled</label>
                  <select class="form-control" id="enable" name="enable">
                    <option value="yes">Yes</option>
                    <option value="no">No</option>
                  </select>
                </div>

              </div>
            </div>
          </form>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-saas" onclick="UTIL.clearForm('formcreate');" data-dismiss="modal">Cancel</button>
          <button type="button" onclick="UNIDADES.validateData();" class="btn btn-saas btn-brand">Save and Send</button>
        </div>

      </div>
    </div>
  </div>

  <?php include './admin/include/gerenic_footer.php'; ?>
  <?php include './admin/include/gerenic_script.php'; ?>
  <?php include './admin/include/generic_dataTables.php'; ?>
  <?php include './admin/include/generic_search.php'; ?>

  <script type="text/javascript" src="./admin/js/unidades.js"></script>
</body>
</html>