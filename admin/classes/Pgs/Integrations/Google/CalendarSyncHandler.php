<?php

declare(strict_types=1);

namespace Pgs\Integrations\Google;

use Composer\CaBundle\CaBundle;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Pgs\Core\Config;
use Pgs\Core\Db;
use Pgs\Core\Json;

final class CalendarSyncHandler
{
    private ServiceAccountAuth $auth;
    private ClientInterface $http;
    private string $calendarId;
    private string $eventPrefix;
    private string $timezone;
    private \PDO $pdo;

    public function __construct(
        ?ServiceAccountAuth $auth = null,
        ?ClientInterface $http = null,
        ?\PDO $pdo = null
    ) {
        $this->auth = $auth ?? new ServiceAccountAuth();
        $this->calendarId = (string) Config::get('google', 'calendar_id', '');
        $this->eventPrefix = (string) Config::get('google', 'event_prefix', 'pgsd');
        $this->timezone = (string) Config::get('business', 'business_timezone', 'America/New_York');
        $this->pdo = $pdo ?? Db::pdo();

        $this->http = $http ?? new Client([
            'base_uri' => 'https://www.googleapis.com/calendar/v3/calendars/',
            'timeout' => 15.0,
            'verify' => CaBundle::getSystemCaRootBundlePath(),
        ]);
    }

    /**
     * Tests Google Calendar connection by listing 1 event.
     */
    public function testConnection(): array
    {
        if ($this->calendarId === '') {
            return ['ok' => false, 'message' => 'calendar_id is not configured'];
        }

        if (!$this->auth->isConfigured()) {
            return ['ok' => false, 'message' => 'Google Service Account credentials file is not configured'];
        }

        try {
            $token = $this->auth->getAccessToken();
            $calUrl = rawurlencode($this->calendarId) . '/events?maxResults=1';
            $res = $this->http->request('GET', $calUrl, [
                'headers' => ['Authorization' => "Bearer {$token}"],
            ]);

            $data = Json::decode((string) $res->getBody());
            return [
                'ok' => true,
                'message' => 'Google Calendar connection successful',
                'summary' => $data['summary'] ?? 'Calendar',
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Google Calendar test error: ' . $e->getMessage()];
        }
    }

    /**
     * Syncs a task event to Google Calendar.
     */
    public function syncTask(int $taskId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.*, u.nombre as unit_name, emp.nombre as assignee_name
             FROM tec_tasks t
             LEFT JOIN tbl_unidades u ON u.id = t.unidad_id
             LEFT JOIN tec_employee emp ON emp.id = t.assignee_employee_id
             WHERE t.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $taskId]);
        $task = $stmt->fetch(\PDO::FETCH_ASSOC);

        $eventId = strtolower($this->eventPrefix . 'task' . $taskId);

        if (!$task || in_array($task['status'], ['cancelled'], true)) {
            $this->deleteEvent($eventId, 'task', $taskId);
            return;
        }

        $date = !empty($task['due_date']) ? (string) $task['due_date'] : (string) ($task['required_date'] ?? '');
        if ($date === '') {
            $this->deleteEvent($eventId, 'task', $taskId);
            return;
        }

        $summary = "[{$task['code']}] {$task['title']} - {$task['unit_name']}";
        $description = "Status: {$task['status']}\nPriority: {$task['priority']}\nAssignee: {$task['assignee_name']}\nLocation: {$task['location_label']}";

        // Priority colors: urgent = 11 (red), high = 5 (yellow), normal = 7 (cyan)
        $colorId = match ($task['priority']) {
            'urgent' => '11',
            'high' => '5',
            default => '7',
        };

        $eventPayload = [
            'id' => $eventId,
            'summary' => $summary,
            'description' => $description,
            'colorId' => $colorId,
            'start' => ['date' => $date],
            'end' => ['date' => date('Y-m-d', strtotime($date . ' +1 day'))],
            'status' => 'confirmed',
        ];

