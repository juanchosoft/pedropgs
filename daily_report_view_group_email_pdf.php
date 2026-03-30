
<?php
require './admin/include/generic_classes.php';
include './admin/classes/DailyReport.php';
require_once __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$errors = [];

// Validación de campos obligatorios
if (empty($_POST['hoa'])) {
    $errors[] = "The field 'HOA' is required.";
}
if (empty($_POST['f1'])) {
    $errors[] = "The field 'Date 1' is required.";
}
if (empty($_POST['f2'])) {
    $errors[] = "The field 'Date 2' is required.";
}

if (!empty($errors)) {
?>
    <script type='text/javascript'>
        alert('All fields are required');
        window.location = 'daily_report_view_group.php';
    </script>
    <?php
}

if (!empty($_POST['hoa']) && isset($_POST['hoa']) && $_POST['hoa'] > 0) {
    $f1 = $_POST['f1'];
    $f2 = $_POST['f2'];
    $rqst = ['hoa' => $_POST['hoa'], 'f1' => $_POST['f1'], 'f2' => $_POST['f2']];
    $arr = DailyReport::reportListGroupDownload($rqst);

    $isvalid = $arr['output']['valid'];
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
        $email1 = $data['email1'] ?? '';
        $email2 = $data['email2'] ?? '';
        $email3 = $data['email3'] ?? '';
        $email4 = $data['email4'] ?? '';
    } else {
    ?>
        <script type='text/javascript'>
            alert('Sin resultados');
            window.location = 'daily_report_view_group.php';
        </script>
    <?php
        return;
    }
} else {
    ?>
    <script type='text/javascript'>
        alert('You must send a report to generate the document');
        window.location = 'daily_report_view_group.php';
    </script>
<?php
    return;
}

class CustomPDF extends TCPDF {
    // Sobreescribir el método Header para dibujar el marco en cada página
    public function Header() {
        // Generar el marco global con ajustes
        $this->SetLineWidth(0.2); 
        for ($i = 0; $i <= 150; $i++) {
            $red = min(255, 150 + $i * 0.7); 
            $gray = max(200 - $i * 0.5, 100); 
            $this->SetDrawColor($red, $gray, $gray); 
            $this->Rect(5 + ($i * 0.01), 5 + ($i * 0.01), 200 - ($i * 0.02), 287 - ($i * 0.02)); 
        }
    }
}

// Generar el PDF
$pdfFile = __DIR__ . '/report_' . $hoa . '.pdf';
$pdf = new TCPDF();
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 11);

// Instanciar la clase personalizada
$pdf = new CustomPDF();
$pdf->AddPage();

// Configurar contenido del PDF
$pdf->SetFont('helvetica', '', 11);
$pdf->Image("assets/img/logo3.png", '', '', 50, 30);
$pdf->Ln(20);
// Agregar una línea roja en lugar del texto de JOB
$pdf->SetDrawColor(255, 0, 0); 
$pdf->SetLineWidth(0.5); 
$pdf->Line(10, $pdf->GetY() + 15, 200, $pdf->GetY() + 15); 
$pdf->Ln(20); 


// Crear tabla con los datos
$tbl = <<<EOD

<style>
    table {
        border-collapse: collapse;
        width: 100%;
        font-family: Arial, sans-serif;
        margin-top: 20px;
    }
    th {
        color: #333;
        font-weight: bold;
        text-align: center;
        padding: 10px;
        border: 1px solid #ddd;
    }
    td {
        text-align: left;
        padding: 10px;
        border: 1px solid #ddd;
    }
</style>
<table>
    <tr>
        <td><strong>Manager</strong></td>
        <td colspan="2">$manager</td>
    </tr>
    <tr>
        <td><strong>Address</strong></td>
        <td colspan="2">$address</td>
    </tr>
    <tr>
        <td><strong>Email</strong></td>
        <td colspan="2">$email</td>
    </tr>
    <tr>
        <td><strong>Date</strong></td>
        <td colspan="2">$dtcreate</td>
    </tr>
    <tr>
        <td><strong>HOA</strong></td>
        <td colspan="2">$hoa</td>
    </tr>
    <tr>
        <td><strong>Employee</strong></td>
        <td colspan="2">$employee</td>
    </tr>
</table>
EOD;


$pdf->writeHTML($tbl, true, false, false, false, '');
// Ajusta el punto de inicio del contenido para que comience inmediatamente debajo del título
$pdf->SetY($pdf->GetY() + 1); // Reduce el espacio después del título aún más

$title = <<<EOD
<style>
    .title-box {
        border: 1px solid #ddd;
        border-radius: 5px;
        background-color: rgb(235, 50, 50); /* Fondo blanco */
        font-family: Arial, sans-serif;
        text-align: center;
        padding: 5px; /* Reducción del espacio interno */
        margin-top: 0px; /* Sin separación superior */
    }
    .title-text {
        color: white ; /* Color negro */
        font-size: 14px; /* Tamaño de fuente */
        font-weight: bold; /* Negrilla */
    }
</style>
<div class="title-box">
    <p class="title-text"><strong>Job Zone Reported</strong></p>
</div>
EOD;

// Escribe el título en la primera página
$pdf->writeHTML($title, true, false, true, false, '');

// Ajusta el punto de inicio del contenido para que comience inmediatamente debajo del título
$pdf->SetY($pdf->GetY() - 2); // Reduce el espacio después del título

$html = ''; // Inicia el contenido vacío

