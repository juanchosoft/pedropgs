<?php

/**
 * Clase que contiene todas las operaciones utilizadas sobre la base de datos
 * @author SPIDERSOFTWARE
 */
class Cliente {

    public function __construct(){}


    /**
     * Metodo para recuperar todos los registros
     * @return array de las Cliente
     */
    public static function getAll($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "SELECT * FROM " . $db->getTable('tec_customers') . " ORDER BY id DESC LIMIT 100";

        if ($id > 0) {
            $q = "SELECT * FROM " . $db->getTable('tec_customers') . " WHERE id = " . $id;
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
     * Funcion para validar si el documento ingresado ya existe
     * [available description]
     * @param  [type] $rqst [description]
     * @return [type]       [description]
     */
    public static function availableDocument($rqst)
    {
        $identificacion_num = isset($rqst['identificacion_num']) ? ($rqst['identificacion_num']) : '';

        $db = new DbConection();
        $pdo = $db->openConect();

        if ($identificacion_num != "") {
            $q = "SELECT * FROM " . $db->getTable('tec_customers') . " WHERE identificacion_num = :identificacion_num ";
            $result = $pdo->prepare($q);
            $arr = array();
            $arrparam = array(":identificacion_num" => $identificacion_num);
            if ($result->execute($arrparam)) {
                foreach ($result as $valor) {
                    $arr[] = $valor;
                }
                if (count($arr) > 0) {
                    $arrjson = Util::error_documentoduplicado($identificacion_num);
                } else {
                    $arrjson = array('output' => array('valid' => true, 'response' => 'available'));
                }
            } else {
                $arrjson = Util::error_documentoduplicado($identificacion_num);
            }
        } else {
            $arrjson = Util::error_missing_data('El documento es obligatorio');
        }
        $db->closeConect();
        return $arrjson;
    }

    /**
     * Metodo para validar el numero y celular del cliente
     * Que no halla sido registrado en el sistema
     */
    public static function availablePhone($rqst) {

        $telefono = isset($rqst['telefono']) ? ($rqst['telefono']) : '';

        $db = new DbConection();
        $pdo = $db->openConect();

        if ($telefono != "") {
            $q = "SELECT * FROM " . $db->getTable('tec_customers') . " WHERE telefono = :telefono LIMIT 1";
            $result = $pdo->prepare($q);
            $arr = array();
            $arrparam = array(":telefono" => $telefono);
            if ($result->execute($arrparam)) {
                foreach ($result as $valor) {
                    $arr[] = $valor;
                }
                if (count($arr) > 0) {
                    $arrjson = Util::error_telefonoduplicado( $telefono );
                } else {
                    $arrjson = array('output' => array('valid' => true, 'response' => 'available'));
                }
            } else {
                $arrjson = Util::error_general('Consultando los datos del cliente');
            }
        } else {
            $arrjson = Util::error_missing_data();
        }
        $db->closeConect();
        return $arrjson;
    }

    /**
     * Metodo para guardar o actualizar un registro
     * @param REQUEST $rqst
     * @return array de Cliente
     */
    public static function save($rqst)

    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        Util::trace_log($rqst, 'Cliente::save ' . $id);
        $identificacion_tipo = isset($rqst['identificacion_tipo']) ? ($rqst['identificacion_tipo']) : '';
        $identificacion_num = isset($rqst['identificacion_num']) ? ($rqst['identificacion_num']) : '';
        $nombre = isset($rqst['nombre']) ? ($rqst['nombre']) : '';
        $direccion = isset($rqst['direccion']) ? ($rqst['direccion']) : '';
        $ubicacion = isset($rqst['ubicacion']) ? ($rqst['ubicacion']) : '';
        $telefono = isset($rqst['telefono']) ? ($rqst['telefono']) : '';
        $celular = isset($rqst['celular']) ? ($rqst['celular']) : '';
        $cupo = isset($rqst['cupo']) ? ($rqst['cupo']) : '0';
        $saldo = isset($rqst['saldo']) ? ($rqst['saldo']) : '0';
        $email = isset($rqst['email']) ? ($rqst['email']) : '';
        $tel_contacto = isset($rqst['tel_contacto']) ? ($rqst['tel_contacto']) : '';
        $contacto = isset($rqst['contacto']) ? ($rqst['contacto']) : '';
        $enable = isset($rqst['enable']) ? ($rqst['enable']) : 'si';

        $autoretenedor = isset($rqst['autoretenedor']) ? ($rqst['autoretenedor']) : 'no';
        $reteica= isset($rqst['reteica']) ? ($rqst['reteica']) : 'no';
        $dv = isset($rqst['dv']) ? ($rqst['dv']) : '';
        $ciudad = isset($rqst['ciudad']) ? ($rqst['ciudad']) : '';
        $departamento = isset($rqst['departamento']) ? ($rqst['departamento']) : '';

        $db = new DbConection();
        $pdo = $db->openConect();

        if ($id > 0) {
            //actualiza la informacion
            $q = "SELECT id, identificacion_num, telefono  FROM " . $db->getTable('tec_customers') . " WHERE id = " . $id;
            $result = $pdo->query($q);
            if ($result) {
                $table = $db->getTable('tec_customers');
                $arrfieldscomma = array(
                    'identificacion_tipo' => $identificacion_tipo,
                    'identificacion_num' => $identificacion_num,
                    'nombre' => $nombre,
                    'direccion' => $direccion,
                    'ubicacion' => $ubicacion,
                    'telefono' => $telefono,
                    'celular' => $celular,
                    'cupo' => $cupo,
                    'email' => $email,
                    'contacto' => $contacto,
                    'tel_contacto' => $tel_contacto,
                    'saldo' => $saldo,
                    'enable' => $enable,
                    'dv' => $dv,
                    'ciudad' => $ciudad,
                    'departamento' => $departamento,
                    'autoretenedor' => $autoretenedor,
                    'reteica' => $reteica);

                    $identificacion_num_2 = 0;
                    $telefono_2 = 0;
                    foreach ($result as $valor) {
                        $identificacion_num_2 = $valor['identificacion_num'];
                        $telefono_2 = $valor['telefono'];
                    }

                    // Validamos el nùmero de telefono principal
                    if($telefono !== $telefono_2) {
                        $pCel = array('telefono' => $telefono);
                        $resCel = Cliente::availablePhone($pCel);
                        if( !$resCel['output']['valid'] ){
                            $db->closeConect();
                            return $resCel;
                        }
                    }

                    $vbalid = false;
                    // validacion del documento
                    if($identificacion_num !== $identificacion_num_2) {
                        $p = array('identificacion_num' => $identificacion_num);
                        $res = Cliente::availableDocument($p);

                        if( !$res['output']['valid'] ){
                            $db->closeConect();
                            return $res;
                        }
                    }

                    $arrfieldsnocomma = array('dtcreate' => Util::date_now_server());
                    $q = Util::make_query_update($table, "id = '$id'", $arrfieldscomma, $arrfieldsnocomma);
                    $result = $pdo->query($q);
                    if (!$pdo->query($q)) {
                        Util::trace_log_error($rqst, 'Cliente::save ' . $id, $pdo->errorInfo());
                        $arrjson = Util::error_general('Actualizando los datos del Cliente');
                    } else {
                        $arrjson = array('output' => array('valid' => true, 'id' => $id));
                    }

            }
        } else {
            if ($nombre != "") {

                // Se valida que el  telefono no este ingresado
                $pCel = array('telefono' => $telefono);
                $resCel = Cliente::availablePhone($pCel);
                if( $resCel['output']['valid'] ){

                    // se valida que el nùmero de documento no se repita
                    $p = array('identificacion_num' => $identificacion_num);
                    $res = Cliente::availableDocument($p);
                    if ($res['output']['valid']) {

                        $q = "INSERT INTO " . $db->getTable('tec_customers') . " (dtcreate, identificacion_tipo, identificacion_num, nombre, direccion, ubicacion, telefono, celular, cupo, email, saldo, saldo_restante, contacto, tel_contacto, enable, dv ,ciudad, departamento, autoretenedor, reteica)  VALUES (" . Util::date_now_server() . ", :identificacion_tipo, :identificacion_num, :nombre, :direccion, :ubicacion, :telefono, :celular, :cupo, :email, :saldo, :saldo_restante, :contacto, :tel_contacto, :enable, :dv , :ciudad ,  :departamento, :autoretenedor, :reteica)";
                        $result = $pdo->prepare($q);
                        $arrparam = array(
                            ':identificacion_tipo' => $identificacion_tipo,
                            ':identificacion_num' => $identificacion_num,
                            ':nombre' => $nombre,
                            ':direccion' => $direccion,
                            ':ubicacion' => $ubicacion,
                            ':telefono' => $telefono,
                            ':celular' => $celular,
                            ':cupo' => $cupo,
                            ':email' => $email,
                            ':tel_contacto' => $tel_contacto,
                            ':contacto' => $contacto,
                            ':saldo' => $saldo,
                            ':saldo_restante' => $saldo,
                            ':enable' => $enable,
                            ':dv' => $dv,
                            ':ciudad' => $ciudad,
                            ':departamento' => $departamento,
                            ':autoretenedor' => $autoretenedor,
                            ':reteica' => $reteica);
                                                    if ($result->execute($arrparam)) {
                            $arrjson = array('output' => array('valid' => true, 'response' => $pdo->lastInsertId()));
                        } else {
                            Util::trace_log_error($rqst, 'Cliente::save ' . $id, $pdo->errorInfo());
                            $arrjson = Util::error_general();
                        }
                    } else {
                        $arrjson = $res;
                    }

                }else {
                    $arrjson = $resCel;
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
     * @return array de Cliente
     */
    public static function delete($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "DELETE FROM " . $db->getTable('tec_customers') . " WHERE id = " . $id;
        $result = $pdo->query($q);
        if ($result) {
            $arrjson = array('output' => array('valid' => true, 'error' => $pdo->errorInfo()));
        } else {
            Util::trace_log_error($rqst, 'Cliente::delete ' . $id, $pdo->errorInfo());
            $arrjson = Util::error_generaldelete();
        }
        $db->closeConect();
        return $arrjson;
    }

    /**
     * Metodo para habilitar/deshabilitar un registro por Id
     * @param REQUEST $rqst
     * @return array de Cliente
     *
     */
    public static function enable($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        $enable = isset($rqst['enable']) ? ($rqst['enable']) : 'si';

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "UPDATE " . $db->getTable('tec_customers') . " SET enable = '$enable' WHERE id = " . $id;
        $result = $pdo->query($q);
        $arr = array();
        if ($result) {
            $arrjson = array('output' => array('valid' => true, 'response' => $arr, 'error' => $pdo->errorInfo()));
        } else {
            Util::trace_log_error($rqst, 'Cliente::enable ' . $id, $pdo->errorInfo());
            $arrjson = Util::error_general($pdo->errorInfo());
        }
        $db->closeConect();
        return $arrjson;
    }

    /**
     * Metodo para buscar un registro
     * @param REQUEST $rqst
     * @return array de Cliente
     */
    public static function search($rqst){
        $search = isset($rqst['search']) ? ($rqst['search']) : '';
        $telefono = isset($rqst['telefono']) ? ($rqst['telefono']) : '';
        $search_caja = isset($rqst['search_caja']) ? intval($rqst['search_caja']) : 0;

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "SELECT * FROM " . $db->getTable('tec_customers') . "
        WHERE identificacion_num  LIKE '%$search%'  OR
            nombre  LIKE '%$search%' LIMIT 200 ";

        if($search_caja > 0){
            $q = "SELECT * FROM " . $db->getTable('tec_customers') . " WHERE identificacion_num  = " . $search_caja . " LIMIT 1";
        }
        if($telefono !=""){
            $q = "SELECT * FROM " . $db->getTable('tec_customers') . " WHERE telefono  = " . $telefono . " LIMIT 1";
        }

        $result = $pdo->query($q);
        $arr = array();
        if ($result) {
            foreach ($result as $valor) {
                $arr[] = $valor;
            }
            if(count($arr) > 0){
                $arrjson = array('output' => array('valid' => true, 'response' => $arr));
            }else{
                if($search_caja > 0){
                    $arrjson = Util::error_documentonoexiste();
                }else{
                    $arrjson = Util::error_no_result();
                }
            }
        } else {
            $arrjson = Util::error_no_result();
        }
        $db->closeConect();
        return $arrjson;
    }
}
