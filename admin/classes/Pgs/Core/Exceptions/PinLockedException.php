<?php

declare(strict_types=1);

namespace Pgs\Core\Exceptions;

final class PinLockedException extends PgsException
{
    public function __construct(private ?string $lockedUntil = null, private bool $adminLocked = false)
    {
        parent::__construct('PIN locked', 'pin_locked', 423);
    }

    public function lockedUntil(): ?string
    {
        return $this->lockedUntil;
    }

    public function adminLocked(): bool
    {
        return $this->adminLocked;
    }
}
