<?php
require './admin/include/generic_classes.php';
include './admin/classes/Check.php';

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



if (!empty($_GET['report']) && isset($_GET['report']) && $_GET['report'] > 0) {
    $rqst = array('id' => $_GET['report']);
    $arr = Check::getAll($rqst);
  
    $isvalid = $arr['output']['valid'];
    $data = $arr['output']['response'];
    $modulo = 'Show Check Report';

  if (count($data) > 0) {

    // Información del cliente y usuario
    $data = $data[0];
    $id = $data['id'] ? $data['id'] : '';
    
    $unidad = isset($data['hoa']) ? ($data['hoa']) : '';
    $employee = isset($data['employee']) ? ($data['employee']) : '';
    $overall_appearance = isset($data['overall_appearance']) ? ($data['overall_appearance']) : '';
    $condition_walls = isset($data['condition_walls']) ? ($data['condition_walls']) : '';
    $condition_paint = isset($data['condition_paint']) ? ($data['condition_paint']) : '';
    $wall_lights = isset($data['wall_lights']) ? ($data['wall_lights']) : '';
    $ceiling_lights = isset($data['ceiling_lights']) ? ($data['ceiling_lights']) : '';
    $carpet = isset($data['carpet']) ? ($data['carpet']) : '';
    $door_clean = isset($data['door_clean']) ? ($data['door_clean']) : '';
    $spot_lights = isset($data['spot_lights']) ? ($data['spot_lights']) : '';
    $lit = isset($data['lit']) ? ($data['lit']) : '';
    $extinguisher_charged = isset($data['extinguisher_charged']) ? ($data['extinguisher_charged']) : '';
    $shute_door = isset($data['shute_door']) ? ($data['shute_door']) : '';
    $free_storage = isset($data['free_storage']) ? ($data['free_storage']) : '';
    $hazardous_materials = isset($data['hazardous_materials']) ? ($data['hazardous_materials']) : '';
    $inspection_visible1 = isset($data['inspection_visible1']) ? ($data['inspection_visible1']) : '';
    $supplies_stored = isset($data['supplies_stored']) ? ($data['supplies_stored']) : '';
    $debris = isset($data['debris']) ? ($data['debris']) : '';
    $chemical_labeled = isset($data['chemical_labeled']) ? ($data['chemical_labeled']) : '';
    $paint_labeled = isset($data['paint_labeled']) ? ($data['paint_labeled']) : '';
    $fire_charged = isset($data['fire_charged']) ? ($data['fire_charged']) : '';
    $ladders_stored = isset($data['ladders_stored']) ? ($data['ladders_stored']) : '';
    $debrisj = isset($data['debrisj']) ? ($data['debrisj']) : '';
    $inventory_labeled = isset($data['inventory_labeled']) ? ($data['inventory_labeled']) : '';
    $equipment_tested = isset($data['equipment_tested']) ? ($data['equipment_tested']) : '';
    $inspectionf = isset($data['inspectionf']) ? ($data['inspectionf']) : '';
    $storagef = isset($data['storagef']) ? ($data['storagef']) : '';
    $hazardousf = isset($data['hazardousf']) ? ($data['hazardousf']) : '';
    $chargedf = isset($data['chargedf']) ? ($data['chargedf']) : '';
    $elevators_working = isset($data['elevators_working']) ? ($data['elevators_working']) : '';
    $doors_clean = isset($data['debris']) ? ($data['debris']) : '';
    $floors_clean = isset($data['floors_clean']) ? ($data['floors_clean']) : '';
    $permit_posted = isset($data['permit_posted']) ? ($data['permit_posted']) : '';
    $call_working = isset($data['call_working']) ? ($data['call_working']) : '';
    $pump_operational = isset($data['pump_operational']) ? ($data['pump_operational']) : '';
    $pump_functioning = isset($data['pump_functioning']) ? ($data['pump_functioning']) : '';
    $check_gauge = isset($data['check_gauge']) ? ($data['check_gauge']) : '';
    $visiblep = isset($data['visiblep']) ? ($data['visiblep']) : '';
    $storagep = isset($data['storagep']) ? ($data['storagep']) : '';
    $materialp = isset($data['materialp']) ? ($data['materialp']) : '';
    $extinguisherp = isset($data['extinguisherp']) ? ($data['extinguisherp']) : '';
    $tested = isset($data['tested']) ? ($data['tested']) : '';
    $fuel_level = isset($data['fuel_level']) ? ($data['fuel_level']) : '';
    $materialsd = isset($data['materialsd']) ? ($data['materialsd']) : '';
    $storaged = isset($data['storaged']) ? ($data['storaged']) : '';
    $inspectiond = isset($data['inspectiond']) ? ($data['inspectiond']) : '';
    $extinguisherd = isset($data['extinguisherd']) ? ($data['extinguisherd']) : '';
    $ac_units = isset($data['ac_units']) ? ($data['ac_units']) : '';
    $acfilters = isset($data['acfilters']) ? ($data['acfilters']) : '';
    $thermostat_properly = isset($data['thermostat_properly']) ? ($data['thermostat_properly']) : '';
    $interior_clear = isset($data['interior_clear']) ? ($data['interior_clear']) : '';
    $maintenance_schedule = isset($data['maintenance_schedule']) ? ($data['maintenance_schedule']) : '';
    $visibleac = isset($data['visibleac']) ? ($data['visibleac']) : '';
    $debrisac = isset($data['debrisac']) ? ($data['debrisac']) : '';
    $no_water = isset($data['no_water']) ? ($data['no_water']) : '';
    $compactor_functioning = isset($data['compactor_functioning']) ? ($data['compactor_functioning']) : '';
    $elevator = isset($data['elevator']) ? ($data['elevator']) : '';
    $dumpster_correctly = isset($data['dumpster_correctly']) ? ($data['dumpster_correctly']) : '';
    $inspection_visible = isset($data['inspection_visible']) ? ($data['inspection_visible']) : '';
    $materialst = isset($data['materialst']) ? ($data['materialst']) : '';
    $debrist = isset($data['debrist']) ? ($data['debrist']) : '';
    $doors_secure = isset($data['doors_secure']) ? ($data['doors_secure']) : '';
    $inspection_shingles = isset($data['inspection_shingles']) ? ($data['inspection_shingles']) : '';
    $drains_clear = isset($data['drains_clear']) ? ($data['drains_clear']) : '';
    $debrisr = isset($data['debrisr']) ? ($data['debrisr']) : '';
    $dtcreate = isset($data['dtcreate']) ? ($data['dtcreate']) : '';
    $observations = isset($data['observations']) ? ($data['observations']) : '';
  
  } else {
?>
<script type='text/javascript'>
    alert('Sin resultados');
    window.location = 'check_report_list.php';
</script>
<?php
    return;
  }
} else { ?>
<script type='text/javascript'>
    alert('You must send a report to generate the document');
    window.location = 'check_report_list.php';
</script>
<?php
  return;
}
?>




 <!-- Bootstrap CSS -->
 <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- DataTables Bootstrap 4 CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/2.0.3/css/dataTables.bootstrap4.min.css">
    <!-- DataTables Select Bootstrap 4 CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/select/2.0.0/css/select.bootstrap4.min.css">
    <!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.js"></script>

