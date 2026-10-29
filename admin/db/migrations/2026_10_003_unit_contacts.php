<?php

declare(strict_types=1);

use Pgs\Migrations\AbstractMigration;
use Pgs\Migrations\MigrationContext;
use Pgs\Migrations\SchemaHelper;
use Pgs\Units\UnitContactSync;

return new class extends AbstractMigration {
    public function id(): string
    {
        return '2026_10_003_unit_contacts';
    }

    public function description(): string
    {
        return 'Unit contacts table and legacy backfill';
    }

    public function up(\PDO $pdo, MigrationContext $ctx): void
    {
        SchemaHelper::createTable($pdo, 'tec_unit_contacts', <<<'SQL'
(
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  unidad_id INT NOT NULL,
  contact_role ENUM('administrator','emergency','board','report_recipient','other') NOT NULL,
  name VARCHAR(150) NULL,
  phone_e164 VARCHAR(20) CHARACTER SET ascii NULL,
  phone_raw VARCHAR(120) NULL,
  email VARCHAR(150) NULL,
  receives_reports TINYINT(1) NOT NULL DEFAULT 0,
  receives_notifications TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  source ENUM('legacy','manual') NOT NULL,
  source_field VARCHAR(40) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_unit_contact_source (unidad_id, source_field),
  KEY idx_unit_contact_phone (phone_e164)
)
SQL, $ctx, [
            'fk_unit_contacts_unidad' => 'FOREIGN KEY (unidad_id) REFERENCES tbl_unidades (id) ON DELETE CASCADE',
        ]);

        $synced = UnitContactSync::syncAll($pdo);
        $ctx->out("Backfill tec_unit_contacts synced for $synced units");
    }

    public function rollbackNotes(): string
    {
        return 'DROP TABLE tec_unit_contacts';
    }
};
