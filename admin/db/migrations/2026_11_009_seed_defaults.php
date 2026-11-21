<?php

declare(strict_types=1);

use Pgs\Core\Clock;
use Pgs\Migrations\AbstractMigration;
use Pgs\Migrations\MigrationContext;

return new class extends AbstractMigration {
    private const DEFAULTS = [
        'voice_enabled' => '1',
        'esc.remind_min' => '15',
        'esc.escalate_min' => '30',
        'esc.urgent_remind_min' => '5',
        'esc.urgent_escalate_min' => '10',
        'esc.repeat_min' => '30',
        'esc.max_repeats' => '3',
        'esc.user_ids' => '',
        'esc.extra_emails' => '',
        'hours.schedule' => '{"mon":["08:00","17:00"],"tue":["08:00","17:00"],"wed":["08:00","17:00"],"thu":["08:00","17:00"],"fri":["08:00","17:00"],"sat":null,"sun":null}',
        'cron.last_tick' => '',
    ];

    public function id(): string
    {
        return '2026_11_009_seed_defaults';
    }

    public function description(): string
    {
        return 'Default voice and escalation settings (insert ignore)';
    }

    public function up(\PDO $pdo, MigrationContext $ctx): void
    {
        $now = Clock::nowUtcSql();
        $stmt = $pdo->prepare('INSERT IGNORE INTO tec_settings (setting_key, setting_value, updated_by, updated_at) VALUES (:k, :v, NULL, :now)');
        foreach (self::DEFAULTS as $key => $value) {
            $stmt->execute([':k' => $key, ':v' => $value, ':now' => $now]);
        }
        $ctx->out('Default settings present: ' . count(self::DEFAULTS));
    }
};
