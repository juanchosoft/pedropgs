<?php

declare(strict_types=1);

namespace Pgs\Admin;

final class AdminContext
{
    /**
     * @param list<int>|null $unitIds null means every property
     * @param list<string> $permissions
     */
    public function __construct(
        public readonly int $userId,
        public readonly bool $isSuper,
        public readonly ?array $unitIds,
        private readonly array $permissions
    ) {
    }

    public static function fromSession(): self
    {
        $isSuper = \SessionData::superAdministrador();
        $keys = \SessionData::permissionKeys();
        $manages = $isSuper || in_array('solicitudes.requests.manage', $keys, true);
        $units = array_values(array_unique(array_map('intval', (array) \SessionData::getUnidadesUser())));

        return new self((int) \SessionData::getUserId(), $isSuper, $manages ? null : $units, $keys);
    }

    public function can(string $key): bool
    {
        return $this->isSuper || in_array($key, $this->permissions, true);
    }

    public function seesAllProperties(): bool
    {
        return $this->unitIds === null;
    }

    public function canSeeUnit(?int $unitId): bool
    {
        if ($this->unitIds === null) {
            return true;
        }

        return $unitId !== null && in_array($unitId, $this->unitIds, true);
    }
}
