<?php



/**

 * Clase que contiene todas las operaciones utilizadas sobre la base de datos

 * @author SPIDERSOFTWARE

 */

class Entrada {


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



        $q = "SELECT * FROM " . $db->getTable('tec_entry') . " ORDER BY id DESC LIMIT 100";



        if ($id > 0) {

            $q = "SELECT * FROM " . $db->getTable('tec_entry') . " WHERE id = " . $id;

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

     * @return array de entrada personal

     *

     */

    public static function save($rqst){

        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;

        $cc = isset($rqst['cc']) ? ($rqst['cc']) : '';

        $entrada = isset($rqst['ingreso']) ? ($rqst['ingreso']) : '';



        $db = new DbConection();

        $pdo = $db->openConect();



        if ($cc != "") {

                $q = "INSERT INTO " . $db->getTable('tec_entry') . " (cc, entrada ) VALUES ( :cc, :entrada)";

                $result = $pdo->prepare($q);

                $arrparam = array(

                    ':cc' => $cc,

                    ':entrada' => $entrada);

                if ($result->execute($arrparam)) {

                    $arrjson = array('output' => array('valid' => true, 'response' => $pdo->lastInsertId()));

                } else {

                    Util::trace_log_error($rqst, 'Entrada::save' . $id, $pdo->errorInfo());

                    $arrjson = Util::error_general();

                }

        } else {

            $arrjson = Util::error_missing_data();

        }

        $db->closeConect();

        return $arrjson;

    }

}

