<?php

declare(strict_types=1);

namespace Pgs\Core\Exceptions;

final class RateLimitedException extends PgsException
{
    public function __construct(private int $retryAfter = 1)
    {
        parent::__construct('Rate limit exceeded', 'rate_limited', 429);
    }

    public function retryAfter(): int
    {
        return $this->retryAfter;
    }
}
