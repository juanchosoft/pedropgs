<?php

declare(strict_types=1);

use Pgs\Migrations\AbstractMigration;
use Pgs\Migrations\MigrationContext;
use Pgs\Migrations\SchemaHelper;

return new class extends AbstractMigration {
    public function id(): string
    {
        return '2026_10_009_notifications';
    }

    public function description(): string
    {
        return 'Notifications, outbox, preferences, and type defaults matrix';
    }

    public function up(\PDO $pdo, MigrationContext $ctx): void
    {
        SchemaHelper::createTable($pdo, 'tec_notifications', <<<'SQL'
(
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  event_type VARCHAR(60) NOT NULL,
  title VARCHAR(190) NOT NULL,
  body VARCHAR(1000) NOT NULL,
  link_url VARCHAR(255) NULL,
  entity_type VARCHAR(40) NULL,
  entity_id BIGINT UNSIGNED NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_notif_user_unread (user_id, read_at, created_at)
)
SQL, $ctx);

        SchemaHelper::createTable($pdo, 'tec_notification_outbox', <<<'SQL'
(
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_type VARCHAR(60) NOT NULL,
  entity_type VARCHAR(40) NULL,
  entity_id BIGINT UNSIGNED NULL,
  channel ENUM('whatsapp','email','inapp') NOT NULL,
  recipient_user_id INT UNSIGNED NULL,
  recipient_address VARCHAR(190) NULL,
  template_key VARCHAR(60) NOT NULL,
  lang ENUM('es','en') NOT NULL,
  payload MEDIUMTEXT NOT NULL,
  attachment_path VARCHAR(255) NULL,
  dedupe_key VARCHAR(190) NOT NULL,
  status ENUM('pending','sending','sent','delivered','read','failed','dead','skipped') NOT NULL DEFAULT 'pending',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts TINYINT UNSIGNED NOT NULL DEFAULT 6,
  next_attempt_at DATETIME NOT NULL,
  locked_until DATETIME NULL,
  last_error VARCHAR(500) NULL,
  provider_message_id VARCHAR(128) NULL,
  created_at DATETIME NOT NULL,
  sent_at DATETIME NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_outbox_dedupe (dedupe_key),
  KEY idx_outbox_due (status, next_attempt_at),
  KEY idx_outbox_provider (provider_message_id)
)
SQL, $ctx);

        SchemaHelper::createTable($pdo, 'tec_notification_prefs', <<<'SQL'
(
  user_id INT UNSIGNED NOT NULL,
  event_type VARCHAR(60) NOT NULL,
  channel ENUM('whatsapp','email','inapp') NOT NULL,
  enabled TINYINT(1) NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (user_id, event_type, channel)
)
SQL, $ctx);

        SchemaHelper::createTable($pdo, 'tec_notification_type_defaults', <<<'SQL'
(
  user_type VARCHAR(30) NOT NULL,
  event_type VARCHAR(60) NOT NULL,
  channel ENUM('whatsapp','email','inapp') NOT NULL,
  enabled TINYINT(1) NOT NULL,
  PRIMARY KEY (user_type, event_type, channel)
)
SQL, $ctx);

        SchemaHelper::createTable($pdo, 'tec_whatsapp_webhook_events', <<<'SQL'
(
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  dedupe_hash CHAR(64) NOT NULL,
  wamid VARCHAR(128) NULL,
  event_kind ENUM('status','message','other') NOT NULL,
  status VARCHAR(20) NULL,
  payload MEDIUMTEXT NOT NULL,
  received_at DATETIME NOT NULL,
  processed_at DATETIME NULL,
  error VARCHAR(500) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wa_events_dedupe (dedupe_hash),
  KEY idx_wa_events_wamid (wamid)
)
SQL, $ctx);

        // Seed notification type defaults
        $matrix = [
            'task.created' => ['SuperAdministrador' => [0, 1, 1], 'Administrador' => [1, 1, 1], 'Staff' => [0, 0, 1]],
            'task.assigned' => ['SuperAdministrador' => [0, 0, 1], 'Administrador' => [0, 0, 0], 'Staff' => [1, 0, 1]],
            'task.status_changed' => ['SuperAdministrador' => [0, 0, 1], 'Administrador' => [1, 1, 1], 'Staff' => [0, 0, 1]],
            'task.due_soon' => ['SuperAdministrador' => [0, 1, 1], 'Administrador' => [0, 0, 0], 'Staff' => [1, 0, 1]],
            'service_request.created' => ['SuperAdministrador' => [1, 1, 1], 'Administrador' => [0, 1, 1], 'Staff' => [0, 0, 0]],
            'reservation.confirmed' => ['SuperAdministrador' => [0, 1, 1], 'Administrador' => [1, 1, 1], 'Staff' => [1, 0, 1]],
            'reservation.cancelled' => ['SuperAdministrador' => [0, 1, 1], 'Administrador' => [1, 1, 1], 'Staff' => [1, 0, 1]],
            'absence.requested' => ['SuperAdministrador' => [1, 1, 1], 'Administrador' => [0, 0, 0], 'Staff' => [0, 0, 0]],
            'absence.decided' => ['SuperAdministrador' => [0, 0, 0], 'Administrador' => [0, 0, 0], 'Staff' => [1, 0, 1]],
            'invoice.issued' => ['SuperAdministrador' => [0, 0, 1], 'Administrador' => [1, 1, 1], 'Staff' => [0, 0, 0]],
            'invoice.overdue' => ['SuperAdministrador' => [0, 1, 1], 'Administrador' => [1, 1, 1], 'Staff' => [0, 0, 0]],
            'document.delivery' => ['SuperAdministrador' => [1, 1, 0], 'Administrador' => [1, 1, 0], 'Staff' => [1, 1, 0]],
            'phone.message' => ['SuperAdministrador' => [1, 1, 1], 'Administrador' => [0, 0, 0], 'Staff' => [0, 0, 0]],
            'voice.pin_locked' => ['SuperAdministrador' => [1, 1, 1], 'Administrador' => [1, 1, 1], 'Staff' => [1, 1, 1]],
            'expense.registered' => ['SuperAdministrador' => [0, 0, 1], 'Administrador' => [0, 0, 0], 'Staff' => [0, 0, 0]],
        ];

        $insStmt = $pdo->prepare(
            "INSERT IGNORE INTO tec_notification_type_defaults (user_type, event_type, channel, enabled)
             VALUES (:ut, :et, :ch, :en)"
        );

        $channels = ['whatsapp', 'email', 'inapp'];
        $seeded = 0;
        foreach ($matrix as $eventType => $types) {
            foreach ($types as $userType => $flags) {
                foreach ($channels as $idx => $channel) {
                    $insStmt->execute([
                        ':ut' => $userType,
                        ':et' => $eventType,
                        ':ch' => $channel,
                        ':en' => $flags[$idx],
                    ]);
                    $seeded++;
                }
            }
        }
        $ctx->out("Seeded tec_notification_type_defaults ($seeded combinations)");
    }

    public function rollbackNotes(): string
    {
        return 'DROP TABLE tec_whatsapp_webhook_events, tec_notification_type_defaults, tec_notification_prefs, tec_notification_outbox, tec_notifications';
    }
};