        $this->upsertEvent($eventId, $eventPayload, 'task', $taskId);
    }

    /**
     * Syncs a worker reservation to Google Calendar.
     */
    public function syncReservation(int $rsvId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.*, emp.nombre as worker_name, u.nombre as unit_name
             FROM tec_worker_reservations r
             LEFT JOIN tec_employee emp ON emp.id = r.employee_id
             LEFT JOIN tbl_unidades u ON u.id = r.unidad_id
             WHERE r.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $rsvId]);
        $rsv = $stmt->fetch(\PDO::FETCH_ASSOC);

        $eventId = strtolower($this->eventPrefix . 'rsv' . $rsvId);

        if (!$rsv || (string) ($rsv['status'] ?? '') === 'cancelled') {
            $this->deleteEvent($eventId, 'reservation', $rsvId);
            return;
        }

        $date = (string) $rsv['work_date'];
        $shift = (string) ($rsv['day_part'] ?? 'full');
        $summary = "{$rsv['worker_name']} @ {$rsv['unit_name']}";

        $eventPayload = [
            'id' => $eventId,
            'summary' => $summary,
            'description' => "Reservation #{$rsvId}\nNotes: " . ($rsv['notes'] ?? 'None'),
            'colorId' => '9', // Blueberry / Blue
            'status' => 'confirmed',
        ];

        if ($shift === 'am') {
            $eventPayload['start'] = ['dateTime' => "{$date}T08:00:00", 'timeZone' => $this->timezone];
            $eventPayload['end'] = ['dateTime' => "{$date}T12:00:00", 'timeZone' => $this->timezone];
        } elseif ($shift === 'pm') {
            $eventPayload['start'] = ['dateTime' => "{$date}T13:00:00", 'timeZone' => $this->timezone];
            $eventPayload['end'] = ['dateTime' => "{$date}T17:00:00", 'timeZone' => $this->timezone];
        } else {
            // Full day
            $eventPayload['start'] = ['date' => $date];
            $eventPayload['end'] = ['date' => date('Y-m-d', strtotime($date . ' +1 day'))];
        }

        $this->upsertEvent($eventId, $eventPayload, 'reservation', $rsvId);
    }

    /**
     * Syncs an employee absence to Google Calendar.
     */
    public function syncAbsence(int $absId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, emp.nombre as worker_name
             FROM tec_worker_absences a
             LEFT JOIN tec_employee emp ON emp.id = a.employee_id
             WHERE a.id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $absId]);
        $abs = $stmt->fetch(\PDO::FETCH_ASSOC);

        $eventId = strtolower($this->eventPrefix . 'abs' . $absId);

        if (!$abs || (string) ($abs['status'] ?? '') !== 'approved') {
            $this->deleteEvent($eventId, 'absence', $absId);
            return;
        }

        $summary = "Absence - {$abs['worker_name']}";
        $startDate = (string) $abs['date_from'];
        $endDate = (string) $abs['date_to'];

        $eventPayload = [
            'id' => $eventId,
            'summary' => $summary,
            'description' => "Motivo: {$abs['reason_type']}\nDetalle: " . ($abs['reason_text'] ?? ''),
            'colorId' => '8', // Graphite / Grey
            'start' => ['date' => $startDate],
            'end' => ['date' => date('Y-m-d', strtotime($endDate . ' +1 day'))],
            'status' => 'confirmed',
        ];

        $this->upsertEvent($eventId, $eventPayload, 'absence', $absId);
    }

    private function upsertEvent(string $eventId, array $payload, string $entityType, int $entityId): void
    {
        if ($this->calendarId === '' || !$this->auth->isConfigured()) {
            $this->recordSyncLink($entityType, $entityId, $eventId, $payload);
            return;
        }

        $token = $this->auth->getAccessToken();
        $calUrl = rawurlencode($this->calendarId) . '/events';

        try {
            // Try creating new event
            $this->http->request('POST', $calUrl, [
                'headers' => ['Authorization' => "Bearer {$token}"],
                'json' => $payload,
            ]);
        } catch (ClientException $e) {
            if ($e->getResponse() && $e->getResponse()->getStatusCode() === 409) {
                // Event ID exists, patch it
                $patchUrl = rawurlencode($this->calendarId) . '/events/' . rawurlencode($eventId);
                $this->http->request('PATCH', $patchUrl, [
                    'headers' => ['Authorization' => "Bearer {$token}"],
                    'json' => $payload,
                ]);
            } else {
                throw $e;
            }
        }

        $this->recordSyncLink($entityType, $entityId, $eventId, $payload);
    }

    private function deleteEvent(string $eventId, string $entityType, int $entityId): void
    {
        if ($this->calendarId !== '' && $this->auth->isConfigured()) {
            try {
                $token = $this->auth->getAccessToken();
                $delUrl = rawurlencode($this->calendarId) . '/events/' . rawurlencode($eventId);
                $this->http->request('DELETE', $delUrl, [
                    'headers' => ['Authorization' => "Bearer {$token}"],
                ]);
            } catch (ClientException $e) {
                // 404 or 410 is treated as successfully deleted
                $status = $e->getResponse() ? $e->getResponse()->getStatusCode() : 0;
                if ($status !== 404 && $status !== 410) {
                    throw $e;
                }
            }
        }

        $stmt = $this->pdo->prepare(
            "DELETE FROM tec_sync_links 
             WHERE target = 'calendar' AND entity_type = :et AND entity_id = :eid"
        );
        $stmt->execute([':et' => $entityType, ':eid' => $entityId]);
    }

    private function recordSyncLink(string $entityType, int $entityId, string $eventId, array $payload): void
    {
        $hash = hash('sha256', Json::encode($payload));

        $ins = $this->pdo->prepare(
            "INSERT INTO tec_sync_links (
                target, entity_type, entity_id, external_id, last_hash, synced_at
            ) VALUES (
                'calendar', :et, :eid, :rem, :h, NOW()
            ) ON DUPLICATE KEY UPDATE 
                external_id = VALUES(external_id), 
                last_hash = VALUES(last_hash), 
                synced_at = VALUES(synced_at)"
        );
        $ins->execute([
            ':et' => $entityType,
            ':eid' => $entityId,
            ':rem' => $eventId,
            ':h' => $hash,
        ]);
    }

    public function buildEventId(string $type, int $id): string
    {
        $short = match ($type) {
            'task' => 'task',
            'reservation', 'rsv' => 'rsv',
            'absence', 'abs' => 'abs',
            default => 'evt',
        };
        return strtolower($this->eventPrefix . $short . $id);
    }

    public function mapShiftTimes(string $date, string $shift): array
    {
        return match (strtolower($shift)) {
            'am' => ['start' => "{$date}T08:00:00", 'end' => "{$date}T12:00:00"],
            'pm' => ['start' => "{$date}T13:00:00", 'end' => "{$date}T17:00:00"],
            default => ['start' => "{$date}T08:00:00", 'end' => "{$date}T17:00:00"],
        };
    }
}
