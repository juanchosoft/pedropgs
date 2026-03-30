<?php

require './admin/include/generic_classes.php';

include './admin/classes/Producto.php';

//Permisos

$view = SessionData::getPermission(50);
$create = SessionData::getPermission(51);
$edit = SessionData::getPermission(52);
$delete = SessionData::getPermission(53);
$enable = SessionData::getPermission(54);

//Validación

if (!$view) {
  require 'permiso_denegado.php';
}

$arr = Producto::getAll(null);
$isvalid = $arr['output']['valid'];
$arr = $arr['output']['response'];
$modulo = 'Inventory Tools';
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
                        <li class="breadcrumb-item active"><a href="javascript:void(0)">Inventory General</a></li>
                    </ol>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">Inventory</h4>
                                <div class="d-flex mt-3 mt-sm-0">
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="dynamictable" class="table table-hover table-responsive-sm">
                                        <thead>
                                            <th>Exit</th>
                                            <th>Adjustment</th>
                                            <th>Details</th>
                                            <th>Code</th>
                                            <th>Picture</th>
                                            <th>Name</th>
                                            <th>HOA</th>
                                            <th>Quantity</th>
                                            <th>Alert Quantity</th>
                                            <th>Category</th>
                                        </thead>
                                        <tbody>
                                            <?php

                                        $c = count($arr);
                                        if ($isvalid) {
                                          for ($i = 0; $i < $c; $i++) {
                                            $img = $arr[$i]["image"];
                                            $img = $img !=="" && $img !== "no_image.png" ? "assets/img/admin/".$img : 'assets/img/logo-spiderP.png';
                                        ?>
                                            <tr>
                                                <td class="td-actions text-left">
                                                    <button class="btn light btn-danger btn-sm"
                                                        data-target="#myModalEstadoCuenta"
                                                        onclick="INVENTARIO.abrirModal( <?php echo $arr[$i]['id']; ?>, '#myModalSalida');"
                                                        title="Exit" data-original-title=""
                                                        data-backdrop="static" data-keyboard="false"><i
                                                            class="fa fa-sign-out"></i>
                                                    </button>
                                                </td>
                                                <td class="td-actions text-left">
                                                    <button class="btn light btn-info btn-sm"
                                                        data-target="#myModalEstadoCuenta"
                                                        onclick="INVENTARIO.abrirModal( <?php echo $arr[$i]['id']; ?>, '#myModalAjuste');"
                                                        title="Adjust" data-original-title="" data-backdrop="static"
                                                        data-keyboard="false"><i class="fa fa-exchange"></i>
                                                    </button>
                                                </td>
                                                <td class="td-actions text-left">
                                                    <button class="btn light btn-primary btn-sm"
                                                        data-target="#myModalEstadoCuenta"
                                                        onclick="INVENTARIO.getInventarioDetalladaSalidas( <?php echo $arr[$i]['id']; ?>);"
                                                        title="Details" data-original-title="" data-backdrop="static"
                                                        data-keyboard="false"><i class="fa fa-eye"></i>
                                                    </button>
                                                </td>
                                                <td class="text-primary"><?php echo $arr[$i]['codigo']; ?></td>
                                                <td class="text-primary"><img width='60' height='60'
                                                        src='<?php echo $img; ?>' alt='Imagen' />
                                                </td>
                                                <td class="text-primary"> <?php echo $arr[$i]['nombre_prod']; ?></td>
                                                <td class="text-primary"> <?php echo $arr[$i]['unidad']; ?></td>
                                                <td class="text-primary"> <?php echo $arr[$i]['cant_actual']; ?></td>
                                                <td class="text-primary"> <?php echo $arr[$i]['cant_minima']; ?></td>
                                                <td class="text-primary"> <?php echo $arr[$i]['name']; ?></td>
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
    <div class="modal fade" id="myModalMovimiento" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header card-header card-header-danger">
                    <h4 class="modal-title">Details Motion (Producto: <span id="prodName"></span>) <span
                            class="badge light badge-warning"><span id="cantidadActualProd"></span> Unit</h4>
                    <button type="button" onclick="UTIL.clearForm('formcreate');" class="close"
                        data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="formcreate" autocomplete="off">
                        <input type="hidden" name="op" id="op" />
                        <input type="hidden" name="id_prod_movimiento" id="id_prod_movimiento" />
                    </form>
                    <div class="nav nav-pills mb-2 light">
                        <ul class="nav" data-tabs="tabs">
                            <li class="nav-item">
                                <a class="nav-link active" href="#profile" data-toggle="tab">
                                    <i class="fa fa-usd"></i> Sales <span class="badge badge-light"
                                        id="cantidadVentas"></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#messages" data-toggle="tab">
                                    <i class="fa fa-chevron-down"></i> Exit Inventory <span class="badge badge-light"
                                        id="cantidadSalidas"></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#settings" data-toggle="tab">
                                    <i class="fa fa-exchange"></i> Adjustment
                                    <div class="ripple-container"></div>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#compras" data-toggle="tab">
                                    <i class="fa fa-shopping-cart"></i> Purchases <span class="badge badge-light"
                                        id="cantidadCompras"></span>
                                    <div class="ripple-container"></div>
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="tab-content">
                        <div class="tab-pane active" id="profile">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <th>Origen</th>
                                        <th>Id Item</th>
                                        <th>Cantidad</th>
                                    </thead>
                                    <tbody id="tbodyProductos">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane" id="messages">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <th>Origen</th>
                                        <th>Id Item</th>
                                        <th>Cantidad</th>
                                        <th>Autorizó</th>
                                    </thead>
                                    <tbody id="tbodyProductosSalidas">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane" id="settings">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <th>Origen</th>
                                        <th>Id Item</th>
                                        <th>Cantidad</th>
                                        <th>Motivo</th>
                                        <th>Acción</th>
                                    </thead>
                                    <tbody id="tbodyProductosAjustes">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="tab-pane" id="compras">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <th>Origen</th>
                                        <th>Id Item</th>
                                        <th>Cantidad</th>
                                    </thead>
                                    <tbody id="tbodyProductosCompras">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-dark btn-rounded" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <!-- The Modal  Ajuste de ivnetario -->
    <div class="modal fade" id="myModalAjuste" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header card-header card-header-danger">
                    <h4 class="modal-title">Inventory Adjustment</h4>
                    <button type="button" onclick="UTIL.clearForm('formcreate');" class="close"
                        data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="formcreate" autocomplete="off">
                        <input type="hidden" name="op" id="op" />
                        <input type="hidden" name="id_prod_ajuste" id="id_prod_ajuste" />
                        <div class="form-group">
                            <label class="bmd-label-floating">Action<b class="errLbl">*</b></label>
                            <select class="form-control" id="accion" name="accion">
                                <option value="sumar">Addition</option>
                                <option value="restar">Subtraction</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="bmd-label-floating">Quantity<b class="errLbl">*</b></label>
                            <input type="text" class="form-control" id="cantidad_ajuste" name="cantidad_ajuste">
                        </div>
                        <div class="form-group">
                            <label class="bmd-label-floating">Reason<b class="errLbl">*</b></label>
                            <input type="text" class="form-control" id="motivo_ajuste" name="motivo_ajuste">
                        </div>
                   </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-dark btn-rounded"
                        onclick="UTIL.clearForm('formcreate');" data-dismiss="modal">Cancel</button>
                    <button type="button" onclick="INVENTARIO.saveAjuste();"
                        class="btn btn-primary btn-rounded">Save</button>
                </div>
            </div>
        </div>
    </div>
    <!-- The Modal salida inventario -->
    <div class="modal fade" id="myModalSalida" tabindex="-1" role="dialog" aria-labelledby="" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header card-header card-header-danger">
                    <h4 class="modal-title">inventory Output</h4>
                    <button type="button" onclick="UTIL.clearForm('formcreate');" class="close"
                        data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="formcreate" autocomplete="off">
                        <input type="hidden" name="op" id="op" />
                        <input type="hidden" name="id_prod_salida" id="id_prod_salida" />
                        <div class="form-group">
                            <label class="bmd-label-floating">Authorized By:<b class="errLbl">*</b></label>
                            <input type="text" class="form-control" id="autorizado_salida" name="autorizado_salida">
                        </div>
                        <div class="form-group">
                            <label class="bmd-label-floating">Quantity<b class="errLbl">*</b></label>
                            <input type="text" onKeyPress="return soloNumeros(event);" class="form-control text-primary"
                                id="cantidad" name="cantidad">
                        </div>
                        <div class="form-group">
                            <label class="bmd-label-floating">Reason<b class="errLbl">*</b></label>
                            <input type="text" class="form-control" id="motivo_salida" name="motivo_salida">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-dark btn-rounded"
                        onclick="UTIL.clearForm('formcreate');" data-dismiss="modal">Cancel</button>
                    <button type="button" onclick="INVENTARIO.saveSalidaInventario();"
                        class="btn btn-primary btn-rounded">Save</button>
                </div>
            </div>
        </div>
    </div>

    <?php include './admin/include/gerenic_footer.php'; ?>
    <?php include './admin/include/gerenic_script.php'; ?>
    <?php include './admin/include/generic_search.php'; ?>
    <?php include './admin/include/generic_dataTables.php'; ?>
    <script type="text/javascript" src="./admin/js/inventario.js"></script>
</body>
</html>