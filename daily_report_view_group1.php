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


if (!empty($_GET['hoa']) && isset($_GET['hoa']) && $_GET['hoa'] > 0) {
    $rqst = array('hoa' => $_GET['hoa'], 'place' => $_GET['place'], 'date' => $_GET['date']);
    $arr = DailyReport::getAllfe($rqst);

    $isvalid = $arr['output']['valid'];
    $data = $arr['output']['response'];
    $dataShow = $data;

    if (count($data) > 0) {

        $data = $data[0];
        $id = $data['id'] ? $data['id'] : '';
        $hoa = isset($data['nombre']) ? ($data['nombre']) : '';
        $employee = isset($data['employee']) ? ($data['employee']) : '';
        $dtcreate = isset($data['dtcreate']) ? ($data['dtcreate']) : '';
        $email = isset($data['email']) ? ($data['email']) : '';
        $manager = isset($data['administrador']) ? ($data['administrador']) : '';
        $address = isset($data['ubicacion']) ? ($data['ubicacion']) : '';
    } else {
?>
        <script type='text/javascript'>
            alert('Sin resultados');
            window.location = 'daily_report_group.php';
        </script>
    <?php
        return;
    }
} else { ?>
    <script type='text/javascript'>
        alert('You must send a report to generate the document');
        window.location = 'daily_report_group.php';
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
            color: white;
            text-transform: uppercase;
            text-align: center;
            font-weight: bold;
            font-size: 90%;
        }

        .texto {
            color: #FFFFFF !important;
            font-weight: bold;
            text-shadow: 1px 1px 2px #000000;
        }

        .texto1 {
            color: #000000 !important;
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
        img.max-size {
    max-width: 390px;
    max-height: 377px;
    width: 50%;
    height: auto;
    object-fit: contain; /* Ajusta la imagen sin distorsión */
}
/* Tamaño de página y márgenes */
@page {
    size: 8.5in 11in;
    margin: 1cm; /* Puedes ajustar los márgenes según tus necesidades */
}


    </style>

<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title></title>
    <link rel="stylesheet" href="styles.css"> <!-- Asegúrate de enlazar tu archivo CSS -->
</head>
<body>
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
                                <img src="assets/img/logo3.png" alt="" width="15%">

                                <div class="float-right">
                                    <h6 class="mb-0">Job Report NO <?php echo $id; ?></h6>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row mb-4">
                                    <div class="col-sm-6">
                                        <h5 class="mb-3"></h5>
                                       <h3 class="text-dark mb-1"></h3>
                                        <div><?php echo $hoa; ?></div>
                                        <div>Manager: <?php echo $manager; ?></div>
                                        <div>Address: <?php echo $address; ?></div>
                                        <div>Email: <?php echo $email; ?></div>
                                    </div>
                                    <div class="col-sm-6">
                                        <h5 class="mb-3"> </h5>
                                        <h3 class="text-dark mb-1"> </h3>
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
                                    <div class="col-lg-4 col-sm-5"> </div>
                                    <div class="col-lg-4 col-sm-5 ml-auto"></div>
                                </div>
                                <?php
                                $c = count($dataShow);
                                if ($isvalid) {
                                    for ($i = 0; $i < $c; $i++) {

                                        $img = $dataShow[$i]["img"];
                                        $img = $img !== null && $img !== "" && $img !== "no_image.png" ? "assets/img/admin/" . $img : 'assets/img/logo1.png';

                                        $imga = $dataShow[$i]["imga"];
                                        $imga = $imga !== null && $imga !== "" && $imga !== "no_image.png" ? "assets/img/admin/" . $imga : 'assets/img/logo1.png';

                                ?>  
                                <br>                                 
                                        <h5 class="red-background">Job Zone Reported</h5>
                                     
                                        <table class="table table-bordered table-sm">
                                            <tbody>
                                                <tr>
                                                    <td class="texto1">Item</td>
                                                    <td><?php echo $dataShow[$i]['id']; ?></td>
                                                    <td class="texto1">Zone</td>
                                                    <td><?php echo $dataShow[$i]['zona']; ?></td>
                                                    <td class="texto1">Work Performed</td>
                                                    <td><?php echo $dataShow[$i]['oficio']; ?></td>
                                            </tbody> 
                                        </table>
                                        <div class="offset-xl-2 col-xl-8 col-lg-12 col-md-12 col-sm-12 col-12">
                                            <h6 class="red-background">Description of the work performed</h6>
                                            <p><?php echo $dataShow[$i]['descripcion']; ?></p>
                                        </div>
                                         <div class="container">
                                            <div class="image-container">
                                                <h6>Before</h6>
                                                <img src="<?= $img ?>" alt="" class="img-fluid max-size" >
                                            </div>
                                            <div class="image-container">
                                                <h6>After</h6>
                                                <img src="<?= $imga ?>" alt="" class="img-fluid max-size">
                                            </div>
                                        </div>
                                <?php
                                    }
                                }
                                ?>
                               
                            </div>
                        </div>
                    </div>
  
                </div>
            </div>
        </div>
    </div>
   
</body>
<!-- <script type="text/javascript">
        window.print();
</script> -->
</html>