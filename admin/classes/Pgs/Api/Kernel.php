<?php

declare(strict_types=1);

namespace Pgs\Api;

use Pgs\Core\Clock;
use Pgs\Core\Config;
use Pgs\Core\Db;
use Pgs\Core\Ulid;
use Pgs\Core\Exceptions\ConfirmationRequiredException;
use Pgs\Core\Exceptions\ConflictException;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\GoneException;
use Pgs\Core\Exceptions\NotFoundException;
use Pgs\Core\Exceptions\PinLockedException;
use Pgs\Core\Exceptions\RateLimitedException;
use Pgs\Core\Exceptions\UnauthorizedException;
use Pgs\Core\Exceptions\ValidationException;
use Pgs\Core\Exceptions\VerificationRequiredException;

final class Kernel
{
    private Router $router;

    public function __construct(?Router $router = null)
    {
        $this->router = $router ?? new Router();
        $this->registerBaseRoutes();
    }

    public function router(): Router
    {
        return $this->router;
    }

    private function registerBaseRoutes(): void
    {
        // Health check (IA_05 §3.1)
        $this->router->add('GET', '/health', function (Request $req): Response {
            try {
                $pdo = Db::pdo();
                $pdo->query('SELECT 1')->fetch();
                return Response::ok([
                    'status' => 'ok',
                    'db' => 'ok',
                    'time' => Clock::nowUtc()->format('Y-m-d\TH:i:s\Z'),
                    'version' => (string) Config::get('app', 'version', '1.0.0'),
                ]);
            } catch (\Throwable $e) {
                return Response::error(503, 'service_unavailable', 'Database connection error');
            }
        });

        // ElevenLabs conversation-init webhook (IA_05 §3.2)
        $this->router->add(
            'POST',
            '/webhooks/elevenlabs/conversation-init',
            function (Request $req): Response {
                try {
                    $service = new \Pgs\Voice\CallSessionService();
                    $data = $service->initWebhook($req->json());
                } catch (\Pgs\Core\Exceptions\ForbiddenException $e) {
                    throw $e;
                } catch (\Throwable $e) {
                    \Pgs\Core\AppLog::error('conversation-init', $e);
                    $data = ['type' => 'conversation_initiation_client_data', 'dynamic_variables' => ['caller_type' => 'unknown', 'verification_level' => 'none', 'caller_lang' => 'es', 'business_open' => 'false', 'pgs_session_ok' => 'false']];
                }
                return Response::raw(200, \Pgs\Core\Json::encode($data), 'application/json; charset=utf-8');
            },
            [
                new \Pgs\Api\Middleware\WebhookSecret('webhook_init_secret'),
                new \Pgs\Api\Middleware\RateLimit('webhook:{ip}', 120, 60),
            ]
        );

        // ElevenLabs post-call webhook (IA_05 §3.2)
        $this->router->add(
            'POST',
            '/webhooks/elevenlabs/post-call',
            function (Request $req): Response {
                $service = new \Pgs\Voice\CallSessionService();
                $sig = $req->header('ElevenLabs-Signature') ?? '';
                $raw = $req->rawBody();
                $data = $service->handlePostCall($sig, $raw);
                return Response::ok($data);
            },
            [
                new \Pgs\Api\Middleware\RateLimit('webhook:{ip}', 120, 60),
            ]
        );

        // WhatsApp webhooks (IA_05 §3.2)
        $this->router->add(
            'GET',
            '/webhooks/whatsapp',
            function (Request $req): Response {
                $handler = new \Pgs\Notifications\WhatsAppWebhookHandler();
                return $handler->handleVerify($req);
            },
            [
                new \Pgs\Api\Middleware\RateLimit('webhook:{ip}', 120, 60),
            ]
        );

        $this->router->add(
            'POST',
            '/webhooks/whatsapp',
            function (Request $req): Response {
                $handler = new \Pgs\Notifications\WhatsAppWebhookHandler();
                return $handler->handleWebhook($req);
            },
            [
                new \Pgs\Api\Middleware\RateLimit('webhook:{ip}', 120, 60),
            ]
        );

        // Voice session routes (IA_05 §3.4)
        $voiceReadStack = [
            new \Pgs\Api\Middleware\ApiTokenAuth(['voice:read']),
            new \Pgs\Api\Middleware\RateLimit('api:{token}'),
            new \Pgs\Api\Middleware\VoiceSession(),
        ];
        $voiceWriteStack = [
            new \Pgs\Api\Middleware\ApiTokenAuth(['voice:write']),
            new \Pgs\Api\Middleware\RateLimit('api:{token}'),
            new \Pgs\Api\Middleware\VoiceSession(),
            new \Pgs\Api\Middleware\Idempotency(),
        ];

        $this->router->add('GET', '/voice/session', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceSessionController())->getSession($req);
        }, $voiceReadStack);

        $this->router->add('POST', '/voice/session/verify-pin', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceSessionController())->verifyPin($req);
        }, $voiceWriteStack);

        $this->router->add('POST', '/voice/session/identify', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceSessionController())->identify($req);
        }, $voiceWriteStack);

        $this->router->add('POST', '/voice/session/lang', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceSessionController())->setLang($req);
        }, $voiceWriteStack);

        $this->router->add('POST', '/voice/session/end', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceSessionController())->endSession($req);
        }, $voiceWriteStack);

        // Voice operations routes (IA_05 §3.5, §3.6)
        $this->router->add('GET', '/voice/services', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceOperationsController())->getServices($req);
        }, $voiceReadStack);

        $this->router->add('GET', '/voice/units', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceOperationsController())->getUnits($req);
        }, $voiceReadStack);

        $this->router->add('GET', '/voice/units/{id}/contacts', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceOperationsController())->getUnitContacts($req);
        }, $voiceReadStack);

        $this->router->add('GET', '/voice/escalation', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceOperationsController())->getEscalation($req);
        }, $voiceReadStack);

        $this->router->add('GET', '/voice/tasks', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceOperationsController())->listTasks($req);
        }, $voiceReadStack);

        $this->router->add('GET', '/voice/tasks/{code}', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceOperationsController())->getTask($req);
        }, $voiceReadStack);

        $this->router->add('POST', '/voice/tasks', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceOperationsController())->createTask($req);
        }, $voiceWriteStack);

        $this->router->add('POST', '/voice/tasks/{code}/status', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceOperationsController())->updateTaskStatus($req);
        }, $voiceWriteStack);

        $this->router->add('POST', '/voice/service-requests', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceOperationsController())->createServiceRequest($req);
        }, $voiceWriteStack);

        $this->router->add('POST', '/voice/messages', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceOperationsController())->takeMessage($req);
        }, $voiceWriteStack);

        // Voice availability routes (IA_05 §3.7)
        $this->router->add('GET', '/voice/workers', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceAvailabilityController())->getWorkers($req);
        }, $voiceReadStack);

        $this->router->add('GET', '/voice/availability', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceAvailabilityController())->getAvailability($req);
        }, $voiceReadStack);

        $this->router->add('POST', '/voice/reservations', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceAvailabilityController())->createReservation($req);
        }, $voiceWriteStack);

        $this->router->add('GET', '/voice/leave/balance', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceAvailabilityController())->getLeaveBalance($req);
        }, $voiceReadStack);

        $this->router->add('POST', '/voice/absences', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceAvailabilityController())->requestAbsence($req);
        }, $voiceWriteStack);

        // Voice invoices & reports routes (IA_05 §3.3, §3.8, §3.10)
        $this->router->add('GET', '/voice/invoices', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceInvoicesReportsController())->listInvoices($req);
        }, $voiceReadStack);

        $this->router->add('POST', '/voice/invoices/{id}/send', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceInvoicesReportsController())->sendInvoice($req);
        }, $voiceWriteStack);

        $this->router->add('GET', '/voice/reports/catalog', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceInvoicesReportsController())->getReportsCatalog($req);
        }, $voiceReadStack);

        $this->router->add('POST', '/voice/reports/send', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceInvoicesReportsController())->sendReport($req);
        }, $voiceWriteStack);

        // Signed files download endpoint (IA_05 §3.3)
        $this->router->add('GET', '/files/{token}', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceInvoicesReportsController())->serveSignedFile($req);
        });

        // Voice expenses routes (IA_05 §3.9)
        $this->router->add('POST', '/voice/expenses/draft', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceExpensesController())->createDraft($req);
        }, $voiceWriteStack);

        $this->router->add('POST', '/voice/expenses/drafts/{id}/confirm', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceExpensesController())->confirmDraft($req);
        }, $voiceWriteStack);

        $this->router->add('DELETE', '/voice/expenses/drafts/{id}', function (Request $req): Response {
            return (new \Pgs\Voice\VoiceExpensesController())->discardDraft($req);
        }, $voiceWriteStack);
    }

    public function handle(Request $r): Response
    {
        // RequestId generation / resolution (IA_05 §2)
        $rawReqId = $r->header('X-Request-Id');
        if ($rawReqId !== null && preg_match('/^[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}$/', $rawReqId)) {
            $requestId = $rawReqId;
        } else {
            $requestId = Ulid::generate();
        }
        \Pgs\Core\RequestContext::set($requestId);
        \Pgs\Core\RequestContext::setLang(null);
        $r = $r->withAttr('request_id', $requestId);

        try {
            $response = $this->router->dispatch($r);
        } catch (ValidationException $e) {
            $response = Response::error(422, 'validation_error', $e->getMessage(), ['fields' => $e->fields()]);
        } catch (ForbiddenException $e) {
            $response = Response::error(403, 'forbidden', $e->getMessage());
        } catch (VerificationRequiredException $e) {
            $response = Response::error(403, 'verification_required', $e->getMessage(), ['required' => $e->required()]);
        } catch (PinLockedException $e) {
            $response = Response::error(423, 'pin_locked', $e->getMessage(), [
                'locked_until' => $e->lockedUntil(),
                'admin_locked' => $e->adminLocked(),
            ]);
        } catch (NotFoundException $e) {
            $response = Response::error(404, 'not_found', $e->getMessage());
        } catch (ConfirmationRequiredException $e) {
            $details = [
                'preview' => $e->preview(),
                'confirmation_token' => $e->confirmationToken(),
            ];
            $response = Response::error(409, 'confirmation_required', $e->getMessage(), $details);
        } catch (ConflictException $e) {
            $response = Response::error(409, 'conflict', $e->getMessage(), $e->fields());
        } catch (RateLimitedException $e) {
            $retryAfter = $e->retryAfter();
            $response = Response::error(429, 'rate_limited', $e->getMessage(), ['retry_after' => $retryAfter]);
            if ($retryAfter > 0) {
                $response->header('Retry-After', (string) $retryAfter);
            }
        } catch (UnauthorizedException $e) {
            $response = Response::error(401, 'unauthorized', $e->getMessage());
        } catch (GoneException $e) {
            $response = Response::error(410, 'gone', $e->getMessage());
        } catch (\Pgs\Core\Exceptions\PgsException $e) {
            $response = Response::error($e->httpStatus(), $e->errorCode(), $e->getMessage(), $e->fields() ?: null);
        } catch (\Throwable $e) {
            \Pgs\Core\AppLog::error('api', $e, ['method' => $r->method(), 'path' => $r->path(), 'trace' => mb_substr($e->getTraceAsString(), 0, 1500)]);
            $response = Response::error(500, 'internal_error', 'Internal server error');
        }

        $lang = \Pgs\Core\RequestContext::lang();
        if ($lang !== null) {
            $response->header('X-Pgs-Lang', $lang);
        }

        $response->setRequestId($requestId);
        return $response;
    }
}
