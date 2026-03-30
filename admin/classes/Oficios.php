<?php

/**
 * Clase que contiene todas las operaciones utilizadas sobre la base de datos
 * @author SPIDERSOFTWARE
 */
class Oficios
{

    public function __construct()
    {
    }

    public static function getAll($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "SELECT * FROM " . $db->getTable('tec_oficios') . " ORDER BY tec_oficios.oficio ASC LIMIT 100";
        if ($id > 0) {
            $q = "SELECT * FROM " . $db->getTable('tec_oficios') . " WHERE id = " . $id;
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
    public static function delete($rqst)
    {

        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;

        $db = new DbConection();
        $pdo = $db->openConect();


        $q = "DELETE FROM " . $db->getTable('tec_oficios') . " WHERE id = " . $id;
        $result = $pdo->query($q);
        if ($result) {

           
            $arrjson = array('output' => array('valid' => true, 'error' => $pdo->errorInfo()));
        } else {
            $arrjson = Util::error_generaldelete();
        }

        $db->closeConect();
        return $arrjson;
    }




    public static function save($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;

        $oficio = isset($rqst['oficio']) ? ($rqst['oficio']) : '';
        $descripcion = isset($rqst['descripcion']) ? ($rqst['descripcion']) : '';
        

        $db = new DbConection();
        $pdo = $db->openConect();

        if ($id > 0) {
            //actualiza la informacion
            $q = "SELECT id  FROM " . $db->getTable('tec_oficios') . " WHERE id = " . $id;
            $result = $pdo->query($q);
            if ($result) {
                $table = $db->getTable('tec_oficios');
                $arrfieldscomma = array(
                    'oficio' => $oficio,
                    'descripcion' => $descripcion,
                  
                );
                $arrfieldsnocomma = array('dtcreate' => Util::date_now_server());
                $q = Util::make_query_update($table, "id = '$id'", $arrfieldscomma, $arrfieldsnocomma);
                $result = $pdo->query($q);
                if (!$result) {
                    $arrjson = Util::error_general('Updating unit data');
                } else {
                    $arrjson = array('output' => array('valid' => true, 'id' => $id));
                }
            }
        } else {
            if ($oficio != "") {
                $q = "INSERT INTO " . $db->getTable('tec_oficios') . " (dtcreate, oficio, descripcion) 
                                    VALUES (" . Util::date_now_server() . ", :oficio, :descripcion)";
                $result = $pdo->prepare($q);
                $arrparam = array(
                    ':oficio' => $oficio,
                    ':descripcion' => $descripcion,
                 
                );
                if ($result->execute($arrparam)) {
                    $arrjson = array('output' => array('valid' => true, 'response' => $pdo->lastInsertId()));
                } else {
                    $arrjson = Util::error_general();
                }
            } else {
                $arrjson = Util::error_missing_data();
            }
        }
        $db->closeConect();
        return $arrjson;
    }

   
}
