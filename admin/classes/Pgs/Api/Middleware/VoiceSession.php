<?php

declare(strict_types=1);

namespace Pgs\Api\Middleware;

use Pgs\Api\Request;
use Pgs\Api\Response;
use Pgs\Auth\PrincipalFactory;
use Pgs\Voice\CallSessionService;

final class VoiceSession
{
    private CallSessionService $sessionService;

    public function __construct(?CallSessionService $sessionService = null)
    {
        $this->sessionService = $sessionService ?? new CallSessionService();
    }

    public function __invoke(Request $req, callable $next): Response
    {
        $convId = $req->header('X-Conversation-Id');
        if ($convId === null || !preg_match('/^[A-Za-z0-9_-]{8,80}$/', trim($convId))) {
            return Response::error(
                422,
                'validation_error',
                'Missing or invalid X-Conversation-Id header',
                ['fields' => ['conversation_id' => 'Header X-Conversation-Id must match ^[A-Za-z0-9_-]{8,80}$']]
            );
        }

        $cleanConvId = trim($convId);
        $session = $this->sessionService->resolveSession($cleanConvId);

        \Pgs\Core\RequestContext::setLang(in_array($session['lang'] ?? '', ['es', 'en'], true) ? (string) $session['lang'] : null);
        $sessionId = (int) ($session['id'] ?? 0);
        $apiTokenId = (int) ($req->attr('api_token_id') ?? 0);
        $scopes = (array) ($req->attr('api_token_scopes') ?? []);
        $resolvedUid = !empty($session['resolved_user_id']) ? (int) $session['resolved_user_id'] : null;
        $verification = (string) ($session['verification'] ?? 'none');

        if ($resolvedUid !== null) {
            $principal = PrincipalFactory::fromUserId(
                $resolvedUid,
                'voice',
                $verification,
                $sessionId,
                $apiTokenId,
                $scopes
            );
        } else {
            $principal = PrincipalFactory::anonymousVoice(
                $sessionId,
                $apiTokenId,
                $scopes
            );
        }

        $req = $req->withAttr('principal', $principal)
                   ->withAttr('call_session', $session)
                   ->withAttr('call_session_id', $sessionId)
                   ->withAttr('conversation_id', $cleanConvId)
                   ->withAttr('caller_e164', $session['caller_e164'] ?? null);

        $start = microtime(true);
        $response = $next($req);
        $latencyMs = (microtime(true) - $start) * 1000;

        if ($sessionId > 0) {
            $this->sessionService->recordCallLog(
                $sessionId,
                $req->path(),
                $response->getHttpCode(),
                $latencyMs,
                $req->json()
            );
        }

        return $response;
    }
}
