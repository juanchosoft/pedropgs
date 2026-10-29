<?php

declare(strict_types=1);

namespace Pgs\Integrations\WhatsApp;

final class TemplateCatalog
{
    /**
     * All 14 Meta WhatsApp Cloud API templates approved for PGS Centrum.
     */
    public const TEMPLATES = [
        'pgs_task_created' => [
            'category' => 'UTILITY',
            'has_header_document' => false,
            'es' => 'Se registró la solicitud {{1}} para {{2}}: {{3}}. Prioridad: {{4}}. Le avisaremos los cambios.',
            'en' => 'Request {{1}} was registered for {{2}}: {{3}}. Priority: {{4}}. We will keep you posted.',
        ],
        'pgs_task_assigned' => [
            'category' => 'UTILITY',
            'has_header_document' => false,
            'es' => 'Se le asignó la tarea {{1}} en {{2}}: {{3}}. Fecha límite: {{4}}. Revísela en la aplicación.',
            'en' => 'Task {{1}} at {{2}} was assigned to you: {{3}}. Due date: {{4}}. Check it in the app.',
        ],
        'pgs_task_status' => [
            'category' => 'UTILITY',
            'has_header_document' => false,
            'es' => 'La tarea {{1}} de {{2}} cambió a estado: {{3}}. Gracias.',
            'en' => 'Task {{1}} at {{2}} changed to: {{3}}. Thank you.',
        ],
        'pgs_task_due_soon' => [
            'category' => 'UTILITY',
            'has_header_document' => false,
            'es' => 'Recordatorio: la tarea {{1}} en {{2}} vence el {{3}}. Por favor actualice su estado.',
            'en' => 'Reminder: task {{1}} at {{2}} is due on {{3}}. Please update its status.',
        ],
        'pgs_service_request' => [
            'category' => 'UTILITY',
            'has_header_document' => false,
            'es' => 'Nueva solicitud de servicio {{1}}: {{2}} para {{3}}, fecha requerida {{4}}. Revísela en la aplicación.',
            'en' => 'New service request {{1}}: {{2}} for {{3}}, required date {{4}}. Review it in the app.',
        ],
        'pgs_reservation_confirmed' => [
            'category' => 'UTILITY',
            'has_header_document' => false,
            'es' => 'Reserva confirmada: {{1}} trabajará en {{2}} el {{3}} ({{4}}). Gracias.',
            'en' => 'Reservation confirmed: {{1}} will work at {{2}} on {{3}} ({{4}}). Thank you.',
        ],
        'pgs_reservation_cancelled' => [
            'category' => 'UTILITY',
            'has_header_document' => false,
            'es' => 'Se canceló la reserva de {{1}} en {{2}} para el {{3}}. Gracias.',
            'en' => 'The reservation of {{1}} at {{2}} on {{3}} was cancelled. Thank you.',
        ],
        'pgs_absence_requested' => [
            'category' => 'UTILITY',
            'has_header_document' => false,
            'es' => 'Solicitud de días libres de {{1}} del {{2}} al {{3}} ({{4}} días). Apruébela o recházela en la aplicación.',
            'en' => 'Time off request from {{1}} from {{2}} to {{3}} ({{4}} days). Approve or reject it in the app.',
        ],
        'pgs_absence_decision' => [
            'category' => 'UTILITY',
            'has_header_document' => false,
            'es' => 'Su solicitud de días libres del {{1}} al {{2}} fue {{3}}. Días disponibles: {{4}}.',
            'en' => 'Your time off request from {{1}} to {{2}} was {{3}}. Days available: {{4}}.',
        ],
        'pgs_invoice_issued' => [
            'category' => 'UTILITY',
            'has_header_document' => true,
            'es' => 'Factura {{1}} para {{2}} por {{3}}, vence el {{4}}. Adjuntamos el documento.',
            'en' => 'Invoice {{1}} for {{2}} for {{3}}, due {{4}}. The document is attached.',
        ],
        'pgs_invoice_overdue' => [
            'category' => 'UTILITY',
            'has_header_document' => false,
            'es' => 'La factura {{1}} de {{2}} por {{3}} está vencida desde el {{4}}. Gracias por su atención.',
            'en' => 'Invoice {{1}} for {{2}} for {{3}} is overdue since {{4}}. Thank you.',
        ],
        'pgs_document_delivery' => [
            'category' => 'UTILITY',
            'has_header_document' => true,
            'es' => 'Adjuntamos {{1}} de {{2}} correspondiente a {{3}}. Gracias.',
            'en' => 'Attached is the {{1}} for {{2}} covering {{3}}. Thank you.',
        ],
        'pgs_phone_message' => [
            'category' => 'UTILITY',
            'has_header_document' => false,
            'es' => 'Mensaje telefónico de {{1}} (devolver a {{2}}): {{3}}. Se registró la tarea {{4}}.',
            'en' => 'Phone message from {{1}} (call back {{2}}): {{3}}. Task {{4}} was created.',
        ],
        'pgs_pin_locked' => [
            'category' => 'UTILITY',
            'has_header_document' => false,
            'es' => 'Por seguridad su PIN de voz quedó bloqueado hasta {{1}}. Si no fue usted, contacte a la administración.',
            'en' => 'For security your voice PIN is locked until {{1}}. If this wasn\'t you, contact the office.',
        ],
    ];

    public static function all(): array
    {
        return self::TEMPLATES;
    }

