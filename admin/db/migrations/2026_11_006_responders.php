<?php

declare(strict_types=1);

use Pgs\Migrations\AbstractMigration;
use Pgs\Migrations\MigrationContext;
use Pgs\Migrations\SchemaHelper;

return new class extends AbstractMigration {
    public function id(): string
    {
        return '2026_11_006_responders';
    }

    public function description(): string
    {
        return 'Responders list used for transfers and alerts';
    }

    public function up(\PDO $pdo, MigrationContext $ctx): void
    {
        SchemaHelper::createTable($pdo, 'tec_responders', <<<'SQL'
(
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  name VARCHAR(120) NOT NULL,
  phone_e164 VARCHAR(20) NULL,
  email VARCHAR(190) NULL,
  priority_order SMALLINT NOT NULL DEFAULT 100,
  receives_transfers TINYINT(1) NOT NULL DEFAULT 0,
  receives_alerts TINYINT(1) NOT NULL DEFAULT 1,
  is_owner TINYINT(1) NOT NULL DEFAULT 0,
  days_mask TINYINT UNSIGNED NOT NULL DEFAULT 127,
  time_from TIME NULL,
  time_to TIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_active (is_active, priority_order)
)
SQL, $ctx);
    }
};
