<?php

declare(strict_types=1);

namespace Pgs\Api;

use Pgs\Api\Middleware\VoiceBearer;
use Pgs\Api\Middleware\WebhookSecret;
use Pgs\Core\AppLog;
use Pgs\Core\Clock;
use Pgs\Core\Db;
use Pgs\Core\RequestContext;
use Pgs\Core\SignedLinks;
use Pgs\Core\Ulid;
use Pgs\Core\Exceptions\ConflictException;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\GoneException;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\PgsException;
use Pgs\Core\Exceptions\RateLimitedException;
use Pgs\Core\Exceptions\UnauthorizedException;
use Pgs\Core\Exceptions\ValidationException;
use Pgs\Core\Json;
use Pgs\Core\RateLimiter;
use Pgs\Requests\RequestService;
use Pgs\Responders\SettingsRepository;
use Pgs\Voice\VoiceController;
use Pgs\Voice\WebhookController;

final class Kernel
{
    private Router $router;

    public function __construct(?Router $router = null)
    {
        $this->router = $router ?? new Router();
        $this->registerRoutes();
    }

    public function router(): Router
    {
        return $this->router;
    }

    private function registerRoutes(): void
    {
        $this->router->add('GET', '/health', function (Request $req): Response {
            try {
                Db::pdo()->query('SELECT 1')->fetch();
                $last = (new SettingsRepository())->get('cron.last_tick', '');
                $age = $last !== '' ? max(0, Clock::nowUtc()->getTimestamp() - (int) strtotime($last . ' UTC')) : null;

                return Response::ok(['db' => 'up', 'time' => Clock::nowUtc()->format('Y-m-d\TH:i:s\Z'), 'cron_age_s' => $age]);
            } catch (\Throwable $e) {
                return Response::error(503, 'service_unavailable', 'Database connection error');
            }
        });

        $this->router->add('POST', '/webhooks/elevenlabs/conversation-init', function (Request $req): Response {
            $data = (new WebhookController())->init($req->json());

            return Response::raw(200, Json::encode($data), 'application/json; charset=utf-8');
        }, [new WebhookSecret('webhook_init_secret')]);

        $this->router->add('POST', '/webhooks/elevenlabs/post-call', function (Request $req): Response {
            $result = (new WebhookController())->postCall((string) $req->header('ElevenLabs-Signature'), $req->rawBody());

            return Response::raw(200, Json::encode($result), 'application/json; charset=utf-8');
        });

        $this->router->add('GET', '/ack', fn (Request $req): Response => $this->limited($req, fn () => $this->ackPage($req)));
        $this->router->add('POST', '/ack', fn (Request $req): Response => $this->limited($req, fn () => $this->ackConfirm($req)));

        $voice = [new VoiceBearer()];
        $this->router->add('POST', '/voice/requests', fn (Request $req): Response => (new VoiceController())->register($req), $voice);
        $this->router->add('GET', '/voice/requests', fn (Request $req): Response => (new VoiceController())->open($req), $voice);
        $this->router->add('POST', '/voice/responder', fn (Request $req): Response => (new VoiceController())->responder($req), $voice);
    }

    /**
     * Public link endpoints are limited per address to stop token guessing and scanner floods.
     *
     * @param callable():Response $handler
     */
    private function limited(Request $req, callable $handler): Response
    {
        try {
            RateLimiter::hit('ack:' . $req->ip(), 30, 60);
        } catch (RateLimitedException) {
            return $this->html(429, 'Too many requests', 'Please wait a minute and try again.');
        }

        return $handler();
    }

