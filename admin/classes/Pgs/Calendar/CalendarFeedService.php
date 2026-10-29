<?php

declare(strict_types=1);

namespace Pgs\Calendar;

use Pgs\Auth\Principal;
use Pgs\Core\Db;

final class CalendarFeedService
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    /**
     * Aggregates tasks, reservations, and absences into a unified calendar event stream.
     * Colores: tarea #E10600, reserva #0B0F19, ausencia #64748b.
     *
     * @return list<array<string, mixed>>
     */
    public function events(Principal $p, string $from, string $to): array
    {
        $p->requireAny([
            'tareas.tasks.view',
            'tareas.tasks.view_all',
            'disponibilidad.schedule.view',
            'disponibilidad.reservations.create',
        ]);

        $events = [];

        // 1. Tasks with a due date (falls back to the required date)
        if ($p->canAny(['tareas.tasks.view', 'tareas.tasks.view_all'])) {
            $scopeSql = '';
            $params = [':from' => $from, ':to' => $to];
            if (!$p->isSystemAdmin()) {
                $uids = $p->unitIds;
                if (!empty($uids)) {
                    $inPlace = implode(',', array_map('intval', $uids));
                    $scopeSql = " AND (t.unidad_id IN ({$inPlace}) OR t.unidad_id IS NULL)";
                } else {
                    $scopeSql = " AND t.unidad_id IS NULL";
                }
            }
            $taskStmt = $this->pdo->prepare(
                "SELECT t.id, t.code, t.title, COALESCE(t.due_date, t.required_date) AS due_date, t.status, t.priority, u.nombre as unit_name
                 FROM tec_tasks t
                 LEFT JOIN tbl_unidades u ON u.id = t.unidad_id
                 WHERE t.deleted_at IS NULL
                   AND COALESCE(t.due_date, t.required_date) IS NOT NULL
                   AND COALESCE(t.due_date, t.required_date) BETWEEN :from AND :to
                   {$scopeSql}
                 ORDER BY COALESCE(t.due_date, t.required_date) ASC"
            );
            $taskStmt->execute($params);
            while ($t = $taskStmt->fetch(\PDO::FETCH_ASSOC)) {
                $unitPrefix = $t['unit_name'] ? " [{$t['unit_name']}]" : '';
                $events[] = [
                    'id' => 'task_' . $t['id'],
                    'title' => "[Task] {$t['code']}{$unitPrefix}: {$t['title']}",
                    'start' => $t['due_date'],
                    'end' => $t['due_date'],
                    'color' => '#E10600',
                    'textColor' => '#ffffff',
                    'url' => 'task_view.php?id=' . $t['id'],
                    'extendedProps' => [
                        'type' => 'task',
                        'code' => $t['code'],
                        'status' => $t['status'],
                        'priority' => $t['priority'],
                    ],
                ];
            }
        }

        // 2. Reservations
        if ($p->canAny(['disponibilidad.reservations.create', 'disponibilidad.reservations.manage'])) {
            $scopeSql = '';
            $params = [':from' => $from, ':to' => $to];
            if (!$p->isSystemAdmin()) {
                $uids = $p->unitIds;
                if (!empty($uids)) {
                    $inPlace = implode(',', array_map('intval', $uids));
                    $scopeSql = " AND r.unidad_id IN ({$inPlace})";
                } else {
                    $scopeSql = " AND 1=0";
                }
            }
            $rsvStmt = $this->pdo->prepare(
                "SELECT r.id, r.code, r.work_date, r.status, u.nombre as unit_name,
                        e.nombre as worker_name
                 FROM tec_worker_reservations r
                 LEFT JOIN tbl_unidades u ON u.id = r.unidad_id
                 LEFT JOIN tec_employee e ON e.id = r.employee_id
                 WHERE r.status != 'cancelled'
                   AND r.work_date BETWEEN :from AND :to
                   {$scopeSql}
                 ORDER BY r.work_date ASC"
            );
            $rsvStmt->execute($params);
            while ($r = $rsvStmt->fetch(\PDO::FETCH_ASSOC)) {
                $unitPrefix = $r['unit_name'] ? " ({$r['unit_name']})" : '';
                $workerPrefix = $r['worker_name'] ? " - {$r['worker_name']}" : '';
                $events[] = [
                    'id' => 'rsv_' . $r['id'],
                    'title' => "[Reservation] {$r['code']}{$unitPrefix}{$workerPrefix}",
                    'start' => $r['work_date'],
                    'end' => $r['work_date'],
                    'color' => '#0B0F19',
                    'textColor' => '#ffffff',
                    'url' => 'reservations.php',
                    'extendedProps' => [
                        'type' => 'reservation',
                        'code' => $r['code'],
                        'status' => $r['status'],
                    ],
                ];
            }
        }

        // 3. Approved Absences / Time Off
        if ($p->canAny(['disponibilidad.schedule.view', 'disponibilidad.absences.approve'])) {
            $params = [':from' => $from, ':to' => $to];
            $absStmt = $this->pdo->prepare(
                "SELECT a.id, a.reason_type, a.date_from, a.date_to, a.status,
                        e.nombre as employee_name
                 FROM tec_worker_absences a
                 JOIN tec_employee e ON e.id = a.employee_id
                 WHERE a.status = 'approved'
                   AND a.date_from <= :to
                   AND a.date_to >= :from
                 ORDER BY a.date_from ASC"
            );
            $absStmt->execute($params);
            while ($a = $absStmt->fetch(\PDO::FETCH_ASSOC)) {
                $endDate = (new \DateTimeImmutable($a['date_to']))->modify('+1 day')->format('Y-m-d');
                $events[] = [
                    'id' => 'abs_' . $a['id'],
                    'title' => "[Time Off] {$a['employee_name']} (" . ucfirst($a['reason_type']) . ")",
                    'start' => $a['date_from'],
                    'end' => $endDate,
                    'color' => '#64748b',
                    'textColor' => '#ffffff',
                    'url' => 'absences.php',
                    'extendedProps' => [
                        'type' => 'absence',
                        'leave_type' => $a['reason_type'],
                        'status' => $a['status'],
                    ],
                ];
            }
        }

        return $events;
    }
}
