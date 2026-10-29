<?php

declare(strict_types=1);

namespace Pgs\Voice;

use Pgs\Api\Request;
use Pgs\Api\Response;
use Pgs\Auth\Principal;
use Pgs\Auth\UnitScope;
use Pgs\Core\AuditLogger;
use Pgs\Core\Clock;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\ConfirmationRequiredException;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\GoneException;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\ValidationException;
use Pgs\Core\Exceptions\VerificationRequiredException;
use Pgs\Core\Phone;
use Pgs\Core\PrivateStorage;
use Pgs\Core\SignedLinks;
use Pgs\Invoices\InvoiceService;
use Pgs\PendingActions\PendingActionService;
use Pgs\Reports\ReportCatalog;
use Pgs\Reports\ReportDeliveryService;

final class VoiceInvoicesReportsController
{
    private \PDO $pdo;
    private PendingActionService $pendingActions;

    public function __construct(?\PDO $pdo = null, ?PendingActionService $pendingActions = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
        $this->pendingActions = $pendingActions ?? new PendingActionService($this->pdo);
    }

    /**
     * GET /voice/invoices
     */
    public function listInvoices(Request $req): Response
    {
        $this->requirePinLevel($req);
        $principal = $req->attr('principal');
        if (!$principal instanceof Principal) {
            throw new ForbiddenException();
        }

        // Safeguard D-25: UnitAdmin can never have facturas.invoices.manage
        if ($principal->isUnitAdmin()) {
            $principal->require('facturas.invoices.view_own_units');
        } else {
            $principal->requireAny(['facturas.invoices.view_own_units', 'facturas.invoices.manage']);
        }

        $unitId = (int) $req->query('unit_id', 0);
        $status = trim((string) $req->query('status', ''));

        $where = ["i.payment_status != 'void'"];
        $params = [];

        if (!$principal->isSystemAdmin()) {
            $scope = UnitScope::forUser($this->pdo, (int) $principal->id(), $principal->userType);
            if (empty($scope)) {
                return Response::ok([]);
            }
            $inSql = implode(',', array_map('intval', $scope));
            $where[] = "i.unidad_id IN ({$inSql})";
        }

        if ($unitId > 0) {
            $where[] = "i.unidad_id = :uid";
            $params[':uid'] = $unitId;
        }

        if ($status !== '') {
            $where[] = "i.payment_status = :st";
            $params[':st'] = $status;
        }

        $whereSql = implode(' AND ', $where);
        $sql = "SELECT i.*, u.nombre as unit_nombre
                FROM tec_invoices i
                LEFT JOIN tbl_unidades u ON u.id = i.unidad_id
                WHERE {$whereSql}
                ORDER BY i.id DESC LIMIT 5";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $r) {
            $balance = (float) $r['amount'] - (float) ($r['amount_paid'] ?? 0);
            $out[] = [
                'id' => (int) $r['id'],
                'number' => (string) $r['invoice_number'],
                'unit' => (string) ($r['unit_nombre'] ?? ''),
                'issue_date' => (string) $r['issue_date'],
                'due_date' => (string) $r['due_date'],
                'amount' => (float) $r['amount'],
                'currency' => (string) $r['currency'],
                'status' => (string) $r['payment_status'],
                'balance' => $balance,
            ];

            // Audit viewed
            AuditLogger::log($principal, 'invoice.viewed', 'invoice', (int) $r['id'], null, [
                'invoice_number' => $r['invoice_number'],
            ], (int) $r['unidad_id']);
        }

