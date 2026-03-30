<?php
require './admin/include/generic_classes.php';
include './admin/classes/Requerimiento.php';
//Permisos
$view = SessionData::getPermission(38);
$create = SessionData::getPermission(38);
$edit = SessionData::getPermission(38);
$delete = SessionData::getPermission(38);
$enable = SessionData::getPermission(38);
//Validación
if (!$view) {
    require 'permiso_denegado.php';
}

// Variables para descargar excel con los requerimientos resgitradas
$f1 = "00:00:0000";
$f2 = "00:00:0000";

// Buscador
if (isset($_POST['search']) && $_POST['search'] != "") {
    $rqs = array('search' => $_POST['search']);
    $arr = Requerimiento::getRequerimientoHistorial($rqs);
} else {
    // Buscar por fechas (Fecha inicial y final)
    if (isset($_POST['f1']) && $_POST['f1'] != "" && isset($_POST['f2']) && $_POST['f2'] != "") {
        $f1 = $_POST['f1'];
        $f2 = $_POST['f2'];
        $rqs = array('rango_fechas' => 'si', 'f1' => $_POST['f1'], 'f2' => $_POST['f2']);
        $arr = Requerimiento::getRequerimientoHistorial($rqs);
    } else {
        // Información de Requerimientos registrados
        $arr = Requerimiento::getRequerimientoHistorial(null);
    }
}


$isvalid = $arr['output']['valid'];
$arr = $arr['output']['response'];
$modulo = 'History of work done';
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
                        <li class="breadcrumb-item active"><a href="javascript:void(0)">History Works</a></li>
                    </ol>
                </div>

                <div class="row">
                    <div class="col-xl-6 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Search by Date</h4>
                            </div>
                            <div class="card-body">
                                <form name="form" action="work_done.php" class="navbar-form" method="POST">
                                    <div class="input-group no-border">
                                        <div class="col-sm-5">
                                            <div class="form-horizontal">
                                                <div class="form-group mb-0">
                                                    <input type="date" id="f1" name="f1" class="form-control" placeholder="Fecha inicial">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-5">
                                            <div class="form-horizontal">
                                                <div class="form-group mb-0">
                                                    <input type="date" id="f2" name="f2" class="form-control" placeholder="Fecha final">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-sm-2">
                                            <div class="form-horizontal">
                                                <div class="form-group mb-0">
                                                    <button type="submit" id="btn-fechas" name="btn-fechas" class="btn btn-outline-warning btn-round btn-just-icon">
                                                        <i class="fa fa-chevron-right"></i>
                                                        <div class="ripple-container"></div>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
<!--                     <div class="col-xl-3 col-lg-3">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Date Filter</h4>
                            </div>
                            <div class="card-body">
                                <p class="card-category d-inline"><?php echo $f1; ?> - <?php echo $f2; ?> </p>
                                <form name="form" action="descargar_ventas.php" class="navbar-form d-inline" method="POST">
                                    <div class="input-group no-border d-inline">
                                        <input type="hidden" id="f1d" name="f1d" value="<?php echo $f1; ?>">
                                        <input type="hidden" id="f2d" name="f2d" value="<?php echo $f2; ?>">
                                        <button type="submit" class="btn btn-outline-warning btn-round btn-just-icon"><i class="fa fa-cloud-download"></i></button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div> -->
                    <div class="col-xl-6 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Search by Id</h4>
                            </div>
                            <div class="card-body">
                                <form name="form" action="work_done.php" class="navbar-form" method="POST">
                                    <div class="input-group no-border">
                                        <input type="text" id="search" name="search" class="form-control" placeholder="Buscar por N°">
                                        <button type="submit" class="btn btn-outline-warning btn-round btn-just-icon">
                                            <i class="fa fa-search"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="dynamictable" class="table table-hover table-responsive-sm">
                                        <thead class=" text-primary">
                                        <th></th>
                                        <th>ID</th>
                                        <th>Zone</th>
                                        <th>Priority requirement</th>
                                        <th>Date</th>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $c = count($arr);
                                            if ($isvalid) {
                                                for ($i = 0; $i < $c; $i++) {
                                            ?>
                                                    <tr>
                                                        <td class="td-actions text-left">
                                                            <div class="dropdown">
                                                                <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                                                    Action
                                                                </button>
                                                                <div class="dropdown-menu">
                                                                    <a type="button" href="" target="_blank" class="dropdown-item">
                                                                        After
                                                                    </a>
                                                                    <a type="button" href="" target="_blank" class="dropdown-item">
                                                                        before
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><?php echo $arr[$i]['zona']; ?></td>
                                                        <td><?php echo $arr[$i]['detalles']; ?></td>
                                                        <td><?php echo $arr[$i]['estado']; ?></td>
                                                        <td><?php echo $arr[$i]['dtcreate']; ?></td>
                                                       
                                                       
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
                    <h4 class="modal-title">Confirmación para anular la venta realizada.</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="formcreate" autocomplete="off">
                        <div class="container-fluid">
                            <input type="hidden" name="op" id="op" />
                            <input type="hidden" name="id" id="id" />
                            <div class="row">
                                <div class="card-body">

                                    <div class="col-sm-12">
                                        <div class="form-horizontal">
                                            <div class="form-group">
                                                <label class="bmd-label-floating">Observación de la anulación<b class="errLbl">*</b></label>
                                                <textarea type="text" class="form-control" max="200" id="observaciones" name="observaciones" placeholder=""></textarea>
                                                <span class="bmd-help">Ingrese un motivo por el cual va a anular la venta seleccionada
                                                    previamente.</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-danger" data-dismiss="modal">Cancelar</button>
                                        <button type="button" onclick="VENTAS_REGISTRADAS.confirmarAnulacion();" class="btn btn-success">Confirmar anulación</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include './admin/include/gerenic_footer.php'; ?>
    <?php include './admin/include/gerenic_script.php'; ?>
    <?php include './admin/include/generic_search.php'; ?>
    <?php include './admin/include/generic_dataTables.php'; ?>

    <script type="text/javascript" src="./admin/js/ventas-registradas.js"></script>
    <script type="text/javascript" src="./admin/js/impresion.js"></script>
</body>

</html>