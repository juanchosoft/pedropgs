<?php

declare(strict_types=1);

namespace Pgs\Core\Exceptions;

final class NotFoundException extends PgsException
{
    public function __construct(string $message = 'Not found')
    {
        parent::__construct($message, 'not_found', 404);
    }
}
