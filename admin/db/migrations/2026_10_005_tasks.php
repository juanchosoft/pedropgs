<?php

declare(strict_types=1);

use Pgs\Migrations\AbstractMigration;
use Pgs\Migrations\MigrationContext;
use Pgs\Migrations\SchemaHelper;

return new class extends AbstractMigration {
    public function id(): string
    {
        return '2026_10_005_tasks';
    }

    public function description(): string
    {
        return 'Tasks, task history, comments and attachments';
    }

    public function up(\PDO $pdo, MigrationContext $ctx): void
    {
        SchemaHelper::createTable($pdo, 'tec_tasks', <<<'SQL'
(
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(20) NOT NULL,
  origin ENUM('manual','service_request','phone','assistant','migration','phone_escalation') NOT NULL,
  unidad_id INT NULL,
  location_label VARCHAR(120) NULL,
  service_id INT UNSIGNED NULL,
  requested_by_user_id INT UNSIGNED NULL,
  requested_by_phone VARCHAR(20) CHARACTER SET ascii NULL,
  requested_by_name VARCHAR(150) NULL,
  requested_verification ENUM('session','pin','phone','none') NOT NULL DEFAULT 'session',
  title VARCHAR(160) NOT NULL,
  description TEXT NULL,
  priority ENUM('low','normal','high','urgent') NOT NULL DEFAULT 'normal',
  status ENUM('pending','assigned','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  assignee_employee_id INT NULL,
  required_date DATE NULL,
  due_date DATE NULL,
  cancel_reason VARCHAR(255) NULL,
  completed_at DATETIME NULL,
  cancelled_at DATETIME NULL,
  due_soon_notified_at DATETIME NULL,
  legacy_requerimiento_id INT NULL,
  legacy_updated_at DATETIME NULL,
  needs_review TINYINT(1) NOT NULL DEFAULT 0,
  call_session_id BIGINT UNSIGNED NULL,
  created_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  row_version INT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_tasks_code (code),
  UNIQUE KEY uq_tasks_legacy (legacy_requerimiento_id),
  KEY idx_tasks_unit_status (unidad_id, status),
  KEY idx_tasks_assignee_status (assignee_employee_id, status),
  KEY idx_tasks_status_due (status, due_date),
  KEY idx_tasks_created (created_at)
)
SQL, $ctx, [
            'fk_tasks_unidad' => 'FOREIGN KEY (unidad_id) REFERENCES tbl_unidades (id) ON DELETE SET NULL',
            'fk_tasks_service' => 'FOREIGN KEY (service_id) REFERENCES tec_services (id) ON DELETE SET NULL',
            'fk_tasks_assignee' => 'FOREIGN KEY (assignee_employee_id) REFERENCES tec_employee (id) ON DELETE SET NULL',
        ]);

        SchemaHelper::createTable($pdo, 'tec_task_history', <<<'SQL'
(
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  task_id INT UNSIGNED NOT NULL,
  event ENUM('created','updated','assigned','unassigned','status_changed','commented','attachment_added','migrated','notified') NOT NULL,
  from_status VARCHAR(20) NULL,
  to_status VARCHAR(20) NULL,
  changes MEDIUMTEXT NULL,
  note VARCHAR(500) NULL,
  actor_kind ENUM('user','voice','api','system','anonymous') NOT NULL,
  actor_user_id INT UNSIGNED NULL,
  channel VARCHAR(20) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_task_history_task (task_id, created_at)
)
SQL, $ctx, [
            'fk_task_history_task' => 'FOREIGN KEY (task_id) REFERENCES tec_tasks (id) ON DELETE CASCADE',
        ]);

        SchemaHelper::createTable($pdo, 'tec_task_comments', <<<'SQL'
(
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  task_id INT UNSIGNED NOT NULL,
  author_user_id INT UNSIGNED NULL,
  body TEXT NOT NULL,
  is_internal TINYINT(1) NOT NULL DEFAULT 0,
  channel VARCHAR(20) NOT NULL,
  created_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_task_comments_task (task_id, created_at)
)
SQL, $ctx, [
            'fk_task_comments_task' => 'FOREIGN KEY (task_id) REFERENCES tec_tasks (id) ON DELETE CASCADE',
        ]);

        SchemaHelper::createTable($pdo, 'tec_task_attachments', <<<'SQL'
(
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  task_id INT UNSIGNED NOT NULL,
  storage_path VARCHAR(255) NOT NULL,
  original_name VARCHAR(190) NOT NULL,
  mime VARCHAR(100) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  sha256 CHAR(64) NOT NULL,
  uploaded_by_user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_task_attach_task (task_id)
)
SQL, $ctx, [
            'fk_task_attach_task' => 'FOREIGN KEY (task_id) REFERENCES tec_tasks (id) ON DELETE CASCADE',
        ]);
    }

    public function rollbackNotes(): string
    {
        return 'DROP TABLE tec_task_attachments, tec_task_comments, tec_task_history, tec_tasks';
    }
};
