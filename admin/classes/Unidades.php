<?php

/**
 * Clase que contiene todas las operaciones utilizadas sobre la base de datos
 * @author SPIDERSOFTWARE
 */
class Unidades
{

    public function __construct()
    {
    }

    public static function getAll($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "SELECT * FROM " . $db->getTable('tbl_unidades') . " ORDER BY tbl_unidades.ubicacion ASC LIMIT 100";
        if ($id > 0) {
            $q = "SELECT * FROM " . $db->getTable('tbl_unidades') . " WHERE id = " . $id;
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

        $nombre = isset($rqst['nombre']) ? ($rqst['nombre']) : '';
        $ubicacion = isset($rqst['ubicacion']) ? ($rqst['ubicacion']) : '';
        $administrador = isset($rqst['administrador']) ? ($rqst['administrador']) : '';
        $email = isset($rqst['email']) ? ($rqst['email']) : '';
        $celular = isset($rqst['celular']) ? ($rqst['celular']) : '';
        $contact1 = isset($rqst['contact1']) ? ($rqst['contact1']) : '';
        $email1 = isset($rqst['email1']) ? ($rqst['email1']) : '';
        $contact2 = isset($rqst['contact2']) ? ($rqst['contact2']) : '';
        $email2 = isset($rqst['email2']) ? ($rqst['email2']) : '';
        $contact3 = isset($rqst['contact3']) ? ($rqst['contact3']) : '';
        $email3 = isset($rqst['email3']) ? ($rqst['email3']) : '';
        $contact4 = isset($rqst['contact4']) ? ($rqst['contact4']) : '';
        $email4 = isset($rqst['email4']) ? ($rqst['email4']) : '';
        $enable = isset($rqst['enable']) ? ($rqst['enable']) : 'si';
        $telefono_emergencia = isset($rqst['telefono_emergencia']) ? ($rqst['telefono_emergencia']) : '';

        $db = new DbConection();
        $pdo = $db->openConect();

        if ($id > 0) {
            //actualiza la informacion
            $q = "SELECT id  FROM " . $db->getTable('tbl_unidades') . " WHERE id = " . $id;
            $result = $pdo->query($q);
            if ($result) {
                $table = $db->getTable('tbl_unidades');
                $arrfieldscomma = array(
                    'nombre' => $nombre,
                    'ubicacion' => $ubicacion,
                    'administrador' => $administrador,
                    'celular' => $celular,
                    'email' => $email,
                    'contact1' => $contact1,
                    'email1' => $email1,
                    'contact2' => $contact2,
                    'email2' => $email2,
                    'contact3' => $contact3,
                    'email3' => $email3,
                    'contact4' => $contact4,
                    'email4' => $email4,
                    'enable' => $enable,
                    'telefono_emergencia' => $telefono_emergencia,
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
            if ($nombre != "") {
                $q = "INSERT INTO " . $db->getTable('tbl_unidades') . " (dtcreate, nombre, ubicacion, administrador, celular, email, contact1, email1, contact2, email2, contact3, email3, contact4, email4, enable, telefono_emergencia) 
                                    VALUES (" . Util::date_now_server() . ", :nombre, :ubicacion, :administrador, :celular, :email, :contact1, :email1, :contact2, :email2, :contact3, :email3, :contact4, :email4, :enable, :telefono_emergencia)";
                $result = $pdo->prepare($q);
                $arrparam = array(
                    ':nombre' => $nombre,
                    ':ubicacion' => $ubicacion,
                    ':administrador' => $administrador,
                    ':celular' => $celular,
                    ':email' => $email,
                    ':contact1' => $contact1,
                    ':email1' => $email1,
                    ':contact2' => $contact2,
                    ':email2' => $email2,
                    ':contact3' => $contact3,
                    ':email3' => $email3,
                    ':contact4' => $contact4,
                    ':email4' => $email4,
                    ':enable' => $enable,
                    ':telefono_emergencia' => $telefono_emergencia,
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

    public static function delete($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "DELETE FROM " . $db->getTable('tbl_unidades') . " WHERE id = " . $id;
        $result = $pdo->query($q);
        if ($result) {
            $arrjson = array('output' => array('valid' => true, 'error' => $pdo->errorInfo()));
        } else {
            Util::trace_log_error($rqst, 'Unidades::delete ' . $id, $pdo->errorInfo());
            $arrjson = Util::error_generaldelete();
        }
        $db->closeConect();
        return $arrjson;
    }

    public static function enable($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        Util::trace_log($rqst, 'Unidades::enable ' . $id);
        $enable = isset($rqst['enable']) ? ($rqst['enable']) : 'si';

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "UPDATE " . $db->getTable('tbl_unidades') . " SET enable = '$enable' WHERE id = " . $id;
        $result = $pdo->query($q);
        $arr = array();
        if ($result) {
            $arrjson = array('output' => array('valid' => true, 'response' => $arr, 'error' => $pdo->errorInfo()));
        } else {
            $arrjson = Util::error_general($pdo->errorInfo());
        }
        $db->closeConect();
        return $arrjson;
    }

    public static function search($rqst)
    {
        $search = isset($rqst['search']) ? ($rqst['search']) : '';

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "SELECT * FROM " . $db->getTable('tbl_unidades') . " 
        WHERE nombre  LIKE '%$search%'  OR
            ubicacion  LIKE '%$search%' LIMIT 200 ";

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
