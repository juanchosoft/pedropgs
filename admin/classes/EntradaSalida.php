<?php

require_once __DIR__ . '/DbConection.php';
require_once __DIR__ . '/Util.php';
require_once __DIR__ . '/SessionData.php';

/**
 * Registro de entrada/salida (reloj) vinculado al empleado de la sesión.
 * Permite múltiples check-in / check-out el mismo día según el último evento.
 */
class EntradaSalida
{
    public function __construct()
    {
    }

    /**
     * Estado actual según el último registro del día.
     * @return array{status:string,label:string,has_employee:bool}
     *   status: checkin | checkout | no_employee
     */
    public static function getTodayStatus()
    {
        date_default_timezone_set('America/Bogota');
        $cc = SessionData::getEmployeeCc();
        if ($cc <= 0) {
            return [
                'status' => 'no_employee',
                'label' => 'Check-in',
                'has_employee' => false,
            ];
        }

        $db = new DbConection();
        $pdo = $db->openConect();
        $today = date('Y-m-d');

        try {
            $next = self::resolveNextAction($pdo, $db, $cc, $today);
            return [
                'status' => $next,
                'label' => ($next === 'checkout') ? 'Check-out' : 'Check-in',
                'has_employee' => true,
            ];
        } catch (Exception $e) {
            return [
                'status' => 'checkin',
                'label' => 'Check-in',
                'has_employee' => true,
            ];
        } finally {
            $db->closeConect();
        }
    }

