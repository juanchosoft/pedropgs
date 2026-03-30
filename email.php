<?php
// IMPORTANTE CREAR LA TABLA EN LA DB secure_links si no, no funciona 
require './admin/include/generic_classes.php';
include './admin/classes/DailyReport.php';
require_once __DIR__ . '/vendor/autoload.php';



use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Validar campos requeridos
if (empty($_POST['hoa']) || $_POST['hoa'] === "seleccione" || empty($_POST['f1']) || empty($_POST['f2'])) {
    echo "<script>alert('All fields are required'); window.location = 'report-list-group.php';</script>";
    exit;
}

$f1 = $_POST['f1'];
$f2 = $_POST['f2'];
$hoaId = $_POST['hoa'];

// Consultar el reporte
$rqst = ['hoa' => $hoaId, 'f1' => $f1, 'f2' => $f2];
$arr = DailyReport::reportListGroupDownload($rqst);

if (empty($arr['output']['response'])) {
    echo "<script>alert('No results found'); window.location = 'report-list-group.php';</script>";
    exit;
}

$data = $arr['output']['response'];
$hoaName = $data[0]['nombre'] ?? ''; // Obtener el nombre del HOA
$manager = $data[0]['administrador'] ?? '';
$email = $data[0]['email'] ?? '';
$email1 = $data[0]['email1'] ?? '';
$email2 = $data[0]['email2'] ?? '';
$email3 = $data[0]['email3'] ?? '';
$email4 = $data[0]['email4'] ?? '';

// Validar que el nombre del HOA no esté vacío
if (empty($hoaName)) {
    die("Error: Unable to retrieve HOA name.");
}

// Generar un token único
$token = bin2hex(random_bytes(16));

// Guardar el token y los datos en la base de datos
$dbHost = 'localhost';
$dbUser = 'u552917860_spiappgs';
$dbPass = 'Martin3933++$$@@**';
$dbName = 'u552917860_spiappgs';
date_default_timezone_set('America/Sao_Paulo');

try {
    $db = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8", $dbUser, $dbPass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $db->prepare("INSERT INTO secure_links (token, hoa, hoa_name, f1, f2, expires_at) VALUES (:token, :hoa, :hoa_name, :f1, :f2, :expires_at)");
    $stmt->execute([
        ':token' => $token,
        ':hoa' => $hoaId,
        ':hoa_name' => $hoaName,
        ':f1' => $f1,
        ':f2' => $f2,
        ':expires_at' => '2099-12-31 23:59:59'
    ]);
} catch (PDOException $e) {
    die("Database connection error: " . $e->getMessage());
}

// Generar el enlace seguro
$baseURL = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$baseURL .= "://{$_SERVER['HTTP_HOST']}";
$link = $baseURL . "/ap/secure_daily_report.php?token=$token";

// Enviar el correo
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.hostinger.com'; 
    $mail->SMTPAuth = true;
    $mail->Username = 'envios@spidersoftware.co'; 
    $mail->Password = 'Martin3933++$$@@'; 
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    $mail->setFrom('envios@spidersoftware.co', 'Job Report');
    if (!empty($email) && Util::validate_email($email)) {
        $mail->addAddress($email);
    }
    for ($i = 1; $i <= 4; $i++) {
        $emailVar = 'email' . $i;
        if (!empty($$emailVar) && Util::validate_email($$emailVar)) {
            $mail->addCC($$emailVar);
            $emails_sent[] = $$emailVar;
        }
    }

    $mail->isHTML(true);
    $mail->Subject = "Job Report for HOA $hoaName";

    // Estilo para el correo
    $mail->Body = "
    <div style='background-color: #f4f4f9; font-family: Arial, sans-serif; padding: 20px;'>
        <!-- Contenedor principal -->
        <div style='max-width: 500px; margin: auto; background: white; padding: 0; border-radius: 18px; 
                    box-shadow: 0 4px 10px rgba(0,0,0,0.1); 
                    border: 5px solid; 
                    border-image: linear-gradient(to right, grey, red) 1;'><!-- Cambiado max-width a 500px -->
            <!-- Encabezado con imagen de fondo -->
            <div style='position: relative; text-align: center; height: 200px; 
                        background-image: url(\"cid:header_image\"); 
                        background-size: cover; 
                        background-position: center -45px; /* Mueve la imagen hacia arriba es 50 ddsadasd*/
                        border-radius: 10px 10px 0 0;
                        margin-top: -20px;'><!-- Ajustado background-position -->
                <!-- Espacio vacío para desplazar -->
            </div>
            <!-- Contenido principal del correo -->
            <div style='padding: 20px;'>
                <p style='color: #555; font-size: 18px; text-align: center;'>Dear <strong>$manager</strong>,</p>
                <p style='color: #555; font-size: 18px; text-align: center;'>The Job Report for HOA <strong>$hoaName</strong> (From <strong>$f1</strong> to <strong>$f2</strong>) has been generated.</p>
                <p style='color: #555; font-size: 18px; text-align: center;'>For information security purposes, please remember to log in beforehand.</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$link' style='display: inline-block; padding: 15px 25px; font-size: 16px; font-weight: bold; color: white; 
                        background-color: #007bff; text-decoration: none; border-radius: 5px;'>View Report</a>
                    </div>
                        <hr style='border: 0; height: 1px; background: #ddd;'>
                        <p style='color: #999; font-size: 12px; text-align: center;'>If you have any problems viewing the information, please contact technical support.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    ";


    $mail->AddEmbeddedImage('assets/img/pgsinfo.png', 'header_image', 'pgsinfo.png');


    $mail->send();
    echo "<script>alert('Email sent successfully!'); window.location = 'report-list-group.php';</script>";
} catch (Exception $e) {
    echo "<script>alert('Error sending email: {$mail->ErrorInfo}');</script>";
}
?>
