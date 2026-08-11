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

$f1 = trim((string) $_POST['f1']);
$f2 = trim((string) $_POST['f2']);
$hoaId = trim((string) $_POST['hoa']);

// Consultar el reporte
$rqst = ['hoa' => $hoaId, 'f1' => $f1, 'f2' => $f2];
$arr = DailyReport::reportListGroupDownload($rqst);

if (empty($arr['output']['response'])) {
    echo "<script>alert('No results found'); window.location = 'report-list-group.php';</script>";
    exit;
}

$data = $arr['output']['response'];
$hoaName = $data[0]['nombre'] ?? '';
$manager = $data[0]['administrador'] ?? '';
$email = trim((string) ($data[0]['email'] ?? ''));
$email1 = trim((string) ($data[0]['email1'] ?? ''));
$email2 = trim((string) ($data[0]['email2'] ?? ''));
$email3 = trim((string) ($data[0]['email3'] ?? ''));
$email4 = trim((string) ($data[0]['email4'] ?? ''));

$employeesUnique = [];
foreach ($data as $rowEmp) {
    $empName = trim((string) ($rowEmp['usuario'] ?? ''));
    if ($empName !== '') {
        $employeesUnique[$empName] = $empName;
    }
}
$employeesList = implode(', ', array_values($employeesUnique));
$jobsCount = count($data);

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

// Generar el enlace seguro (misma carpeta que email.php)
$baseURL = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseURL .= '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim($scriptDir, '/');
$link = $baseURL . $scriptDir . '/secure_daily_report.php?token=' . urlencode($token);

// Enviar el correo
$mail = new PHPMailer(true);
$emails_sent = [];
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.hostinger.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'envios@spidersoftware.co';
    $mail->Password = 'Martin3933++$$@@';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';

    $mail->setFrom('envios@spidersoftware.co', 'Job Report');

    $hasRecipient = false;
    if (!empty($email) && Util::validate_email($email)) {
        $mail->addAddress($email);
        $emails_sent[] = $email;
        $hasRecipient = true;
    }
    for ($i = 1; $i <= 4; $i++) {
        $emailVar = 'email' . $i;
        if (!empty($$emailVar) && Util::validate_email($$emailVar)) {
            $mail->addCC($$emailVar);
            $emails_sent[] = $$emailVar;
            $hasRecipient = true;
        }
    }

    if (!$hasRecipient) {
        echo "<script>alert('No valid email recipients configured for this HOA.'); window.location = 'report-list-group.php';</script>";
        exit;
    }

    $mail->isHTML(true);
    $mail->Subject = "Job Report for HOA $hoaName";

    $employeesHtml = htmlspecialchars($employeesList !== '' ? $employeesList : 'N/A', ENT_QUOTES, 'UTF-8');
    $hoaNameHtml = htmlspecialchars($hoaName, ENT_QUOTES, 'UTF-8');
    $managerHtml = htmlspecialchars($manager, ENT_QUOTES, 'UTF-8');
    $f1Html = htmlspecialchars($f1, ENT_QUOTES, 'UTF-8');
    $f2Html = htmlspecialchars($f2, ENT_QUOTES, 'UTF-8');
    $linkHtml = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

    $mail->Body = "
    <div style='background-color: #f4f4f9; font-family: Arial, sans-serif; padding: 20px;'>
        <div style='max-width: 500px; margin: auto; background: white; padding: 0; border-radius: 18px; 
                    box-shadow: 0 4px 10px rgba(0,0,0,0.1); 
                    border: 5px solid; 
                    border-image: linear-gradient(to right, grey, red) 1;'>
            <div style='position: relative; text-align: center; height: 200px; 
                        background-image: url(\"cid:header_image\"); 
                        background-size: cover; 
                        background-position: center -45px;
                        border-radius: 10px 10px 0 0;
                        margin-top: -20px;'>
            </div>
            <div style='padding: 20px;'>
                <p style='color: #555; font-size: 18px; text-align: center;'>Dear <strong>{$managerHtml}</strong>,</p>
                <p style='color: #555; font-size: 18px; text-align: center;'>The Job Report for HOA <strong>{$hoaNameHtml}</strong> (From <strong>{$f1Html}</strong> to <strong>{$f2Html}</strong>) has been generated.</p>
                <p style='color: #555; font-size: 15px; text-align: center;'><strong>Jobs:</strong> {$jobsCount}<br><strong>Employees:</strong> {$employeesHtml}</p>
                <p style='color: #555; font-size: 18px; text-align: center;'>For information security purposes, please remember to log in beforehand.</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$linkHtml}' style='display: inline-block; padding: 15px 25px; font-size: 16px; font-weight: bold; color: white; 
                        background-color: #007bff; text-decoration: none; border-radius: 5px;'>View Report</a>
                </div>
                <hr style='border: 0; height: 1px; background: #ddd;'>
                <p style='color: #999; font-size: 12px; text-align: center;'>If you have any problems viewing the information, please contact technical support.</p>
            </div>
        </div>
    </div>
    ";

    $mail->AddEmbeddedImage('assets/img/pgsinfo.png', 'header_image', 'pgsinfo.png');

    $mail->send();
    echo "<script>alert('Email sent successfully!'); window.location = 'report-list-group.php';</script>";
} catch (Exception $e) {
    echo "<script>alert('Error sending email: " . addslashes($mail->ErrorInfo) . "'); window.location = 'report-list-group.php';</script>";
}
?>
