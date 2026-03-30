<?php



/**

 * Clase que contiene todas las operaciones utilizadas sobre la base de datos

 * @author SPIDERSOFTWARE

 */

class Empleado
{
    public function __construct()
    {
    }
    /**

     * Metodo para recuperar todos los registros

     * @return array de las Empleados

     */

    public static function getAll($rqst)

    {

        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        $db = new DbConection();
        $pdo = $db->openConect();
        $q = "SELECT * FROM " . $db->getTable('tec_employee') . " ORDER BY id DESC";
        if ($id > 0) {

            $q = "SELECT * FROM " . $db->getTable('tec_employee') . " WHERE id = " . $id;
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

     * @return array de empleados

     */

    public static function save($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        $tbl_unidad_id = isset($rqst['tbl_unidad_id']) ? intval($rqst['tbl_unidad_id']) : 0;
        $nombre = isset($rqst['nombre']) ? ($rqst['nombre']) : '';
        $cc = isset($rqst['cc']) ? ($rqst['cc']) : '';
        $fecha_ingreso = isset($rqst['fecha_ingreso']) ? ($rqst['fecha_ingreso']) : '';
        $telefono = isset($rqst['telefono']) ? ($rqst['telefono']) : '';
        $celular = isset($rqst['celular']) ? ($rqst['celular']) : '';
        $email = isset($rqst['email']) ? ($rqst['email']) : '';
        $fecha_nacimiento = isset($rqst['fecha_nacimiento']) ? ($rqst['fecha_nacimiento']) : '';
        $lugar_nacimiento = isset($rqst['lugar_nacimiento']) ? ($rqst['lugar_nacimiento']) : '';
        $estado_civil = isset($rqst['estado_civil']) ? ($rqst['estado_civil']) : '';
        $direccion = isset($rqst['direccion']) ? ($rqst['direccion']) : '';
        $rh = isset($rqst['rh']) ? ($rqst['rh']) : '';
        $camisa = isset($rqst['camisa']) ? ($rqst['camisa']) : '';
        $pantalon = isset($rqst['pantalon']) ? ($rqst['pantalon']) : '';
        $calzado = isset($rqst['calzado']) ? ($rqst['calzado']) : '';
        $entrega_uniforme = isset($rqst['entrega_uniforme']) ? ($rqst['entrega_uniforme']) : '';
        $enable = isset($rqst['enable']) ? ($rqst['enable']) : 'si';
        $genero = isset($rqst['genero']) ? ($rqst['genero']) : 'si';
        $dias_descanso = isset($rqst['dias_descanso']) ? intval($rqst['dias_descanso']) : 0;

        $imagefileToUpload = isset($_SESSION['file']['nombrearchivo']) ? ($_SESSION['file']['nombrearchivo']) : '';

        if (isset($_SESSION['file']['nombrearchivo'])) {

            $_SESSION['file']['nombrearchivo'] = NULL;
        }
        $db = new DbConection();
        $pdo = $db->openConect();
        if ($id > 0) {

            //actualiza la informacion
            $q = "SELECT id  FROM " . $db->getTable('tec_employee') . " WHERE id = " . $id;

            $result = $pdo->query($q);

            if ($result) {

                $table = $db->getTable('tec_employee');

                $arrfieldscomma = array(
                    'nombre' => $nombre,
                    'cc' => $cc,
                    'telefono' => $telefono,
                    'celular' => $celular,
                    'fecha_ingreso' => $fecha_ingreso,
                    'email' => $email,
                    'fecha_nacimiento' => $fecha_nacimiento,
                    'lugar_nacimiento' => $lugar_nacimiento,
                    'estado_civil' => $estado_civil,
                    'direccion' => $direccion,
                    'rh' => $rh,
                    'camisa' => $camisa,
                    'pantalon' => $pantalon,
                    'calzado' => $calzado,
                    'entrega_uniforme' => $entrega_uniforme,
                    'enable' => $enable,
                    'genero' => $genero,
                    'tbl_unidad_id' => $tbl_unidad_id,
                    'dias_descanso' => $dias_descanso,
                );

                $arrfieldsnocomma = array('dtcreate' => Util::date_now_server());
                $q = Util::make_query_update($table, "id = '$id'", $arrfieldscomma, $arrfieldsnocomma);
                $result = $pdo->query($q);
                if (!$pdo->query($q)) {

                    // Se valida quela cc no se haya ingresado nuevamnte
                    $parrams = array('cc' => $cc);
                    $response = Empleado::available($parrams);
                    if (!$response['output']['valid']) {
                        // Obtemos el valor de la imagen del Empleado
                        foreach ($result as $valor) {
                            $file = $valor['image'];
                        }
                        // Eliminamos el archivo anterior
                        if ($file != "" && file_exists($file)) {

                            unlink("../assets/img/admin/" . $file);
                        }
                        $arrjson = $response;
                    } else {
                        $arrjson = Util::error_general('Actualizando los datos del Empleado');
                    }
                } else {
                    $arrjson = array('output' => array('valid' => true, 'id' => $id));
                }
            }

            $db->closeConect();
            return $arrjson;
        } else {

            if ($cc != "") {
                // Se valida que el la cc no exista
                $parrams = array('cc' => $cc);
                $response = Empleado::available($parrams);

                if ($response['output']['valid']) {

                    $q = "INSERT INTO " . $db->getTable('tec_employee') . "
                    (dtcreate, nombre, genero, cc, celular, fecha_ingreso, email, fecha_nacimiento, lugar_nacimiento, direccion, estado_civil,  rh, camisa, pantalon, calzado, entrega_uniforme, image, enable, tbl_unidad_id, dias_descanso)
                VALUES (" . Util::date_now_server() . ", :nombre, :cc, :genero, :celular, :fecha_ingreso, :email, :fecha_nacimiento, :lugar_nacimiento, :direccion, :estado_civil,:rh, :camisa, :pantalon, :calzado, :entrega_uniforme, :image, :enable, :tbl_unidad_id, :dias_descanso)";
                    $result = $pdo->prepare($q);
                    $arrparam = array(
                        ':nombre' => $nombre,
                        ':genero' => $genero,
                        ':cc' => $cc,
                        ':celular' => $celular,
                        ':fecha_ingreso' => $fecha_ingreso,
                        ':email' => $email,
                        ':fecha_nacimiento' => $fecha_nacimiento,
                        ':lugar_nacimiento' => $lugar_nacimiento,
                        ':direccion' => $direccion,
                        ':estado_civil' => $estado_civil,
                        ':rh' => $rh,
                        ':camisa' => $camisa,
                        ':pantalon' => $pantalon,
                        ':calzado' => $calzado,
                        ':genero' => $genero,
                        ':entrega_uniforme' => $entrega_uniforme,
                        ':image' => $imagefileToUpload,
                        ':enable' => $enable,
                        ':tbl_unidad_id' => $tbl_unidad_id,
                        ':dias_descanso' => $dias_descanso,
                    );
                    if ($result->execute($arrparam)) {
                        $arrjson = array('output' => array('valid' => true, 'response' => $pdo->lastInsertId()));
                    } else {
                        $arrjson = Util::error_general();
                    }
                } else {
                    $arrjson = Util::error_missing_data();
                }
                $db->closeConect();
                return $arrjson;
            }
        }
    }

    public static function delete($rqst)
    {

        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;



        $db = new DbConection();

        $pdo = $db->openConect();



        $q = "DELETE FROM " . $db->getTable('tec_employee') . " WHERE id = " . $id;

        $result = $pdo->query($q);

        if ($result) {

            $arrjson = array('output' => array('valid' => true, 'error' => $pdo->errorInfo()));
        } else {

            Util::trace_log_error($rqst, 'Empleado::delete' . $id, $pdo->errorInfo());

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



        $q = "UPDATE " . $db->getTable('tec_employee') . " SET enable = '$enable' WHERE id = " . $id;

        $result = $pdo->query($q);

        $arr = array();

        if ($result) {

            $arrjson = array('output' => array('valid' => true, 'response' => $arr, 'error' => $pdo->errorInfo()));
        } else {

            Util::trace_log_error($rqst, 'Empleado::enable ' . $id, $pdo->errorInfo());

            $arrjson = Util::error_general($pdo->errorInfo());
        }

        $db->closeConect();

        return $arrjson;
    }

    public static function available($rqst)
    {

        $cc = isset($rqst['cc']) ? ($rqst['cc']) : '';



        $db = new DbConection();

        $pdo = $db->openConect();



        $q = "SELECT * FROM " . $db->getTable('tec_employee') . " WHERE cc = :cc";

        $result = $pdo->prepare($q);

        $arr = array();

        $arrparam = array(":cc" => $cc);

        if ($result->execute($arrparam)) {

            foreach ($result as $valor) {

                $arr[] = $valor;
            }

            if (count($arr) > 0) {

                $arrjson = Util::error_general('La cc Ingresada ya Existe');
            } else {

                $arrjson = array('output' => array('valid' => true, 'response' => 'available'));
            }
        } else {

            $arrjson = Util::error_general('Consultado codigo del Empleado');
        }

        $db->closeConect();

        return $arrjson;
    }
}
