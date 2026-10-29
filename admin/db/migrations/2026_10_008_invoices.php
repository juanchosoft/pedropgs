<?php

declare(strict_types=1);

use Pgs\Migrations\AbstractMigration;
use Pgs\Migrations\MigrationContext;
use Pgs\Migrations\SchemaHelper;

return new class extends AbstractMigration {
    public function id(): string
    {
        return '2026_10_008_invoices';
    }

    public function description(): string
    {
        return 'Invoices and expense-to-invoice link';
    }

    public function up(\PDO $pdo, MigrationContext $ctx): void
    {
        SchemaHelper::createTable($pdo, 'tec_invoices', <<<'SQL'
(
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_number VARCHAR(40) NOT NULL,
  unidad_id INT NOT NULL,
  location_label VARCHAR(120) NULL,
  issue_date DATE NOT NULL,
  due_date DATE NULL,
  currency CHAR(3) NOT NULL,
  amount DECIMAL(14,2) NOT NULL,
  amount_paid DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  payment_status ENUM('pending','partially_paid','paid','overdue','void') NOT NULL DEFAULT 'pending',
  paid_at DATETIME NULL,
  void_reason VARCHAR(255) NULL,
  voided_at DATETIME NULL,
  notes TEXT NULL,
  pdf_path VARCHAR(255) NULL,
  pdf_original_name VARCHAR(190) NULL,
  pdf_size_bytes INT UNSIGNED NULL,
  pdf_sha256 CHAR(64) NULL,
  created_by_user_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  row_version INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_invoices_number (invoice_number),
  KEY idx_invoices_unit (unidad_id, payment_status, issue_date),
  KEY idx_invoices_due (payment_status, due_date)
)
SQL, $ctx, [
            'fk_invoices_unidad' => 'FOREIGN KEY (unidad_id) REFERENCES tbl_unidades (id) ON DELETE RESTRICT',
        ]);

        SchemaHelper::addFk(
            $pdo,
            'tec_expenses',
            'fk_expenses_invoice',
            'FOREIGN KEY (invoice_id) REFERENCES tec_invoices (id) ON DELETE SET NULL',
            $ctx
        );
    }

    public function rollbackNotes(): string
    {
        return 'ALTER TABLE tec_expenses DROP FOREIGN KEY fk_expenses_invoice; DROP TABLE tec_invoices;';
    }
};
