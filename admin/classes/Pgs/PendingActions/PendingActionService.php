<?php

declare(strict_types=1);

namespace Pgs\PendingActions;

use Pgs\Auth\Principal;
use Pgs\Availability\AbsenceService;
use Pgs\Availability\ReservationService;
use Pgs\Core\Clock;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\ConflictException;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Json;
use Pgs\Core\RequestContext;
use Pgs\Core\Ulid;
use Pgs\Expenses\ExpenseService;
use Pgs\Reports\ReportDeliveryService;
use Pgs\Services\ServiceRequestService;
use Pgs\Tasks\TaskService;

final class PendingActionService
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    /**
     * Creates a proposed action requiring user confirmation.
     *
     * @return array{action_id: string, summary: string, expires_at: string}
     */
    public function create(
        Principal $p,
        string $channel,
        string $type,
        array $payload,
        string $summary,
        ?int $conversacionId = null,
        ?int $callSessionId = null,
        int $ttlSeconds = 600
    ): array {
        $publicId = Ulid::generate();
        $now = Clock::nowUtc();
        $expiresAt = $now->modify("+{$ttlSeconds} seconds")->format('Y-m-d H:i:s');
        $nowSql = $now->format('Y-m-d H:i:s');
        $requestId = RequestContext::id();

        $stmt = $this->pdo->prepare(
            "INSERT INTO tec_pending_actions (
                public_id, channel, action_type, principal_user_id, call_session_id,
                ia_conversacion_id, request_id, payload, summary, status,
                expires_at, created_at
             ) VALUES (
                :pid, :chan, :atype, :uid, :sid,
                :cid, :req_id, :payload, :summary, 'pending',
                :expires, :now
             )"
        );

        $stmt->execute([
            ':pid' => $publicId,
            ':chan' => $channel,
            ':atype' => $type,
            ':uid' => $p->id(),
            ':sid' => $callSessionId,
            ':cid' => $conversacionId,
            ':req_id' => $requestId,
            ':payload' => Json::encode($payload),
            ':summary' => $summary,
            ':expires' => $expiresAt,
            ':now' => $nowSql,
        ]);

        return [
            'action_id' => $publicId,
            'summary' => $summary,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Confirms and executes a pending action.
     */
    public function confirm(
        Principal $p,
        string $publicId,
        ?int $conversacionId = null,
        ?int $callSessionId = null
    ): array {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM tec_pending_actions WHERE public_id = :pid FOR UPDATE"
            );
            $stmt->execute([':pid' => $publicId]);
            $action = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$action) {
                throw new NotFoundException("Confirmation token not found");
            }

            if ($action['status'] !== 'pending') {
                throw new ConflictException("Action is already {$action['status']}");
            }

            $now = Clock::nowUtc();
            if ($action['expires_at'] !== null && strtotime((string) $action['expires_at']) <= $now->getTimestamp()) {
                $this->pdo->prepare("UPDATE tec_pending_actions SET status = 'expired' WHERE id = :id")
                          ->execute([':id' => $action['id']]);
                throw new ConflictException("Confirmation token has expired");
            }

            // User / session match verification
            if ($action['principal_user_id'] !== null && $p->id() !== null && (int) $action['principal_user_id'] !== $p->id()) {
                throw new ForbiddenException("Pending action belongs to a different user");
            }

            if ($callSessionId !== null && $action['call_session_id'] !== null && (int) $action['call_session_id'] !== $callSessionId) {
                throw new ForbiddenException("Pending action belongs to a different call session");
            }

            if ($conversacionId !== null && $action['ia_conversacion_id'] !== null && (int) $action['ia_conversacion_id'] !== $conversacionId) {
                throw new ForbiddenException("Pending action belongs to a different conversation");
            }

            $currentReqId = RequestContext::id();
            if (!empty($action['request_id']) && $action['request_id'] === $currentReqId) {
                throw new ConflictException("Confirmation must be issued in a separate request");
            }

            $payload = json_decode((string) $action['payload'], true) ?? [];
            $actionType = (string) $action['action_type'];
            $result = null;
            $resultRef = null;

            // Dispatch execution
            switch ($actionType) {
                case 'task.create':
                    $taskSvc = new TaskService($this->pdo);
                    $result = $taskSvc->create($p, $payload);
                    if (!empty($payload['attachment_id']) && !empty($result['id'])) {
                        (new \Pgs\Tasks\TaskAttachmentService($this->pdo))->copyFromIaAttachment($p, (int) $result['id'], (string) $payload['attachment_id']);
                    }
                    $resultRef = (string) ($result['code'] ?? $result['id'] ?? '');
                    break;

                case 'task.status':
                    $taskSvc = new TaskService($this->pdo);
                    $result = $taskSvc->changeStatus(
                        $p,
                        (int) $payload['task_id'],
                        (string) $payload['status'],
                        isset($payload['note']) ? (string) $payload['note'] : null,
                        null
                    );
                    $resultRef = (string) ($result['code'] ?? $payload['task_id']);
                    break;

                case 'task.assign':
                    $taskSvc = new TaskService($this->pdo);
                    $result = $taskSvc->assign(
                        $p,
                        (int) $payload['task_id'],
                        (int) $payload['employee_id'],
                        isset($payload['note']) ? (string) $payload['note'] : null
                    );
                    $resultRef = (string) ($result['code'] ?? $payload['task_id']);
                    break;

                case 'service.request':
                    $reqSvc = new ServiceRequestService($this->pdo);
                    $result = $reqSvc->create($p, $payload);
                    $resultRef = (string) ($result['task_code'] ?? $result['code'] ?? '');
                    break;

                case 'reservation.create':
                    $rsvSvc = new ReservationService($this->pdo);
                    $result = $rsvSvc->createBatch(
                        $p,
                        (int) $payload['employee_id'],
                        (int) $payload['unit_id'],
                        (array) $payload['dates'],
                        (string) ($payload['day_part'] ?? 'full'),
                        isset($payload['notes']) ? (string) $payload['notes'] : null
                    );
                    $resultRef = Json::encode($result['codes'] ?? []);
                    break;

                case 'absence.request':
                    $absSvc = new AbsenceService($this->pdo);
                    $result = $absSvc->request(
                        $p,
                        (int) $payload['employee_id'],
                        (string) $payload['date_from'],
                        (string) $payload['date_to'],
                        (string) $payload['reason_type'],
                        isset($payload['reason_text']) ? (string) $payload['reason_text'] : null
                    );
                    $resultRef = (string) ($result['id'] ?? '');
                    break;

                case 'expense.create':
                    $expSvc = new ExpenseService($this->pdo);
                    $expChannel = ['voice' => 'phone', 'assistant' => 'assistant_text'][(string) $action['channel']] ?? 'web';
                    $result = $expSvc->create(
                        $p,
                        $payload,
                        $expChannel,
                        (int) $action['id'],
                        !empty($payload['attachment_id']) ? (string) $payload['attachment_id'] : null
                    );
                    $resultRef = (string) ($result['code'] ?? '');
                    break;

                case 'report.send':
                    $repSvc = new ReportDeliveryService($this->pdo);
                    $result = $repSvc->deliver($p, $payload);
                    $resultRef = 'sent';
                    break;

                case 'invoice.send':
                    $invSvc = new \Pgs\Invoices\InvoiceService($this->pdo);
                    $inv = $invSvc->get($p, (int) $payload['invoice_id']);
                    $channel = (string) ($payload['channel'] ?? 'email');
                    $destination = (string) ($payload['destination'] ?? '');
                    if (empty($inv['pdf_path'])) {
                        throw new \Pgs\Core\Exceptions\ValidationException('This invoice has no PDF file to send.');
                    }
                    $notifSvc = new \Pgs\Notifications\NotificationService($this->pdo);
                    $notifSvc->emit($this->pdo, 'document.delivery', 'invoice', (int) $inv['id'], [
                        ['user_id' => $p->id()],
                    ], [
                        'title' => "Invoice {$inv['invoice_number']}",
                        'body' => "Your requested invoice {$inv['invoice_number']} is attached.",
                        'attachment_path' => \Pgs\Core\PrivateStorage::abs((string) $inv['pdf_path']),
                        'lang' => $p->language() ?? 'en',
                        'force_channel' => $channel,
                        'payload' => [
                            'invoice_id' => $inv['id'],
                            'invoice_number' => $inv['invoice_number'],
                            'channel' => $channel,
                            'destination' => $destination,
                            'filename' => $inv['pdf_original_name'] ?: ($inv['invoice_number'] . '.pdf'),
                        ],
                    ]);
                    \Pgs\Core\AuditLogger::log($p, 'invoice.delivered', 'invoice', (int) $inv['id'], null, ['channel' => $channel], (int) $inv['unidad_id']);
                    $result = ['queued' => true, 'destination_masked' => $payload['destination_masked'] ?? $destination];
                    $resultRef = 'sent';
                    break;

                case 'invoice.create_from_attachment':
                    $invSvc = new \Pgs\Invoices\InvoiceService($this->pdo);
                    $result = $invSvc->createFromAttachment($p, $payload);
                    $resultRef = (string) ($result['id'] ?? $result['number'] ?? '');
                    break;

                default:
                    throw new \RuntimeException("Unknown action type: {$actionType}");
            }

            // Mark confirmed
            $this->pdo->prepare(
                "UPDATE tec_pending_actions
                 SET status = 'confirmed',
                     result_ref = :ref,
                     decided_at = NOW()
                 WHERE id = :id"
            )->execute([
                ':ref' => $resultRef,
                ':id' => $action['id'],
            ]);

            $this->pdo->commit();
            return [
                'ok' => true,
                'result' => $result,
                'result_ref' => $resultRef,
                'action_id' => $publicId,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function cancel(Principal $p, string $publicId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE tec_pending_actions
             SET status = 'cancelled', decided_at = NOW()
             WHERE public_id = :pid AND status = 'pending'"
        );
        $stmt->execute([':pid' => $publicId]);
    }

    public function expireStale(): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE tec_pending_actions
             SET status = 'expired'
             WHERE status = 'pending'
               AND expires_at IS NOT NULL
               AND expires_at < NOW()"
        );
        $stmt->execute();
        return $stmt->rowCount();
    }
}
