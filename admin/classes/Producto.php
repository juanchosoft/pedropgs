<?php

class Producto
{

    public function __construct()
    {
    }

    public static function getAll($rqst)
    {

        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        $tec_category_id = isset($rqst['tec_category_id']) ? intval($rqst['tec_category_id']) : 0;

        $db = new DbConection();
        $pdo = $db->openConect();

        
        $q= "SELECT tec_products.*, tbl_unidades.nombre AS unidad, 
        tec_categories.name, tec_categories.group_category
        FROM ( " . $db->getTable('tec_products') . "  INNER JOIN " . $db->getTable('tbl_unidades') ."  ON tec_products.tbl_unidad_id = tbl_unidades.id)
        INNER JOIN " . $db->getTable('tec_categories') . "  ON tec_products.tec_category_id = tec_categories.id";




        if ($id > 0) {
            $q = "SELECT * FROM " . $db->getTable('tec_products') . " WHERE id = " . $id;
        }
        if ($tec_category_id > 0) {
            $q = "SELECT * FROM " . $db->getTable('tec_products') . " WHERE tec_category_id = " . $tec_category_id . "  ORDER BY nombre_prod";
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
        $tipo = isset($rqst['tipo']) ? ($rqst['tipo']) : '';
        $codigo = isset($rqst['codigo']) ? ($rqst['codigo']) : '';
        $nombre_prod = isset($rqst['nombre_prod']) ? ($rqst['nombre_prod']) : '';
        $presentacion = isset($rqst['presentacion']) ? ($rqst['presentacion']) : '';
        $tbl_unidad_id = isset($rqst['tbl_unidad_id']) ? intval($rqst['tbl_unidad_id']) : 0;
        $tec_category_id = isset($rqst['tec_category_id']) ? intval($rqst['tec_category_id']) : 0;
        $costo = isset($rqst['costo']) ? ($rqst['costo']) : '';
        $descripcion = isset($rqst['descripcion']) ? ($rqst['descripcion']) : '';
        $quantity = isset($rqst['quantity']) ? intval($rqst['quantity']) : 0;
        $cant_ini = isset($rqst['cant_ini']) ? intval($rqst['cant_ini']) : 0;
        $cant_minima = isset($rqst['cant_minima']) ? intval($rqst['cant_minima']) : 0;
        $enable = isset($rqst['enable']) ? ($rqst['enable']) : 'si';
        $imagefileToUpload = isset($_SESSION['file']['nombrearchivo']) ? ($_SESSION['file']['nombrearchivo']) : '';

        if (isset($_SESSION['file']['nombrearchivo'])) {
            $_SESSION['file']['nombrearchivo'] = NULL;
        }

        $db = new DbConection();
        $pdo = $db->openConect();

        if ($id > 0) {
            //actualiza la informacion
            $q = "SELECT id  FROM " . $db->getTable('tec_products') . " WHERE id = " . $id;

            $result = $pdo->query($q);

            if ($result) {

                $table = $db->getTable('tec_products');

                $arrfieldscomma = array(
                    'tipo ' => $tipo,
                    'codigo' => $codigo,
                    'nombre_prod' => $nombre_prod,
                    'presentacion' => $presentacion,
                    'tbl_unidad_id' => $tbl_unidad_id,
                    'tec_category_id' => $tec_category_id,
                    'costo' => $costo,
                    'descripcion' => $descripcion,
                    'image' => $imagefileToUpload,
                    'quantity' => $quantity,
                    'cant_ini' => $cant_ini,
                    'cant_minima' => $cant_minima,
                    'enable' => $enable,
                );
                $arrfieldsnocomma = array('dtcreate' => Util::date_now_server());
                $q = Util::make_query_update($table, "id = '$id'", $arrfieldscomma, $arrfieldsnocomma);
                $result = $pdo->query($q);

                if (!$pdo->query($q)) {

                    $parrams = array('codigo' => $codigo);
                    $response = Producto::available($parrams);
                    if (!$response['output']['valid']) {
                        // Obtemos el valor de la imagen del producto
                        foreach ($result as $valor) {
                            $file = $valor['image'];
                        }
                        // Eliminamos el archivo anterior
                        if ($file != "" && file_exists($file)) {
                            unlink("../assets/img/admin/" . $file);
                        }
                        $arrjson = $response;
                    } else {
                        $arrjson = Util::error_general('Actualizando los datos del producto');
                    }
                } else {
                    $arrjson = array('output' => array('valid' => true, 'id' => $id));
                }
            }
        } else {

            if ($codigo != "") {
                // Se valida que el codigo ingresado no exista
                $parrams = array('codigo' => $codigo);
                $response = Producto::available($parrams);
                if ($response['output']['valid']) {

                    $q = "INSERT INTO " . $db->getTable('tec_products') . " 
                    (dtcreate, tipo, codigo, nombre_prod, presentacion, tbl_unidad_id, tec_category_id, costo, descripcion, quantity, cant_ini, cant_actual, image, enable, cant_minima) 
                    VALUES (" . Util::date_now_server() . ", :tipo, :codigo, :nombre_prod, :presentacion, :tbl_unidad_id, :tec_category_id, :costo, :descripcion, :quantity, :cant_ini, :cant_actual, :image, :enable, :cant_minima )";
                    $result = $pdo->prepare($q);

                    $arrparam = array(
                        ':tipo' => $tipo,
                        ':codigo' => $codigo,
                        ':nombre_prod' => $nombre_prod,
                        ':presentacion' => $presentacion,
                        ':tbl_unidad_id' => $tbl_unidad_id,
                        ':tec_category_id' => $tec_category_id,
                        ':costo' => $costo,
                        ':descripcion' => $descripcion,
                        ':quantity' => $quantity,
                        ':cant_ini' => $cant_ini,
                        ':cant_actual' => $cant_ini,
                        ':image' => $imagefileToUpload,
                        ':enable' => $enable,
                        ':cant_minima' => $cant_minima,
                    );

                    if ($result->execute($arrparam)) {
                        $arrjson = array('output' => array('valid' => true, 'response' => $pdo->lastInsertId()));
                    } else {
                        $arrjson = Util::error_general();
                    }
                } else {
                    $arrjson = $response;
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
        $q = "DELETE FROM " . $db->getTable('tec_products') . " WHERE id = " . $id;
        $result = $pdo->query($q);
        if ($result) {
            $arrjson = array('output' => array('valid' => true, 'error' => $pdo->errorInfo()));
        } else {
            Util::trace_log_error($rqst, 'Producto::delete ' . $id, $pdo->errorInfo());
            $arrjson = Util::error_generaldelete();
        }
        $db->closeConect();
        return $arrjson;
    }
    public static function enable($rqst)
    {

        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        $enable = isset($rqst['enable']) ? ($rqst['enable']) : 'si';
        $db = new DbConection();
        $pdo = $db->openConect();
        $q = "UPDATE " . $db->getTable('tec_products') . " SET enable = '$enable' WHERE id = " . $id;
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
    public static function available($rqst)
    {
        $codigo = isset($rqst['codigo']) ? ($rqst['codigo']) : '';
        $db = new DbConection();
        $pdo = $db->openConect();
        $q = "SELECT * FROM " . $db->getTable('tec_products') . " WHERE codigo = :codigo";
        $result = $pdo->prepare($q);
        $arr = array();
        $arrparam = array(":codigo" => $codigo);
        if ($result->execute($arrparam)) {
            foreach ($result as $valor) {
                $arr[] = $valor;
            }
            if (count($arr) > 0) {
                $arrjson = Util::error_general('The code already exists');
            } else {
                $arrjson = array('output' => array('valid' => true, 'response' => 'available'));
            }
        } else {
            $arrjson = Util::error_general('Consultado codigo del producto');
        }
        $db->closeConect();
        return $arrjson;
    }
}
