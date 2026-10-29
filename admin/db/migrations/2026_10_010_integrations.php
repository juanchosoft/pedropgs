<?php

declare(strict_types=1);

use Pgs\Migrations\AbstractMigration;
use Pgs\Migrations\MigrationContext;
use Pgs\Migrations\SchemaHelper;

return new class extends AbstractMigration {
    public function id(): string
    {
        return '2026_10_010_integrations';
    }

    public function description(): string
    {
        return 'API tokens, sync outbox, sync links, and outbound call requests';
    }

    public function up(\PDO $pdo, MigrationContext $ctx): void
    {
        SchemaHelper::createTable($pdo, 'tec_api_tokens', <<<'SQL'
(
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  token_prefix CHAR(12) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  scopes VARCHAR(500) NOT NULL,
  ip_allowlist VARCHAR(500) NULL,
  rate_limit_per_minute SMALLINT UNSIGNED NULL,
  created_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  expires_at DATETIME NULL,
  last_used_at DATETIME NULL,
  last_used_ip VARCHAR(45) NULL,
  revoked_at DATETIME NULL,
  revoked_by_user_id INT UNSIGNED NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_api_tokens_prefix (token_prefix),
  UNIQUE KEY uq_api_tokens_hash (token_hash)
)
SQL, $ctx);

        SchemaHelper::createTable($pdo, 'tec_sync_outbox', <<<'SQL'
(
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  target ENUM('sheets','calendar') NOT NULL,
  entity_type ENUM('expense','task','reservation','absence') NOT NULL,
  entity_id INT UNSIGNED NOT NULL,
  operation ENUM('upsert','delete') NOT NULL,
  status ENUM('pending','processing','done','failed','dead') NOT NULL DEFAULT 'pending',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  next_attempt_at DATETIME NOT NULL,
  locked_until DATETIME NULL,
  last_error VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sync_entity (target, entity_type, entity_id),
  KEY idx_sync_due (status, next_attempt_at)
)
SQL, $ctx);

        SchemaHelper::createTable($pdo, 'tec_sync_links', <<<'SQL'
(
  target ENUM('sheets','calendar') NOT NULL,
  entity_type VARCHAR(20) NOT NULL,
  entity_id INT UNSIGNED NOT NULL,
  external_id VARCHAR(190) NOT NULL,
  last_hash CHAR(64) NULL,
  synced_at DATETIME NOT NULL,
  PRIMARY KEY (target, entity_type, entity_id)
)
SQL, $ctx);

        SchemaHelper::createTable($pdo, 'tec_outbound_call_requests', <<<'SQL'
(
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  unidad_id INT NOT NULL,
  unit_contact_id INT UNSIGNED NULL,
  phone_e164 VARCHAR(20) CHARACTER SET ascii NOT NULL,
  purpose VARCHAR(255) NOT NULL,
  task_id INT UNSIGNED NULL,
  requested_by_user_id INT UNSIGNED NULL,
  call_session_id BIGINT UNSIGNED NULL,
  status ENUM('not_executed','queued','cancelled') NOT NULL DEFAULT 'not_executed',
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_outbound_unit (unidad_id)
)
SQL, $ctx);
    }

    public function rollbackNotes(): string
    {
        return 'DROP TABLE tec_outbound_call_requests, tec_sync_links, tec_sync_outbox, tec_api_tokens';
    }
};