    public static function mapParameters(string $templateKey, array $payload, string $lang = 'es'): array
    {
        $eventType = str_starts_with($templateKey, 'pgs_') ? substr($templateKey, 4) : $templateKey;
        $mapped = self::map(str_replace('_', '.', $eventType), $payload, $lang);
        return [
            'template' => $mapped['template'],
            'language' => ['code' => $lang],
            'parameters' => $mapped['body_params'],
        ];
    }

    /**
     * Maps an event type and payload to WhatsApp template and parameters.
     *
     * @param string $eventType e.g. 'task.created'
     * @param array<string, mixed> $payload
     * @param string $lang 'es' or 'en'
     * @return array{template: string, body_params: list<string>, has_document: bool}
     */
    public static function map(string $eventType, array $payload, string $lang = 'es'): array
    {
        $templateKey = 'pgs_' . str_replace('.', '_', $eventType);
        if (!isset(self::TEMPLATES[$templateKey])) {
            $templateKey = 'pgs_document_delivery';
        }

        $meta = self::TEMPLATES[$templateKey];
        $params = [];

        switch ($templateKey) {
            case 'pgs_task_created':
                $params = [
                    (string) ($payload['code'] ?? 'TSK-0000'),
                    (string) ($payload['unit_name'] ?? 'PGS Centrum'),
                    (string) ($payload['title'] ?? 'Task'),
                    (string) ($payload['priority'] ?? 'Normal'),
                ];
                break;

            case 'pgs_task_assigned':
                $params = [
                    (string) ($payload['code'] ?? 'TSK-0000'),
                    (string) ($payload['unit_name'] ?? 'PGS Centrum'),
                    (string) ($payload['title'] ?? 'Task'),
                    (string) ($payload['due_date'] ?? 'N/A'),
                ];
                break;

            case 'pgs_task_status':
                $params = [
                    (string) ($payload['code'] ?? 'TSK-0000'),
                    (string) ($payload['unit_name'] ?? 'PGS Centrum'),
                    (string) ($payload['status'] ?? 'Updated'),
                ];
                break;

            case 'pgs_task_due_soon':
                $params = [
                    (string) ($payload['code'] ?? 'TSK-0000'),
                    (string) ($payload['unit_name'] ?? 'PGS Centrum'),
                    (string) ($payload['due_date'] ?? 'Today'),
                ];
                break;

            case 'pgs_service_request':
                $params = [
                    (string) ($payload['code'] ?? 'REQ-0000'),
                    (string) ($payload['service_name'] ?? 'Service'),
                    (string) ($payload['unit_name'] ?? 'PGS Centrum'),
                    (string) ($payload['required_date'] ?? 'As scheduled'),
                ];
                break;

            case 'pgs_reservation_confirmed':
                $params = [
                    (string) ($payload['worker_name'] ?? 'Staff'),
                    (string) ($payload['unit_name'] ?? 'Property'),
                    (string) ($payload['date'] ?? 'Scheduled date'),
                    (string) ($payload['shift'] ?? 'Full day'),
                ];
                break;

            case 'pgs_reservation_cancelled':
                $params = [
                    (string) ($payload['worker_name'] ?? 'Staff'),
                    (string) ($payload['unit_name'] ?? 'Property'),
                    (string) ($payload['date'] ?? 'Date'),
                ];
                break;

            case 'pgs_absence_requested':
                $params = [
                    (string) ($payload['employee_name'] ?? 'Employee'),
                    (string) ($payload['start_date'] ?? 'Start'),
                    (string) ($payload['end_date'] ?? 'End'),
                    (string) ($payload['days_count'] ?? '1'),
                ];
                break;

            case 'pgs_absence_decision':
                $params = [
                    (string) ($payload['start_date'] ?? 'Start'),
                    (string) ($payload['end_date'] ?? 'End'),
                    (string) ($payload['decision'] ?? 'aprobada'),
                    (string) ($payload['remaining_days'] ?? '0'),
                ];
                break;

            case 'pgs_invoice_issued':
                $params = [
                    (string) ($payload['invoice_number'] ?? 'INV-0000'),
                    (string) ($payload['unit_name'] ?? 'HOA'),
                    (string) ($payload['total_formatted'] ?? '$0.00'),
                    (string) ($payload['due_date'] ?? 'N/A'),
                ];
                break;

            case 'pgs_invoice_overdue':
                $params = [
                    (string) ($payload['invoice_number'] ?? 'INV-0000'),
                    (string) ($payload['unit_name'] ?? 'HOA'),
                    (string) ($payload['total_formatted'] ?? '$0.00'),
                    (string) ($payload['due_date'] ?? 'Past due'),
                ];
                break;

            case 'pgs_document_delivery':
                $params = [
                    (string) ($payload['document_title'] ?? 'Documento'),
                    (string) ($payload['unit_name'] ?? 'PGS Centrum'),
                    (string) ($payload['period'] ?? 'Período actual'),
                ];
                break;

            case 'pgs_phone_message':
                $params = [
                    (string) ($payload['caller_name'] ?? 'Caller'),
                    (string) ($payload['callback_phone'] ?? 'N/A'),
                    (string) ($payload['message'] ?? 'Message'),
                    (string) ($payload['task_code'] ?? 'TSK-0000'),
                ];
                break;

            case 'pgs_pin_locked':
                $params = [
                    (string) ($payload['locked_until'] ?? '30 minutes'),
                ];
                break;

            default:
                $params = [
                    (string) ($payload['title'] ?? 'Notification'),
                    (string) ($payload['unit_name'] ?? 'PGS Centrum'),
                    (string) ($payload['summary'] ?? 'Updated'),
                ];
                break;
        }

        return [
            'template' => $templateKey,
            'body_params' => $params,
            'has_document' => (bool) ($meta['has_header_document'] ?? false),
        ];
    }
}
