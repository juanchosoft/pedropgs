<?php

declare(strict_types=1);

use Pgs\Migrations\AbstractMigration;
use Pgs\Migrations\MigrationContext;
use Pgs\Migrations\SchemaHelper;

return new class extends AbstractMigration {
    public function id(): string
    {
        return '2026_10_007_expenses';
    }

    public function description(): string
    {
        return 'Expenses and expense items';
    }

    public function up(\PDO $pdo, MigrationContext $ctx): void
    {
        SchemaHelper::createTable($pdo, 'tec_expenses', <<<'SQL'
(
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL,
  unidad_id INT NOT NULL,
  location_label VARCHAR(120) NULL,
  purchase_date DATE NOT NULL,
  vendor VARCHAR(150) NULL,
  receipt_number VARCHAR(60) NULL,
  notes TEXT NULL,
  currency CHAR(3) NOT NULL,
  total_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  items_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  billing_status ENUM('pending','invoiced','void') NOT NULL DEFAULT 'pending',
  invoice_id INT UNSIGNED NULL,
  invoiced_at DATETIME NULL,
  void_reason VARCHAR(255) NULL,
  voided_at DATETIME NULL,
  channel ENUM('web','assistant_text','assistant_voice','phone') NOT NULL,
  created_by_user_id INT UNSIGNED NOT NULL,
  created_by_employee_id INT NULL,
  pending_action_id BIGINT UNSIGNED NULL,
  receipt_path VARCHAR(255) NULL,
  source_attachment_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  row_version INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_expenses_code (code),
  UNIQUE KEY uq_expenses_pending_action (pending_action_id),
  KEY idx_expenses_unit_status (unidad_id, billing_status, purchase_date),
  KEY idx_expenses_creator (created_by_user_id, created_at),
  KEY idx_expenses_invoice (invoice_id)
)
SQL, $ctx, [
            'fk_expenses_unidad' => 'FOREIGN KEY (unidad_id) REFERENCES tbl_unidades (id) ON DELETE RESTRICT',
        ]);

        SchemaHelper::createTable($pdo, 'tec_expense_items', <<<'SQL'
(
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  expense_id INT UNSIGNED NOT NULL,
  line_no SMALLINT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL,
  quantity DECIMAL(12,3) NOT NULL,
  unit_price DECIMAL(14,2) NOT NULL,
  line_total DECIMAL(14,2) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_expense_items_line (expense_id, line_no)
)
SQL, $ctx, [
            'fk_expense_items_expense' => 'FOREIGN KEY (expense_id) REFERENCES tec_expenses (id) ON DELETE CASCADE',
        ]);
    }

    public function rollbackNotes(): string
    {
        return 'DROP TABLE tec_expense_items, tec_expenses';
    }
};
