<?php

declare(strict_types=1);

use Pgs\Migrations\AbstractMigration;
use Pgs\Migrations\MigrationContext;
use Pgs\Migrations\SchemaHelper;

return new class extends AbstractMigration {
    public function id(): string
    {
        return '2026_11_010_calls_property_asked';
    }

    public function description(): string
    {
        return 'Track whether the property question was already asked in a call';
    }

    public function up(\PDO $pdo, MigrationContext $ctx): void
    {
        SchemaHelper::addColumn($pdo, 'tec_calls', 'property_asked', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER candidate_ids', $ctx);
    }
};
