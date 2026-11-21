<?php
// Tiny session health probe used only to recover a stalled mobile redirect.
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');
$authenticated = !empty($_SESSION['session_user']['id']);
$redirect = $authenticated && !empty($_SESSION['pgs_passkey_offer']) ? 'passkeys_setup.php' : 'main.php';
session_write_close();
echo json_encode(['authenticated'=>$authenticated,'redirect'=>$redirect], JSON_UNESCAPED_SLASHES);
