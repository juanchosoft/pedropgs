<?php

declare(strict_types=1);

use Pgs\Migrations\AbstractMigration;
use Pgs\Migrations\MigrationContext;
use Pgs\Migrations\SchemaHelper;

return new class extends AbstractMigration {
    public function id(): string
    {
        return '2026_10_004_services';
    }

    public function description(): string
    {
        return 'Services catalog and unit availability mapping';
    }

    public function up(\PDO $pdo, MigrationContext $ctx): void
    {
        SchemaHelper::createTable($pdo, 'tec_services', <<<'SQL'
(
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(30) NOT NULL,
  category VARCHAR(60) NOT NULL,
  name_en VARCHAR(150) NOT NULL,
  name_es VARCHAR(150) NOT NULL,
  description_en TEXT NULL,
  description_es TEXT NULL,
  conditions_en TEXT NULL,
  conditions_es TEXT NULL,
  price_amount DECIMAL(14,2) NULL,
  price_unit ENUM('fixed','hour','visit','sqft','quote') NOT NULL DEFAULT 'quote',
  currency CHAR(3) NOT NULL,
  lead_time_days SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_public TINYINT(1) NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  default_assignee_user_id INT UNSIGNED NULL,
  created_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_services_code (code),
  KEY idx_services_active (is_active, is_public, sort_order)
)
SQL, $ctx);

        SchemaHelper::createTable($pdo, 'tec_service_units', <<<'SQL'
(
  service_id INT UNSIGNED NOT NULL,
  unidad_id INT NOT NULL,
  PRIMARY KEY (service_id, unidad_id),
  KEY idx_service_units_unidad (unidad_id)
)
SQL, $ctx, [
            'fk_service_units_service' => 'FOREIGN KEY (service_id) REFERENCES tec_services (id) ON DELETE CASCADE',
            'fk_service_units_unidad' => 'FOREIGN KEY (unidad_id) REFERENCES tbl_unidades (id) ON DELETE CASCADE',
        ]);
    }

    public function rollbackNotes(): string
    {
        return 'DROP TABLE tec_service_units, tec_services';
    }
};
