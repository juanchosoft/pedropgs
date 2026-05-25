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

   public static function getAllrestday($rqst)
{
    $id = isset($rqst['id']) ? intval($rqst['id']) : 0;

    $db = new DbConection();
    $pdo = $db->openConect();

    try {

        $arr = array();

        if ($id > 0) {

            $q = "SELECT 
                        tec_employee.nombre AS nombre_empleado,
                        tec_employee.cc,
                        tec_employee.dias_descanso,
                        tec_usuarios.id AS usuario_id,
                        tec_usuarios.nombre AS nombre_usuario,
                        tec_usuarios.apellido,
                        tec_usuarios.employee_id
                  FROM " . $db->getTable('tec_employee') . " AS tec_employee
                  INNER JOIN " . $db->getTable('tec_usuarios') . " AS tec_usuarios
                      ON tec_employee.cc = tec_usuarios.employee_id
                  WHERE tec_usuarios.id = :id
                  LIMIT 1";

            $stmt = $pdo->prepare($q);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

        } else {

            $q = "SELECT 
                        tec_employee.nombre AS nombre_empleado,
                        tec_employee.cc,
                        tec_employee.dias_descanso,
                        tec_usuarios.id AS usuario_id,
                        tec_usuarios.nombre AS nombre_usuario,
                        tec_usuarios.apellido,
                        tec_usuarios.employee_id
                  FROM " . $db->getTable('tec_employee') . " AS tec_employee
                  INNER JOIN " . $db->getTable('tec_usuarios') . " AS tec_usuarios
                      ON tec_employee.cc = tec_usuarios.employee_id
                  ORDER BY tec_employee.nombre ASC
                  LIMIT 100";

            $stmt = $pdo->prepare($q);
            $stmt->execute();
        }

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($result && count($result) > 0) {
            foreach ($result as $valor) {
                $arr[] = $valor;
            }

            $arrjson = array(
                'output' => array(
                    'valid' => true,
                    'response' => $arr
                )
            );
        } else {
            $arrjson = Util::error_no_result();
        }

    } catch (Exception $e) {

        $arrjson = array(
            'output' => array(
                'valid' => false,
                'response' => 'Error al consultar los días de descanso: ' . $e->getMessage()
            )
        );

    }

    $db->closeConect();
    return $arrjson;
}

}
