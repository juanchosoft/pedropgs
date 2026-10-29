<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

require_once __DIR__ . '/../bootstrap_pgs.php';

use Pgs\Auth\VoicePin;
use Pgs\Core\Clock;
use Pgs\Core\Config;
use Pgs\Core\Db;
use Pgs\Core\Phone;

$env = Config::get('app', 'env', 'local');
$options = getopt('', ['force::']);
$isForce = isset($options['force']);

if ($env !== 'local' && !$isForce) {
    echo "Error: seed_demo.php is only permitted in local environment (current: '$env'). Use --force to override.\n";
    exit(1);
}

$pdo = Db::pdo();
$now = Clock::nowUtcSql();
$currentYear = (int) Clock::nowUtc()->format('Y');

echo "=== Seeding PGS Demo Data ===\n";

// 1. Seed demo services into tec_services
$services = [
    [
        'code' => 'SRV-PLUMB',
        'name_en' => 'General Plumbing Service',
        'name_es' => 'Servicio General de Plomería',
        'category' => 'Plumbing',
        'description_en' => 'Inspection, repair of leaks, pipes and fixtures',
        'description_es' => 'Inspección y reparación de fugas, tuberías y accesorios',
        'price_amount' => 85.00,
        'price_unit' => 'fixed',
        'currency' => 'USD',
        'lead_time_days' => 1,
        'is_public' => 1,
        'sort_order' => 10,
    ],
    [
        'code' => 'SRV-ELEC',
        'name_en' => 'Electrical Maintenance',
        'name_es' => 'Mantenimiento Eléctrico',
        'category' => 'Electrical',
        'description_en' => 'Wiring inspection, outlets, switches and breakers',
        'description_es' => 'Revisión de cableado, tomas, interruptores y breakers',
        'price_amount' => 95.00,
        'price_unit' => 'fixed',
        'currency' => 'USD',
        'lead_time_days' => 1,
        'is_public' => 1,
        'sort_order' => 20,
    ],
    [
        'code' => 'SRV-CLEAN',
        'name_en' => 'Deep Cleaning Service',
        'name_es' => 'Limpieza Profunda',
        'category' => 'Cleaning',
        'description_en' => 'Complete deep cleaning of unit interior and surfaces',
        'description_es' => 'Limpieza profunda de interiores y superficies de la unidad',
        'price_amount' => 150.00,
        'price_unit' => 'visit',
        'currency' => 'USD',
        'lead_time_days' => 2,
        'is_public' => 1,
        'sort_order' => 30,
    ],
    [
        'code' => 'SRV-HVAC',
        'name_en' => 'HVAC Inspection & Filter Change',
        'name_es' => 'Inspección y Cambio de Filtros de A/C',
        'category' => 'HVAC',
        'description_en' => 'Air conditioning inspection, cleaning and filter replacement',
        'description_es' => 'Revisión, limpieza y reemplazo de filtros de aire acondicionado',
        'price_amount' => 120.00,
        'price_unit' => 'fixed',
        'currency' => 'USD',
        'lead_time_days' => 1,
        'is_public' => 1,
        'sort_order' => 40,
    ],
    [
        'code' => 'SRV-PAINT',
        'name_en' => 'Interior Painting & Touch-up',
        'name_es' => 'Pintura Interior y Retoques',
        'category' => 'Painting',
        'description_en' => 'Wall repairs, priming and fresh paint coats',
        'description_es' => 'Reparación de paredes y aplicación de pintura',
        'price_amount' => null,
        'price_unit' => 'quote',
        'currency' => 'USD',
        'lead_time_days' => 3,
        'is_public' => 1,
        'sort_order' => 50,
    ],
];

$svcStmt = $pdo->prepare(
    "INSERT INTO tec_services (
        code, name_en, name_es, category, description_en, description_es,
        price_amount, price_unit, currency, lead_time_days, is_public,
        sort_order, is_active, created_at, updated_at
    ) VALUES (
        :code, :name_en, :name_es, :category, :desc_en, :desc_es,
        :price, :unit, :cur, :lead, :public,
        :sort, 1, :now, :now
    ) ON DUPLICATE KEY UPDATE
        name_en = VALUES(name_en),
        name_es = VALUES(name_es),
        price_amount = VALUES(price_amount),
        price_unit = VALUES(price_unit),
        lead_time_days = VALUES(lead_time_days),
        updated_at = VALUES(updated_at)"
);

$seededSvc = 0;
foreach ($services as $s) {
    $svcStmt->execute([
        ':code' => $s['code'],
        ':name_en' => $s['name_en'],
        ':name_es' => $s['name_es'],
        ':category' => $s['category'],
        ':desc_en' => $s['description_en'],
        ':desc_es' => $s['description_es'],
        ':price' => $s['price_amount'],
        ':unit' => $s['price_unit'],
        ':cur' => $s['currency'],
        ':lead' => $s['lead_time_days'],
        ':public' => $s['is_public'],
        ':sort' => $s['sort_order'],
        ':now' => $now,
    ]);
    $seededSvc++;
}
echo "[OK] Seeded/updated $seededSvc demo services.\n";

// 2. Seed worker schedule for active employees
$employees = $pdo->query("SELECT id FROM tec_employee LIMIT 5")->fetchAll();
$unit = $pdo->query("SELECT id FROM tbl_unidades LIMIT 1")->fetch();
$unitId = $unit ? (int) $unit['id'] : 1;
$seededSched = 0;
$todayDate = Clock::nowUtc()->format('Y-01-01');

if (!empty($employees)) {
    $schedStmt = $pdo->prepare(
        "INSERT INTO tec_worker_schedule (
            employee_id, unidad_id, weekday, day_part, valid_from, is_active, created_at, updated_at
        ) VALUES (
            :eid, :uid, :dow, 'full', :valid_from, 1, :now, :now
        ) ON DUPLICATE KEY UPDATE
            is_active = 1, updated_at = :now"
    );

    foreach ($employees as $emp) {
        $eid = (int) $emp['id'];
        for ($dow = 1; $dow <= 5; $dow++) { // Mon-Fri
            $schedStmt->execute([
                ':eid' => $eid,
                ':uid' => $unitId,
                ':dow' => $dow,
                ':valid_from' => $todayDate,
                ':now' => $now,
            ]);
            $seededSched++;
        }
    }
}
echo "[OK] Seeded $seededSched worker schedule slots.\n";

// Voice users are configured per user with admin/cli/user_voice_setup.php or from my_profile.php.

echo "=== Seed Demo Complete ===\n";
