<?php

declare(strict_types=1);

namespace Pgs\Requests;

use Pgs\Admin\AdminContext;
use Pgs\Core\Clock;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\ValidationException;
use Pgs\Notifications\OutboxRepository;

final class RequestsAdminService
{
    private const PER_PAGE_MAX = 100;

    private \PDO $pdo;
    private RequestService $requests;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
        $this->requests = new RequestService($this->pdo);
    }

    /**
     * @param array<string,mixed> $f status, priority, q, from, to, review, page, per_page
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,per_page:int,counts:array<string,int>,system:array{cron_age_s:?int,responders:int}}
     */
    public function list(AdminContext $ctx, array $f): array
    {
        [$scopeSql, $scopeParams] = $this->scope($ctx);
        $where = ['r.deleted_at IS NULL', $scopeSql];
        $params = $scopeParams;

        $status = (string) ($f['status'] ?? 'open');
        if ($status === 'open') {
            $where[] = "r.status IN ('received','assigned','in_progress')";
        } elseif (in_array($status, StatusMachine::STATUSES, true)) {
            $where[] = 'r.status = :status';
            $params[':status'] = $status;
        }
        $priority = (string) ($f['priority'] ?? '');
        if (in_array($priority, StatusMachine::PRIORITIES, true)) {
            $where[] = 'r.priority = :priority';
            $params[':priority'] = $priority;
        }
        if (!empty($f['review'])) {
            $where[] = 'r.needs_review = 1';
        }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(r.code LIKE :q1 OR r.summary LIKE :q2 OR r.property_text LIKE :q3 OR u.nombre LIKE :q4 OR r.caller_e164 LIKE :q5 OR r.caller_name LIKE :q6)';
            foreach (['q1', 'q2', 'q3', 'q4', 'q5', 'q6'] as $k) {
                $params[':' . $k] = '%' . addcslashes($q, '%_\\') . '%';
            }
        }
        foreach (['from' => '>=', 'to' => '<'] as $key => $op) {
            $date = $this->localDate((string) ($f[$key] ?? ''), $key === 'to');
            if ($date !== null) {
                $where[] = "r.received_at {$op} :{$key}";
                $params[':' . $key] = $date;
            }
        }

        $page = max(1, (int) ($f['page'] ?? 1));
        $perPage = min(self::PER_PAGE_MAX, max(1, (int) ($f['per_page'] ?? 25)));
        $whereSql = implode(' AND ', $where);

        $count = $this->pdo->prepare("SELECT COUNT(*) FROM tec_requests r LEFT JOIN tbl_unidades u ON u.id = r.unidad_id WHERE {$whereSql}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT r.id, r.code, r.unidad_id, u.nombre AS unit_name, r.property_text, r.summary, r.priority, r.status,
                    r.caller_name, r.caller_e164, r.needs_review, r.received_at, r.assigned_user_id,
                    CONCAT_WS(' ', a.nombre, a.apellido) AS assigned_name
             FROM tec_requests r
             LEFT JOIN tbl_unidades u ON u.id = r.unidad_id
             LEFT JOIN tec_usuarios a ON a.id = r.assigned_user_id
             WHERE {$whereSql}
             ORDER BY (r.status = 'received') DESC, FIELD(r.priority, 'urgent', 'high', 'normal', 'low'), r.received_at DESC
             LIMIT " . $perPage . ' OFFSET ' . (($page - 1) * $perPage)
        );
        $stmt->execute($params);
        $now = Clock::nowUtc()->getTimestamp();
        $rows = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $row['received_local'] = Clock::toLocal((string) $row['received_at'])->format('M j, g:i A');
            $row['age_minutes'] = max(0, (int) floor(($now - strtotime((string) $row['received_at'] . ' UTC')) / 60));
            $rows[] = $row;
        }

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'counts' => $this->counts($ctx), 'system' => $this->systemStatus()];
    }

    /**
     * @return array{cron_age_s:?int,responders:int}
     */
    private function systemStatus(): array
    {
        $last = (string) $this->pdo->query("SELECT setting_value FROM tec_settings WHERE setting_key = 'cron.last_tick'")->fetchColumn();
        $age = $last !== '' ? max(0, Clock::nowUtc()->getTimestamp() - (int) strtotime($last . ' UTC')) : null;
        $responders = (int) $this->pdo->query('SELECT COUNT(*) FROM tec_responders WHERE is_active = 1 AND deleted_at IS NULL AND receives_alerts = 1')->fetchColumn();

        return ['cron_age_s' => $age, 'responders' => $responders];
    }

    /**
     * @return array{request:array<string,mixed>,timeline:list<array<string,mixed>>,deliveries:list<array<string,mixed>>,next:list<string>,can_manage:bool}
     */
    public function detail(AdminContext $ctx, int $id): array
    {
        $row = $this->visible($ctx, $id);
        $manage = $ctx->can('solicitudes.requests.manage');
        $row['received_local'] = Clock::toLocal((string) $row['received_at'])->format('M j, Y g:i A');
        $name = $row['assigned_user_id'] !== null ? $this->userName((int) $row['assigned_user_id']) : null;
        $row['assigned_name'] = $name;

        $timeline = $this->requests->timeline($id);
        foreach ($timeline as &$event) {
            $event['created_local'] = Clock::toLocal((string) $event['created_at'])->format('M j, g:i A');
        }
        unset($event);

        $next = StatusMachine::nextOptions((string) $row['status']);
        if (!$manage) {
            $next = [];
        }

        return [
            'request' => $row,
            'timeline' => $timeline,
            'deliveries' => $manage ? (new OutboxRepository($this->pdo))->forRequest($id) : [],
            'next' => $next,
            'can_manage' => $manage,
        ];
    }

    public function changeStatus(AdminContext $ctx, int $id, string $status, ?string $note): array
    {
        $this->assertManage($ctx);
        $this->visible($ctx, $id);

        return $this->requests->changeStatus($id, $status, $ctx->userId, null, $note, true);
    }

    public function assign(AdminContext $ctx, int $id, ?int $userId, ?string $note): array
    {
        $this->assertManage($ctx);
        $this->visible($ctx, $id);
        if ($userId !== null) {
            $exists = $this->pdo->prepare("SELECT COUNT(*) FROM tec_usuarios WHERE id = :id AND habilitado IN ('yes','si')");
            $exists->execute([':id' => $userId]);
            if ((int) $exists->fetchColumn() === 0) {
                throw new ValidationException(['user_id' => 'User not found']);
            }
        }

        $name = $userId !== null ? $this->userName($userId) : null;
        $note ??= $userId === null ? 'Unassigned' : 'Assigned to ' . ($name ?? 'user #' . $userId);

        return $this->requests->assign($id, $userId, $ctx->userId, $note);
    }

    public function addNote(AdminContext $ctx, int $id, string $note): void
    {
        $this->assertManage($ctx);
        $this->visible($ctx, $id);
        $this->requests->addNote($id, $ctx->userId, $note);
    }

    public function markReviewed(AdminContext $ctx, int $id): void
    {
        $this->assertManage($ctx);
        $this->visible($ctx, $id);
        $this->requests->markReviewed($id, $ctx->userId);
    }

    /**
     * Updates the property of a request that could not be identified on the call.
     */
    public function setProperty(AdminContext $ctx, int $id, int $unitId): void
    {
        $this->assertManage($ctx);
        $this->visible($ctx, $id);
        $exists = $this->pdo->prepare('SELECT COUNT(*) FROM tbl_unidades WHERE id = :id');
        $exists->execute([':id' => $unitId]);
        if ((int) $exists->fetchColumn() === 0) {
            throw new ValidationException(['unidad_id' => 'Property not found']);
        }
        $this->pdo->prepare('UPDATE tec_requests SET unidad_id = :u, needs_review = 0, updated_at = :now WHERE id = :id')
            ->execute([':u' => $unitId, ':id' => $id, ':now' => Clock::nowUtcSql()]);
        $this->requests->addNote($id, $ctx->userId, 'Property set manually');
    }

    /**
     * @return array{received:int,urgent:int,review:int}
     */
    public function summary(AdminContext $ctx): array
    {
        [$scopeSql, $params] = $this->scope($ctx);
        $stmt = $this->pdo->prepare(
            "SELECT SUM(r.status = 'received') AS received,
                    SUM(r.status = 'received' AND r.priority = 'urgent') AS urgent,
                    SUM(r.needs_review = 1 AND r.status IN ('received','assigned','in_progress')) AS review
             FROM tec_requests r WHERE r.deleted_at IS NULL AND {$scopeSql}"
        );
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        return ['received' => (int) ($row['received'] ?? 0), 'urgent' => (int) ($row['urgent'] ?? 0), 'review' => (int) ($row['review'] ?? 0)];
    }

    /**
     * @return list<array{id:int,name:string}>
     */
    public function assignableUsers(AdminContext $ctx): array
    {
        $this->assertManage($ctx);

        return $this->userDirectory();
    }

    /**
     * Enabled users for pickers. The caller is responsible for authorising access.
     *
     * @return list<array{id:int,name:string}>
     */
    public function userDirectory(): array
    {
        $rows = $this->pdo->query(
            "SELECT id, TRIM(CONCAT_WS(' ', nombre, apellido)) AS name, nickname FROM tec_usuarios
             WHERE habilitado IN ('yes','si') ORDER BY nombre ASC, apellido ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'name' => ($r['name'] !== '' ? $r['name'] : (string) $r['nickname'])], $rows);
    }

    /**
     * @return list<array{id:int,name:string}>
     */
    public function properties(AdminContext $ctx): array
    {
        $this->assertManage($ctx);
        $rows = $this->pdo->query("SELECT id, nombre FROM tbl_unidades WHERE enable IN ('yes','si') ORDER BY nombre ASC")->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'name' => (string) $r['nombre']], $rows);
    }

    /** @return array<string,int> */
    private function counts(AdminContext $ctx): array
    {
        [$scopeSql, $params] = $this->scope($ctx);
        $stmt = $this->pdo->prepare("SELECT r.status, COUNT(*) AS n FROM tec_requests r WHERE r.deleted_at IS NULL AND {$scopeSql} GROUP BY r.status");
        $stmt->execute($params);
        $counts = array_fill_keys(StatusMachine::STATUSES, 0);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $counts[(string) $row['status']] = (int) $row['n'];
        }
        $counts['open'] = $counts['received'] + $counts['assigned'] + $counts['in_progress'];

        return $counts;
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private function scope(AdminContext $ctx): array
    {
        if ($ctx->unitIds === null) {
            return ['1=1', []];
        }
        if ($ctx->unitIds === []) {
            return ['1=0', []];
        }
        $params = [];
        $names = [];
        foreach ($ctx->unitIds as $i => $unit) {
            $names[] = ':sc' . $i;
            $params[':sc' . $i] = $unit;
        }

        return ['r.unidad_id IN (' . implode(',', $names) . ')', $params];
    }

    /** @return array<string,mixed> */
    private function visible(AdminContext $ctx, int $id): array
    {
        $row = $this->requests->get($id);
        if (!$ctx->canSeeUnit($row['unidad_id'] !== null ? (int) $row['unidad_id'] : null)) {
            throw new NotFoundException('Request not found');
        }

        return $row;
    }

    private function assertManage(AdminContext $ctx): void
    {
        if (!$ctx->can('solicitudes.requests.manage')) {
            throw new ForbiddenException('You are not allowed to manage requests');
        }
    }

    private function userName(int $id): ?string
    {
        $stmt = $this->pdo->prepare("SELECT TRIM(CONCAT_WS(' ', nombre, apellido)) FROM tec_usuarios WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $name = $stmt->fetchColumn();

        return $name === false || $name === '' ? null : (string) $name;
    }

    private function localDate(string $value, bool $endExclusive): ?string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        $local = new \DateTimeImmutable($value . ' 00:00:00', Clock::businessTz());
        if ($endExclusive) {
            $local = $local->modify('+1 day');
        }

        return $local->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
