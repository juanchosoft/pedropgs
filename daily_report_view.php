<?php
require './admin/include/generic_classes.php';
include './admin/classes/DailyReport.php';

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

$arr = DailyReport::getAll(NULL);
$isvalid = $arr['output']['valid'];
$arr = $arr['output']['response'];
$modulo = 'Daily Report View';

$arrfecha = DailyReport::getAllfe(NULL);
$isvalid = $arrfecha['output']['valid'];
$arrfecha = $arrfecha['output']['response'];
$modulo = 'Daily Report View';




if (!empty($_GET['reporte']) && isset($_GET['reporte']) && $_GET['reporte'] > 0) {
  $rqst = array('id' => $_GET['reporte']);
  $arr = DailyReport::getAll($rqst);

  $isvalid = $arr['output']['valid'];
  $data = $arr['output']['response'];

  if (count($data) > 0) {

    // Información del cliente y usuario
    $data = $data[0];
    $id = $data['id'] ? $data['id'] : '';
    
    $hoa = isset($data['nombre']) ? ($data['nombre']) : '';
    $employee = isset($data['employee']) ? ($data['employee']) : '';
    $oficio = isset($data['oficio']) ? ($data['oficio']) : '';
    $descripcion = isset($data['descripcion']) ? ($data['descripcion']) : '';
    $dtcreate = isset($data['dtcreate']) ? ($data['dtcreate']) : '';
    $zona = isset($data['zona']) ? ($data['zona']) : '';
    $email = isset($data['email']) ? ($data['email']) : '';
    $manager= isset($data['manager']) ? ($data['manager']) : '';
    $address = isset($data['address']) ? ($data['address']) : '';
    $img    = isset($data['img']) ? ($data['img']) : '';
    $imga   = isset($data['imga']) ? ($data['imga']) : '';
  
  } else {
?>

<script type='text/javascript'>
    alert('Sin resultados');
    window.location = 'daily_report_view.php';
</script>
<?php
    return;
  }
} else { ?>
<script type='text/javascript'>
    alert('You must send a report to generate the document');
    window.location = 'daily_report_view.php';
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
.container {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        .image-container {
            margin: 10px 0;
            padding: 10px;
            border: 2px solid black;
            border-radius: 15px;
            background-color: white;
        }
        .image-container img {
            width: 80%;
            height: auto;
            border-radius: 15px;
        }
        @media (min-width: 768px) {
            .container {
                flex-direction: row;
            }
            .image-container {
                margin: 0 20px;
            }
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
                                   
                                    <div class="float-right"> <h3 class="mb-0">Job Report NO <?php echo $id; ?></h3>
                                   </div>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-4">
                                        <div class="col-sm-6">
                                            <h5 class="mb-3"></h5>                                            
                                            <h3 class="text-dark mb-1"></h3>
                                          
                                            <div>PGS CENTRUM</div>
                                            <div>Jobs Report</div>
                                            <div><?php echo $hoa; ?></div>
                                            <div>Manager: <?php echo $manager; ?></div>
                                            <div>Address: <?php echo $address; ?></div>
                                            <div>Email: <?php echo $email; ?></div>
                                        
                                        </div>
                                        <div class="col-sm-6">
                                            <h5 class="mb-3">   </h5>
                                            <h3 class="text-dark mb-1"> </h3>                                            
                                            <div><strong>Pag.</strong> 1 de 1</div>
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
                                                    <td class="left"><?php echo $hoa; ?></td>
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
                                <?php
                                

                                $img = $img !== null && $img !== "" && $img !== "no_image.png" ? "assets/img/admin/" . $img : 'assets/img/logo-spiderP.png';     
                             $imga = $imga !== null && $imga !== "" && $imga !== "no_image.png" ? "assets/img/admin/" . $imga : 'assets/img/logo-spiderP.png';     ?>  
                                <h5 class="red-background">Job Zone Reported</h5> 
                                <table class="table table-bordered table-sm">                               
                                <tbody>
                                    <tr>
                                    <td class="texto1">Zone</td>
                                    <td><?php echo $zona; ?></td>
                                    <td class="texto1">Work Performed</td>
                                    <td><?php echo $oficio ?></td>
                                 
                                    </tbody>
                                    </table>
                                    <div class="offset-xl-2 col-xl-8 col-lg-12 col-md-12 col-sm-12 col-12">
                                        <h6 class="red-background">Description of the work performed</h6>
                                        <p><?php echo $descripcion; ?></p>
                                    </div>
<br>
<hr>

                                    <div class="container">
                                        <div class="image-container">
                                            <h3>Before</h3>
                                            <img src="<?= $img ?>" alt="" class="img-fluid">
                                        </div>
                                        <div class="image-container">
                                            <h3>After</h3>
                                            <img src="<?= $imga ?>" alt="" class="img-fluid">
                                        </div>
                                    </div>
    

            
                    </body>
                                        <script type="text/javascript">
                                        window.print();
                                        </script>
                                        </html>