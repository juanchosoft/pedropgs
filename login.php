<?php
session_start();
$mensaje = '';

include './admin/classes/SessionData.php';
include './admin/classes/DbConection.php';
include './admin/classes/Util.php';
include './admin/classes/Usuario.php';

if (isset($_SESSION['session_user'])) {
  ?>
  <script>
    window.location = 'main.php';
  </script>
  <?php
  exit;
} else {
  $rqst = $_REQUEST;
  $op = isset($rqst['op']) ? $rqst['op'] : '';
  if ($op == 'pms_usrlogin') {
    $nickname = isset($rqst['nickname']) ? $rqst['nickname'] : '';
    $hashpass = isset($rqst['hashpass']) ? $rqst['hashpass'] : '';

    if ($nickname != "" && $hashpass != "") {
      $hashpass = md5($hashpass);
      $arr = array('nickname' => $nickname, 'hashpass' => $hashpass);
      $res = Usuario::login($arr);
      $isvalid = $res['output']['valid'];
      if ($isvalid) {
        $_SESSION['session_user'] = $res['output']['response'][0];
        $_SESSION['session_user']['permisos'] = $res['output']['permisos'];
        $_SESSION['session_user']['config'] = $res['output']['config'];
        $_SESSION['session_user']['telefono_emergencia'] = $res['output']['telefono_emergencia'];
        $_SESSION['session_user']['unidad'] = $res['output']['unidad'];
        ?>
        <script>
          window.location = 'main.php';
        </script>
        <?php
        exit;
      } else {
        $mensaje = $res['output']['response']['content'];
      }
    } else {
      $mensaje = 'All fields are required.';
    }
  }
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

                <form id="loginForm" class="form" method="POST" action="login.php" autocomplete="off">
                  <input type="hidden" id="op" name="op" value="pms_usrlogin">

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
                        autocomplete="username"
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
                        autocomplete="current-password"
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
                      <input type="checkbox" id="basic_checkbox_1">
                      Remember me
                    </label>

                    <div style="color:rgba(255,255,255,.55);font-size:12px;">
                      Tip: Press <b>Enter</b> to login
                    </div>
                  </div>

                  <button id="btnLogin" type="submit" class="btn-saas">
                    <span class="btn-text">Login</span>
                    <span class="btn-loading"><span class="spinner"></span> Signing in...</span>
                  </button>

                  <?php if (!empty($mensaje)) { ?>
                    <div class="alert-saas" role="alert">
                      <b>Login error:</b> <?php echo htmlspecialchars((string)$mensaje); ?>
                    </div>
                  <?php } ?>

                  <div class="mini-footer">
                    Having issues? Contact <a href="#" onclick="return false;">System Support</a>
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
  <?php include './admin/include/gerenic_script.php'; ?>
  <!-- Script -->

  <script>
    (function(){
      const form = document.getElementById('loginForm');
      const btn  = document.getElementById('btnLogin');
      const btnText = btn.querySelector('.btn-text');
      const btnLoading = btn.querySelector('.btn-loading');

      const pass = document.getElementById('hashpass');
      const toggle = document.getElementById('togglePass');

      // Show/Hide password
      toggle.addEventListener('click', function(){
        const isPass = pass.type === 'password';
        pass.type = isPass ? 'text' : 'password';
      });

      // Button loading state
      form.addEventListener('submit', function(){
        // minimal front validation (HTML required already)
        btn.setAttribute('disabled', 'disabled');
        btnText.style.display = 'none';
        btnLoading.style.display = 'inline-flex';
      });
    })();
  </script>
</body>
</html>