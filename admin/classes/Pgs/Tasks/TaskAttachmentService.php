<?php

declare(strict_types=1);

namespace Pgs\Tasks;

use Pgs\Auth\Policy\TaskPolicy;
use Pgs\Auth\Principal;
use Pgs\Core\AuditLogger;
use Pgs\Core\Clock;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\ValidationException;
use Pgs\Core\PrivateStorage;

final class TaskAttachmentService
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    public function upload(Principal $p, int $taskId, array $file): array
    {
        $task = $this->loadTask($taskId);
        if (!TaskPolicy::canUpdate($p, $task) && !TaskPolicy::canCreate($p, $task['unidad_id'] !== null ? (int) $task['unidad_id'] : null)) {
            throw new ForbiddenException('Cannot add attachments to this task');
        }

        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ValidationException('No file uploaded or upload error occurred.');
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $origName = basename((string) ($file['name'] ?? 'attachment'));
        $sizeBytes = (int) ($file['size'] ?? 0);

        if ($sizeBytes <= 0 || $sizeBytes > 10 * 1024 * 1024) {
            throw new ValidationException('File size must be between 1 byte and 10 MB.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);

        $allowedMimes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
        ];

        if (!isset($allowedMimes[$mime])) {
            throw new ValidationException('Only JPG, PNG and PDF files are permitted.');
        }

        $ext = $allowedMimes[$mime];
        $sha256 = hash_file('sha256', $tmpPath);
        $storagePath = PrivateStorage::put('tasks', $ext, $tmpPath);
        $now = Clock::nowUtc()->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO tec_task_attachments (task_id, storage_path, original_name, mime, size_bytes, sha256, uploaded_by_user_id, created_at)
             VALUES (:task_id, :path, :name, :mime, :size, :sha, :user_id, :created_at)'
        );
        $stmt->execute([
            ':task_id' => $taskId,
            ':path' => $storagePath,
            ':name' => mb_substr($origName, 0, 190),
            ':mime' => $mime,
            ':size' => $sizeBytes,
            ':sha' => $sha256,
            ':user_id' => $p->userId,
            ':created_at' => $now,
        ]);
        $attachId = (int) $this->pdo->lastInsertId();

        // History
        $histStmt = $this->pdo->prepare(
            'INSERT INTO tec_task_history (task_id, event, from_status, to_status, changes, note, actor_kind, actor_user_id, channel, created_at)
             VALUES (:task_id, "attachment_added", :status, :status, :changes, :note, "user", :user_id, "web", :created_at)'
        );
        $histStmt->execute([
            ':task_id' => $taskId,
            ':status' => $task['status'],
            ':changes' => json_encode(['attachment_id' => $attachId, 'filename' => $origName], JSON_UNESCAPED_SLASHES),
            ':note' => "Attached file: {$origName}",
            ':user_id' => $p->userId,
            ':created_at' => $now,
        ]);

        AuditLogger::log($p, 'task.attachment_added', 'task', $taskId, null, [
            'attachment_id' => $attachId,
            'name' => $origName,
            'size' => $sizeBytes,
        ], $task['unidad_id'] !== null ? (int) $task['unidad_id'] : null);

        return [
            'id' => $attachId,
            'original_name' => $origName,
            'mime' => $mime,
            'size_bytes' => $sizeBytes,
            'created_at' => $now,
        ];
    }

    public function delete(Principal $p, int $attachmentId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, t.id as task_id, t.unidad_id, t.status, t.assignee_employee_id
             FROM tec_task_attachments a
             JOIN tec_tasks t ON t.id = a.task_id
             WHERE a.id = :id AND a.deleted_at IS NULL'
        );
        $stmt->execute([':id' => $attachmentId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            throw new NotFoundException('Attachment not found.');
        }

        if (!TaskPolicy::canUpdate($p, $row) && !$p->isSystemAdmin()) {
            throw new ForbiddenException('Cannot delete this attachment.');
        }

        $now = Clock::nowUtc()->format('Y-m-d H:i:s');
        $this->pdo->prepare('UPDATE tec_task_attachments SET deleted_at = :now WHERE id = :id')
            ->execute([':now' => $now, ':id' => $attachmentId]);

        AuditLogger::log($p, 'task.attachment_deleted', 'task', (int) $row['task_id'], [
            'attachment_id' => $attachmentId,
            'original_name' => $row['original_name'],
        ], null, $row['unidad_id'] !== null ? (int) $row['unidad_id'] : null);
    }

    public function stream(Principal $p, int $attachmentId): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, t.id as task_id, t.unidad_id, t.status, t.assignee_employee_id
             FROM tec_task_attachments a
             JOIN tec_tasks t ON t.id = a.task_id
             WHERE a.id = :id AND a.deleted_at IS NULL'
        );
        $stmt->execute([':id' => $attachmentId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            echo 'File not found.';
            exit;
        }

        if (!TaskPolicy::canView($p, $row)) {
            http_response_code(403);
            echo 'Forbidden.';
            exit;
        }

        $absPath = PrivateStorage::abs($row['storage_path']);
        if (!is_file($absPath)) {
            http_response_code(404);
            echo 'File not found on storage disk.';
            exit;
        }

        header('Content-Type: ' . $row['mime']);
        header('Content-Length: ' . (string) filesize($absPath));
        $filename = str_replace('"', '', $row['original_name']);
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=3600');
        readfile($absPath);
        exit;
    }

    public function copyFromIaAttachment(Principal $p, int $taskId, string $publicId): void
    {
        $task = $this->loadTask($taskId);
        if (!TaskPolicy::canUpdate($p, $task)) {
            throw new ForbiddenException('Cannot attach files to this task.');
        }

        $stmt = $this->pdo->prepare('SELECT * FROM tec_ia_adjuntos WHERE public_id = :pid AND user_id = :uid AND deleted_at IS NULL');
        $stmt->execute([':pid' => $publicId, ':uid' => $p->userId]);
        $ia = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$ia) {
            throw new NotFoundException('AI attachment not found.');
        }

        $sourceAbs = PrivateStorage::abs($ia['storage_path']);
        if (!is_file($sourceAbs)) {
            throw new NotFoundException('Source attachment not found.');
        }

        $ext = pathinfo($ia['original_name'], PATHINFO_EXTENSION) ?: 'bin';
        $newStoragePath = PrivateStorage::put('tasks', $ext, $sourceAbs);
        $now = Clock::nowUtc()->format('Y-m-d H:i:s');

        $ins = $this->pdo->prepare(
            'INSERT INTO tec_task_attachments (task_id, storage_path, original_name, mime, size_bytes, sha256, uploaded_by_user_id, created_at)
             VALUES (:task_id, :path, :name, :mime, :size, :sha, :user_id, :now)'
        );
        $ins->execute([
            ':task_id' => $taskId,
            ':path' => $newStoragePath,
            ':name' => $ia['original_name'],
            ':mime' => $ia['mime'],
            ':size' => $ia['size_bytes'],
            ':sha' => $ia['sha256'],
            ':user_id' => $p->userId,
            ':now' => $now,
        ]);
    }

    private function loadTask(int $taskId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM tec_tasks WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([':id' => $taskId]);
        $task = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$task) {
            throw new NotFoundException('Task not found.');
        }
        return $task;
    }
}
