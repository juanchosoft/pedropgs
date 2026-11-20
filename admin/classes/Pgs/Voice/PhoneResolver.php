<?php

declare(strict_types=1);

namespace Pgs\Voice;

use Pgs\Core\Db;
use Pgs\Core\Phone;

final class PhoneResolver
{
    private const MAX_CANDIDATES = 5;

    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    /**
     * @return array{status:string,e164:?string,caller_kind:string,caller_name:?string,unidad_id:?int,unit_name:?string,candidates:list<array{id:int,name:string}>}
     */
    public function resolve(?string $raw): array
    {
        $result = [
            'status' => 'hidden',
            'e164' => null,
            'caller_kind' => 'unknown',
            'caller_name' => null,
            'unidad_id' => null,
            'unit_name' => null,
            'candidates' => [],
        ];

        $e164 = $raw !== null ? Phone::toE164($raw, 'US') : null;
        if ($e164 === null) {
            return $result;
        }
        $result['e164'] = $e164;
        $result['status'] = 'none';

        $stmt = $this->pdo->prepare(
            "SELECT d.kind, d.unidad_id, d.employee_id, u.nombre AS unit_name, e.nombre AS employee_name,
                    (SELECT MAX(r.received_at) FROM tec_requests r WHERE r.unidad_id = d.unidad_id) AS last_activity
             FROM tec_phone_directory d
             LEFT JOIN tbl_unidades u ON u.id = d.unidad_id AND u.enable IN ('yes','si')
             LEFT JOIN tec_employee e ON e.id = d.employee_id
             WHERE d.phone_e164 = :phone AND d.is_active = 1
             ORDER BY last_activity DESC, d.unidad_id ASC"
        );
        $stmt->execute([':phone' => $e164]);

        $units = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if ($row['kind'] === 'staff') {
                $result['caller_kind'] = 'staff';
                $result['caller_name'] ??= $row['employee_name'] !== null ? (string) $row['employee_name'] : null;
                continue;
            }
            if ($row['unit_name'] === null || isset($units[(int) $row['unidad_id']])) {
                continue;
            }
            $units[(int) $row['unidad_id']] = (string) $row['unit_name'];
        }

        if (count($units) === 1) {
            $id = array_key_first($units);
            $result['status'] = 'single';
            $result['unidad_id'] = $id;
            $result['unit_name'] = $units[$id];
            if ($result['caller_kind'] === 'unknown') {
                $result['caller_kind'] = 'property';
            }
        } elseif (count($units) > 1) {
            $result['status'] = 'multiple';
            foreach (array_slice($units, 0, self::MAX_CANDIDATES, true) as $id => $name) {
                $result['candidates'][] = ['id' => (int) $id, 'name' => $name];
            }
            if ($result['caller_kind'] === 'unknown') {
                $result['caller_kind'] = 'property';
            }
        }

        return $result;
    }
}
