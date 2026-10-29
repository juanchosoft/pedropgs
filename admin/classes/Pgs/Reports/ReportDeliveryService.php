<?php

declare(strict_types=1);

namespace Pgs\Reports;

use Pgs\Auth\Principal;
use Pgs\Auth\UnitScope;
use Pgs\Core\AuditLogger;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\ValidationException;
use Pgs\Core\Phone;
use Pgs\Core\PrivateStorage;
use Pgs\Notifications\NotificationService;

final class ReportDeliveryService
{
    private \PDO $pdo;
    private NotificationService $notifications;

    public function __construct(?\PDO $pdo = null, ?NotificationService $notifications = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
        $this->notifications = $notifications ?? new NotificationService($this->pdo);
    }

    /**
     * Generates a report PDF and saves it to private storage.
     *
     * @return array{path:string, filename:string, pages:int, size:int}
     */
    public function generate(Principal $p, string $key, int $unitId, string $from, string $to): array
    {
        if (!isset(ReportCatalog::REPORTS[$key])) {
            throw new NotFoundException("Report '{$key}' not found in catalog");
        }

        $cfg = ReportCatalog::REPORTS[$key];

        // Safeguard: Check deny_unit_admin
        if (!empty($cfg['deny_unit_admin']) && $p->isUnitAdmin()) {
            throw new ForbiddenException("Access denied: Property administrators cannot generate report '{$key}'");
        }

        // Check permission
        $perms = (array) $cfg['perm'];
        if (!$p->hasAnyPermission($perms)) {
            throw new ForbiddenException("Missing permission for report '{$key}'");
        }

        // Scope check for unit
        if (!$p->isSystemAdmin()) {
            $allowedUnits = UnitScope::accessibleUnitIds($p);
            if (!in_array($unitId, $allowedUnits, true)) {
                throw new ForbiddenException("Access to property #{$unitId} is not permitted");
            }
        }

        // Date validation: Y-m-d, from <= to, max 92 days
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            throw new ValidationException(['date_format' => 'Invalid date format. Expected YYYY-MM-DD']);
        }

        $d1 = \DateTimeImmutable::createFromFormat('Y-m-d', $from);
        $d2 = \DateTimeImmutable::createFromFormat('Y-m-d', $to);
        if (!$d1 || !$d2 || $d1 > $d2) {
            throw new ValidationException(['date_range' => 'Invalid date range: start date must be before or equal to end date']);
        }

        $diffDays = $d1->diff($d2)->days;
        if ($diffDays > 92) {
            throw new ValidationException(['date_range' => 'Date range cannot exceed 92 days (approx. 3 months)']);
        }

        // Render PDF
        $class = $cfg['pdf'];
        /** @var PdfBase $renderer */
        $renderer = new $class();
        $lang = $p->language() ?? 'en';
        $res = $renderer->render($p, $unitId, $from, $to, $lang);

        // Audit log
        AuditLogger::log(
            $p,
            'report.generated',
            'report',
            $unitId,
            [
                'key' => $key,
                'from' => $from,
                'to' => $to,
                'path' => $res['path'],
                'pages' => $res['pages'],
            ]
        );

        return $res;
    }

    /**
     * Streams the report PDF directly to output.
     */
    public function stream(Principal $p, string $key, int $unitId, string $from, string $to): void
    {
        $res = $this->generate($p, $key, $unitId, $from, $to);
        $abs = PrivateStorage::abs($res['path']);

        if (!is_file($abs)) {
            throw new NotFoundException('Generated report PDF not found on disk');
        }

        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($res['filename']) . '"');
        header('Content-Length: ' . filesize($abs));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, must-revalidate, max-age=0');
        header('Pragma: public');

        readfile($abs);
        exit;
    }

    /**
     * Generates and delivers the report to the Principal's registered contact.
     *
     * @return array{queued:bool, destination_masked:string, filename:string}
     */
    public function deliver(
        Principal $p,
        array|string $key,
        ?int $unitId = null,
        ?string $from = null,
        ?string $to = null,
        ?string $channel = null
    ): array {
        if (is_array($key)) {
            $unitId = (int) ($key['unit_id'] ?? 0);
            $from = (string) ($key['from'] ?? '');
            $to = (string) ($key['to'] ?? '');
            $channel = (string) ($key['channel'] ?? 'email');
            $key = (string) ($key['report_key'] ?? $key['key'] ?? '');
        }

        // Permission check
        if (!$p->hasAnyPermission(['reportes.catalog.send', 'facturas.invoices.manage'])) {
            throw new ForbiddenException('Missing permission reportes.catalog.send');
        }

        $channel = strtolower(trim($channel));
        if (!in_array($channel, ['whatsapp', 'email'], true)) {
            throw new ValidationException(['channel' => "Unsupported delivery channel '{$channel}'. Must be whatsapp or email"]);
        }

        // Resolve recipient destination STRICTLY from the principal's own profile
        $destination = null;
        $destinationMasked = '';

        if ($channel === 'whatsapp') {
            $phone = $p->phone();
            if (!$phone) {
                throw new ValidationException(['phone' => 'User does not have a registered phone number for WhatsApp delivery']);
            }

            // Check opt-in in DB
            $uStmt = $this->pdo->prepare('SELECT whatsapp_opt_in FROM tec_usuarios WHERE id = :id');
            $uStmt->execute([':id' => $p->id()]);
            $optIn = (int) ($uStmt->fetchColumn() ?: 0);
            if ($optIn !== 1) {
                throw new ValidationException(['whatsapp_opt_in' => 'User has not opted in to receive WhatsApp notifications']);
            }

            $destination = $phone;
            $destinationMasked = Phone::mask($destination);
        } else {
            // Email
            $uStmt = $this->pdo->prepare('SELECT notify_email, nickname FROM tec_usuarios WHERE id = :id');
            $uStmt->execute([':id' => $p->id()]);
            $uRow = $uStmt->fetch(\PDO::FETCH_ASSOC);
            $email = !empty($uRow['notify_email']) ? (string) $uRow['notify_email'] : (!empty($uRow['nickname']) ? (string) $uRow['nickname'] : null);

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new ValidationException(['email' => 'User does not have a valid notification email for report delivery']);
            }

            $destination = $email;
            $parts = explode('@', $destination);
            $destinationMasked = substr($parts[0], 0, 2) . '***@' . ($parts[1] ?? '');
        }

        // Generate report
        $res = $this->generate($p, $key, $unitId, $from, $to);

        // Emit document.delivery
        $this->notifications->emit(
            $this->pdo,
            'document.delivery',
            'report',
            $unitId,
            [['user_id' => $p->id()]],
            [
                'title' => "Report: {$key}",
                'body' => "Your requested report {$res['filename']} is ready.",
                'attachment_path' => PrivateStorage::abs($res['path']),
                'force_channel' => $channel,
                'lang' => $p->language() ?? 'en',
                'payload' => [
                    'report_key' => $key,
                    'unit_id' => $unitId,
                    'channel' => $channel,
                    'destination' => $destination,
                    'filename' => $res['filename'],
                ],
            ]
        );

        // Audit log
        AuditLogger::log(
            $p,
            'report.delivered',
            'report',
            $unitId,
            [
                'key' => $key,
                'channel' => $channel,
                'destination_masked' => $destinationMasked,
                'path' => $res['path'],
            ]
        );

        return [
            'queued' => true,
            'destination_masked' => $destinationMasked,
            'filename' => $res['filename'],
        ];
    }
}
