<?php

declare(strict_types=1);

namespace Pgs\Tasks;

use Pgs\Auth\Policy\TaskPolicy;
use Pgs\Auth\Principal;
use Pgs\Core\Exceptions\ForbiddenException;
use Pgs\Core\Exceptions\ValidationException;

final class TaskStateMachine
{
    public const TRANSITIONS = [
        'pending'     => ['assigned' => 'assign', 'in_progress' => 'status', 'cancelled' => 'update'],
        'assigned'    => ['in_progress' => 'status', 'pending' => 'assign', 'cancelled' => 'update'],
        'in_progress' => ['completed' => 'status', 'assigned' => 'status', 'cancelled' => 'update'],
        'completed'   => ['in_progress' => 'update'],
        'cancelled'   => ['pending' => 'system_admin'],
    ];

    public static function assertCan(Principal $p, array $task, string $to): void
    {
        $from = $task['status'] ?? 'pending';
        if ($from === $to) {
            return;
        }

        $allowedTransitions = self::TRANSITIONS[$from] ?? [];
        if (!isset($allowedTransitions[$to])) {
            throw new ValidationException("Transition from '{$from}' to '{$to}' is not allowed.");
        }

        $requirement = $allowedTransitions[$to];

        switch ($requirement) {
            case 'assign':
                if (!TaskPolicy::canAssign($p)) {
                    throw new ForbiddenException("Missing permission to assign task.");
                }
                break;
            case 'status':
                if (!TaskPolicy::canChangeStatus($p, $task)) {
                    throw new ForbiddenException("Missing permission to change task status.");
                }
                break;
            case 'update':
                $isCreatorCancelling = ($to === 'cancelled' && $from === 'pending'
                    && isset($task['created_by_user_id'])
                    && (int) $task['created_by_user_id'] === $p->userId);

                if (!$isCreatorCancelling && !TaskPolicy::canUpdate($p, $task)) {
                    throw new ForbiddenException("Missing permission to update task.");
                }
                break;
            case 'system_admin':
                if (!$p->isSystemAdmin()) {
                    throw new ForbiddenException("Only system administrators can reopen cancelled tasks.");
                }
                break;
            default:
                throw new ForbiddenException("Unauthorized state transition.");
        }
    }

    public static function allowed(Principal $p, array $task): array
    {
        $from = $task['status'] ?? 'pending';
        $possible = self::TRANSITIONS[$from] ?? [];
        $result = [];

        foreach ($possible as $to => $req) {
            try {
                self::assertCan($p, $task, $to);
                $result[] = $to;
            } catch (\Throwable) {
                // Not permitted for current principal
            }
        }

        return $result;
    }
}
