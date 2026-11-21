<?php

declare(strict_types=1);

use Pgs\Migrations\AbstractMigration;
use Pgs\Migrations\MigrationContext;
use Pgs\Migrations\SchemaHelper;

return new class extends AbstractMigration {
    public function id(): string
    {
        return '2026_11_011_responder_properties';
    }

    public function description(): string
    {
        return 'Properties each responder is in charge of';
    }

    public function up(\PDO $pdo, MigrationContext $ctx): void
    {
        SchemaHelper::createTable($pdo, 'tec_responder_properties', <<<'SQL'
(
  responder_id INT UNSIGNED NOT NULL,
  unidad_id INT NOT NULL,
  PRIMARY KEY (responder_id, unidad_id),
  KEY idx_unidad (unidad_id)
)
SQL, $ctx, [
            'fk_responder_properties_responder' => 'FOREIGN KEY (responder_id) REFERENCES tec_responders (id) ON DELETE CASCADE',
        ]);
    }
};
