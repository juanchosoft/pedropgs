<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
$mensaje = '';
$pgsAjaxLogin = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_SERVER['HTTP_X_PGS_LOGIN'] ?? '') === '1';
function pgsLoginJson($ok, $message = '', $redirect = '') {
  header('Content-Type: application/json; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  header('Cache-Control: no-store');
  echo json_encode(['success' => (bool)$ok, 'message' => (string)$message, 'redirect' => (string)$redirect], JSON_UNESCAPED_SLASHES);
  exit;
}
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

include './admin/classes/SessionData.php';
include './admin/classes/DbConection.php';
include './admin/classes/Util.php';
include './admin/classes/Usuario.php';

if (isset($_SESSION['session_user'])) {
  // Authenticated users may return after a mobile navigation interruption.
  $next = !empty($_SESSION['pgs_passkey_offer']) ? 'passkeys_setup.php' : 'main.php';
  session_write_close();
  header('Location: ' . $next, true, 303);
  exit;
} else {
  $rqst = $_POST;
  $op = isset($rqst['op']) ? $rqst['op'] : '';
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals('pms_usrlogin', (string)$op)) {
    if (!isset($_POST['login_csrf'], $_SESSION['login_csrf']) || !hash_equals((string)$_SESSION['login_csrf'], (string)$_POST['login_csrf'])) {
      http_response_code(403);
      $mensaje = 'Your session expired. Refresh the page and try again.';
      if ($pgsAjaxLogin) pgsLoginJson(false, $mensaje);
    } else {
    $nickname = isset($rqst['nickname']) ? $rqst['nickname'] : '';
    $hashpass = isset($rqst['hashpass']) ? $rqst['hashpass'] : '';

    if ($nickname != "" && $hashpass != "") {
      $hashpass = md5($hashpass);
      $arr = array('nickname' => $nickname, 'hashpass' => $hashpass);
      $res = Usuario::login($arr);
      $isvalid = $res['output']['valid'];
      if ($isvalid) {
        session_regenerate_id(true);
        $_SESSION['session_user'] = $res['output']['response'][0];
        $_SESSION['session_user']['permisos'] = $res['output']['permisos'];
        $_SESSION['session_user']['permission_keys'] = isset($res['output']['response'][0]['permission_keys'])
            ? $res['output']['response'][0]['permission_keys']
            : [];
        $_SESSION['session_user']['role'] = isset($res['output']['response'][0]['role'])
            ? $res['output']['response'][0]['role']
            : null;
        $_SESSION['session_user']['config'] = $res['output']['config'];
        $_SESSION['session_user']['telefono_emergencia'] = $res['output']['telefono_emergencia'];
        $_SESSION['session_user']['unidad'] = $res['output']['unidad'];
        $_SESSION['session_user']['unidades'] = isset($res['output']['unidades']) ? $res['output']['unidades'] : [];
        // Flush the authenticated session before navigating: prevents mobile requests waiting on the PHP session lock.
        $_SESSION['pgs_passkey_offer'] = true;
        session_write_close();
        if ($pgsAjaxLogin) pgsLoginJson(true, '', 'passkeys_setup.php');
        header('Location: passkeys_setup.php', true, 303);
        exit;
      } else {
        $mensaje = (string)($res['output']['response']['content'] ?? 'Incorrect username or password.');
        if ($pgsAjaxLogin) pgsLoginJson(false, $mensaje);
      }
    } else {
      $mensaje = 'Username and password are required.';
      if ($pgsAjaxLogin) pgsLoginJson(false, $mensaje);
    }
    }
  }
}
if (empty($_SESSION['login_csrf'])) {
  $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en" class="h-100">

<head>
  <?php include './admin/include/generic_head.php'; ?>

  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />

  <style>
    :root{
      --brand-1:#20427F;
      --brand-2:#2e58a8;
      --brand-3:#132b52;
      --soft-bg:#0b1220;
      --card: rgba(255,255,255,.08);
      --card2: rgba(255,255,255,.12);
      --stroke: rgba(255,255,255,.16);
      --stroke2: rgba(255,255,255,.24);
      --text: rgba(255,255,255,.92);
      --muted: rgba(255,255,255,.65);
      --danger:#ff5a7a;
      --success:#37d67a;
      --shadow: 0 18px 60px rgba(0,0,0,.45);
      --radius: 18px;
    }

    body{
      background: radial-gradient(1200px 600px at 15% 10%, rgba(46,88,168,.45), transparent 60%),
                  radial-gradient(900px 500px at 85% 20%, rgba(32,66,127,.45), transparent 55%),
                  radial-gradient(900px 700px at 60% 95%, rgba(19,43,82,.55), transparent 60%),
                  linear-gradient(180deg, #070b14 0%, #0b1220 45%, #070b14 100%);
      color: var(--text);
      min-height: 100vh;
    }

    /* Fix for old template wrappers */
    .authincation{
      min-height: 100vh;
      display:flex;
      align-items:center;
    }

    .saas-wrap{
      position: relative;
      width: 100%;
      padding: 24px 0;
    }

    .saas-card{
      position: relative;
      border-radius: calc(var(--radius) + 6px);
      background: linear-gradient(180deg, var(--card) 0%, rgba(255,255,255,.06) 100%);
      border: 1px solid var(--stroke);
      box-shadow: var(--shadow);
      overflow: hidden;
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
    }

    .saas-card::before{
      content:'';
      position:absolute;
      inset:-2px;
      background: radial-gradient(800px 240px at 20% 0%, rgba(46,88,168,.35), transparent 60%),
                  radial-gradient(700px 260px at 90% 10%, rgba(32,66,127,.35), transparent 55%),
                  radial-gradient(700px 320px at 60% 110%, rgba(255,255,255,.08), transparent 55%);
      pointer-events:none;
      filter: blur(0px);
    }

    .saas-grid{
      position: relative;
      display: grid;
      grid-template-columns: 1.1fr .9fr;
      min-height: 520px;
    }

    @media (max-width: 991.98px){
      .saas-grid{
        grid-template-columns: 1fr;
        min-height: auto;
      }
      .saas-side{
        display:none;
      }
    }

    .saas-side{
      position: relative;
      padding: 34px 34px;
      border-right: 1px solid rgba(255,255,255,.10);
      background:
        linear-gradient(180deg, rgba(0,0,0,.10), rgba(0,0,0,.20)),
        radial-gradient(900px 500px at 25% 15%, rgba(46,88,168,.35), transparent 60%),
        radial-gradient(700px 420px at 85% 25%, rgba(32,66,127,.30), transparent 55%);
    }

    .brand-chip{
      display: inline-flex;
      align-items:center;
      gap:10px;
      padding: 10px 14px;
      border-radius: 999px;
      border: 1px solid rgba(255,255,255,.14);
      background: rgba(255,255,255,.08);
      color: var(--text);
      font-weight: 600;
      letter-spacing: .2px;
    }

    .side-title{
      margin-top: 18px;
      font-size: 28px;
      line-height: 1.15;
      font-weight: 800;
      letter-spacing: -.3px;
    }

    .side-sub{
      margin-top: 10px;
      color: var(--muted);
      font-size: 14px;
      line-height: 1.5;
      max-width: 420px;
    }

    .side-feats{
      margin-top: 22px;
      display: grid;
      gap: 10px;
    }

    .feat{
      display:flex;
      gap: 10px;
      align-items:flex-start;
      padding: 10px 12px;
      border: 1px solid rgba(255,255,255,.10);
      background: rgba(255,255,255,.06);
      border-radius: 14px;
    }

    .feat b{
      display:block;
      font-size: 13px;
      line-height: 1.2;
    }

    .feat small{
      display:block;
      color: var(--muted);
      font-size: 12px;
      line-height: 1.4;
      margin-top: 2px;
    }

    .dot{
      width: 10px;
      height: 10px;
      border-radius: 999px;
      margin-top: 4px;
      background: linear-gradient(180deg, rgba(255,255,255,.9), rgba(255,255,255,.2));
      box-shadow: 0 0 0 3px rgba(46,88,168,.15);
    }

    .saas-form{
      position: relative;
      padding: 34px 34px;
    }

    @media (max-width: 575.98px){
      .saas-form{ padding: 22px 18px; }
    }

    .logo-wrap{
      display:flex;
      justify-content:center;
      margin-bottom: 14px;
    }

    .logo-wrap img{
      width: 190px;
      max-width: 70vw;
      height: auto;
      filter: drop-shadow(0 10px 20px rgba(0,0,0,.35));
    }

    .headline{
      text-align:center;
      font-weight: 800;
      font-size: 18px;
      margin: 8px 0 2px;
      letter-spacing: -.2px;
    }
    .subline{
      text-align:center;
      color: var(--muted);
      font-size: 13px;
      margin: 0 0 18px;
    }

    .form-label{
      font-weight: 700;
      font-size: 12px;
      color: rgba(255,255,255,.80);
      margin-bottom: 8px;
    }

    .input-wrap{
      position: relative;
    }

    .saas-input{
      width: 100%;
      border-radius: 14px;
      border: 1px solid rgba(255,255,255,.16);
      background: rgba(0,0,0,.18);
      color: var(--text);
      padding: 12px 44px 12px 14px;
      outline: none;
      transition: .18s ease;
      box-shadow: inset 0 1px 0 rgba(255,255,255,.06);
    }

    .saas-input::placeholder{ color: rgba(255,255,255,.40); }

    .saas-input:focus{
      border-color: rgba(46,88,168,.65);
      box-shadow: 0 0 0 4px rgba(46,88,168,.18);
      background: rgba(0,0,0,.22);
    }

    .icon-btn{
      position:absolute;
      top:50%;
      right:10px;
      transform: translateY(-50%);
      border: 0;
      width: 34px;
      height: 34px;
      border-radius: 10px;
      background: rgba(255,255,255,.08);
      color: rgba(255,255,255,.85);
      display:flex;
      align-items:center;
      justify-content:center;
      cursor:pointer;
      transition: .18s ease;
    }
    .icon-btn:hover{ background: rgba(255,255,255,.12); transform: translateY(-50%) scale(1.03); }
    .icon-btn:active{ transform: translateY(-50%) scale(.98); }

    .row-actions{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap: 10px;
      margin: 10px 0 12px;
      flex-wrap: wrap;
    }

    .remember{
      display:flex;
      align-items:center;
      gap: 10px;
      color: rgba(255,255,255,.75);
      font-size: 13px;
      user-select:none;
    }

    .remember input{
      width: 18px;
      height: 18px;
      accent-color: var(--brand-2);
      cursor:pointer;
    }

    .btn-saas{
      width: 100%;
      border: 0;
      border-radius: 14px;
      padding: 12px 14px;
      font-weight: 800;
      letter-spacing: .2px;
      background: linear-gradient(135deg, var(--brand-2), var(--brand-1));
      color: white;
      box-shadow: 0 14px 30px rgba(46,88,168,.28);
      transition: .18s ease;
      position: relative;
      overflow:hidden;
    }

    .btn-saas:hover{
      transform: translateY(-1px);
      box-shadow: 0 18px 38px rgba(46,88,168,.34);
    }

    .btn-saas:active{
      transform: translateY(0);
      box-shadow: 0 10px 24px rgba(46,88,168,.25);
    }

    .btn-saas[disabled]{
      opacity: .7;
      cursor: not-allowed;
      transform:none !important;
    }

    .btn-loading{
      display:none;
      align-items:center;
      justify-content:center;
      gap:10px;
    }

    .spinner{
      width: 16px;
      height: 16px;
      border-radius: 999px;
      border: 2px solid rgba(255,255,255,.35);
      border-top-color: rgba(255,255,255,.95);
      animation: spin .8s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg);} }

    .alert-saas{
      margin-top: 12px;
      border-radius: 14px;
      border: 1px solid rgba(255,90,122,.35);
      background: rgba(255,90,122,.10);
      color: rgba(255,255,255,.92);
      padding: 10px 12px;
      font-size: 13px;
      line-height: 1.35;
    }

    .alert-saas b{
      display:inline-block;
      margin-right:6px;
    }

    .mini-footer{
      margin-top: 14px;
      text-align:center;
      color: rgba(255,255,255,.55);
      font-size: 12px;
    }

    .mini-footer a{
      color: rgba(255,255,255,.78);
      text-decoration:none;
      border-bottom: 1px dashed rgba(255,255,255,.28);
    }
    .mini-footer a:hover{ color: rgba(255,255,255,.95); border-bottom-color: rgba(255,255,255,.55); }

    .secure-hint { margin-top:15px; padding:11px 12px; border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.045); border-radius:12px; color:var(--muted); font-size:12px; line-height:1.55; }
    .secure-hint strong { display:block; font-weight:600; color:rgba(255,255,255,.8); }
    .secure-hint-icon { margin-right:4px; }
    .saas-input { font-size:16px; min-height:48px; }
    .btn-saas { min-height:49px; touch-action: manipulation; }
    .icon-btn { min-width:42px; min-height:42px; right:5px; }
    html { -webkit-text-size-adjust:100%; }
    @media (max-width:575.98px) {
      body { min-height:100dvh; }
      .authincation { min-height:100dvh; align-items:center; }
      .saas-wrap { padding:14px 0 calc(14px + env(safe-area-inset-bottom)); }
      .saas-card { border-radius:19px; }
      .saas-form { padding:25px 20px 22px; }
      .logo-wrap img { width:155px; }
      .headline { font-size:21px; }
      .subline { margin-bottom:21px; }
      .row-actions { align-items:flex-start; }
      .remember { min-height:35px; }
    }
    @media (prefers-reduced-motion: reduce) { .spinner { animation:none; } * { transition:none !important; } }

    /* make template default card not fight */
    .authincation-content{
      background: transparent !important;
      box-shadow: none !important;
      border: 0 !important;
      padding: 0 !important;
    }
    .auth-form{ padding:0 !important; }
  </style>
