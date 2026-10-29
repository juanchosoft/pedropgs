<?php

declare(strict_types=1);

namespace Pgs\Voice;

use Pgs\Api\Request;
use Pgs\Api\Response;
use Pgs\Auth\Principal;
use Pgs\Auth\UnitScope;
use Pgs\Core\Clock;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\ConfirmationRequiredException;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\ValidationException;
use Pgs\Core\Exceptions\VerificationRequiredException;
use Pgs\Core\Phone;
use Pgs\Notifications\NotificationService;
use Pgs\PendingActions\PendingActionService;
use Pgs\Services\ServiceCatalogService;
use Pgs\Services\ServiceRequestService;
use Pgs\Tasks\TaskService;

final class VoiceOperationsController
{
    private \PDO $pdo;
    private PendingActionService $pendingActions;

    public function __construct(?\PDO $pdo = null, ?PendingActionService $pendingActions = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
        $this->pendingActions = $pendingActions ?? new PendingActionService($this->pdo);
    }

    /**
     * GET /voice/services
     */
    public function getServices(Request $req): Response
    {
        $session = (array) $req->attr('call_session');
        $verification = (string) ($session['verification'] ?? 'none');
        $lang = (string) ($session['lang'] ?? 'es');

        $q = trim((string) $req->query('q', ''));
        $unitId = $verification === 'pin' ? (int) $req->query('unit_id', 0) : 0;

        $where = ["s.is_active = 1"];
        $params = [];

        if ($verification !== 'pin') {
            $where[] = "s.is_public = 1";
        }

        if ($q !== '') {
            $where[] = "(s.name_es LIKE :q OR s.name_en LIKE :q2 OR s.code LIKE :q3)";
            $params[':q'] = "%{$q}%";
            $params[':q2'] = "%{$q}%";
            $params[':q3'] = "%{$q}%";
        }

        $whereSql = implode(' AND ', $where);
        $stmt = $this->pdo->prepare(
            "SELECT s.* FROM tec_services s WHERE {$whereSql} ORDER BY s.id ASC LIMIT 8"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $r) {
            $name = $lang === 'en' && !empty($r['name_en']) ? (string) $r['name_en'] : (string) $r['name_es'];
            $desc = $lang === 'en' && !empty($r['description_en']) ? (string) $r['description_en'] : (string) $r['description_es'];
            $cond = $lang === 'en' && !empty($r['conditions_en']) ? (string) $r['conditions_en'] : (string) ($r['conditions_es'] ?? '');

            $priceText = '$0';
            $unit = (string) ($r['price_unit'] ?? $r['price_type'] ?? '');
            if (!empty($unit)) {
                if ($unit === 'fixed') {
                    $priceText = '$' . number_format((float) ($r['price_amount'] ?? 0), 2);
                } elseif ($unit === 'hour' || $unit === 'hourly') {
                    $priceText = '$' . number_format((float) ($r['price_amount'] ?? 0), 2) . ($lang === 'en' ? '/hr' : '/hora');
                } else {
                    $priceText = $lang === 'en' ? 'Quote required' : 'Cotización requerida';
                }
            }

            $out[] = [
                'id' => (int) $r['id'],
                'code' => (string) $r['code'],
                'name' => $name,
                'description' => $desc,
                'conditions' => $cond,
                'price_text' => $priceText,
                'lead_time_days' => (int) ($r['lead_time_days'] ?? 0),
            ];
        }

        return Response::ok($out);
    }

