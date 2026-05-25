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
                 * también traemos los días de descanso desde tec_employee.
                 *
                 * Relación correcta:
                 * tec_usuarios.employee_id = tec_employee.cc
                 */
                $q = "SELECT 
                        u.*,
                        e.dias_descanso,
                        CASE 
                            WHEN u.employee_id IS NOT NULL AND u.employee_id <> '' THEN 'si'
                            ELSE 'no'
                        END AS es_empleado
                      FROM " . $db->getTable('tec_usuarios') . " AS u
                      LEFT JOIN " . $db->getTable('tec_employee') . " AS e
                        ON e.cc = u.employee_id
                      WHERE u.id = :id
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
                    'unidad' => $unidad
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
        $employee_id = isset($rqst['employee_id']) ? trim($rqst['employee_id']) : '';
        $nombre = isset($rqst['nombre']) ? trim($rqst['nombre']) : '';
        $apellido = isset($rqst['apellido']) ? trim($rqst['apellido']) : '';
        $tipo = isset($rqst['tipo']) ? trim($rqst['tipo']) : '';
        $tbl_unidad_id = isset($rqst['tbl_unidad_id']) ? intval($rqst['tbl_unidad_id']) : 0;
        $habilitado = isset($rqst['habilitado']) ? trim($rqst['habilitado']) : '';

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
                 * Si tiene Employee ID, se sincroniza con tec_employee usando cc.
                 * No se modifican los días restantes.
                 */
                if ($employee_id != "") {
                    self::syncEmployee($pdo, $db, $employee_id, $nombre, $apellido, $nickname, $tbl_unidad_id);
                }

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
             * Solo crea o actualiza empleado cuando employee_id viene con información.
             */
            if ($employee_id != "") {
                self::syncEmployee($pdo, $db, $employee_id, $nombre, $apellido, $nickname, $tbl_unidad_id);
            }

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

    private static function syncEmployee($pdo, $db, $employee_id, $nombre, $apellido, $email, $tbl_unidad_id)
    {
        $employee_id = trim($employee_id);

        if ($employee_id == "") {
            return true;
        }

        $nombreCompleto = trim($nombre . " " . $apellido);

        /*
         * IMPORTANTE:
         * En tec_usuarios el campo se llama employee_id.
         * En tec_employee el campo real se llama cc.
         *
         * Relación:
         * tec_usuarios.employee_id = tec_employee.cc
         */

        $qCheck = "SELECT id 
                   FROM " . $db->getTable('tec_employee') . " 
                   WHERE cc = :cc 
                   LIMIT 1";

        $stmtCheck = $pdo->prepare($qCheck);
        $stmtCheck->execute([
            ':cc' => $employee_id
        ]);

        $empleado = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($empleado) {
            /*
             * Si existe, actualizamos solo información básica.
             * No tocamos dias_descanso para no borrar los días restantes.
             */
            $qUpdate = "UPDATE " . $db->getTable('tec_employee') . " SET
                            nombre = :nombre,
                            email = :email,
                            enable = :enable,
                            tbl_unidad_id = :tbl_unidad_id
                        WHERE cc = :cc";

            $stmtUpdate = $pdo->prepare($qUpdate);

            return $stmtUpdate->execute([
                ':nombre' => $nombreCompleto,
                ':email' => $email,
                ':enable' => 'si',
                ':tbl_unidad_id' => $tbl_unidad_id,
                ':cc' => $employee_id
            ]);
        }

        /*
         * Si no existe, lo creamos.
         * dias_descanso inicia en 0 para que después puedas actualizarlo desde empleados.
         */
        $qInsert = "INSERT INTO " . $db->getTable('tec_employee') . " 
                    (
                        dtcreate, 
                        nombre, 
                        cc, 
                        email, 
                        enable, 
                        tbl_unidad_id,
                        dias_descanso
                    ) 
                    VALUES 
                    (
                        " . Util::date_now_server() . ", 
                        :nombre, 
                        :cc, 
                        :email, 
                        :enable, 
                        :tbl_unidad_id,
                        :dias_descanso
                    )";

        $stmtInsert = $pdo->prepare($qInsert);

        return $stmtInsert->execute([
            ':nombre' => $nombreCompleto,
            ':cc' => $employee_id,
            ':email' => $email,
            ':enable' => 'si',
            ':tbl_unidad_id' => $tbl_unidad_id,
            ':dias_descanso' => 0
        ]);
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