<?php

declare(strict_types=1);

use Pgs\Core\Config;
use Pgs\Core\Sequence;
use Pgs\Migrations\AbstractMigration;
use Pgs\Migrations\MigrationContext;
use Pgs\Tasks\LegacyRequerimientoMapper;

return new class extends AbstractMigration {
    public function id(): string
    {
        return '2026_10_014_migrate_requerimientos';
    }

    public function description(): string
    {
        return 'Migrate legacy tbl_requerimientos into tec_tasks (batched)';
    }

    public function isBatched(): bool
    {
        return true;
    }

    public function up(\PDO $pdo, MigrationContext $ctx): void
    {
        $batchSize = 200;
        $offsetHours = (int) Config::get('app', 'legacy_utc_offset_hours', -4);

        $stmt = $pdo->prepare(
            "SELECT r.* FROM tbl_requerimientos r
             LEFT JOIN tec_tasks t ON t.legacy_requerimiento_id = r.id
             WHERE t.id IS NULL
             ORDER BY r.id
             LIMIT :lim"
        );
        $stmt->bindValue(':lim', $batchSize, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $count = count($rows);

        if ($count === 0) {
            $ctx->setHasMore(false);
            $totalReq = (int) $pdo->query("SELECT COUNT(*) FROM tbl_requerimientos")->fetchColumn();
            $totalMig = (int) $pdo->query("SELECT COUNT(*) FROM tec_tasks WHERE origin = 'migration'")->fetchColumn();
            $needsRev = (int) $pdo->query("SELECT COUNT(*) FROM tec_tasks WHERE origin = 'migration' AND needs_review = 1")->fetchColumn();
            $ctx->out("Migration 014 complete: tbl_requerimientos=$totalReq, tec_tasks(origin=migration)=$totalMig, needs_review=$needsRev");
            return;
        }

        $unitsMap = [];
        $uStmt = $pdo->query("SELECT id FROM tbl_unidades");
        while ($uid = $uStmt->fetchColumn()) {
            $unitsMap[(int) $uid] = true;
        }

        $insTask = $pdo->prepare(
            "INSERT IGNORE INTO tec_tasks
             (code, origin, unidad_id, location_label, service_id, requested_by_user_id, requested_by_phone,
              requested_by_name, requested_verification, title, description, priority, status, assignee_employee_id,
              required_date, due_date, cancel_reason, completed_at, cancelled_at, due_soon_notified_at,
              legacy_requerimiento_id, legacy_updated_at, needs_review, call_session_id, created_by_user_id,
              created_at, updated_at, row_version)
             VALUES
             (:code, 'migration', :unit, :loc, NULL, NULL, NULL,
              :req_name, 'session', :title, :desc, :priority, :status, NULL,
              NULL, NULL, NULL, :completed_at, :cancelled_at, NULL,
              :legacy_id, :legacy_upd, :needs_rev, NULL, NULL,
              :created_at, :updated_at, 1)"
        );

        $insHist = $pdo->prepare(
            "INSERT INTO tec_task_history (task_id, event, note, actor_kind, channel, created_at)
             VALUES (:tid, 'migrated', :note, 'system', 'migration', :created_at)"
        );

        $warnings = 0;
        foreach ($rows as $r) {
            $reqId = (int) $r['id'];
            $unitId = isset($r['tbl_unidad_id']) && $r['tbl_unidad_id'] !== null ? (int) $r['tbl_unidad_id'] : null;
            $unitValid = $unitId !== null && isset($unitsMap[$unitId]);
            $unitToSave = $unitValid ? $unitId : null;

            [$priority, $priReview] = LegacyRequerimientoMapper::priority($r['prioridad'] ?? null);
            [$status, $statReview] = LegacyRequerimientoMapper::status($r['estado'] ?? null);
            $needsReview = (!$unitValid && $unitId !== null) || $priReview || $statReview ? 1 : 0;
            if ($needsReview === 1) {
                $warnings++;
            }

            $title = LegacyRequerimientoMapper::title($r['detalles'] ?? null, $r['zona'] ?? null);
            $desc = LegacyRequerimientoMapper::description(
                $r['zona'] ?? null,
                $r['detalles'] ?? null,
                $r['nombre'] ?? null,
                $r['administrador'] ?? null
            );

            // Time calculation
            $dtcreate = !empty($r['dtcreate']) ? (string) $r['dtcreate'] : 'now';
            $ts = strtotime($dtcreate);
            if ($ts === false) {
                $ts = time();
            }
            $utcTs = $ts - ($offsetHours * 3600);
            $utcDate = new \DateTimeImmutable('@' . $utcTs);
            $utcSql = $utcDate->format('Y-m-d H:i:s');

            $code = Sequence::code($pdo, 'TSK', $utcDate);
            $completedAt = in_array($status, ['completed'], true) ? $utcSql : null;
            $cancelledAt = in_array($status, ['cancelled'], true) ? $utcSql : null;

            $insTask->execute([
                ':code' => $code,
                ':unit' => $unitToSave,
                ':loc' => !empty($r['zona']) ? mb_substr(trim((string) $r['zona']), 0, 120) : null,
                ':req_name' => !empty($r['nombre']) ? mb_substr(trim((string) $r['nombre']), 0, 150) : null,
                ':title' => $title,
                ':desc' => $desc,
                ':priority' => $priority,
                ':status' => $status,
                ':completed_at' => $completedAt,
                ':cancelled_at' => $cancelledAt,
                ':legacy_id' => $reqId,
                ':legacy_upd' => $utcSql,
                ':needs_rev' => $needsReview,
                ':created_at' => $utcSql,
                ':updated_at' => $utcSql,
            ]);

            $newTaskId = (int) $pdo->lastInsertId();
            if ($newTaskId > 0) {
                $insHist->execute([
                    ':tid' => $newTaskId,
                    ':note' => "Imported from tbl_requerimientos #$reqId",
                    ':created_at' => $utcSql,
                ]);
            }
        }

        $ctx->out("Migrated batch of $count requerimientos ($warnings flagged for review)");
        $ctx->setHasMore($count === $batchSize);
    }

    public function rollbackNotes(): string
    {
        return "DELETE FROM tec_tasks WHERE origin = 'migration';";
    }
};
