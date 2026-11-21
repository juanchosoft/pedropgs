<?php

declare(strict_types=1);

use Pgs\Migrations\AbstractMigration;
use Pgs\Migrations\MigrationContext;
use Pgs\Migrations\SchemaHelper;

return new class extends AbstractMigration {
    public function id(): string
    {
        return '2026_11_007_outbox';
    }

    public function description(): string
    {
        return 'Notification outbox (email and WhatsApp)';
    }

    public function up(\PDO $pdo, MigrationContext $ctx): void
    {
        SchemaHelper::createTable($pdo, 'tec_outbox', <<<'SQL'
(
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  channel ENUM('email','whatsapp') NOT NULL,
  recipient VARCHAR(190) NOT NULL,
  template VARCHAR(60) NOT NULL,
  lang CHAR(2) NOT NULL DEFAULT 'en',
  payload TEXT NOT NULL,
  request_id BIGINT UNSIGNED NULL,
  dedupe_key VARCHAR(120) NOT NULL,
  status ENUM('pending','sending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  next_attempt_at DATETIME NOT NULL,
  locked_at DATETIME NULL,
  sent_at DATETIME NULL,
  provider_message_id VARCHAR(100) NULL,
  last_error VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dedupe (dedupe_key),
  KEY idx_due (status, next_attempt_at),
  KEY idx_request (request_id)
)
SQL, $ctx);
    }
};
