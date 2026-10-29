<?php

declare(strict_types=1);

namespace Pgs\Tasks;

use Pgs\Auth\Principal;
use Pgs\Core\AppLog;
use Pgs\Notifications\NotificationService;

final class TaskNotifier
{
    public static function created(\PDO $pdo, ?Principal $actor, int $taskId): void
    {
        self::notify($pdo, 'task.created', $actor, $taskId, 'New task', 'created');
    }

    public static function assigned(\PDO $pdo, ?Principal $actor, int $taskId, ?int $employeeId): void
    {
        if ($employeeId === null) {
            return;
        }
        self::notify($pdo, 'task.assigned', $actor, $taskId, 'Task assigned', 'assigned-' . $employeeId);
    }

    public static function statusChanged(\PDO $pdo, ?Principal $actor, int $taskId, string $from, string $to): void
    {
        self::notify($pdo, 'task.status_changed', $actor, $taskId, 'Task status changed', 'status-' . $to, "Status: {$from} -> {$to}");
    }

    private static function notify(\PDO $pdo, string $event, ?Principal $actor, int $taskId, string $heading, string $suffix, ?string $extraLine = null): void
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT t.id, t.code, t.title, t.description, t.priority, t.status, t.origin, t.unidad_id,
                        t.location_label, t.assignee_employee_id, t.required_date, t.due_date,
                        u.nombre AS unit_name, e.nombre AS employee_name
                 FROM tec_tasks t
                 LEFT JOIN tbl_unidades u ON u.id = t.unidad_id
                 LEFT JOIN tec_employee e ON e.id = t.assignee_employee_id
                 WHERE t.id = :id'
            );
            $stmt->execute([':id' => $taskId]);
            $t = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$t) {
                return;
            }

            $lines = ["{$t['code']}: {$t['title']}"];
            if (!empty($t['unit_name'])) {
                $lines[] = 'Property: ' . $t['unit_name'] . (!empty($t['location_label']) ? ' (' . $t['location_label'] . ')' : '');
            } elseif (!empty($t['location_label'])) {
                $lines[] = 'Location: ' . $t['location_label'];
            }
            $lines[] = 'Priority: ' . $t['priority'] . ' | Status: ' . $t['status'] . ' | Origin: ' . $t['origin'];
            if (!empty($t['employee_name'])) {
                $lines[] = 'Assigned to: ' . $t['employee_name'];
            }
            $date = $t['due_date'] ?: $t['required_date'];
            if (!empty($date)) {
                $lines[] = 'Date: ' . $date;
            }
            if ($extraLine !== null) {
                $lines[] = $extraLine;
            }
            if (!empty($t['description'])) {
                $lines[] = mb_substr((string) $t['description'], 0, 400);
            }

            $data = [
                'title' => "{$heading} {$t['code']}" . (!empty($t['unit_name']) ? " - {$t['unit_name']}" : ''),
                'body' => implode("\n", $lines),
                'link_url' => 'task_view.php?id=' . $taskId,
                'dedupe_suffix' => $suffix,
                'payload' => ['task_id' => $taskId, 'code' => $t['code']],
            ];

            $service = new NotificationService($pdo);
            $actorId = $actor !== null && $actor->userId !== null ? (int) $actor->userId : 0;

            $supers = self::ids($pdo, "SELECT id FROM tec_usuarios WHERE tipo = 'SuperAdministrador' AND habilitado IN ('yes','si')");
            $service->emit($pdo, $event, 'task', $taskId, self::asRecipients($supers), $data + ['always_email' => true]);

            $others = [];
            if (!empty($t['unidad_id'])) {
                $others = self::unitAdmins($pdo, (int) $t['unidad_id']);
            }
            if ($event === 'task.assigned' && !empty($t['assignee_employee_id'])) {
                $others = array_merge($others, self::ids(
                    $pdo,
                    'SELECT id FROM tec_usuarios WHERE employee_pk = ' . (int) $t['assignee_employee_id'] . " AND habilitado IN ('yes','si')"
                ));
            }
            $others = array_values(array_diff(array_unique($others), $supers, [$actorId]));
            if ($others !== []) {
                $service->emit($pdo, $event, 'task', $taskId, self::asRecipients($others), $data);
            }
        } catch (\Throwable $e) {
            AppLog::error('task-notify', $e, ['event' => $event, 'task_id' => $taskId]);
        }
    }

    /** @return list<int> */
    private static function unitAdmins(\PDO $pdo, int $unitId): array
    {
        $ids = [];
        $pivot = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tec_usuarios_has_tbl_unidades'")->fetchColumn();
        if ($pivot > 0) {
            $ids = self::ids(
                $pdo,
                "SELECT u.id FROM tec_usuarios u JOIN tec_usuarios_has_tbl_unidades h ON h.tec_usuarios_id = u.id
                 WHERE h.tbl_unidades_id = {$unitId} AND u.tipo = 'Administrador' AND u.habilitado IN ('yes','si')"
            );
        }

        return array_values(array_unique(array_merge($ids, self::ids(
            $pdo,
            "SELECT id FROM tec_usuarios WHERE tbl_unidad_id = {$unitId} AND tipo = 'Administrador' AND habilitado IN ('yes','si')"
        ))));
    }

    /** @return list<int> */
    private static function ids(\PDO $pdo, string $sql): array
    {
        return array_map('intval', $pdo->query($sql)->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * @param list<int> $ids
     * @return list<array{user_id:int}>
     */
    private static function asRecipients(array $ids): array
    {
        return array_map(static fn (int $id): array => ['user_id' => $id], $ids);
    }
}