    private function ackPage(Request $req): Response
    {
        $token = (string) $req->query('t', '');
        try {
            $link = SignedLinks::peek($token);
            $request = (new RequestService())->get((int) $link['resource_ref']);
        } catch (NotFoundException | GoneException) {
            return $this->html(410, 'Link no longer valid', 'This link has expired or was already used. Open the application to manage the request.');
        }
        $action = htmlspecialchars('ack?t=' . rawurlencode($token));
        $body = '<p style="margin:0 0 6px;color:#64748b">Request</p><p style="margin:0 0 14px;font-size:18px;font-weight:700;color:#0B0F19">'
            . htmlspecialchars((string) $request['code']) . '</p><p style="margin:0 0 18px;color:#0B0F19">' . htmlspecialchars((string) $request['summary']) . '</p>'
            . '<form method="post" action="' . $action . '"><button type="submit" style="background:#E10600;color:#fff;border:0;border-radius:8px;padding:12px 22px;font-size:15px;font-weight:700;cursor:pointer">I have it</button></form>';

        return $this->html(200, 'Acknowledge request', null, $body);
    }

    private function ackConfirm(Request $req): Response
    {
        $token = (string) $req->query('t', '');
        try {
            $link = SignedLinks::consume($token);
            (new RequestService())->acknowledge((int) $link['resource_ref'], 'email ack');
        } catch (NotFoundException | GoneException) {
            return $this->html(410, 'Link no longer valid', 'This link has expired or was already used. Open the application to manage the request.');
        }

        return $this->html(200, 'Thank you', 'The request was acknowledged and the alerts have stopped.');
    }

    private function html(int $status, string $title, ?string $message, ?string $body = null): Response
    {
        $content = $body ?? '<p style="margin:0;color:#0B0F19">' . htmlspecialchars((string) $message) . '</p>';
        $page = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
            . '<title>' . htmlspecialchars($title) . '</title></head><body style="margin:0;padding:24px;background:#F6F7FB;font-family:Arial,Helvetica,sans-serif">'
            . '<div style="max-width:440px;margin:40px auto;background:#fff;border:1px solid #e7eaf1;border-radius:14px;overflow:hidden">'
            . '<div style="background:#0B0F19;padding:16px 22px;border-bottom:3px solid #E10600"><span style="color:#fff;font-size:18px;font-weight:700">PGS <span style="color:#E10600">CENTRUM</span></span></div>'
            . '<div style="padding:22px"><h2 style="margin:0 0 14px;font-size:18px;color:#0B0F19">' . htmlspecialchars($title) . '</h2>' . $content . '</div></div></body></html>';

        return Response::raw($status, $page, 'text/html; charset=utf-8')->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'no-store');
    }

    public function handle(Request $r): Response
    {
        $rawReqId = $r->header('X-Request-Id');
        $requestId = ($rawReqId !== null && preg_match('/^[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}$/', $rawReqId)) ? $rawReqId : Ulid::generate();
        RequestContext::set($requestId);
        RequestContext::setLang(null);
        $r = $r->withAttr('request_id', $requestId);

        try {
            $response = $this->router->dispatch($r);
        } catch (ValidationException $e) {
            $response = Response::error(422, 'validation_error', $e->getMessage(), ['fields' => $e->fields()]);
        } catch (ForbiddenException $e) {
            $response = Response::error(403, 'forbidden', $e->getMessage());
        } catch (NotFoundException $e) {
            $response = Response::error(404, 'not_found', $e->getMessage());
        } catch (ConflictException $e) {
            $response = Response::error(409, 'conflict', $e->getMessage(), $e->fields());
        } catch (RateLimitedException $e) {
            $response = Response::error(429, 'rate_limited', $e->getMessage(), ['retry_after' => $e->retryAfter()]);
        } catch (UnauthorizedException $e) {
            $response = Response::error(401, 'unauthorized', $e->getMessage());
        } catch (GoneException $e) {
            $response = Response::error(410, 'gone', $e->getMessage());
        } catch (PgsException $e) {
            $response = Response::error($e->httpStatus(), $e->errorCode(), $e->getMessage(), $e->fields() ?: null);
        } catch (\Throwable $e) {
            AppLog::error('api', $e, ['method' => $r->method(), 'path' => $r->path(), 'trace' => mb_substr($e->getTraceAsString(), 0, 1500)]);
            $response = Response::error(500, 'internal_error', 'Internal server error');
        }

        $response->setRequestId($requestId);

        return $response;
    }
}
