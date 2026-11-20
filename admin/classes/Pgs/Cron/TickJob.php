<?php

declare(strict_types=1);

namespace Pgs\Cron;

use Pgs\Core\AppLog;
use Pgs\Core\Clock;
use Pgs\Core\Db;
use Pgs\Notifications\OutboxWorker;
use Pgs\Requests\EscalationService;
use Pgs\Responders\SettingsRepository;

/**
 * Periodic job: heartbeat, call expiry, escalation, outbox delivery and daily retention.
 */
final class TickJob
{
    /**
     * @return array<string,mixed>|null null when another tick holds the lock
     * @throws \Throwable
     */
    public static function run(\DateTimeImmutable $now): ?array
    {
        $pdo = Db::pdo();
        $lock = $pdo->query("SELECT GET_LOCK('pgs_tick', 0)")->fetchColumn();
        if ((int) $lock !== 1) {
            return null;
        }

        try {
            $settings = new SettingsRepository($pdo);
            $settings->set('cron.last_tick', $now->format('Y-m-d H:i:s'));

            $expired = $pdo->prepare("UPDATE tec_calls SET state = 'expired' WHERE state = 'active' AND started_at < :cut");
            $expired->execute([':cut' => $now->modify('-2 hours')->format('Y-m-d H:i:s')]);

            $summary = [
                'at' => $now->format('Y-m-d\TH:i:s\Z'),
                'calls_expired' => $expired->rowCount(),
                'escalation' => (new EscalationService($pdo))->tick($now),
                'outbox' => (new OutboxWorker($pdo))->process(50, $now),
            ];

            $local = $now->setTimezone(Clock::businessTz());
            if ($local->format('G') === '3' && $settings->get('retention.last_run', '') !== $local->format('Y-m-d')) {
                $pdo->prepare('DELETE FROM tec_calls WHERE started_at < :cut')->execute([':cut' => $now->modify('-180 days')->format('Y-m-d H:i:s')]);
                $pdo->prepare("DELETE FROM tec_outbox WHERE status IN ('sent','failed','skipped') AND created_at < :cut")->execute([':cut' => $now->modify('-30 days')->format('Y-m-d H:i:s')]);
                $pdo->prepare('DELETE FROM tec_signed_links WHERE expires_at < :cut')->execute([':cut' => $now->modify('-7 days')->format('Y-m-d H:i:s')]);
                $pdo->prepare('DELETE FROM tec_rate_limits WHERE window_start < :cut')->execute([':cut' => $now->modify('-2 days')->format('Y-m-d H:i:s')]);
                $settings->set('retention.last_run', $local->format('Y-m-d'));
                $summary['retention'] = 'done';
            }

            return $summary;
        } catch (\Throwable $e) {
            AppLog::error('cron', $e);
            throw $e;
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('pgs_tick')");
        }
    }
}
