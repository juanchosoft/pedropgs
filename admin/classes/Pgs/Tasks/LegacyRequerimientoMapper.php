<?php

declare(strict_types=1);

namespace Pgs\Tasks;

final class LegacyRequerimientoMapper
{
    /**
     * @return array{0:string, 1:bool} [priority, needsReview]
     */
    public static function priority(?string $raw): array
    {
        if ($raw === null) {
            return ['normal', false];
        }
        $val = mb_strtolower(trim($raw));
        $val = strtr($val, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        ]);

        return match ($val) {
            'alta', 'high' => ['high', false],
            'urgente', 'urgent' => ['urgent', false],
            'baja', 'low' => ['low', false],
            'media', 'normal', 'medium', '' => ['normal', false],
            default => ['normal', true],
        };
    }

    /**
     * @return array{0:string, 1:bool} [status, needsReview]
     */
    public static function status(?string $raw): array
    {
        if ($raw === null) {
            return ['pending', false];
        }
        $val = mb_strtolower(trim($raw));
        $val = strtr($val, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        ]);

        return match ($val) {
            'pendiente', 'activo', 'nuevo', '' => ['pending', false],
            'asignado' => ['assigned', false],
            'en proceso', 'proceso' => ['in_progress', false],
            'terminado', 'completado', 'finalizado' => ['completed', false],
            'cancelado' => ['cancelled', false],
            default => ['pending', true],
        };
    }

    public static function title(?string $detalles, ?string $zona): string
    {
        $d = trim((string) $detalles);
        if ($d !== '') {
            return mb_substr($d, 0, 120);
        }
        $z = trim((string) $zona);
        return $z !== '' ? "Request - $z" : "Request";
    }

    public static function description(?string $zona, ?string $detalles, ?string $nombre, ?string $administrador): string
    {
        $lines = [];
        if (!empty($zona)) {
            $lines[] = "Zone: " . trim((string) $zona);
        }
        if (!empty($nombre)) {
            $lines[] = "Requested by: " . trim((string) $nombre);
        }
        if (!empty($administrador)) {
            $lines[] = "Manager: " . trim((string) $administrador);
        }
        if (!empty($detalles)) {
            if ($lines !== []) {
                $lines[] = '';
            }
            $lines[] = trim((string) $detalles);
        }
        return implode("\n", $lines);
    }
}
