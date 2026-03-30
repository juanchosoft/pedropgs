<?php

/**
 * Clase que contiene todas las operaciones utilizadas sobre la base de datos
 * @author SPIDERSOFTWARE
 */
class Lugar
{

    public function __construct()
    {
    }

    public static function getAll($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;

        $db = new DbConection();
        $pdo = $db->openConect();

        $q="SELECT tbl_unidades.id,tbl_unidades.nombre AS hoa, tec_lugar.zona, tec_lugar.id 
        FROM " . $db->getTable('tbl_unidades') . " 
        INNER JOIN " . $db->getTable('tec_lugar') . "  ON tbl_unidades.id = tec_lugar.tbl_unidades_id";
        if ($id > 0) {
            $q="SELECT tbl_unidades.id,tbl_unidades.nombre AS hoa, tec_lugar.zona, tec_lugar.id 
            FROM " . $db->getTable('tbl_unidades') . " 
            INNER JOIN " . $db->getTable('tec_lugar') . "  ON tbl_unidades.id = tec_lugar.tbl_unidades_id  WHERE tec_lugar.id = " . $id;
           
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


        $q = "DELETE FROM " . $db->getTable('tec_lugar') . " WHERE id = " . $id;
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

        $tbl_unidades_id = isset($rqst['tbl_unidades_id']) ? ($rqst['tbl_unidades_id']) : '';
        $zona = isset($rqst['zona']) ? ($rqst['zona']) : '';
        

        $db = new DbConection();
        $pdo = $db->openConect();

        if ($id > 0) {
            //actualiza la informacion
            $q = "SELECT id  FROM " . $db->getTable('tec_lugar') . " WHERE id = " . $id;
            $result = $pdo->query($q);
            if ($result) {
                $table = $db->getTable('tec_lugar');
                $arrfieldscomma = array(
                    'tbl_unidades_id' => $tbl_unidades_id,
                    'zona' => $zona,
                  
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
            if ($tbl_unidades_id != "") {
                $q = "INSERT INTO " . $db->getTable('tec_lugar') . " (dtcreate, tbl_unidades_id, zona) 
                                    VALUES (" . Util::date_now_server() . ", :tbl_unidades_id, :zona)";
                $result = $pdo->prepare($q);
                $arrparam = array(
                    ':tbl_unidades_id' => $tbl_unidades_id,
                    ':zona' => $zona,
                 
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
