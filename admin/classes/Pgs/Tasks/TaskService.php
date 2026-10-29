<?php

declare(strict_types=1);

namespace Pgs\Tasks;

use Pgs\Auth\Policy\TaskPolicy;
use Pgs\Auth\Principal;
use Pgs\Auth\UnitScope;
use Pgs\Core\AuditLogger;
use Pgs\Core\Clock;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\ConflictException;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\ValidationException;
use Pgs\Core\Sequence;
use Pgs\Integrations\SyncEnqueuer;

final class TaskService
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    public function create(Principal $p, array $in): array
    {
        $unitId = isset($in['unidad_id']) && $in['unidad_id'] !== '' && $in['unidad_id'] !== null ? (int) $in['unidad_id'] : null;
        if (!TaskPolicy::canCreate($p, $unitId)) {
            throw new ForbiddenException('Cannot create tasks in this unit.');
        }

        $title = trim((string) ($in['title'] ?? ''));
        if (mb_strlen($title) < 3 || mb_strlen($title) > 160) {
            throw new ValidationException('Title must be between 3 and 160 characters.');
        }

        $desc = isset($in['description']) ? trim((string) $in['description']) : null;
        if ($desc !== null && mb_strlen($desc) > 5000) {
            throw new ValidationException('Description exceeds 5000 characters.');
        }

        $priority = (string) ($in['priority'] ?? 'normal');
        if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            throw new ValidationException('Invalid priority.');
        }

        $origin = (string) ($in['origin'] ?? 'manual');
        if (!in_array($origin, ['manual', 'service_request', 'phone', 'assistant', 'migration', 'phone_escalation'], true)) {
            $origin = 'manual';
        }

        $locationLabel = isset($in['location_label']) ? trim((string) $in['location_label']) : null;
        if ($locationLabel !== null && mb_strlen($locationLabel) > 120) {
            throw new ValidationException('Location label exceeds 120 characters.');
        }

        $serviceId = isset($in['service_id']) && $in['service_id'] !== '' ? (int) $in['service_id'] : null;
        if ($serviceId !== null) {
            $svcStmt = $this->pdo->prepare('SELECT id, is_active FROM tec_services WHERE id = :id AND deleted_at IS NULL');
            $svcStmt->execute([':id' => $serviceId]);
            $svc = $svcStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$svc || !(bool) $svc['is_active']) {
                throw new ValidationException('Selected service is inactive or does not exist.');
            }
        }

        $requiredDate = !empty($in['required_date']) ? (string) $in['required_date'] : null;
        $dueDate = !empty($in['due_date']) ? (string) $in['due_date'] : null;

        if ($requiredDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $requiredDate)) {
            throw new ValidationException('Invalid required date format.');
        }
        if ($dueDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            throw new ValidationException('Invalid due date format.');
        }
        if ($requiredDate !== null && $dueDate !== null && $dueDate < $requiredDate) {
            throw new ValidationException('Due date cannot be before required date.');
        }

        $assigneeId = isset($in['assignee_employee_id']) && $in['assignee_employee_id'] !== '' ? (int) $in['assignee_employee_id'] : null;
        $status = 'pending';
        if ($assigneeId !== null) {
            if (!TaskPolicy::canAssign($p)) {
                throw new ForbiddenException('You do not have permission to assign tasks.');
            }
            $empStmt = $this->pdo->prepare("SELECT id FROM tec_employee WHERE id = :id AND enable IN ('si','yes')");
            $empStmt->execute([':id' => $assigneeId]);
            if (!$empStmt->fetch(\PDO::FETCH_ASSOC)) {
                throw new ValidationException('Assigned employee does not exist.');
            }
            \Pgs\Workers\EmployeeScope::assertUsable($p, $assigneeId, $unitId, $this->pdo);
            $status = 'assigned';
        }

        $now = Clock::nowUtc();
        $code = Sequence::code($this->pdo, 'TSK', $now);
        $nowStr = $now->format('Y-m-d H:i:s');

        $reqUser = $in['requested_by_user_id'] ?? $p->userId;
        $reqName = $in['requested_by_name'] ?? null;
        if ($reqName === null && $p->userId !== null) {
            $nickStmt = $this->pdo->prepare('SELECT nickname FROM tec_usuarios WHERE id = :id');
            $nickStmt->execute([':id' => $p->userId]);
            $reqName = $nickStmt->fetchColumn() ?: null;
        }
        $reqPhone = $in['requested_by_phone'] ?? null;
        $reqVerification = $in['requested_verification'] ?? 'session';

        $ownsTx = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $ownsTx = true;
        }
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO tec_tasks (
                    code, origin, unidad_id, location_label, service_id,
                    requested_by_user_id, requested_by_phone, requested_by_name, requested_verification,
                    title, description, priority, status, assignee_employee_id,
                    required_date, due_date, created_by_user_id, created_at, updated_at, row_version
                ) VALUES (
                    :code, :origin, :unidad_id, :location_label, :service_id,
                    :requested_by_user_id, :requested_by_phone, :requested_by_name, :requested_verification,
                    :title, :description, :priority, :status, :assignee_employee_id,
                    :required_date, :due_date, :created_by_user_id, :created_at, :updated_at, 1
                )'
            );
            $stmt->execute([
                ':code' => $code,
                ':origin' => $origin,
                ':unidad_id' => $unitId,
                ':location_label' => $locationLabel,
                ':service_id' => $serviceId,
                ':requested_by_user_id' => $reqUser,
                ':requested_by_phone' => $reqPhone,
                ':requested_by_name' => $reqName,
                ':requested_verification' => $reqVerification,
                ':title' => $title,
                ':description' => $desc,
                ':priority' => $priority,
                ':status' => $status,
                ':assignee_employee_id' => $assigneeId,
                ':required_date' => $requiredDate,
                ':due_date' => $dueDate,
                ':created_by_user_id' => $p->userId,
                ':created_at' => $nowStr,
                ':updated_at' => $nowStr,
            ]);
            $taskId = (int) $this->pdo->lastInsertId();

            // History
            $histStmt = $this->pdo->prepare(
                'INSERT INTO tec_task_history (task_id, event, to_status, changes, note, actor_kind, actor_user_id, channel, created_at)
                 VALUES (:task_id, "created", :to_status, :changes, :note, "user", :actor_user_id, "web", :created_at)'
            );
            $histStmt->execute([
                ':task_id' => $taskId,
                ':to_status' => $status,
                ':changes' => json_encode(['title' => $title, 'status' => $status, 'priority' => $priority], JSON_UNESCAPED_SLASHES),
                ':note' => 'Task created',
                ':actor_user_id' => $p->userId,
                ':created_at' => $nowStr,
            ]);

            AuditLogger::log($p, 'task.created', 'task', $taskId, null, [
                'code' => $code,
                'title' => $title,
                'status' => $status,
            ], $unitId);

            SyncEnqueuer::task($this->pdo, $taskId, $dueDate !== null || $requiredDate !== null);

            if ($ownsTx) {
                $this->pdo->commit();
            }

            TaskNotifier::created($this->pdo, $p, $taskId);
            if ($assigneeId) {
                TaskNotifier::assigned($this->pdo, $p, $taskId, (int) $assigneeId);
            }

            return $this->get($p, $taskId);
        } catch (\Throwable $e) {
            if ($ownsTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function update(Principal $p, int $id, array $in, int $rowVersion): array
    {
        $task = $this->loadRaw($id);
        if (!TaskPolicy::canUpdate($p, $task)) {
            throw new ForbiddenException('Cannot edit this task.');
        }

        if ((int) $task['row_version'] !== $rowVersion) {
            throw new ConflictException('The task was modified by another user. Please refresh and try again.');
        }

        $title = isset($in['title']) ? trim((string) $in['title']) : (string) $task['title'];
        if (mb_strlen($title) < 3 || mb_strlen($title) > 160) {
            throw new ValidationException('Title must be between 3 and 160 characters.');
        }

        $desc = isset($in['description']) ? trim((string) $in['description']) : $task['description'];
        if ($desc !== null && mb_strlen($desc) > 5000) {
            throw new ValidationException('Description exceeds 5000 characters.');
        }

        $priority = isset($in['priority']) ? (string) $in['priority'] : (string) $task['priority'];
        if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            throw new ValidationException('Invalid priority.');
        }

        $unitId = isset($in['unidad_id']) && $in['unidad_id'] !== '' ? (int) $in['unidad_id'] : $task['unidad_id'];
        if ($unitId !== null) {
            UnitScope::assertUnit($p, (int) $unitId);
        }

        $locationLabel = isset($in['location_label']) ? trim((string) $in['location_label']) : $task['location_label'];
        if ($locationLabel !== null && mb_strlen($locationLabel) > 120) {
            throw new ValidationException('Location label exceeds 120 characters.');
        }

        $requiredDate = array_key_exists('required_date', $in) ? (!empty($in['required_date']) ? (string) $in['required_date'] : null) : $task['required_date'];
        $dueDate = array_key_exists('due_date', $in) ? (!empty($in['due_date']) ? (string) $in['due_date'] : null) : $task['due_date'];

        if ($requiredDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $requiredDate)) {
            throw new ValidationException('Invalid required date format.');
        }
        if ($dueDate !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
            throw new ValidationException('Invalid due date format.');
        }
        if ($requiredDate !== null && $dueDate !== null && $dueDate < $requiredDate) {
            throw new ValidationException('Due date cannot be before required date.');
        }

        $nowStr = Clock::nowUtc()->format('Y-m-d H:i:s');
        $changes = [];
        if ($title !== $task['title']) $changes['title'] = ['from' => $task['title'], 'to' => $title];
        if ($desc !== $task['description']) $changes['description'] = 'modified';
        if ($priority !== $task['priority']) $changes['priority'] = ['from' => $task['priority'], 'to' => $priority];
        if ($unitId != $task['unidad_id']) $changes['unidad_id'] = ['from' => $task['unidad_id'], 'to' => $unitId];
        if ($locationLabel !== $task['location_label']) $changes['location_label'] = ['from' => $task['location_label'], 'to' => $locationLabel];
        if ($requiredDate !== $task['required_date']) $changes['required_date'] = ['from' => $task['required_date'], 'to' => $requiredDate];
        if ($dueDate !== $task['due_date']) $changes['due_date'] = ['from' => $task['due_date'], 'to' => $dueDate];

        $ownsTx = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $ownsTx = true;
        }
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE tec_tasks SET
                    title = :title, description = :description, priority = :priority,
                    unidad_id = :unidad_id, location_label = :location_label,
                    required_date = :required_date, due_date = :due_date,
                    updated_at = :updated_at, row_version = row_version + 1
                 WHERE id = :id AND row_version = :row_version'
            );
            $stmt->execute([
                ':title' => $title,
                ':description' => $desc,
                ':priority' => $priority,
                ':unidad_id' => $unitId,
                ':location_label' => $locationLabel,
                ':required_date' => $requiredDate,
                ':due_date' => $dueDate,
                ':updated_at' => $nowStr,
                ':id' => $id,
                ':row_version' => $rowVersion,
            ]);

            if ($stmt->rowCount() === 0) {
                throw new ConflictException('Concurrent update detected.');
            }

            if (!empty($changes)) {
                $histStmt = $this->pdo->prepare(
                    'INSERT INTO tec_task_history (task_id, event, from_status, to_status, changes, note, actor_kind, actor_user_id, channel, created_at)
                     VALUES (:task_id, "updated", :status, :status, :changes, :note, "user", :actor_user_id, "web", :created_at)'
                );
                $histStmt->execute([
                    ':task_id' => $id,
                    ':status' => $task['status'],
                    ':changes' => json_encode($changes, JSON_UNESCAPED_SLASHES),
                    ':note' => 'Task details updated',
                    ':actor_user_id' => $p->userId,
                    ':created_at' => $nowStr,
                ]);

                AuditLogger::log($p, 'task.updated', 'task', $id, $changes, null, $unitId !== null ? (int) $unitId : null);
                SyncEnqueuer::task($this->pdo, $id, $dueDate !== null || $requiredDate !== null);
            }

            if ($ownsTx) {
                $this->pdo->commit();
            }

            return $this->get($p, $id);
        } catch (\Throwable $e) {
            if ($ownsTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function assign(Principal $p, int $id, ?int $employeeId, ?string $note): array
    {
        $task = $this->loadRaw($id);
        $targetStatus = $employeeId !== null ? 'assigned' : 'pending';

        TaskStateMachine::assertCan($p, $task, $targetStatus);

        if ($employeeId !== null) {
            $empStmt = $this->pdo->prepare('SELECT id, nombre FROM tec_employee WHERE id = :id');
            $empStmt->execute([':id' => $employeeId]);
            $emp = $empStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$emp) {
                throw new ValidationException('Employee does not exist.');
            }
            \Pgs\Workers\EmployeeScope::assertUsable($p, $employeeId, $task['unidad_id'] !== null ? (int) $task['unidad_id'] : null, $this->pdo);
        }

        $nowStr = Clock::nowUtc()->format('Y-m-d H:i:s');
        $fromStatus = $task['status'];
        $event = $employeeId !== null ? 'assigned' : 'unassigned';

        $ownsTx = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $ownsTx = true;
        }
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE tec_tasks SET
                    assignee_employee_id = :emp_id,
                    status = :status,
                    updated_at = :updated_at,
                    row_version = row_version + 1
                 WHERE id = :id'
            );
            $stmt->execute([
                ':emp_id' => $employeeId,
                ':status' => $targetStatus,
                ':updated_at' => $nowStr,
                ':id' => $id,
            ]);

            $histStmt = $this->pdo->prepare(
                'INSERT INTO tec_task_history (task_id, event, from_status, to_status, changes, note, actor_kind, actor_user_id, channel, created_at)
                 VALUES (:task_id, :event, :from_status, :to_status, :changes, :note, "user", :actor_user_id, "web", :created_at)'
            );
            $histStmt->execute([
                ':task_id' => $id,
                ':event' => $event,
                ':from_status' => $fromStatus,
                ':to_status' => $targetStatus,
                ':changes' => json_encode(['assignee_employee_id' => ['from' => $task['assignee_employee_id'], 'to' => $employeeId]], JSON_UNESCAPED_SLASHES),
                ':note' => $note ?: ($employeeId ? "Assigned to employee #{$employeeId}" : 'Unassigned employee'),
                ':actor_user_id' => $p->userId,
                ':created_at' => $nowStr,
            ]);

            AuditLogger::log($p, 'task.assigned', 'task', $id, [
                'status' => $fromStatus,
            ], [
                'status' => $targetStatus,
                'assignee_employee_id' => $employeeId,
            ], $task['unidad_id'] !== null ? (int) $task['unidad_id'] : null);

            SyncEnqueuer::task($this->pdo, $id, !empty($task['due_date']) || !empty($task['required_date']));

            if ($ownsTx) {
                $this->pdo->commit();
            }

            TaskNotifier::assigned($this->pdo, $p, $id, $employeeId);

            return $this->get($p, $id);
        } catch (\Throwable $e) {
            if ($ownsTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function changeStatus(Principal $p, int $id, string $to, ?string $note, ?string $cancelReason): array
    {
        $task = $this->loadRaw($id);
        $from = (string) $task['status'];

        TaskStateMachine::assertCan($p, $task, $to);

        $nowStr = Clock::nowUtc()->format('Y-m-d H:i:s');
        $completedAt = $task['completed_at'];
        $cancelledAt = $task['cancelled_at'];
        $reason = $task['cancel_reason'];

        if ($to === 'cancelled') {
            $reason = trim((string) $cancelReason);
            if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
                throw new ValidationException('Cancellation reason is required (3 to 255 characters).');
            }
            $cancelledAt = $nowStr;
        } elseif ($to === 'completed') {
            $completedAt = $nowStr;
        } elseif ($from === 'completed' && $to === 'in_progress') {
            if (empty(trim((string) $note))) {
                throw new ValidationException('A note explaining why the completed task is reopened is required.');
            }
            $completedAt = null;
        } elseif ($from === 'cancelled' && $to === 'pending') {
            $cancelledAt = null;
            $reason = null;
        }

        $ownsTx = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $ownsTx = true;
        }
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE tec_tasks SET
                    status = :status,
                    cancel_reason = :cancel_reason,
                    completed_at = :completed_at,
                    cancelled_at = :cancelled_at,
                    updated_at = :updated_at,
                    row_version = row_version + 1
                 WHERE id = :id'
            );
            $stmt->execute([
                ':status' => $to,
                ':cancel_reason' => $reason,
                ':completed_at' => $completedAt,
                ':cancelled_at' => $cancelledAt,
                ':updated_at' => $nowStr,
                ':id' => $id,
            ]);

            $histStmt = $this->pdo->prepare(
                'INSERT INTO tec_task_history (task_id, event, from_status, to_status, changes, note, actor_kind, actor_user_id, channel, created_at)
                 VALUES (:task_id, "status_changed", :from_status, :to_status, :changes, :note, "user", :actor_user_id, "web", :created_at)'
            );
            $histStmt->execute([
                ':task_id' => $id,
                ':from_status' => $from,
                ':to_status' => $to,
                ':changes' => json_encode(['status' => ['from' => $from, 'to' => $to]], JSON_UNESCAPED_SLASHES),
                ':note' => $note ?: "Status changed from {$from} to {$to}",
                ':actor_user_id' => $p->userId,
                ':created_at' => $nowStr,
            ]);

            AuditLogger::log($p, 'task.status_changed', 'task', $id, [
                'status' => $from,
            ], [
                'status' => $to,
                'cancel_reason' => $reason,
            ], $task['unidad_id'] !== null ? (int) $task['unidad_id'] : null);

            SyncEnqueuer::task($this->pdo, $id, !empty($task['due_date']) || !empty($task['required_date']));

            if ($ownsTx) {
                $this->pdo->commit();
            }

            TaskNotifier::statusChanged($this->pdo, $p, $id, (string) $from, (string) $to);

            return $this->get($p, $id);
        } catch (\Throwable $e) {
            if ($ownsTx && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function list(Principal $p, array $filters, int $page = 1, int $perPage = 25): array
    {
        $params = [];
        $where = ['t.deleted_at IS NULL'];
        $where[] = TaskPolicy::scopeSql($p, 't.unidad_id', $params, 't');

        if (!empty($filters['status'])) {
            $statuses = is_array($filters['status']) ? $filters['status'] : explode(',', (string) $filters['status']);
            $statuses = array_filter(array_map('trim', $statuses));
            if (!empty($statuses)) {
                $placeholders = [];
                foreach ($statuses as $idx => $st) {
                    $ph = ":st_{$idx}";
                    $placeholders[] = $ph;
                    $params[$ph] = $st;
                }
                $where[] = 't.status IN (' . implode(',', $placeholders) . ')';
            }
        }

        if (!empty($filters['unidad_id'])) {
            $where[] = 't.unidad_id = :f_unit';
            $params[':f_unit'] = (int) $filters['unidad_id'];
        }

        if (!empty($filters['priority'])) {
            $where[] = 't.priority = :f_prio';
            $params[':f_prio'] = (string) $filters['priority'];
        }

        if (!empty($filters['assignee_employee_id'])) {
            $where[] = 't.assignee_employee_id = :f_emp';
            $params[':f_emp'] = (int) $filters['assignee_employee_id'];
        }

        if (!empty($filters['origin'])) {
            $where[] = 't.origin = :f_orig';
            $params[':f_orig'] = (string) $filters['origin'];
        }

        if (!empty($filters['created_from'])) {
            $where[] = 't.created_at >= :f_cfrom';
            $params[':f_cfrom'] = (string) $filters['created_from'] . ' 00:00:00';
        }

        if (!empty($filters['created_to'])) {
            $where[] = 't.created_at <= :f_cto';
            $params[':f_cto'] = (string) $filters['created_to'] . ' 23:59:59';
        }

        if (!empty($filters['only_mine']) && $p->employeeId !== null) {
            $where[] = 't.assignee_employee_id = :f_mine';
            $params[':f_mine'] = $p->employeeId;
        }

        if (!empty($filters['q'])) {
            $where[] = '(t.code LIKE :f_q OR t.title LIKE :f_q OR t.location_label LIKE :f_q)';
            $params[':f_q'] = '%' . trim((string) $filters['q']) . '%';
        }

        $whereClause = implode(' AND ', $where);

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM tec_tasks t WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT t.*, u.nombre as unidad_nombre,
                       e.nombre as assignee_nombre,
                       s.code as service_code, s.name_en as service_name
                FROM tec_tasks t
                LEFT JOIN tbl_unidades u ON u.id = t.unidad_id
                LEFT JOIN tec_employee e ON e.id = t.assignee_employee_id
                LEFT JOIN tec_services s ON s.id = t.service_id
                WHERE {$whereClause}
                ORDER BY t.id DESC
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

    public function get(Principal $p, int|string $idOrCode): array
    {
        $sql = 'SELECT t.*, u.nombre as unidad_nombre,
                       e.nombre as assignee_nombre,
                       s.code as service_code, s.name_en as service_name, s.name_es as service_name_es
                FROM tec_tasks t
                LEFT JOIN tbl_unidades u ON u.id = t.unidad_id
                LEFT JOIN tec_employee e ON e.id = t.assignee_employee_id
                LEFT JOIN tec_services s ON s.id = t.service_id
                WHERE ' . (is_numeric($idOrCode) ? 't.id = :id' : 't.code = :id') . ' AND t.deleted_at IS NULL';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $idOrCode]);
        $task = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$task) {
            throw new NotFoundException('Task not found.');
        }

        if (!TaskPolicy::canView($p, $task)) {
            throw new ForbiddenException('Access denied to this task.');
        }

        $taskId = (int) $task['id'];

        // History
        $histStmt = $this->pdo->prepare(
            'SELECT h.*, u.nickname as actor_nickname
             FROM tec_task_history h
             LEFT JOIN tec_usuarios u ON u.id = h.actor_user_id
             WHERE h.task_id = :id
             ORDER BY h.id ASC'
        );
        $histStmt->execute([':id' => $taskId]);
        $history = $histStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Comments
        $canSeeInternal = TaskPolicy::canSeeInternalComments($p);
        $commSql = 'SELECT c.*, u.nickname as author_nickname
                    FROM tec_task_comments c
                    LEFT JOIN tec_usuarios u ON u.id = c.author_user_id
                    WHERE c.task_id = :id AND c.deleted_at IS NULL '
                 . ($canSeeInternal ? '' : 'AND c.is_internal = 0 ')
                 . 'ORDER BY c.id ASC';
        $commStmt = $this->pdo->prepare($commSql);
        $commStmt->execute([':id' => $taskId]);
        $comments = $commStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Attachments
        $attStmt = $this->pdo->prepare(
            'SELECT a.*, u.nickname as uploaded_by_nickname
             FROM tec_task_attachments a
             LEFT JOIN tec_usuarios u ON u.id = a.uploaded_by_user_id
             WHERE a.task_id = :id AND a.deleted_at IS NULL
             ORDER BY a.id ASC'
        );
        $attStmt->execute([':id' => $taskId]);
        $attachments = $attStmt->fetchAll(\PDO::FETCH_ASSOC);

        // Reservations
        $rsvStmt = $this->pdo->prepare(
            'SELECT r.*, e.nombre as employee_nombre
             FROM tec_worker_reservations r
             LEFT JOIN tec_employee e ON e.id = r.employee_id
             WHERE r.task_id = :id AND r.status != "cancelled"
             ORDER BY r.work_date ASC'
        );
        $rsvStmt->execute([':id' => $taskId]);
        $reservations = $rsvStmt->fetchAll(\PDO::FETCH_ASSOC);

        $task['history'] = $history;
        $task['comments'] = $comments;
        $task['attachments'] = $attachments;
        $task['reservations'] = $reservations;
        $task['allowed_transitions'] = TaskStateMachine::allowed($p, $task);
        $task['can_update'] = TaskPolicy::canUpdate($p, $task);
        $task['can_assign'] = TaskPolicy::canAssign($p);
        $task['can_delete'] = TaskPolicy::canDelete($p);
        $task['can_see_internal'] = $canSeeInternal;

        return $task;
    }

    public function addComment(Principal $p, int $id, string $body, bool $internal): array
    {
        $task = $this->loadRaw($id);
        if (!TaskPolicy::canView($p, $task)) {
            throw new ForbiddenException('Cannot comment on this task.');
        }

        $body = trim($body);
        if (mb_strlen($body) < 1 || mb_strlen($body) > 2000) {
            throw new ValidationException('Comment must be between 1 and 2000 characters.');
        }

        if ($internal && !TaskPolicy::canSeeInternalComments($p)) {
            throw new ForbiddenException('Cannot create internal comments.');
        }

        $nowStr = Clock::nowUtc()->format('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO tec_task_comments (task_id, author_user_id, body, is_internal, channel, created_at)
                 VALUES (:task_id, :author, :body, :is_internal, "web", :created_at)'
            );
            $stmt->execute([
                ':task_id' => $id,
                ':author' => $p->userId,
                ':body' => $body,
                ':is_internal' => $internal ? 1 : 0,
                ':created_at' => $nowStr,
            ]);
            $commId = (int) $this->pdo->lastInsertId();

            $histStmt = $this->pdo->prepare(
                'INSERT INTO tec_task_history (task_id, event, from_status, to_status, changes, note, actor_kind, actor_user_id, channel, created_at)
                 VALUES (:task_id, "commented", :status, :status, :changes, :note, "user", :actor_user_id, "web", :created_at)'
            );
            $histStmt->execute([
                ':task_id' => $id,
                ':status' => $task['status'],
                ':changes' => json_encode(['comment_id' => $commId, 'is_internal' => $internal], JSON_UNESCAPED_SLASHES),
                ':note' => $internal ? 'Internal comment added' : 'Comment added',
                ':actor_user_id' => $p->userId,
                ':created_at' => $nowStr,
            ]);

            $this->pdo->commit();

            $authorNickname = null;
            if ($p->userId !== null) {
                $nickStmt = $this->pdo->prepare('SELECT nickname FROM tec_usuarios WHERE id = :id');
                $nickStmt->execute([':id' => $p->userId]);
                $authorNickname = $nickStmt->fetchColumn() ?: 'User #' . $p->userId;
            }

            return [
                'id' => $commId,
                'task_id' => $id,
                'author_user_id' => $p->userId,
                'author_nickname' => $authorNickname,
                'body' => $body,
                'is_internal' => $internal ? 1 : 0,
                'created_at' => $nowStr,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function softDelete(Principal $p, int $id): void
    {
        $task = $this->loadRaw($id);
        if (!TaskPolicy::canDelete($p)) {
            throw new ForbiddenException('Cannot delete tasks.');
        }

        $nowStr = Clock::nowUtc()->format('Y-m-d H:i:s');
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('UPDATE tec_tasks SET deleted_at = :now WHERE id = :id');
            $stmt->execute([':now' => $nowStr, ':id' => $id]);

            AuditLogger::log($p, 'task.deleted', 'task', $id, [
                'code' => $task['code'],
            ], null, $task['unidad_id'] !== null ? (int) $task['unidad_id'] : null);

            SyncEnqueuer::deleteTask($this->pdo, $id);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function dueSoon(\DateTimeImmutable $localDate, int $hours): array
    {
        $target = $localDate->modify("+{$hours} hours")->format('Y-m-d');
        $stmt = $this->pdo->prepare(
            'SELECT t.* FROM tec_tasks t
             WHERE t.deleted_at IS NULL
               AND t.status IN ("pending","assigned","in_progress")
               AND t.due_date IS NOT NULL
               AND t.due_date <= :target
               AND t.due_soon_notified_at IS NULL'
        );
        $stmt->execute([':target' => $target]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function countsForDashboard(Principal $p): array
    {
        $params = [];
        $scope = TaskPolicy::scopeSql($p, 't.unidad_id', $params, 't');
        $stmt = $this->pdo->prepare(
            "SELECT status, COUNT(*) as cnt
             FROM tec_tasks t
             WHERE t.deleted_at IS NULL AND {$scope}
             GROUP BY status"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        return [
            'pending' => (int) ($rows['pending'] ?? 0),
            'assigned' => (int) ($rows['assigned'] ?? 0),
            'in_progress' => (int) ($rows['in_progress'] ?? 0),
            'completed' => (int) ($rows['completed'] ?? 0),
            'cancelled' => (int) ($rows['cancelled'] ?? 0),
            'total_open' => (int) (($rows['pending'] ?? 0) + ($rows['assigned'] ?? 0) + ($rows['in_progress'] ?? 0)),
        ];
    }

    private function loadRaw(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tec_tasks WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([':id' => $id]);
        $task = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$task) {
            throw new NotFoundException('Task not found.');
        }
        return $task;
    }
}
