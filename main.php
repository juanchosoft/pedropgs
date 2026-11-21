<?php
require './admin/include/generic_classes.php';
include './admin/classes/Main.php';
include './admin/classes/Unidades.php';

// Dashboard: avoid an unused database query on every page load.
$modulo = 'Dashboard';


/*
 * Days off / rest days for logged user
 * ---------------------------------------------------------
 * This block is safe:
 * - It only shows the card if the logged user has information.
 * - It keeps the current dashboard functionality intact.
 * - It supports the getAllrestday(['id' => user_id]) format.
 */
$sessionUserId = 0;

if (class_exists('SessionData')) {
  if (method_exists('SessionData', 'getUserId')) {
    $sessionUserId = intval(SessionData::getUserId());
  } elseif (method_exists('SessionData', 'getIdUser')) {
    $sessionUserId = intval(SessionData::getIdUser());
  } elseif (method_exists('SessionData', 'getUserIdData')) {
    $sessionUserId = intval(SessionData::getUserIdData());
  }
}

/* Extra fallback, in case the project stores the user id directly in $_SESSION */
if ($sessionUserId <= 0 && isset($_SESSION)) {
  $sessionUserId = intval(
    $_SESSION['user_id']
    ?? $_SESSION['id_user']
    ?? $_SESSION['id']
    ?? $_SESSION['usuario_id']
    ?? ($_SESSION['session_user']['id'] ?? null)
    ?? ($_SESSION['session_user']['usuario_id'] ?? null)
    ?? 0
  );
}

$arrDescansoData = array();
$restDayUser = null;
$hasRestDayInfo = false;

// Never request all employees' rest-day balances if the session ID is unknown.
$arrDescanso = ($sessionUserId > 0)
  ? Main::getAllrestday(array('id' => $sessionUserId))
  : array('output' => array('valid' => false, 'response' => array()));

if (
  isset($arrDescanso['output']['valid']) &&
  $arrDescanso['output']['valid'] === true &&
  isset($arrDescanso['output']['response']) &&
  is_array($arrDescanso['output']['response'])
) {
  $arrDescansoData = $arrDescanso['output']['response'];

  /*
   * If Main::getAllrestday(['id' => logged_user_id]) already returns one user,
   * take the first one. If it returns all, filter by usuario_id.
   */
  foreach ($arrDescansoData as $descansoItem) {
    $usuarioIdRow = intval($descansoItem['usuario_id'] ?? $descansoItem['id'] ?? 0);

    if ($usuarioIdRow === $sessionUserId) {
      $restDayUser = $descansoItem;
      break;
    }
  }

  if (
    is_array($restDayUser) &&
    array_key_exists('dias_descanso', $restDayUser) &&
    $restDayUser['dias_descanso'] !== null &&
    $restDayUser['dias_descanso'] !== ''
  ) {
    $hasRestDayInfo = true;
  }
}


$unitsResult = Unidades::getAll(null);
$isvalid = !empty($unitsResult['output']['valid']);
$arrUnidades = ($isvalid && is_array($unitsResult['output']['response'] ?? null))
  ? $unitsResult['output']['response'] : array();
