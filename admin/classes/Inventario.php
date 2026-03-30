<?php
/**
 * Clase que contiene todas las operaciones utilizadas sobre la base de datos
 * @author SPIDERSOFTWARE
 */
class Inventario
{
        public function __construct()
    {
    }
    /**
     * Metodo para guardaro actualizar un registro
     * @param REQUEST $rqst
     * @return array de categorias
     */
    public static function saveSalida($rqst){
        $motivo = isset($rqst['motivo']) ? ($rqst['motivo']) : '';
        $autoriza = isset($rqst['autoriza']) ? ($rqst['autoriza']) : '';
        $cantidad = isset($rqst['cantidad']) ? ($rqst['cantidad']) : '';
        $tec_product_id = isset($rqst['tec_product_id']) ? intval($rqst['tec_product_id']) : '';
        $tec_usuario_id = $_SESSION['session_user']['id'];
        $db = new DbConection();
        $pdo = $db->openConect();
        if ($autoriza != "" || $cantidad > 0 || $cantidad > 0 || $autoriza != "" || $tec_usuario_id > 0 ) {
                $q = "INSERT INTO " . $db->getTable('tec_inventory_output') . " (dtcreate, autoriza, cantidad, tec_product_id, tec_usuario_id, motivo) VALUES (" . Util::date_now_server() . ", :autoriza, :cantidad, :tec_product_id, :tec_usuario_id, :motivo)";
                $result = $pdo->prepare($q);
                $arrparam = array(
                    ':autoriza' => $autoriza,
                    ':cantidad' => $cantidad,
                    ':tec_product_id' => $tec_product_id,
                    ':tec_usuario_id' => $tec_usuario_id,
                    ':motivo' => $motivo
                );
                if ($result->execute($arrparam)) {
                    $qUpdateProd = "UPDATE " . $db->getTable('tec_products') . "
                    SET cant_actual = (cant_actual) - $cantidad
                    WHERE id = " . $tec_product_id;
                    $result = $pdo->query($qUpdateProd);
                    if (!$result) {
                        $db->closeConect();
                        return $arrjson = Util::error_general('Actualizando inventario');
                    }
                    $arrjson = array('output' => array('valid' => true, 'response' => $pdo->lastInsertId()));
                } else {
                    Util::trace_log_error($rqst, 'Inventario::save ', $pdo->errorInfo());
                    $arrjson = Util::error_general();
                }
        } else {
            $arrjson = Util::error_missing_data();
        }
        $db->closeConect();
        return $arrjson;
    }
    public static function saveAjuste($rqst){
        $motivo = isset($rqst['motivo']) ? ($rqst['motivo']) : '';
        $accion = isset($rqst['accion']) ? ($rqst['accion']) : '';
        $cantidad = isset($rqst['cantidad']) ? ($rqst['cantidad']) : '';
        $tec_product_id = isset($rqst['tec_product_id']) ? intval($rqst['tec_product_id']) : '';
        $tec_usuario_id = $_SESSION['session_user']['id'];
        $db = new DbConection();
        $pdo = $db->openConect();
        if ($accion != "" || $cantidad > 0 || $accion != "" || $tec_usuario_id > 0 || $tec_product_id > 0) {
                $qProd = "SELECT id, cant_actual  FROM " . $db->getTable('tec_products') . " WHERE id = " . $tec_product_id;
                $resultProd = $pdo->query($qProd);
                $cantidadActual = 0;
                if ($resultProd) {
                    foreach ($resultProd as $valor) {
                        $cantidadActual = $valor['cant_actual'];
                    }
                }
                $q = "INSERT INTO " . $db->getTable('tec_inventory_settings') . " (dtcreate, accion, cantidad, tec_product_id, tec_usuario_id, motivo) VALUES (" . Util::date_now_server() . ", :accion, :cantidad, :tec_product_id, :tec_usuario_id, :motivo)";
                $result = $pdo->prepare($q);
                $arrparam = array(
                    ':accion' => $accion,
                    ':cantidad' => $cantidad,
                    ':tec_product_id' => $tec_product_id,
                    ':tec_usuario_id' => $tec_usuario_id,
                    ':motivo' => $motivo
                );
                if ($result->execute($arrparam)) {
                    if($accion == 'sumar'){
                    if( $cantidadActual < 0 ) {
                            $qUpdateProd = "UPDATE " . $db->getTable('tec_products') . "
                            SET cant_actual = $cantidad
                            WHERE id = " . $tec_product_id;
                        } else {
                            $qUpdateProd = "UPDATE " . $db->getTable('tec_products') . "
                            SET cant_actual = (cant_actual) + $cantidad
                            WHERE id = " . $tec_product_id;
                        }
                    }else{
                        if($accion == 'restar'){
                            if( $cantidadActual < 0 ) {
                                $qUpdateProd = "UPDATE " . $db->getTable('tec_products') . "
                                SET cant_actual = $cantidad
                                WHERE id = " . $tec_product_id;
                            } else {
                                $qUpdateProd = "UPDATE " . $db->getTable('tec_products') . "
                                SET cant_actual = (cant_actual) - $cantidad
                                WHERE id = " . $tec_product_id;
                            }
                        }
                    }
                    $resultUpodate = $pdo->query($qUpdateProd);
                    if (!$resultUpodate) {
                        $db->closeConect();
                        return $arrjson = Util::error_general('Actualizando inventario');
                   }
                    $arrjson = array('output' => array('valid' => true, 'response' => $pdo->lastInsertId()));
                } else {
                    Util::trace_log_error($rqst, 'Inventario::save ', $pdo->errorInfo());
                    $arrjson = Util::error_general();
                }
        } else {
            $arrjson = Util::error_missing_data();
        }
        $db->closeConect();
        return $arrjson;
    }
    public static function getMovimientosDetalladoSalidas($rqst){
        $tec_product_id = isset($rqst['tec_product_id']) ? intval($rqst['tec_product_id']) : '';
        $db = new DbConection();
        $pdo = $db->openConect();
        if ($tec_product_id > 0) {
                // Información del producto
                $q0 = "SELECT tec_products.*, tec_categories.name as categoria
                FROM " . $db->getTable('tec_products') . "," . $db->getTable('tec_categories') . "
                WHERE tec_products.categoria_id = tec_categories.id AND tec_products.id = " . $tec_product_id;
                $result0 = $pdo->query($q0);
                foreach ($result0 as $valor) {
                    $arr0[] = $valor;
                }
               
                 // Movimientos de las salidas de inventario
                $q1= "SELECT tec_inventory_output.id, tec_products.id AS tec_product_id, tec_products.nombre_prod, tec_inventory_output.cantidad,  tec_inventory_output.motivo, tec_inventory_output.autoriza
                FROM " . $db->getTable('tec_products') . " 
                INNER JOIN " . $db->getTable('tec_inventory_output') . " ON tec_products.id = tec_inventory_output.tec_product_id
                WHERE tec_products.id = " . $tec_product_id;
                $result1 = $pdo->query($q1);
                $arr1 = array();
                foreach ($result1 as $valor1) {
                    $arr1[] = $valor1;
                }
                // Ajustes de inventario
                $q2 = "SELECT tec_inventory_settings.id, tec_products.id AS tec_product_id , tec_products.nombre_prod, tec_inventory_settings.accion, tec_inventory_settings.cantidad, tec_inventory_settings.motivo, tec_inventory_settings.accion 
                FROM " . $db->getTable('tec_products') . " INNER JOIN " . $db->getTable('tec_inventory_settings') . "  ON tec_products.id = tec_inventory_settings.tec_product_id
                WHERE tec_products.id = " . $tec_product_id;
                $result2= $pdo->query($q2);
                $arr2 = array();
                foreach ($result2 as $valor2) {
                    $arr2[] = $valor2;
                }
              
                $arrjson = array('output' => array(
                    'valid' => true,
                    'producto' => $arr0,
                    'arr1' => $arr1,
                    'arr2' => $arr2,
                ));

        } else {
            $arrjson = Util::error_missing_data();
        }
        $db->closeConect();
        return $arrjson;        
    }
}