    /**
     * GET /voice/units
     */
    public function getUnits(Request $req): Response
    {
        $this->requirePinLevel($req);
        $principal = $req->attr('principal');
        if (!$principal instanceof Principal) {
            throw new ForbiddenException();
        }

        $q = trim((string) $req->query('q', ''));
        $where = ["u.enable IN ('si', 'yes')"];
        $params = [];

        if (!$principal->isSystemAdmin()) {
            $scope = UnitScope::forUser($this->pdo, (int) $principal->id(), $principal->userType);
            if (empty($scope)) {
                return Response::ok([]);
            }
            $inSql = implode(',', array_map('intval', $scope));
            $where[] = "u.id IN ({$inSql})";
        }

        if ($q !== '') {
            $where[] = "u.nombre LIKE :q";
            $params[':q'] = "%{$q}%";
        }

        $whereSql = implode(' AND ', $where);
        $stmt = $this->pdo->prepare("SELECT u.id, u.nombre FROM tbl_unidades u WHERE {$whereSql} ORDER BY u.nombre ASC LIMIT 20");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $out = array_map(fn($r) => ['id' => (int) $r['id'], 'name' => (string) $r['nombre']], $rows);
        return Response::ok($out);
    }

    /**
     * GET /voice/units/{id}/contacts
     */
    public function getUnitContacts(Request $req): Response
    {
        $this->requirePinLevel($req);
        $principal = $req->attr('principal');
        if (!$principal instanceof Principal || !$principal->isSystemAdmin()) {
            throw new ForbiddenException("Only system administrators can access unit emergency contacts");
        }

        $unitId = (int) $req->attr('id');
        $stmt = $this->pdo->prepare(
            "SELECT contact_role, name, phone_e164, phone_raw FROM tec_unit_contacts
             WHERE unidad_id = :uid AND is_active = 1
             ORDER BY id ASC"
        );
        $stmt->execute([':uid' => $unitId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $r) {
            $rawPhone = $r['phone_e164'] ?? $r['phone_raw'];
            $masked = Phone::mask((string) $rawPhone);
            $out[] = [
                'role' => (string) $r['contact_role'],
                'name' => (string) ($r['name'] ?? 'Contact'),
                'phone_masked' => $masked,
            ];
        }

        return Response::ok($out);
    }

    /**
     * GET /voice/escalation
     */
    public function getEscalation(Request $req): Response
    {
        $session = (array) $req->attr('call_session');
        $lang = (string) ($session['lang'] ?? 'es');
        $service = new EscalationService($this->pdo);
        $info = $service->getEscalationInfo(null, $lang);

        if (!empty($session['id'])) {
            $service->recordEscalation((int) $session['id'], $info['mode'], 'query');
        }

        return Response::ok($info);
    }

    /**
     * GET /voice/tasks
     */
    public function listTasks(Request $req): Response
    {
        $this->requirePinLevel($req);
        $principal = $req->attr('principal');
        if (!$principal instanceof Principal) {
            throw new ForbiddenException();
        }
        $principal->requireAny(['tareas.tasks.view', 'tareas.tasks.view_all']);

        $unitId = (int) $req->query('unit_id', 0);
        $status = trim((string) $req->query('status', ''));

        $where = ["t.deleted_at IS NULL"];
        $params = [];

        if (!$principal->isSystemAdmin()) {
            $scope = UnitScope::forUser($this->pdo, (int) $principal->id(), $principal->userType);
            if (empty($scope)) {
                return Response::ok([]);
            }
            $inSql = implode(',', array_map('intval', $scope));
            $where[] = "t.unidad_id IN ({$inSql})";
        }

        if ($unitId > 0) {
            $where[] = "t.unidad_id = :uid";
            $params[':uid'] = $unitId;
        }

        if ($status !== '') {
            $where[] = "t.status = :st";
            $params[':st'] = $status;
        }

        $whereSql = implode(' AND ', $where);
        $sql = "SELECT t.*, u.nombre as unit_nombre, e.nombre as employee_nombre
                FROM tec_tasks t
                LEFT JOIN tbl_unidades u ON u.id = t.unidad_id
                LEFT JOIN tec_employee e ON e.id = t.assignee_employee_id
                WHERE {$whereSql}
                ORDER BY t.id DESC LIMIT 5";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $r) {
            $assigneeParts = explode(' ', trim((string) ($r['employee_nombre'] ?? '')));
            $assigneeFirst = $assigneeParts[0] ?? null;

            $out[] = [
                'code' => (string) $r['code'],
                'title' => (string) $r['title'],
                'unit' => (string) ($r['unit_nombre'] ?? ''),
                'status' => (string) $r['status'],
                'priority' => (string) $r['priority'],
                'required_date' => $r['required_date'],
                'assignee_first_name' => $assigneeFirst,
            ];
        }

        return Response::ok($out);
    }

