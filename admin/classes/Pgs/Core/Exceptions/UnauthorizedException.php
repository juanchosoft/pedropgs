<?php

declare(strict_types=1);

namespace Pgs\Core\Exceptions;

final class UnauthorizedException extends PgsException
{
    public function __construct(string $message = 'Unauthorized', string $errorCode = 'unauthorized')
    {
        parent::__construct($message, $errorCode, 401);
    }
}
