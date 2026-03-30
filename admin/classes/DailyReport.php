<?php

/**
 * Clase que contiene todas las operaciones utilizadas sobre la base de datos
 * @author SPIDERSOFTWARE
 */
class DailyReport
{

    public function __construct() {}

    public static function getAll($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "SELECT tbl_fotos.*, tbl_unidades.*, tec_usuarios.nombre, tec_usuarios.apellido, tbl_unidades.nombre AS hoa
        FROM " . $db->getTable('tbl_fotos') . " 
        INNER JOIN " . $db->getTable('tbl_unidades') . " ON tbl_fotos.tbl_unidad_id = tbl_unidades.id 
        INNER JOIN " . $db->getTable('tec_usuarios') . " ON tbl_fotos.tbl_usuario_id = tec_usuarios.id";

        if ($id > 0) {
            $q = "SELECT tbl_fotos.*, tbl_unidades.*, tec_usuarios.nombre, tec_usuarios.apellido, tbl_unidades.nombre AS hoa
            FROM " . $db->getTable('tbl_fotos') . " 
            INNER JOIN " . $db->getTable('tbl_unidades') . " ON tbl_fotos.tbl_unidad_id = tbl_unidades.id 
            INNER JOIN " . $db->getTable('tec_usuarios') . " ON tbl_fotos.tbl_usuario_id = tec_usuarios.id WHERE tec_daily_report.id = " . $id;
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

    public static function getAllfe($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        $hoa = isset($rqst['hoa']) ? intval($rqst['hoa']) : 0;
        $zona = isset($rqst['place']) ? intval($rqst['place']) : 0;
        $date = isset($rqst['date']) ? ($rqst['date']) : '';
        $dateConvert = date('Y-m-d', strtotime($date));

        $db = new DbConection();
        $pdo = $db->openConect();

        $q= "SELECT tbl_fotos.*, tbl_unidades.*, tec_usuarios.nombre, tec_usuarios.apellido, , tbl_unidades.nombre AS hoa
        FROM " . $db->getTable('tbl_fotos') . " 
        INNER JOIN " . $db->getTable('tbl_unidades') . " ON tbl_fotos.tbl_unidad_id = tbl_unidades.id 
        INNER JOIN " . $db->getTable('tec_usuarios') . " ON tbl_fotos.tbl_usuario_id = tec_usuarios.id  GROUP BY tbl_unidades.nombre, tec_lugar.zona,  DATE(tec_daily_report.dtcreate)";

        if ($hoa > 0 && $zona > 0) {
            $q= "SELECT tbl_fotos.*, tbl_unidades.*, tec_usuarios.nombre, tec_usuarios.apellido, , tbl_unidades.nombre AS hoa
            FROM " . $db->getTable('tbl_fotos') . " 
            INNER JOIN " . $db->getTable('tbl_unidades') . " ON tbl_fotos.tbl_unidad_id = tbl_unidades.id 
            INNER JOIN " . $db->getTable('tec_usuarios') . " ON tbl_fotos.tbl_usuario_id = tec_usuarios.id 
            WHERE tec_daily_report.tbl_unidades_id = $hoa AND tec_daily_report.tbl_lugar_id = " . $zona . "  AND
            tec_daily_report.dtcreate >= '$dateConvert 00:00:01' AND tec_daily_report.dtcreate <= '$dateConvert 23:59:59' ";
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

        $tbl_unidades_id = isset($rqst['tbl_unidades_id']) ? ($rqst['tbl_unidades_id']) : '';
        $tbl_employees_id = isset($rqst['tbl_employees_id']) ? ($rqst['tbl_employees_id']) : '';
        $tbl_lugar_id = isset($rqst['tbl_lugar_id']) ? ($rqst['tbl_lugar_id']) : '';
        $tec_oficios_id = isset($rqst['tec_oficios_id']) ? ($rqst['tec_oficios_id']) : '';
        $img = isset($_SESSION['file']['nombrearchivo']) ? ($_SESSION['file']['nombrearchivo']) : '';
        $imga = isset($_SESSION['file']['nombrearchivo_after']) ? ($_SESSION['file']['nombrearchivo_after']) : '';


        $db = new DbConection();
        $pdo = $db->openConect();

        if ($id > 0) {
            //actualiza la informacion
            $q = "SELECT id  FROM " . $db->getTable('tec_daily_report') . " WHERE id = " . $id;
            $result = $pdo->query($q);
            if ($result) {
                $table = $db->getTable('tec_daily_report');
                $arrfieldscomma = array(
                    'tbl_unidades_id' => $tbl_unidades_id,
                    'tbl_employees_id' => $tbl_employees_id,
                    'tbl_lugar_id' => $tbl_lugar_id,
                    'tec_oficios_id' => $tec_oficios_id,
                    'img' => $img,
                    'imga' => $imga,

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
            if ($tbl_employees_id != "") {
                $q = "INSERT INTO " . $db->getTable('tec_daily_report') . " (dtcreate, tbl_unidades_id, tbl_employees_id, tbl_lugar_id,tec_oficios_id, img, imga) 
                                    VALUES (" . Util::date_now_server() . ", :tbl_unidades_id, :tbl_employees_id, :tbl_lugar_id,:tec_oficios_id, :img, :imga )";
                $result = $pdo->prepare($q);
                $arrparam = array(
                    'tbl_unidades_id' => $tbl_unidades_id,
                    'tbl_employees_id' => $tbl_employees_id,
                    'tbl_lugar_id' => $tbl_lugar_id,
                    'tec_oficios_id' => $tec_oficios_id,
                    'img' => $img,
                    'imga' => $imga,
                );
                if ($result->execute($arrparam)) {
                    $_SESSION['file']["nombrearchivo_after"] = "";
                    $_SESSION['file']["nombrearchivo"] = "";
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


    public static function reportListGroupDownload($rqst)
    {
        $hoa = isset($rqst['hoa']) ? intval($rqst['hoa']) : 0;
        $f1 = isset($rqst['f1']) ? ($rqst['f1']) : '';
        $f2 = isset($rqst['f2']) ? ($rqst['f2']) : '';

        if ($hoa > 0) {

            $db = new DbConection();
            $pdo = $db->openConect();

            $q = "SELECT
                tbl_unidades.*,
                tec_usuarios.nombre AS usuario, 
                tbl_fotos.*
            FROM 
                " . $db->getTable('tbl_fotos') . " 
            INNER JOIN 
                " . $db->getTable('tbl_unidades') . " ON tbl_fotos.tbl_unidad_id = tbl_unidades.id 
            INNER JOIN 
                " . $db->getTable('tec_usuarios') . " ON tbl_fotos.tbl_usuario_id = tec_usuarios.id
            WHERE 
                tbl_fotos.tbl_unidad_id = $hoa 
                AND tbl_fotos.dtcreate BETWEEN '$f1 00:00:01' AND '$f2 23:59:59' ";
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
        } else {
            return  Util::error_no_result();
        }
    }
}