    /**
     * Determina la siguiente acción según el último evento del día.
     * - Sin registros o última salida → checkin
     * - Última entrada → checkout
     *
     * @return string checkin|checkout
     */
    private static function resolveNextAction(PDO $pdo, DbConection $db, $cc, $today)
    {
        $entryTable = $db->getTable('tec_entry');
        $exitTable = $db->getTable('tec_exit');

        $sql = "SELECT tipo, momento FROM (
                    SELECT 'entrada' AS tipo, entrada AS momento, id
                    FROM {$entryTable}
                    WHERE cc = :cc1 AND DATE(entrada) = :today1
                    UNION ALL
                    SELECT 'salida' AS tipo, salida AS momento, id
                    FROM {$exitTable}
                    WHERE cc = :cc2 AND DATE(salida) = :today2
                ) AS eventos
                ORDER BY momento DESC, id DESC
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cc1' => $cc,
            ':today1' => $today,
            ':cc2' => $cc,
            ':today2' => $today,
        ]);
        $last = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$last) {
            return 'checkin';
        }

        return ($last['tipo'] === 'entrada') ? 'checkout' : 'checkin';
    }

    public static function save($rqst)
    {
        date_default_timezone_set('America/Bogota');

        // CC desde sesión (tec_usuarios.employee_id = tec_employee.cc)
        $cc = SessionData::getEmployeeCc();
        if ($cc <= 0 && isset($rqst['cc'])) {
            $cc = (int) $rqst['cc'];
        }

        $fecha = date('Y-m-d H:i:s');
        $coords = $rqst['coords'] ?? '';
        if (empty($coords) || $coords === '0,0') {
            $coords = self::getCoordsByIP();
        }
        $today = date('Y-m-d');
        $ip = Util::get_real_ipaddress();

        if ($cc <= 0) {
            return Util::error_general('Your user is not linked to an employee. Ask an administrator to associate your account.');
        }

        $db = new DbConection();
        $pdo = $db->openConect();

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "SELECT nombre, email, cc FROM " . $db->getTable('tec_employee') . " WHERE cc = :cc LIMIT 1"
            );
            $stmt->execute([':cc' => $cc]);
            $empleado = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$empleado) {
                $pdo->rollBack();
                return Util::error_general('Employee record not found for your linked ID.');
            }

            $userFullName = SessionData::getUserFullName();
            $nextAction = self::resolveNextAction($pdo, $db, $cc, $today);
            $mailType = null;
            $msg = '';
            $action = '';
            $nextStatus = 'checkin';
            $nextLabel = 'Check-in';

            if ($nextAction === 'checkin') {
                $stmt = $pdo->prepare(
                    "INSERT INTO " . $db->getTable('tec_entry') . " (cc, entrada, ip, coords)
                     VALUES (:cc, :entrada, :ip, :coords)"
                );
                $stmt->execute([
                    ':cc' => $cc,
                    ':entrada' => $fecha,
                    ':ip' => $ip,
                    ':coords' => $coords,
                ]);
                $msg = 'Check-in registered. Welcome, ' . $empleado['nombre'];
                $mailType = 'entrada';
                $action = 'checkin';
                $nextStatus = 'checkout';
                $nextLabel = 'Check-out';
            } else {
                // Check-out: exige que el último evento sea una entrada
                $stmt = $pdo->prepare(
                    "INSERT INTO " . $db->getTable('tec_exit') . " (cc, salida, ip, coords)
                     VALUES (:cc, :salida, :ip, :coords)"
                );
                $stmt->execute([
                    ':cc' => $cc,
                    ':salida' => $fecha,
                    ':ip' => $ip,
                    ':coords' => $coords,
                ]);
                $msg = 'Check-out registered. You can Check-in again if you return today, ' . $empleado['nombre'];
                $mailType = 'salida';
                $action = 'checkout';
                $nextStatus = 'checkin';
                $nextLabel = 'Check-in';
            }

            $pdo->commit();

            if ($mailType !== null) {
                $mailResult = self::sendAttendanceEmail([
                    'type' => $mailType,
                    'employee_name' => $empleado['nombre'],
                    'employee_email' => isset($empleado['email']) ? trim((string) $empleado['email']) : '',
                    'user_name' => $userFullName,
                    'user_email' => '',
                    'cc' => $cc,
                    'datetime' => $fecha,
                    'ip' => $ip,
                ]);

                if (!$mailResult['sent']) {
                    $msg .= ' (Email could not be sent' . ($mailResult['error'] !== '' ? ': ' . $mailResult['error'] : '') . ')';
                }
            }

            return [
                'output' => [
                    'valid' => true,
                    'response' => $msg,
                    'action' => $action,
                    'next_status' => $nextStatus,
                    'next_label' => $nextLabel,
                ],
            ];
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return Util::error_general('System error processing record: ' . $e->getMessage());
        } finally {
            $db->closeConect();
        }
    }

    /**
     * @param array{type:string,employee_name:string,employee_email:string,user_name:string,user_email:string,cc:int|string,datetime:string,ip:string} $data
     * @return array{sent:bool,error:string}
     */
    private static function sendAttendanceEmail(array $data): array
    {
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            return ['sent' => false, 'error' => 'Mail library missing'];
        }
        require_once $autoload;
        require_once dirname(__DIR__) . '/config/constants.php';

        $adminEmail = defined('ATTENDANCE_ADMIN_EMAIL') ? trim((string) ATTENDANCE_ADMIN_EMAIL) : '';
        if ($adminEmail === '' || !Util::validate_email($adminEmail)) {
            return ['sent' => false, 'error' => 'Admin notification email is not configured'];
        }

        $isIn = ($data['type'] === 'entrada');
        $eventLabel = $isIn ? 'Clock In' : 'Clock Out';
        $eventLabelEs = $isIn ? 'Entrada' : 'Salida';
        $accent = $isIn ? '#16a34a' : '#E10600';
        $name = htmlspecialchars($data['employee_name'] !== '' ? $data['employee_name'] : $data['user_name'], ENT_QUOTES, 'UTF-8');
        $datetime = htmlspecialchars($data['datetime'], ENT_QUOTES, 'UTF-8');
        $ip = htmlspecialchars((string) $data['ip'], ENT_QUOTES, 'UTF-8');
        $cc = htmlspecialchars((string) $data['cc'], ENT_QUOTES, 'UTF-8');
        $userName = htmlspecialchars((string) $data['user_name'], ENT_QUOTES, 'UTF-8');

        $html = self::renderEmailTemplate('attendance.html', [
            'event_label' => $eventLabel,
            'event_label_es' => $eventLabelEs,
            'accent' => $accent,
            'name' => $name,
            'datetime' => $datetime,
            'user_name' => $userName,
            'cc' => $cc,
            'ip' => $ip,
        ]);
        if ($html === null) {
            return ['sent' => false, 'error' => 'Email template not found'];
        }

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.hostinger.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'envios@spidersoftware.co';
            $mail->Password = 'Martin3933++$$@@';
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom('envios@spidersoftware.co', 'PGS Centrum Time Record');
            $mail->addAddress($adminEmail);

            $mail->isHTML(true);
            $mail->Subject = $eventLabel . ' — ' . strip_tags($name) . ' — ' . $data['datetime'];
            $mail->Body = $html;
            $mail->AltBody = $eventLabel . ' for ' . strip_tags($name) . ' at ' . $data['datetime'];
            $mail->send();

            return ['sent' => true, 'error' => ''];
        } catch (\Throwable $e) {
            return ['sent' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Carga plantilla HTML desde admin/templates/email/ y reemplaza {{placeholders}}.
     *
     * @param array<string, string> $vars
     */
    private static function renderEmailTemplate(string $fileName, array $vars): ?string
    {
        $path = dirname(__DIR__) . '/templates/email/' . basename($fileName);
        if (!is_file($path)) {
            return null;
        }
        $html = file_get_contents($path);
        if ($html === false) {
            return null;
        }
        foreach ($vars as $key => $value) {
            $html = str_replace('{{' . $key . '}}', (string) $value, $html);
        }
        return $html;
    }

    private static function getCoordsByIP()
    {
        $ip = Util::get_real_ipaddress();
        $url = "http://ip-api.com/json/{$ip}?fields=lat,lon";
        $resp = false;
        if (ini_get('allow_url_fopen')) {
            $resp = @file_get_contents($url);
        }
        if (!$resp && function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            $resp = curl_exec($ch);
            curl_close($ch);
        }
        if ($resp) {
            $data = json_decode($resp, true);
            if ($data && isset($data['lat']) && isset($data['lon'])) {
                return $data['lat'] . ',' . $data['lon'];
            }
        }
        return '';
    }
}
