<?php

declare(strict_types=1);

use Pgs\Migrations\AbstractMigration;
use Pgs\Migrations\MigrationContext;
use Pgs\Migrations\SchemaHelper;

return new class extends AbstractMigration {
    public function id(): string
    {
        return '2026_10_001_core_infra';
    }

    public function description(): string
    {
        return 'Core infrastructure: audit log, sequences, idempotency, rate limits, signed links, pending actions';
    }

    public function up(\PDO $pdo, MigrationContext $ctx): void
    {
        SchemaHelper::createTable($pdo, 'tec_audit_log', <<<'SQL'
(
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  occurred_at DATETIME(3) NOT NULL,
  request_id CHAR(26) NULL,
  actor_kind ENUM('user','voice','api','system','anonymous') NOT NULL,
  actor_user_id INT UNSIGNED NULL,
  api_token_id INT UNSIGNED NULL,
  call_session_id BIGINT UNSIGNED NULL,
  channel ENUM('web','assistant','voice','api','cron','webhook','migration') NOT NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(40) NULL,
  entity_id BIGINT UNSIGNED NULL,
  unidad_id INT NULL,
  before_json MEDIUMTEXT NULL,
  after_json MEDIUMTEXT NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  PRIMARY KEY (id),
  KEY idx_audit_entity (entity_type, entity_id),
  KEY idx_audit_actor (actor_user_id, occurred_at),
  KEY idx_audit_action (action, occurred_at),
  KEY idx_audit_time (occurred_at)
)
SQL, $ctx);

        SchemaHelper::createTable($pdo, 'tec_sequences', <<<'SQL'
(
  seq_name VARCHAR(40) NOT NULL,
  seq_year SMALLINT UNSIGNED NOT NULL,
  last_value INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (seq_name, seq_year)
)
SQL, $ctx);

        SchemaHelper::createTable($pdo, 'tec_idempotency_keys', <<<'SQL'
(
  scope VARCHAR(64) NOT NULL,
  idem_key VARCHAR(100) NOT NULL,
  request_hash CHAR(64) NOT NULL,
  status ENUM('processing','done') NOT NULL DEFAULT 'processing',
  response_code SMALLINT UNSIGNED NULL,
  response_body MEDIUMTEXT NULL,
  created_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  PRIMARY KEY (scope, idem_key),
  KEY idx_idem_expires (expires_at)
)
SQL, $ctx);

        SchemaHelper::createTable($pdo, 'tec_rate_limits', <<<'SQL'
(
  bucket VARCHAR(150) NOT NULL,
  window_start DATETIME NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (bucket, window_start),
  KEY idx_rate_window (window_start)
)
SQL, $ctx);

        SchemaHelper::createTable($pdo, 'tec_signed_links', <<<'SQL'
(
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  token_hash CHAR(64) NOT NULL,
  resource_type ENUM('invoice_pdf','report_pdf','task_attachment','expense_receipt') NOT NULL,
  resource_ref VARCHAR(255) NOT NULL,
  filename VARCHAR(190) NOT NULL,
  issued_to_user_id INT UNSIGNED NULL,
  issued_channel ENUM('whatsapp','email','web','voice') NOT NULL,
  max_uses SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  use_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  last_used_at DATETIME NULL,
  last_used_ip VARCHAR(45) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_signed_token (token_hash),
  KEY idx_signed_expires (expires_at)
)
SQL, $ctx);

        SchemaHelper::createTable($pdo, 'tec_pending_actions', <<<'SQL'
(
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  channel ENUM('assistant','voice','web') NOT NULL,
  action_type VARCHAR(60) NOT NULL,
  principal_user_id INT UNSIGNED NULL,
  call_session_id BIGINT UNSIGNED NULL,
  ia_conversacion_id INT UNSIGNED NULL,
  request_id CHAR(26) NULL,
  payload MEDIUMTEXT NOT NULL,
  summary TEXT NOT NULL,
  status ENUM('pending','confirmed','cancelled','expired','failed') NOT NULL DEFAULT 'pending',
  result_ref VARCHAR(64) NULL,
  error VARCHAR(500) NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  decided_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pending_public (public_id),
  KEY idx_pending_user (principal_user_id, status),
  KEY idx_pending_call (call_session_id, status),
  KEY idx_pending_conv (ia_conversacion_id, status)
)
SQL, $ctx);
    }

    public function rollbackNotes(): string
    {
        return 'DROP TABLE in reverse order: tec_pending_actions, tec_signed_links, tec_rate_limits, tec_idempotency_keys, tec_sequences, tec_audit_log';
    }
};
