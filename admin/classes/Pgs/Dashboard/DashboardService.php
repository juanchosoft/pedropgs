<?php

declare(strict_types=1);

namespace Pgs\Dashboard;

use Pgs\Auth\Principal;
use Pgs\Core\Db;
use Pgs\Tasks\TaskService;

final class DashboardService
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    /**
     * Aggregates key metrics for the main dashboard cards.
     *
     * @return array<string, mixed>
     */
    public function summary(Principal $p): array
    {
        $taskSvc = new TaskService($this->pdo);
        $taskCounts = $taskSvc->countsForDashboard($p);

        // 1. My Open Tasks
        $myOpenTasks = $taskCounts['total_open'];
        if ($p->isEmployee() && $p->employeeId) {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM tec_tasks
                 WHERE deleted_at IS NULL
                   AND assignee_employee_id = :emp_id
                   AND status IN ('pending', 'assigned', 'in_progress')"
            );
            $stmt->execute([':emp_id' => $p->employeeId]);
            $myOpenTasks = (int) $stmt->fetchColumn();
        }

        // 2. Pending Requests
        $pendingRequests = 0;
        if ($p->canAny(['tareas.tasks.view', 'tareas.tasks.view_all'])) {
            $scopeSql = '';
            if (!$p->isSystemAdmin()) {
                $uids = $p->unitIds;
                if (!empty($uids)) {
                    $inPlace = implode(',', array_map('intval', $uids));
                    $scopeSql = " AND (unidad_id IN ({$inPlace}) OR unidad_id IS NULL)";
                } else {
                    $scopeSql = " AND unidad_id IS NULL";
                }
            }
            $reqStmt = $this->pdo->query(
                "SELECT COUNT(*) FROM tec_tasks
                 WHERE deleted_at IS NULL
                   AND origin = 'service_request'
                   AND status = 'pending' {$scopeSql}"
            );
            $pendingRequests = (int) $reqStmt->fetchColumn();
        }

        // 3. Upcoming Reservations (7 days)
        $upcomingReservations = 0;
        if ($p->canAny(['disponibilidad.reservations.create', 'disponibilidad.reservations.manage'])) {
            $scopeSql = '';
            if (!$p->isSystemAdmin()) {
                $uids = $p->unitIds;
                if (!empty($uids)) {
                    $inPlace = implode(',', array_map('intval', $uids));
                    $scopeSql = " AND unidad_id IN ({$inPlace})";
                } else {
                    $scopeSql = " AND 1=0";
                }
            }
            $rsvStmt = $this->pdo->query(
                "SELECT COUNT(*) FROM tec_worker_reservations
                 WHERE status = 'confirmed'
                   AND work_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) {$scopeSql}"
            );
            $upcomingReservations = (int) $rsvStmt->fetchColumn();
        }

        // 4. Overdue Invoices
        $overdueInvoices = 0;
        if ($p->can('facturas.invoices.manage')) {
            $scopeSql = '';
            if (!$p->isSystemAdmin()) {
                $uids = $p->unitIds;
                if (!empty($uids)) {
                    $inPlace = implode(',', array_map('intval', $uids));
                    $scopeSql = " AND unidad_id IN ({$inPlace})";
                } else {
                    $scopeSql = " AND 1=0";
                }
            }
            $invStmt = $this->pdo->query(
                "SELECT COUNT(*) FROM tec_invoices
                 WHERE payment_status = 'overdue' {$scopeSql}"
            );
            $overdueInvoices = (int) $invStmt->fetchColumn();
        }

        return [
            'my_open_tasks' => $myOpenTasks,
            'pending_requests' => $pendingRequests,
            'upcoming_reservations' => $upcomingReservations,
            'overdue_invoices' => $overdueInvoices,
            'task_counts' => $taskCounts,
            'can_tasks' => $p->canAny(['tareas.tasks.view', 'tareas.tasks.view_all']),
            'can_reservations' => $p->canAny(['disponibilidad.reservations.create', 'disponibilidad.reservations.manage']),
            'can_invoices' => $p->can('facturas.invoices.manage'),
        ];
    }
}
