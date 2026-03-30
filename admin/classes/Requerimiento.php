<?php

/**
 * Clase que contiene todas las operaciones utilizadas sobre la base de datos
 * @author SPIDERSOFTWARE
 */
class Requerimiento
{

    public function __construct()
    {
    }
    

    public static function getAll($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "SELECT * FROM " . $db->getTable('tbl_requerimientos') . " ORDER BY tbl_requerimientos.zona ASC LIMIT 100";
        if ($id > 0) {
            $q = "SELECT * FROM " . $db->getTable('tbl_requerimientos') . " WHERE id = " . $id;
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

    public static function save($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        $zona = isset($rqst['zona']) ? ($rqst['zona']) : '';
        $prioridad = isset($rqst['prioridad']) ? ($rqst['prioridad']) : '';
        $detalles = isset($rqst['detalles']) ? ($rqst['detalles']) : '';

        $db = new DbConection();
        $pdo = $db->openConect();

        if ($id > 0) {
            //actualiza la informacion
            $q = "SELECT id  FROM " . $db->getTable('tbl_requerimientos') . " WHERE id = " . $id;
            $result = $pdo->query($q);
            if ($result) {
                $table = $db->getTable('tbl_requerimientos');
                $arrfieldscomma = array(
                    'zona' => $zona,
                    'prioridad' => $prioridad,
                    'detalles' => $detalles
                );
                $arrfieldsnocomma = array('dtcreate' => Util::date_now_server());
                $q = Util::make_query_update($table, "id = '$id'", $arrfieldscomma, $arrfieldsnocomma);
                $result = $pdo->query($q);
                if (!$result) {
                    $arrjson = Util::error_general('Update request');
                } else {
                    $arrjson = array('output' => array('valid' => true, 'id' => $id));
                }
            }
        } else {
            if ($zona != "") {
                $q = "INSERT INTO " . $db->getTable('tbl_requerimientos') . " (dtcreate, zona, prioridad, detalles) VALUES (" . Util::date_now_server() . ", :zona, :prioridad, :detalles)";
                $result = $pdo->prepare($q);
                $arrparam = array(
                    ':zona' => $zona,
                    ':prioridad' => $prioridad,
                    ':detalles' => $detalles
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

    public static function getRequerimientoHistorial($rqst)
    {

        $search = isset($rqst['search']) ? ($rqst['search']) : '';
        $rango_fechas = isset($rqst['rango_fechas']) ? ($rqst['rango_fechas']) : '';
        $f1 = isset($rqst['f1']) ? ($rqst['f1']) : '';
        $f2 = isset($rqst['f2']) ? ($rqst['f2']) : '';
        $ROWS_LIMIT = 1000;

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "SELECT 
        tbl_requerimientos.* FROM " .
            $db->getTable('tbl_requerimientos') . "
        ORDER BY id desc LIMIT $ROWS_LIMIT";

        if ($search != "") {
            $q = "SELECT tbl_requerimientos.*,
            FROM " .
                $db->getTable('tbl_requerimientos') . "
            WHERE 
            tbl_requerimientos.nombre LIKE '%$search%' AND
            tbl_requerimientos.administrador LIKE '%$search%'
            ORDER BY tbl_requerimientos.id LIMIT $ROWS_LIMIT";
        }

        // Buscar por rango de fechas
        if ($rango_fechas == "si" &&  $f1 != "" &&  $f2 != "") {
            $q = "SELECT tbl_requerimientos.*,
            FROM " .
                $db->getTable('tbl_requerimientos') . "  
            WHERE 
            tbl_requerimientos.dtcreate >= '$f1 00:00:01' AND
            tbl_requerimientos.dtcreate <= '$f2 23:59:59'
            ORDER BY tbl_requerimientos.id LIMIT $ROWS_LIMIT";
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


        /*         print_r($q);
        exit(); */
        $db->closeConect();
        return $arrjson;
    }

    public static function delete($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        Util::trace_log($rqst, 'Requerimiento::delete ' . $id);

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "DELETE FROM " . $db->getTable('tbl_requerimientos') . " WHERE id = " . $id;
        $result = $pdo->query($q);
        if ($result) {
            $arrjson = array('output' => array('valid' => true, 'error' => $pdo->errorInfo()));
        } else {
            Util::trace_log_error($rqst, 'Requerimiento::delete ' . $id, $pdo->errorInfo());
            $arrjson = Util::error_generaldelete();
        }
        $db->closeConect();
        return $arrjson;
    }

    public static function search($rqst)
    {
        $search = isset($rqst['search']) ? ($rqst['search']) : '';

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "SELECT * FROM " . $db->getTable('tbl_requerimientos') . " 
        WHERE code  LIKE '%$search%'  OR
            name  LIKE '%$search%' LIMIT 200 ";

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
