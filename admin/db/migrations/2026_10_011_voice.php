<?php

declare(strict_types=1);

use Pgs\Migrations\AbstractMigration;
use Pgs\Migrations\MigrationContext;
use Pgs\Migrations\SchemaHelper;

return new class extends AbstractMigration {
    public function id(): string
    {
        return '2026_10_011_voice';
    }

    public function description(): string
    {
        return 'Voice call sessions and call logs';
    }

    public function up(\PDO $pdo, MigrationContext $ctx): void
    {
        SchemaHelper::createTable($pdo, 'tec_call_sessions', <<<'SQL'
(
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id VARCHAR(80) NULL,
  call_sid VARCHAR(120) NULL,
  agent_id VARCHAR(80) NULL,
  caller_e164 VARCHAR(20) CHARACTER SET ascii NULL,
  caller_raw VARCHAR(80) NULL,
  called_e164 VARCHAR(20) CHARACTER SET ascii NULL,
  caller_trust ENUM('cli','claimed','none') NOT NULL,
  resolved_user_id INT UNSIGNED NULL,
  caller_type ENUM('system_admin','unit_admin','employee','unknown') NOT NULL DEFAULT 'unknown',
  verification ENUM('none','phone','pin') NOT NULL DEFAULT 'none',
  pin_verified_at DATETIME NULL,
  pin_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  lang ENUM('es','en') NOT NULL DEFAULT 'es',
  status ENUM('active','ended','expired') NOT NULL DEFAULT 'active',
  origin ENUM('init_webhook','lazy') NOT NULL,
  started_at DATETIME NOT NULL,
  last_activity_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  ended_at DATETIME NULL,
  outcome VARCHAR(40) NULL,
  summary TEXT NULL,
  transferred_to VARCHAR(20) NULL,
  duration_secs INT UNSIGNED NULL,
  postcall_received_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_call_conversation (conversation_id),
  KEY idx_call_sid (call_sid),
  KEY idx_call_caller (caller_e164, started_at),
  KEY idx_call_status (status, expires_at)
)
SQL, $ctx);

        SchemaHelper::createTable($pdo, 'tec_call_logs', <<<'SQL'
(
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  call_session_id BIGINT UNSIGNED NOT NULL,
  kind ENUM('tool','event','webhook') NOT NULL,
  name VARCHAR(80) NOT NULL,
  input_redacted TEXT NULL,
  result_code VARCHAR(40) NOT NULL,
  latency_ms INT UNSIGNED NULL,
  created_at DATETIME(3) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_call_logs_session (call_session_id, created_at)
)
SQL, $ctx, [
            'fk_call_logs_session' => 'FOREIGN KEY (call_session_id) REFERENCES tec_call_sessions (id) ON DELETE CASCADE',
        ]);
    }

    public function rollbackNotes(): string
    {
        return 'DROP TABLE tec_call_logs, tec_call_sessions';
    }
};
