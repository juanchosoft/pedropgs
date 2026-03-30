<?php

require 'Empleado.php';
/**
 * Clase que contiene todas las operaciones utilizadas sobre la base de datos
 * @author SPIDERSOFTWARE
 */

class Usuario
{
    public function __construct()
    {
    }

    public static function getAll($rqst)
    {

        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        $tipo = isset($rqst['tipo']) ? ($rqst['tipo']) : '';



        $db = new DbConection();

        $pdo = $db->openConect();
        $q = "SELECT * FROM " . $db->getTable('tec_usuarios') . " ORDER BY id DESC LIMIT 100";

        if ($id > 0) {
            $q = "SELECT * FROM " . $db->getTable('tec_usuarios') . " WHERE id = " . $id;
        }

        if ($tipo != "") {
            $q = "SELECT * FROM " . $db->getTable('tec_usuarios') . " WHERE tipo = '$tipo' AND habilitado = 'si' ORDER BY tec_usuarios.nombre asc";
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


    public static function available($rqst)
    {

        $nickname = isset($rqst['nickname']) ? ($rqst['nickname']) : '';

        $db = new DbConection();
        $pdo = $db->openConect();



        $q = "SELECT * FROM " . $db->getTable('tec_usuarios') . " WHERE nickname = :nickname";

        $result = $pdo->prepare($q);

        $arr = array();

        $arrparam = array(":nickname" => $nickname);

        if ($result->execute($arrparam)) {

            foreach ($result as $valor) {

                $arr[] = $valor;
            }

            if (count($arr) > 0) {
                $arrjson = Util::error_general('The user email already exists');
            } else {
                $arrjson = array('output' => array('valid' => true, 'response' => 'available'));
            }
        } else {
            $arrjson = Util::error_general('');
        }
        $db->closeConect();
        return $arrjson;
    }


    public static function login($rqst)
    {

        $nickname = isset($rqst['nickname']) ? ($rqst['nickname']) : '';
        $hashpass = isset($rqst['hashpass']) ? ($rqst['hashpass']) : '';

        $db = new DbConection();
        $pdo = $db->openConect();

        if (strlen($hashpass) > 2) {
            $hashpass = Util::make_hash_pass($hashpass);
        }

        $q = "SELECT * FROM " . $db->getTable('tec_usuarios') . " WHERE nickname = :nickname AND hashpass = :hashpass AND habilitado='yes'";
        $arrparam = array(":nickname" => $nickname, ":hashpass" => $hashpass);
        $result = $pdo->prepare($q);
        $arr = array();
        $tbl_unidad_id = 0;
        if ($result->execute($arrparam)) {

            foreach ($result as $valor) {
                $valor['application'][] = Util::get_app_id();
                $arr[] = $valor;
                $id = $valor['id'];
                $tbl_unidad_id = $valor['tbl_unidad_id'];
            }

            if (count($arr) > 0) {

                //Se consultan los perfiles asignados
                $q1 = "SELECT tec_permiso_id FROM " . $db->getTable('tec_usuarios_has_tec_permisos') . " WHERE tec_usuarios_id ='" . $id . "' ORDER BY tec_permiso_id ASC";
                $result1 = $pdo->query($q1);
                $arrassigned = array();
                foreach ($result1 as $valor2) {
                    $arrassigned[] = $valor2['tec_permiso_id'];
                }

                $valor['permisos'] = $arrassigned;
                $arr[] = $valor;

                // Información de la configuración
                $q4 = "SELECT * FROM " . $db->getTable('tec_config') . " ORDER BY id LIMIT 1 ";
                $result4 = $pdo->query($q4);
                $arr4 = array();
                if ($result4) {
                    foreach ($result4 as $valor4) {
                        $arr4[] = $valor4;
                    }
                }

                // Información de la configuración
                $q5 = "SELECT * FROM " . $db->getTable('tbl_unidades') . " WHERE id = $tbl_unidad_id ";
                $result5 = $pdo->query($q5);
                $telefono_emergencia = "";
                $unidad = "";
                if ($result5) {
                    foreach ($result5 as $valor5) {
                        $unidad = $valor5['nombre'];
                        $telefono_emergencia = $valor5['telefono_emergencia'];
                    }
                }
                $arrjson = array('output' => array('valid' => true, 'response' => $arr, 'permisos' => $arrassigned, 'config' => $arr4, 'telefono_emergencia' => $telefono_emergencia, 'unidad' => $unidad));
            } else {
                $arrjson = Util::error_wrong_data_login();
            }
        } else {
            $arrjson = Util::error_wrong_data_login();
        }





        $db->closeConect();

        return $arrjson;
    }

    public static function save($rqst)
    {

        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        $nickname = isset($rqst['nickname']) ? ($rqst['nickname']) : '';
        $hashpass = isset($rqst['hashpass']) ? ($rqst['hashpass']) : '';
        $nombre = isset($rqst['nombre']) ? ($rqst['nombre']) : '';
        $apellido = isset($rqst['apellido']) ? ($rqst['apellido']) : '';
        $tipo = isset($rqst['tipo']) ? ($rqst['tipo']) : '';
        $tbl_unidad_id =  isset($rqst['tbl_unidad_id']) ? intval($rqst['tbl_unidad_id']) : 0;
        $habilitado = isset($rqst['habilitado']) ? ($rqst['habilitado']) : '';
        $img = isset($_SESSION['file']['nombrearchivo']) ? ($_SESSION['file']['nombrearchivo']) : '';
        $tbl_usuario_id  = $_SESSION['session_user']['id'];

        $db = new DbConection();
        $pdo = $db->openConect();

        if (strlen($hashpass) > 2) {
            $hashpass = Util::make_hash_pass($hashpass);
        }

        if ($id > 0) {
            $q = "SELECT id, img FROM " . $db->getTable('tec_usuarios') . " WHERE id = " . $id;
            $result = $pdo->query($q);
            if ($result) {
                $table = $db->getTable('tec_usuarios');

                $arrfieldscomma = array(
                    'nickname' => $nickname,
                    'hashpass' => $hashpass,
                    'nombre' => $nombre,
                    'apellido' => $apellido,
                    'tipo' => $tipo,
                    'tbl_unidad_id' => $tbl_unidad_id,
                    'img' => $img,
                    'habilitado' => $habilitado
                );
                $arrfieldsnocomma = array('dtcreate' => Util::date_now_server());
                $q = Util::make_query_update($table, "id = '$id'", $arrfieldscomma, $arrfieldsnocomma);
                $result = $pdo->query($q);
                // Obtemos el valor de la imagen del producto
                $file = "";
                foreach ($result as $valor) {
                    $file = $valor['img'];
                }

                if (!$result) {
                    $arrjson = Util::error_general('Actualizando los datos del usuario');
                } else {
                    $arrjson = array('output' => array('valid' => true, 'id' => $id, 'img' => $file));
                    // Eliminamos el archivo anterior siempre y cuando se halla actualizado la imagen
                    if ($file != "" && file_exists($file)) {
                        unlink("../assets/img/admin/" . $file);
                    }
                }
            } else {
                $arrjson = Util::error_general();
            }
        } else {

            if ($nombre != "" && $nickname != "") {
                $q = "INSERT INTO " . $db->getTable('tec_usuarios') . " (dtcreate, nickname, hashpass, nombre, apellido,  tipo, tbl_unidad_id, img, habilitado) VALUES ( " . Util::date_now_server() . ", :nickname, :hashpass, :nombre, :apellido, :tipo, :tbl_unidad_id, :img, :habilitado)";
                $result = $pdo->prepare($q);
                $arrparam = array(
                    ':nickname' => $nickname,
                    ':hashpass' => $hashpass,
                    ':nombre' => $nombre,
                    ':apellido' => $apellido,
                    ':tipo' => $tipo,
                    ':tbl_unidad_id' => $tbl_unidad_id,
                    ':img' => $img,
                    ':habilitado' => $habilitado
                );

  
                if ($result->execute($arrparam)) {
                    $arrjson = array('output' => array('valid' => true, 'response' => $pdo->lastInsertId()));

                    $q = "INSERT INTO " . $db->getTable('tec_employee') . " 
                    (dtcreate, nombre, email, enable, tbl_unidad_id) VALUES 
                    (" . Util::date_now_server() . ", :nombre, :email, :enable, :tbl_unidad_id)";
                    $result = $pdo->prepare($q);
                    $arrparam = array(
                        ':nombre' => $nombre . " " . $apellido,
                        ':email' => $nickname,
                        ':enable' => 'si',
                        ':tbl_unidad_id' => $tbl_unidad_id,
                    );
                    if ($result->execute($arrparam)) {
                        $arrjson = array('output' => array('valid' => true, 'response' => $pdo->lastInsertId()));
                    } else {
                        $arrjson = Util::error_general('Saving records in employees');
                    }
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


        $q = "DELETE FROM " . $db->getTable('tec_usuarios') . " WHERE id = " . $id;
        $result = $pdo->query($q);
        if ($result) {

            $q1 = "DELETE FROM " . $db->getTable('tec_usuarios_has_tec_permisos') . " WHERE tec_usuarios_id  = " . $id;
            $result1 = $pdo->query($q1);

            $arrjson = array('output' => array('valid' => true, 'error' => $pdo->errorInfo()));
        } else {
            $arrjson = Util::error_generaldelete();
        }

        $db->closeConect();
        return $arrjson;
    }


    public static function enable($rqst)
    {

        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        $habilitado = isset($rqst['habilitado']) ? ($rqst['habilitado']) : 'si';



        $db = new DbConection();
        $pdo = $db->openConect();



        $q = "UPDATE " . $db->getTable('tec_usuarios') . " SET habilitado = '$habilitado' WHERE id = " . $id;

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


    public static function getConfisys($rqst)
    {



        $tipo = isset($rqst['tipo']) ? ($rqst['tipo']) : '';



        $db = new DbConection();

        $pdo = $db->openConect();



        $q = "SELECT * FROM " . $db->getTable('tec_confisys');

        $result = $pdo->query($q);

        $arr = array();

        if ($result) {

            foreach ($result as $valor) {

                $arr[] = $valor;
            }



            //Validamos cuantos usuarios hay creados por el tipo a crear

            $q1 = "SELECT count(*) FROM " . $db->getTable('tec_usuarios') . " WHERE tipo = " . $tipo;

            $result1 = $pdo->query($q1);

            $cantidad = 0;

            if ($result1) {

                foreach ($result1 as $valor1) {

                    $cantidad = $valor1;
                }
            }



            // Se validan las cantidades







            $arrjson = array('output' => array('valid' => true, 'response' => $arr));
        } else {

            $arrjson = Util::error_no_result();
        }

        $db->closeConect();

        return $arrjson;
    }
}
