<?php
require 'admin/classes/Util.php';
require 'admin/classes/DbConection.php';
require 'admin/include/generic_validate_session.php';
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
require 'admin/classes/SessionData.php';
require 'admin/include/page_permissions.php';
PagePermissions::guardCurrentRequest();