// Define altura fija para cada bloque
$blockHeight = 100; // Ajusta según necesidad

for ($i = 0; $i < count($dataShow); $i++) {
    // Verifica si hay espacio suficiente en la página para agregar el bloque completo
    if ($pdf->GetY() + $blockHeight > $pdf->getPageHeight() - 20) {
        $pdf->writeHTML($html, true, false, true, false, ''); // Renderiza el contenido actual
        $pdf->AddPage(); // Agrega una nueva página
        $pdf->SetY(20); // Inicia justo desde el borde superior en nuevas páginas
        $html = ''; // Reinicia el contenido
    }
    

    // Rutas de las imágenes
    $beforeImage = "admin/js/camara/foto/" . $dataShow[$i]['foto_antes'];
    $afterImage = "admin/js/camara/foto/" . $dataShow[$i]['foto_despues'];

    // Verifica si las imágenes existen
    $beforeImageHTML = file_exists($beforeImage)
    ? '<img src="' . $beforeImage . '" style="width: 150px; height: 150px; border: 1px solid #ccc;">'
    : '<p style="width: 150px; height: 150px; border: 1px solid #ccc; background-color: #f5f5f5; display: inline-block;">No image</p>';

    $afterImageHTML = file_exists($afterImage)
    ? '<img src="' . $afterImage . '" style="width: 150px; height: 150px; border: 1px solid #ccc;">'
    : '<p style="width: 150px; height: 150px; border: 1px solid #ccc; background-color: #f5f5f5; display: inline-block;">No image</p>';


    // Genera el contenido del bloque con márgenes ajustados
    $html .= <<<EOD
    <div nobr="true" style="font-family: Arial, sans-serif; margin-top: 0px; margin-bottom: 10px;">
        <!-- Línea roja -->
        <hr style="border: 0; border-top: 2px solid red; margin-bottom: 10px; width: 101%; margin-left: 100%;">

        
        <!-- Tabla para la información en formato horizontal -->
        <style>
            table {
                border-collapse: collapse;
                width: 100%;
                font-family: Arial, sans-serif;
                margin-top: 20px;
            }
            th {
                color: #333;
                font-weight: bold;
                text-align: center;
                padding: 10px;
                border: 1px solid #ddd;
            }
            td {
                text-align: left;
                padding: 10px;
                border: 1px solid #ddd;
            }
        </style>
        <table>
            <tr>
                <th>Item</th>
                <th>Date</th>
                <th>Zone</th>
                <th>Activities</th>
                <th>Observations</th>
            </tr>
            <tr>
                <td>{$dataShow[$i]['id']}</td>
                <td>{$dataShow[$i]['dtcreate']}</td>
                <td>{$dataShow[$i]['zone']}</td>
                <td>{$dataShow[$i]['actividades']}</td>
                <td>{$dataShow[$i]['observaciones']}</td>
            </tr>
        </table>
        
        <!-- Contenedor de imágenes sin caja -->
        <table style="width: 100%; margin-top: 10px; border-collapse: collapse;">
            <tr>
                <!-- Columna Before -->
                <td style="width: 50%; text-align: center; vertical-align: top; padding: 5px;">
                    <p style="margin-bottom: 5px; font-size: 15px;"><strong>Before</strong></p>
                    $beforeImageHTML
                </td>
                
                <!-- Columna After -->
                <td style="width: 50%; text-align: center; vertical-align: top; padding: 5px;">
                    <p style="margin-bottom: 5px; font-size: 15px;"><strong>After</strong></p>
                    $afterImageHTML
                </td>
            </tr>
        </table>
    </div>
    EOD;



    // Forzar el contenido inicial de la primera página
    if ($i === 0 && $pdf->PageNo() === 1) {
        $pdf->writeHTML($html, true, false, true, false, '');
        $html = ''; // Limpia después de la primera iteración
    }
}

// Escribir el contenido final
$pdf->writeHTML($html, true, false, true, false, '');

// Genera el PDF final
$pdf->Output($pdfFile, 'F');


// Configuración y envío del correo
$mail = new PHPMailer(true);
$emails_sent = []; // Arreglo para correos enviados
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'sendspidersoftware@gmail.com';
    $mail->Password = 'Martin3933++$$++';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 465;

    $mail->setFrom('sendspidersoftware@gmail.com', "Reporte $hoa between $f1 and $f2");
    if (!empty($email) && Util::validate_email($email)) {
        $mail->addAddress($email);
        $emails_sent[] = $email;
    }

    for ($i = 1; $i <= 4; $i++) {
        $emailVar = 'email' . $i;
        if (!empty($$emailVar) && Util::validate_email($$emailVar)) {
            $mail->addCC($$emailVar);
            $emails_sent[] = $$emailVar;
        }
    }

    $mail->addAttachment($pdfFile);
    $mail->isHTML(true);
    $mail->Subject = "Job Report for Hoa $hoa";
    $mail->Body = "<p>Dear $manager,</p><p>Attached is the Job Report for HOA: $hoa.</p>";

    $mail->send();
    $emails_list = implode(', ', $emails_sent);
    echo "<script>
            alert('PDF successfully sent to: $emails_list');
            setTimeout(function() {
                window.location = 'report-list-group.php';
            }, 800);
          </script>";
} catch (Exception $e) {
    echo "<script>alert('Failed to send PDF: {$mail->ErrorInfo}');</script>";
}

if (file_exists($pdfFile)) {
    unlink($pdfFile);
}
?>