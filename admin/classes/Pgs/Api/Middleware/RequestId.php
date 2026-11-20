<?php

declare(strict_types=1);

namespace Pgs\Api\Middleware;

use Pgs\Api\Request;
use Pgs\Api\Response;
use Pgs\Core\Ulid;

final class RequestId
{
    public function __invoke(Request $req, callable $next): Response
    {
        $raw = $req->header('X-Request-Id');
        if ($raw !== null && preg_match('/^[A-Za-z0-9-]{8,64}$/', $raw)) {
            $id = $raw;
        } else {
            $id = Ulid::generate();
        }

        $req = $req->withAttr('request_id', $id);
        $res = $next($req);
        return $res->setRequestId($id);
    }
}
