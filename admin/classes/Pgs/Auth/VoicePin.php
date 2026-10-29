<?php

declare(strict_types=1);

namespace Pgs\Auth;

use Pgs\Core\Clock;
use Pgs\Core\Db;
use Pgs\Core\Exceptions\PinLockedException;
use Pgs\Core\Exceptions\ValidationException;

final class VoicePin
{
    private const FAILS_PER_LEVEL = 5;
    private const FAIL_WINDOW_HOURS = 24;
    private const LOCK_MINUTES = [1 => 15, 2 => 60, 3 => 1440];

    public static function validateFormat(string $pin): void
    {
        $pin = trim($pin);
        if (!preg_match('/^\d{6}$/', $pin)) {
            throw new ValidationException(['pin' => 'PIN must be exactly 6 digits']);
        }
        if (preg_match('/^(.)\1{5}$/', $pin)) {
            throw new ValidationException(['pin' => 'PIN cannot consist of repeated identical digits']);
        }
        if (count(array_unique(str_split($pin))) <= 2) {
            throw new ValidationException(['pin' => 'PIN is too predictable']);
        }
        $digits = array_map('intval', str_split($pin));
        $steps = [];
        for ($i = 1; $i < 6; $i++) {
            $steps[] = $digits[$i] - $digits[$i - 1];
        }
        if (count(array_unique($steps)) === 1 && abs($steps[0]) === 1) {
            throw new ValidationException(['pin' => 'PIN cannot be an obvious sequence']);
        }
    }

    public static function set(int $userId, string $pin): void
    {
        self::validateFormat($pin);
        $pdo = Db::pdo();
        $hash = password_hash($pin, PASSWORD_DEFAULT);
        $now = Clock::nowUtcSql();

        $stmt = $pdo->prepare(
            "UPDATE tec_usuarios
             SET voice_pin_hash = :h,
                 voice_pin_set_at = :now,
                 voice_pin_failed = 0,
                 voice_pin_fail_window_start = NULL,
                 voice_pin_lock_level = 0,
                 voice_pin_locked_until = NULL,
                 voice_pin_admin_locked = 0
             WHERE id = :uid"
        );
        $stmt->execute([':h' => $hash, ':now' => $now, ':uid' => $userId]);
    }

