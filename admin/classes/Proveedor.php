<?php



/**

 * Clase que contiene todas las operaciones utilizadas sobre la base de datos

 * @author SPIDERSOFTWARE

 */

class Proveedor {



    public function __construct(){}





    public static function getAll($rqst)

    {

        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;

        $tec_provider_id = isset($rqst['tec_provider_id']) ? intval($rqst['tec_provider_id']) : 0;

        $nit = isset($rqst['nit']) ? intval($rqst['nit']) : 0;





        $db = new DbConection();

        $pdo = $db->openConect();



        $q = "SELECT * FROM " . $db->getTable('tec_suppliers') . " ORDER BY id DESC LIMIT 500";



        if ($id > 0) {

            $q = "SELECT * FROM " . $db->getTable('tec_suppliers') . " WHERE id = " . $id;

        }

        if ($nit > 0) {

            $q = "SELECT * FROM " . $db->getTable('tec_suppliers') . " WHERE numero_doc = " . $nit .  " LIMIT 1";

        }

        // Sacar los productos de mayor rotación del proveedor

        if ($tec_provider_id > 0) {



            $q= "SELECT tec_sales.id, tec_products.nombre_prod, tec_sales_tec_products.cantidad, tec_suppliers.nombre, tec_products.id, COUNT(1) AS total

            FROM (( " . $db->getTable('tec_sales') . "  INNER JOIN " . $db->getTable('tec_sales_tec_products') . "  ON tec_sales.id = tec_sales_tec_products.tec_sale_id)

            INNER JOIN " . $db->getTable('tec_products') . "  ON tec_sales_tec_products.tec_product_id = tec_products.id)

            INNER JOIN " . $db->getTable('tec_suppliers') . "  ON tec_products.proveedor_id = tec_suppliers.id

            WHERE tec_suppliers.id = $tec_provider_id AND NOT tec_products.nombre_prod = '1PRODUCTOS-1000'

            GROUP BY tec_products.id ORDER BY total DESC LIMIT 15 ";

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

        $identificacion_tipo = isset($rqst['identificacion_tipo']) ? ($rqst['identificacion_tipo']) : '';

        $numero_doc = isset($rqst['numero_doc']) ? ($rqst['numero_doc']) : '';

        $dv = isset($rqst['dv']) ? ($rqst['dv']) : '';

        $nombre = isset($rqst['nombre']) ? ($rqst['nombre']) : '';

        $vendedor = isset($rqst['vendedor']) ? ($rqst['vendedor']) : '';

        $direccion = isset($rqst['direccion']) ? ($rqst['direccion']) : '';

        $ciudad = isset($rqst['ciudad']) ? ($rqst['ciudad']) : '';

        $departamento = isset($rqst['departamento']) ? ($rqst['departamento']) : '';

        $telefono = isset($rqst['telefono']) ? ($rqst['telefono']) : '';

        $celular = isset($rqst['celular']) ? ($rqst['celular']) : '';

        $cuenta = isset($rqst['cuenta']) ? ($rqst['cuenta']) : '';

        $email = isset($rqst['email']) ? ($rqst['email']) : '';

        $banco= isset($rqst['banco']) ? ($rqst['banco']) : '';

        $cupo = isset($rqst['cupo']) ? ($rqst['cupo']) : '';

        $autoretenedor = isset($rqst['autoretenedor']) ? ($rqst['autoretenedor']) : 'no';

        $reteica= isset($rqst['reteica']) ? ($rqst['reteica']) : 'no';

        $enable = isset($rqst['enable']) ? ($rqst['enable']) : 'si';







        $db = new DbConection();

        $pdo = $db->openConect();



        if ($id > 0) {

            //actualiza la informacion

            $q = "SELECT id  FROM " . $db->getTable('tec_suppliers') . " WHERE id = " . $id;

            $result = $pdo->query($q);

            if ($result) {

                $table = $db->getTable('tec_suppliers');

                $arrfieldscomma = array(

                    'identificacion_tipo' => $identificacion_tipo,

                    'numero_doc' => $numero_doc,

                    'dv' => $dv,

                    'nombre' => $nombre,

                    'vendedor' => $vendedor,

                    'direccion' => $direccion,

                    'ciudad' => $ciudad,

                    'departamento' => $departamento,

                    'telefono' => $telefono,

                    'celular' => $celular,

                    'cuenta' => $cuenta,

                    'email' => $email,

                    'banco' => $banco,

                    'cupo' => $cupo,

                    'autoretenedor' => $autoretenedor,

                    'reteica' => $reteica,

                    'enable' => $enable);

                $arrfieldsnocomma = array('dtcreate' => Util::date_now_server());

                $q = Util::make_query_update($table, "id = '$id'", $arrfieldscomma, $arrfieldsnocomma);

                $result = $pdo->query($q);

                if (!$pdo->query($q)) {

                    Util::trace_log_error($rqst, 'Usuario::save ' . $id, $pdo->errorInfo());

                    $arrjson = Util::error_general('Actualizando los datos del producto');

                } else {

                    $arrjson = array('output' => array('valid' => true, 'id' => $id));

                }

            }

        } else {

            if ($nombre != "") {

                $q = "INSERT INTO " . $db->getTable('tec_suppliers') . " (dtcreate, identificacion_tipo, numero_doc, dv, nombre, vendedor, direccion, ciudad, departamento, telefono, celular, cuenta, banco, email, cupo, autoretenedor, reteica, enable) 
                VALUES (" . Util::date_now_server() . ", :identificacion_tipo, :numero_doc, :dv, :nombre, :vendedor, :direccion, :ciudad, :departamento, :telefono, :celular, :cuenta, :banco, :email, :cupo, :autoretenedor, :reteica, :enable)";

                $result = $pdo->prepare($q);

                $arrparam = array(

                    ':identificacion_tipo' => $identificacion_tipo,

                    ':numero_doc' => $numero_doc,

                    ':dv' => $dv,

                    ':nombre' => $nombre,

                    ':vendedor' => $vendedor,

                    ':direccion' => $direccion,

                    ':ciudad' => $ciudad,

                    ':departamento' => $departamento,

                    ':telefono' => $telefono,

                    ':celular' => $celular,

                    ':cuenta' => $cuenta,

                    ':banco' => $banco,

                    ':email' => $email,

                    ':cupo' => $cupo,

                    ':autoretenedor' => $autoretenedor,

                    ':reteica' => $reteica,

                    ':enable' => $enable);

                if ($result->execute($arrparam)) {

                    $arrjson = array('output' => array('valid' => true, 'response' => $pdo->lastInsertId()));

                } else {

                    Util::trace_log_error($rqst, 'Proveedor::save ' . $id, $pdo->errorInfo());

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



        $q = "DELETE FROM " . $db->getTable('tec_suppliers') . " WHERE id = " . $id;

        $result = $pdo->query($q);

        if ($result) {

            $arrjson = array('output' => array('valid' => true, 'error' => $pdo->errorInfo()));

        } else {

            Util::trace_log_error($rqst, 'Proveedor::delete ' . $id, $pdo->errorInfo());

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



        $q = "UPDATE " . $db->getTable('tec_suppliers') . " SET enable = '$enable' WHERE id = " . $id;

        $result = $pdo->query($q);

        $arr = array();

        if ($result) {

            $arrjson = array('output' => array('valid' => true, 'response' => $arr, 'error' => $pdo->errorInfo()));

        } else {

            Util::trace_log_error($rqst, 'Proveedor::enable ' . $id, $pdo->errorInfo());

            $arrjson = Util::error_general($pdo->errorInfo());

        }

        $db->closeConect();

        return $arrjson;

    }



    public static function search($rqst){

        $search = isset($rqst['search']) ? ($rqst['search']) : '';



        $db = new DbConection();

        $pdo = $db->openConect();



        $q = "SELECT * FROM " . $db->getTable('tec_suppliers') . "

        WHERE numero_doc  LIKE '%$search%'  OR

            nombre  LIKE '%$search%' LIMIT 200 ";



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

     * Metodo para obtener las ordenes recibidas del proveedor x al comercio

     */

    public static function ordenesRecibidas($rqst){

        $tec_supplier_id = isset($rqst['tec_supplier_id']) ? ($rqst['tec_supplier_id']) : '';



        if($tec_supplier_id > 0){

            $db = new DbConection();

            $pdo = $db->openConect();



            $q = "SELECT * FROM " . $db->getTable('tec_orders') . " WHERE tec_supplier_id  = $tec_supplier_id AND estado = 'RECIBIDO' " ;

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

        }else{

            return Util::error_missing_data();

        }

    }

}

