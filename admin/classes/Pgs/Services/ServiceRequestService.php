<?php

declare(strict_types=1);

namespace Pgs\Services;

use Pgs\Auth\Principal;
use Pgs\Auth\UnitScope;
use Pgs\Core\AuditLogger;
use Pgs\Core\Clock;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\ValidationException;
use Pgs\Tasks\TaskService;

final class ServiceRequestService
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    public function create(Principal $p, array $in): array
    {
        if (!$p->can('servicios.requests.create')) {
            throw new ForbiddenException('Cannot submit service requests.');
        }

        $unitId = (int) ($in['unidad_id'] ?? $in['unit_id'] ?? 0);
        if ($unitId <= 0) {
            throw new ValidationException(['unidad_id' => 'Property is required.']);
        }
        UnitScope::assertUnit($p, $unitId);

        $serviceId = (int) ($in['service_id'] ?? 0);
        if ($serviceId <= 0) {
            throw new ValidationException(['service_id' => 'Service is required.']);
        }

        // Verify service exists, active and available for unit
        $stmt = $this->pdo->prepare('SELECT * FROM tec_services WHERE id = :id AND is_active = 1 AND deleted_at IS NULL');
        $stmt->execute([':id' => $serviceId]);
        $svc = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$svc) {
            throw new NotFoundException('Service not found or is inactive.');
        }

        // Check if service restricted to specific properties
        $unitAssocCount = (int) $this->pdo->query("SELECT COUNT(*) FROM tec_service_units WHERE service_id = {$serviceId}")->fetchColumn();
        if ($unitAssocCount > 0) {
            $assocStmt = $this->pdo->prepare('SELECT COUNT(*) FROM tec_service_units WHERE service_id = :sid AND unidad_id = :uid');
            $assocStmt->execute([':sid' => $serviceId, ':uid' => $unitId]);
            if ((int) $assocStmt->fetchColumn() === 0) {
                throw new ValidationException(['service_id' => 'This service is not offered at the selected property.']);
            }
        }

        $now = Clock::nowUtc();
        $todayStr = $now->format('Y-m-d');
        $maxDate = $now->modify('+365 days')->format('Y-m-d');

        $reqDate = !empty($in['required_date']) ? (string) $in['required_date'] : null;
        if ($reqDate !== null) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $reqDate) || $reqDate < $todayStr || $reqDate > $maxDate) {
                throw new ValidationException(['required_date' => 'Required date must be between today and next year.']);
            }
        }

        $warning = null;
        $leadTime = (int) ($svc['lead_time_days'] ?? 0);
        if ($leadTime > 0 && $reqDate !== null) {
            $minLeadDate = $now->modify("+{$leadTime} days")->format('Y-m-d');
            if ($reqDate < $minLeadDate) {
                $warning = sprintf(
                    'This service typically requires %d day(s) lead time. The request will proceed, but completion by %s may not be guaranteed.',
                    $leadTime,
                    $reqDate
                );
            }
        }

        $notes = trim((string) ($in['notes'] ?? ''));
        $locationLabel = isset($in['location_label']) ? trim((string) $in['location_label']) : null;
        $priority = (string) ($in['priority'] ?? 'normal');
        if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            $priority = 'normal';
        }

        $lang = $p->lang === 'es' ? 'es' : 'en';
        $title = $lang === 'es' && !empty($svc['name_es']) ? (string) $svc['name_es'] : (string) $svc['name_en'];

        // Delegate to TaskService
        $taskService = new TaskService($this->pdo);
        $task = $taskService->create($p, [
            'origin' => 'service_request',
            'unidad_id' => $unitId,
            'location_label' => $locationLabel,
            'service_id' => $serviceId,
            'title' => $title,
            'description' => $notes,
            'priority' => $priority,
            'required_date' => $reqDate,
            'requested_by_user_id' => $p->userId,
            'requested_verification' => 'session',
        ]);

        AuditLogger::log($p, 'service_request.created', 'task', (int) $task['id'], null, [
            'code' => $task['code'],
            'service_id' => $serviceId,
            'unidad_id' => $unitId,
        ], $unitId);

        return [
            'task_code' => $task['code'],
            'task_id' => (int) $task['id'],
            'warning' => $warning,
        ];
    }
}
