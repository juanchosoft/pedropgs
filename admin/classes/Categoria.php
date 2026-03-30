<?php

/**
 * Clase que contiene todas las operaciones utilizadas sobre la base de datos
 * @author SPIDERSOFTWARE
 */
class Categoria {

    public function __construct(){}

    /**
     * Metodo para recuperar todos los registros
     * @return array de las categorias
     */
    public static function getAll($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "SELECT * FROM " . $db->getTable('tec_categories') . " ORDER BY tec_categories.name ASC LIMIT 100";
        if ($id > 0) {
            $q = "SELECT * FROM " . $db->getTable('tec_categories') . " WHERE id = " . $id;
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
     * @return array de categorias
     */
    public static function save($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;

        $code = isset($rqst['code']) ? ($rqst['code']) : '';
        $name = isset($rqst['name']) ? ($rqst['name']) : '';
        $group_category = isset($rqst['group_category']) ? ($rqst['group_category']) : '';
        $enable = isset($rqst['enable']) ? ($rqst['enable']) : '';
        $imagefileToUpload = isset($_SESSION['file']['nombrearchivo']) ? ($_SESSION['file']['nombrearchivo']) : '';
        if(isset($_SESSION['file']['nombrearchivo'])){
            $_SESSION['file']['nombrearchivo'] = NULL;
        }

        $db = new DbConection();
        $pdo = $db->openConect();

        if ($id > 0) {
            //actualiza la informacion
            $q = "SELECT id  FROM " . $db->getTable('tec_categories') . " WHERE id = " . $id ;
            $result = $pdo->query($q);
            if ($result) {
                $table = $db->getTable('tec_categories');
                $arrfieldscomma = array(
                    'code' => $code,
                    'name' => $name,
                    'group_category' => $group_category,
                    'image' => $imagefileToUpload,
                    'enable' => $enable);
                $arrfieldsnocomma = array('dtcreate' => Util::date_now_server());
                $q = Util::make_query_update($table, "id = '$id'", $arrfieldscomma, $arrfieldsnocomma);
                $result = $pdo->query($q);
                if(!$result){
                    Util::trace_log_error($rqst, 'Usuario::save ' . $id, $pdo->errorInfo());
                    $arrjson = Util::error_general('Actualizando los datos de categoria');
                }else{
                    $arrjson = array('output' => array('valid' => true, 'id' => $id));
                }
            }
        } else {
            if ($code != "") {
                $q = "INSERT INTO " . $db->getTable('tec_categories') . " (dtcreate, code, name, group_category, enable, image) VALUES (" . Util::date_now_server() . ", :code, :name, :group_category,:enable, :image)";
                $result = $pdo->prepare($q);
                $arrparam = array(
                    ':code' => $code,
                    ':name' => $name,
                    ':group_category' => $group_category,
                    ':image' => $imagefileToUpload,
                    ':enable' => $enable);
                if ($result->execute($arrparam)) {
                    $arrjson = array('output' => array('valid' => true, 'response' => $pdo->lastInsertId()));
                } else {
                    Util::trace_log_error($rqst, 'Categoria::save ' . $id, $pdo->errorInfo());
                    $arrjson = Util::error_general();
                }
            } else {
                $arrjson = Util::error_missing_data();
            }
        }
        $db->closeConect();
        return $arrjson;
    }

    /**
     * Metodo para eliminar un registro por Id
     * @param REQUEST $rqst
     * @return array de usuario
     */
    public static function delete($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        Util::trace_log($rqst, 'Categoria::delete ' . $id);

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "DELETE FROM " . $db->getTable('tec_categories') . " WHERE id = " . $id;
        $result = $pdo->query($q);
        if ($result) {
            $arrjson = array('output' => array('valid' => true, 'error' => $pdo->errorInfo()));
        } else {
            Util::trace_log_error($rqst, 'Categoria::delete ' . $id, $pdo->errorInfo());
            $arrjson = Util::error_generaldelete();
        }
        $db->closeConect();
        return $arrjson;
    }

    /**
     * Metodo para habilitar/deshabilitar un registro por Id
     * @param REQUEST $rqst
     * @return array de usuario
     */
    public static function enable($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        Util::trace_log($rqst, 'Categoria::enable ' . $id);
        $enable = isset($rqst['enable']) ? ($rqst['enable']) : 'si';

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "UPDATE " . $db->getTable('tec_categories') . " SET enable = '$enable' WHERE id = " . $id;
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

/**
 * Metodo para eliminar un registro por Id
 * @param REQUEST $rqst
 * @return array de Categoria
 */

    public static function search($rqst){
        $search = isset($rqst['search']) ? ($rqst['search']) : '';

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "SELECT * FROM " . $db->getTable('tec_categories') . " 
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
