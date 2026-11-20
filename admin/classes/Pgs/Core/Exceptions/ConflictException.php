<?php

declare(strict_types=1);

namespace Pgs\Core\Exceptions;

final class ConflictException extends PgsException
{
    public function __construct(string $message = 'Conflict', string $errorCode = 'conflict', array $fields = [])
    {
        parent::__construct($message, $errorCode, 409, $fields);
    }
}
