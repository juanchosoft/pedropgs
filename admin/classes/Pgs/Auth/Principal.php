<?php

declare(strict_types=1);

namespace Pgs\Auth;

use Pgs\Core\Exceptions\ForbiddenException;

final class Principal
{
    /**
     * @param list<string> $permissionKeys
     * @param list<int>|null $unitIds
     * @param list<string> $tokenScopes
     */
    public function __construct(
        public readonly string $kind,
        public readonly ?int $userId,
        public readonly string $userType,
        public readonly ?string $roleKey,
        public readonly array $permissionKeys,
        public readonly ?int $employeeId,
        public readonly ?array $unitIds,
        public readonly string $channel,
        public readonly string $verification,
        public readonly string $lang = 'en',
        public readonly ?int $callSessionId = null,
        public readonly ?int $apiTokenId = null,
        public readonly array $tokenScopes = [],
        public readonly bool $isInternalStaff = false,
    ) {
    }

    public function isAnonymous(): bool
    {
        return $this->kind === 'anonymous' || $this->userId === null;
    }

    public function isSystemAdmin(): bool
    {
        return $this->userType === 'SuperAdministrador';
    }

    public function isUnitAdmin(): bool
    {
        return $this->userType === 'Administrador' && !$this->isInternalStaff;
    }

    public function isEmployee(): bool
    {
        return $this->employeeId !== null && ($this->userType === 'Staff' || $this->isInternalStaff);
    }

    public function isInternalStaff(): bool
    {
        return $this->isInternalStaff;
    }

    private function isDeniedByRole(string $key): bool
    {
        return $this->isUnitAdmin() && InternalOnlyPermissions::isInternalOnly($key);
    }

    public function can(string $key): bool
    {
        if ($this->isSystemAdmin()) {
            return true;
        }
        if ($this->isDeniedByRole($key)) {
            return false;
        }
        return in_array($key, $this->permissionKeys, true);
    }

    /**
     * @param list<string> $keys
     */
    public function canAny(array $keys): bool
    {
        if ($this->isSystemAdmin()) {
            return true;
        }
        foreach ($keys as $k) {
            if ($this->can($k)) {
                return true;
            }
        }
        return false;
    }

    public function require(string $key): void
    {
        if (!$this->can($key)) {
            throw new ForbiddenException("Missing required permission: $key");
        }
    }

    /**
     * @param list<string> $keys
     */
    public function requireAny(array $keys): void
    {
        if (!$this->canAny($keys)) {
            throw new ForbiddenException("Missing at least one required permission: " . implode(', ', $keys));
        }
    }

    public function id(): ?int
    {
        return $this->userId;
    }

    public function hasPermission(string $key): bool
    {
        return $this->can($key);
    }

    /**
     * @param list<string> $keys
     */
    public function hasAnyPermission(array $keys): bool
    {
        return $this->canAny($keys);
    }

    public function language(): string
    {
        return $this->lang;
    }

    public function phone(): ?string
    {
        if ($this->userId === null) {
            return null;
        }
        $pdo = \Pgs\Core\Db::pdo();
        $stmt = $pdo->prepare('SELECT phone_e164 FROM tec_usuarios WHERE id = :id');
        $stmt->execute([':id' => $this->userId]);
        $val = $stmt->fetchColumn();
        return $val !== false && $val !== '' ? (string) $val : null;
    }

    public static function anonymous(string $channel = 'web'): self
    {
        return PrincipalFactory::anonymous($channel);
    }

    public static function system(string $channel = 'cron'): self
    {
        return PrincipalFactory::system($channel);
    }
}
