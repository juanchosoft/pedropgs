<?php



// Obteniendo la fecha actual con hora, minutos y segundos en PHP
$fechaActual = date('d-m-Y H:i:s');




/**

 * Clase que contiene todas las operaciones utilizadas sobre la base de datos

 * @author SPIDERSOFTWARE

 */

class Main
{



    public function __construct()
    {
    }



    /**

     * Metodo para recuperar todos los registros

     * @return array de las categorias

     */

    public static function getDataMain($rqst)
    {



        $db = new DbConection();

        $pdo = $db->openConect();



        $usuariosConectados = 0;

        $cajaAbiertas = 0;

        $totalArticulos = 0;

        $totalArticulos = 0;



        // Usuarios conectados

        $q = "SELECT count(*) as cantidad FROM " . $db->getTable('tec_session_x_tec_cashier');

        $result = $pdo->query($q);

        if ($result) {

            foreach ($result as $valor) {

                $usuariosConectados = $valor['cantidad'];
            }



            // Cajas abiertas.

            $q2 = "SELECT count(*) as cantidad FROM " . $db->getTable('tec_session_x_tec_cashier') . "  INNER JOIN " . $db->getTable('tec_cashier') . "  ON tec_session_x_tec_cashier.tec_cashier_id = tec_cashier.id WHERE tec_cashier.tipo ='caja'";

            $resultCajasAbiertas = $pdo->query($q2);

            foreach ($resultCajasAbiertas as $valor) {

                $cajaAbiertas = $valor['cantidad'];
            }



            // Total articulos

            $q3 = "SELECT count(*) as cantidad FROM " . $db->getTable('tec_products');

            $resultTotalArticulos = $pdo->query($q3);

            foreach ($resultTotalArticulos as $valor) {

                $totalArticulos = $valor['cantidad'];
            }

            // Total carros lavados
            $q4 ="SELECT count(tec_parking.id) as cantidad FROM " . $db->getTable('tec_parking')  . " 
            WHERE tec_parking.dtcreate >= '" . Util::getDateCurrently(). " 00:00:01' AND
            tec_parking.dtcreate <=  '" . Util::getDateCurrently(). " 23:59:59' ";
            $resultTotalCarros = $pdo->query($q4);
            $totalCarros = 0;
            foreach ($resultTotalCarros as $valor) {
                $totalCarros = intval($valor['cantidad']);
            }
            $resultTotalCarros = $pdo->query($q4);

            $arrjson = array('output' => array(
                'valid' => true, 'usuariosConectados' => $usuariosConectados, 'cajaAbiertas' => $cajaAbiertas,
                'totalArticulos' => $totalArticulos, 'totalCarros' => $totalCarros
            ));
        } else {

            $arrjson = Util::error_no_result();
        }

        $db->closeConect();

        return $arrjson;
    }
}