$userUnidades = SessionData::getUnidadesUser();
$userUnidades = is_array($userUnidades) ? array_map('intval', $userUnidades) : array();
$userType = SessionData::getUserType();
$visibleUnits = array();
foreach ($arrUnidades as $unidad) {
  if (!is_array($unidad)) continue;
  $unitId = (int) ($unidad['id'] ?? 0);
  if ($userType == Util::SuperAdmin() ||
      (($userType == Util::Manager() || $userType == Util::Staff()) && in_array($unitId, $userUnidades, true))) {
    $visibleUnits[] = $unidad;
  }
}
$fullName = htmlspecialchars((string)SessionData::getUserFullName(), ENT_QUOTES, 'UTF-8');
$roleName = htmlspecialchars((string)$userType, ENT_QUOTES, 'UTF-8');
$config = SessionData::getConfigSistema();
$companyName = htmlspecialchars((string)($config['empresa'] ?? 'PGS Centrum'), ENT_QUOTES, 'UTF-8');
$logoUrl = (string) SessionData::getLogoEmpresa();
// Avoid unsafe URL schemes in a dynamic image src.
if (preg_match('~^(?:javascript|data):~i', trim($logoUrl))) $logoUrl = '';
$logoUrl = htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include './admin/include/generic_head.php'; ?>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#f7f8fa">

  <!-- ✅ Premium SaaS UI (RED + BLACK) | Only design -->
  <style>
    :root{--pgs-red:#d91f32;--pgs-red-dark:#aa1023;--pgs-ink:#111827;--pgs-muted:#526074;--pgs-border:#e6eaf0;--pgs-radius:18px}
    .content-body{background:#f5f7fa!important;min-height:100vh}
    .content-body .container-fluid{max-width:1240px;margin:auto;padding:18px 18px 30px}
    .pgs-hero,.pgs-rest-card,.pgs-unit-card.card,.pgs-welcome,.pgs-alert{background:#fff!important;border:1px solid var(--pgs-border)!important;border-radius:var(--pgs-radius)!important;box-shadow:0 5px 20px rgba(16,24,40,.045)!important}
    .pgs-hero{padding:20px;margin-bottom:16px;border-top:3px solid var(--pgs-red)!important}
    .pgs-hero-inner{display:flex;flex-direction:column;gap:12px}
    .pgs-hero-title{font-size:clamp(1.25rem,3vw,1.7rem);color:var(--pgs-ink);font-weight:800;line-height:1.2;margin:0}
    .pgs-hero-sub{margin:5px 0 0;font-size:.9rem;color:var(--pgs-muted);line-height:1.4}
    .pgs-chiprow,.pgs-hero-actions{display:flex;flex-wrap:wrap;align-items:center;gap:8px}
    .pgs-chip,.pgs-pill,.pgs-rest-mini{display:inline-flex;gap:7px;align-items:center;border:1px solid var(--pgs-border);border-radius:999px;padding:7px 11px;background:#f8fafc;color:#334155;font-size:.78rem;font-weight:650;max-width:100%;overflow-wrap:anywhere}
    .pgs-chip i,.pgs-rest-mini i{color:var(--pgs-red)}
    .pgs-btn,.pgs-btn-outline,.pgs-go,.pgs-support{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:44px;border-radius:12px!important;padding:9px 15px!important;font-size:.88rem!important;font-weight:750!important;text-decoration:none!important;box-shadow:none!important;transition:background .15s ease,transform .15s ease}
    .pgs-btn,.pgs-go{background:var(--pgs-red)!important;border:1px solid var(--pgs-red)!important;color:#fff!important}
    .pgs-btn:hover,.pgs-go:hover{background:var(--pgs-red-dark)!important;color:#fff!important}
    .pgs-btn-outline,.pgs-support{background:#fff!important;border:1px solid #cfd6e1!important;color:var(--pgs-ink)!important}
    .pgs-btn:focus-visible,.pgs-go:focus-visible{outline:3px solid #f7a1ab;outline-offset:2px}
    .pgs-alert{padding:13px 44px 13px 16px!important;margin-bottom:16px!important}
    .pgs-alert-line{display:flex;align-items:center;gap:8px;flex-wrap:wrap;color:#334155;font-size:.9rem}
    .pgs-alert-line strong{color:var(--pgs-ink)}
    .pgs-badge,.pgs-rest-kicker{display:inline-flex;align-items:center;gap:6px;background:#fff1f2;color:#a51627;border:1px solid #ffd9dd;border-radius:999px;padding:5px 10px;font-size:.74rem;font-weight:750}
    .pgs-rest-wrapper{margin-bottom:16px}
    .pgs-rest-card{padding:18px;border-left:4px solid var(--pgs-red)!important}
    .pgs-rest-content{display:flex;align-items:center;justify-content:space-between;gap:14px}
    .pgs-rest-left{display:flex;align-items:center;gap:12px;min-width:0}
    .pgs-rest-left>div:last-child{min-width:0}
    .pgs-rest-icon,.pgs-unit-icon{display:flex;align-items:center;justify-content:center;flex:0 0 auto;background:#fff1f2;color:var(--pgs-red);border-radius:12px;width:44px;height:44px;font-size:1.1rem}
    .pgs-rest-title{font-size:1.05rem;line-height:1.35;font-weight:800;color:var(--pgs-ink);margin:8px 0 0;overflow-wrap:anywhere}
    .pgs-rest-sub{font-size:.82rem;line-height:1.45;color:var(--pgs-muted);margin:5px 0 0}
    .pgs-rest-number{display:flex;flex-direction:column;align-items:center;justify-content:center;flex:0 0 79px;min-height:79px;background:var(--pgs-red);color:#fff;border-radius:16px;text-align:center}
    .pgs-rest-number strong{font-size:1.75rem;line-height:1.1}.pgs-rest-number span{font-size:.72rem}
    .pgs-rest-foot{display:flex;flex-wrap:wrap;gap:7px;margin-top:12px}
    .pgs-section-head{display:flex;align-items:baseline;justify-content:space-between;gap:10px;margin:16px 0 12px}
    .pgs-section-head h2{font-size:1.08rem;color:var(--pgs-ink);font-weight:800;margin:0}
    .pgs-section-head span{font-size:.8rem;color:var(--pgs-muted)}
    .pgs-grid>[class*=col-]{margin-bottom:12px}
    .pgs-unit-card.card{height:100%;margin-bottom:0!important;overflow:hidden}
    .pgs-unit-card .card-body{padding:16px!important;display:flex;flex-direction:column;justify-content:space-between;height:100%}
    .pgs-unit-head{display:flex;align-items:flex-start;gap:11px}
    .pgs-unit-head>div:last-child{min-width:0}
    .pgs-unit-title{font-size:.96rem;line-height:1.35;color:var(--pgs-ink);font-weight:800;overflow-wrap:anywhere;margin:0}
    .pgs-unit-meta{font-size:.8rem;color:var(--pgs-muted);line-height:1.4;margin:5px 0 0}
    .pgs-unit-actions{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:15px}
    .pgs-go{min-width:82px}.pgs-pill{font-size:.73rem}
    .pgs-welcome{padding:18px!important;margin-top:8px}
    .pgs-welcome .media{display:flex;gap:15px;align-items:center}
    .pgs-welcome-logo{width:74px;height:74px;object-fit:contain;flex-shrink:0}
    .pgs-welcome h4{font-size:1.05rem;color:var(--pgs-ink);margin:0;font-weight:800}
    .pgs-welcome p{color:var(--pgs-muted);margin:5px 0 0;font-size:.86rem;overflow-wrap:anywhere}
    .pgs-note{color:#64748b;font-size:.78rem;margin:10px 0 0}
    @media(max-width:767.98px){
      .content-body .container-fluid{padding:12px 12px calc(20px + env(safe-area-inset-bottom))}
      .pgs-hero,.pgs-rest-card,.pgs-welcome{padding:15px}
      .pgs-chiprow{gap:6px}
      .pgs-chip{font-size:.74rem;padding:6px 9px}
      .pgs-hero-actions .pgs-btn{width:100%}
      .pgs-rest-icon{width:38px;height:38px}
      .pgs-rest-number{flex-basis:66px;min-height:66px;border-radius:12px}
      .pgs-rest-number strong{font-size:1.5rem}
      .pgs-rest-title{font-size:.92rem}
      .pgs-rest-sub{font-size:.78rem}
      .pgs-welcome .media{align-items:flex-start}
      .pgs-welcome-logo{width:60px;height:60px}
      .pgs-unit-card .card-body{padding:14px!important}
    }
    @media(max-width:359.98px){.pgs-rest-icon{display:none}.pgs-rest-left{gap:0}.pgs-rest-number{flex-basis:58px;min-height:60px}.pgs-unit-actions{flex-wrap:wrap}.pgs-go{width:100%}}
    @media(prefers-reduced-motion:reduce){.pgs-btn,.pgs-btn-outline,.pgs-go{transition:none!important}}
  </style>
</head>

<body>
  <div id="preloader" aria-hidden="true">
    <div class="sk-three-bounce">
      <div class="sk-child sk-bounce1"></div>
      <div class="sk-child sk-bounce2"></div>
      <div class="sk-child sk-bounce3"></div>
    </div>
  </div>


  <div id="main-wrapper">
    <?php include './admin/include/generic_header.php'; ?>

    <div class="deznav">
      <div class="deznav-scroll">
        <?php include './admin/include/generic_navbar.php'; ?>
      </div>
    </div>

    <div class="content-body">
      <div class="container-fluid">

        <!-- ✅ SaaS Hero -->
        <div class="pgs-hero">
          <div class="pgs-hero-inner">
            <div>
              <h1 class="pgs-hero-title">Dashboard</h1>
              <p class="pgs-hero-sub">Fast access to your units, reports, and administrative tools.</p>
            </div>

            <div class="pgs-chiprow">
              <span class="pgs-chip">
                <i class="fa fa-user"></i>
                <?php echo $fullName; ?>
              </span>
              <span class="pgs-chip">
                <i class="fa fa-shield"></i>
                Role: <?php echo $roleName; ?>
              </span>
              <span class="pgs-chip">
                <i class="fa fa-clock-o"></i>
                <?php echo date('Y-m-d H:i'); ?>
              </span>
            </div>

            <div class="pgs-hero-actions">
              <a href="report.php" class="btn pgs-btn">
                Open Reports <i class="las la-long-arrow-alt-right ml-2"></i>
              </a>

            </div>
          </div>
        </div>

        <?php include './admin/include/report_quick_edit_widget.php'; ?>

        <!-- Optional dismissible welcome banner -->
        <div class="alert alert-light alert-dismissible fade show pgs-alert" role="status">
          <div class="pgs-alert-line">
            <strong>Welcome,</strong> <?php echo $fullName; ?>
            <span class="pgs-badge">
              <i class="fa fa-shield"></i> <?php echo $roleName; ?>
            </span>
          </div>
          <button type="button" class="close h-100" data-dismiss="alert" aria-label="Close">
            <span><i class="mdi mdi-close"></i></span>
          </button>
        </div>

        <?php if ($hasRestDayInfo): ?>
          <?php
            $diasDescanso = intval($restDayUser['dias_descanso']);
            $nombreRestUser = trim(
              ($restDayUser['nombre_usuario'] ?? '') . ' ' . ($restDayUser['apellido'] ?? '')
            );
            if ($nombreRestUser === '') {
              $nombreRestUser = $restDayUser['nombre_empleado'] ?? SessionData::getUserFullName();
            }
            $employeeIdRest = $restDayUser['employee_id'] ?? $restDayUser['cc'] ?? '';
          ?>
          <!-- ✅ Logged user rest days card -->
          <div class="pgs-rest-wrapper">
            <div class="pgs-rest-card">
              <div class="pgs-rest-content">
                <div class="pgs-rest-left">
                  <div class="pgs-rest-icon" aria-hidden="true">
                    <i class="fa fa-calendar-check-o"></i>
                  </div>

                  <div style="min-width:0;">
                    <span class="pgs-rest-kicker">
                      <i class="fa fa-user-check"></i>
                      Employee rest balance
                    </span>
                    <h3 class="pgs-rest-title">
                      Days off available for <?php echo htmlspecialchars((string)$nombreRestUser, ENT_QUOTES, 'UTF-8'); ?>
                    </h3>
                    <p class="pgs-rest-sub">
                      These are the available leave days you still have. If you need to use them, please request approval from your supervisor.
                    </p>
                  </div>
                </div>

                <div class="pgs-rest-number" title="Rest days available">
                  <strong><?php echo $diasDescanso; ?></strong>
                  <span><?php echo ($diasDescanso == 1) ? 'day' : 'days'; ?></span>
                </div>
              </div>

              <div class="pgs-rest-foot">
                <?php if ($employeeIdRest !== ''): ?>
                  <span class="pgs-rest-mini">
                    <i class="fa fa-id-card-o"></i>
                    Employee ID: <?php echo htmlspecialchars((string)$employeeIdRest, ENT_QUOTES, 'UTF-8'); ?>
                  </span>
                <?php endif; ?>

                <span class="pgs-rest-mini">
                  <i class="fa fa-database"></i>
                  Updated from database
                </span>
              </div>
            </div>
          </div>
        <?php endif; ?>


        <!-- Accessible units: filtered server-side using current session permissions. -->
        <div class="pgs-section-head">
          <h2>My units</h2>
          <span><?php echo count($visibleUnits); ?> available</span>
        </div>
        <div class="row pgs-grid">
          <?php foreach ($visibleUnits as $unidad): ?>
            <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
              <div class="widget-stat card pgs-unit-card">
                <div class="card-body">
                  <div class="pgs-unit-head">
                    <div class="pgs-unit-icon" aria-hidden="true"><i class="ti-id-badge"></i></div>
                    <div>
                      <h3 class="pgs-unit-title"><?php echo htmlspecialchars((string)($unidad['nombre'] ?? 'Unit'), ENT_QUOTES, 'UTF-8'); ?></h3>
                      <p class="pgs-unit-meta">View reports and manage your work</p>
                    </div>
                  </div>
                  <div class="pgs-unit-actions">
                    <span class="pgs-pill">ID: <?php echo (int)($unidad['id'] ?? 0); ?></span>
                    <a href="report.php" class="btn pgs-go" aria-label="Open reports for <?php echo htmlspecialchars((string)($unidad['nombre'] ?? 'Unit'), ENT_QUOTES, 'UTF-8'); ?>">Open <i class="las la-arrow-right" aria-hidden="true"></i></a>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if (count($visibleUnits) === 0): ?>
          <div class="pgs-alert" role="status">No units are currently assigned to your account.</div>
        <?php endif; ?>

        <!-- ✅ License / Brand Card -->
        <div class="row">
          <div class="col-xl-12">
            <div class="welcome-card pgs-welcome mt-2 mb-4 position-relative">
              <div class="bootstrap-media">
                <div class="media">
                  <img
                    src="<?php echo $logoUrl; ?>" loading="lazy" decoding="async"
                    class="pgs-welcome-logo"
                    alt="Company logo"
                  />

                  <div class="media-body">
                    <h4>Administrative System</h4>
                    <p>Licensed to <?php echo $companyName; ?></p>

                    <p class="pgs-note">For assistance, contact your system administrator.</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <?php include './admin/include/gerenic_footer.php'; ?>
    <?php include './admin/include/gerenic_script.php'; ?>
    <script>
      // In case a third-party theme script fails, never leave the dashboard blocked.
      (function () {
        var loader = document.getElementById('preloader');
        function dismissLoader() { if (loader) loader.style.display = 'none'; }
        if (document.readyState === 'complete') dismissLoader();
        else window.addEventListener('load', dismissLoader, {once:true});
        setTimeout(dismissLoader, 4500);
      })();
    </script>
    <?php if (class_exists('SessionData') && SessionData::getPermission(9)): ?>
    <script>
      window.PGS_CAN_EDIT_REPORT = true;
    </script>
    <script src="./admin/js/report_quick_edit.js"></script>
    <?php endif; ?>
  </div>
</body>

</html>