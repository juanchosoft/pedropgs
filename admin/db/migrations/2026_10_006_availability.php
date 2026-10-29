<?php

declare(strict_types=1);

use Pgs\Migrations\AbstractMigration;
use Pgs\Migrations\MigrationContext;
use Pgs\Migrations\SchemaHelper;

return new class extends AbstractMigration {
    public function id(): string
    {
        return '2026_10_006_availability';
    }

    public function description(): string
    {
        return 'Worker schedule, worker absences, and worker reservations';
    }

    public function up(\PDO $pdo, MigrationContext $ctx): void
    {
        SchemaHelper::createTable($pdo, 'tec_worker_schedule', <<<'SQL'
(
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_id INT NOT NULL,
  unidad_id INT NOT NULL,
  weekday TINYINT UNSIGNED NOT NULL,
  day_part ENUM('full','am','pm') NOT NULL DEFAULT 'full',
  valid_from DATE NOT NULL,
  valid_to DATE NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  notes VARCHAR(255) NULL,
  created_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_schedule_slot (employee_id, unidad_id, weekday, day_part, valid_from),
  KEY idx_schedule_unit (unidad_id, weekday)
)
SQL, $ctx, [
            'fk_schedule_employee' => 'FOREIGN KEY (employee_id) REFERENCES tec_employee (id) ON DELETE CASCADE',
            'fk_schedule_unidad' => 'FOREIGN KEY (unidad_id) REFERENCES tbl_unidades (id) ON DELETE CASCADE',
        ]);

        SchemaHelper::createTable($pdo, 'tec_worker_absences', <<<'SQL'
(
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_id INT NOT NULL,
  date_from DATE NOT NULL,
  date_to DATE NOT NULL,
  day_part ENUM('full','am','pm') NOT NULL DEFAULT 'full',
  reason_type ENUM('vacation','sick','personal','other') NOT NULL,
  reason_text VARCHAR(255) NULL,
  days_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  deducts_balance TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('requested','approved','rejected','cancelled') NOT NULL DEFAULT 'requested',
  requested_by_user_id INT UNSIGNED NULL,
  decided_by_user_id INT UNSIGNED NULL,
  decided_at DATETIME NULL,
  decision_note VARCHAR(255) NULL,
  channel VARCHAR(20) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_absence_employee (employee_id, status, date_from, date_to)
)
SQL, $ctx, [
            'fk_absence_employee' => 'FOREIGN KEY (employee_id) REFERENCES tec_employee (id) ON DELETE CASCADE',
        ]);

        SchemaHelper::createTable($pdo, 'tec_worker_reservations', <<<'SQL'
(
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL,
  employee_id INT NOT NULL,
  unidad_id INT NOT NULL,
  work_date DATE NOT NULL,
  day_part ENUM('full','am','pm') NOT NULL DEFAULT 'full',
  status ENUM('pending','confirmed','rejected','cancelled','fulfilled') NOT NULL DEFAULT 'pending',
  notes VARCHAR(500) NULL,
  requested_by_user_id INT UNSIGNED NULL,
  channel VARCHAR(20) NOT NULL,
  task_id INT UNSIGNED NULL,
  cancel_reason VARCHAR(255) NULL,
  confirmed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  slot_lock VARCHAR(48) GENERATED ALWAYS AS (CASE WHEN status IN ('confirmed','fulfilled') THEN CONCAT(employee_id, '|', work_date, '|', day_part) ELSE NULL END) STORED,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rsv_code (code),
  UNIQUE KEY uq_rsv_slot (slot_lock),
  KEY idx_rsv_employee_date (employee_id, work_date, status),
  KEY idx_rsv_unit_date (unidad_id, work_date)
)
SQL, $ctx, [
            'fk_rsv_employee' => 'FOREIGN KEY (employee_id) REFERENCES tec_employee (id) ON DELETE RESTRICT ON UPDATE RESTRICT',
            'fk_rsv_unidad' => 'FOREIGN KEY (unidad_id) REFERENCES tbl_unidades (id) ON DELETE RESTRICT ON UPDATE RESTRICT',
            'fk_rsv_task' => 'FOREIGN KEY (task_id) REFERENCES tec_tasks (id) ON DELETE SET NULL',
        ]);
    }

    public function rollbackNotes(): string
    {
        return 'DROP TABLE tec_worker_reservations, tec_worker_absences, tec_worker_schedule';
    }
};
