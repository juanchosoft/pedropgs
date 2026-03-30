<?php
require './admin/include/generic_classes.php';
include './admin/classes/Lugar.php';
include './admin/classes/Unidades.php';

//Permisos
$view = SessionData::getPermission(7);
$create = SessionData::getPermission(7);
$edit = SessionData::getPermission(7);
$delete = SessionData::getPermission(7);
$enable = SessionData::getPermission(7);
//Validación
if (!$view) {
  require 'permiso_denegado.php';
}

// Opción de las Unidades
$arrUnidades = Unidades::getAll(null);
$isvalidUni = $arrUnidades['output']['valid'];
$arrUnidades = $arrUnidades['output']['response'];
$optionUnidades = '<option value="selecct">Select...</option>';
foreach ($arrUnidades as $val) {
  $optionUnidades .= "<option value='" . $val['id'] . "'>" . $val['nombre'] . "</option>";
}

// Buscador
if (isset($_POST['search']) && $_POST['search'] != "") {
  $rqs = array('search' => $_POST['search']);
  $arr = Lugar::search($rqs);
} else {
  // Información de Requerimientoes
  $arr = Lugar::getAll(null);
}
$isvalid = $arr['output']['valid'];
$arr = $arr['output']['response'];
$modulo = 'Jobs Areas';
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
            <li class="breadcrumb-item active"><a href="javascript:void(0)"><?php echo $modulo; ?></a></li>
          </ol>
        </div>

        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h4 class="card-title"><?php echo $modulo; ?></h4>
                <div class="d-flex mt-3 mt-sm-0">
                  <?php if ($create) { ?>
                    <button class="btn btn-primary btn-rounded ml-3" data-target="#myModal" data-toggle="modal" data-backdrop="static" data-keyboard="false"> New Request</button>
                  <?php } ?>
                </div>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table id="dynamictable" class="table table-hover table-responsive-sm">
                    <thead>
                      <th>ID</th>
                      <th>Job</th>
                     <th>Date</th>
                      <th></th>
                    </thead>
                    <tbody>
                      <?php
                      $c = count($arr);
                      if ($isvalid) {
                        for ($i = 0; $i < $c; $i++) {
                      ?>
                          <tr>
                          <td class="text-primary"><?php echo $arr[$i]['id']; ?></td>
                            <td class="text-primary"><?php echo $arr[$i]['hoa']; ?></td>
                            <td class="text-primary"><?php echo $arr[$i]['zona']; ?></td>
                            <td class="td-actions text-left">
                              <?php if ($edit) { ?>
                                <button type="button" rel="tooltip" class="btn btn-outline-primary btn-sm" onclick="LUGAR.editdata(<?php echo $arr[$i]['id']; ?>);" data-original-title="" title="Edit">
                                  <i class="fa fa-pencil"></i>
                                </button>
                              <?php }
                              if ($delete) { ?>

                                <button type="button" rel="tooltip" class="btn btn-outline-danger btn-sm" onclick="LUGAR.deletedata(<?php echo $arr[$i]['id']; ?>);" data-original-title="" title="Delete">
                                  <i class="fa fa-trash-o"></i>
                                </button>
                              <?php } ?>
                            </td>
                          </tr>
                      <?php }
                      } ?>
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
    <div class="modal-dialog modal-xl">

      <div class="modal-content">

        <!-- Modal Header -->
        <div class="card-header card-header-spider">
          <h4 class="modal-title">Lugar</h4>
          <button type="button" onclick="UTIL.clearForm('formcreate');" class="close" data-dismiss="modal">&times;</button>
        </div>

        <!-- Modal Creación y Edición de Usuarios -->
        <div class="modal-body">
          <form id="formcreate" autocomplete="off">
            <div class="container-fluid">
              <input type="hidden" name="op" id="op" />
              <input type="hidden" name="id" id="id" />
              <div class="row">

              <div class="col-sm-6">
                <div class="form-group">
                  <label class="bmd-label-floating">HOA designated (Name)<b class="errLbl">*</b></label>
                  <select class="form-control" id="tbl_unidades_id" name="tbl_unidades_id">
                    <?php echo $optionUnidades; ?>
                  </select>
                </div>
              </div>

                
                </div>
                <div class="col-sm-6">
                <div class="form-group">
                  <label class="bmd-label-floating">Zone<b class="errLbl">*</b></label>
                  <input type="text" class="form-control" id="zona" name="zona" placeholder="" value="">
                
                </div>
              </div>


              </div>
      
          </form>
          <!-- Modal footer -->
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-dark btn-rounded" onclick="UTIL.clearForm('formcreate');" data-dismiss="modal">Cancel</button>
          <button type="button" onclick="LUGAR.validateData();" class="btn btn-primary btn-rounded">Save and send</button>
        </div>

      </div>
      </div>
      </div>
      </div>
      </div>



  <?php include './admin/include/gerenic_footer.php'; ?>

  <!-- Script -->
  <?php include './admin/include/gerenic_script.php'; ?>
  <?php include './admin/include/generic_dataTables.php'; ?>
  <?php include './admin/include/generic_search.php'; ?>
  <!-- Script -->
  <script type="text/javascript" src="./admin/js/zones.js"></script>
</body>

</html>