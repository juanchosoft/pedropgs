<?php

declare(strict_types=1);

namespace Pgs\Api\Middleware;

use Pgs\Api\Request;
use Pgs\Api\Response;
use Pgs\Core\RateLimiter;

final class RateLimit
{
    private string $bucketTemplate;
    private int $limit;
    private int $window;

    public function __construct(string $bucketTemplate, int $limit = 600, int $window = 60)
    {
        $this->bucketTemplate = $bucketTemplate;
        $this->limit = $limit;
        $this->window = $window;
    }

    public function __invoke(Request $req, callable $next): Response
    {
        $tokenRow = (array) ($req->attr('api_token_row') ?? []);
        $tokenId = $req->attr('api_token_id') ?? $req->ip();
        $caller = $req->attr('caller_e164') ?? $req->ip();

        $bucket = str_replace(
            ['{token}', '{ip}', '{caller_e164}'],
            [(string) $tokenId, $req->ip(), (string) $caller],
            $this->bucketTemplate
        );

        $effectiveLimit = $this->limit;
        if (!empty($tokenRow['rate_limit_per_minute']) && str_starts_with($this->bucketTemplate, 'api:')) {
            $effectiveLimit = (int) $tokenRow['rate_limit_per_minute'];
        }

        RateLimiter::hit($bucket, $effectiveLimit, $this->window);

        return $next($req);
    }
}
