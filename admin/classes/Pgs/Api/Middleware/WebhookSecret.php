<?php

declare(strict_types=1);

namespace Pgs\Api\Middleware;

use Pgs\Api\Request;
use Pgs\Api\Response;
use Pgs\Core\Config;

final class WebhookSecret
{
    private string $configKey;

    public function __construct(string $configKey)
    {
        $this->configKey = $configKey;
    }

    public function __invoke(Request $req, callable $next): Response
    {
        $provided = $req->header('X-Pgs-Webhook-Secret');
        $expected = (string) Config::get('elevenlabs', $this->configKey, '');

        if ($expected === '' || $provided === null || !hash_equals($expected, $provided)) {
            return Response::error(401, 'unauthorized', 'Invalid or missing webhook secret');
        }

        return $next($req);
    }
}