    /**
     * GET /voice/tasks/{code}
     */
    public function getTask(Request $req): Response
    {
        $code = (string) $req->attr('code');
        $session = (array) $req->attr('call_session');
        $verification = (string) ($session['verification'] ?? 'none');
        $principal = $req->attr('principal');

        if ($verification === 'none') {
            throw new VerificationRequiredException("Identification required to view tasks", "phone");
        }

        $stmt = $this->pdo->prepare(
            "SELECT t.*, u.nombre as unit_nombre
             FROM tec_tasks t
             LEFT JOIN tbl_unidades u ON u.id = t.unidad_id
             WHERE t.code = :code AND t.deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([':code' => $code]);
        $t = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$t) {
            throw new NotFoundException("Task not found: {$code}");
        }

        // If verification is phone, caller must be the requester
        if ($verification === 'phone') {
            $userId = !empty($session['resolved_user_id']) ? (int) $session['resolved_user_id'] : null;
            if ($userId === null || (int) ($t['requested_by_user_id'] ?? 0) !== $userId) {
                throw new VerificationRequiredException("PIN verification required to view this task", "pin");
            }
        }

        return Response::ok([
            'code' => (string) $t['code'],
            'title' => (string) $t['title'],
            'unit' => (string) ($t['unit_nombre'] ?? ''),
            'status' => (string) $t['status'],
            'priority' => (string) $t['priority'],
            'required_date' => $t['required_date'],
            'due_date' => $t['due_date'],
            'last_update' => $t['updated_at'] ?? $t['created_at'],
        ]);
    }

    /**
     * POST /voice/tasks
     */
    public function createTask(Request $req): Response
    {
        $this->requirePhoneOrPin($req);
        $principal = $req->attr('principal');
        if (!$principal instanceof Principal) {
            throw new ForbiddenException();
        }
        $principal->require('tareas.tasks.create');

        $session = (array) $req->attr('call_session');
        $body = $req->json();
        $token = $body['confirmation_token'] ?? null;
        $sessionId = (int) $session['id'];

        if (!empty($token)) {
            $res = $this->pendingActions->confirm($principal, (string) $token, null, $sessionId);
            return Response::ok(['code' => $res['result_ref']], 201);
        }

        // Step 1: validate and create pending action
        $title = trim((string) ($body['title'] ?? ''));
        if ($title === '') {
            throw new ValidationException(['title' => 'Title is required']);
        }

        $unitId = isset($body['unit_id']) ? (int) $body['unit_id'] : null;
        $unitText = trim((string) ($body['unit_text'] ?? ''));
        $priority = in_array($body['priority'] ?? '', ['low', 'normal', 'high', 'urgent'], true) ? (string) $body['priority'] : 'normal';

        $payload = [
            'title' => $title,
            'description' => $body['description'] ?? null,
            'priority' => $priority,
            'required_date' => $body['required_date'] ?? null,
            'origin' => 'phone',
            'requested_verification' => (string) ($session['verification'] ?? 'phone'),
            'unidad_id' => $unitId,
            'location_label' => $unitText ?: null,
        ];

        // If phone level: verify unit in scope, else fallback to location_label and needs_review
        if (($session['verification'] ?? '') === 'phone') {
            $scope = UnitScope::forUser($this->pdo, (int) $principal->id(), $principal->userType);
            if ($unitId !== null && $scope !== null && !in_array($unitId, $scope, true)) {
                $payload['unidad_id'] = null;
                $payload['location_label'] = $unitText ?: ("Unit #" . $unitId);
                $payload['needs_review'] = 1;
            }
        }

        $unitName = 'su propiedad';
        if ($unitId) {
            $uStmt = $this->pdo->prepare("SELECT nombre FROM tbl_unidades WHERE id = :id");
            $uStmt->execute([':id' => $unitId]);
            $uName = $uStmt->fetchColumn();
            if ($uName) {
                $unitName = (string) $uName;
            }
        } elseif ($unitText !== '') {
            $unitName = $unitText;
        }

        $preview = "Crear tarea '{$title}' para {$unitName} con prioridad {$priority}. ¿Confirmo la creación?";
        $action = $this->pendingActions->create($principal, 'voice', 'task.create', $payload, $preview, null, $sessionId);

        throw new ConfirmationRequiredException(['text' => $preview], $action['action_id']);
    }