</head>

<body class="h-100">
  <div class="authincation h-100">
    <div class="container saas-wrap">
      <div class="row justify-content-center align-items-center">
        <div class="col-xl-10 col-lg-11 col-md-12">
          <div class="saas-card">
            <div class="saas-grid">

              <!-- LEFT / BRAND SIDE -->
             <div class="saas-side">
  <div class="brand-chip">
    <span style="width:10px;height:10px;border-radius:999px;background:linear-gradient(135deg,#2e58a8,#20427F);display:inline-block;"></span>
    PGS Centrum · Property Services
  </div>

  <div class="side-title">
    Property Maintenance<br/>Renovations & Repairs
  </div>

  <div class="side-sub">
    Trusted services for condominiums, associations and commercial properties across Central Florida.
    Quality work, clear safety standards, and reliable execution—no matter the project size.
  </div>

  <div class="side-feats">
    <div class="feat">
      <span class="dot"></span>
      <div>
        <b>Tailored solutions</b>
        <small>Customized maintenance and improvement services to enhance and protect your investment.</small>
      </div>
    </div>

    <div class="feat">
      <span class="dot"></span>
      <div>
        <b>Professional team</b>
        <small>Skilled staff delivering consistent, high-quality results with attention to detail.</small>
      </div>
    </div>

    <div class="feat">
      <span class="dot"></span>
      <div>
        <b>Safety-first operations</b>
        <small>Every job is carried out with clear signage and organized execution for safer environments.</small>
      </div>
    </div>

    <div class="feat">
      <span class="dot"></span>
      <div>
        <b>Fast response & logistics</b>
        <small>Vehicles and equipment support for quick transport of personnel, materials, and tools.</small>
      </div>
    </div>
  </div>

  <div style="position:absolute;left:34px;right:34px;bottom:26px;color:rgba(255,255,255,.55);font-size:12px;">
    © <?php echo date('Y'); ?> PGS Centrum · Orlando, FL · All rights reserved
  </div>
