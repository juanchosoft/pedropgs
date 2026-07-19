<?php

require_once 'Empleado.php';

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
        $tipo = isset($rqst['tipo']) ? trim($rqst['tipo']) : '';

        $db = new DbConection();
        $pdo = $db->openConect();

        try {
            if ($id > 0) {
                /*
                 * Cuando se consulta un usuario para editar,
                 * traemos el nombre del empleado asociado.
                 *
                 * Relación correcta (campo mal nombrado históricamente):
                 * tec_usuarios.employee_id = tec_employee.cc
                 */
                $q = "SELECT 
                        u.*,
                        e.nombre AS employee_nombre,
                        e.cc AS employee_cc,
                        e.dias_descanso,
                        CASE 
                            WHEN u.employee_id IS NOT NULL AND u.employee_id <> '' AND u.employee_id <> 0 THEN 'si'
                            ELSE 'no'
                        END AS es_empleado,
                        GROUP_CONCAT(uhu.tbl_unidades_id) AS unidades_ids
                      FROM " . $db->getTable('tec_usuarios') . " AS u
                      LEFT JOIN " . $db->getTable('tec_employee') . " AS e
                        ON CAST(e.cc AS CHAR) = CAST(u.employee_id AS CHAR)
                      LEFT JOIN " . $db->getTable('tec_usuarios_has_tbl_unidades') . " AS uhu
                        ON uhu.tec_usuarios_id = u.id
                      WHERE u.id = :id
                      GROUP BY u.id
                      LIMIT 1";

                $stmt = $pdo->prepare($q);
                $stmt->execute([':id' => $id]);

            } elseif ($tipo != "") {
                $q = "SELECT * 
                      FROM " . $db->getTable('tec_usuarios') . " 
                      WHERE tipo = :tipo 
                      AND habilitado = 'si' 
                      ORDER BY nombre ASC";

                $stmt = $pdo->prepare($q);
                $stmt->execute([':tipo' => $tipo]);

            } else {
                $q = "SELECT * 
                      FROM " . $db->getTable('tec_usuarios') . " 
                      ORDER BY id DESC 
                      LIMIT 100";

                $stmt = $pdo->prepare($q);
                $stmt->execute();
            }

            $arr = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Transform unidades_ids for the single-user edit case
            if ($id > 0) {
                foreach ($arr as &$row) {
                    $row['unidades_ids'] = isset($row['unidades_ids']) && $row['unidades_ids'] ? explode(',', $row['unidades_ids']) : [];
                    $row['unidades_ids'] = array_map('intval', $row['unidades_ids']);
                }
                unset($row);
            }

            $arrjson = array(
                'output' => array(
                    'valid' => true,
                    'response' => $arr
                )
            );

        } catch (PDOException $e) {
            $arrjson = Util::error_general('Error consultando usuarios: ' . $e->getMessage());
        }

        $db->closeConect();
        return $arrjson;
    }

    public static function available($rqst)
    {
        $nickname = isset($rqst['nickname']) ? trim($rqst['nickname']) : '';

        $db = new DbConection();
        $pdo = $db->openConect();

        try {
            $q = "SELECT id 
                  FROM " . $db->getTable('tec_usuarios') . " 
                  WHERE nickname = :nickname 
                  LIMIT 1";

            $stmt = $pdo->prepare($q);
            $stmt->execute([':nickname' => $nickname]);

            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $arrjson = Util::error_general('The user email already exists');
            } else {
                $arrjson = array(
                    'output' => array(
                        'valid' => true,
                        'response' => 'available'
                    )
                );
            }

        } catch (PDOException $e) {
            $arrjson = Util::error_general('Error validando usuario: ' . $e->getMessage());
        }

        $db->closeConect();
        return $arrjson;
    }

    public static function login($rqst)
    {
        $nickname = isset($rqst['nickname']) ? trim($rqst['nickname']) : '';
        $hashpass = isset($rqst['hashpass']) ? trim($rqst['hashpass']) : '';

        $db = new DbConection();
        $pdo = $db->openConect();

        try {
            if (strlen($hashpass) > 2) {
                $hashpass = Util::make_hash_pass($hashpass);
            }

            $q = "SELECT * 
                  FROM " . $db->getTable('tec_usuarios') . " 
                  WHERE nickname = :nickname 
                  AND hashpass = :hashpass 
                  AND habilitado = 'yes'
                  LIMIT 1";

            $stmt = $pdo->prepare($q);
            $stmt->execute([
                ':nickname' => $nickname,
                ':hashpass' => $hashpass
            ]);

            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                $db->closeConect();
                return Util::error_wrong_data_login();
            }

            $usuario['application'][] = Util::get_app_id();

            $id = intval($usuario['id']);
            $tbl_unidad_id = isset($usuario['tbl_unidad_id']) ? intval($usuario['tbl_unidad_id']) : 0;

            $q1 = "SELECT tec_permiso_id 
                   FROM " . $db->getTable('tec_usuarios_has_tec_permisos') . " 
                   WHERE tec_usuarios_id = :id 
                   ORDER BY tec_permiso_id ASC";

            $stmtPermisos = $pdo->prepare($q1);
            $stmtPermisos->execute([':id' => $id]);

            $arrassigned = array();

            foreach ($stmtPermisos->fetchAll(PDO::FETCH_ASSOC) as $permiso) {
                $arrassigned[] = $permiso['tec_permiso_id'];
            }

            $usuario['permisos'] = $arrassigned;

            /*
             * Fetch all assigned unidades from the pivot table.
             */
            $qUnid = "SELECT tbl_unidades_id 
                      FROM " . $db->getTable('tec_usuarios_has_tbl_unidades') . " 
                      WHERE tec_usuarios_id = :id 
                      ORDER BY tbl_unidades_id ASC";

            $stmtUnid = $pdo->prepare($qUnid);
            $stmtUnid->execute([':id' => $id]);

            $unidadIds = array();
            foreach ($stmtUnid->fetchAll(PDO::FETCH_ASSOC) as $rowUnid) {
                $unidadIds[] = intval($rowUnid['tbl_unidades_id']);
            }

            // Backward compat: if no pivot entries, use tbl_unidad_id from the user record
            if (empty($unidadIds) && $tbl_unidad_id > 0) {
                $unidadIds[] = $tbl_unidad_id;
            }

            $usuario['unidades'] = $unidadIds;

            $arr = array();
            $arr[] = $usuario;

            $q4 = "SELECT * 
                   FROM " . $db->getTable('tec_config') . " 
                   ORDER BY id 
                   LIMIT 1";

            $stmtConfig = $pdo->prepare($q4);
            $stmtConfig->execute();

            $arr4 = $stmtConfig->fetchAll(PDO::FETCH_ASSOC);

            $telefono_emergencia = "";
            $unidad = "";

            if ($tbl_unidad_id > 0) {
                $q5 = "SELECT * 
                       FROM " . $db->getTable('tbl_unidades') . " 
                       WHERE id = :tbl_unidad_id 
                       LIMIT 1";

                $stmtUnidad = $pdo->prepare($q5);
                $stmtUnidad->execute([':tbl_unidad_id' => $tbl_unidad_id]);

                $unidadData = $stmtUnidad->fetch(PDO::FETCH_ASSOC);

                if ($unidadData) {
                    $unidad = isset($unidadData['nombre']) ? $unidadData['nombre'] : "";
                    $telefono_emergencia = isset($unidadData['telefono_emergencia']) ? $unidadData['telefono_emergencia'] : "";
                }
            }

            $arrjson = array(
                'output' => array(
                    'valid' => true,
                    'response' => $arr,
                    'permisos' => $arrassigned,
                    'config' => $arr4,
                    'telefono_emergencia' => $telefono_emergencia,
                    'unidad' => $unidad,
                    'unidades' => $unidadIds
                )
            );

        } catch (PDOException $e) {
            $arrjson = Util::error_general('Error iniciando sesión: ' . $e->getMessage());
        }

        $db->closeConect();
        return $arrjson;
    }

    public static function save($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        $nickname = isset($rqst['nickname']) ? trim($rqst['nickname']) : '';
        $hashpass = isset($rqst['hashpass']) ? trim($rqst['hashpass']) : '';
        // employee_id en usuarios almacena tec_employee.cc (no el PK).
        $employee_id = self::normalizeEmployeeCc(isset($rqst['employee_id']) ? $rqst['employee_id'] : '');
        $emp_mode = isset($rqst['emp_mode']) ? trim((string) $rqst['emp_mode']) : '';
        $es_empleado = !empty($rqst['es_empleado']) && (string) $rqst['es_empleado'] !== '0';
        $nombre = isset($rqst['nombre']) ? trim($rqst['nombre']) : '';
        $apellido = isset($rqst['apellido']) ? trim($rqst['apellido']) : '';
        $tipo = isset($rqst['tipo']) ? trim($rqst['tipo']) : '';
        $tbl_unidad_id_raw = isset($rqst['tbl_unidad_id']) ? $rqst['tbl_unidad_id'] : [];
        $habilitado = isset($rqst['habilitado']) ? trim($rqst['habilitado']) : '';

        // Handle single or multiple unidad IDs
        $unidadIds = [];
        if (is_array($tbl_unidad_id_raw)) {
            $unidadIds = array_values(array_filter(array_map('intval', $tbl_unidad_id_raw), function($v) { return $v > 0; }));
            $tbl_unidad_id = !empty($unidadIds) ? $unidadIds[0] : 0;
        } else {
            $tbl_unidad_id = intval($tbl_unidad_id_raw);
            if ($tbl_unidad_id > 0) {
                $unidadIds[] = $tbl_unidad_id;
            }
        }

        $imgNueva = isset($_SESSION['file']['nombrearchivo']) ? trim($_SESSION['file']['nombrearchivo']) : '';

        $db = new DbConection();
        $pdo = $db->openConect();

        try {
            if ($nombre == "" || $nickname == "") {
                $db->closeConect();
                return Util::error_missing_data();
            }

            if (strlen($hashpass) > 2) {
                $hashpass = Util::make_hash_pass($hashpass);
            }

            /*
             * ACTUALIZAR USUARIO
             */
            if ($id > 0) {
                $qOld = "SELECT id, img, hashpass 
                         FROM " . $db->getTable('tec_usuarios') . " 
                         WHERE id = :id 
                         LIMIT 1";

                $stmtOld = $pdo->prepare($qOld);
                $stmtOld->execute([':id' => $id]);

                $usuarioActual = $stmtOld->fetch(PDO::FETCH_ASSOC);

                if (!$usuarioActual) {
                    $db->closeConect();
                    return Util::error_general('El usuario que intenta actualizar no existe');
                }

                $imgAnterior = isset($usuarioActual['img']) ? $usuarioActual['img'] : '';
                $imgFinal = $imgNueva != "" ? $imgNueva : $imgAnterior;

                /*
                 * Si no se envía nueva contraseña, conserva la anterior.
                 */
                $hashFinal = $hashpass != "" ? $hashpass : $usuarioActual['hashpass'];

                if ($es_empleado) {
                    $resolved = self::resolveEmployeeId($pdo, $db, $emp_mode, $employee_id, $rqst, $nombre, $apellido, $nickname);
                    if (!$resolved['ok']) {
                        $db->closeConect();
                        return Util::error_general($resolved['msg']);
                    }
                    $employee_id = $resolved['employee_id'];
                } else {
                    $employee_id = null;
                }

                $q = "UPDATE " . $db->getTable('tec_usuarios') . " SET
                        dtcreate = " . Util::date_now_server() . ",
                        nickname = :nickname,
                        hashpass = :hashpass,
                        employee_id = :employee_id,
                        nombre = :nombre,
                        apellido = :apellido,
                        tipo = :tipo,
                        tbl_unidad_id = :tbl_unidad_id,
                        img = :img,
                        habilitado = :habilitado
                      WHERE id = :id";

                $stmt = $pdo->prepare($q);

                $ok = $stmt->execute([
                    ':nickname' => $nickname,
                    ':hashpass' => $hashFinal,
                    ':employee_id' => $employee_id,
                    ':nombre' => $nombre,
                    ':apellido' => $apellido,
                    ':tipo' => $tipo,
                    ':tbl_unidad_id' => $tbl_unidad_id,
                    ':img' => $imgFinal,
                    ':habilitado' => $habilitado,
                    ':id' => $id
                ]);

                if (!$ok) {
                    $db->closeConect();
                    return Util::error_general('Actualizando los datos del usuario');
                }

                /*
                 * Sincroniza la tabla pivote de unidades asignadas.
                 */
                self::syncUnidades($pdo, $db, $id, $unidadIds);

                /*
                 * Elimina imagen anterior solo si se subió una nueva diferente.
                 */
                if ($imgNueva != "" && $imgAnterior != "" && $imgNueva != $imgAnterior) {
                    $rutaAnterior = "../assets/img/admin/" . $imgAnterior;

                    if (file_exists($rutaAnterior)) {
                        @unlink($rutaAnterior);
                    }
                }

                $arrjson = array(
                    'output' => array(
                        'valid' => true,
                        'id' => $id,
                        'img' => $imgFinal
                    )
                );

                $db->closeConect();
                return $arrjson;
            }

            /*
             * CREAR USUARIO
             */
            $qCheck = "SELECT id 
                       FROM " . $db->getTable('tec_usuarios') . " 
                       WHERE nickname = :nickname 
                       LIMIT 1";

            $stmtCheck = $pdo->prepare($qCheck);
            $stmtCheck->execute([':nickname' => $nickname]);

            if ($stmtCheck->fetch(PDO::FETCH_ASSOC)) {
                $db->closeConect();
                return Util::error_general('The user email already exists');
            }

            if ($es_empleado) {
                $resolved = self::resolveEmployeeId($pdo, $db, $emp_mode, $employee_id, $rqst, $nombre, $apellido, $nickname);
                if (!$resolved['ok']) {
                    $db->closeConect();
                    return Util::error_general($resolved['msg']);
                }
                $employee_id = $resolved['employee_id'];
            } else {
                $employee_id = null;
            }

            $q = "INSERT INTO " . $db->getTable('tec_usuarios') . " 
                    (
                        dtcreate, 
                        nickname, 
                        hashpass, 
                        employee_id, 
                        nombre, 
                        apellido,  
                        tipo, 
                        tbl_unidad_id, 
                        img, 
                        habilitado
                    ) 
                  VALUES 
                    (
                        " . Util::date_now_server() . ", 
                        :nickname, 
                        :hashpass, 
                        :employee_id, 
                        :nombre, 
                        :apellido, 
                        :tipo, 
                        :tbl_unidad_id, 
                        :img, 
                        :habilitado
                    )";

            $stmt = $pdo->prepare($q);

            $ok = $stmt->execute([
                ':nickname' => $nickname,
                ':hashpass' => $hashpass,
                ':employee_id' => $employee_id,
                ':nombre' => $nombre,
                ':apellido' => $apellido,
                ':tipo' => $tipo,
                ':tbl_unidad_id' => $tbl_unidad_id,
                ':img' => $imgNueva,
                ':habilitado' => $habilitado
            ]);

            if (!$ok) {
                $db->closeConect();
                return Util::error_general('No fue posible guardar el usuario');
            }

            $usuarioId = $pdo->lastInsertId();

            /*
             * Sincroniza la tabla pivote de unidades asignadas.
             */
            self::syncUnidades($pdo, $db, $usuarioId, $unidadIds);

            $arrjson = array(
                'output' => array(
                    'valid' => true,
                    'response' => $usuarioId
                )
            );

        } catch (PDOException $e) {
            $arrjson = Util::error_general('Error guardando usuario: ' . $e->getMessage());
        }

        $db->closeConect();
        return $arrjson;
    }

    /**
     * Normaliza el valor que va en tec_usuarios.employee_id (= tec_employee.cc).
     * La columna de usuarios es int(11); se acepta string numérico.
     *
     * @param mixed $cc
     * @return int|null
     */
    private static function normalizeEmployeeCc($cc)
    {
        $cc = trim((string) $cc);
        if ($cc === '' || $cc === '0') {
            return null;
        }
        if (!ctype_digit($cc) && !preg_match('/^-?\d+$/', $cc)) {
            return null;
        }
        $asInt = (int) $cc;
        return $asInt === 0 ? null : $asInt;
    }

    /**
     * Resuelve tec_usuarios.employee_id = tec_employee.cc
     * Modes: keep | existing | create
     *
     * @return array{ok:bool, employee_id:?int, msg:string}
     */
    private static function resolveEmployeeId($pdo, $db, $emp_mode, $employee_id, $rqst, $nombre, $apellido, $email)
    {
        $emp_mode = trim((string) $emp_mode);
        $employee_id = self::normalizeEmployeeCc($employee_id);

        // Ya asociado y no pide cambiar: conservar (employee_id ya es el cc)
        if ($emp_mode === '' || $emp_mode === 'keep') {
            if ($employee_id !== null && self::employeeExistsByCc($pdo, $db, $employee_id)) {
                return ['ok' => true, 'employee_id' => $employee_id, 'msg' => ''];
            }
            return ['ok' => false, 'employee_id' => null, 'msg' => 'Debe asociar un empleado existente o crear uno nuevo.'];
        }

        if ($emp_mode === 'existing') {
            if ($employee_id === null) {
                return ['ok' => false, 'employee_id' => null, 'msg' => 'Seleccione un empleado existente.'];
            }
            if (!self::employeeExistsByCc($pdo, $db, $employee_id)) {
                return ['ok' => false, 'employee_id' => null, 'msg' => 'El empleado seleccionado no existe (cc).'];
            }
            return ['ok' => true, 'employee_id' => $employee_id, 'msg' => ''];
        }

        if ($emp_mode === 'create') {
            $created = self::createEmployeeFromUser($pdo, $db, $rqst, $nombre, $apellido, $email);
            if (!$created['ok']) {
                return $created;
            }
            // Tras crear, el vínculo del usuario es el cc (no el PK).
            return ['ok' => true, 'employee_id' => $created['employee_id'], 'msg' => ''];
        }

        return ['ok' => false, 'employee_id' => null, 'msg' => 'Modo de empleado no válido.'];
    }

    private static function employeeExistsByCc($pdo, $db, $cc)
    {
        $cc = self::normalizeEmployeeCc($cc);
        if ($cc === null) {
            return false;
        }
        $stmt = $pdo->prepare(
            "SELECT id FROM " . $db->getTable('tec_employee') . "
             WHERE CAST(cc AS CHAR) = :cc
             LIMIT 1"
        );
        $stmt->execute([':cc' => (string) $cc]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Crea tec_employee con datos del usuario + campos emp_*.
     * El cc se genera automáticamente (Empleado::generateNextCc).
     * Devuelve employee_id = cc (valor a guardar en tec_usuarios.employee_id).
     *
     * @return array{ok:bool, employee_id:?int, msg:string}
     */
    private static function createEmployeeFromUser($pdo, $db, $rqst, $nombre, $apellido, $email)
    {
        $nombreCompleto = trim($nombre . ' ' . $apellido);
        $cc = Empleado::generateNextCc($pdo, $db);
        $fecha_ingreso = isset($rqst['emp_fecha_ingreso']) ? trim((string) $rqst['emp_fecha_ingreso']) : '';
        $celular = isset($rqst['emp_celular']) ? trim((string) $rqst['emp_celular']) : '';
        $direccion = isset($rqst['emp_direccion']) ? trim((string) $rqst['emp_direccion']) : '';
        $genero = isset($rqst['emp_genero']) ? trim((string) $rqst['emp_genero']) : '';
        $camisa = isset($rqst['emp_camisa']) ? trim((string) $rqst['emp_camisa']) : '';
        $pantalon = isset($rqst['emp_pantalon']) ? trim((string) $rqst['emp_pantalon']) : '';
        $calzado = isset($rqst['emp_calzado']) ? trim((string) $rqst['emp_calzado']) : '';
        $entrega_uniforme = isset($rqst['emp_entrega_uniforme']) ? trim((string) $rqst['emp_entrega_uniforme']) : '';

        if ($genero === 'seleccione') {
            $genero = '';
        }
        if ($camisa === 'seleccione') {
            $camisa = '';
        }
        if ($pantalon === 'seleccione') {
            $pantalon = '';
        }
        if ($calzado === 'seleccione') {
            $calzado = '';
        }

        if ($fecha_ingreso === '') {
            return ['ok' => false, 'employee_id' => null, 'msg' => 'La fecha de ingreso del empleado es obligatoria.'];
        }
        if ($celular === '') {
            return ['ok' => false, 'employee_id' => null, 'msg' => 'El celular del empleado es obligatorio.'];
        }

        // Reintento corto si hubo carrera en el código generado
        if (self::employeeExistsByCc($pdo, $db, $cc)) {
            $cc = Empleado::generateNextCc($pdo, $db);
        }
        if (self::employeeExistsByCc($pdo, $db, $cc)) {
            return ['ok' => false, 'employee_id' => null, 'msg' => 'No fue posible generar un Id de empleado único.'];
        }

        $qInsert = "INSERT INTO " . $db->getTable('tec_employee') . "
                    (
                        dtcreate, nombre, cc, email, enable, tbl_unidad_id, dias_descanso,
                        image, document_id, genero, celular, fecha_ingreso, fecha_nacimiento,
                        lugar_nacimiento, direccion, estado_civil, rh, camisa, pantalon,
                        calzado, entrega_uniforme
                    )
                    VALUES
                    (
                        " . Util::date_now_server() . ",
                        :nombre, :cc, :email, :enable, :tbl_unidad_id, :dias_descanso,
                        :image, :document_id, :genero, :celular, :fecha_ingreso, :fecha_nacimiento,
                        :lugar_nacimiento, :direccion, :estado_civil, :rh, :camisa, :pantalon,
                        :calzado, :entrega_uniforme
                    )";

        $stmt = $pdo->prepare($qInsert);
        $ok = $stmt->execute([
            ':nombre' => $nombreCompleto,
            ':cc' => (string) $cc,
            ':email' => $email,
            ':enable' => 'si',
            ':tbl_unidad_id' => 0,
            ':dias_descanso' => 0,
            ':image' => '',
            ':document_id' => 1,
            ':genero' => $genero !== '' ? $genero : 'Other',
            ':celular' => $celular,
            ':fecha_ingreso' => $fecha_ingreso,
            ':fecha_nacimiento' => '',
            ':lugar_nacimiento' => '',
            ':direccion' => $direccion,
            ':estado_civil' => '',
            ':rh' => '',
            ':camisa' => $camisa,
            ':pantalon' => $pantalon,
            ':calzado' => $calzado,
            ':entrega_uniforme' => $entrega_uniforme,
        ]);

        if (!$ok) {
            return ['ok' => false, 'employee_id' => null, 'msg' => 'No fue posible crear el empleado.'];
        }

        // Vínculo correcto: tec_usuarios.employee_id = tec_employee.cc
        return ['ok' => true, 'employee_id' => (int) $cc, 'msg' => ''];
    }

    private static function syncUnidades($pdo, $db, $usuarioId, array $unidadIds)
    {
        $qDel = "DELETE FROM " . $db->getTable('tec_usuarios_has_tbl_unidades') . " WHERE tec_usuarios_id = :uid";
        $stmtDel = $pdo->prepare($qDel);
        $stmtDel->execute([':uid' => $usuarioId]);

        foreach ($unidadIds as $unid) {
            $qIns = "INSERT INTO " . $db->getTable('tec_usuarios_has_tbl_unidades') . " (tec_usuarios_id, tbl_unidades_id) VALUES (:uid, :unid)";
            $stmtIns = $pdo->prepare($qIns);
            $stmtIns->execute([':uid' => $usuarioId, ':unid' => $unid]);
        }
    }

    public static function delete($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;

        $db = new DbConection();
        $pdo = $db->openConect();

        try {
            if ($id <= 0) {
                $db->closeConect();
                return Util::error_missing_data();
            }

            $q1 = "DELETE FROM " . $db->getTable('tec_usuarios_has_tec_permisos') . " 
                   WHERE tec_usuarios_id = :id";

            $stmt1 = $pdo->prepare($q1);
            $stmt1->execute([':id' => $id]);

            $qUnid = "DELETE FROM " . $db->getTable('tec_usuarios_has_tbl_unidades') . " 
                      WHERE tec_usuarios_id = :id";

            $stmtUnid = $pdo->prepare($qUnid);
            $stmtUnid->execute([':id' => $id]);

            $q = "DELETE FROM " . $db->getTable('tec_usuarios') . " 
                  WHERE id = :id";

            $stmt = $pdo->prepare($q);
            $ok = $stmt->execute([':id' => $id]);

            if ($ok) {
                $arrjson = array(
                    'output' => array(
                        'valid' => true
                    )
                );
            } else {
                $arrjson = Util::error_generaldelete();
            }

        } catch (PDOException $e) {
            $arrjson = Util::error_general('Error eliminando usuario: ' . $e->getMessage());
        }

        $db->closeConect();
        return $arrjson;
    }

    public static function enable($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        $habilitado = isset($rqst['habilitado']) ? trim($rqst['habilitado']) : 'si';

        $db = new DbConection();
        $pdo = $db->openConect();

        try {
            if ($id <= 0) {
                $db->closeConect();
                return Util::error_missing_data();
            }

            $q = "UPDATE " . $db->getTable('tec_usuarios') . " 
                  SET habilitado = :habilitado 
                  WHERE id = :id";

            $stmt = $pdo->prepare($q);

            $ok = $stmt->execute([
                ':habilitado' => $habilitado,
                ':id' => $id
            ]);

            if ($ok) {
                $arrjson = array(
                    'output' => array(
                        'valid' => true,
                        'response' => array()
                    )
                );
            } else {
                $arrjson = Util::error_general('No fue posible cambiar el estado del usuario');
            }

        } catch (PDOException $e) {
            $arrjson = Util::error_general('Error cambiando estado del usuario: ' . $e->getMessage());
        }

        $db->closeConect();
        return $arrjson;
    }

    public static function getConfisys($rqst)
    {
        $tipo = isset($rqst['tipo']) ? trim($rqst['tipo']) : '';

        $db = new DbConection();
        $pdo = $db->openConect();

        try {
            $q = "SELECT * 
                  FROM " . $db->getTable('tec_confisys');

            $stmt = $pdo->prepare($q);
            $stmt->execute();

            $arr = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $cantidad = 0;

            if ($tipo != "") {
                $q1 = "SELECT COUNT(*) AS total 
                       FROM " . $db->getTable('tec_usuarios') . " 
                       WHERE tipo = :tipo";

                $stmtCantidad = $pdo->prepare($q1);
                $stmtCantidad->execute([':tipo' => $tipo]);

                $rowCantidad = $stmtCantidad->fetch(PDO::FETCH_ASSOC);

                if ($rowCantidad) {
                    $cantidad = intval($rowCantidad['total']);
                }
            }

            $arrjson = array(
                'output' => array(
                    'valid' => true,
                    'response' => $arr,
                    'cantidad' => $cantidad
                )
            );

        } catch (PDOException $e) {
            $arrjson = Util::error_general('Error consultando configuración: ' . $e->getMessage());
        }

        $db->closeConect();
        return $arrjson;
    }
}