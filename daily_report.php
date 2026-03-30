<?php
require './admin/include/generic_classes.php';
include './admin/classes/Usuario.php';
include './admin/classes/Unidades.php';
include './admin/classes/Lugar.php';
include './admin/classes/Oficios.php';
include './admin/classes/DailyReport.php';

//Permisos
$view = SessionData::getPermission(1);
$create = SessionData::getPermission(2);
$edit = SessionData::getPermission(3);
$delete = SessionData::getPermission(4);
$enable = SessionData::getPermission(5);
$permits = SessionData::getPermission(6);
//Validación
if (!$view) {
  require 'permiso_denegado.php';
}
// Opción de las Unidades
$arrUnidades = Unidades::getAll(null);
$isvalidUni = $arrUnidades['output']['valid'];
$arrUnidades = $arrUnidades['output']['response'];
$optionUnidades = '<option value="select">Select...</option>';
foreach ($arrUnidades as $val) {
  $optionUnidades .= "<option value='" . $val['id'] . "'>" . $val['nombre'] . "</option>";
}


$arr = Usuario::getAll(null);
$isvalid = $arr['output']['valid'];
$arr = $arr['output']['response'];
$optionarr = '<option value="select">Select...</option>';
foreach ($arr as $val) {
  $optionarr .= "<option value='" . $val['id'] . "'>" . $val['nombre'] . "</option>";
}

$arrLugar = Lugar::getAll(null);
$isvalid = $arrLugar['output']['valid'];
$arrLugar = $arrLugar['output']['response'];
$optionarrLugar = '<option value="select">Select...</option>';
foreach ($arrLugar as $val) {
  $optionarrLugar .= "<option value='" . $val['id'] . "'>" . $val['zona'] . "</option>";
}

$arrOficio = Oficios::getAll(null);
$isvalid = $arrOficio['output']['valid'];
$arrOficio = $arrOficio['output']['response'];
$optionarrOficio = '<option value="select">Select...</option>';
foreach ($arrOficio as $val) {
  $optionarrOficio .= "<option value='" . $val['id'] . "'>" . $val['oficio'] . "</option>";
}

$arrReport = DailyReport::getAll(null);
$isvalid = $arrReport['output']['valid'];
$arrReport = $arrReport['output']['response'];


$modulo = 'Daily Report';
?>
<!DOCTYPE html>
<html lang="es">

