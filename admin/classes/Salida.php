<?php



/**

 * Clase que contiene todas las operaciones utilizadas sobre la base de datos

 * @author SPIDERSOFTWARE

 */

class Salida {



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



        $q = "SELECT * FROM " . $db->getTable('tec_exit') . " ORDER BY id DESC LIMIT 100";



        if ($id > 0) {

            $q = "SELECT * FROM " . $db->getTable('tec_exit') . " WHERE id = " . $id;

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

     * @param REQUEST $rqst0

     * @return array de Salida personal

     *

     */

    public static function save($rqst){

        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;

        Util::trace_log($rqst, 'Salida::save ' . $id);

        $cc = isset($rqst['cc']) ? ($rqst['cc']) : '';

        $salida = isset($rqst['salida']) ? ($rqst['salida']) : '';



        $db = new DbConection();

        $pdo = $db->openConect();



        if ($cc != "") {

                $q = "INSERT INTO " . $db->getTable('tec_exit') . " (cc, salida ) VALUES ( :cc, :salida)";

                $result = $pdo->prepare($q);

                $arrparam = array(

                    ':cc' => $cc,

                    ':salida' => $salida);

                if ($result->execute($arrparam)) {

                    $arrjson = array('output' => array('valid' => true, 'response' => $pdo->lastInsertId()));

                } else {

                    Util::trace_log_error($rqst, 'Salida::save' . $id, $pdo->errorInfo());

                    $arrjson = Util::error_general();

                }

            } else {

                $arrjson = Util::error_missing_data();

            }

        $db->closeConect();

        return $arrjson;

    }

}

