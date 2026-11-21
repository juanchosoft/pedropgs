<?php

declare(strict_types=1);

use Pgs\Migrations\AbstractMigration;
use Pgs\Migrations\MigrationContext;
use Pgs\Migrations\SchemaHelper;

return new class extends AbstractMigration {
    public function id(): string
    {
        return '2026_11_005_requests';
    }

    public function description(): string
    {
        return 'Service requests and their history';
    }

    public function up(\PDO $pdo, MigrationContext $ctx): void
    {
        SchemaHelper::createTable($pdo, 'tec_requests', <<<'SQL'
(
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL,
  unidad_id INT NULL,
  property_text VARCHAR(190) NULL,
  caller_e164 VARCHAR(20) NULL,
  caller_name VARCHAR(120) NULL,
  summary VARCHAR(300) NOT NULL,
  details TEXT NULL,
  priority ENUM('urgent','high','normal','low') NOT NULL DEFAULT 'normal',
  status ENUM('received','assigned','in_progress','completed','cancelled') NOT NULL DEFAULT 'received',
  assigned_user_id INT UNSIGNED NULL,
  origin ENUM('phone','app') NOT NULL DEFAULT 'phone',
  created_via ENUM('tool','urgent_flow','post_call','app') NOT NULL DEFAULT 'tool',
  call_id BIGINT UNSIGNED NULL,
  dedupe_key CHAR(40) NULL,
  needs_review TINYINT(1) NOT NULL DEFAULT 0,
  acknowledged_at DATETIME NULL,
  received_at DATETIME NOT NULL,
  assigned_at DATETIME NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  last_alert_at DATETIME NULL,
  next_alert_at DATETIME NULL,
  alerts_sent SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  escalation_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_code (code),
  UNIQUE KEY uq_dedupe (dedupe_key),
  KEY idx_next_alert (status, next_alert_at),
  KEY idx_list (status, priority, received_at),
  KEY idx_unidad (unidad_id, status),
  KEY idx_caller (caller_e164, status),
  KEY idx_call (call_id)
)
SQL, $ctx);

        SchemaHelper::createTable($pdo, 'tec_request_history', <<<'SQL'
(
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id BIGINT UNSIGNED NOT NULL,
  event ENUM('created','status','assigned','ack','alert','escalated','exhausted','merged','edited','note') NOT NULL,
  from_status VARCHAR(20) NULL,
  to_status VARCHAR(20) NULL,
  actor_user_id INT UNSIGNED NULL,
  actor_label VARCHAR(100) NULL,
  note VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_request (request_id, created_at)
)
SQL, $ctx, [
            'fk_request_history_request' => 'FOREIGN KEY (request_id) REFERENCES tec_requests (id) ON DELETE CASCADE',
        ]);
    }
};
