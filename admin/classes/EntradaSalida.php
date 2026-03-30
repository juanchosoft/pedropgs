<?php
class EntradaSalida
{

    public function __construct()
    {
    }

    public static function save($rqst)
    {

        $cc = isset($rqst['cc']) ? intval($rqst['cc']) : '';
        $fecha = isset($rqst['fecha']) ? ($rqst['fecha']) : '';
        $coords = isset($rqst['coords']) ? ($rqst['coords']) : '';
        $today = date("Y-m-d");
        $ip = Util::get_real_ipaddress();


 

        $db = new DbConection();
        $pdo = $db->openConect();

        if ($cc  != "") {

            $q_cc = "SELECT * FROM " . $db->getTable('tec_employee') . " WHERE cc = " . $cc . " LIMIT 1";
            $result_cc = $pdo->query($q_cc);
            $arr_cc = array();

            if ($result_cc) {
                foreach ($result_cc as $valor_cc) {
                    $arr_cc[] = $valor_cc;
                }

                if (count($arr_cc) == 0) {
                    $arrjson = Util::error_general('The document ' . $cc . ' does not exist in the database..');
                } else {

                    // Se valida que no tenga ingreso del día actual
                    $q_ingreso_dia_actual = "SELECT * FROM " . $db->getTable('tec_entry') . " WHERE cc = '$cc'  AND entrada >= '$today 00:00:01' AND entrada <= '$today 23:59:59'  ";
                    $result_ingreso = $pdo->query($q_ingreso_dia_actual);
                    $arr_ingreso = array();
                    if ($result_ingreso) {

                        foreach ($result_ingreso as $valor_ingreso) {
                            $arr_ingreso[] = $valor_ingreso;
                        }

                        if (count($arr_ingreso) == 0) {
                            $q = "INSERT INTO " . $db->getTable('tec_entry') . " (cc, entrada, ip, coords) VALUES (:cc, :entrada, :ip, :coords)";
                            $result = $pdo->prepare($q);
                            $arrparam = array(
                                ':cc' => $cc,
                                ':entrada' => $fecha,
                                ':ip' => $ip,
                                ':coords' => $coords,
                            );

                            if ($result->execute($arrparam)) {
                                $arrjson = array('output' => array('valid' => true, 'response' => 'Welcome to the company and we wish you a good day and look forward to your best attitude. '  . $arr_cc[0]['nombre']));
                            } else {
                                $arrjson = Util::error_general('Entering employee input information');
                            }
                        } else {

                            // Se valida que no tenga ingreso del día actual
                            $q_ingreso_dia_actual = "SELECT * FROM " . $db->getTable('tec_exit') . " WHERE cc = '$cc'  AND salida >= '$today 00:00:01' AND salida <= '$today 23:59:59'  ";
                            $result_ingreso = $pdo->query($q_ingreso_dia_actual);
                            $arr_ingreso = array();
                            if ($result_ingreso) {

                                foreach ($result_ingreso as $valor_ingreso) {
                                    $arr_ingreso[] = $valor_ingreso;
                                }
                                if (count($arr_ingreso) == 0) {
                                    $q = "INSERT INTO " . $db->getTable('tec_exit') . "  (cc, salida, ip,coords)  VALUES (:cc, :salida, :ip, :coords)";
                                    $result = $pdo->prepare($q);
                                    $arrparam = array(
                                        ':cc' => $cc,
                                        ':salida' => $fecha,
                                        ':ip' => $ip,
                                        ':coords' => $coords,
                                    );

                                    if ($result->execute($arrparam)) {
                                        $arrjson = array('output' => array('valid' => true, 'response' => 'See you tomorrow and have a nice rest of the afternoon '  . $arr_cc[0]['nombre']));
                                    } else {
                                        $arrjson = Util::error_general('Entering Output Information');
                                    }
                                } else {
                                    $arrjson = array('output' => array('valid' => true, 'response' => ' Happy day '  . $arr_cc[0]['nombre']));
                                }
                                // print_r($arrjson);
                                // echo "<br>";
                                // echo ' Empelado --->'.$arr_cc[0]['nombre'];
                                // echo "<br>";
                                // exit();

                            } else {
                                $arrjson = Util::error_general('Querying Output Information');
                            }
                        }
                    } else {
                        $arrjson = Util::error_general('Consulting income information');
                    }
                }
            } else {
                $arrjson = Util::error_general('Querying user information');
            }
        } else {
            $arrjson = Util::error_missing_data('Citizenship card is mandatory');
        }

        $db->closeConect();
        return $arrjson;
    }
}
