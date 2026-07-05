<?php
class EntradaSalida
{

    public function __construct()
    {
    }

    public static function save($rqst)
    {
        $cc = isset($rqst['cc']) ? intval($rqst['cc']) : 0;
        $fecha = $rqst['fecha'] ?? '';
        $coords = $rqst['coords'] ?? '';
        if (empty($coords) || $coords === '0,0') {
            $coords = self::getCoordsByIP();
        }
        $today = date("Y-m-d");
        $ip = Util::get_real_ipaddress();

        if (empty($cc)) {
            return Util::error_missing_data('Citizenship card is mandatory');
        }

        $db = new DbConection();
        $pdo = $db->openConect();

        try {
            $pdo->beginTransaction();

            // 1. Verificar existencia de usuario usando Prepared Statement
            $stmt = $pdo->prepare("SELECT nombre FROM " . $db->getTable('tec_employee') . " WHERE cc = :cc LIMIT 1");
            $stmt->execute([':cc' => $cc]);
            $empleado = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$empleado) {
                $pdo->rollBack();
                return Util::error_general('The document ' . $cc . ' does not exist in the database.');
            }

            // 2. Verificar entrada del día
            $stmt = $pdo->prepare("SELECT id FROM " . $db->getTable('tec_entry') . " WHERE cc = :cc AND DATE(entrada) = :today");
            $stmt->execute([':cc' => $cc, ':today' => $today]);
            $yaIngreso = $stmt->fetch();

            if (!$yaIngreso) {
                // Registrar Entrada
                $stmt = $pdo->prepare("INSERT INTO " . $db->getTable('tec_entry') . " (cc, entrada, ip, coords) VALUES (:cc, :entrada, :ip, :coords)");
                $stmt->execute([':cc' => $cc, ':entrada' => $fecha, ':ip' => $ip, ':coords' => $coords]);
                $msg = 'Welcome to the company ' . $empleado['nombre'];
            } else {
                // 3. Verificar si ya registró salida
                $stmt = $pdo->prepare("SELECT id FROM " . $db->getTable('tec_exit') . " WHERE cc = :cc AND DATE(salida) = :today");
                $stmt->execute([':cc' => $cc, ':today' => $today]);
                $yaSalio = $stmt->fetch();

                if (!$yaSalio) {
                    // Registrar Salida
                    $stmt = $pdo->prepare("INSERT INTO " . $db->getTable('tec_exit') . " (cc, salida, ip, coords) VALUES (:cc, :salida, :ip, :coords)");
                    $stmt->execute([':cc' => $cc, ':salida' => $fecha, ':ip' => $ip, ':coords' => $coords]);
                    $msg = 'See you tomorrow, ' . $empleado['nombre'];
                } else {
                    $msg = 'Happy day ' . $empleado['nombre'];
                }
            }

            $pdo->commit();
            $arrjson = array('output' => array('valid' => true, 'response' => $msg));

        } catch (Exception $e) {
            $pdo->rollBack();
            $arrjson = Util::error_general('System error processing record: ' . $e->getMessage());
        }

        $db->closeConect();
        return $arrjson;
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
