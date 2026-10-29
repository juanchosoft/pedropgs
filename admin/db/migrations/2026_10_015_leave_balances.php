<?php

declare(strict_types=1);

use Pgs\Core\Clock;
use Pgs\Migrations\AbstractMigration;
use Pgs\Migrations\MigrationContext;
use Pgs\Migrations\SchemaHelper;

return new class extends AbstractMigration {
    public function id(): string
    {
        return '2026_10_015_leave_balances';
    }

    public function description(): string
    {
        return 'Leave allocations, leave ledger, and opening balance backfill';
    }

    public function up(\PDO $pdo, MigrationContext $ctx): void
    {
        SchemaHelper::createTable($pdo, 'tec_leave_allocations', <<<'SQL'
(
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_id INT NOT NULL,
  leave_year SMALLINT UNSIGNED NOT NULL,
  allocated_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  notes VARCHAR(255) NULL,
  created_by_user_id INT UNSIGNED NULL,
  updated_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  row_version INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_leave_alloc (employee_id, leave_year)
)
SQL, $ctx, [
            'fk_leave_alloc_employee' => 'FOREIGN KEY (employee_id) REFERENCES tec_employee (id) ON DELETE CASCADE',
        ]);

        SchemaHelper::createTable($pdo, 'tec_leave_ledger', <<<'SQL'
(
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  employee_id INT NOT NULL,
  leave_year SMALLINT UNSIGNED NOT NULL,
  movement ENUM('opening','allocation','adjustment','consumption','reversal') NOT NULL,
  days SMALLINT NOT NULL,
  absence_id INT UNSIGNED NULL,
  reason VARCHAR(255) NULL,
  actor_user_id INT UNSIGNED NULL,
  channel VARCHAR(20) NOT NULL,
  created_at DATETIME NOT NULL,
  movement_key VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_leave_movement_key (movement_key),
  KEY idx_leave_ledger_emp_year (employee_id, leave_year, created_at),
  KEY idx_leave_ledger_absence (absence_id)
)
SQL, $ctx, [
            'fk_leave_ledger_employee' => 'FOREIGN KEY (employee_id) REFERENCES tec_employee (id) ON DELETE CASCADE',
            'fk_leave_ledger_absence' => 'FOREIGN KEY (absence_id) REFERENCES tec_worker_absences (id) ON DELETE SET NULL',
        ]);

        // Backfill opening balance
        $year = (int) Clock::todayLocal()->format('Y');
        $now = Clock::nowUtcSql();

        $stmtEmployees = $pdo->query(
            "SELECT id, nombre, dias_descanso FROM tec_employee WHERE dias_descanso IS NOT NULL AND dias_descanso > 0"
        );

        $insAlloc = $pdo->prepare(
            "INSERT IGNORE INTO tec_leave_allocations (employee_id, leave_year, allocated_days, notes, created_at, updated_at)
             VALUES (:e, :y, :d, 'Opening balance from tec_employee.dias_descanso', :now, :now)"
        );

        $insLedger = $pdo->prepare(
            "INSERT IGNORE INTO tec_leave_ledger (employee_id, leave_year, movement, days, reason, channel, created_at, movement_key)
             VALUES (:e, :y, 'opening', :d, 'Opening balance from tec_employee.dias_descanso', 'migration', :now, :mkey)"
        );

        $count = 0;
        while ($emp = $stmtEmployees->fetch()) {
            $eid = (int) $emp['id'];
            $days = (int) $emp['dias_descanso'];
            $name = (string) $emp['nombre'];
            $mkey = "opening:{$eid}:{$year}";

            $insAlloc->execute([':e' => $eid, ':y' => $year, ':d' => $days, ':now' => $now]);
            $insLedger->execute([':e' => $eid, ':y' => $year, ':d' => $days, ':now' => $now, ':mkey' => $mkey]);

            $ctx->out("Opening leave balance: employee=$eid ($name) days=$days year=$year");
            $count++;
        }

        $ctx->out("Backfilled opening leave balances for $count employees");
    }

    public function rollbackNotes(): string
    {
        return 'DROP TABLE tec_leave_ledger, tec_leave_allocations';
    }
};
