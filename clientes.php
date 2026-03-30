<?php
require './admin/include/generic_classes.php';
include './admin/classes/Cliente.php';
include './admin/classes/Unidades.php';

//Permisos
$view = SessionData::getPermission(12);
$create = SessionData::getPermission(13);
$edit = SessionData::getPermission(16);
$delete = SessionData::getPermission(14);
$enable = SessionData::getPermission(15);
//Validación
if (!$view) {
  require 'permiso_denegado.php';
}


// Opción de las Unidades
$arrUnidades = Unidades::getAll(null);
$isvalidDep = $arrUnidades['output']['valid'];
$arrUnidades = $arrUnidades['output']['response'];
$optionUnidades = '<option value="seleccione">Seleccione...</option>';
foreach ($arrUnidades as $val) {
  $optionUnidades .= "<option value='" . $val['nombre'] . "'>" . $val['administrador'] . "</option>";
}


// Buscador
if (isset($_POST['search']) && $_POST['search'] != "") {
  $rqs = array('search' => $_POST['search']);
  $arr = Cliente::search($rqs);
} else {
  // Información de Clientes
  $arr = Cliente::getAll(null);
}
$isvalid = $arr['output']['valid'];
$arr = $arr['output']['response'];
$modulo = 'Customers';
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
            <li class="breadcrumb-item active"><a href="javascript:void(0)">Customers</a></li>
          </ol>
        </div>

        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h4 class="card-title"><?php echo $modulo; ?></h4>
                <div class="d-flex mt-3 mt-sm-0">
<!--                   <form name="form" action="clientes.php" class="navbar-form" method="POST">
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
                    <button class="btn btn-primary btn-rounded ml-3" data-target="#myModal" data-toggle="modal" data-backdrop="static" data-keyboard="false"> New Customer</button>
                  <?php } ?>
                </div>
              </div>

              <div class="card-body">
                <div class="table-responsive">
                  <table id="dynamictable" class="table table-hover table-responsive-sm">
                    <thead>
                      <th>Id</th>
                      <th>HOA Association</th>
                      <th>Address</th>
                      <th>Cellphone</th>
                      <th>Date Creation</th>
                      <th>Habilitado</th>
                      <th>Action</th>
                    </thead>
                    <tbody>
                      <?php
                      $c = count($arr);
                      if ($isvalid) {
                        for ($i = 0; $i < $c; $i++) {
                      ?>
                          <tr>
                            <td class="text-primary"> <?php echo $arr[$i]['id']; ?></td>
                            <td class="text-primary"> <?php echo $arr[$i]['nombre']; ?></td>
                            <td class="text-primary"> <?php echo $arr[$i]['direccion']; ?></td>
                            <td class="text-primary"> <?php echo $arr[$i]['contacto']; ?></td>
                            <td class="text-primary"> <?php echo $arr[$i]['dtcreate']; ?></td>
                            <td><?php echo $arr[$i]['enable']; ?></td>
                            <td class="td-actions text-left">
                              <?php
                              if ($edit) {
                              ?>
                                <button type="button" rel="tooltip" class="btn btn-outline-primary btn-sm" onclick="CLIENTE.editdata(<?php echo $arr[$i]['id']; ?>);" data-original-title="" title="Editar Cliente">
                                  <i class="fa fa-pencil"></i>
                                </button>
                              <?php
                              }

                              if ($delete) {
                              ?>
                                <!-- <button type="button" rel="tooltip" class="btn btn-danger btn-round" onclick="CLIENTE.deletedata(<?php echo $arr[$i]['id']; ?>);" data-original-title="" title="Eliminar Cliente">
                                                      <i class="material-icons">delete_forever</i>
                                                    </button> -->
                              <?php
                              }

                              if ($enable) {
                              ?>
                                <button type="button" rel="tooltip" onclick="CLIENTE.enabledata(<?php echo $arr[$i]['id']; ?>, '<?php echo $arr[$i]['enable']; ?>');" class="btn btn-outline-warning btn-sm" data-original-title="" title="Habilitar/Inhabilitar">
                                  <i class="fa fa-unlock"></i>
                                  <div class="ripple-container"></div>
                                </button>
                              <?php
                              }
                              ?>
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
  <div class="modal" id="myModal">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header card-header card-header-danger">
          <h4 class="modal-title">Customer details</h4>
          <button type="button" onclick="UTIL.clearForm('formcreate');" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <form id="formcreate" autocomplete="off">
            <div class="container-fluid">
              <input type="hidden" name="op" id="op" />
              <input type="hidden" name="id" id="id" />
              <div class="row">
                <div class="col-sm-12-">
                  <p class="validateTips"></p>
                </div>
                               
                <div class="form-group col-md-4">
                  <label class="bmd-label-floating">HOA Association Name<b class="errLbl">*</b></label>
                  <input type="text" class="form-control" id="nombre" name="nombre">
                  <!-- <span class="bmd-help">Ingrese sus apellidos</span> -->
                </div>
                <div class="form-group col-md-4">
                  <label class="bmd-label-floating">Address<b class="errLbl">*</b></label>
                  <input type="text" class="form-control" id="direccion" name="direccion">
                </div>
                  <div class="form-group col-md-4">
                  <label class="bmd-label-floating">Manager</label>
                  <input type="text" class="form-control" id="contacto" name="contacto">
                </div>
               
                <div class="form-group col-md-4">
                  <label class="bmd-label-floating">Cellphone<b class="errLbl">*</b></label>
                  <input type="text" class="form-control" id="celular" name="celular" placeholder="" onKeyPress="return soloNumeros(event);">
                </div>
                
                
                <div class="form-group col-md-4">
                  <label for="enable" class="bmd-label-floating">Enabled</label>
                  <select class="form-control" id="enable" name="enable">
                    <option value="si">Yes</option>
                    <option value="no">No</option>
                  </select>
                </div>
              </div>
            </div>
          </form>
        </div>

        <!-- Modal footer -->
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-dark btn-rounded" onclick="UTIL.clearForm('formcreate');" data-dismiss="modal">Cancel</button>
          <button type="button" onclick="CLIENTE.validateData();" class="btn btn-primary btn-rounded">Save</button>
        </div>

      </div>

    </div>
  </div>

  <?php include './admin/include/gerenic_footer.php'; ?>
  <?php include './admin/include/gerenic_script.php'; ?>
  <?php include './admin/include/generic_search.php'; ?>
  <?php include './admin/include/generic_dataTables.php'; ?>
  <!-- Script -->
  <script type="text/javascript" src="./admin/js/cliente.js"></script>
  <script type="text/javascript" src="./admin/js/proveedor.js"></script>
</body>