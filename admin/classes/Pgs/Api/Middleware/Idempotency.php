<?php

declare(strict_types=1);

namespace Pgs\Api\Middleware;

use Pgs\Api\Request;
use Pgs\Api\Response;
use Pgs\Core\Idempotency as CoreIdempotency;

final class Idempotency
{
    public function __invoke(Request $req, callable $next): Response
    {
        $idemKey = $req->header('Idempotency-Key');
        if ($idemKey === null || trim($idemKey) === '') {
            return Response::error(
                422,
                'validation_error',
                'Missing Idempotency-Key header',
                ['fields' => ['idempotency_key' => 'Idempotency-Key header is required for write operations']]
            );
        }

        $cleanKey = trim($idemKey);
        if (!preg_match('/^[A-Za-z0-9-]{8,100}$/', $cleanKey)) {
            return Response::error(
                422,
                'validation_error',
                'Invalid Idempotency-Key header format',
                ['fields' => ['idempotency_key' => 'Idempotency-Key must match ^[A-Za-z0-9-]{8,100}$']]
            );
        }

        $tokenId = $req->attr('api_token_id') ?? 'anon';
        $scope = "api:{$tokenId}:{$req->path()}";
        $requestHash = hash('sha256', $req->method() . '|' . $req->path() . '|' . $req->rawBody());

        // Idempotency::begin handles existing check, duplicate mismatch (409) and in-progress (409)
        $cached = CoreIdempotency::begin($scope, $cleanKey, $requestHash);
        if ($cached !== null) {
            $resp = new Response($cached['code'], $cached['body'], ['X-Idempotent-Replay' => 'true']);
            return $resp;
        }

        $response = $next($req);

        // If successful 2xx response, record in idempotency store
        if ($response->getHttpCode() >= 200 && $response->getHttpCode() < 300) {
            CoreIdempotency::finish($scope, $cleanKey, $response->getHttpCode(), $response->getBody());
        }

        return $response;
    }
}
