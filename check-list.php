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
$modulo = 'Check List';

?>
<!DOCTYPE html>
<html lang="es">
<style>
    
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .form-group {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .checkbox {
            width: 50%!important;
            padding: 10px!important;
            box-sizing: border-box!important;
        }
        
        .form-group label {
            flex: 1;
            margin-right: 10px;
        }
        .form-group select {
            flex: 1;
        }
        .form-group:nth-child(even) {
            background-color: #f9f9f9;
        }
        @media (max-width: 600px) {
            .form-group {
                flex-direction: column;
            }
            .form-group label, .form-group select {
                flex: 1 1 30%;
                margin: 0;
                margin-bottom: 1px;
            }
        }
    </style>

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
            <li class="breadcrumb-item active"><a href="javascript:void(0)">Check List</a></li>
          </ol>
        </div>
        <div class="row">
          <div class="col-12">
            <div class="card">
              <div class="card-header">
                <h4 class="card-title"><?php echo $modulo; ?></h4>
                
              </div>
        
          
              <div class="container">
              <h1 class="card-title">Inspection Form</h1>
        <form action="save_inspection.php" method="POST">
        <div class="form-group">
                  <label class="bmd-label-floating">HOA designated (Name)<b class="errLbl">*</b></label>
                  <select class="form-control" id="tbl_unidad_id" name="tbl_unidad_id">
                    <?php echo $optionUnidades; ?>
                  </select>
                </div>
            <?php
            $items = [
               ('<h2>Individual Floors</h2>'),
                 "Overall appearance",
                "Conditions of walls",
                "Condition of paint",
                "Wall lights working",
                "Ceiling lights working",
                "Condition of Carpet",
                "Unit Exterior Door Clean",
                "Door Spot Lights Working",
                "Exit Sign Lit",
                "Fire Extinguisher Charged",
                ('<h2>Trash Room Floor</h2>'),
                "Shute Door Open/Closes",
                "Free of Storage",
                "Free of Hazardous materials",
                "Shute free of Debris",
                "Inspection Sheet Visible",
                ('<h2>Maintenance Janitoral Room</h2>'),
                "Supplies Properly Stored",
                "Chemical Properly Labeled",
                "Paints Properly Labeled",
                "Fire Extinguisher Charged",
                "Ladders Properly Stored",
                "Free of debris",
                "Inventory Properly Labeled",
                ('<h2>Fire Control Panel Room</h2>'),
                "Equipment tested and working",
                "Inspection Sheet Visible",
                "Free Storage",
                "Free hazardous materials",
                "Fire Extinguisher Charged1",
                ('<h2>Elevators</h2>'),
                "Elevators Working properly",
                "Elevators Doors Clean",
                "Elevators Floors Clean",
                "Elevators Permit Posted",
                "Emergency call Working",
                ('<h2>Water Pump Room</h2>'),
                "Pump Operational",
                "Pump Functioning Properly",
                "Check PDI Gauge",
                "Inspection Sheet Visible",
                "Free storage",
                "Free of hazardous material",
                "Fire Extinguisher Charged",
                ('<h2>Disel Generator</h2>'),
                "Tested Regularly",
                "Fuel level tested",
                "Free of hazardous materials2",
                "Free of storage2",
                "Inspection Sheet Visible2",
                "Fire Extinguisher Charged",
                ('<h2>Common Area A/C Units</h2>'),
                "A/C Units Functional",
                "A/C Filters Clean",
                "Thermostat Properly Set",
                "Interior Vents Clear",
                "Routine Maintenance Schedule",
                "Inspection Sheet Visible",
                "Area Free of Debris",
                "No Standing Water",
                ('<h2>Trash Compactor Room</h2>'),
                "Compactor Functioning",
                "Elevator",
                "Dumpster Attached Correctly",
                "Free Hazardous Materials3",
                "Inspection Sheet Visible3",
                "Shute Free of Debris",
                ('<h2>Roof</h2>'),
                "No Standing Water",
                "Free of Debris",
                "All Doors Secure",
                "Inspection Shingles",
                "Runoff Drains Clear"
            ];

            $count = 0;

            foreach ($items as $item) {
              
                echo '<div class="form-group">';
                echo '<label>' . $item . '</label>';
                echo '<input type="checkbox" name="items[]" value="' . $item . '">';
                echo '</div>';
                $count++;
            }
            ?>
            <button type="submit" class="btn btn-primary btn-sm">Save and</button>
        </form>
    </div>
                  






                </div>
              </div>
            </div>
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

  <?php include './admin/include/gerenic_script.php'; ?>

  <?php include './admin/include/generic_search.php'; ?>

  <script type="text/javascript" src="./admin/js/producto.js"></script>

  <script type="text/javascript" src="./admin/js/detalle_producto.js"></script>

  <?php include './admin/include/generic_dataTables.php'; ?>

</body>



</html>