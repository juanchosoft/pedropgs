<?php

/**
 * Clase que contiene todas las operaciones utilizadas sobre la base de datos
 * @author SPIDERSOFTWARE
 */
class Ciudad {

    public function __construct(){}


    /**
     * Metodo para recuperar todos los registros
     * @return array de las Promociones
     */
    public static function getAll($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        $codigo_departamento = isset($rqst['codigo_departamento']) ? intval($rqst['codigo_departamento']) : 0;

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "SELECT * FROM " . $db->getTable('tec_city') . " ORDER BY id DESC LIMIT 100";

        if ($id > 0) {
            $q = "SELECT * FROM " . $db->getTable('tec_city') . " WHERE id = " . $id;
        }
        if ($codigo_departamento > 0) {
            $q = "SELECT * FROM " . $db->getTable('tec_city') . " WHERE codigo_departamento = " . $codigo_departamento;
        }
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