</div>

              <!-- RIGHT / FORM -->
              <div class="saas-form">
                <div class="logo-wrap">
                  <img src="assets/img/logo1a.png" alt="pgscentrum" />
                </div>

                <div class="headline">Welcome back</div>
                <div class="subline">Enter your credentials to continue</div>

                <form id="loginForm" class="form" method="POST" action="login.php" autocomplete="on">
                  <input type="hidden" id="op" name="op" value="pms_usrlogin">
                  <input type="hidden" name="login_csrf" value="<?php echo htmlspecialchars($_SESSION['login_csrf'], ENT_QUOTES, 'UTF-8'); ?>">

                  <div class="form-group mb-3">
                    <label class="form-label" for="nickname">User</label>
                    <div class="input-wrap">
                      <input
                        type="text"
                        id="nickname"
                        name="nickname"
                        class="saas-input"
                        placeholder="Type your username"
                        required
                        autocomplete="username webauthn" autocapitalize="none" spellcheck="false" inputmode="text" enterkeyhint="next"
                        value="<?php echo isset($_POST['nickname']) ? htmlspecialchars((string)$_POST['nickname']) : ''; ?>"
                      />
                    </div>
                  </div>

                  <div class="form-group mb-2">
                    <label class="form-label" for="hashpass">Password</label>
                    <div class="input-wrap">
                      <input
                        type="password"
                        id="hashpass"
                        name="hashpass"
                        class="saas-input"
                        placeholder="Type your password"
                        required
                        autocomplete="current-password" enterkeyhint="go"
                      />
                      <button type="button" class="icon-btn" id="togglePass" aria-label="Show/Hide password" title="Show/Hide">
                        <!-- simple eye icon (inline svg) -->
                        <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                          <path d="M2 12C2 12 5.5 5 12 5C18.5 5 22 12 22 12C22 12 18.5 19 12 19C5.5 19 2 12 2 12Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                          <path d="M12 15C13.6569 15 15 13.6569 15 12C15 10.3431 13.6569 9 12 9C10.3431 9 9 10.3431 9 12C9 13.6569 10.3431 15 12 15Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                      </button>
                    </div>
                  </div>

                  <div class="row-actions">
                    <label class="remember" for="basic_checkbox_1">
                      <input type="checkbox" id="basic_checkbox_1" name="remember_username" checked>
                      Remember my username
                    </label>

                    <div style="color:rgba(255,255,255,.55);font-size:12px;">
                      Use saved passwords for quicker sign-in
                    </div>
                  </div>

                  <button id="btnLogin" type="submit" class="btn-saas">
                    <span class="btn-text">Login</span>
                    <span class="btn-loading"><span class="spinner"></span> Signing in...</span>
                  </button>

                  <button id="pgsPasskeyLogin" type="button" class="btn-saas" style="margin-top:11px;background:#203858;border:1px solid rgba(255,255,255,.20)">🔐 Sign in with fingerprint / Face ID</button>
                  <p id="pgsPasskeyError" role="alert" style="color:#ffbdc8;font-size:13px;margin-top:9px" hidden></p>
                  <div id="pgsLoginStatus" role="status" aria-live="polite" class="alert-saas" hidden></div>

                  <?php if (!empty($mensaje)) { ?>
                    <div class="alert-saas" role="alert">
                      <b>Login error:</b> <?php echo htmlspecialchars((string)$mensaje); ?>
                    </div>
                  <?php } ?>

                  <div class="secure-hint" aria-live="polite">
                    <span class="secure-hint-icon" aria-hidden="true">&#128274;</span>
                    Your phone can unlock saved credentials with fingerprint, face recognition or PIN when supported.
                    <strong>To enable biometric sign-in, first log in with your password and register a Passkey.</strong>
                  </div>

                  <div class="mini-footer">
                    Need help? Contact your system administrator.
                  </div>
                </form>
              </div>

            </div>
          </div>

        </div>
      </div>
    </div>
  </div>

  <!-- Script -->
  <!-- Login uses native JavaScript; omit heavy dashboard scripts on this screen. -->
  <!-- Script -->

  <script>
    (function () {
      'use strict';
      const form = document.getElementById('loginForm');
      const btn = document.getElementById('btnLogin');
      const btnText = btn.querySelector('.btn-text');
      const btnLoading = btn.querySelector('.btn-loading');
      const nickname = document.getElementById('nickname');
      const pass = document.getElementById('hashpass');
      const remember = document.getElementById('basic_checkbox_1');
      const toggle = document.getElementById('togglePass');
      const storeKey = 'pgscentrum.remembered_username.v1';
      const rememberKey = 'pgscentrum.remember_username_enabled.v1';

      try {
        const setting = localStorage.getItem(rememberKey);
        remember.checked = setting !== 'false';
        if (remember.checked && !nickname.value) {
          nickname.value = localStorage.getItem(storeKey) || '';
        }
      } catch (_) { /* Private mode or blocked storage: browser autofill still works. */ }

      toggle.addEventListener('click', function () {
        const reveal = pass.type === 'password';
        pass.type = reveal ? 'text' : 'password';
        toggle.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
      });

      remember.addEventListener('change', function () {
        try {
          localStorage.setItem(rememberKey, remember.checked ? 'true' : 'false');
          if (!remember.checked) localStorage.removeItem(storeKey);
        } catch (_) {}
      });

      form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (!form.checkValidity()) { form.reportValidity(); return; }
        if (btn.disabled) return;
        try {
          if (remember.checked) {
            localStorage.setItem(storeKey, nickname.value.trim());
            localStorage.setItem(rememberKey, 'true');
          } else {
            localStorage.removeItem(storeKey);
            localStorage.setItem(rememberKey, 'false');
          }
        } catch (_) {}
        const status = document.getElementById('pgsLoginStatus');
        const showStatus = (message) => {
          if (status) {status.hidden = false; status.textContent = message;}
        };
        if (status) status.hidden = true;
        btn.disabled = true;
        btnText.style.display = 'none';
        btnLoading.style.display = 'inline-flex';
        let moving = false;
        const go = (path) => {
          moving = true;
          window.location.assign(path === 'passkeys_setup.php' ? 'passkeys_setup.php' : 'main.php');
        };
        // One AJAX request avoids mobile WebView getting stuck on the previous
        // page during a native form POST + HTTP 303 redirect.
        const ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
        const timeout = ctrl ? setTimeout(() => ctrl.abort(), 16000) : null;
        try {
          const response = await fetch('login.php', {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {'X-PGS-Login':'1', 'Accept':'application/json'},
            body: new FormData(form),
            ...(ctrl ? {signal: ctrl.signal} : {})
          });
          const result = await response.json();
          if (result.success === true) {
            pass.value = '';
            showStatus('Sign-in successful. Opening your account...');
            go(result.redirect);
            return;
          }
          showStatus(result.message || 'Unable to sign in. Check your credentials.');
        } catch (_) {
          // A network interruption can happen AFTER PHP committed a valid session.
          // Check server-side session once before telling the user to retry.
          try {
            const response = await fetch('auth_status.php', {
              credentials: 'same-origin', cache: 'no-store',
              headers:{'Accept':'application/json'}
            });
            const result = await response.json();
            if (result.authenticated === true) {
              pass.value = '';
              showStatus('Sign-in successful. Opening your account...');
              go(result.redirect);
              return;
            }
          } catch (_) {}
          showStatus('Could not confirm your sign-in. Check your connection and try again.');
        } finally {
          if (timeout) clearTimeout(timeout);
          if (!moving) {
            btn.disabled = false;
            btnText.style.display = '';
            btnLoading.style.display = 'none';
          }
        }
      });

      // On browser history restore, re-enable the button.
      window.addEventListener('pageshow', function () {
        btn.disabled = false;
        btnText.style.display = '';
        btnLoading.style.display = 'none';
        const status = document.getElementById('pgsLoginStatus');
        if (status) status.hidden = true;
      });
    })();
  </script>
  <script src="assets/js/pgs-passkeys.js?v=2" defer></script>
</body>
</html>