    /**
     * POST /voice/tasks/{code}/status
     */
    public function updateTaskStatus(Request $req): Response
    {
        $this->requirePinLevel($req);
        $principal = $req->attr('principal');
        if (!$principal instanceof Principal) {
            throw new ForbiddenException();
        }

        $code = (string) $req->attr('code');
        $session = (array) $req->attr('call_session');
        $sessionId = (int) $session['id'];
        $body = $req->json();
        $token = $body['confirmation_token'] ?? null;

        if (!empty($token)) {
            $res = $this->pendingActions->confirm($principal, (string) $token, null, $sessionId);
            return Response::ok(['code' => $code, 'status' => $res['result']['status'] ?? 'updated']);
        }

        $stmt = $this->pdo->prepare("SELECT id, code, status FROM tec_tasks WHERE code = :code AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([':code' => $code]);
        $task = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$task) {
            throw new NotFoundException("Task not found: {$code}");
        }

        $newStatus = trim((string) ($body['status'] ?? ''));
        if ($newStatus === '') {
            throw new ValidationException(['status' => 'Status is required']);
        }

        $note = isset($body['note']) ? (string) $body['note'] : null;
        $payload = [
            'task_id' => (int) $task['id'],
            'task_code' => $code,
            'status' => $newStatus,
            'note' => $note,
        ];

        $preview = "Cambiar el estado de la tarea {$code} a '{$newStatus}'. ¿Confirmo el cambio?";
        $action = $this->pendingActions->create($principal, 'voice', 'task.status', $payload, $preview, null, $sessionId);

        throw new ConfirmationRequiredException(['text' => $preview], $action['action_id']);
    }

    /**
     * POST /voice/service-requests
     */
    public function createServiceRequest(Request $req): Response
    {
        $this->requirePhoneOrPin($req);
        $principal = $req->attr('principal');
        if (!$principal instanceof Principal) {
            throw new ForbiddenException();
        }
        $principal->require('servicios.requests.create');

        $session = (array) $req->attr('call_session');
        $sessionId = (int) $session['id'];
        $body = $req->json();
        $token = $body['confirmation_token'] ?? null;

        if (!empty($token)) {
            $res = $this->pendingActions->confirm($principal, (string) $token, null, $sessionId);
            return Response::ok(['task_code' => $res['result_ref']], 201);
        }

        $serviceId = (int) ($body['service_id'] ?? 0);
        $unitId = (int) ($body['unit_id'] ?? 0);
        $requiredDate = (string) ($body['required_date'] ?? '');

        if ($serviceId <= 0 || $unitId <= 0 || $requiredDate === '') {
            throw new ValidationException(['service_id' => 'Required', 'unit_id' => 'Required', 'required_date' => 'Required']);
        }

        $svcStmt = $this->pdo->prepare("SELECT name_es FROM tec_services WHERE id = :id");
        $svcStmt->execute([':id' => $serviceId]);
        $svcName = (string) ($svcStmt->fetchColumn() ?: 'Servicio');

        $uStmt = $this->pdo->prepare("SELECT nombre FROM tbl_unidades WHERE id = :id");
        $uStmt->execute([':id' => $unitId]);
        $unitName = (string) ($uStmt->fetchColumn() ?: 'Unidad');

        $payload = [
            'service_id' => $serviceId,
            'unit_id' => $unitId,
            'required_date' => $requiredDate,
            'priority' => $body['priority'] ?? 'normal',
            'notes' => $body['notes'] ?? null,
            'location_label' => $body['location_label'] ?? null,
        ];

        $preview = "Solicitar el servicio {$svcName} para {$unitName} el día {$requiredDate}. ¿Confirmo la solicitud?";
        $action = $this->pendingActions->create($principal, 'voice', 'service.request', $payload, $preview, null, $sessionId);

        throw new ConfirmationRequiredException(['text' => $preview], $action['action_id']);
    }

