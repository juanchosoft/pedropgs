<?php

declare(strict_types=1);

namespace Pgs\Services;

use Pgs\Auth\Policy\ServicePolicy;
use Pgs\Auth\Principal;
use Pgs\Auth\UnitScope;
use Pgs\Core\AuditLogger;
use Pgs\Core\Clock;
use Pgs\Core\Config;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\ValidationException;

final class ServiceCatalogService
{
    public function list(Principal $p, array $f): array
    {
        if (!ServicePolicy::canView($p)) {
            throw new ForbiddenException('Cannot view services catalog');
        }

        $pdo = Db::pdo();
        $manager = ServicePolicy::canManage($p);
        $allowed = $manager ? null : array_map('intval', UnitScope::accessibleUnitIds($p));
        $params = [];
        $sql = "SELECT s.*, u.nickname AS default_assignee_name
                FROM tec_services s
                LEFT JOIN tec_usuarios u ON u.id = s.default_assignee_user_id
                WHERE s.deleted_at IS NULL";
        if (!$manager) {
            $sql .= " AND s.is_active = 1 AND s.is_public = 1" . self::applicableSql($allowed, $params);
        }

        if (!empty($f['category'])) {
            $sql .= " AND s.category = :cat";
            $params[':cat'] = trim((string) $f['category']);
        }

        if (isset($f['active']) && $f['active'] !== '') {
            $sql .= " AND s.is_active = :act";
            $params[':act'] = (int) $f['active'];
        }

        if (!empty($f['search'])) {
            $sql .= " AND (s.code LIKE :q OR s.name_en LIKE :q OR s.name_es LIKE :q OR s.category LIKE :q)";
            $params[':q'] = '%' . trim((string) $f['search']) . '%';
        }

        $sql .= " ORDER BY s.sort_order ASC, s.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Attach property unit IDs for each service
        $unitStmt = $pdo->prepare("SELECT unidad_id FROM tec_service_units WHERE service_id = :sid");
        foreach ($rows as &$r) {
            $unitStmt->execute([':sid' => $r['id']]);
            $r['unit_ids'] = array_map('intval', $unitStmt->fetchAll(\PDO::FETCH_COLUMN));
            if (!$manager) {
                $r = self::publicView($r, $allowed);
            }
        }
        unset($r);

        return $rows;
    }

    public function get(Principal $p, int $id): array
    {
        if (!ServicePolicy::canView($p)) {
            throw new ForbiddenException('Cannot view services catalog');
        }

        $pdo = Db::pdo();
        $stmt = $pdo->prepare("SELECT * FROM tec_services WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new NotFoundException('Service not found');
        }

        $unitStmt = $pdo->prepare("SELECT unidad_id FROM tec_service_units WHERE service_id = :sid");
        $unitStmt->execute([':sid' => $id]);
        $row['unit_ids'] = array_map('intval', $unitStmt->fetchAll(\PDO::FETCH_COLUMN));

        if (!ServicePolicy::canManage($p)) {
            $allowed = array_map('intval', UnitScope::accessibleUnitIds($p));
            $applies = $row['unit_ids'] === [] || array_intersect($row['unit_ids'], $allowed) !== [];
            if ((int) $row['is_active'] !== 1 || (int) $row['is_public'] !== 1 || !$applies) {
                throw new NotFoundException('Service not found');
            }
            $row = self::publicView($row, $allowed);
        }

        return $row;
    }

