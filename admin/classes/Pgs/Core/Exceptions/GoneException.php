<?php

declare(strict_types=1);

namespace Pgs\Core\Exceptions;

final class GoneException extends PgsException
{
    public function __construct(string $message = 'Gone')
    {
        parent::__construct($message, 'gone', 410);
    }
}
