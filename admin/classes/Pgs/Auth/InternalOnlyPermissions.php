<?php

declare(strict_types=1);

namespace Pgs\Auth;

/**
 * Capabilities reserved to PGS staff. A property administrator never receives them,
 * whatever the role assigned to the user says.
 */
final class InternalOnlyPermissions
{
    private const KEYS = [
        'servicios.catalog.manage',
        'disponibilidad.schedule.manage',
        'disponibilidad.absences.approve',
        'disponibilidad.reservations.manage',
        'tareas.tasks.delete',
        'facturas.invoices.manage',
        'auditoria.logs.view',
        'telefonia.calls.view',
        'ia.logs.view',
    ];

    private const PREFIXES = ['gastos.', 'integraciones.', 'sistema.', 'configuracion.', 'empleados.', 'tiempo.', 'clientes.'];

    public static function isInternalOnly(string $key): bool
    {
        if (in_array($key, self::KEYS, true)) {
            return true;
        }
        foreach (self::PREFIXES as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string[] $keys
     * @return string[]
     */
    public static function strip(array $keys): array
    {
        return array_values(array_filter($keys, static fn (string $k) => !self::isInternalOnly($k)));
    }
}
