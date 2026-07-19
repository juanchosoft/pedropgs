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
        $document_id = isset($rqst['document_id']) ? ($rqst['document_id']) : '1';
        $cc = isset($rqst['cc']) ? trim((string) $rqst['cc']) : '';
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
            // En edición el cc es inmutable (vínculo con usuarios); se conserva el de BD.
            $stmtCc = $pdo->prepare("SELECT cc FROM " . $db->getTable('tec_employee') . " WHERE id = :id LIMIT 1");
            $stmtCc->execute([':id' => $id]);
            $rowCc = $stmtCc->fetch(PDO::FETCH_ASSOC);
            if (!$rowCc) {
                $db->closeConect();
                return Util::error_general('Empleado no encontrado');
            }
            $cc = trim((string) $rowCc['cc']);

            $table = $db->getTable('tec_employee');
            $arrfieldscomma = array(
                'nombre' => $nombre,
                'document_id' => $document_id,
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
            if ($result) {
                $arrjson = array('output' => array('valid' => true, 'id' => $id));
            } else {
                $arrjson = Util::error_general('Actualizando los datos del Empleado');
            }

            $db->closeConect();
            return $arrjson;
        }

        // Alta: generar cc automáticamente (siguiente disponible)
        $cc = (string) self::generateNextCc($pdo, $db);

        $q = "INSERT INTO " . $db->getTable('tec_employee') . "
                (dtcreate, nombre, document_id, genero, cc, celular, fecha_ingreso, email, fecha_nacimiento, lugar_nacimiento, direccion, estado_civil, rh, camisa, pantalon, calzado, entrega_uniforme, image, enable, tbl_unidad_id, dias_descanso)
              VALUES (" . Util::date_now_server() . ", :nombre, :document_id, :genero, :cc, :celular, :fecha_ingreso, :email, :fecha_nacimiento, :lugar_nacimiento, :direccion, :estado_civil, :rh, :camisa, :pantalon, :calzado, :entrega_uniforme, :image, :enable, :tbl_unidad_id, :dias_descanso)";
        $result = $pdo->prepare($q);
        $arrparam = array(
            ':nombre' => $nombre,
            ':document_id' => $document_id,
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
            ':entrega_uniforme' => $entrega_uniforme,
            ':image' => $imagefileToUpload,
            ':enable' => $enable,
            ':tbl_unidad_id' => $tbl_unidad_id,
            ':dias_descanso' => $dias_descanso,
        );

        if ($result->execute($arrparam)) {
            $newId = (int) $pdo->lastInsertId();
            // Asegura consistencia: cc = id de fila cuando sea posible (y único)
            if ($newId > 0 && (string) $newId !== $cc) {
                // Si el autoincrement difiere del código generado, conservar el cc generado (ya único).
            }
            $arrjson = array('output' => array('valid' => true, 'response' => $newId, 'cc' => $cc));
        } else {
            $arrjson = Util::error_general();
        }

        $db->closeConect();
        return $arrjson;
    }

    /**
     * Siguiente código de empleado (cc).
     * Usa el mayor entre MAX(id) y MAX(cc numérico) + 1 para no chocar con datos históricos.
     *
     * @param PDO $pdo
     * @param DbConection $db
     * @return int
     */
    public static function generateNextCc($pdo, $db)
    {
        $q = "SELECT GREATEST(
                    COALESCE(MAX(id), 0),
                    COALESCE(MAX(CAST(NULLIF(TRIM(cc), '') AS UNSIGNED)), 0)
                  ) + 1 AS next_code
              FROM " . $db->getTable('tec_employee');
        $stmt = $pdo->query($q);
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        $next = isset($row['next_code']) ? (int) $row['next_code'] : 1;
        return $next > 0 ? $next : 1;
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
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;



        $db = new DbConection();

        $pdo = $db->openConect();



        $q = "SELECT * FROM " . $db->getTable('tec_employee') . " WHERE cc = :cc";
        $arrparam = array(":cc" => $cc);

        if ($id > 0) {
            $q .= " AND id != :id";
            $arrparam[":id"] = $id;
        }

        $result = $pdo->prepare($q);

        $arr = array();

        if ($result->execute($arrparam)) {

            foreach ($result as $valor) {

                $arr[] = $valor;
            }

            if (count($arr) > 0) {

                $arrjson = Util::error_general(' The account number has already been entered ');
            } else {

                $arrjson = array('output' => array('valid' => true, 'response' => 'available'));
            }
        } else {

            $arrjson = Util::error_general('Employee code looked up');
        }

        $db->closeConect();

        return $arrjson;
    }

    /**
     * Búsqueda de empleados para Select2.
     * El valor (id) es el cc, porque tec_usuarios.employee_id = tec_employee.cc.
     * Excluye empleados ya asociados a otro usuario.
     */
    public static function search($rqst)
    {
        $term = isset($rqst['q']) ? trim((string) $rqst['q']) : '';
        if ($term === '' && isset($rqst['term'])) {
            $term = trim((string) $rqst['term']);
        }
        // Usuario en edición: no excluir su propio vínculo (por si se reabre el select)
        $excludeUserId = isset($rqst['exclude_user_id']) ? (int) $rqst['exclude_user_id'] : 0;

        $db = new DbConection();
        $pdo = $db->openConect();

        try {
            $empTable = $db->getTable('tec_employee');
            $usrTable = $db->getTable('tec_usuarios');

            $q = "SELECT e.id, e.cc, e.nombre
                  FROM {$empTable} AS e
                  WHERE e.enable = 'si'
                    AND e.cc IS NOT NULL
                    AND e.cc <> ''
                    AND NOT EXISTS (
                        SELECT 1
                        FROM {$usrTable} AS u
                        WHERE u.employee_id IS NOT NULL
                          AND u.employee_id <> ''
                          AND u.employee_id <> 0
                          AND CAST(u.employee_id AS CHAR) = CAST(e.cc AS CHAR)
                          AND (:exclude_user_id = 0 OR u.id <> :exclude_user_id2)
                    )";
            $params = [
                ':exclude_user_id' => $excludeUserId,
                ':exclude_user_id2' => $excludeUserId,
            ];

            if ($term !== '') {
                $q .= " AND (e.nombre LIKE :term OR CAST(e.cc AS CHAR) LIKE :term_cc)";
                $params[':term'] = '%' . $term . '%';
                $params[':term_cc'] = '%' . $term . '%';
            }

            $q .= " ORDER BY e.nombre ASC LIMIT 50";

            $stmt = $pdo->prepare($q);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $results = [];
            foreach ($rows as $row) {
                $cc = trim((string) $row['cc']);
                if ($cc === '' || $cc === '0') {
                    continue;
                }
                $nombre = isset($row['nombre']) ? trim((string) $row['nombre']) : '';
                $results[] = [
                    // Select2 value = cc (= lo que se guarda en tec_usuarios.employee_id)
                    'id' => $cc,
                    'text' => $nombre !== '' ? $nombre : ('Employee ' . $cc),
                    'nombre' => $nombre,
                    'cc' => $cc,
                    'pk' => (int) $row['id'],
                ];
            }

            return [
                'output' => [
                    'valid' => true,
                    'response' => $results,
                ],
            ];
        } catch (Exception $e) {
            return Util::error_general($e->getMessage());
        } finally {
            $db->closeConect();
        }
    }
}