    public function save(Principal $p, array $in): int
    {
        if (!ServicePolicy::canManage($p)) {
            throw new ForbiddenException('Cannot manage services catalog');
        }

        $pdo = Db::pdo();
        $id = isset($in['id']) ? (int) $in['id'] : 0;
        $code = strtoupper(trim((string) ($in['code'] ?? '')));
        $category = trim((string) ($in['category'] ?? ''));
        $nameEn = trim((string) ($in['name_en'] ?? ''));
        $nameEs = trim((string) ($in['name_es'] ?? ''));
        $descEn = isset($in['description_en']) ? trim((string) $in['description_en']) : null;
        $descEs = isset($in['description_es']) ? trim((string) $in['description_es']) : null;
        $condEn = isset($in['conditions_en']) ? trim((string) $in['conditions_en']) : null;
        $condEs = isset($in['conditions_es']) ? trim((string) $in['conditions_es']) : null;
        $priceUnit = trim((string) ($in['price_unit'] ?? 'quote'));
        $currency = strtoupper(trim((string) ($in['currency'] ?? Config::get('app', 'currency', 'USD'))));
        $leadTime = isset($in['lead_time_days']) ? (int) $in['lead_time_days'] : 0;
        $isPublic = !empty($in['is_public']) ? 1 : 0;
        $isActive = isset($in['is_active']) ? (!empty($in['is_active']) ? 1 : 0) : 1;
        $sortOrder = isset($in['sort_order']) ? (int) $in['sort_order'] : 0;
        $defaultAssignee = !empty($in['default_assignee_user_id']) ? (int) $in['default_assignee_user_id'] : null;

        $errors = [];
        if (!preg_match('/^[A-Z0-9_-]{2,30}$/', $code)) {
            $errors['code'] = 'Code must be between 2 and 30 alphanumeric characters (uppercase, numbers, -, _)';
        }
        if (mb_strlen($nameEn) < 2 || mb_strlen($nameEn) > 150) {
            $errors['name_en'] = 'English name must be between 2 and 150 characters';
        }
        if (mb_strlen($nameEs) < 2 || mb_strlen($nameEs) > 150) {
            $errors['name_es'] = 'Spanish name must be between 2 and 150 characters';
        }
        if (mb_strlen($category) < 2 || mb_strlen($category) > 60) {
            $errors['category'] = 'Category must be between 2 and 60 characters';
        }
        if (!in_array($priceUnit, ['fixed', 'hour', 'visit', 'sqft', 'quote'], true)) {
            $errors['price_unit'] = 'Invalid price unit';
        }

        $priceAmount = null;
        if ($priceUnit === 'quote') {
            $priceAmount = null;
        } else {
            if (!isset($in['price_amount']) || $in['price_amount'] === '' || (float) $in['price_amount'] < 0) {
                $errors['price_amount'] = 'Price amount is required and must be >= 0 when price unit is not quote';
            } else {
                $priceAmount = round((float) $in['price_amount'], 2);
            }
        }

        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            $errors['currency'] = 'Currency must be a 3-letter code (e.g. USD)';
        }
        if ($leadTime < 0 || $leadTime > 365) {
            $errors['lead_time_days'] = 'Lead time must be between 0 and 365 days';
        }

        // Check code uniqueness among active records
        $stmtCode = $pdo->prepare("SELECT id FROM tec_services WHERE code = :code AND id <> :id AND deleted_at IS NULL");
        $stmtCode->execute([':code' => $code, ':id' => $id]);
        if ($stmtCode->fetch()) {
            $errors['code'] = 'A service with this code already exists';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }

        $now = Clock::nowUtcSql();
        $unitIds = isset($in['unit_ids']) && is_array($in['unit_ids']) ? array_map('intval', $in['unit_ids']) : [];

        if ($id > 0) {
            // Update
            $before = $this->get($p, $id);
            $stmt = $pdo->prepare(
                "UPDATE tec_services SET
                    code = :code, category = :cat, name_en = :nen, name_es = :nes,
                    description_en = :den, description_es = :des, conditions_en = :cen, conditions_es = :ces,
                    price_amount = :pam, price_unit = :punit, currency = :cur, lead_time_days = :ltd,
                    is_public = :pub, is_active = :act, sort_order = :sort,
                    default_assignee_user_id = :assignee, updated_at = :now
                 WHERE id = :id AND deleted_at IS NULL"
            );
            $stmt->execute([
                ':code' => $code,
                ':cat' => $category,
                ':nen' => $nameEn,
                ':nes' => $nameEs,
                ':den' => $descEn,
                ':des' => $descEs,
                ':cen' => $condEn,
                ':ces' => $condEs,
                ':pam' => $priceAmount,
                ':punit' => $priceUnit,
                ':cur' => $currency,
                ':ltd' => $leadTime,
                ':pub' => $isPublic,
                ':act' => $isActive,
                ':sort' => $sortOrder,
                ':assignee' => $defaultAssignee,
                ':now' => $now,
                ':id' => $id,
            ]);

            // Sync units
            $pdo->prepare("DELETE FROM tec_service_units WHERE service_id = :id")->execute([':id' => $id]);
            if (!empty($unitIds)) {
                $insUnit = $pdo->prepare("INSERT INTO tec_service_units (service_id, unidad_id) VALUES (:sid, :uid)");
                foreach ($unitIds as $uid) {
                    $insUnit->execute([':sid' => $id, ':uid' => $uid]);
                }
            }

            $after = $this->get($p, $id);
            AuditLogger::log($p, 'service.updated', 'service', $id, $before, $after);
            return $id;
        }

