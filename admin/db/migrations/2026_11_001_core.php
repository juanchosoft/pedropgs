<?php

declare(strict_types=1);

use Pgs\Migrations\AbstractMigration;
use Pgs\Migrations\MigrationContext;
use Pgs\Migrations\SchemaHelper;

return new class extends AbstractMigration {
    public function id(): string
    {
        return '2026_11_001_core';
    }

    public function description(): string
    {
        return 'Settings, sequences, signed links and rate limits';
    }

    public function up(\PDO $pdo, MigrationContext $ctx): void
    {
        SchemaHelper::createTable($pdo, 'tec_settings', <<<'SQL'
(
  setting_key VARCHAR(100) NOT NULL,
  setting_value TEXT NULL,
  updated_by INT UNSIGNED NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (setting_key)
)
SQL, $ctx);

        SchemaHelper::createTable($pdo, 'tec_sequences', <<<'SQL'
(
  seq_name VARCHAR(50) NOT NULL,
  seq_year SMALLINT UNSIGNED NOT NULL,
  last_value INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (seq_name, seq_year)
)
SQL, $ctx);

        SchemaHelper::createTable($pdo, 'tec_signed_links', <<<'SQL'
(
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  token_hash CHAR(64) NOT NULL,
  resource_type VARCHAR(40) NOT NULL,
  resource_ref VARCHAR(255) NOT NULL,
  filename VARCHAR(190) NOT NULL,
  issued_to_user_id INT UNSIGNED NULL,
  issued_channel VARCHAR(20) NOT NULL,
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

        SchemaHelper::createTable($pdo, 'tec_rate_limits', <<<'SQL'
(
  bucket VARCHAR(150) NOT NULL,
  window_start DATETIME NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (bucket, window_start),
  KEY idx_rate_window (window_start)
)
SQL, $ctx);
    }
};
