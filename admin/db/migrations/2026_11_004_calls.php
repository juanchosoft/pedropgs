<?php

declare(strict_types=1);

use Pgs\Migrations\AbstractMigration;
use Pgs\Migrations\MigrationContext;
use Pgs\Migrations\SchemaHelper;

return new class extends AbstractMigration {
    public function id(): string
    {
        return '2026_11_004_calls';
    }

    public function description(): string
    {
        return 'Voice call sessions';
    }

    public function up(\PDO $pdo, MigrationContext $ctx): void
    {
        SchemaHelper::createTable($pdo, 'tec_calls', <<<'SQL'
(
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id VARCHAR(100) NOT NULL,
  caller_e164 VARCHAR(20) NULL,
  caller_raw VARCHAR(50) NULL,
  called_number VARCHAR(20) NULL,
  match_status ENUM('single','multiple','none','hidden') NOT NULL DEFAULT 'none',
  unidad_id INT NULL,
  candidate_ids VARCHAR(255) NULL,
  caller_name VARCHAR(120) NULL,
  caller_kind ENUM('property','staff','unknown') NOT NULL DEFAULT 'unknown',
  lang CHAR(2) NULL,
  state ENUM('active','ended','expired') NOT NULL DEFAULT 'active',
  outcome ENUM('request_created','transferred','message','none','unknown') NOT NULL DEFAULT 'unknown',
  transferred_to VARCHAR(120) NULL,
  duration_seconds INT NULL,
  summary TEXT NULL,
  started_at DATETIME NOT NULL,
  ended_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_conversation (conversation_id),
  KEY idx_started (started_at),
  KEY idx_state (state, started_at)
)
SQL, $ctx);
    }
};
