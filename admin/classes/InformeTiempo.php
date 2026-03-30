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

        $q = "  SELECT 
        tec_employee.nombre, 
        tec_employee.cc, 
        tec_entry.entrada AS entrada, 
        tec_entry.ip AS ip_entrada, 
        tec_entry.coords AS coords_entrada, 
        tec_exit.salida AS salida, 
        tec_exit.ip AS ip_salida,
        tec_exit.coords AS coords_salida
        FROM (" . $db->getTable('tec_employee') . " INNER JOIN " . $db->getTable('tec_entry') . " ON tec_employee.cc = tec_entry.cc) 
        LEFT JOIN " . $db->getTable('tec_exit') . " ON tec_entry.cc = tec_exit.cc ";


        $result = $pdo->query($q);
        $arr = array();
        if ($result) {
            foreach ($result as $valor) {
                $arr[] = $valor;
            }
            $arrjson = array('output' => array('valid' => true, 'response' => $arr));
        } else {
            $arrjson = Util::error_no_result();
        }
        $db->closeConect();
        return $arrjson;
    }
}
