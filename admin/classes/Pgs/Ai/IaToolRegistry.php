<?php

declare(strict_types=1);

namespace Pgs\Ai;

use Pgs\Auth\Principal;
use Pgs\Auth\UnitScope;
use Pgs\Availability\AbsenceService;
use Pgs\Availability\AvailabilityService;
use Pgs\Availability\LeaveBalanceService;
use Pgs\Availability\LeaveDayCounter;
use Pgs\Availability\ReservationService;
use Pgs\Core\AuditLogger;
use Pgs\Core\Clock;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\ConflictException;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\ValidationException;
use Pgs\Core\Json;
use Pgs\Core\Phone;
use Pgs\Expenses\ExpenseService;
use Pgs\Invoices\InvoiceService;
use Pgs\Notifications\NotificationService;
use Pgs\PendingActions\PendingActionService;
use Pgs\Reports\ReportCatalog;
use Pgs\Services\ServiceRequestService;
use Pgs\Tasks\TaskService;

final class IaToolRegistry
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    /**
     * Tool definitions dictionary.
     *
     * @var array<string, array<string, mixed>>
     */
    private const HERRAMIENTAS = [
        'buscar_unidades' => [
            'permiso' => 'ia.asistente.use',
            'description' => 'Search property units (HOA communities/condos) accessible to the current user.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'q' => ['type' => 'string', 'description' => 'Search term for property name'],
                ],
                'additionalProperties' => false,
            ],
            'writes' => false,
        ],
        'consultar_tareas' => [
            'permiso' => ['tareas.tasks.view', 'tareas.tasks.view_all'],
            'description' => 'List maintenance tasks filtered by property, status, priority, or assignee.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'unit_id' => ['type' => 'integer', 'description' => 'Property unit ID'],
                    'status' => ['type' => 'string', 'enum' => ['pending', 'in_progress', 'completed', 'cancelled']],
                    'priority' => ['type' => 'string', 'enum' => ['low', 'normal', 'high', 'urgent']],
                    'assigned_to_me' => ['type' => 'boolean'],
                    'from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                ],
                'additionalProperties' => false,
            ],
            'writes' => false,
        ],
        'ver_tarea' => [
            'permiso' => ['tareas.tasks.view', 'tareas.tasks.view_all'],
            'description' => 'Get full details of a maintenance task by its code (TSK-YYYY-NNNNNN) or ID.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'code' => ['type' => 'string', 'description' => 'Task code like TSK-2026-000001 or ID'],
                ],
                'required' => ['code'],
                'additionalProperties' => false,
            ],
            'writes' => false,
        ],
        'proponer_tarea' => [
            'permiso' => 'tareas.tasks.create',
            'description' => 'Propose creating a maintenance task. Returns summary and action_id requiring explicit user confirmation.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'unit_id' => ['type' => 'integer', 'description' => 'Target property unit ID'],
                    'title' => ['type' => 'string', 'description' => 'Task title (3-160 chars)'],
                    'description' => ['type' => 'string', 'description' => 'Detailed description'],
                    'priority' => ['type' => 'string', 'enum' => ['low', 'normal', 'high', 'urgent']],
                    'required_date' => ['type' => 'string', 'description' => 'Desired date YYYY-MM-DD'],
                    'due_date' => ['type' => 'string', 'description' => 'Due date YYYY-MM-DD'],
                    'location_label' => ['type' => 'string', 'description' => 'Location within property (e.g. Apt 302, Clubhouse)'],
                    'attachment_id' => ['type' => 'string', 'description' => 'Public ID or numeric ID of an AI attachment'],
                ],
                'required' => ['unit_id', 'title'],
                'additionalProperties' => false,
            ],
            'writes' => true,
        ],
        'proponer_cambio_estado_tarea' => [
            'permiso' => ['tareas.tasks.status', 'tareas.tasks.update'],
            'description' => 'Propose transitioning a task status. Returns summary and action_id requiring user confirmation.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'code' => ['type' => 'string', 'description' => 'Task code or ID'],
                    'status' => ['type' => 'string', 'enum' => ['pending', 'in_progress', 'completed', 'cancelled']],
                    'note' => ['type' => 'string', 'description' => 'Transition note'],
                    'cancel_reason' => ['type' => 'string', 'description' => 'Reason if cancelling'],
                ],
                'required' => ['code', 'status'],
                'additionalProperties' => false,
            ],
            'writes' => true,
        ],
        'proponer_asignacion_tarea' => [
            'permiso' => 'tareas.tasks.assign',
            'description' => 'Propose assigning a task to a technician/worker. Returns action_id for confirmation.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'code' => ['type' => 'string', 'description' => 'Task code or ID'],
                    'employee_id' => ['type' => 'integer', 'description' => 'Worker employee ID'],
                    'note' => ['type' => 'string', 'description' => 'Assignment note'],
                ],
                'required' => ['code', 'employee_id'],
                'additionalProperties' => false,
            ],
            'writes' => true,
        ],
        'consultar_servicios' => [
            'permiso' => 'servicios.catalog.view',
            'description' => 'List services offered in the catalog (pricing, lead times, descriptions).',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'q' => ['type' => 'string', 'description' => 'Search term'],
                    'unit_id' => ['type' => 'integer', 'description' => 'Filter by unit availability'],
                ],
                'additionalProperties' => false,
            ],
            'writes' => false,
        ],
        'proponer_solicitud_servicio' => [
            'permiso' => 'servicios.requests.create',
            'description' => 'Propose requesting a catalog service for a property. Returns action_id for confirmation.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'service_id' => ['type' => 'integer'],
                    'unit_id' => ['type' => 'integer'],
                    'required_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'priority' => ['type' => 'string', 'enum' => ['low', 'normal', 'high', 'urgent']],
                    'notes' => ['type' => 'string'],
                    'location_label' => ['type' => 'string'],
                ],
                'required' => ['service_id', 'unit_id', 'required_date'],
                'additionalProperties' => false,
            ],
            'writes' => true,
        ],
        'consultar_trabajadores' => [
            'permiso' => ['disponibilidad.schedule.view', 'disponibilidad.reservations.create'],
            'description' => 'List active maintenance workers and technicians assigned to a property.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'unit_id' => ['type' => 'integer'],
                ],
                'required' => ['unit_id'],
                'additionalProperties' => false,
            ],
            'writes' => false,
        ],
        'consultar_disponibilidad' => [
            'permiso' => ['disponibilidad.schedule.view', 'disponibilidad.reservations.create'],
            'description' => 'Check worker availability calendar across dates (up to 31 days).',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'employee_id' => ['type' => 'integer'],
                    'unit_id' => ['type' => 'integer'],
                    'from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                ],
                'required' => ['employee_id', 'unit_id', 'from', 'to'],
                'additionalProperties' => false,
            ],
            'writes' => false,
        ],
        'proponer_reserva' => [
            'permiso' => 'disponibilidad.reservations.create',
            'description' => 'Propose reserving worker dates for a property. Returns action_id for confirmation.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'employee_id' => ['type' => 'integer'],
                    'unit_id' => ['type' => 'integer'],
                    'dates' => [
                        'type' => 'array',
                        'items' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'minItems' => 1,
                        'maxItems' => 10,
                    ],
                    'day_part' => ['type' => 'string', 'enum' => ['full', 'am', 'pm']],
                    'notes' => ['type' => 'string'],
                ],
                'required' => ['employee_id', 'unit_id', 'dates'],
                'additionalProperties' => false,
            ],
            'writes' => true,
        ],
        'consultar_dias_libres' => [
            'permiso' => ['disponibilidad.absences.request', 'disponibilidad.absences.approve'],
            'description' => 'Query time off balances (allocated, used, pending, available) for the current user or a worker.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'year' => ['type' => 'integer'],
                    'employee_id' => ['type' => 'integer', 'description' => 'Only for managers with approval permissions'],
                ],
                'additionalProperties' => false,
            ],
            'writes' => false,
        ],
        'proponer_ausencia' => [
            'permiso' => 'disponibilidad.absences.request',
            'description' => 'Propose requesting full days off for the current employee. Precomputes working days and balance deduction.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'date_from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'date_to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'reason_type' => ['type' => 'string', 'enum' => ['vacation', 'sick', 'personal', 'other']],
                    'reason_text' => ['type' => 'string'],
                ],
                'required' => ['date_from', 'date_to', 'reason_type'],
                'additionalProperties' => false,
            ],
            'writes' => true,
        ],
        'registrar_gastos' => [
            'permiso' => 'gastos.expenses.create',
            'description' => 'Prepare a purchase expense record for a property with items breakdown. Totals computed by server. Blocked for HOA UnitAdmins.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'unit_id' => ['type' => 'integer'],
                    'location_label' => ['type' => 'string'],
                    'purchase_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'vendor' => ['type' => 'string'],
                    'receipt_number' => ['type' => 'string'],
                    'notes' => ['type' => 'string'],
                    'attachment_id' => ['type' => 'string'],
                    'items' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'description' => ['type' => 'string'],
                                'quantity' => ['type' => 'number', 'minimum' => 0.001],
                                'unit_price' => ['type' => 'number', 'minimum' => 0],
                            ],
                            'required' => ['description', 'quantity', 'unit_price'],
                            'additionalProperties' => false,
                        ],
                        'minItems' => 1,
                        'maxItems' => 50,
                    ],
                ],
                'required' => ['unit_id', 'items'],
                'additionalProperties' => false,
            ],
            'writes' => true,
        ],
        'consultar_gastos' => [
            'permiso' => ['gastos.expenses.view_own', 'gastos.expenses.view_all'],
            'description' => 'List expenses and purchases recorded for properties. Blocked for HOA UnitAdmins.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'unit_id' => ['type' => 'integer'],
                    'from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'billing_status' => ['type' => 'string', 'enum' => ['pending', 'invoiced', 'non_billable']],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                    'totals' => ['type' => 'boolean'],
                ],
                'additionalProperties' => false,
            ],
            'writes' => false,
        ],
        'consultar_facturas' => [
            'permiso' => ['facturas.invoices.view_own_units', 'facturas.invoices.manage'],
            'description' => 'List invoices issued to properties in scope (amounts, balances, payment status).',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'unit_id' => ['type' => 'integer'],
                    'status' => ['type' => 'string', 'enum' => ['pending', 'partially_paid', 'paid', 'overdue', 'void']],
                    'from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                ],
                'additionalProperties' => false,
            ],
            'writes' => false,
        ],
        'preparar_factura_desde_adjunto' => [
            'permiso' => 'facturas.invoices.manage',
            'description' => 'Propose registering a new invoice from an uploaded PDF attachment and linking expense codes. Blocked for HOA UnitAdmins.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'attachment_id' => ['type' => 'string', 'description' => 'Public ID or numeric ID of the uploaded PDF'],
                    'invoice_number' => ['type' => 'string'],
                    'unit_id' => ['type' => 'integer'],
                    'issue_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'due_date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'amount' => ['type' => 'number', 'minimum' => 0.01],
                    'currency' => ['type' => 'string', 'default' => 'USD'],
                    'notes' => ['type' => 'string'],
                    'expense_codes' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'maxItems' => 100,
                    ],
                ],
                'required' => ['attachment_id', 'invoice_number', 'unit_id', 'issue_date', 'amount'],
                'additionalProperties' => false,
            ],
            'writes' => true,
        ],
        'consultar_contactos_unidad' => [
            'permiso' => 'unidades.contacts.view',
            'description' => 'List property contacts with roles and masked phone numbers (system admin only).',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'unit_id' => ['type' => 'integer'],
                ],
                'required' => ['unit_id'],
                'additionalProperties' => false,
            ],
            'writes' => false,
        ],
        'catalogo_reportes' => [
            'permiso' => 'reportes.catalog.view',
            'description' => 'List available operational report templates accessible for the user role.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [],
                'additionalProperties' => false,
            ],
            'writes' => false,
        ],
        'proponer_envio_reporte' => [
            'permiso' => 'reportes.catalog.send',
            'description' => 'Propose generating and sending an official PDF report to the registered email or WhatsApp.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'report_key' => ['type' => 'string', 'enum' => ['activity', 'tasks', 'invoices', 'expenses']],
                    'unit_id' => ['type' => 'integer'],
                    'from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    'channel' => ['type' => 'string', 'enum' => ['whatsapp', 'email']],
                ],
                'required' => ['report_key', 'unit_id', 'from', 'to', 'channel'],
                'additionalProperties' => false,
            ],
            'writes' => true,
        ],
        'consultar_notificaciones' => [
            'permiso' => 'notificaciones.inbox.view',
            'description' => 'List in-app notifications and alerts received for the current user.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'unread_only' => ['type' => 'boolean'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20],
                ],
                'additionalProperties' => false,
            ],
            'writes' => false,
        ],
        'confirmar_accion' => [
            'permiso' => 'ia.asistente.use',
            'description' => 'Execute a previously proposed action using its action_id. Call ONLY after explicit user confirmation in their latest message.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action_id' => ['type' => 'string', 'pattern' => '^[0-9A-HJKMNP-TV-Z]{26}$'],
                ],
                'required' => ['action_id'],
                'additionalProperties' => false,
            ],
            'writes' => true,
        ],
        'cancelar_accion' => [
            'permiso' => 'ia.asistente.use',
            'description' => 'Cancel a previously proposed pending action.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'action_id' => ['type' => 'string'],
                ],
                'required' => ['action_id'],
                'additionalProperties' => false,
            ],
            'writes' => false,
        ],
    ];

    /**
     * Builds list of tool definitions allowed for the given principal.
     */
    public static function definiciones(Principal $p): array
    {
        $out = [];
        $isUnitAdmin = $p->isUnitAdmin();

        foreach (self::HERRAMIENTAS as $nombre => $def) {
            // Safeguard D-25: UnitAdmin is strictly blocked from expenses & invoice manage
            if ($isUnitAdmin && in_array($nombre, ['registrar_gastos', 'consultar_gastos', 'preparar_factura_desde_adjunto'], true)) {
                continue;
            }

            if ($nombre === 'consultar_contactos_unidad' && !$p->isSystemAdmin()) {
                continue;
            }

            $perm = $def['permiso'];
            $allowed = is_array($perm) ? $p->canAny($perm) : $p->can($perm);

            if ($allowed) {
                $schema = $def['input_schema'];
                if (empty($schema['properties'])) {
                    $schema['properties'] = (object) [];
                }
                $out[] = [
                    'name' => $nombre,
                    'description' => $def['description'],
                    'input_schema' => $schema,
                ];
            }
        }

        return $out;
    }

    /**
     * Executes a tool, logs execution to tec_ia_tool_logs, and returns result array.
     */
    public function ejecutar(string $nombre, array $input, Principal $p, ?int $conversacionId = null): array
    {
        $t0 = microtime(true);
        $def = self::HERRAMIENTAS[$nombre] ?? null;

        if (!$def) {
            throw new ValidationException("Unknown tool: {$nombre}");
        }

        // Safeguard D-25
        if ($p->isUnitAdmin() && in_array($nombre, ['registrar_gastos', 'consultar_gastos', 'preparar_factura_desde_adjunto'], true)) {
            throw new ForbiddenException("Access to expense operations is restricted to PGS staff.");
        }

        if ($nombre === 'consultar_contactos_unidad' && !$p->isSystemAdmin()) {
            throw new ForbiddenException("Access to contacts is restricted to system administrators.");
        }

        $perm = $def['permiso'];
        $hasPerm = is_array($perm) ? $p->canAny($perm) : $p->can($perm);
        if (!$hasPerm) {
            throw new ForbiddenException("Missing required permission for tool: {$nombre}");
        }

        $pendingActionId = null;
        $exito = true;
        $errorMsg = null;
        $result = [];

        try {
            $result = $this->dispatchHandler($nombre, $input, $p, $conversacionId, $pendingActionId);
        } catch (\Throwable $e) {
            $exito = false;
            $errorMsg = mb_substr($e->getMessage(), 0, 500);
            throw $e;
        } finally {
            $duracionMs = (int) round((microtime(true) - $t0) * 1000);
            $filas = is_array($result) ? count($result) : 1;

            // Redact possible secrets from input
            $safeInput = $input;
            if (isset($safeInput['pin'])) {
                $safeInput['pin'] = '******';
            }

            try {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO tec_ia_tool_logs
                     (user_id, conversacion_id, tool_nombre, tool_input, filas_devueltas, duracion_ms, exito, error, pending_action_id, dt_create)
                     VALUES (:uid, :cid, :nombre, :input, :filas, :dur, :exito, :err, :paid, NOW())"
                );
                $stmt->execute([
                    ':uid' => (int) ($p->id() ?? 0),
                    ':cid' => $conversacionId,
                    ':nombre' => $nombre,
                    ':input' => Json::encode($safeInput),
                    ':filas' => $filas,
                    ':dur' => $duracionMs,
                    ':exito' => $exito ? 1 : 0,
                    ':err' => $errorMsg,
                    ':paid' => $pendingActionId,
                ]);
            } catch (\Throwable $logEx) {
                // Do not fail tool execution if logging fails
                error_log("Failed to log IA tool execution: " . $logEx->getMessage());
            }
        }

        return $result;
    }

    private function dispatchHandler(string $name, array $in, Principal $p, ?int $cid, ?int &$pendingActionId): array
    {
        $pendingSvc = new PendingActionService($this->pdo);

        return match ($name) {
            'buscar_unidades' => $this->handleBuscarUnidades($p, $in),
            'consultar_tareas' => $this->handleConsultarTareas($p, $in),
            'ver_tarea' => $this->handleVerTarea($p, $in),
            'proponer_tarea' => $this->handleProponerTarea($p, $in, $cid, $pendingSvc, $pendingActionId),
            'proponer_cambio_estado_tarea' => $this->handleProponerCambioEstado($p, $in, $cid, $pendingSvc, $pendingActionId),
            'proponer_asignacion_tarea' => $this->handleProponerAsignacion($p, $in, $cid, $pendingSvc, $pendingActionId),
            'consultar_servicios' => $this->handleConsultarServicios($p, $in),
            'proponer_solicitud_servicio' => $this->handleProponerSolicitudServicio($p, $in, $cid, $pendingSvc, $pendingActionId),
            'consultar_trabajadores' => $this->handleConsultarTrabajadores($p, $in),
            'consultar_disponibilidad' => $this->handleConsultarDisponibilidad($p, $in),
            'proponer_reserva' => $this->handleProponerReserva($p, $in, $cid, $pendingSvc, $pendingActionId),
            'consultar_dias_libres' => $this->handleConsultarDiasLibres($p, $in),
            'proponer_ausencia' => $this->handleProponerAusencia($p, $in, $cid, $pendingSvc, $pendingActionId),
            'registrar_gastos' => $this->handleRegistrarGastos($p, $in, $cid, $pendingSvc, $pendingActionId),
            'consultar_gastos' => $this->handleConsultarGastos($p, $in),
            'consultar_facturas' => $this->handleConsultarFacturas($p, $in),
            'preparar_factura_desde_adjunto' => $this->handlePrepararFacturaAdjunto($p, $in, $cid, $pendingSvc, $pendingActionId),
            'consultar_contactos_unidad' => $this->handleConsultarContactos($p, $in),
            'catalogo_reportes' => $this->handleCatalogoReportes($p),
            'proponer_envio_reporte' => $this->handleProponerEnvioReporte($p, $in, $cid, $pendingSvc, $pendingActionId),
            'consultar_notificaciones' => $this->handleConsultarNotificaciones($p, $in),
            'confirmar_accion' => $this->handleConfirmarAccion($p, $in, $cid, $pendingSvc),
            'cancelar_accion' => $this->handleCancelarAccion($p, $in, $pendingSvc),
            default => throw new ValidationException("No handler implemented for tool: {$name}"),
        };
    }

    private function handleBuscarUnidades(Principal $p, array $in): array
    {
        $q = trim((string) ($in['q'] ?? ''));
        $where = ["enable IN ('si', 'yes')"];
        $params = [];

        if (!$p->isSystemAdmin()) {
            $scope = UnitScope::forUser($this->pdo, (int) $p->id(), $p->userType);
            if (empty($scope)) {
                return [];
            }
            $inSql = implode(',', array_map('intval', $scope));
            $where[] = "id IN ({$inSql})";
        }

        if ($q !== '') {
            $where[] = "nombre LIKE :q";
            $params[':q'] = "%{$q}%";
        }

        $stmt = $this->pdo->prepare("SELECT id, nombre, ubicacion FROM tbl_unidades WHERE " . implode(' AND ', $where) . " ORDER BY nombre ASC LIMIT 20");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'units' => array_map(fn($r) => [
                'id' => (int) $r['id'],
                'name' => (string) $r['nombre'],
                'address' => (string) ($r['ubicacion'] ?? ''),
            ], $rows),
        ];
    }

    private function handleConsultarTareas(Principal $p, array $in): array
    {
        $svc = new TaskService($this->pdo);
        $limit = isset($in['limit']) ? (int) $in['limit'] : 10;
        $tasks = $svc->list($p, $in, 1, $limit);
        $rows = $tasks['rows'] ?? $tasks['items'] ?? [];

        return [
            'tasks' => array_map(static fn (array $r) => [
                'code' => $r['code'],
                'title' => $r['title'],
                'status' => $r['status'],
                'priority' => $r['priority'],
                'property' => $r['unidad_nombre'] ?? null,
                'assignee' => $r['assignee_nombre'] ?? null,
                'required_date' => $r['required_date'],
                'due_date' => $r['due_date'],
                'created_at' => $r['created_at'],
            ], $rows),
            'total' => $tasks['total'] ?? count($rows),
        ];
    }

    private function handleVerTarea(Principal $p, array $in): array
    {
        $svc = new TaskService($this->pdo);
        $code = trim((string) $in['code']);
        if (preg_match('/^[A-Z]{3}-\d{4}-\d{4,6}$/', $code)) {
            return $svc->get($p, $code);
        }
        return $svc->get($p, (int) $code);
    }

    private function handleProponerTarea(Principal $p, array $in, ?int $cid, PendingActionService $svc, ?int &$paid): array
    {
        $unitId = (int) ($in['unit_id'] ?? 0);
        UnitScope::assertUnit($p, $unitId);
        if (!\Pgs\Auth\Policy\TaskPolicy::canCreate($p, $unitId)) {
            throw new ForbiddenException('Cannot create tasks in this property.');
        }

        $unitName = $this->getUnitName($unitId);
        $title = trim((string) ($in['title'] ?? ''));
        $priority = (string) ($in['priority'] ?? 'normal');
        $summary = "Create {$priority} task '{$title}' for property {$unitName}";

        $payload = [
            'unidad_id' => $unitId,
            'title' => $title,
            'description' => $in['description'] ?? null,
            'priority' => $priority,
            'required_date' => $in['required_date'] ?? null,
            'due_date' => $in['due_date'] ?? null,
            'location_label' => $in['location_label'] ?? null,
            'origin' => 'assistant',
            'attachment_id' => $in['attachment_id'] ?? null,
        ];

        $created = $svc->create($p, 'assistant', 'task.create', $payload, $summary, $cid, null);
        $paid = $this->lookupPendingActionId($created['action_id']);

        return [
            'action_id' => $created['action_id'],
            'summary' => $summary,
            'proposed_summary' => $summary,
            'status' => 'pending_confirmation',
            'requires_confirmation' => true,
        ];
    }
    private function handleProponerCambioEstado(Principal $p, array $in, ?int $cid, PendingActionService $svc, ?int &$paid): array
    {
        $code = trim((string) $in['code']);
        $newStatus = trim((string) $in['status']);
        $task = $this->handleVerTarea($p, ['code' => $code]);

        $summary = "Change status of task {$task['code']} ('{$task['title']}') to '{$newStatus}'";
        $payload = array_merge($in, ['task_id' => (int) $task['id']]);

        $created = $svc->create($p, 'assistant', 'task.status', $payload, $summary, $cid, null);
        $paid = $this->lookupPendingActionId($created['action_id']);

        return [
            'action_id' => $created['action_id'],
            'proposed_summary' => $summary,
            'requires_confirmation' => true,
        ];
    }

    private function handleProponerAsignacion(Principal $p, array $in, ?int $cid, PendingActionService $svc, ?int &$paid): array
    {
        $code = trim((string) $in['code']);
        $task = $this->handleVerTarea($p, ['code' => $code]);
        $empId = (int) $in['employee_id'];

        $eStmt = $this->pdo->prepare("SELECT nombre FROM tec_employee WHERE id = :id LIMIT 1");
        $eStmt->execute([':id' => $empId]);
        $empName = (string) ($eStmt->fetchColumn() ?: "Worker #{$empId}");

        $summary = "Assign task {$task['code']} to worker {$empName}";
        $payload = array_merge($in, ['task_id' => (int) $task['id']]);

        $created = $svc->create($p, 'assistant', 'task.assign', $payload, $summary, $cid, null);
        $paid = $this->lookupPendingActionId($created['action_id']);

        return [
            'action_id' => $created['action_id'],
            'proposed_summary' => $summary,
            'requires_confirmation' => true,
        ];
    }
    private function handleConsultarServicios(Principal $p, array $in): array
    {
        $svc = new \Pgs\Services\ServiceCatalogService();
        $filters = [
            'search' => trim((string) ($in['q'] ?? '')),
            'active' => 1,
        ];
        $rows = $svc->list($p, $filters);
        return [
            'services' => array_map(fn($r) => [
                'id' => (int) $r['id'],
                'code' => (string) ($r['code'] ?? ''),
                'name_es' => (string) ($r['name_es'] ?? ''),
                'name_en' => (string) ($r['name_en'] ?? ''),
                'lead_time_days' => (int) ($r['lead_time_days'] ?? 0),
                'price_amount' => (float) ($r['price_amount'] ?? 0.0),
                'is_public' => (bool) ($r['is_public'] ?? false),
            ], $rows),
        ];
    }

    private function handleProponerSolicitudServicio(Principal $p, array $in, ?int $cid, PendingActionService $svc, ?int &$paid): array
    {
        $unitId = (int) ($in['unit_id'] ?? 0);
        UnitScope::assertUnit($p, $unitId);

        $unitName = $this->getUnitName($unitId);
        $svcStmt = $this->pdo->prepare("SELECT name_es, name_en FROM tec_services WHERE id = :id AND is_active = 1 AND deleted_at IS NULL LIMIT 1");
        $svcStmt->execute([':id' => (int) $in['service_id']]);
        $service = $svcStmt->fetch(\PDO::FETCH_ASSOC);
        if (!$service) {
            throw new NotFoundException('Service not found or inactive.');
        }
        $svcName = $service['name_en'] ?: $service['name_es'];

        $summary = "Request service '{$svcName}' for property {$unitName} on {$in['required_date']}";
        $payload = [
            'unidad_id' => $unitId,
            'service_id' => (int) $in['service_id'],
            'required_date' => $in['required_date'] ?? null,
            'priority' => $in['priority'] ?? 'normal',
            'notes' => $in['notes'] ?? null,
            'location_label' => $in['location_label'] ?? null,
            'channel' => 'assistant',
        ];
        $created = $svc->create($p, 'assistant', 'service.request', $payload, $summary, $cid, null);
        $paid = $this->lookupPendingActionId($created['action_id']);

        return [
            'action_id' => $created['action_id'],
            'proposed_summary' => $summary,
            'requires_confirmation' => true,
        ];
    }
    private function handleConsultarTrabajadores(Principal $p, array $in): array
    {
        $unitId = (int) $in['unit_id'];
        UnitScope::assertUnit($p, $unitId);

        $rows = (new AvailabilityService($this->pdo))->workersForUnit($p, $unitId);

        return array_map(static fn (array $r) => [
            'employee_id' => (int) $r['id'],
            'first_name' => explode(' ', trim((string) $r['nombre']))[0],
        ], $rows);
    }

    private function handleConsultarDisponibilidad(Principal $p, array $in): array
    {
        $svc = new AvailabilityService($this->pdo);
        return $svc->forEmployee(
            $p,
            (int) $in['employee_id'],
            (int) $in['unit_id'],
            (string) $in['from'],
            (string) $in['to']
        );
    }

    private function handleProponerReserva(Principal $p, array $in, ?int $cid, PendingActionService $svc, ?int &$paid): array
    {
        $empId = (int) $in['employee_id'];
        $unitId = (int) $in['unit_id'];
        UnitScope::assertUnit($p, $unitId);

        $dates = (array) $in['dates'];
        $dayPart = (string) ($in['day_part'] ?? 'full');

        $availSvc = new AvailabilityService($this->pdo);
        $unavailable = [];
        foreach ($dates as $d) {
            if (empty(AvailabilityService::isFree($this->pdo, $empId, $unitId, (string) $d, $dayPart)['free'])) {
                $unavailable[] = $d;
            }
        }

        if (!empty($unavailable)) {
            $alternatives = AvailabilityService::alternatives($this->pdo, $empId, $unitId, (string) $unavailable[0], $dayPart, 5);
            throw new ConflictException("Dates unavailable: " . implode(', ', $unavailable), 'slot_unavailable', [
                'unavailable_dates' => $unavailable,
                'alternatives' => $alternatives,
            ]);
        }

        $unitName = $this->getUnitName($unitId);
        $datesText = implode(', ', $dates);
        $summary = "Reserve worker #{$empId} for property {$unitName} on {$datesText} ({$dayPart} day)";

        $created = $svc->create($p, 'assistant', 'reservation.create', $in, $summary, $cid, null);
        $paid = $this->lookupPendingActionId($created['action_id']);

        return [
            'action_id' => $created['action_id'],
            'proposed_summary' => $summary,
            'requires_confirmation' => true,
        ];
    }

    private function handleConsultarDiasLibres(Principal $p, array $in): array
    {
        $svc = new LeaveBalanceService($this->pdo);
        $empId = isset($in['employee_id']) && $p->can('disponibilidad.absences.approve')
            ? (int) $in['employee_id']
            : (int) ($p->employeeId ?? 0);

        $year = isset($in['year']) ? (int) $in['year'] : (int) date('Y');
        return LeaveBalanceService::balance($this->pdo, $empId, $year);
    }

    private function handleProponerAusencia(Principal $p, array $in, ?int $cid, PendingActionService $svc, ?int &$paid): array
    {
        $empId = (int) ($p->employeeId ?? 0);
        if ($empId <= 0) {
            throw new ValidationException('Current user is not linked to an employee profile.');
        }

        $dateFrom = (string) $in['date_from'];
        $dateTo = (string) $in['date_to'];
        $reasonType = (string) $in['reason_type'];

        $counter = new LeaveDayCounter();
        $counted = $counter->count($dateFrom, $dateTo);
        $workingDays = $counted['days'];

        $balSvc = new LeaveBalanceService($this->pdo);
        $year = (int) substr($dateFrom, 0, 4);
        $bal = LeaveBalanceService::balance($this->pdo, $empId, $year);

        $deducts = in_array($reasonType, ['vacation', 'personal'], true);
        if ($deducts && $bal['available'] < $workingDays) {
            throw new ValidationException("Insufficient leave balance. Requested {$workingDays} days, available: {$bal['available']}.");
        }

        $remAfter = $deducts ? ($bal['available'] - $workingDays) : $bal['available'];
        $summary = "Request {$workingDays} working days off ({$dateFrom} to {$dateTo}, reason: {$reasonType}). Remaining balance after: {$remAfter} days.";

        $payload = array_merge($in, ['employee_id' => $empId, 'working_days' => $workingDays]);
        $created = $svc->create($p, 'assistant', 'absence.request', $payload, $summary, $cid, null);
        $paid = $this->lookupPendingActionId($created['action_id']);

        return [
            'action_id' => $created['action_id'],
            'proposed_summary' => $summary,
            'working_days' => $workingDays,
            'remaining_balance_after' => $remAfter,
            'requires_confirmation' => true,
        ];
    }

    private function handleRegistrarGastos(Principal $p, array $in, ?int $cid, PendingActionService $svc, ?int &$paid): array
    {
        $draft = (new ExpenseService($this->pdo))->validateDraft($p, $in);
        $norm = $draft['normalized'];
        $unitName = $this->getUnitName((int) $norm['unidad_id']);
        $itemCount = count($draft['lines']);
        $total = number_format((float) $draft['total'], 2, '.', '');
        $summary = "Register expense of \${$total} for property {$unitName} ({$itemCount} items)";

        $payload = [
            'unidad_id' => (int) $norm['unidad_id'],
            'location_label' => $norm['location_label'],
            'purchase_date' => $norm['purchase_date'],
            'vendor' => $norm['vendor'],
            'receipt_number' => $norm['receipt_number'],
            'notes' => $norm['notes'],
            'items' => array_map(static fn (array $l) => [
                'description' => $l['description'],
                'quantity' => $l['quantity'],
                'unit_price' => $l['unit_price'],
            ], $draft['lines']),
            'attachment_id' => $in['attachment_id'] ?? null,
        ];

        $created = $svc->create($p, 'assistant', 'expense.create', $payload, $summary, $cid, null);
        $paid = $this->lookupPendingActionId($created['action_id']);

        return [
            'action_id' => $created['action_id'],
            'lines' => $draft['lines'],
            'total_amount' => $draft['total'],
            'items_count' => $itemCount,
            'proposed_summary' => $summary,
            'requires_confirmation' => true,
        ];
    }
    private function handleConsultarGastos(Principal $p, array $in): array
    {
        $limit = isset($in['limit']) ? max(1, min((int) $in['limit'], 50)) : 20;
        $filters = array_filter([
            'unidad_id' => $in['unit_id'] ?? null,
            'billing_status' => $in['billing_status'] ?? null,
            'from_date' => $in['from'] ?? null,
            'to_date' => $in['to'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        $svc = new ExpenseService($this->pdo);
        $res = $svc->list($p, $filters, 1, $limit);
        $out = ['expenses' => array_map(static fn (array $r) => [
            'code' => $r['code'],
            'purchase_date' => $r['purchase_date'],
            'property' => $r['unidad_nombre'],
            'location' => $r['location_label'],
            'vendor' => $r['vendor'],
            'total' => (float) $r['total_amount'],
            'billing_status' => $r['billing_status'],
        ], $res['items'])];

        if (!empty($in['totals']) && ($p->isSystemAdmin() || $p->can('gastos.expenses.totals'))) {
            $out['totals'] = $svc->totals($p, $filters);
        }

        return $out;
    }
    private function handleConsultarFacturas(Principal $p, array $in): array
    {
        $limit = isset($in['limit']) ? max(1, min((int) $in['limit'], 20)) : 10;
        $filters = array_filter([
            'unidad_id' => $in['unit_id'] ?? null,
            'payment_status' => $in['status'] ?? null,
            'from_date' => $in['from'] ?? null,
            'to_date' => $in['to'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        $res = (new InvoiceService($this->pdo))->listForPrincipal($p, $filters, 1, $limit);
        $rows = $res['items'] ?? $res['rows'] ?? [];
        AuditLogger::log($p, 'invoice.viewed', 'invoice', null, null, ['count' => count($rows), 'channel' => 'assistant']);

        return ['invoices' => array_map(static fn (array $r) => [
            'id' => (int) $r['id'],
            'invoice_number' => $r['invoice_number'],
            'property' => $r['unidad_nombre'] ?? null,
            'issue_date' => $r['issue_date'],
            'due_date' => $r['due_date'],
            'amount' => (float) $r['amount'],
            'amount_paid' => (float) $r['amount_paid'],
            'payment_status' => $r['payment_status'],
        ], $rows)];
    }
    private function handlePrepararFacturaAdjunto(Principal $p, array $in, ?int $cid, PendingActionService $svc, ?int &$paid): array
    {
        $unitId = (int) $in['unit_id'];
        UnitScope::assertUnit($p, $unitId);

        $unitName = $this->getUnitName($unitId);
        $num = trim((string) $in['invoice_number']);
        $amount = (float) $in['amount'];

        $summary = "Create invoice {$num} for property {$unitName} (\${$amount}) from attachment";
        $created = $svc->create($p, 'assistant', 'invoice.create_from_attachment', $in, $summary, $cid, null);
        $paid = $this->lookupPendingActionId($created['action_id']);

        return [
            'action_id' => $created['action_id'],
            'proposed_summary' => $summary,
            'requires_confirmation' => true,
        ];
    }

    private function handleConsultarContactos(Principal $p, array $in): array
    {
        $unitId = (int) $in['unit_id'];
        $stmt = $this->pdo->prepare("SELECT contact_role, name, phone_raw, phone_e164 FROM tec_unit_contacts WHERE unidad_id = :uid");
        $stmt->execute([':uid' => $unitId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn($r) => [
            'role' => (string) $r['contact_role'],
            'name' => (string) ($r['name'] ?? 'Contact'),
            'phone_masked' => Phone::mask((string) ($r['phone_e164'] ?: $r['phone_raw'])),
        ], $rows);
    }

    private function handleCatalogoReportes(Principal $p): array
    {
        return ['reports' => ReportCatalog::forPrincipal($p)];
    }

    private function handleProponerEnvioReporte(Principal $p, array $in, ?int $cid, PendingActionService $svc, ?int &$paid): array
    {
        $unitId = (int) $in['unit_id'];
        UnitScope::assertUnit($p, $unitId);

        $repKey = (string) $in['report_key'];
        $allowed = array_map(static fn ($r) => (string) ($r['key'] ?? ''), ReportCatalog::forPrincipal($p));
        if (!in_array($repKey, $allowed, true)) {
            throw new ForbiddenException('This report is not available for your role.');
        }

        $channel = (string) $in['channel'];
        if (!in_array($channel, ['whatsapp', 'email'], true)) {
            throw new ValidationException('Channel must be whatsapp or email.');
        }

        $uStmt = $this->pdo->prepare('SELECT nickname, notify_email, phone_e164, whatsapp_opt_in FROM tec_usuarios WHERE id = :id');
        $uStmt->execute([':id' => (int) $p->id()]);
        $user = $uStmt->fetch(\PDO::FETCH_ASSOC) ?: [];

        if ($channel === 'whatsapp') {
            if (empty($user['phone_e164']) || (int) ($user['whatsapp_opt_in'] ?? 0) !== 1) {
                throw new ValidationException('Your profile has no phone number with WhatsApp consent. Update it in My Profile or choose email.');
            }
            $destMasked = Phone::mask((string) $user['phone_e164']);
        } else {
            $email = trim((string) (!empty($user['notify_email']) ? $user['notify_email'] : ($user['nickname'] ?? '')));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new ValidationException('Your profile has no valid notification email.');
            }
            [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
            $destMasked = substr($local, 0, 2) . '***@' . $domain;
        }

        $unitName = $this->getUnitName($unitId);
        $summary = "Generate and send {$repKey} report for property {$unitName} via {$channel} to {$destMasked}";
        $created = $svc->create($p, 'assistant', 'report.send', $in, $summary, $cid, null);
        $paid = $this->lookupPendingActionId($created['action_id']);

        return [
            'action_id' => $created['action_id'],
            'destination_masked' => $destMasked,
            'summary' => $summary,
            'proposed_summary' => $summary,
            'status' => 'pending_confirmation',
            'requires_confirmation' => true,
        ];
    }
    private function handleConsultarNotificaciones(Principal $p, array $in): array
    {
        $unreadOnly = !empty($in['unread_only']);
        $limit = isset($in['limit']) ? min((int) $in['limit'], 20) : 10;

        $where = ["user_id = :uid"];
        $params = [':uid' => (int) ($p->id() ?? 0)];

        if ($unreadOnly) {
            $where[] = "read_at IS NULL";
        }

        $stmt = $this->pdo->prepare("SELECT id, title, body, created_at, read_at FROM tec_notifications WHERE " . implode(' AND ', $where) . " ORDER BY id DESC LIMIT {$limit}");
        $stmt->execute($params);
        return ['notifications' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
    }

    private function handleConfirmarAccion(Principal $p, array $in, ?int $cid, PendingActionService $svc): array
    {
        $actionId = trim((string) $in['action_id']);
        $conf = $svc->confirm($p, $actionId, $cid, null);
        return [
            'status' => 'confirmed',
            'action_id' => $actionId,
            'code' => $conf['result_ref'] ?? '',
            'result' => $conf['result'] ?? [],
        ];
    }

    private function handleCancelarAccion(Principal $p, array $in, PendingActionService $svc): array
    {
        $actionId = trim((string) $in['action_id']);
        $svc->cancel($p, $actionId);
        return ['cancelled' => true, 'action_id' => $actionId];
    }

    private function getUnitName(int $unitId): string
    {
        $stmt = $this->pdo->prepare("SELECT nombre FROM tbl_unidades WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $unitId]);
        return (string) ($stmt->fetchColumn() ?: "Unit #{$unitId}");
    }

    private function lookupPendingActionId(string $publicId): ?int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM tec_pending_actions WHERE public_id = :pid LIMIT 1");
        $stmt->execute([':pid' => $publicId]);
        $val = $stmt->fetchColumn();
        return $val ? (int) $val : null;
    }
}
