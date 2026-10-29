<?php

declare(strict_types=1);

namespace Pgs\Core;

use Pgs\Auth\Principal;

final class AuditLogger
{
    /**
     * @param array<string,mixed>|null $before
     * @param array<string,mixed>|null $after
     */
    public static function log(
        Principal $p,
        string $action,
        ?string $entityType,
        ?int $entityId,
        ?array $before = null,
        ?array $after = null,
        ?int $unitId = null
    ): void {
        $actorKind = match ($p->kind) {
            'voice' => 'voice',
            'system' => 'system',
            'anonymous' => 'anonymous',
            default => $p->channel === 'api' ? 'api' : 'user',
        };
        $stmt = Db::pdo()->prepare(
            'INSERT INTO tec_audit_log
             (occurred_at, request_id, actor_kind, actor_user_id, api_token_id, call_session_id, channel, action,
              entity_type, entity_id, unidad_id, before_json, after_json, ip, user_agent)
             VALUES (:t, :rid, :ak, :uid, :tid, :cid, :ch, :a, :et, :eid, :unit, :b, :af, :ip, :ua)'
        );
        $channel = in_array($p->channel, ['web','assistant','voice','api','cron','webhook','migration'], true)
            ? $p->channel
            : 'web';
        $stmt->execute([
            ':t' => Clock::nowUtc()->format('Y-m-d H:i:s.v'),
            ':rid' => RequestContext::id(),
            ':ak' => $actorKind,
            ':uid' => $p->userId,
            ':tid' => $p->apiTokenId,
            ':cid' => $p->callSessionId,
            ':ch' => $channel,
            ':a' => substr($action, 0, 80),
            ':et' => $entityType,
            ':eid' => $entityId,
            ':unit' => $unitId,
            ':b' => $before === null ? null : Json::encode($before),
            ':af' => $after === null ? null : Json::encode($after),
            ':ip' => RequestContext::ip(),
            ':ua' => RequestContext::userAgent(),
        ]);
    }
}