        // Insert
        $stmt = $pdo->prepare(
            "INSERT INTO tec_services (
                code, category, name_en, name_es, description_en, description_es,
                conditions_en, conditions_es, price_amount, price_unit, currency,
                lead_time_days, is_public, is_active, sort_order,
                default_assignee_user_id, created_by_user_id, created_at, updated_at
            ) VALUES (
                :code, :cat, :nen, :nes, :den, :des,
                :cen, :ces, :pam, :punit, :cur,
                :ltd, :pub, :act, :sort,
                :assignee, :uid, :now, :now
            )"
        );
        $stmt->execute([
            ':code' => $code,
            ':cat' => $category,
            ':nen' => $nameEn,
            ':nes' => $nameEs,
            ':den' => $descEn,
            ':des' => $descEs,
            ':cen' => $condEn,
            ':ces' => $condEs,
            ':pam' => $priceAmount,
            ':punit' => $priceUnit,
            ':cur' => $currency,
            ':ltd' => $leadTime,
            ':pub' => $isPublic,
            ':act' => $isActive,
            ':sort' => $sortOrder,
            ':assignee' => $defaultAssignee,
            ':uid' => $p->userId,
            ':now' => $now,
        ]);
        $newId = (int) $pdo->lastInsertId();

        if (!empty($unitIds)) {
            $insUnit = $pdo->prepare("INSERT INTO tec_service_units (service_id, unidad_id) VALUES (:sid, :uid)");
            foreach ($unitIds as $uid) {
                $insUnit->execute([':sid' => $newId, ':uid' => $uid]);
            }
        }

        $after = $this->get($p, $newId);
        AuditLogger::log($p, 'service.created', 'service', $newId, null, $after);
        return $newId;
    }

    public function toggle(Principal $p, int $id, bool $active): void
    {
        if (!ServicePolicy::canManage($p)) {
            throw new ForbiddenException('Cannot manage services catalog');
        }

        $before = $this->get($p, $id);
        $now = Clock::nowUtcSql();
        Db::pdo()->prepare("UPDATE tec_services SET is_active = :act, updated_at = :now WHERE id = :id AND deleted_at IS NULL")
            ->execute([':act' => $active ? 1 : 0, ':now' => $now, ':id' => $id]);

        $after = $this->get($p, $id);
        AuditLogger::log($p, 'service.toggled', 'service', $id, $before, $after);
    }

    public function softDelete(Principal $p, int $id): void
    {
        if (!ServicePolicy::canManage($p)) {
            throw new ForbiddenException('Cannot manage services catalog');
        }

        $before = $this->get($p, $id);
        $now = Clock::nowUtcSql();
        Db::pdo()->prepare("UPDATE tec_services SET deleted_at = :now, updated_at = :now WHERE id = :id AND deleted_at IS NULL")
            ->execute([':now' => $now, ':id' => $id]);

        AuditLogger::log($p, 'service.deleted', 'service', $id, $before, null);
    }

    public function forUnit(Principal $p, int $unitId, string $lang = 'en'): array
    {
        if (!ServicePolicy::canView($p) && !ServicePolicy::canRequest($p)) {
            throw new ForbiddenException('Cannot view services for unit');
        }

        if (!ServicePolicy::canManage($p)) {
            UnitScope::assertUnit($p, $unitId);
        }

        $pdo = Db::pdo();
        // A service is available for a unit if it is active, not deleted,
        // and either has NO entries in tec_service_units (all units) or has an entry for this unitId.
        $stmt = $pdo->prepare(
            "SELECT s.* FROM tec_services s
             WHERE s.is_active = 1 AND s.deleted_at IS NULL
               AND (
                 NOT EXISTS (SELECT 1 FROM tec_service_units su WHERE su.service_id = s.id)
                 OR EXISTS (SELECT 1 FROM tec_service_units su WHERE su.service_id = s.id AND su.unidad_id = :uid)
               )
             ORDER BY s.sort_order ASC, s.id ASC"
        );
        $stmt->execute([':uid' => $unitId]);
        $rows = $stmt->fetchAll();

        $nameCol = $lang === 'es' ? 'name_es' : 'name_en';
        $descCol = $lang === 'es' ? 'description_es' : 'description_en';
        $condCol = $lang === 'es' ? 'conditions_es' : 'conditions_en';

        return array_map(static function ($r) use ($nameCol, $descCol, $condCol) {
            return [
                'id' => (int) $r['id'],
                'code' => $r['code'],
                'name' => $r[$nameCol] ?: $r['name_en'],
                'category' => $r['category'],
                'description' => $r[$descCol],
                'conditions' => $r[$condCol],
                'price_amount' => $r['price_amount'] !== null ? (float) $r['price_amount'] : null,
                'price_unit' => $r['price_unit'],
                'currency' => $r['currency'],
                'lead_time_days' => (int) $r['lead_time_days'],
            ];
        }, $rows);
    }

    /**
     * @param int[] $allowed
     * @param array<string, mixed> $params
     */
    private static function applicableSql(array $allowed, array &$params): string
    {
        if ($allowed === []) {
            return ' AND NOT EXISTS (SELECT 1 FROM tec_service_units su0 WHERE su0.service_id = s.id)';
        }
        $names = [];
        foreach (array_values($allowed) as $i => $unit) {
            $names[] = ':au' . $i;
            $params[':au' . $i] = $unit;
        }

        return ' AND (NOT EXISTS (SELECT 1 FROM tec_service_units su0 WHERE su0.service_id = s.id)'
            . ' OR EXISTS (SELECT 1 FROM tec_service_units su1 WHERE su1.service_id = s.id AND su1.unidad_id IN (' . implode(',', $names) . ')))';
    }

    /**
     * Customer-facing view: no internal assignee, only the viewer's own properties.
     *
     * @param array<string, mixed> $row
     * @param int[] $allowed
     * @return array<string, mixed>
     */
    private static function publicView(array $row, array $allowed): array
    {
        unset($row['default_assignee_user_id'], $row['default_assignee_name'], $row['created_by_user_id'], $row['updated_by_user_id']);
        $row['unit_ids'] = array_values(array_intersect(array_map('intval', $row['unit_ids'] ?? []), $allowed));

        return $row;
    }

    public function listPublic(string $lang, ?string $q, ?int $unitId): array
    {
        $pdo = Db::pdo();
        $params = [];
        $sql = "SELECT s.* FROM tec_services s
                WHERE s.is_active = 1 AND s.is_public = 1 AND s.deleted_at IS NULL";

        if ($q !== null && trim($q) !== '') {
            $sql .= " AND (s.name_en LIKE :q OR s.name_es LIKE :q OR s.category LIKE :q OR s.description_en LIKE :q OR s.description_es LIKE :q)";
            $params[':q'] = '%' . trim($q) . '%';
        }

        if ($unitId !== null && $unitId > 0) {
            $sql .= " AND (
                NOT EXISTS (SELECT 1 FROM tec_service_units su WHERE su.service_id = s.id)
                OR EXISTS (SELECT 1 FROM tec_service_units su WHERE su.service_id = s.id AND su.unidad_id = :uid)
            )";
            $params[':uid'] = $unitId;
        }

        $sql .= " ORDER BY s.sort_order ASC, s.id ASC LIMIT 8";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $nameCol = $lang === 'es' ? 'name_es' : 'name_en';
        $descCol = $lang === 'es' ? 'description_es' : 'description_en';
        $condCol = $lang === 'es' ? 'conditions_es' : 'conditions_en';

        return array_map(static function ($r) use ($nameCol, $descCol, $condCol) {
            $priceText = $r['price_unit'] === 'quote'
                ? 'Cotización previa'
                : ($r['price_amount'] !== null ? number_format((float) $r['price_amount'], 2) . ' ' . $r['currency'] : '');

            return [
                'id' => (int) $r['id'],
                'code' => $r['code'],
                'name' => $r[$nameCol] ?: $r['name_en'],
                'category' => $r['category'],
                'description' => $r[$descCol] ?: '',
                'conditions' => $r[$condCol] ?: '',
                'price_text' => $priceText,
                'lead_time_days' => (int) $r['lead_time_days'],
            ];
        }, $rows);
    }
}
