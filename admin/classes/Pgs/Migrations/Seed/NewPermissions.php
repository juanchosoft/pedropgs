<?php

declare(strict_types=1);

namespace Pgs\Migrations\Seed;

final class NewPermissions
{
    /**
     * @return list<array{key:string,module:string,action:string,name:string,legacy_id:null}>
     */
    public static function definitions(): array
    {
        return [
            ['key' => 'tareas.tasks.view', 'module' => 'tareas', 'action' => 'view', 'name' => 'Tasks - View', 'legacy_id' => null],
            ['key' => 'tareas.tasks.view_all', 'module' => 'tareas', 'action' => 'view_all', 'name' => 'Tasks - View All Units', 'legacy_id' => null],
            ['key' => 'tareas.tasks.create', 'module' => 'tareas', 'action' => 'create', 'name' => 'Tasks - Create', 'legacy_id' => null],
            ['key' => 'tareas.tasks.update', 'module' => 'tareas', 'action' => 'update', 'name' => 'Tasks - Edit', 'legacy_id' => null],
            ['key' => 'tareas.tasks.assign', 'module' => 'tareas', 'action' => 'assign', 'name' => 'Tasks - Assign', 'legacy_id' => null],
            ['key' => 'tareas.tasks.status', 'module' => 'tareas', 'action' => 'status', 'name' => 'Tasks - Change Status', 'legacy_id' => null],
            ['key' => 'tareas.tasks.delete', 'module' => 'tareas', 'action' => 'delete', 'name' => 'Tasks - Delete', 'legacy_id' => null],
            ['key' => 'servicios.catalog.view', 'module' => 'servicios', 'action' => 'view', 'name' => 'Services Catalog - View', 'legacy_id' => null],
            ['key' => 'servicios.catalog.manage', 'module' => 'servicios', 'action' => 'manage', 'name' => 'Services Catalog - Manage', 'legacy_id' => null],
            ['key' => 'servicios.requests.create', 'module' => 'servicios', 'action' => 'create', 'name' => 'Service Requests - Create', 'legacy_id' => null],
            ['key' => 'disponibilidad.schedule.view', 'module' => 'disponibilidad', 'action' => 'view', 'name' => 'Worker Schedule - View', 'legacy_id' => null],
            ['key' => 'disponibilidad.schedule.manage', 'module' => 'disponibilidad', 'action' => 'manage', 'name' => 'Worker Schedule - Manage', 'legacy_id' => null],
            ['key' => 'disponibilidad.absences.request', 'module' => 'disponibilidad', 'action' => 'request', 'name' => 'Absences - Request', 'legacy_id' => null],
            ['key' => 'disponibilidad.absences.approve', 'module' => 'disponibilidad', 'action' => 'approve', 'name' => 'Absences - Approve', 'legacy_id' => null],
            ['key' => 'disponibilidad.reservations.create', 'module' => 'disponibilidad', 'action' => 'create', 'name' => 'Reservations - Create', 'legacy_id' => null],
            ['key' => 'disponibilidad.reservations.manage', 'module' => 'disponibilidad', 'action' => 'manage', 'name' => 'Reservations - Manage', 'legacy_id' => null],
            ['key' => 'gastos.expenses.create', 'module' => 'gastos', 'action' => 'create', 'name' => 'Expenses - Register', 'legacy_id' => null],
            ['key' => 'gastos.expenses.view_own', 'module' => 'gastos', 'action' => 'view_own', 'name' => 'Expenses - View Own', 'legacy_id' => null],
            ['key' => 'gastos.expenses.view_all', 'module' => 'gastos', 'action' => 'view_all', 'name' => 'Expenses - View All', 'legacy_id' => null],
            ['key' => 'gastos.expenses.totals', 'module' => 'gastos', 'action' => 'totals', 'name' => 'Expenses - Totals', 'legacy_id' => null],
            ['key' => 'gastos.expenses.update', 'module' => 'gastos', 'action' => 'update', 'name' => 'Expenses - Edit', 'legacy_id' => null],
            ['key' => 'gastos.expenses.void', 'module' => 'gastos', 'action' => 'void', 'name' => 'Expenses - Void', 'legacy_id' => null],
            ['key' => 'gastos.expenses.mark_invoiced', 'module' => 'gastos', 'action' => 'mark_invoiced', 'name' => 'Expenses - Mark Invoiced', 'legacy_id' => null],
            ['key' => 'facturas.invoices.view_own_units', 'module' => 'facturas', 'action' => 'view_own_units', 'name' => 'Invoices - View Own Units', 'legacy_id' => null],
            ['key' => 'facturas.invoices.download', 'module' => 'facturas', 'action' => 'download', 'name' => 'Invoices - Download', 'legacy_id' => null],
            ['key' => 'facturas.invoices.manage', 'module' => 'facturas', 'action' => 'manage', 'name' => 'Invoices - Manage', 'legacy_id' => null],
            ['key' => 'reportes.catalog.view', 'module' => 'reportes', 'action' => 'view', 'name' => 'Reports Catalog - View', 'legacy_id' => null],
            ['key' => 'reportes.catalog.send', 'module' => 'reportes', 'action' => 'send', 'name' => 'Reports Catalog - Send', 'legacy_id' => null],
            ['key' => 'notificaciones.inbox.view', 'module' => 'notificaciones', 'action' => 'view', 'name' => 'Notifications - View', 'legacy_id' => null],
            ['key' => 'notificaciones.prefs.manage', 'module' => 'notificaciones', 'action' => 'manage', 'name' => 'Notifications - Global Preferences', 'legacy_id' => null],
            ['key' => 'ia.asistente.use', 'module' => 'ia', 'action' => 'use', 'name' => 'AI Assistant - Use', 'legacy_id' => null],
            ['key' => 'ia.voz.use', 'module' => 'ia', 'action' => 'voice', 'name' => 'AI Assistant - Voice', 'legacy_id' => null],
            ['key' => 'ia.adjuntos.use', 'module' => 'ia', 'action' => 'attachments', 'name' => 'AI Assistant - Attachments', 'legacy_id' => null],
            ['key' => 'ia.logs.view', 'module' => 'ia', 'action' => 'logs', 'name' => 'AI Logs - View', 'legacy_id' => null],
            ['key' => 'telefonia.calls.view', 'module' => 'telefonia', 'action' => 'view', 'name' => 'Phone Calls - View', 'legacy_id' => null],
            ['key' => 'integraciones.api_tokens.manage', 'module' => 'integraciones', 'action' => 'api_tokens', 'name' => 'API Tokens - Manage', 'legacy_id' => null],
            ['key' => 'integraciones.sync.manage', 'module' => 'integraciones', 'action' => 'sync', 'name' => 'Google Sync - Manage', 'legacy_id' => null],
            ['key' => 'integraciones.whatsapp.manage', 'module' => 'integraciones', 'action' => 'whatsapp', 'name' => 'WhatsApp - Manage', 'legacy_id' => null],
            ['key' => 'sistema.migraciones.run', 'module' => 'sistema', 'action' => 'migrations', 'name' => 'Migrations - Run', 'legacy_id' => null],
            ['key' => 'auditoria.logs.view', 'module' => 'auditoria', 'action' => 'view', 'name' => 'Audit Log - View', 'legacy_id' => null],
        ];
    }
}
