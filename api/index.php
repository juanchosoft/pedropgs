<?php

declare(strict_types=1);

require __DIR__ . '/../admin/bootstrap_pgs.php';

(new \Pgs\Api\Kernel())->handle(\Pgs\Api\Request::fromGlobals())->send();