<!-- Popper.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js"></script>

<!-- Bootstrap -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.5.2/js/bootstrap.min.js"></script>

<!-- DataTables -->
<script src="https://cdn.datatables.net/2.0.3/js/dataTables.js"></script>
<script src="https://cdn.datatables.net/2.0.3/js/dataTables.bootstrap4.js"></script>

<!-- DataTables Select -->
<script src="https://cdn.datatables.net/select/2.0.0/js/dataTables.select.js"></script>
<script src="https://cdn.datatables.net/select/2.0.0/js/select.bootstrap4.js"></script>

<body>
    <style>
.red-background {
    background-color: red;
    color:white;
    text-transform: uppercase;
    text-align: center;   
    font-weight: bold; 
    font-size:90%;
}
.texto{
    color: #FFFFFF!important;
    font-weight: bold;
    text-shadow: 1px 1px 2px #000000;
}
.texto1{
    color: #000000!important;
    font-weight: bold;
  
}
    </style>

        <div class="dashboard-wrapper">
            <div class="dashboard-ecommerce">
                <div class="container-fluid dashboard-content ">
                    <!-- ============================================================== -->
                    <!-- pageheader  -->
                    <!-- ============================================================== -->
         
                    <!-- ============================================================== -->
                    <!-- end pageheader  -->
                    <!-- ============================================================== -->
                    <div class="row">
                        <div class="offset-xl-2 col-xl-8 col-lg-12 col-md-12 col-sm-12 col-12">
                            <div class="card">
                                <div class="card-header p-4">
                                <img src="assets/img/logo3.png" alt="" width="20%">
                                   
                                    <div class="float-right"> <h3 class="mb-0">Check Report NO <?php echo $id; ?></h3>
                                   </div>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-4">
                                        <div class="col-sm-6">
                                            <h5 class="mb-3"></h5>                                            
                                            <h3 class="text-dark mb-1"></h3>
                                          
                                            <div>PGS CENTRUM</div>
                                            <div>CHEK REPORT</div>
                                            <div><?php echo $unidad; ?></div>
                                        
                                        </div>
                                        <div class="col-sm-6">
                                            <h5 class="mb-3">   </h5>
                                            <h3 class="text-dark mb-1"> </h3>                                            
                                            <div><strong>Pag.</strong> 1 de 2</div>
                                            <div><strong>Version:</strong> 1</div>
                                            <div><strong>Date:</strong> <?php echo $dtcreate; ?> </div>
                                        </div>
                                    </div>
                                    <div class="table-responsive-sm">
                                        <table class="table table-striped">
                                            <thead>
                                                     <th>Date Check</th>
                                                    <th class="right">HOA</th>
                                                    <th class="center">Employee</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>                                              
                                                    <td class="left strong"><?php echo $dtcreate; ?></td>
                                                    <td class="left"><?php echo $unidad; ?></td>
                                                    <td class="right"><?php echo $employee; ?></td>
                                            
                                                </tr>
                                               
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="row">
                                        <div class="col-lg-4 col-sm-5">
                                        </div>
                                        <div class="col-lg-4 col-sm-5 ml-auto">
                                            
                                    </div>
                                </div>
                                <h5 class="red-background">Individual Floors</h5> 
                                <table class="table table-bordered table-sm">                               
                                <tbody>
                                    <tr>
                                    <td>Overall appearance</td>
                                    <td><?php echo $overall_appearance; ?></td>
                                    <td>Conditions of walls</td>
                                    <td><?php echo $condition_walls; ?></td>
                                    </tr>
                                    <tr>
                                    <td>Condition of paint</td>
                                    <td><?php echo $condition_paint; ?></td>
                                    <td>Wall lights working</td>
                                    <td><?php echo $wall_lights; ?></td>
                                    </tr>
                                    <tr>
                                    <td>Ceiling lights working</td>
                                    <td><?php echo $ceiling_lights; ?></td>
                                    <td>Condition of Carpet</td>
                                    <td><?php echo $carpet; ?></td>
                                    </tr>
                                    <tr>
                                    <td>Unit Exterior Door Clean</td>
                                    <td><?php echo $door_clean; ?></td>
                                    <td>Door Spot Lights Working</td>
                                    <td><?php echo $spot_lights; ?></td>
                                    </tr>
                                    <tr>
                                    <td>Exit Sign Lit</td>
                                    <td><?php echo $lit; ?></td>
                                    <td></td>
                                    <td></td>
                                    </tr>
                                </tbody>
                                </table>
                                <h5 class="red-background">Trash Room Floor</h5> 
                                <table class="table table-bordered table-sm">                                 
                                <tbody>
                                    <tr>
                                    <td>Shute Door Open/Closes</td>
                                    <td><?php echo $free_storage; ?></td>
                                    <td>Free of Storage</td>
                                    <td><?php echo $free_storage; ?></td>
                                    </tr>
                                    <tr>
                                    <td>Free of Hazardous materials</td>
                                    <td><?php echo $hazardous_materials; ?></td>
                                    <td>Shute free of Debris</td>
                                    <td><?php echo $debris; ?></td>
                                    </tr>
                                    <tr>
                                    <td>Inspection Sheet Visible</td>
                                    <td><?php echo $inspection_visible1; ?></td>
                                    <td>Condition of Carpet</td>
                                    <td><?php echo $carpet; ?></td>
                                    </tr>
                                  </tbody>
                                </table>
                                <h5 class="red-background">Maintenance Janitoral Room</h5> 
                                <table class="table table-bordered table-sm">                                 
                                <tbody>
                                    <tr>
                                    <td>Supplies Properly Stored</td>
                                    <td><?php echo $supplies_stored; ?></td>
                                    <td>Chemical Properly Labeled</td>
                                    <td><?php echo $chemical_labeled; ?></td>
                                    </tr>
                                    <tr>
                                    <td>Paints Properly Labeled</td>
                                    <td><?php echo $fire_charged; ?></td>
                                    <td>Ladders Properly Stored</td>
                                    <td><?php echo $ladders_stored; ?></td>
                                    </tr>
                                    <tr>
                                    <td>Free of debris</td>
                                    <td><?php echo $debrisj; ?></td>
                                    <td>Inventory Properly Labeled</td>
                                    <td><?php echo $inventory_labeled; ?></td>
                                    </tr>
                                   
                                </tbody>
                                </table>

                                <h5 class="red-background">Fire Control Panel Room</h5> 
                                <table class="table table-bordered table-sm">                                 
                                <tbody>
                                    <tr>
                                    <td>Equipment tested and working</td>
                                    <td><?php echo $supplies_stored; ?></td>
                                    <td>Free Storage</td>
                                    <td><?php echo $storagef; ?></td>
                                    </tr>
                                    <tr>
                                    <td>Inspection Sheet Visible</td>
                                    <td><?php echo $inspectionf; ?></td>
                                    <td>Free hazardous materials</td>
                                    <td><?php echo $fire_charged; ?></td>
                                    </tr>
                                    <tr>
                                    <td>Fire Extinguisher Charged</td>
                                    <td><?php echo $chargedf; ?></td>
                                    <td></td>
                                    <td></td>
                                    </tr>
                                   
                                </tbody>
                                </table>


                                <h5 class="red-background">Elevators</h5> 
                                <table class="table table-bordered table-sm">                                 
                                <tbody>
                                    <tr>
                                    <td>Elevators Working properly</td>
                                    <td><?php echo $elevators_working; ?></td>
                                    <td>Elevators Doors Clean</td>
                                    <td><?php echo $doors_clean; ?></td>
                                    </tr>
                                    <tr>
                                    <td>Elevators Floors Clean</td>
                                    <td><?php echo $floors_clean; ?></td>
                                    <td>Elevators Permit Posted</td>
                                    <td><?php echo $permit_posted; ?></td>
                                    </tr>
                                    <tr>
                                    <td>Emergency call Working</td>
                                    <td><?php echo $call_working; ?></td>
                                    <td></td>
                                    <td></td>
                                    </tr>
                                   
                                </tbody>
                                </table>


                                <h5 class="red-background">Water Pump Room</h5> 
                                <table class="table table-bordered table-sm">                                 
                                <tbody>
                                    <tr>
                                    <td>Pump Operational</td>
                                    <td><?php echo $pump_operational; ?></td>
                                    <td>Pump Functioning Properly</td>
                                    <td><?php echo $doors_clean; ?></td>
                                    </tr>
                                    <tr>
                                    <td>Pump Functioning Properly</td>
                                    <td><?php echo $pump_functioning; ?></td>
                                    <td>Check PDI Gauge</td>
                                    <td><?php echo $check_gauge; ?></td>
                                    </tr>
                                    <tr>
                                    <td>Inspection Sheet Visible</td>
                                    <td><?php echo $visiblep; ?></td>
                                    <td>Free storage</td>
                                    <td><?php echo $storagep; ?></td>
                                    </tr>
                                    <tr>
                                    <td>Free of hazardous material</td>
                                    <td><?php echo $materialp; ?></td>
                                    <td>Fire Extinguisher Charged</td>
                                    <td><?php echo $extinguisherp; ?></td>
                                    </tr>
                                    <tr>
                                   
                                </tbody>
                                </table>

                                <h5 class="red-background">Disel Generator</h5> 
                                <table class="table table-bordered table-sm">                                 
                                <tbody>
                                    <tr>
                                    <td>Tested Regularly</td>
                                    <td><?php echo $tested; ?></td>
                                    <td>Fuel level tested</td>
                                    <td><?php echo $fuel_level; ?></td>
                                    </tr>
                                    <tr>
                                    <td>Free of hazardous materials</td>
                                    <td><?php echo $materialsd; ?></td>
                                    <td>Free of storage</td>
                                    <td><?php echo $storaged; ?></td>
                                    </tr>
                                    <tr>
                                    <td>Inspection Sheet Visible</td>
                                    <td><?php echo $inspectiond; ?></td>
                                    <td>Fire Extinguisher Charge</td>
                                    <td><?php echo $extinguisherd; ?></td>
                                    </tr>
                                   
                                </tbody>
                                </table>


                                <h5 class="red-background">Common Area A/C Units</h5> 
                                <table class="table table-bordered table-sm">                                 
                                <tbody>
                                    <tr>
                                    <td>A/C Units Functional</td>
                                    <td><?php echo $ac_units; ?></td>
                                    <td>A/C Filters Clean</td>
                                    <td><?php echo $thermostat_properly; ?></td>
                                    </tr>
                                    <tr>
                                    <td>Routine Maintenance Schedule</td>
                                    <td><?php echo $interior_clear; ?></td>
                                    <td>Free of hazardous materials</td>
                                    <td><?php echo $maintenance_schedule; ?></td>
                                    </tr>
                                    <tr>
                                    <td>Inspection Sheet Visible</td>
                                    <td><?php echo $visibleac; ?></td>
                                    <td>Area Free of Debris</td>
                                    <td><?php echo $debrisac; ?></td>
                                    </tr>
                                    <tr>
                                    <td>No Standing Water</td>
                                    <td><?php echo $no_water; ?></td>
                                    <td></td>
                                    <td></td>
                                    </tr>
                                   
                                </tbody>
                                </table>
                                <h5 class="red-background">Trash Compactor Room</h5> 
                                <table class="table table-bordered table-sm">                                 
                                <tbody>
                                    <tr>
                                    <td>Compactor Functioning</td>
                                    <td><?php echo $compactor_functioning; ?></td>
                                    <td>A/C Filters Clean</td>
                                    <td><?php echo $thermostat_properly; ?></td>
                                    </tr>
                                    <tr>
                                    <td>Elevator</td>
                                    <td><?php echo $elevator; ?></td>
                                    <td>Free of hazardous materials</td>
                                    <td><?php echo $maintenance_schedule; ?></td>
                                    </tr>
                                    <tr>
                                    <td>Dumpster Attached Correctly</td>
                                    <td><?php echo $dumpster_correctly ?></td>
                                    <td>Free Hazardous Materials</td>
                                    <td><?php echo $materialst; ?></td>
                                    </tr>
                                    <tr>
                                    <td>Inspection Sheet Visible</td>
                                    <td><?php echo $inspection_visible; ?></td>
                                    <td>Shute Free of Debris</td>
                                    <td><?php echo $debrist; ?></td>
                                    </tr>
                                   
                                </tbody>
                                </table>

                                <h5 class="red-background">Roof</h5> 
                                <table class="table table-bordered table-sm">                                 
                                <tbody>
                                    <tr>
                                    <td>No Standing Water</td>
                                    <td><?php echo $no_water; ?></td>
                                    <td>Free of Debris</td>
                                    <td><?php echo $debrisr; ?></td>
                                    </tr>
                                    <tr>
                                    <td>All Doors Secure</td>
                                    <td><?php echo $doors_secure; ?></td>
                                    <td>Inspection Shingles</td>
                                    <td><?php echo $inspection_shingles; ?></td>
                                    </tr>
                                    <tr>
                                    <td>Runoff Drains Clear</td>
                                    <td><?php echo $drains_clear; ?></td>
                                    <td></td>
                                    <td></td>
                                    </tr>
                                    </tbody>
                                    </table>
                                    <div class="offset-xl-2 col-xl-8 col-lg-12 col-md-12 col-sm-12 col-12">
                                        <h6 class="red-background">Observations</h6>
                                        <p><?php echo $observations; ?></p>
                                    </div>
                                   
                                < </body>
                                        <script type="text/javascript">
                                        window.print();
                                        </script>
                                        </html>