    /**
     * POST /voice/messages
     */
    public function takeMessage(Request $req): Response
    {
        $session = (array) $req->attr('call_session');
        $sessionId = (int) $session['id'];

        // Limit to 3 messages per call session
        $countStmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM tec_tasks WHERE origin = 'phone_escalation' AND requested_by_user_id IS NULL AND created_at >= :st"
        );
        $countStmt->execute([':st' => $session['started_at'] ?? Clock::nowUtcSql()]);
        if ((int) $countStmt->fetchColumn() >= 3) {
            throw new ValidationException(['message' => 'Maximum message limit reached for this session']);
        }

        $body = $req->json();
        $name = trim((string) ($body['name'] ?? ''));
        $message = trim((string) ($body['message'] ?? ''));

        if ($name === '' || $message === '') {
            throw new ValidationException(['name' => 'Name is required', 'message' => 'Message text is required']);
        }

        $callbackPhone = Phone::toE164($body['callback_phone'] ?? null) ?? ($session['caller_e164'] ?? null);
        $unitText = trim((string) ($body['unit_text'] ?? ''));
        $urgency = ($body['urgency'] ?? 'normal') === 'urgent' ? 'urgent' : 'normal';

        $pdo = $this->pdo;
        $now = Clock::nowUtc();
        $code = \Pgs\Core\Sequence::code($pdo, 'TSK', $now);

        $stmt = $pdo->prepare(
            "INSERT INTO tec_tasks (
                code, title, description, priority, status, origin, requested_by_name,
                requested_by_phone, location_label, needs_review, created_at, updated_at
             ) VALUES (
                :code, :title, :desc, :prio, 'pending', 'phone_escalation', :name,
                :phone, :loc, 1, NOW(), NOW()
             )"
        );
        $stmt->execute([
            ':code' => $code,
            ':title' => "Phone message from {$name}",
            ':desc' => $message,
            ':prio' => $urgency,
            ':name' => $name,
            ':phone' => $callbackPhone,
            ':loc' => $unitText ?: null,
        ]);
        $taskId = (int) $pdo->lastInsertId();
        $pdo->prepare(
            "INSERT INTO tec_task_history (task_id, event, note, actor_kind, channel, created_at)
             VALUES (:id, 'created', 'Phone message taken by the voice assistant', 'voice', 'voice', NOW())"
        )->execute([':id' => $taskId]);

        // Emit phone.message notification
        $notif = new NotificationService($pdo);
        $admins = $pdo->query("SELECT id FROM tec_usuarios WHERE tipo = 'SuperAdministrador' AND habilitado IN ('yes', 'si')")->fetchAll(\PDO::FETCH_ASSOC);
        $recipients = array_map(fn($a) => ['user_id' => (int) $a['id']], $admins);

        if (!empty($recipients)) {
            $notif->emit($pdo, 'phone.message', 'task', $taskId, $recipients, [
                'title' => "New phone message from {$name}",
                'body' => "Message: {$message}",
                'link_url' => "task_view.php?id={$taskId}",
                'always_email' => true,
                'payload' => ['task_id' => $taskId, 'code' => $code, 'caller' => $callbackPhone],
            ]);
        }

        return Response::ok(['code' => $code], 201);
    }

    private function requirePinLevel(Request $req): void
    {
        $session = (array) $req->attr('call_session');
        if (($session['verification'] ?? 'none') !== 'pin') {
            throw new VerificationRequiredException("PIN verification required", "pin");
        }
    }

    private function requirePhoneOrPin(Request $req): void
    {
        $session = (array) $req->attr('call_session');
        $v = $session['verification'] ?? 'none';
        if ($v !== 'phone' && $v !== 'pin') {
            throw new VerificationRequiredException("Identification required", "phone");
        }
    }
}
