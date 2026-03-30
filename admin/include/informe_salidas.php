<?php

require './admin/include/generic_classes.php';

include './admin/classes/InformeTiempo.php';



$arr = InformeTiempo::getAll(null);

$isvalid = $arr['output']['valid'];

$entradas = $arr['output']['entradas'];

$salidas = $arr['output']['salidas'];

$modulo = 'Informe Tiempos Entradas - Salidas';

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
      <!-- INICIO MENU MOVIL -->
    <?php
    if (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/(Android|iPhone|iPad|iPod|Windows Phone)/i', $_SERVER['HTTP_USER_AGENT'])) {
        include './admin/include/menu_movil.php';
    } else {
        echo '<style>.menu_movil-container { display: none !important; }</style>';
    }
    ?>
    <!-- FIN MENU MOVIL -->

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

                          <li class="breadcrumb-item active"><a href="javascript:void(0)"><?php echo $modulo; ?></a></li> 

                      </ol>

                  </div>



                  <div class="row">

                    <div class="col-12">

                          <div class="card">

                              <div class="card-header">

                                  <h4 class="card-title">Listado de entradas y salidas de los empleados</h4>

                              </div>

                              <div class="card-body">

                                  <div class="table-responsive">

                                      <table id="dynamictable" class="table table-hover table-responsive-sm">

                                        <thead>

                                          <th>Documento</th>

                                          <th>Nombre</th>

                                          <th>Cargo</th>

                                          <th>Entrada</th>

                                          <th>Salida</th>

                                        </thead>

                                        <tbody>

                                          <?php

                                          $c = count($entradas);

                                          $cSalidas = count($salidas);

                                          if ($isvalid) {

                                            for ($i = 0; $i < $c; $i++) {

                                              for ($k = 0; $k < $cSalidas; $k++) {

                                                $fechaSalida = "";



                                                if ($entradas[$i]['cc'] == $salidas[$k]['cc'] && $entradas[$i]['fecha'] == $salidas[$k]['fecha']) {

                                                  $fechaSalida = $salidas[$k]['salida'];

                                                  break;

                                                }

                                              }

                                          ?>

                                                <tr>

                                                  <td><?php echo $entradas[$i]['cc']; ?></td>

                                                  <td><?php echo $entradas[$i]['nombre']; ?></td>

                                                  <td><?php echo $entradas[$i]['cargo']; ?></td>

                                                  <td><?php echo $entradas[$i]['entrada']; ?></td>

                                                  <td><?php echo $fechaSalida; ?></td>

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



    </div>



    <?php include './admin/include/gerenic_footer.php'; ?>

  </div>

  </div>



  <!-- Script -->

  <?php include './admin/include/gerenic_script.php'; ?>

  <!-- Script -->



  <!-- Paginaciòn datatables -->

  <?php include './admin/include/generic_dataTables.php'; ?>

  <!-- Fin Paginaciòn -->



</body>



</html>