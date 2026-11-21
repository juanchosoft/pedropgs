<?php

declare(strict_types=1);

use Pgs\Migrations\AbstractMigration;
use Pgs\Migrations\MigrationContext;
use Pgs\Migrations\SchemaHelper;

return new class extends AbstractMigration {
    public function id(): string
    {
        return '2026_11_002_phone_directory';
    }

    public function description(): string
    {
        return 'Phone directory for properties and staff, plus discarded legacy numbers';
    }

    public function up(\PDO $pdo, MigrationContext $ctx): void
    {
        SchemaHelper::createTable($pdo, 'tec_phone_directory', <<<'SQL'
(
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  kind ENUM('property','staff') NOT NULL DEFAULT 'property',
  unidad_id INT NOT NULL DEFAULT 0,
  employee_id INT NOT NULL DEFAULT 0,
  phone_e164 VARCHAR(20) NOT NULL,
  label VARCHAR(100) NULL,
  source ENUM('legacy','manual') NOT NULL DEFAULT 'manual',
  legacy_field VARCHAR(40) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_phone_target (phone_e164, kind, unidad_id, employee_id),
  KEY idx_phone_active (phone_e164, is_active),
  KEY idx_unidad (unidad_id)
)
SQL, $ctx);

        SchemaHelper::createTable($pdo, 'tec_phone_discards', <<<'SQL'
(
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  source_table VARCHAR(40) NOT NULL,
  source_id INT NOT NULL,
  field_name VARCHAR(40) NOT NULL,
  raw_value VARCHAR(100) NOT NULL,
  reason VARCHAR(40) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_discard (source_table, source_id, field_name),
  KEY idx_reason (reason)
)
SQL, $ctx);
    }
};
