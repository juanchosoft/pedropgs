<?php

declare(strict_types=1);

namespace Pgs\Ai\Attachments;

use Pgs\Core\Db;

final class AttachmentRepository
{
    private \PDO $pdo;

    public function __construct(?\PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Db::pdo();
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO tec_ia_adjuntos
             (public_id, user_id, conversacion_id, mensaje_id, original_name, kind, mime, size_bytes,
              sha256, storage_path, derived_path, text_chars, pages, width, height, status, reject_reason,
              created_at, attached_at, expires_at)
             VALUES
             (:pid, :uid, :cid, :mid, :name, :kind, :mime, :size,
              :sha, :spath, :dpath, :chars, :pages, :w, :h, :status, :rej,
              :created, :attached, :expires)"
        );

        $stmt->execute([
            ':pid' => $data['public_id'],
            ':uid' => $data['user_id'],
            ':cid' => $data['conversacion_id'] ?? null,
            ':mid' => $data['mensaje_id'] ?? null,
            ':name' => $data['original_name'],
            ':kind' => $data['kind'],
            ':mime' => $data['mime'],
            ':size' => $data['size_bytes'],
            ':sha' => $data['sha256'],
            ':spath' => $data['storage_path'],
            ':dpath' => $data['derived_path'] ?? null,
            ':chars' => $data['text_chars'] ?? null,
            ':pages' => $data['pages'] ?? null,
            ':w' => $data['width'] ?? null,
            ':h' => $data['height'] ?? null,
            ':status' => $data['status'] ?? 'uploaded',
            ':rej' => $data['reject_reason'] ?? null,
            ':created' => $data['created_at'],
            ':attached' => $data['attached_at'] ?? null,
            ':expires' => $data['expires_at'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findByPublicId(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tec_ia_adjuntos WHERE public_id = :pid LIMIT 1");
        $stmt->execute([':pid' => $publicId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findRecentBySha256(int $userId, string $sha256, int $withinSeconds = 60): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM tec_ia_adjuntos
             WHERE user_id = :uid AND sha256 = :sha AND status = 'uploaded'
               AND created_at >= DATE_SUB(NOW(), INTERVAL :sec SECOND)
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->bindValue(':uid', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':sha', $sha256, \PDO::PARAM_STR);
        $stmt->bindValue(':sec', $withinSeconds, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function countUploadsLastHour(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM tec_ia_adjuntos
             WHERE user_id = :uid AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $stmt->execute([':uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function markAttached(string $publicId, int $convId, int $msgId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE tec_ia_adjuntos
             SET status = 'attached', conversacion_id = :cid, mensaje_id = :mid, attached_at = NOW()
             WHERE public_id = :pid AND status = 'uploaded'"
        );
        $stmt->execute([':pid' => $publicId, ':cid' => $convId, ':mid' => $msgId]);
    }

    public function markDeleted(string $publicId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE tec_ia_adjuntos
             SET status = 'deleted'
             WHERE public_id = :pid"
        );
        $stmt->execute([':pid' => $publicId]);
    }

    public function deleteByConversation(int $convId): int
    {
        $stmt = $this->pdo->prepare(
            "UPDATE tec_ia_adjuntos
             SET status = 'deleted'
             WHERE conversacion_id = :cid"
        );
        $stmt->execute([':cid' => $convId]);
        return $stmt->rowCount();
    }

    public function findStale(int $unusedTtlHours = 24, int $attachedTtlDays = 30): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM tec_ia_adjuntos
             WHERE status != 'deleted'
               AND (
                 (status = 'uploaded' AND created_at < DATE_SUB(NOW(), INTERVAL :uh HOUR))
                 OR
                 (status = 'attached' AND attached_at < DATE_SUB(NOW(), INTERVAL :ad DAY))
                 OR
                 (expires_at < NOW())
               )"
        );
        $stmt->bindValue(':uh', $unusedTtlHours, \PDO::PARAM_INT);
        $stmt->bindValue(':ad', $attachedTtlDays, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
