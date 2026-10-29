<?php

declare(strict_types=1);

namespace Pgs\Core\Exceptions;

final class VerificationRequiredException extends PgsException
{
    public function __construct(string $message = 'Verification required', private string $required = 'pin')
    {
        parent::__construct($message, 'verification_required', 403);
    }

    public function required(): string
    {
        return $this->required;
    }
}