        return Response::ok($out);
    }

    /**
     * POST /voice/invoices/{id}/send
     */
    public function sendInvoice(Request $req): Response
    {
        $this->requirePinLevel($req);
        $principal = $req->attr('principal');
        if (!$principal instanceof Principal) {
            throw new ForbiddenException();
        }
        $principal->require('facturas.invoices.download');
        if ($principal->isUnitAdmin()) {
            $principal->require('facturas.invoices.view_own_units');
        } else {
            $principal->requireAny(['facturas.invoices.view_own_units', 'facturas.invoices.manage']);
        }

        $id = (int) $req->attr('id');
        $invSvc = new InvoiceService($this->pdo);
        $inv = $invSvc->get($principal, $id);

        $session = (array) $req->attr('call_session');
        $sessionId = (int) $session['id'];
        $body = $req->json();
        $token = $body['confirmation_token'] ?? null;

        if (!empty($token)) {
            $res = $this->pendingActions->confirm($principal, (string) $token, null, $sessionId);
            $rData = $res['result'] ?? [];
            return Response::ok([
                'queued' => true,
                'destination_masked' => $rData['destination_masked'] ?? '',
            ]);
        }

        $channel = strtolower(trim((string) ($body['channel'] ?? 'email')));
        if ($channel !== 'whatsapp' && $channel !== 'email') {
            throw new ValidationException(['channel' => 'Channel must be whatsapp or email']);
        }

        // Fetch principal user data for destination
        $uStmt = $this->pdo->prepare("SELECT nickname, notify_email, phone_e164, whatsapp_opt_in FROM tec_usuarios WHERE id = :id");
        $uStmt->execute([':id' => $principal->id()]);
        $userRow = $uStmt->fetch(\PDO::FETCH_ASSOC);

        if (!$userRow) {
            throw new ValidationException(['destination' => 'No user profile found'], 'no_registered_destination');
        }

        $destination = '';
        $maskedDestination = '';

        if ($channel === 'whatsapp') {
            if (empty($userRow['phone_e164']) || (int) ($userRow['whatsapp_opt_in'] ?? 0) !== 1) {
                throw new ValidationException([
                    'channel' => 'User does not have an active phone number with WhatsApp opt-in enabled'
                ], 'no_registered_destination');
            }
            $destination = (string) $userRow['phone_e164'];
            $maskedDestination = Phone::mask($destination);
            $previewDest = "su número terminado en " . substr(preg_replace('/\D/', '', $destination), -4);
        } else {
            $email = trim((string) (!empty($userRow['notify_email']) ? $userRow['notify_email'] : ($userRow['nickname'] ?? '')));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new ValidationException([
                    'channel' => 'User does not have a registered email address'
                ], 'no_registered_destination');
            }
            $destination = $email;
            $parts = explode('@', $email);
            $maskedDestination = substr($parts[0], 0, 2) . '•••@' . ($parts[1] ?? '');
            $previewDest = "su correo electrónico {$maskedDestination}";
        }

        $preview = "Enviaré la factura {$inv['invoice_number']} por {$channel} a {$previewDest}. ¿Confirmo?";

        $payload = [
            'invoice_id' => $id,
            'channel' => $channel,
            'destination' => $destination,
            'destination_masked' => $maskedDestination,
        ];

        $action = $this->pendingActions->create($principal, 'voice', 'invoice.send', $payload, $preview, null, $sessionId);

        throw new ConfirmationRequiredException(['text' => $preview], $action['action_id']);
    }

    /**
     * GET /voice/reports/catalog
     */
    public function getReportsCatalog(Request $req): Response
    {
        $this->requirePinLevel($req);
        $principal = $req->attr('principal');
        if (!$principal instanceof Principal) {
            throw new ForbiddenException();
        }
        $principal->require('reportes.catalog.view');

        $reports = ReportCatalog::forPrincipal($principal);
        $out = [];
        foreach ($reports as $r) {
            $out[] = [
                'key' => (string) ($r['key'] ?? ''),
                'name' => (string) ($r['name'] ?? ''),
            ];
        }

        return Response::ok($out);
    }

    /**
     * POST /voice/reports/send
     */
    public function sendReport(Request $req): Response
    {
        $this->requirePinLevel($req);
        $principal = $req->attr('principal');
        if (!$principal instanceof Principal) {
            throw new ForbiddenException();
        }
        $principal->require('reportes.catalog.send');

        $session = (array) $req->attr('call_session');
        $sessionId = (int) $session['id'];
        $body = $req->json();
        $token = $body['confirmation_token'] ?? null;

        if (!empty($token)) {
            $res = $this->pendingActions->confirm($principal, (string) $token, null, $sessionId);
            $rData = $res['result'] ?? [];
            return Response::ok([
                'queued' => true,
                'destination_masked' => $rData['destination_masked'] ?? '',
            ]);
        }

        $reportKey = trim((string) ($body['report_key'] ?? ''));
        $unitId = (int) ($body['unit_id'] ?? 0);
        $from = trim((string) ($body['from'] ?? ''));
        $to = trim((string) ($body['to'] ?? ''));
        $channel = strtolower(trim((string) ($body['channel'] ?? 'email')));

        if ($reportKey === '' || $unitId <= 0 || $from === '' || $to === '') {
            throw new ValidationException([
                'report_key' => 'Required',
                'unit_id' => 'Required',
                'from' => 'Required',
                'to' => 'Required',
            ]);
        }

        if ($channel !== 'whatsapp' && $channel !== 'email') {
            throw new ValidationException(['channel' => 'Channel must be whatsapp or email']);
        }

        if (!isset(ReportCatalog::REPORTS[$reportKey])) {
            throw new NotFoundException("Report '{$reportKey}' not found");
        }

        $cfg = ReportCatalog::REPORTS[$reportKey];
        if (!empty($cfg['deny_unit_admin']) && $principal->isUnitAdmin()) {
            throw new ForbiddenException("Access denied: Property administrators cannot generate this report");
        }
        $principal->requireAny((array) $cfg['perm']);

        UnitScope::assertUnit($principal, $unitId);

        // Fetch user destination
        $uStmt = $this->pdo->prepare("SELECT nickname, notify_email, phone_e164, whatsapp_opt_in FROM tec_usuarios WHERE id = :id");
        $uStmt->execute([':id' => $principal->id()]);
        $userRow = $uStmt->fetch(\PDO::FETCH_ASSOC);

        if (!$userRow) {
            throw new ValidationException(['destination' => 'No user profile found'], 'no_registered_destination');
        }

        if ($channel === 'whatsapp') {
            if (empty($userRow['phone_e164']) || (int) ($userRow['whatsapp_opt_in'] ?? 0) !== 1) {
                throw new ValidationException(['channel' => 'No registered WhatsApp phone number with opt-in'], 'no_registered_destination');
            }
            $dest = (string) $userRow['phone_e164'];
            $maskedDest = Phone::mask($dest);
        } else {
            $email = trim((string) (!empty($userRow['notify_email']) ? $userRow['notify_email'] : ($userRow['nickname'] ?? '')));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new ValidationException(['channel' => 'No registered email address'], 'no_registered_destination');
            }
            $dest = $email;
            $parts = explode('@', $email);
            $maskedDest = substr($parts[0], 0, 2) . '•••@' . ($parts[1] ?? '');
        }

        $reportName = $cfg['name'] ?? $reportKey;
        $preview = "Enviaré el reporte de {$reportName} de {$from} a {$to} por {$channel} a {$maskedDest}. ¿Confirmo?";

        $payload = [
            'report_key' => $reportKey,
            'unit_id' => $unitId,
            'from' => $from,
            'to' => $to,
            'channel' => $channel,
            'destination' => $dest,
            'destination_masked' => $maskedDest,
        ];

        $action = $this->pendingActions->create($principal, 'voice', 'report.send', $payload, $preview, null, $sessionId);

        throw new ConfirmationRequiredException(['text' => $preview], $action['action_id']);
    }

    /**
     * GET /files/{token}
     */
    public function serveSignedFile(Request $req): Response
    {
        $token = (string) $req->attr('token');
        if ($token === '') {
            throw new NotFoundException("Token is required");
        }

        // SignedLinks::consume handles expiration and max uses (throws 404 or 410)
        $link = SignedLinks::consume($token);

        $relPath = (string) $link['resource_ref'];
        $abs = PrivateStorage::abs($relPath);

        if (!is_file($abs)) {
            throw new NotFoundException("File not found on storage");
        }

        $filename = (string) ($link['filename'] ?? basename($abs));
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'csv' => 'text/csv',
            default => 'application/octet-stream',
        };

        // Record audit
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO tec_audit_log (occurred_at, request_id, actor_kind, actor_user_id, channel, action, entity_type, ip, user_agent)
                 VALUES (NOW(), :rid, 'anonymous', :uid, 'web', 'file.downloaded', 'file', :ip, :ua)"
            );
            $stmt->execute([
                ':rid' => \Pgs\Core\RequestContext::id(),
                ':uid' => $link['issued_to_user_id'] ?? null,
                ':ip' => $req->ip(),
                ':ua' => $req->header('User-Agent') ?? '',
            ]);
        } catch (\Throwable) {
            // Non-blocking audit
        }

        $body = file_get_contents($abs);
        $res = Response::raw(200, $body ?: '', $mime);
        $res->header('Content-Disposition', 'attachment; filename="' . basename($filename) . '"');
        $res->header('X-Content-Type-Options', 'nosniff');
        $res->header('Cache-Control', 'private, no-cache, no-store, must-revalidate');

        return $res;
    }

    private function requirePinLevel(Request $req): void
    {
        $session = (array) $req->attr('call_session');
        if (($session['verification'] ?? 'none') !== 'pin') {
            throw new VerificationRequiredException("PIN verification required", "pin");
        }
    }
}
