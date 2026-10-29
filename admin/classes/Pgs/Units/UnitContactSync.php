<?php

declare(strict_types=1);

namespace Pgs\Units;

use Pgs\Core\Clock;
use Pgs\Core\Phone;

final class UnitContactSync
{
    public static function syncUnit($pdoOrUnitId, ?int $unitId = null): void
    {
        if ($pdoOrUnitId instanceof \PDO) {
            $pdo = $pdoOrUnitId;
            $unitId = (int) ($unitId ?? 0);
        } else {
            $pdo = \Pgs\Core\Db::pdo();
            $unitId = (int) $pdoOrUnitId;
        }

        if ($unitId <= 0) {
            return;
        }

        $stmt = $pdo->prepare("SELECT * FROM tbl_unidades WHERE id = :id");
        $stmt->execute([':id' => $unitId]);
        $u = $stmt->fetch();
        if (!$u) {
            return;
        }

        $now = Clock::nowUtcSql();

        $upsertStmt = $pdo->prepare(
            "INSERT INTO tec_unit_contacts
             (unidad_id, contact_role, name, phone_e164, phone_raw, email, receives_reports, receives_notifications, is_active, source, source_field, created_at, updated_at)
             VALUES (:uid, :role, :name, :e164, :raw, :email, :reports, :notifs, :active, 'legacy', :field, :now, :now)
             ON DUPLICATE KEY UPDATE
               contact_role = VALUES(contact_role),
               name = VALUES(name),
               phone_e164 = VALUES(phone_e164),
               phone_raw = VALUES(phone_raw),
               email = VALUES(email),
               receives_reports = VALUES(receives_reports),
               receives_notifications = VALUES(receives_notifications),
               is_active = VALUES(is_active),
               updated_at = VALUES(updated_at)"
        );

        $adminName = !empty($u['administrador']) ? trim((string) $u['administrador']) : null;

        // email (main)
        $email = !empty($u['email']) ? trim((string) $u['email']) : null;
        $upsertStmt->execute([
            ':uid' => $unitId,
            ':role' => 'report_recipient',
            ':name' => $adminName,
            ':e164' => null,
            ':raw' => null,
            ':email' => $email,
            ':reports' => 1,
            ':notifs' => 1,
            ':active' => ($email !== null ? 1 : 0),
            ':field' => 'email',
            ':now' => $now,
        ]);

        // email1..4
        for ($i = 1; $i <= 4; $i++) {
            $key = "email$i";
            $val = !empty($u[$key]) ? trim((string) $u[$key]) : null;
            $upsertStmt->execute([
                ':uid' => $unitId,
                ':role' => 'report_recipient',
                ':name' => null,
                ':e164' => null,
                ':raw' => null,
                ':email' => $val,
                ':reports' => 1,
                ':notifs' => 0,
                ':active' => ($val !== null ? 1 : 0),
                ':field' => $key,
                ':now' => $now,
            ]);
        }

        // celular
        $celular = !empty($u['celular']) ? trim((string) $u['celular']) : null;
        $upsertStmt->execute([
            ':uid' => $unitId,
            ':role' => 'administrator',
            ':name' => $adminName,
            ':e164' => Phone::toE164($celular),
            ':raw' => $celular,
            ':email' => null,
            ':reports' => 0,
            ':notifs' => 1,
            ':active' => ($celular !== null ? 1 : 0),
            ':field' => 'celular',
            ':now' => $now,
        ]);

        // telefono_emergencia
        $emergencia = !empty($u['telefono_emergencia']) ? trim((string) $u['telefono_emergencia']) : null;
        $upsertStmt->execute([
            ':uid' => $unitId,
            ':role' => 'emergency',
            ':name' => null,
            ':e164' => Phone::toE164($emergencia),
            ':raw' => $emergencia,
            ':email' => null,
            ':reports' => 0,
            ':notifs' => 0,
            ':active' => ($emergencia !== null ? 1 : 0),
            ':field' => 'telefono_emergencia',
            ':now' => $now,
        ]);

        // contact1..4
        for ($i = 1; $i <= 4; $i++) {
            $key = "contact$i";
            $val = !empty($u[$key]) ? trim((string) $u[$key]) : null;
            $upsertStmt->execute([
                ':uid' => $unitId,
                ':role' => 'other',
                ':name' => null,
                ':e164' => Phone::toE164($val),
                ':raw' => $val,
                ':email' => null,
                ':reports' => 0,
                ':notifs' => 0,
                ':active' => ($val !== null ? 1 : 0),
                ':field' => $key,
                ':now' => $now,
            ]);
        }
    }

    public static function syncAll(?\PDO $pdo = null): int
    {
        $pdo = $pdo ?? \Pgs\Core\Db::pdo();
        $units = $pdo->query("SELECT id FROM tbl_unidades")->fetchAll();
        $count = 0;
        foreach ($units as $u) {
            self::syncUnit($pdo, (int) $u['id']);
            $count++;
        }
        return $count;
    }
}
