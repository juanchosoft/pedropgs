<?php

declare(strict_types=1);

namespace Pgs\Core\Web;

use Pgs\Auth\Principal;
use Pgs\Auth\PrincipalFactory;

final class PgsPage
{
    public static function principal(): Principal
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION['session_user']) || empty($_SESSION['session_user']['id'])) {
            header('Location: login.php');
            exit;
        }

        $p = PrincipalFactory::fromSession('web');
        if ($p->isAnonymous()) {
            header('Location: login.php');
            exit;
        }

        return $p;
    }

    public static function principalOrNull(): ?Principal
    {
        try {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            if (empty($_SESSION['session_user']['id'])) {
                return null;
            }
            $p = PrincipalFactory::fromSession('web');
            return $p->isAnonymous() ? null : $p;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