<head>
  <?php include './admin/include/generic_head.php'; ?>
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
            <li class="breadcrumb-item"><a href="javascript:void(0)">Home</a></li>
            <li class="breadcrumb-item active"><a href="javascript:void(0)"><?php echo $modulo ?></a></li>
          </ol>
        </div>

        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h4 class="card-title">Daily Report to managers</h4>
                <div class="d-flex mt-3 mt-sm-0">
                  <!--                                     <form name="form" action="usuarios.php" class="navbar-form" method="POST">
                                        <div class="input-group input-primary">
                                        <input type="text" id="search" name="search" class="form-control" placeholder="Buscar...">
                                          <div class="input-group-append">
                                            <button type="submit" class="input-group-text">
                                            <i class="fa fa-search"></i>
                                          </button>
                                        </div>
                                      </div>
                                    </form> -->
                  <?php if ($create) { ?>
                    <button class="btn btn-primary btn-rounded ml-3" data-target="#myModal" data-toggle="modal" data-backdrop="static" data-keyboard="false"> New Report</button>
                  <?php } ?>
                </div>
              </div>

              <div class="card-body">
                <div class="table-responsive">
                  <table id="dynamictable" class="table table-hover table-responsive-sm">
                    <thead>
                      <th>Before</th>
                      <th>After</th>
                      <th>Employee</th>
                      <th>HOA</th>
                      <th>Date</th>
                      <th>See Report</th>
                    </thead>
                    <tbody>
                      <?php
                      $c = count($arrReport);
                      if ($isvalid) {
                        for ($i = 0; $i < $c; $i++) {

                          $img = $arrReport[$i]["img"];
                          $img = $img !== null && $img !== "" && $img !== "no_image.png" ? "assets/img/admin/" . $img : 'assets/img/logo-spiderP.png';

                          $imga = $arrReport[$i]["imga"];
                          $imga = $imga !== null && $imga !== "" && $imga !== "no_image.png" ? "assets/img/admin/" . $imga : 'assets/img/logo-spiderP.png';

                      ?>
                          <tr id="prod<?php echo $arrReport[$i]['id'] ?>">
                            <td class="text-primary"><img width='60' height='60' src='<?php echo $img; ?>' alt='Imagen' /> </td>
                            <td class="text-primary"><img width='60' height='60' src='<?php echo $imga; ?>' alt='Imagen' /> </td>
                            <td class="text-primary"><?php echo $arrReport[$i]['employee']; ?></td>
                            <td class="text-primary"> <?php echo $arrReport[$i]['nombre']; ?></td>
                            <td class="text-primary"> <?php echo $arrReport[$i]['dtcreate']; ?></td>
                            <td><a href="daily_report_view.php?reporte=<?php echo $arrReport[$i]['id']; ?>" target="_blank" type="submit" title="Ver" rel="tooltip" class="btn btn-outline-primary btn-sm"><i class="fas fa-eye"></i></a>


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
      </div>
    </div>
  </div>

  <!-- The Modal -->
  <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="lbcondiciones_rgpd" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header card-header card-header-danger">
          <h4 class="modal-title">Daily Report</h4>
          <button type="button" onclick="UTIL.clearForm('formcreate');" class="close" data-dismiss="modal">&times;</button>
        </div>

        <div class="modal-body">
          <form id="formcreate" autocomplete="off">
            <input type="hidden" name="op" id="op" />
            <input type="hidden" name="id" id="id" />
            <div class="row">
              <div class="col-sm-12">
                <p class="validateTips"></p>
              </div>
              <div class="col-sm-6">
                <div class="form-group">
                  <label class="bmd-label-floating">HOA designated (Name)<b class="errLbl">*</b></label>
                  <select class="form-control" id="tbl_unidades_id" name="tbl_unidades_id">
                    <?php echo $optionUnidades; ?>
                  </select>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="form-group">
                  <label class="bmd-label-floating">Employee<b class="errLbl">*</b></label>
                  <select class="form-control" id="tbl_employees_id" name="tbl_employees_id">
                    <?php echo $optionarr; ?>
                  </select>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="form-group">
                  <label class="bmd-label-floating">Work Area<b class="errLbl">*</b></label>
                  <select class="form-control" id="tbl_lugar_id" name="tbl_lugar_id">
                    <?php echo $optionarrLugar; ?>
                  </select>
                </div>
              </div>
              <div class="col-sm-6">
                <div class="form-group">
                  <label class="bmd-label-floating">Work done</label>
                  <select class="form-control" id="tec_oficios_id" name="tec_oficios_id">
                    <?php echo $optionarrOficio; ?>
                  </select>

                </div>
              </div>



              <div class="col-sm-6">
                <div class="form-group">
                  <label class="col-sm-4 control-label" for="exampleInputName2">Before</label>
                  <div class="col-sm-8">
                    <div class="controls">
                      <iframe id='ifm' name='ifm' src="upload.php" width="200" height="60" scrolling="no" frameborder="0"></iframe>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-sm-6">
                <div class="form-group">
                  <label class="col-sm-4 control-label" for="exampleInputName2">After</label>
                  <div class="col-sm-8">
                    <div class="controls">
                      <iframe id='ifm2' name='ifm2' src="upload_after.php" width="200" height="60" scrolling="no" frameborder="0"></iframe>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </form>
        </div>

        <!-- Modal footer -->
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-dark btn-rounded" onclick="UTIL.clearForm('formcreate');" data-dismiss="modal">Cancel</button>
          <button type="button" onclick="DAILYREPORT.validateData();" class="btn btn-primary btn-rounded">Save</button>
        </div>

      </div>
    </div>
  </div>
  </div>


  </div>
  </div>

  </div>
  </div>
  </div>

  <?php include './admin/include/gerenic_footer.php'; ?>

  <?php include './admin/include/generic_search.php'; ?>
  </div>
  </div>

  <!-- Script -->
  <?php include './admin/include/gerenic_script.php'; ?>
  <?php include './admin/include/generic_dataTables.php'; ?>
  <!-- Script -->

  <script type="text/javascript" src="./admin/js/lib/data-md5.js"></script>
  <script type="text/javascript" src="./admin/js/daily_report.js"></script>


</body>

</html>