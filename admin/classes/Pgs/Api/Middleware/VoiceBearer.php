<?php

declare(strict_types=1);

namespace Pgs\Api\Middleware;

use Pgs\Api\Request;
use Pgs\Api\Response;
use Pgs\Core\Config;
use Pgs\Core\RequestContext;
use Pgs\Responders\SettingsRepository;
use Pgs\Voice\Say;
use Pgs\Voice\WebhookController;

final class VoiceBearer
{
    public function __invoke(Request $req, callable $next): Response
    {
        $expected = (string) Config::get('api', 'voice_token', '');
        $header = (string) $req->header('Authorization');
        $provided = preg_match('/^Bearer\s+(.+)$/i', $header, $m) ? trim($m[1]) : '';
        if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
            return Response::error(401, 'unauthorized', 'Invalid or missing token');
        }

        $conversationId = trim((string) $req->header('X-Conversation-Id'));
        if (!preg_match('/^[A-Za-z0-9_-]{8,100}$/', $conversationId)) {
            return Response::error(422, 'validation_error', 'Missing or invalid X-Conversation-Id header', [
                'fields' => ['conversation_id' => 'Header X-Conversation-Id must match ^[A-Za-z0-9_-]{8,100}$'],
            ]);
        }

        if (!(new SettingsRepository())->bool('voice_enabled', true)) {
            return Response::ok([
                'created' => false,
                'mode' => 'message',
                'next' => 'none',
                'say' => Say::text('fallback', Say::lang($req->header('X-Pgs-Lang'))),
            ]);
        }

        $call = (new WebhookController())->ensureCall($conversationId);
        if (is_string($call['lang'] ?? null)) {
            RequestContext::setLang($call['lang']);
        }

        return $next($req->withAttr('call', $call));
    }
}
