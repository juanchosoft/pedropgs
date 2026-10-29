<?php

declare(strict_types=1);

namespace Pgs\Logs;

use Pgs\Auth\Principal;
use Pgs\Core\Db;
use Pgs\Core\Phone;

final class LogService
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    /**
     * AI Tool Execution Logs & token consumption stats (IA_04 §10).
     * Strictly avoids exposing raw text of other users' chat messages.
     *
     * @return array<string, mixed>
     */
    public function iaLogs(Principal $p, array $f, int $page = 1, int $perPage = 30): array
    {
        $p->require('ia.logs.view');

        $where = ['1=1'];
        $params = [];

        if (!empty($f['user_id'])) {
            $where[] = 'l.user_id = :uid';
            $params[':uid'] = (int) $f['user_id'];
        }

        if (!empty($f['tool_name'])) {
            $where[] = 'l.tool_nombre = :tool';
            $params[':tool'] = trim((string) $f['tool_name']);
        }

        if (isset($f['success']) && $f['success'] !== '') {
            $where[] = 'l.exito = :succ';
            $params[':succ'] = (int) $f['success'];
        }

        if (!empty($f['from'])) {
            $where[] = 'l.dt_create >= :from';
            $params[':from'] = $f['from'] . ' 00:00:00';
        }

        if (!empty($f['to'])) {
            $where[] = 'l.dt_create <= :to';
            $params[':to'] = $f['to'] . ' 23:59:59';
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM tec_ia_tool_logs l WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT l.id,
                       l.user_id,
                       l.conversacion_id,
                       l.tool_nombre AS tool_name,
                       l.tool_input AS tool_arguments,
                       l.duracion_ms AS execution_time_ms,
                       l.exito AS success,
                       l.error AS error_message,
                       l.dt_create AS created_at,
                       TRIM(CONCAT(IFNULL(u.nombre, ''), ' ', IFNULL(u.apellido, ''))) AS user_name,
                       u.nickname
                FROM tec_ia_tool_logs l
                LEFT JOIN tec_usuarios u ON u.id = l.user_id
                WHERE {$whereSql}
                ORDER BY l.id DESC
                LIMIT {$offset}, {$perPage}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Daily token statistics (last 14 days)
        $tokenStatsStmt = $this->pdo->query(
            "SELECT DATE(dt_create) as log_date,
                    SUM(tokens_entrada) as total_in,
                    SUM(tokens_salida) as total_out,
                    COUNT(*) as total_messages
             FROM tec_ia_mensajes
             GROUP BY DATE(dt_create)
             ORDER BY log_date DESC
             LIMIT 14"
        );
        $tokenStats = $tokenStatsStmt ? $tokenStatsStmt->fetchAll(\PDO::FETCH_ASSOC) : [];

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'token_stats' => $tokenStats,
        ];
    }

    /**
     * Phone Call Sessions & tool execution traces (IA_04 §10).
     *
     * @return array<string, mixed>
     */
    public function callLogs(Principal $p, array $f, int $page = 1, int $perPage = 25): array
    {
        $p->require('telefonia.calls.view');

        $where = ['1=1'];
        $params = [];

        if (!empty($f['status'])) {
            $where[] = 's.status = :st';
            $params[':st'] = trim((string) $f['status']);
        }

        if (!empty($f['caller_type'])) {
            $where[] = 's.caller_type = :ctype';
            $params[':ctype'] = trim((string) $f['caller_type']);
        }

        if (!empty($f['from'])) {
            $where[] = 's.started_at >= :from';
            $params[':from'] = $f['from'] . ' 00:00:00';
        }

        if (!empty($f['to'])) {
            $where[] = 's.started_at <= :to';
            $params[':to'] = $f['to'] . ' 23:59:59';
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM tec_call_sessions s WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT s.*
                FROM tec_call_sessions s
                WHERE {$whereSql}
                ORDER BY s.id DESC
                LIMIT {$offset}, {$perPage}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rawRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $rows = [];
        foreach ($rawRows as $r) {
            $callerRaw = (string) ($r['caller_e164'] ?? $r['caller_raw'] ?? '');
            $r['caller_phone_masked'] = $callerRaw !== '' ? Phone::mask($callerRaw) : 'Unknown';
            unset($r['caller_e164'], $r['caller_raw']); // privacy
            $rows[] = $r;
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * Retrieves detailed tool traces for a specific call session.
     *
     * @return list<array<string, mixed>>
     */
    public function callSessionTraces(Principal $p, int $sessionId): array
    {
        $p->require('telefonia.calls.view');

        $stmt = $this->pdo->prepare(
            'SELECT * FROM tec_call_logs WHERE call_session_id = :sid ORDER BY id ASC'
        );
        $stmt->execute([':sid' => $sessionId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Audit Log entries with formatted before/after changes (IA_04 §10).
     *
     * @return array<string, mixed>
     */
    public function auditLogs(Principal $p, array $f, int $page = 1, int $perPage = 30): array
    {
        $p->require('auditoria.logs.view');

        $where = ['1=1'];
        $params = [];

        if (!empty($f['actor_user_id'])) {
            $where[] = 'a.actor_user_id = :act_uid';
            $params[':act_uid'] = (int) $f['actor_user_id'];
        }

        if (!empty($f['action'])) {
            $where[] = 'a.action = :act';
            $params[':act'] = trim((string) $f['action']);
        }

        if (!empty($f['entity_type'])) {
            $where[] = 'a.entity_type = :ent_type';
            $params[':ent_type'] = trim((string) $f['entity_type']);
        }

        if (!empty($f['channel'])) {
            $where[] = 'a.channel = :chan';
            $params[':chan'] = trim((string) $f['channel']);
        }

        if (!empty($f['from'])) {
            $where[] = 'a.created_at >= :from';
            $params[':from'] = $f['from'] . ' 00:00:00';
        }

        if (!empty($f['to'])) {
            $where[] = 'a.created_at <= :to';
            $params[':to'] = $f['to'] . ' 23:59:59';
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM tec_audit_log a WHERE {$whereSql}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT a.*,
                       TRIM(CONCAT(IFNULL(u.nombre, ''), ' ', IFNULL(u.apellido, ''))) AS actor_name,
                       u.nickname as actor_nickname
                FROM tec_audit_log a
                LEFT JOIN tec_usuarios u ON u.id = a.actor_user_id
                WHERE {$whereSql}
                ORDER BY a.id DESC
                LIMIT {$offset}, {$perPage}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }
}
