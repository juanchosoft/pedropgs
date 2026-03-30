<?php

/**
 * Clase que contiene todas las operaciones utilizadas sobre la base de datos
 * @author SPIDERSOFTWARE
 */
class Report
{

    public function __construct()
    {
    }

    public static function getAll($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "SELECT * FROM " . $db->getTable('tbl_fotos') . "  ORDER BY tbl_fotos.dtcreate desc LIMIT 20" ;
        if ($id > 0) {
            $q = "SELECT * FROM " . $db->getTable('tbl_fotos') . " WHERE id = " . $id;
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

    public static function updateFields($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;      
        $actividades = isset($rqst['actividades']) ? ($rqst['actividades']) : '';
        $observaciones = isset($rqst['observaciones']) ? ($rqst['observaciones']) : '';
        $tbl_usuario_id = $_SESSION['session_user']['id'];
 

        $db = new DbConection();
        $pdo = $db->openConect();

        if ($id > 0 && $actividades != "" && $observaciones != "" ) {

            $q = "SELECT id  FROM " . $db->getTable('tbl_fotos') . " WHERE id = " . $id;
            $result = $pdo->query($q);
            if ($result) {
                $table = $db->getTable('tbl_fotos');
                $arrfieldscomma = array(                  
                    'actividades' => $actividades,
                    'observaciones' => $observaciones,
                    'tbl_usuario_id_update' => $tbl_usuario_id                   
                );
                $arrfieldsnocomma = array('dtcreate' => Util::date_now_server());
                $q = Util::make_query_update($table, "id = '$id'", $arrfieldscomma, $arrfieldsnocomma);



                $result = $pdo->query($q);
                if (!$result) {
                    $arrjson = Util::error_general('Updating report');
                } else {
                    $arrjson = array('output' => array('valid' => true, 'id' => $id));
                }
            }
        } else {
            $arrjson = Util::error_missing_data();
        }

        $db->closeConect();

        return $arrjson;
    }
    public static function delete($rqst)
    {

        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "DELETE FROM " . $db->getTable('tbl_fotos') . " WHERE id = " . $id;
        $result = $pdo->query($q);
        if ($result) {
            $arrjson = array('output' => array('valid' => true, 'error' => $pdo->errorInfo()));
        } else {
            $arrjson = Util::error_generaldelete();
        }
        $db->closeConect();

        return $arrjson;
    }
}
