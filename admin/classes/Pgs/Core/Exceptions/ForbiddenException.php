<?php

declare(strict_types=1);

namespace Pgs\Core\Exceptions;

final class ForbiddenException extends PgsException
{
    public function __construct(string $message = 'Forbidden', string $errorCode = 'forbidden')
    {
        parent::__construct($message, $errorCode, 403);
    }
}
