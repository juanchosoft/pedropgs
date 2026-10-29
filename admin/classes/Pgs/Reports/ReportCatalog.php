<?php

declare(strict_types=1);

namespace Pgs\Reports;

use Pgs\Auth\Principal;

final class ReportCatalog
{
    public const REPORTS = [
        'activity_report' => [
            'name' => 'Daily Activity Report',
            'name_es' => 'Reporte Diario de Actividades',
            'desc' => 'Detailed maintenance jobs with before and after photographic evidence.',
            'desc_es' => 'Trabajos de mantenimiento detallados con evidencia fotográfica antes y después.',
            'perm' => 'reportes.catalog.view',
            'pdf' => ActivityReportPdf::class,
        ],
        'tasks_by_unit' => [
            'name' => 'Tasks & Work Orders Report',
            'name_es' => 'Reporte de Tareas y Órdenes de Trabajo',
            'desc' => 'Work orders and tasks breakdown, status counts, and technician assignments.',
            'desc_es' => 'Desglose de órdenes de trabajo, estados y técnicos asignados.',
            'perm' => 'reportes.catalog.view',
            'pdf' => TasksReportPdf::class,
        ],
        'invoices_by_unit' => [
            'name' => 'Billing & Invoices Report',
            'name_es' => 'Reporte de Facturación y Cobros',
            'desc' => 'Summary of invoices, payments received, and outstanding balances.',
            'desc_es' => 'Resumen de facturas emitidas, pagos recibidos y saldo pendiente.',
            'perm' => ['facturas.invoices.view_own_units', 'facturas.invoices.manage'],
            'pdf' => InvoicesReportPdf::class,
        ],
        'expenses_by_unit' => [
            'name' => 'Property Expenses Report (Internal)',
            'name_es' => 'Reporte de Gastos por Conjunto (Interno)',
            'desc' => 'Internal operational expenses and purchases by property. Staff only.',
            'desc_es' => 'Gastos operacionales y compras por propiedad. Exclusivo personal interno.',
            'perm' => 'gastos.expenses.totals',
            'pdf' => ExpensesReportPdf::class,
            'deny_unit_admin' => true,
        ],
    ];

    /**
     * Returns report catalog items accessible to the given Principal.
     *
     * @return array<int, array{key:string, name:string, description:string, deny_unit_admin:bool}>
     */
    public static function forPrincipal(Principal $p): array
    {
        $allowed = [];
        $isEs = ($p->language() === 'es');

        foreach (self::REPORTS as $key => $cfg) {
            if (!empty($cfg['deny_unit_admin']) && $p->isUnitAdmin()) {
                continue;
            }

            $perms = (array) $cfg['perm'];
            if (!$p->hasAnyPermission($perms)) {
                continue;
            }

            $allowed[] = [
                'key' => $key,
                'name' => $isEs ? $cfg['name_es'] : $cfg['name'],
                'description' => $isEs ? $cfg['desc_es'] : $cfg['desc'],
                'deny_unit_admin' => !empty($cfg['deny_unit_admin']),
            ];
        }

        return $allowed;
    }
}
