<?php
require './admin/include/generic_classes.php';
include './admin/classes/Check.php';
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

$arr = Check::getAll(NULL);
$isvalid = $arr['output']['valid'];
$arr = $arr['output']['response'];
$modulo = 'Show Check';


// Opción de las Unidades
$arrUnidades = Unidades::getAll(null);
$isvalidUni = $arrUnidades['output']['valid'];
$arrUnidades = $arrUnidades['output']['response'];
$optionUnidades = '<option value="selecct">Select...</option>';
foreach ($arrUnidades as $val) {
  $optionUnidades .= "<option value='" . $val['id'] . "'>" . $val['nombre'] . "</option>";
}


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
            <li class="breadcrumb-item active"><a href="javascript:void(0)">Show Check</a></li>
          </ol>
        </div>

        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h4 class="card-title">Show Check</h4>
                <div class="d-flex mt-3 mt-sm-0">
                  <button class="btn btn-primary btn-rounded ml-3" data-target="#myModal" data-toggle="modal" data-backdrop="static" data-keyboard="false"> New Employe</button>
                </div>
              </div>


              <div class="card-body">
                <div class="table-responsive">
                  <table id="dynamictable" class="table table-hover table-responsive-sm">
                    <thead>
                      <th>id</th>
                      <th>HOA</th>
                      <th>Employe</th>
                      <th>See report</th>
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
                            <td class="text-primary"> <?php echo $arr[$i]['employee']; ?></td>
                            <td class="text-primary"> <?php echo $arr[$i]['dtcreate']; ?></td>
                            <td class="td-actions text-left">
                            <a href="daily_report_view.php?reporte=<?php echo $arr[$i]['id']; ?>"  target="_blank" type="submit" title="Ver" rel="tooltip" class="btn btn-outline-primary btn-sm"><i class="fas fa-eye"></i></a>
                             
                              </button>
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

  

  <?php include './admin/include/gerenic_footer.php'; ?>

  <!-- Script -->
  <?php include './admin/include/gerenic_script.php'; ?>
  <?php include './admin/include/generic_dataTables.php'; ?>
  <!-- Script -->


  <script type="text/javascript" src="./admin/js/empleado.js"></script>

  <style>
    .mb-0>a {
      display: block;
      position: relative;
    }

    .mb-0>a:after {
      content: "\f078";
      /* fa-chevron-down */
      font-family: 'FontAwesome';
      position: absolute;
      right: 0;
    }

    .mb-0>a[aria-expanded="true"]:after {
      content: "\f077";
      /* fa-chevron-up */
    }

    h5.mb-0 {
      display: block;
      width: 100%;
    }

    .hojadevida .card {
      margin-bottom: 0.5rem;
      border: 1px solid #ecedef;
    }
  </style>
</body>

</html>