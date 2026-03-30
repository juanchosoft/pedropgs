<?php
require './admin/include/generic_classes.php';
include './admin/classes/Categoria.php';
//Permisos
$view = SessionData::getPermission(26);
$create = SessionData::getPermission(21);
$edit = SessionData::getPermission(24);
$delete = SessionData::getPermission(22);
$enable = SessionData::getPermission(23);
//Validación
if (!$view) {
  require 'permiso_denegado.php';
}

$arr = Categoria::getAll(null);
$isvalid = $arr['output']['valid'];
$arr = $arr['output']['response'];
$modulo = 'Category';
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
            <li class="breadcrumb-item active"><a href="javascript:void(0)">Category</a></li>
          </ol>
        </div>

        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h4 class="card-title"><?php echo $modulo; ?></h4>
                <div class="d-flex mt-3 mt-sm-0">
                  </form> -->
                  <?php if ($create) { ?>
                    <button class="btn btn-primary btn-rounded ml-3" data-target="#myModal" data-toggle="modal" data-backdrop="static" data-keyboard="false"> New Categorie</button>
                  <?php } ?>
                </div>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table id="dynamictable" class="table">
                    <thead class=" text-primary">
                      <th>Action</th>
                      <th>Pic</th>
                      <th>Code</th>
                      <th>Name</th>
                      <th>Group</th>
                      <th>Enable</th>
                    </thead>
                    <tbody>
                      <?php
                      $c = count($arr);
                      if ($isvalid) {
                        for ($i = 0; $i < $c; $i++) {
                          $img = $arr[$i]["image"];
                          $img = $img !== "" && $img !== "no_image.png" ? "assets/img/admin/" . $img : 'assets/img/logo2.png.png';
                      ?>
                          <tr>
                            <td class="td-actions text-left">
                              <?php if ($edit) { ?>
                                <button type="button" rel="tooltip" class="btn btn-outline-primary btn-sm" onclick="CATEGORIA.editdata(<?php echo $arr[$i]['id']; ?>);" data-original-title="" title="New Categorie">
                                  <i class="fa fa-pencil"></i>
                                </button>
                              <?php }
                              if ($delete) { ?>
                                <!-- <button type="button" rel="tooltip" class="btn btn-danger btn-round" onclick="CATEGORIA.deletedata(<?php echo $arr[$i]['id']; ?>);" data-original-title="" title="Eliminar Categoria">
                                                      <i class="material-icons">delete_forever</i>
                                                    </button> -->
                              <?php }
                              if ($enable) { ?>
                                <button type="button" rel="tooltip" onclick="CATEGORIA.enabledata(<?php echo $arr[$i]['id']; ?>, '<?php echo $arr[$i]['enable']; ?>');" class="btn btn-outline-warning btn-sm" data-original-title="" title="Enable / Disable">
                                  <i class="fa fa-unlock"></i>
                                </button>
                              <?php } ?>
                            </td>
                            <td class="text-primary"><img width='60' height='60' src='<?php echo $img; ?>' alt='Imagen' /> </td>
                            <td class="text-primary"><?php echo $arr[$i]['code']; ?></td>
                            <td class="text-primary"> <?php echo $arr[$i]['name']; ?></td>
                            <td class="text-primary"> <?php echo $arr[$i]['group_category']; ?></td>
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

  <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="lbcondiciones_rgpd" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header card-header card-header-danger">
          <h4 class="modal-title">New Categorie</h4>
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
                <div class="col-sm-12">
                  <div class="form-horizontal">

                    <div class="form-group">
                      <label class="bmd-label-floating">Name<b class="errLbl">*</b></label>
                      <input required type="text" class="form-control" id="name" name="name">
                    </div>

                    <div class="form-group">
                      <label class="bmd-label-floating">Code<b class="errLbl">*</b></label>
                      <input required type="text" maxlength="6" class="form-control" id="code" name="code">
                    </div>
                    <div class="form-group">
                      <label for="group_category" class="bmd-label-floating">Group Categorie<b class="errLbl">*</b></label>
                      <select class="form-control" id="group_category" name="group_category">
                        <option value="TOOLS">TOOLS</option>
                        <option value="CONSTRUCCION MATERIALS">CONSTRUCCION MATERIALS</option>

                      </select>
                    </div>

                    <div class="form-group">
                      <label for="enable" class="bmd-label-floating">Enable<b class="errLbl">*</b></label>
                      <select class="form-control" id="enable" name="enable">
                        <option value="yes">YES</option>
                        <option value="no">NO</option>
                      </select>
                    </div>

                    <div class="form-group">
                      <label class="col-sm-4 control-label" for="exampleInputName2">File</label>
                      <div class="col-sm-8">
                        <div class="controls">
                          <iframe id='ifm' name='ifm' src="upload.php" width="200" height="60" scrolling="no" frameborder="0"></iframe>
                        </div>
                      </div>
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
          <button type="button" onclick="CATEGORIA.validateData();" class="btn btn-primary btn-rounded">Save</button>
        </div>
      </div>
    </div>
  </div>

  <?php include './admin/include/gerenic_footer.php'; ?>
  <?php include './admin/include/gerenic_script.php'; ?>
  <?php include './admin/include/generic_search.php'; ?>
  <?php include './admin/include/generic_dataTables.php'; ?>
  <script type="text/javascript" src="./admin/js/categoria.js"></script>
</body>

</html>