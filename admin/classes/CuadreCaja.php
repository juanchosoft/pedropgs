<?php

/**
 * Clase que contiene todas las operaciones utilizadas sobre la base de datos
 * @author SPIDERSOFTWARE
 */
class CuadreCaja {

    public function __construct(){}


    /**
     * Metodo para recuperar todos los registros
     * @return array de las Resoluciones
     */
    public static function getAll($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "SELECT * FROM " . $db->getTable('tec_effective_summary') . " ORDER BY id DESC LIMIT 100";

        if ($id > 0) {
            $q = "SELECT * FROM " . $db->getTable('tec_effective_summary') . " WHERE id = " . $id;
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
   
    /**
     * Metodo para guardar o actualizar un registro
     * @param REQUEST $rqst
     * @return array de Resoluciones
     * 
     */
    public static function save($rqst)
    
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        Util::trace_log($rqst, 'CuadreCaja::save ' . $id);
        $base_caja = isset($rqst['base_caja']) ? ($rqst['base_caja']) : '';
        $total_ventas = isset($rqst['total_ventas']) ? ($rqst['total_ventas']) : '';
        $total_creditos = isset($rqst['total_creditos']) ? ($rqst['total_creditos']) : '';
        $total_tdebito = isset($rqst['total_tdebito']) ? ($rqst['total_tdebito']) : '';
        $total_tcredito = isset($rqst['total_tcredito']) ? ($rqst['total_tcredito']) : '';
        $total_transferencia = isset($rqst['total_transferencia']) ? ($rqst['total_transferencia']) : '';
        $dinero_contado = isset($rqst['dinero_contado']) ? ($rqst['dinero_contado']) : '';
        $total_dinero = isset($rqst['total_dinero']) ? ($rqst['total_dinero']) : '';
        $dinero_contado = isset($rqst['dinero_contado']) ? ($rqst['dinero_contado']) : '';
        $diferencia = isset($rqst['diferencia']) ? ($rqst['diferencia']) : '';
        $observaciones = isset($rqst['observaciones']) ? ($rqst['observaciones']) : '';

        $db = new DbConection();
        $pdo = $db->openConect();

        if ($id > 0) {
            //actualiza la informacion
            $q = "SELECT id  FROM " . $db->getTable('tec_effective_summary') . " WHERE id = " . $id;
            $result = $pdo->query($q);
            if ($result) {
                $table = $db->getTable('tec_effective_summary');
                $arrfieldscomma = array(
                    'base_caja' => $base_caja,
                    'total_ventas' => $total_ventas,
                    'total_creditos' => $total_credito,
                    'total_tdebito' => $total_tdebito,
                    'total_tcredito' => $total_tcredito,
                    'total_transferencia' => $total_transferencia,
                    'total_dinero ' => $total_dinero ,
                    'dinero_contado' => $dinero_contado,
                    'diferencia' => $diferencia,
                    'observaciones' => $observaciones);
                   
                $arrfieldsnocomma = array('dtcreate' => Util::date_now_server());
                $q = Util::make_query_update($table, "id = '$id'", $arrfieldscomma, $arrfieldsnocomma);
                $result = $pdo->query($q);
                if (!$pdo->query($q)) {
                    Util::trace_log_error($rqst, 'CuadreCaja::save ' . $id, $pdo->errorInfo());
                    $arrjson = Util::error_general('Actualizando las Resoluciones del Cliente');
                } else {
                    $arrjson = array('output' => array('valid' => true, 'id' => $id));
                }
            }
        } else {
            if ($resolucion != "") {
                $q = "INSERT INTO " . $db->getTable('tec_effective_summary') . " (dtcreate, base_caja, total_ventas, total_creditos, total_tdebito, total_tcredito, total_transferencia, total_dinero, dinero_contado, diferencia, observaciones)  
                                                                          VALUES (" . Util::date_now_server() . ", :base_caja, :total_ventas, :total_creditos, :total_tdebito, :total_tcredito, :total_transferencia, :total_dinero, :dinero_contado, :diferencia, :observaciones)";
                $result = $pdo->prepare($q);
                $arrparam = array(
                    'base_caja' => $base_caja,
                    'total_ventas' => $total_ventas,
                    'total_creditos' => $total_credito,
                    'total_tdebito' => $total_tdebito,
                    'total_tcredito' => $total_tcredito,
                    'total_transferencia' => $total_transferencia,
                    'total_dinero ' => $total_dinero ,
                    'dinero_contado' => $dinero_contado,
                    'diferencia' => $diferencia,
                    'observaciones' => $observaciones);
                if ($result->execute($arrparam)) {
                    $arrjson = array('output' => array('valid' => true, 'response' => $pdo->lastInsertId()));
                } else {
                    Util::trace_log_error($rqst, 'CuadreCaja::save ' . $id, $pdo->errorInfo());
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
