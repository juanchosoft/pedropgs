<?php

/**
 * Reportes de actividades (tbl_fotos).
 */
class Report
{
    public const ESTADO_CREADO = 'creado';
    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_FINALIZADO = 'finalizado';

    public function __construct()
    {
    }

    public static function statusLabel(?string $estado): string
    {
        switch ($estado) {
            case self::ESTADO_CREADO:
                return 'Created / Pending';
            case self::ESTADO_PENDIENTE:
                return 'Pending';
            case self::ESTADO_FINALIZADO:
                return 'Finalized';
            default:
                return $estado !== null && $estado !== '' ? $estado : '—';
        }
    }

    public static function isEditable(?string $estado): bool
    {
        return in_array($estado, [self::ESTADO_CREADO, self::ESTADO_PENDIENTE], true)
            || $estado === null
            || $estado === '';
    }

    public static function getAll($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        $db = new DbConection();
        $pdo = $db->openConect();

        $fotos = $db->getTable('tbl_fotos');
        $unidades = $db->getTable('tbl_unidades');

        // tbl_requerimiento_id = Property seleccionada en el formulario (id de tbl_unidades)
        $select = "SELECT f.*,
                         COALESCE(u_prop.nombre, u_user.nombre) AS unidad_nombre,
                         u_prop.nombre AS propiedad_nombre,
                         u_user.nombre AS unidad_usuario_nombre
                  FROM {$fotos} AS f
                  LEFT JOIN {$unidades} AS u_prop ON u_prop.id = f.tbl_requerimiento_id
                  LEFT JOIN {$unidades} AS u_user ON u_user.id = f.tbl_unidad_id";

        $q = $select . " ORDER BY f.dtcreate DESC LIMIT 20";
        if ($id > 0) {
            $q = $select . " WHERE f.id = " . $id;
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
     * Último reporte del usuario en sesión no finalizado (creado / pendiente).
     */
    public static function getLastUnfinished($rqst = [])
    {
        $userId = isset($_SESSION['session_user']['id']) ? (int) $_SESSION['session_user']['id'] : 0;
        if ($userId <= 0) {
            return Util::error_general('Session required');
        }

        $db = new DbConection();
        $pdo = $db->openConect();
        try {
            $sql = "SELECT id, actividades, observaciones, foto_antes, foto_despues, estado, dtcreate
                    FROM " . $db->getTable('tbl_fotos') . "
                    WHERE tbl_usuario_id = :uid
                      AND (estado IS NULL OR estado = '' OR estado IN (:e1, :e2))
                    ORDER BY id DESC
                    LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':uid' => $userId,
                ':e1' => self::ESTADO_CREADO,
                ':e2' => self::ESTADO_PENDIENTE,
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return ['output' => ['valid' => true, 'response' => null]];
            }
            return ['output' => ['valid' => true, 'response' => $row]];
        } catch (Throwable $e) {
            return Util::error_general('Error loading unfinished report: ' . $e->getMessage());
        } finally {
            $db->closeConect();
        }
    }

    public static function updateFields($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        $actividades = isset($rqst['actividades']) ? ($rqst['actividades']) : '';
        $observaciones = isset($rqst['observaciones']) ? ($rqst['observaciones']) : '';
        $tbl_usuario_id = $_SESSION['session_user']['id'];

        $db = new DbConection();
        $pdo = $db->openConect();
        $arrjson = Util::error_missing_data();

        if ($id > 0 && $actividades != "") {
            $q = "SELECT id, estado FROM " . $db->getTable('tbl_fotos') . " WHERE id = " . $id;
            $result = $pdo->query($q);
            $row = $result ? $result->fetch(PDO::FETCH_ASSOC) : false;
            if ($row) {
                if (!self::isEditable($row['estado'] ?? null)) {
                    $db->closeConect();
                    return Util::error_general('This report is already finalized.');
                }
                $table = $db->getTable('tbl_fotos');
                $arrfieldscomma = array(
                    'actividades' => $actividades,
                    'observaciones' => $observaciones,
                    'estado' => self::ESTADO_PENDIENTE,
                    'tbl_usuario_id_update' => $tbl_usuario_id
                );
                $arrfieldsnocomma = array('dtcreate' => Util::date_now_server());
                $q = Util::make_query_update($table, "id = '$id'", $arrfieldscomma, $arrfieldsnocomma);
                $result = $pdo->query($q);
                if (!$result) {
                    $arrjson = Util::error_general('Updating report');
                } else {
                    $arrjson = array('output' => array('valid' => true, 'id' => $id, 'estado' => self::ESTADO_PENDIENTE));
                }
            }
        }

        $db->closeConect();
        return $arrjson;
    }

    public static function finalize($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;
        if ($id <= 0) {
            return Util::error_missing_data();
        }

        $tbl_usuario_id = $_SESSION['session_user']['id'] ?? 0;
        $db = new DbConection();
        $pdo = $db->openConect();

        try {
            $stmt = $pdo->prepare("SELECT id, estado FROM " . $db->getTable('tbl_fotos') . " WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return Util::error_no_result();
            }
            if (($row['estado'] ?? '') === self::ESTADO_FINALIZADO) {
                return ['output' => ['valid' => true, 'id' => $id, 'estado' => self::ESTADO_FINALIZADO]];
            }
            if (!self::isEditable($row['estado'] ?? null)) {
                return Util::error_general('This report cannot be finalized.');
            }

            $table = $db->getTable('tbl_fotos');
            $arrfieldscomma = array(
                'estado' => self::ESTADO_FINALIZADO,
                'tbl_usuario_id_update' => $tbl_usuario_id,
            );
            $arrfieldsnocomma = array('dtcreate' => Util::date_now_server());
            $q = Util::make_query_update($table, "id = '$id'", $arrfieldscomma, $arrfieldsnocomma);
            if (!$pdo->query($q)) {
                return Util::error_general('Finalizing report');
            }
            return ['output' => ['valid' => true, 'id' => $id, 'estado' => self::ESTADO_FINALIZADO]];
        } catch (Throwable $e) {
            return Util::error_general('Error finalizing report: ' . $e->getMessage());
        } finally {
            $db->closeConect();
        }
    }

    public static function delete($rqst)
    {
        $id = isset($rqst['id']) ? intval($rqst['id']) : 0;

        $db = new DbConection();
        $pdo = $db->openConect();

        $q = "DELETE FROM " . $db->getTable('tbl_fotos') . " WHERE id = " . $id;
        $result = $pdo->query($q);
        if ($result) {
            $arrjson = array('output' => array('valid' => true, 'error' => $pdo->errorInfo()));
        } else {
            $arrjson = Util::error_generaldelete();
        }
        $db->closeConect();

        return $arrjson;
    }
}
