<?php
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
header('Cache-Control: no-store');
if(empty($_SESSION['session_user']['id'])){header('Location: login.php',true,303);exit;}
if(empty($_SESSION['login_csrf']))$_SESSION['login_csrf']=bin2hex(random_bytes(32));
// Do not repeatedly force enrollment after a user leaves this page.
unset($_SESSION['pgs_passkey_offer']);
$user=htmlspecialchars((string)($_SESSION['session_user']['nickname']??''),ENT_QUOTES,'UTF-8');
$csrf=htmlspecialchars($_SESSION['login_csrf'],ENT_QUOTES,'UTF-8');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Enable secure sign-in · PGS Centrum</title>
<style>*{box-sizing:border-box}body{margin:0;font:16px system-ui,Arial,sans-serif;color:white;background:linear-gradient(145deg,#10192b,#24477e);min-height:100dvh;display:grid;place-items:center;padding:18px}.card{max-width:440px;width:100%;padding:30px 24px;border-radius:24px;background:#152238;box-shadow:0 18px 55px #0005;border:1px solid #ffffff30}h1{font-size:25px;margin:10px 0}p{line-height:1.55;color:#d8e0ed}.button{display:block;text-align:center;text-decoration:none;padding:15px;border:0;border-radius:12px;width:100%;font:700 16px system-ui;cursor:pointer;margin-top:12px;background:#2d61b6;color:white}.button.secondary{background:#ffffff18;border:1px solid #ffffff36}.note{font-size:13px}#msg{color:#ffb6b6;white-space:pre-wrap}</style></head><body>
<main class="card" data-passkey-setup data-csrf="<?=$csrf?>">
<div style="font-size:45px">🔐</div><h1>Enable faster sign-in</h1>
<p>Use fingerprint, Face ID or your device PIN to securely sign in next time, without entering your password.</p>
<p class="note">Signed in as <strong><?=$user?></strong>. Your fingerprint or face never leaves your device.</p>
<button class="button" id="pgsRegisterPasskey" type="button">Enable biometric sign-in</button>
<a class="button secondary" href="main.php">Not now · Continue</a>
<p id="msg" role="status" aria-live="polite"></p><p id="pgsCompatibility" class="note" aria-live="polite"></p>
<p class="note">Requires HTTPS on your phone, or localhost for development. Your regular password will continue to work.</p>
</main><script src="assets/js/pgs-passkeys.js?v=2" defer></script></body></html>
