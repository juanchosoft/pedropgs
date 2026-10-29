<?php

declare(strict_types=1);

namespace Pgs\Migrations;

abstract class AbstractMigration
{
    abstract public function id(): string;

    abstract public function description(): string;

    abstract public function up(\PDO $pdo, MigrationContext $ctx): void;

    public function rollbackNotes(): string
    {
        return '';
    }

    public function isBatched(): bool
    {
        return false;
    }
}
