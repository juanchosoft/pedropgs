<?php
require './admin/include/generic_classes.php';
include './admin/classes/Producto.php';
include './admin/classes/Categoria.php';
include './admin/classes/Unidades.php';

//Permisos
$view = SessionData::getPermission(7);
$create = SessionData::getPermission(8);
$edit = SessionData::getPermission(9);
$delete = SessionData::getPermission(10);
$enable = SessionData::getPermission(11);
//Validación
if (!$view) {
  require 'permiso_denegado.php';
}

// Opción de los Categoria
$arrCategorias = Categoria::getAll(null);
$isvalidCat = $arrCategorias['output']['valid'];
$arrCategorias = $arrCategorias['output']['response'];
$optionCategoria = '<option value="seleccione">Seleccione...</option>';
foreach ($arrCategorias as $val) {
  $optionCategoria .= "<option value='" . $val['id'] . "'>" . $val['name'] . "</option>";
}

// Opción de las Unidades
$arrUnidades = Unidades::getAll(null);
$isvalidUni = $arrUnidades['output']['valid'];
$arrUnidades = $arrUnidades['output']['response'];
$optionUnidades = '<option value="selecct">Select...</option>';
foreach ($arrUnidades as $val) {
  $optionUnidades .= "<option value='" . $val['id'] . "'>" . $val['administrador'] . "</option>";
}

// Opciones de unidades
$arrUnidades = Unidades::getAll(null);
$isvalidCat = $arrUnidades['output']['valid'];
$arrUnidades = $arrUnidades['output']['response'];
$optionUnidades = '<option value="seleccione">Seleccione...</option>';
foreach ($arrUnidades as $val) {
  $optionUnidades .= "<option value='" . $val['id'] . "'>" . $val['nombre'] . "</option>";
}

