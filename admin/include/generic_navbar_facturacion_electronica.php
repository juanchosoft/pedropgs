<nav class="navbar navbar-expand-lg navbar-transparent navbar-absolute fixed-top ">
              <div class="container-fluid">
                <div class="navbar-wrapper navCaja">
                  <a class="navbar-brand" href="javascript:;">Sistema POS</a>
                  <!-- <a class="caja-salir btn " style="display:none" href="main.php">Salir al Menu Principal</a> -->
                  
                  <!-- <a class="caja-cerrar btn " style="display:none"  data-target="#myModalcerrar" data-toggle="modal" data-backdrop="static" data-keyboard="false">Cerrar Caja</a> -->
                  <!-- <a class="caja-totalventa btn " style="display:none" href="#">Total Venta</a> -->
                </div>
                <button class="navbar-toggler" type="button" data-toggle="collapse" aria-controls="navigation-index" aria-expanded="false" aria-label="Toggle navigation">
                  <span class="sr-only">Toggle navigation</span>
                  <span class="navbar-toggler-icon icon-bar"></span>
                  <span class="navbar-toggler-icon icon-bar"></span>
                  <span class="navbar-toggler-icon icon-bar"></span>
                </button>
                <div class="collapse navbar-collapse justify-content-end">

                  <ul class="navbar-nav">
                      <a href="#" class="btn btn-danger box-caja">Facturación Electrónica</a>
                      <a href="main.php" target="_blank" class="btn btn-block box-caja">Menu Principal</a>
                    <div>
                    <li>
                        <center><a href="https://api.whatsapp.com/send?phone=57322607-1772&text=Hola!%20i%20Solicito%20un%20soporte%20para%20la%20aplicación%20del%20POS%20%20!" target="_blank" title="Whatsapp">
                        <i class="material-icons" style="color:#000000";>support</i>
                            <p style="color:#000000";><strong>Soporte On Line</strong></p>
                      </li>
                      </div>
                      <br>
                        <div class="logo">
                      <a class="simple-text logo-mini" href="#">
                        <div class="logo-img">
                          <img src="<?php echo SessionData::getAvatar(); ?>" width="40px">
                        </div>
                      </a>
                    </div>
                    
                    <li class="nav-item dropdown">
                      <a class="nav-link" href="javascript:;" id="navbarDropdownProfile" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="material-icons">person</i>
                        <p class="d-lg-none d-md-block">
                          Account
                        </p>
                        Bienvenido: <?php echo SessionData::getUserFullName(); ?>

                      </a>
                      <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdownProfile">
                        <a class="dropdown-item" href="#">Perfil</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="logout.php">Salir</a>
                      </div>
                    </li>
                  </ul>
                </div>
              </div>
            </nav>

            