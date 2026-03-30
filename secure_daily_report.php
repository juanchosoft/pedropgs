<?php
// IMPORTANTE CREAR LA TABLA EN LA DB secure_links si no, no funciona 

// Verificar si el token está presente
if (empty($_GET['token'])) {
    die("Access denied: Token is missing.");
}

// Conectar a la base de datos
$dbHost = 'localhost';
$dbUser = 'u552917860_spiappgs';
$dbPass = 'Martin3933++$$@@**';
$dbName = 'u552917860_spiappgs';


try {
    $db = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8", $dbUser, $dbPass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Buscar el token en la base de datos
    $stmt = $db->prepare("SELECT * FROM secure_links WHERE token = :token AND expires_at > NOW()");
    $stmt->execute([':token' => $_GET['token']]);
    $linkData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$linkData) {
        die("Access denied: Invalid or expired token.");
    }

    // Obtener los datos de la consulta
    $hoa = $linkData['hoa'];
    $f1 = $linkData['f1'];
    $f2 = $linkData['f2'];

    // Incluir clases necesarias
    require './admin/include/generic_classes.php';
    include './admin/classes/DailyReport.php';

    // Consultar el reporte
    $rqst = ['hoa' => $hoa, 'f1' => $f1, 'f2' => $f2];
    $arr = DailyReport::reportListGroupDownload($rqst);

    if (empty($arr['output']['response'])) {
        die("No data found.");
    }

    $data = $arr['output']['response'];
    $dataShow = $data;

    if (count($data) > 0) {
        $data = $data[0];
        $id = $data['id'] ?? '';
        $hoa = $data['nombre'] ?? '';
        $employee = $data['usuario'] ?? '';
        $dtcreate = $data['dtcreate'] ?? '';
        $email = $data['email'] ?? '';
        $manager = $data['administrador'] ?? '';
        $address = $data['ubicacion'] ?? '';
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Mostrar el reporte
?>
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
    <!DOCTYPE html>
    <html lang="es">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Job Report Print</title>
        <style>
            /* Estilos generales para el cuerpo del documento */
            body {
                font-family: Arial, sans-serif; 
                margin: 0; 
                padding: 20px; 
                box-sizing: border-box;
            }

            .card {
                display: flex; 
                flex-direction: column; 
                border: 3px solid; 
                border-image: linear-gradient(to right, red, grey) 1; 
                border-radius: 8px; 
                padding: 20px; 
                margin: 50px 20px; 
                box-sizing: border-box; 
                page-break-inside: avoid; 
                page-break-before: always;
            }

            @page {
                margin: 50px 20px;
                size: A4; /* Tamaño estándar del PDF */
            }

            .page-break {
                page-break-before: always; 
                margin-top:30px;
            }

            
            .header {
                display: flex; 
                align-items: center; 
                justify-content: space-between;
                border-bottom: 5px solid red; 
                padding-bottom: 10px; 
            }

            .header img {
                height: 50px; 
            }

            .header h3 {
                margin: 0; 
                font-size: 1.5rem; 
            }

            
            .table {
                margin-top: 20px; 
                width: 100%; 
                border-collapse: collapse; 
            }

            .table thead th {
                background-color: rgb(248, 248, 248); 
                border: 1px solid #ddd; 
                padding: 8px; 
                text-align: left; 
            }

            .table tbody td {
                border: 1px solid #ddd; 
                padding: 8px; 
            }

            .table tbody tr:nth-child(even) {
                background-color: #f2f2f2; 
            }

            
            .table {
                page-break-inside: avoid; 
            }
            
            .footer {
                text-align: center; 
                margin-top: 20px; 
                font-size: 0.8rem; 
                color: #555; 
            }

            
            .container {
                display: flex; 
                justify-content: center; 
                align-items: flex-start; 
                gap: 30px; 
                margin: 0 auto; 
                text-align: center;
                flex-wrap: wrap; 
            }

            
            .image-container {
                display: flex; 
                flex-direction: column; 
                align-items: flex-start; 
                text-align: center; 
                page-break-inside: avoid; 
                margin-bottom: 30px; 
                width: 300px; 
                height: 400px;
                box-sizing: border-box; 
                position: relative; 
                overflow: hidden;
                padding: 20px 20px 20px 0; 
            }

            
            .image-container img {
                width: 100%; 
                height: calc(100% - 40px); 
                object-fit: cover; 
                margin-top: 20px; 
                margin-bottom: 20px;
                transform: translateX(-60px);
            }

            
            .image-container p {
                margin: 10px 0; 
                font-size: 16px; 
                font-weight: bold; 
                text-transform: uppercase; 
                color: #555; 
            }
            .image-section .table {
                margin-top: 400px; 
            }


            
            .observations {
                width: 100%; 
                box-sizing: border-box; 
                margin-top: 10px; 
                padding: 10px; 
                text-align: left;
                page-break-inside: avoid; 
            }
            .red-background {
                color: black; 
                font-weight: bold; 
                font-size: 20px; 
                text-align: center ;
                margin-left: -5px; 
            }
            
            .content {
                page-break-inside: auto; 
                word-wrap: break-word;
            }
 
        

        </style>

    </head>

    <body>
        <div class="dashboard-wrapper">
            <div class="dashboard-ecommerce">
                <div class="container-fluid dashboard-content">
                    <div class="page-content">
                        <div class="row">
                            <div class="offset-xl-2 col-xl-8 col-lg-12 col-md-12 col-sm-12 col-12">
                                <div class="card">
                                    <div class="card-header p-4">
                                        <img src="assets/img/logo3.png" alt="" width="50%">
                                        <div class="float-right">
                                            <h3 class="mb-0">Job Report No <?= htmlspecialchars($id); ?>
                                            </h3>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row mb-4">
                                            <div class="col-sm-6">
                                                <!-- <div>PGS CENTRUM</div> -->
                                                <div><strong>Jobs report </strong></div>
                                                <div><?= htmlspecialchars($hoa); ?></div>
                                                <div><strong>Manager:</strong> <?= htmlspecialchars($manager); ?></div>
                                                <div><strong>Address:</strong> <?= htmlspecialchars($address); ?></div>
                                                <div><strong>Email:</strong> <?= htmlspecialchars($email); ?></div>

                                            </div>
                                            <div class="col-sm-6">
                                                <div class="page-number"></div>
                                                <div><strong>Version:</strong> 1</div>
                                                <div><strong>Date:</strong> <?= htmlspecialchars($dtcreate); ?></div>
                                            </div>
                                        </div>

                                        <div class="table-responsive-sm">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Date Check</th>
                                                        <th class="right">HOA</th>
                                                        <th class="center">Employee</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td class="left strong"><?= htmlspecialchars($dtcreate); ?></td>
                                                        <td class="left"><?= htmlspecialchars($hoa); ?></td>
                                                        <td class="right"><?= htmlspecialchars($employee); ?></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>



                                        <?php foreach ($dataShow as $data) :
                                            $img = !empty($data["foto_antes"]) && $data["foto_antes"] !== "no_image.png" ? "admin/js/camara/foto/{$data['foto_antes']}" : 'assets/img/logo-spiderP.png';
                                            $imga = !empty($data["foto_despues"]) && $data["foto_despues"] !== "no_image.png" ? "admin/js/camara/foto/{$data['foto_despues']}" : 'assets/img/logo-spiderP.png';
                                        ?>
                                            <hr>
                                            <h5 class="red-background">Job Zone Reported</h5>
                                            <table class="table table-bordered table-sm">
                                                <thead>
                                                    <tr>
                                                        <th class="center">Item</th>
                                                        <th class="center">Date</th>
                                                        <th class="center">Zone</th>
                                                        <th class="center">Activities</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td class="left strong"><?= htmlspecialchars($data['id']); ?></td>
                                                        <td class="left"><?= htmlspecialchars($data['dtcreate']); ?></td>
                                                        <td class="right"><?= htmlspecialchars($data['zone']); ?></td>
                                                        <td class="right"><?= htmlspecialchars($data['actividades']); ?></td>
                                                    </tr>
                                                </tbody>
                                            </table>

                                            <div class="offset-xl-2 col-xl-8 col-lg-12 col-md-12 col-sm-12 col-12">
                                                <h6 class="red-background">Observations</h6>
                                                <p><?= htmlspecialchars($data['observaciones']); ?></p>
                                            </div>

                                            <div class="container">
                                                <div class="image-container">
                                                    <p style="color: black">Before</p>
                                                    <img src="<?= $img ?>" alt="Before Image" class="img-fluid placeholder-image">
                                                </div>
                                                <div class="image-container">
                                                    <p style="color: black">After</p> <!-- Texto en negrilla y negro -->
                                                    <img src="<?= $imga ?>" alt="After Image" class="img-fluid placeholder-image">
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </body>

    <script type="text/javascript">
        window.print();
    </script>

    </html>

    </html>

    </html>