<?php

/**
 * Clase que contiene todas las operaciones utilizadas sobre la base de datos
 * @author SPIDERSOFTWARE
 */
class InformeTiempo
{

    public function __construct()
    {
    }

    public static function getAll($rqst)
    {
        $db = new DbConection();
        $pdo = $db->openConect();


        $q = "SELECT tec_employee.nombre, tec_employee.cc, tec_entry.entrada, tec_entry.ip AS ip_entrada, tec_exit.salida, tec_exit.ip AS ip_salida
        FROM (tec_employee INNER JOIN tec_entry ON tec_employee.cc = tec_entry.cc) LEFT JOIN tec_exit ON tec_entry.cc = tec_exit.cc;
        "

        $q = "SELECT 
        tec_employee.id, tec_employee.nombre, tec_employee.cc, tec_entry.entrada,
        DATE_FORMAT(tec_entry.entrada,'%Y-%m-%d') AS fecha,  tec_entry.ip AS ip_entrada
        FROM " . $db->getTable('tec_employee') . "  INNER JOIN " . $db->getTable('tec_entry') . "  ON tec_employee.cc = tec_entry.cc ";

        $result = $pdo->query($q);
        $arrEntradas = array();
        $arrSalidas = array();
        if ($result) {
            foreach ($result as $valor) {
                $arrEntradas[] = $valor;
                $cc = $valor['cc'];
                $fecha = $valor['fecha'];

                // Consultamos si tenemos Informaciòn de salida
                $q0 = "SELECT tec_employee.id, tec_employee.cc, tec_employee.nombre, tec_exit.salida,
                DATE_FORMAT(tec_exit.salida,'%Y-%m-%d') AS fecha , tec_exit.ip AS ip_salida
                FROM " . $db->getTable('tec_employee') . " 
                INNER JOIN " . $db->getTable('tec_exit') . " ON tec_employee.cc = tec_exit.cc";
                $result0 = $pdo->query($q0);
                foreach ($result0 as $valor0) {
                    $arrSalidas[] = $valor0;
                }
            }
            $arrjson = array('output' => array('valid' => true, 'entradas' => $arrEntradas, 'salidas' => $arrSalidas));
        } else {
            $arrjson = Util::error_no_result();
        }
        $db->closeConect();
        return $arrjson;
    }
}