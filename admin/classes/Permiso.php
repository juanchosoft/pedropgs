<?php

/**
 * Clase que contiene todas las operaciones utilizadas sobre la base de datos
 * @author SPIDERSOFTWARE
 */
class Permiso
{

    public function __construct()
    {}

    /**
     * Lsito todos los permisos que tiene o no asignados a un determinado Usuario
     * @param type $rqst
     * @return type id
     */
    public static function permisos($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;

        $db = new DbConection();
        $pdo = $db->openConect();

        //Se consultan los perfiles asignados
        $q = "SELECT * FROM " . $db->getTable('tec_usuarios_has_tec_permisos') . " WHERE tec_usuarios_id ='" . $id . "' ORDER BY tec_permiso_id ASC";
        $result = $pdo->query($q);
        $arrassigned = array();

        if ($result) {
            foreach ($result as $valor) {
                $arrassigned[] = $valor;
            }
            $q1 = "SELECT * FROM " . $db->getTable('tec_permisos') . " ORDER BY nombre ASC";
            $result1 = $pdo->query($q1);
            $arravailable = array();
            if ($result1) {
                foreach ($result1 as $valor1) {
                    $arravailable[] = $valor1;
                }
            }
            $arrjson = array('output' => array('valid' => true, 'available' => $arravailable, 'assigned' => $arrassigned));
        } else {
            $arrjson = Util::error_no_result();
        }
        $db->closeConect();
        return $arrjson;
    }

    /**
     * Función para guardar la información de los persmisos asignados a un determinado Usuario
     * @param type $rqst
     * @return type id
     */
    public static function savePermisos($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        $chk = isset($rqst['chk']) ? ($rqst['chk']) : '';

        $db = new DbConection();
        $pdo = $db->openConect();

        if ($id > 0) {
            //Se Elimina los perfiles asignados que tenia ese adminsitrador
            $q = "DELETE FROM " . $db->getTable('tec_usuarios_has_tec_permisos') . " WHERE tec_usuarios_id = '" . $id . "'";
            $result = $pdo->query($q);

            $arrchk = explode('-', $chk);
            for ($i = 0; $i < count($arrchk); $i++) {
                $prf_id = intval($arrchk[$i]);
                if ($prf_id > 0) {
                    $q1 = "INSERT INTO " . $db->getTable('tec_usuarios_has_tec_permisos') . " (dtcreate,tec_usuarios_id,tec_permiso_id)  VALUES ( " . Util::date_now_server() . ", $id, $prf_id)";
                    $result1 = $pdo->query($q1);
                    if ($result1) {
                        $arrjson = array('output' => array('valid' => true, 'response' => $pdo->lastInsertId()));
                    } else {
                        Util::trace_log_error($rqst, 'Permiso::save ' . $id, $pdo->errorInfo());
                        $arrjson = Util::error_general('Registrando permisos');
                    }

                }
            }
            $arrjson = array('output' => array('valid' => true, 'id' => $id));
        } else {
            $arrjson = Util::error_missing_data();
        }
        $db->closeConect();
        return $arrjson;
    }
}
