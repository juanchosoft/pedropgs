<?php

declare(strict_types=1);

namespace Pgs\Core\Exceptions;

final class ConfirmationRequiredException extends PgsException
{
    /**
     * @param array<string,mixed> $preview
     */
    public function __construct(private array $preview, private string $confirmationToken)
    {
        parent::__construct('Confirmation required', 'confirmation_required', 409);
    }

    /**
     * @return array<string,mixed>
     */
    public function preview(): array
    {
        return $this->preview;
    }

    public function confirmationToken(): string
    {
        return $this->confirmationToken;
    }
}