// Informaciòn de productos
$arr = Producto::getAll(null);
$isvalid = $arr['output']['valid'];
$arr = $arr['output']['response'];
$modulo = 'Products';

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
            <li class="breadcrumb-item active"><a href="javascript:void(0)">Products</a></li>
          </ol>
        </div>
        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h4 class="card-title"><?php echo $modulo; ?></h4>
                <div class="d-flex mt-3 mt-sm-0">
                  <?php if ($create) { ?>

                    <button class="btn btn-primary btn-rounded ml-3" data-target="#myModal" data-toggle="modal" data-backdrop="static" data-keyboard="false">
                      New Product
                    </button>
                  <?php } ?>
                </div>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table id="dynamictable" class="table table-hover table-responsive-sm">
                    <thead>
                      <tr>
                        <th>Code</th>
                        <th>Pic</th>
                        <th>Name</th>
                        <th>Quantity</th>
                        <th>Category</th>
                        <th>HOA</th>
                        <th>Enable</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>

                      <?php

                      $c = count($arr);
                      if ($isvalid) {
                        for ($i = 0; $i < $c; $i++) {
                          $img = $arr[$i]["image"];
                          $img = $img !== "" && $img !== "no_image.png" ? "assets/img/admin/" . $img : 'assets/img/logo-spiderP.png';
                      ?>
                          <tr>
                            <td class="text-primary"><?php echo $arr[$i]['codigo']; ?></td>
                            <td class="text-primary"><img width='60' height='60' src='<?php echo $img; ?>' alt='Imagen' /></td>
                            <td class="text-primary"> <?php echo $arr[$i]['nombre_prod']; ?></td>
                            <td class="text-primary"> <?php echo $arr[$i]['quantity']; ?></td>
                            <td class="text-primary"> <?php echo $arr[$i]['name']; ?></td>
                            <td class="text-primary"> <?php echo $arr[$i]['unidad']; ?></td>
                            <td><?php echo $arr[$i]['enable']; ?></td>
                            <td class="td-actions text-left">
                              <?php if ($edit) { ?>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="PRODUCTO.editdata(<?php echo $arr[$i]['id']; ?>);" data-original-title="" title="Edit">
                                  <i class="fa fa-pencil"></i>
                                </button>

                              <?php }
                              if ($enable) { ?>
                                <button type="button" class="btn btn-outline-warning btn-sm" onclick="PRODUCTO.enabledata(<?php echo $arr[$i]['id']; ?>, '<?php echo $arr[$i]['enable']; ?>');" class="btn btn-outline-warning btn-sm" data-original-title="" title="Enable / Disable">
                                  <i class="fa fa-unlock"></i>
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
  <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="lbcondiciones_rgpd" aria-hidden="true">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header card-header card-header-danger">
          <h4 class="modal-title">New Product</h4>
          <button type="button" onclick="UTIL.clearForm('formcreate');" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <form id="formcreate" autocomplete="off">
            <div class="container-fluid">
              <input type="hidden" name="op" id="op" />
              <input type="hidden" name="id" id="id" />
              <div class="row">
                <div class="col-sm-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Product Type</label>
                    <select class="form-control" id="tipo" name="tipo">
                      <option value="Estandar">Standard</option>
                      <option value="Combo">Combo</option>
                      <option value="Servicio">Service</option>
                    </select>
                  </div>
                </div>
                <div class="col-sm-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Code<b class="errLbl">*</b></label>
                    <input type="text" onKeyPress="return sololetras_numeros(event);" class="form-control" id="codigo" name="codigo">
                  </div>
                </div>
                <div class="col-sm-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Name<b class="errLbl">*</b></label>
                    <input type="text" class="form-control" id="nombre_prod" name="nombre_prod">
                  </div>
                </div>
                <div class="col-sm-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Product Presentation<b class="errLbl">*</b></label>
                    <select class="form-control" id="presentacion" name="presentacion">
                      <option value="Unidad">Unit</option>
                      <option value="Paquete">package</option>
                    </select>
                  </div>
                </div>
                <div class="col-sm-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">>HOA<b class="errLbl">*</b></label>
                    <select class="form-control" id="tbl_unidad_id" name="tbl_unidad_id">
                      <?php echo $optionUnidades; ?>
                    </select>
                  </div>
                </div>
                <div class="col-sm-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Select Categorie<b class="errLbl">*</b></label>
                    <select class="form-control" id="tec_category_id" name="tec_category_id">
                      <?php echo $optionCategoria; ?>
                    </select>
                  </div>
                </div>
                <div class="col-sm-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Quantity<b class="errLbl">*</b></label>
                    <input type="text" class="form-control" id="quantity" name="quantity" placeholder="" onKeyPress="return soloNumeros(event);">
                  </div>
                </div>
                <div class="col-sm-4">
                  <div class="form-group">
                    <label class="bmd-label-floating"> Initial Quantity<b class="errLbl">*</b></label>
                    <input type="text" class="form-control" id="cant_ini" name="cant_ini" placeholder="" onKeyPress="return soloNumeros(event);">
                  </div>
                </div>
                <div class="col-sm-4">
                  <div class="form-group">
                    <label class="bmd-label-floating"> Minimum Quantity<b class="errLbl">*</b></label>
                    <input type="text" class="form-control" id="cant_minima" name="cant_minima" placeholder="" onKeyPress="return soloNumeros(event);">
                  </div>
                </div>
                <div class="col-sm-4">
                  <div class="form-group">
                    <label for="enable" class="bmd-label-floating">Enable</label>
                    <select class="form-control" id="enable" name="enable">
                      <option value="yes">Yes</option>
                      <option value="no">No</option>
                    </select>
                  </div>
                </div>
                <div class="col-sm-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Cost<b class="errLbl">*</b></label>
                    <input type="text" class="form-control" id="costo" name="costo" onKeyPress="return soloNumeros(event);">
                  </div>
                </div>
                <div class="col-sm-4">
                  <div class="form-group">
                    <label class="bmd-label-floating">Description<b class="errLbl"></b></label>
                    <input type="text" class="form-control" id="descripcion" name="descripcion" placeholder="">
                  </div>
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
          </form>
        </div>
        <!-- Modal footer -->
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-dark btn-rounded" onclick="UTIL.clearForm('formcreate');" data-dismiss="modal">Cancel</button>
          <button type="button" onclick="PRODUCTO.validateData();" class="btn btn-primary btn-rounded">Save</button>
        </div>
      </div>
    </div>
  </div>
  <div class="modal fade" id="myModalDetalle" tabindex="-1" role="dialog" aria-labelledby="largeModal" aria-hidden="true">
    <div class="modal-dialog modal-lg">
     <div class="modal-content">
      
        <!-- Modal Header -->
        <div class="modal-header card-header card-header-danger">
          <h3 class="modal-title">Detallado de Producto: <span id="nameProd"></span></h3>
          <button type="button" onclick="UTIL.resetForm('formcreate');" class="close" data-dismiss="modal">&times;</button>
        </div>

        <!-- añadir detalle del  -->
        <div class="modal-body">
          <div class="container-fluid">
            <input type="hidden" name="op" id="op" />
            <input type="hidden" name="product_id" id="product_id" />
            <div class="row">
              <form id="formcolores" autocomplete="off">
                <div class="col-lg-3 col-md-12">
                  <table id="tablecolores" class="table table-sm">
                    <center>COLORES</center>
                    <tr>
                      <th>Asignar</th>
                      <th>Color</th>
                    </tr>
                    <tbody id="tbodyColores"> </tbody>
                  </table>
                </div>
              </form>
              <form id="formsabores" autocomplete="off">
                <div class="col-lg-3 col-md-12">
                  <table id="tablesabores" class="table table-sm">
                    <center>SABORES</h5>
                    </center>
                    <tr>
                      <th>Asignar</th>
                      <th>Sabor</th>
                      <th>Tipo</th>
                    </tr>
                    <tbody id="tbodySabores"> </tbody>
                  </table>
                </div>
              </form>
              <form id="formtamano" autocomplete="off">
                <div class="col-lg-3 col-md-12">
                  <table id="tabletamanos" class="table table-sm">
                    <center>TAMAÑO</h5> </center>
                    <tr>
                      <th>Asignar</th>
                      <th>Tamaño</th>
                      <th>Tipo</th>
                    </tr>
                    <tbody id="tbodyTamano"> </tbody>
                  </table>
               </div>
              </form>

              <form id="formadiciones" autocomplete="off">
                <div class="col-lg-3 col-md-12">
                  <table id="tableadiciones" class="table table-sm">
                    <center>ADICIONES</h5>
                    </center>
                    <tr>
                      <th>Asignar</th>
                      <th>Adicción</th>
                    </tr>
                    <tbody id="tbodyAdiciones"> </tbody>
                  </table>
                </div>
              </form>
              <form id="formadiciones" autocomplete="off">
                <div class="col-lg-3 col-md-12">
                  <table id="tableadiciones" class="table table-sm">
                    <center>TALLA</h5></center>
                    <tr>
                      <th>Asignar</th>
                      <th>Talla</th>
                    </tr>
                    <tbody id="tbodyTalla"> </tbody>
                  </table>
                </div>
              </form>
            </div>
            <!-- Modal footer -->
            <div class="modal-footer">
              <button type="button" class="btn btn-danger" onclick="UTIL.resetForm('formcreate');" data-dismiss="modal">Cancelar</button>
              <button type="button" onclick="DETALLE_PRODUCTO.saveDetalle();" class="btn btn-success">Guardar</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>



  <!-- The Modal para agregar productos tipo adiciones -->

  <div class="modal fade " id="myModalProductosAdiciones" tabindex="-1" role="dialog" aria-labelledby="lbcondiciones_rgpd" aria-hidden="true">

    <div class="modal-dialog modal-lg modal-right">



      <div class="modal-content">



        <!-- Modal Header -->

        <div class="modal-header card-header card-header-danger">

          <h4 class="modal-title">Productos</h4>

          <button type="button" class="close" data-dismiss="modal">&times;</button>

        </div>



        <!-- Modal productos -->

       <div class="modal-body">

          <form id="formpermission" autocomplete="off">

            <div class="container-fluid">

              <div class="row">

                <div class="card">

                  <div class="card-header card-header-tabs card-header card-header-spider">

                    <div class="nav-tabs-navigation">

                      <div class="nav-tabs-wrapper">

                        <span class="nav-tabs-title">Adiciones para agregar al

                          carrito de compras</span>
                 
                        <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="lbcondiciones_rgpd" aria-hidden="true">
                          <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                              <div class="modal-header card-header card-header-danger">
                                <h4 class="modal-title">New Product</h4>
                                <button type="button" onclick="UTIL.clearForm('formcreate');" class="close" data-dismiss="modal">&times;</button>
                              </div>

                              <div class="modal-body">
                                <form id="formcreate" autocomplete="off">
                                  <div class="container-fluid">
                                    <input type="hidden" name="op" id="op" />
                                    <input type="hidden" name="id" id="id" />
                                    <div class="row">
                                      <div class="col-sm-4">
                                        <div class="form-group">
                                          <label class="bmd-label-floating">Product Type</label>
                                          <select class="form-control" id="tipo" name="tipo">
                                            <option value="Estandar">Standard</option>
                                            <option value="Combo">Combo</option>
                                            <option value="Servicio">Service</option>
                                          </select>
                                        </div>
                                      </div>
                                      <div class="col-sm-4">
                                        <div class="form-group">
                                          <label class="bmd-label-floating">Code<b class="errLbl">*</b></label>
                                          <input type="text" onKeyPress="return sololetras_numeros(event);" class="form-control" id="codigo" name="codigo">
                                        </div>
                                      </div>
                                      <div class="col-sm-4">
                                        <div class="form-group">
                                          <label class="bmd-label-floating">Name<b class="errLbl">*</b></label>
                                          <input type="text" class="form-control" id="nombre_prod" name="nombre_prod">
                                        </div>
                                      </div>
                                      <div class="col-sm-4">
                                        <div class="form-group">
                                          <label class="bmd-label-floating">Product Presentation<b class="errLbl">*</b></label>
                                          <select class="form-control" id="presentacion" name="presentacion">

                                            <option value="Unidad">Unit</option>

                                            <option value="Paquete">package</option>


                                          </select>

                                        </div>

                                      </div>

                                      <div class="col-sm-4">
                                        <div class="form-group">
                                          <label class="bmd-label-floating">HOA designated (administrador)<b class="errLbl">*</b></label>
                                          <select class="form-control" id="tbl_unidad_id" name="tbl_unidad_id">
                                            <?php echo $optionUnidades; ?>
                                          </select>
                                        </div>
                                      </div>
                                      <div class="col-sm-4">
                                        <div class="form-group">
                                          <label class="bmd-label-floating">Select Categorie<b class="errLbl">*</b></label>
                                          <select class="form-control" id="categoria_id" name="categoria_id">
                                            <?php echo $optionCategoria; ?>
                                          </select>
                                        </div>
                                      </div>
                                      <div class="col-sm-4">

                                        <div class="form-group">

                                          <label class="bmd-label-floating">Quantity Minimum<b class="errLbl">*</b></label>

                                          <input type="text" class="form-control" id="cant_minima" name="cant_minima" placeholder="" onKeyPress="return soloNumeros(event);">

                                        </div>

                                      </div>

                                      <div class="col-sm-4">

                                        <div class="form-group">

                                          <label class="bmd-label-floating"> Initial Quantity<b class="errLbl">*</b></label>

                                          <input type="text" class="form-control" id="cant_ini" name="cant_ini" placeholder="" onKeyPress="return soloNumeros(event);">

                                        </div>

                                      </div>

                                      <div class="col-sm-4">

                                        <div class="form-group">

                                          <label for="habilitado" class="bmd-label-floating">Enable</label>

                                          <select class="form-control" id="habilitado" name="habilitado">

                                            <option value="yes">Yes</option>

                                            <option value="no">No</option>

                                          </select>

                                        </div>

                                      </div>



                                      <div class="col-sm-4">

                                        <div class="form-group">

                                          <label class="bmd-label-floating">Cost<b class="errLbl">*</b></label>

                                          <input type="text" class="form-control" id="costo" name="costo" onKeyPress="return soloNumeros(event);">

                                        </div>

                                      </div>


                                      <div class="col-sm-4">

                                        <div class="form-group">

                                          <label class="bmd-label-floating">Description<b class="errLbl"></b></label>

                                          <input type="text" class="form-control" id="descripcion" name="descripcion" placeholder="">

                                        </div>

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

                                </form>

                              </div>


                              <!-- Modal footer -->

                              <div class="modal-footer">

                                <button type="button" class="btn btn-outline-dark btn-rounded" onclick="UTIL.clearForm('formcreate');" data-dismiss="modal">Cancel</button>

                                <button type="button" onclick="PRODUCTO.validateData();" class="btn btn-primary btn-rounded">Save</button>

                              </div>

                            </div>

                          </div>

                        </div>



                        <div class="modal fade" id="myModalDetalle" tabindex="-1" role="dialog" aria-labelledby="largeModal" aria-hidden="true">

                          <div class="modal-dialog modal-lg">



                            <div class="modal-content">



                              <!-- Modal Header -->

                              <div class="modal-header card-header card-header-danger">

                                <h3 class="modal-title">Detallado de Producto: <span id="nameProd"></span></h3>

                                <button type="button" onclick="UTIL.resetForm('formcreate');" class="close" data-dismiss="modal">&times;</button>

                              </div>



                              <!-- añadir detalle del  -->

                              <div class="modal-body">

                                <div class="container-fluid">

                                  <input type="hidden" name="op" id="op" />

                                  <input type="hidden" name="product_id" id="product_id" />

                                  <div class="row">



                                    <form id="formcolores" autocomplete="off">

                                      <div class="col-lg-3 col-md-12">

                                        <table id="tablecolores" class="table table-sm">

                                          <center>COLORES</center>

                                          <tr>

                                            <th>Asignar</th>

                                            <th>Color</th>

                                          </tr>

                                          <tbody id="tbodyColores"> </tbody>

                                        </table>

                                      </div>

                                    </form>



                                    <form id="formsabores" autocomplete="off">

                                      <div class="col-lg-3 col-md-12">

                                        <table id="tablesabores" class="table table-sm">

                                          <center>SABORES</h5>

                                          </center>

                                          <tr>

                                            <th>Asignar</th>

                                            <th>Sabor</th>

                                            <th>Tipo</th>

                                          </tr>

                                          <tbody id="tbodySabores"> </tbody>

                                        </table>

                                      </div>

                                    </form>



                                    <form id="formtamano" autocomplete="off">

                                      <div class="col-lg-3 col-md-12">

                                        <table id="tabletamanos" class="table table-sm">

                                          <center>TAMAÑO</h5>

                                          </center>

                                          <tr>

                                            <th>Asignar</th>

                                            <th>Tamaño</th>

                                            <th>Tipo</th>

                                          </tr>

                                          <tbody id="tbodyTamano"> </tbody>



                                        </table>

                                      </div>

                                    </form>



                                    <form id="formadiciones" autocomplete="off">

                                      <div class="col-lg-3 col-md-12">

                                        <table id="tableadiciones" class="table table-sm">

                                          <center>ADICIONES</h5>

                                          </center>

                                          <tr>

                                            <th>Asignar</th>

                                            <th>Adicción</th>

                                          </tr>

                                          <tbody id="tbodyAdiciones"> </tbody>

                                        </table>

                                      </div>

                                    </form>



                                    <form id="formadiciones" autocomplete="off">

                                      <div class="col-lg-3 col-md-12">

                                        <table id="tableadiciones" class="table table-sm">

                                          <center>TALLA</h5>

                                          </center>

                                          <tr>

                                            <th>Asignar</th>

                                            <th>Talla</th>

                                          </tr>

                                          <tbody id="tbodyTalla"> </tbody>

                                        </table>

                                      </div>

                                    </form>



                                  </div>

                                  <!-- Modal footer -->

                                  <div class="modal-footer">

                                    <button type="button" class="btn btn-danger" onclick="UTIL.resetForm('formcreate');" data-dismiss="modal">Cancelar</button>

                                    <button type="button" onclick="DETALLE_PRODUCTO.saveDetalle();" class="btn btn-success">Guardar</button>

                                  </div>

                                  >>>>>>> 45e2b9250cca1dee003b636db65c09011903c1f4

                                </div>

                              </div>

                              <<<<<<< HEAD </div>

                                <div class="card-body">



                                  <div style="width: 400px;">

                                    <div class="input-group no-border">

                                      <input type="text" id="searchAdicion" name="searchAdicion" class="form-control" placeholder="Search by code or name...">

                                      <button type="button" onclick="PEDIDOCAJA.getProductosAdicion()" title="Refrescar" class="btn btn-danger btn-round btn-just-icon">

                                        <i class="material-icons">refresh</i>

                                        <div class="ripple-container"></div>

                                      </button>

                                    </div>

                                  </div>

                                  <table class="table">

                                    <thead class=" text-primary">

                                      <th>Agregar</th>

                                      <th>Adición</th>

                                      <th>Precio 1 Adición</th>

                                      <th>Cantidad</th>

                                    </thead>

                                    <tbody id="tbodyproductosAdicion">

                                    </tbody>

                                  </table>

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
  =======


  </div>

  </div>
  >>>>>>> 45e2b9250cca1dee003b636db65c09011903c1f4

  </div>

  </div>



  <!-- The Modal para agregar productos tipo adiciones -->

  <div class="modal fade " id="myModalProductosAdiciones" tabindex="-1" role="dialog" aria-labelledby="lbcondiciones_rgpd" aria-hidden="true">

    <div class="modal-dialog modal-lg modal-right">



      <div class="modal-content">



        <!-- Modal Header -->

        <div class="modal-header card-header card-header-danger">

          <h4 class="modal-title">Productos</h4>

          <button type="button" class="close" data-dismiss="modal">&times;</button>

        </div>



        <!-- Modal productos -->

        <div class="modal-body">

          <form id="formpermission" autocomplete="off">

            <div class="container-fluid">

              <div class="row">

                <div class="card">

                  <div class="card-header card-header-tabs card-header card-header-spider">

                    <div class="nav-tabs-navigation">

                      <div class="nav-tabs-wrapper">

                        <span class="nav-tabs-title">Adiciones para agregar al

                          carrito de compras</span>

                      </div>

                    </div>

                  </div>

                  <div class="card-body">



                    <div style="width: 400px;">

                      <div class="input-group no-border">

                        <input type="text" id="searchAdicion" name="searchAdicion" class="form-control" placeholder="Buscar por código o nombre...">

                        <button type="button" onclick="PEDIDOCAJA.getProductosAdicion()" title="Refrescar" class="btn btn-danger btn-round btn-just-icon">

                          <i class="material-icons">refresh</i>

                          <div class="ripple-container"></div>

                        </button>

                      </div>

                    </div>

                    <table class="table">

                      <thead class=" text-primary">

                        <th>Agregar</th>

                        <th>Adición</th>

                        <th>Precio 1 Adición</th>

                        <th>Cantidad</th>

                      </thead>

                      <tbody id="tbodyproductosAdicion">

                      </tbody>

                    </table>

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

  <script type="text/javascript" src="./admin/js/producto.js"></script>

  <script type="text/javascript" src="./admin/js/detalle_producto.js"></script>

  <?php include './admin/include/generic_dataTables.php'; ?>

</body>



</html>