    public static function verify(int $userId, int $callSessionId, string $pin, string $callerE164): bool
    {
        $pdo = Db::pdo();
        $ownTransaction = !$pdo->inTransaction();
        if ($ownTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT voice_pin_hash, voice_pin_failed, voice_pin_fail_window_start, voice_pin_lock_level,
                        voice_pin_locked_until, voice_pin_admin_locked
                 FROM tec_usuarios WHERE id = :uid FOR UPDATE"
            );
            $stmt->execute([':uid' => $userId]);
            $u = $stmt->fetch();
            if (!$u || empty($u['voice_pin_hash'])) {
                if ($ownTransaction) {
                    $pdo->commit();
                }
                return false;
            }

            $now = Clock::nowUtc();
            $utc = new \DateTimeZone('UTC');

            if ((int) $u['voice_pin_admin_locked'] === 1) {
                if ($ownTransaction) {
                    $pdo->commit();
                }
                throw new PinLockedException(null, true);
            }

            if (!empty($u['voice_pin_locked_until'])) {
                $lockedUntil = new \DateTimeImmutable((string) $u['voice_pin_locked_until'], $utc);
                if ($now < $lockedUntil) {
                    if ($ownTransaction) {
                        $pdo->commit();
                    }
                    throw new PinLockedException($lockedUntil->format('Y-m-d H:i:s'), false);
                }
            }

            if (password_verify($pin, (string) $u['voice_pin_hash'])) {
                $pdo->prepare(
                    "UPDATE tec_usuarios
                     SET voice_pin_failed = 0, voice_pin_fail_window_start = NULL,
                         voice_pin_lock_level = 0, voice_pin_locked_until = NULL
                     WHERE id = :uid"
                )->execute([':uid' => $userId]);
                if ($ownTransaction) {
                    $pdo->commit();
                }
                return true;
            }

            $failed = (int) $u['voice_pin_failed'];
            $lockLevel = (int) $u['voice_pin_lock_level'];
            $windowStart = !empty($u['voice_pin_fail_window_start'])
                ? new \DateTimeImmutable((string) $u['voice_pin_fail_window_start'], $utc)
                : null;

            if ($windowStart === null || $windowStart->modify('+' . self::FAIL_WINDOW_HOURS . ' hours') < $now) {
                $failed = 0;
                $lockLevel = 0;
                $windowStart = $now;
            }

            $failed++;
            $lockedUntil = null;
            $adminLocked = 0;

            if ($failed >= self::FAILS_PER_LEVEL) {
                $lockLevel++;
                if (isset(self::LOCK_MINUTES[$lockLevel])) {
                    $lockedUntil = $now->modify('+' . self::LOCK_MINUTES[$lockLevel] . ' minutes')->format('Y-m-d H:i:s');
                } else {
                    $adminLocked = 1;
                }
                $failed = 0;
                $windowStart = $now;
            }

            $pdo->prepare(
                "UPDATE tec_usuarios
                 SET voice_pin_failed = :f,
                     voice_pin_fail_window_start = :ws,
                     voice_pin_lock_level = :ll,
                     voice_pin_locked_until = :lu,
                     voice_pin_admin_locked = :al
                 WHERE id = :uid"
            )->execute([
                ':f' => $failed,
                ':ws' => $windowStart->format('Y-m-d H:i:s'),
                ':ll' => $lockLevel,
                ':lu' => $lockedUntil,
                ':al' => $adminLocked,
                ':uid' => $userId,
            ]);
            if ($ownTransaction) {
                $pdo->commit();
            }
        } catch (PinLockedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($ownTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        if ($adminLocked === 1) {
            throw new PinLockedException(null, true);
        }
        if ($lockedUntil !== null) {
            throw new PinLockedException($lockedUntil, false);
        }

        return false;
    }

    public static function reset(int $userId): void
    {
        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            "UPDATE tec_usuarios
             SET voice_pin_hash = NULL,
                 voice_pin_set_at = NULL,
                 voice_pin_failed = 0,
                 voice_pin_fail_window_start = NULL,
                 voice_pin_lock_level = 0,
                 voice_pin_locked_until = NULL,
                 voice_pin_admin_locked = 0
             WHERE id = :uid"
        );
        $stmt->execute([':uid' => $userId]);
    }

    public static function status(int $userId): array
    {
        $pdo = Db::pdo();
        $stmt = $pdo->prepare(
            "SELECT voice_pin_hash, voice_pin_set_at, voice_pin_failed, voice_pin_lock_level, voice_pin_locked_until, voice_pin_admin_locked
             FROM tec_usuarios WHERE id = :uid"
        );
        $stmt->execute([':uid' => $userId]);
        $u = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$u || empty($u['voice_pin_hash'])) {
            return ['status' => 'not_set', 'label' => 'Not set'];
        }
        if ((int) $u['voice_pin_admin_locked'] === 1) {
            return ['status' => 'locked_admin', 'label' => 'Locked (admin reset required)'];
        }
        if (!empty($u['voice_pin_locked_until'])) {
            $now = Clock::nowUtc();
            $lockedUntil = new \DateTimeImmutable((string) $u['voice_pin_locked_until'], new \DateTimeZone('UTC'));
            if ($now < $lockedUntil) {
                return [
                    'status' => 'locked_temp',
                    'label' => 'Locked until ' . $u['voice_pin_locked_until'] . ' UTC',
                    'locked_until' => $u['voice_pin_locked_until']
                ];
            }
        }
        return ['status' => 'active', 'label' => 'Active', 'set_at' => $u['voice_pin_set_at']];
    }
}
