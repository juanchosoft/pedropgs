<?php

declare(strict_types=1);

use Pgs\Migrations\AbstractMigration;
use Pgs\Migrations\MigrationContext;
use Pgs\Migrations\SchemaHelper;

return new class extends AbstractMigration {
    public function id(): string
    {
        return '2026_10_012_ia';
    }

    public function description(): string
    {
        return 'AI assistant conversations, messages, tool logs, and attachments';
    }

    public function up(\PDO $pdo, MigrationContext $ctx): void
    {
        SchemaHelper::createTable($pdo, 'tec_ia_conversaciones', <<<'SQL'
(
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  titulo VARCHAR(180) NULL,
  activa TINYINT(1) NOT NULL DEFAULT 1,
  dt_create DATETIME NOT NULL,
  dt_update DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_ia_conv_user (user_id, dt_update)
)
SQL, $ctx);

        SchemaHelper::createTable($pdo, 'tec_ia_mensajes', <<<'SQL'
(
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversacion_id INT UNSIGNED NOT NULL,
  rol ENUM('user','assistant') NOT NULL,
  contenido TEXT NULL,
  contenido_api LONGTEXT NOT NULL,
  origen ENUM('texto','voz') NOT NULL DEFAULT 'texto',
  tokens_entrada INT UNSIGNED NULL,
  tokens_salida INT UNSIGNED NULL,
  tokens_cache_lectura INT UNSIGNED NULL,
  dt_create DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_ia_msg_conv (conversacion_id, id)
)
SQL, $ctx, [
            'fk_ia_msg_conv' => 'FOREIGN KEY (conversacion_id) REFERENCES tec_ia_conversaciones (id) ON DELETE CASCADE',
        ]);

        SchemaHelper::createTable($pdo, 'tec_ia_tool_logs', <<<'SQL'
(
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  conversacion_id INT UNSIGNED NULL,
  tool_nombre VARCHAR(80) NOT NULL,
  tool_input TEXT NULL,
  filas_devueltas INT UNSIGNED NULL,
  duracion_ms INT UNSIGNED NULL,
  exito TINYINT(1) NOT NULL DEFAULT 1,
  error VARCHAR(500) NULL,
  pending_action_id BIGINT UNSIGNED NULL,
  dt_create DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_ia_log_user (user_id, dt_create),
  KEY idx_ia_log_tool (tool_nombre)
)
SQL, $ctx);

        SchemaHelper::createTable($pdo, 'tec_ia_adjuntos', <<<'SQL'
(
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id CHAR(26) NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  conversacion_id INT UNSIGNED NULL,
  mensaje_id INT UNSIGNED NULL,
  original_name VARCHAR(190) NOT NULL,
  kind ENUM('image','pdf','text','docx') NOT NULL,
  mime VARCHAR(100) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  sha256 CHAR(64) NOT NULL,
  storage_path VARCHAR(255) NOT NULL,
  derived_path VARCHAR(255) NULL,
  text_chars INT UNSIGNED NULL,
  pages SMALLINT UNSIGNED NULL,
  width SMALLINT UNSIGNED NULL,
  height SMALLINT UNSIGNED NULL,
  status ENUM('uploaded','attached','deleted','rejected') NOT NULL DEFAULT 'uploaded',
  reject_reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  attached_at DATETIME NULL,
  expires_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ia_adj_public (public_id),
  KEY idx_ia_adj_user (user_id, status, created_at),
  KEY idx_ia_adj_conv (conversacion_id),
  KEY idx_ia_adj_expires (status, expires_at)
)
SQL, $ctx, [
            'fk_ia_adj_conv' => 'FOREIGN KEY (conversacion_id) REFERENCES tec_ia_conversaciones (id) ON DELETE SET NULL',
            'fk_ia_adj_msg' => 'FOREIGN KEY (mensaje_id) REFERENCES tec_ia_mensajes (id) ON DELETE SET NULL',
        ]);
    }

    public function rollbackNotes(): string
    {
        return 'DROP TABLE tec_ia_adjuntos, tec_ia_tool_logs, tec_ia_mensajes, tec_ia_conversaciones';
    }
};
