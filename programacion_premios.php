<?php
require './admin/include/generic_classes.php';
include './admin/classes/Premios.php';

//Permisos
$view = SessionData::getPermission(26);
$create = SessionData::getPermission(21);
$edit = SessionData::getPermission(24);
$delete = SessionData::getPermission(22);
$enable = SessionData::getPermission(23);
$modulo = 'Programación sorteos';

//Validación
if (!$view) {
  require 'permiso_denegado.php';
}
// Opción de los Categoria
$arr = Premios::getAll(null);
$isvalid = $arr['output']['valid'];
$arr = $arr['output']['response'];
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
                          <li class="breadcrumb-item"><a href="javascript:void(0)">Inicio</a></li>
                          <li class="breadcrumb-item active"><a href="javascript:void(0)">Sorteos y fidelización</a></li> 
                      </ol>
                  </div>

                  <div class="row">
                    <div class="col-12">
                      <div class="card">
                          <div class="card-header">
                              <h4 class="card-title">Premios - Configuracion de Sorteos</h4>
                              <div class="d-flex mt-3 mt-sm-0">
                                <?php if ($create) { ?>
                                    <button class="btn btn-primary btn-rounded ml-3" data-target="#myModal" data-toggle="modal"
                                        data-backdrop="static" data-keyboard="false">
                                        Crear sorteo
                                    </button>
                                <?php } ?>                                                                      
                              </div>
                          </div>
                          <div class="card-body">
                              <div class="table-responsive">
                                  <table id="dynamictable" class="table table-hover table-responsive-sm">
                                    <thead>
                                      <th>Codigo</th>
                                      <th>Premio Sorteo</th>
                                      <th>Producto Comprado</th>
                                      <th>Fecha sorteo</th>
                                      <th>Ganador</th>
                                      <th>Habilitado</th>
                                    </thead>
                                    <tbody>
                                      <?php
                                      $c = count($arr);
                                      if ($isvalid) {
                                        for ($i = 0; $i < $c; $i++) {
                                      ?>
                                          <tr>
                                            <td class="text-primary"><?php echo $arr[$i]['codigo_sorteo']; ?></td>
                                            <td class="text-primary"> <?php echo $arr[$i]['nombre_premio']; ?></td>
                                            <th class="text-primary"> <?php echo $arr[$i]['producto']; ?></th>
                                            <td class="text-primary"> <?php echo $arr[$i]['fecha_sorteo']; ?></td>
                                            <th class="text-primary"> <?php echo $arr[$i]['cliente']; ?></th>
                                            <td><?php echo $arr[$i]['enable']; ?></td>
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
          <!-- Modal Header -->
          <div class="modal-header card-header card-header-danger">
            <h4 class="modal-title">Creación de Sorteos</h4>
            <button type="button" onclick="UTIL.clearForm('formcreate');" class="close" data-dismiss="modal">&times;</button>
          </div>
          <!-- Modal Creación y Edición de Usuarios -->
          <div class="modal-body">
            <form id="formcreate" autocomplete="off">
              <div class="container-fluid">
                <input type="hidden" name="op" id="op" />
                <input type="hidden" name="id" id="id" />
                <div class="row">
                  <div class="col-sm-12-">
                    <p class="validateTips"></p>
                  </div>
                  <div class="col-sm-12">
                    <div class="form-horizontal">
                      <div class="form-group">
                        <label class="bmd-label-floating">Codigo Sorteo<b class="errLbl">*</b></label>
                        <input required type="text" class="form-control" id="codigo_sorteo" name="codigo_sorteo">
                      </div>
                      <div class="form-group">
                        <label class="bmd-label-floating">Seleccione El Producto<b class="errLbl">*</b></label>
                        <input required type="text" class="form-control" id="tec_product_id" name="tec_product_id">
                      </div>
                      <div class="form-group">
                        <label class="bmd-label-floating">Premio a Entregar<b class="errLbl">*</b></label>
                        <input required type="text"  class="form-control" id="nombre_premio" name="nombre_premio">
                      </div>
                      <div class="form-group">
                        <label class="bmd-label-floating">Fecha Sorteo<b class="errLbl">*</b></label>
                        <br>
                        <input required type="date" maxlength="6" class="form-control" id="fecha_sorteo" name="fecha_sorteo">
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </form>
          </div>

          <!-- Modal footer -->
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-dark btn-rounded" onclick="UTIL.clearForm('formcreate');" data-dismiss="modal">Cancelar</button>
            <button type="button" onclick="PREMIOS.validateData();" class="btn btn-primary btn-rounded">Guardar</button>
          </div>
        </div>
      </div>
    </div>

    <?php include './admin/include/gerenic_footer.php'; ?>
    <?php include './admin/include/gerenic_script.php'; ?>
    <?php include './admin/include/generic_search.php'; ?>
    <script type="text/javascript" src="./admin/js/programacion_premios.js"></script>
</body>
</html>