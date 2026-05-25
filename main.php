<?php
require './admin/include/generic_classes.php';
include './admin/classes/Main.php';
include './admin/classes/Unidades.php';

// Home info
$arr = Main::getDataMain(null);
$isvalid = $arr['output']['valid'];
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
    ?? 0
  );
}

$arrDescansoData = array();
$restDayUser = null;
$hasRestDayInfo = false;

$arrDescansoRqst = ($sessionUserId > 0) ? array('id' => $sessionUserId) : null;
$arrDescanso = Main::getAllrestday($arrDescansoRqst);

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

    if ($sessionUserId <= 0 || $usuarioIdRow === $sessionUserId || count($arrDescansoData) === 1) {
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


$arrUnidades = Unidades::getAll(null);
$isvalid = $arrUnidades['output']['valid'];
$arrUnidades = $arrUnidades['output']['response'];
$arrUnidadesData = $arrUnidades;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <?php include './admin/include/generic_head.php'; ?>

  <!-- ✅ Premium SaaS UI (RED + BLACK) | Only design -->
  <style>
    :root{
      --pgs-red:#E11D2E;
      --pgs-red-2:#B3121E;
      --pgs-black:#0B0F14;
      --pgs-ink:#0f172a;
      --pgs-muted:#6b7280;
      --pgs-border:rgba(2,6,23,.10);
      --pgs-card:rgba(255,255,255,.92);
      --pgs-glass:rgba(255,255,255,.76);
      --pgs-shadow:0 18px 45px rgba(2,6,23,.14);
      --pgs-shadow-soft:0 12px 28px rgba(2,6,23,.10);
      --pgs-radius:18px;
    }

    .content-body .container-fluid{ padding-top: 14px; }
    @media (min-width: 992px){ .content-body .container-fluid{ padding-top: 18px; } }

    /* Hero */
    .pgs-hero{
      position: relative;
      border-radius: calc(var(--pgs-radius) + 6px);
      padding: 18px 18px;
      overflow: hidden;
      border: 1px solid var(--pgs-border);
      background:
        radial-gradient(1200px 420px at 10% 0%, rgba(225,29,46,.18), transparent 60%),
        radial-gradient(900px 380px at 90% 10%, rgba(11,15,20,.16), transparent 55%),
        linear-gradient(180deg, rgba(255,255,255,.94), rgba(255,255,255,.82));
      box-shadow: var(--pgs-shadow-soft);
      margin-bottom: 16px;
    }
    .pgs-hero:before{
      content:"";
      position:absolute;
      inset:-2px;
      background:
        radial-gradient(420px 260px at 10% 20%, rgba(225,29,46,.18), transparent 70%),
        radial-gradient(360px 260px at 90% 0%, rgba(11,15,20,.12), transparent 70%);
      pointer-events:none;
    }
    .pgs-hero-inner{ position:relative; display:flex; flex-direction: column; gap: 10px; }
    .pgs-hero-title{
      font-weight: 900;
      letter-spacing: -.02em;
      color: var(--pgs-black);
      margin:0;
      line-height: 1.1;
      font-size: 1.15rem;
    }
    .pgs-hero-sub{ margin:0; color: var(--pgs-muted); font-size: .92rem; font-weight: 600; }

    .pgs-chiprow{ display:flex; flex-wrap: wrap; gap: 8px; align-items:center; }
    .pgs-chip{
      display:inline-flex; align-items:center; gap: 8px;
      padding: 8px 10px;
      border-radius: 999px;
      border: 1px solid rgba(11,15,20,.10);
      background: rgba(255,255,255,.88);
      color: rgba(11,15,20,.86);
      font-weight: 800;
      font-size: .82rem;
      backdrop-filter: blur(10px);
    }
    .pgs-chip i{ color: var(--pgs-red); }

    .pgs-hero-actions{ display:flex; gap: 10px; flex-wrap: wrap; align-items:center; margin-top: 2px; }

    /* Buttons */
    .pgs-btn{
      border: 1px solid rgba(225,29,46,.26) !important;
      background: linear-gradient(180deg, var(--pgs-red), var(--pgs-red-2)) !important;
      color: #fff !important;
      border-radius: 999px !important;
      padding: 10px 14px !important;
      font-weight: 900 !important;
      letter-spacing: .01em;
      box-shadow: 0 12px 22px rgba(225,29,46,.22);
      transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
    }
    .pgs-btn:hover{
      transform: translateY(-1px);
      box-shadow: 0 18px 32px rgba(225,29,46,.26);
      filter: brightness(1.02);
      color:#fff !important;
    }
    .pgs-btn-outline{
      border: 1px solid rgba(11,15,20,.16) !important;
      background: rgba(255,255,255,.88) !important;
      color: var(--pgs-black) !important;
      border-radius: 999px !important;
      padding: 10px 14px !important;
      font-weight: 900 !important;
      transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
      backdrop-filter: blur(10px);
    }
    .pgs-btn-outline:hover{
      transform: translateY(-1px);
      box-shadow: 0 14px 26px rgba(2,6,23,.10);
      background: rgba(255,255,255,.96) !important;
      color: var(--pgs-black) !important;
    }

    /* Premium alert banner */
    .pgs-alert{
      border-radius: var(--pgs-radius) !important;
      border: 1px solid var(--pgs-border) !important;
      background: linear-gradient(180deg, rgba(255,255,255,.94), rgba(255,255,255,.84)) !important;
      box-shadow: var(--pgs-shadow-soft);
      padding: 14px 14px;
      position: relative;
      overflow: hidden;
    }
    .pgs-alert:after{
      content:"";
      position:absolute;
      inset:auto -40px -60px auto;
      width: 220px;
      height: 220px;
      background: radial-gradient(circle at 30% 30%, rgba(225,29,46,.18), transparent 65%);
      transform: rotate(12deg);
      pointer-events:none;
    }
    .pgs-alert strong{ font-weight: 1000; color: var(--pgs-black); }
    .pgs-alert .pgs-alert-line{
      display:flex; flex-wrap: wrap; gap: 8px; align-items:center;
      color: rgba(11,15,20,.86);
      font-weight: 800;
    }
    .pgs-badge{
      display:inline-flex; align-items:center; gap: 6px;
      padding: 6px 10px;
      border-radius: 999px;
      background: rgba(225,29,46,.10);
      border: 1px solid rgba(225,29,46,.18);
      color: var(--pgs-red-2);
      font-weight: 1000;
      font-size: .78rem;
    }

    /* Logged user rest days card */
    .pgs-rest-wrapper{
      margin-bottom: 16px;
    }
    .pgs-rest-card{
      position: relative;
      min-height: 164px;
      border-radius: calc(var(--pgs-radius) + 6px);
      border: 1px solid rgba(225,29,46,.14);
      background:
        radial-gradient(420px 220px at 10% 10%, rgba(225,29,46,.18), transparent 66%),
        radial-gradient(520px 240px at 100% 20%, rgba(11,15,20,.12), transparent 64%),
        linear-gradient(180deg, rgba(255,255,255,.97), rgba(255,255,255,.86));
      box-shadow: var(--pgs-shadow-soft);
      overflow: hidden;
      padding: 18px;
    }
    .pgs-rest-card:before{
      content:"";
      position:absolute;
      right:-70px;
      top:-70px;
      width: 210px;
      height: 210px;
      border-radius: 999px;
      background: rgba(225,29,46,.10);
      filter: blur(.2px);
      pointer-events:none;
    }
    .pgs-rest-card:after{
      content:"";
      position:absolute;
      right: 22px;
      bottom: 18px;
      width: 92px;
      height: 92px;
      border-radius: 999px;
      background: linear-gradient(180deg, rgba(225,29,46,.18), rgba(179,18,30,.08));
      border: 1px solid rgba(225,29,46,.16);
      pointer-events:none;
    }
    .pgs-rest-content{
      position:relative;
      z-index:2;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap: 16px;
    }
    .pgs-rest-left{
      display:flex;
      align-items:center;
      gap: 14px;
      min-width:0;
    }
    .pgs-rest-icon{
      width: 54px;
      height: 54px;
      border-radius: 18px;
      display:flex;
      align-items:center;
      justify-content:center;
      flex:0 0 auto;
      color:#fff;
      background: linear-gradient(180deg, var(--pgs-red), var(--pgs-red-2));
      box-shadow: 0 18px 32px rgba(225,29,46,.25);
      font-size: 1.25rem;
    }
    .pgs-rest-kicker{
      display:inline-flex;
      align-items:center;
      gap: 6px;
      padding: 6px 10px;
      border-radius: 999px;
      background: rgba(225,29,46,.10);
      border: 1px solid rgba(225,29,46,.16);
      color: var(--pgs-red-2);
      font-size: .76rem;
      font-weight: 1000;
      margin-bottom: 8px;
    }
    .pgs-rest-title{
      margin:0;
      color: var(--pgs-black);
      font-size: 1rem;
      line-height: 1.2;
      font-weight: 1000;
      letter-spacing: -.01em;
    }
    .pgs-rest-sub{
      margin: 5px 0 0 0;
      color: var(--pgs-muted);
      font-size: .84rem;
      font-weight: 700;
    }
    .pgs-rest-number{
      position:relative;
      z-index:3;
      width: 96px;
      min-width: 96px;
      height: 96px;
      border-radius: 28px;
      display:flex;
      flex-direction:column;
      align-items:center;
      justify-content:center;
      color:#fff;
      background:
        radial-gradient(60px 60px at 30% 20%, rgba(255,255,255,.32), transparent 68%),
        linear-gradient(180deg, var(--pgs-red), var(--pgs-red-2));
      box-shadow: 0 22px 38px rgba(225,29,46,.28);
      border: 1px solid rgba(255,255,255,.26);
      text-align:center;
    }
    .pgs-rest-number strong{
      display:block;
      font-size: 2.05rem;
      line-height: .95;
      font-weight: 1000;
      letter-spacing: -.04em;
    }
    .pgs-rest-number span{
      display:block;
      margin-top: 4px;
      font-size: .72rem;
      line-height: 1;
      font-weight: 900;
      text-transform: uppercase;
      opacity:.94;
    }
    .pgs-rest-foot{
      position:relative;
      z-index:2;
      display:flex;
      flex-wrap:wrap;
      gap: 8px;
      margin-top: 14px;
    }
    .pgs-rest-mini{
      display:inline-flex;
      align-items:center;
      gap: 6px;
      padding: 7px 10px;
      border-radius: 999px;
      border: 1px solid rgba(11,15,20,.09);
      background: rgba(255,255,255,.76);
      color: rgba(11,15,20,.72);
      font-size: .78rem;
      font-weight: 900;
      backdrop-filter: blur(10px);
    }
    .pgs-rest-mini i{ color: var(--pgs-red); }
    @media (max-width: 575.98px){
      .pgs-rest-card{ padding: 16px; }
      .pgs-rest-content{
        align-items:flex-start;
      }
      .pgs-rest-left{
        align-items:flex-start;
      }
      .pgs-rest-icon{
        width: 46px;
        height: 46px;
        border-radius: 16px;
      }
      .pgs-rest-number{
        width: 78px;
        min-width: 78px;
        height: 78px;
        border-radius: 22px;
      }
      .pgs-rest-number strong{
        font-size: 1.65rem;
      }
      .pgs-rest-title{
        font-size: .94rem;
      }
    }


    /* Unit cards */
    .pgs-unit-card.card{
      border: 1px solid var(--pgs-border) !important;
      border-radius: var(--pgs-radius) !important;
      background: var(--pgs-card) !important;
      box-shadow: var(--pgs-shadow-soft);
      overflow: hidden;
      transition: transform .18s ease, box-shadow .18s ease;
      height: 100%;
    }
    .pgs-unit-card.card:hover{
      transform: translateY(-2px);
      box-shadow: var(--pgs-shadow);
    }
    .pgs-unit-card .card-body{ padding: 16px 16px !important; }
    .pgs-unit-head{ display:flex; align-items:flex-start; gap: 12px; }

    .pgs-unit-icon{
      width: 42px; height: 42px;
      border-radius: 14px;
      display:flex; align-items:center; justify-content:center;
      flex: 0 0 auto;
      background:
        radial-gradient(28px 28px at 30% 25%, rgba(255,255,255,.65), transparent 70%),
        linear-gradient(180deg, rgba(225,29,46,.16), rgba(11,15,20,.06));
      border: 1px solid rgba(11,15,20,.10);
      color: var(--pgs-red);
      box-shadow: 0 10px 18px rgba(2,6,23,.08);
    }

    .pgs-unit-title{
      margin:0;
      font-weight: 1000;
      color: var(--pgs-black);
      letter-spacing: -.01em;
      font-size: .98rem;
      line-height: 1.2;
    }
    .pgs-unit-meta{
      margin: 4px 0 0 0;
      color: var(--pgs-muted);
      font-size: .82rem;
      font-weight: 700;
    }
    .pgs-unit-actions{
      display:flex;
      justify-content: space-between;
      align-items:center;
      gap: 10px;
      margin-top: 12px;
    }
    .pgs-pill{
      display:inline-flex;
      align-items:center;
      padding: 6px 10px;
      border-radius: 999px;
      background: rgba(11,15,20,.04);
      border: 1px solid rgba(11,15,20,.08);
      color: rgba(11,15,20,.75);
      font-size: .78rem;
      font-weight: 900;
    }
    .pgs-go{
      border-radius: 999px !important;
      padding: 9px 14px !important;
      font-weight: 1000 !important;
      letter-spacing: .02em;
      border: 1px solid rgba(225,29,46,.28) !important;
      background: linear-gradient(180deg, var(--pgs-red), var(--pgs-red-2)) !important;
      box-shadow: 0 12px 20px rgba(225,29,46,.22);
      transition: transform .18s ease, box-shadow .18s ease;
      color:#fff !important;
    }
    .pgs-go:hover{
      transform: translateY(-1px);
      box-shadow: 0 18px 30px rgba(225,29,46,.26);
      color:#fff !important;
    }

    /* Welcome / License card */
    .welcome-card.pgs-welcome{
      border-radius: calc(var(--pgs-radius) + 8px) !important;
      border: 1px solid var(--pgs-border);
      background:
        radial-gradient(900px 320px at 0% 0%, rgba(225,29,46,.18), transparent 55%),
        radial-gradient(900px 340px at 100% 10%, rgba(11,15,20,.14), transparent 55%),
        linear-gradient(180deg, rgba(255,255,255,.96), rgba(255,255,255,.86));
      box-shadow: var(--pgs-shadow);
      padding: 18px !important;
    }
    .pgs-welcome .media{ gap: 14px; align-items: center; }
    .pgs-welcome-logo{
      width: 110px; height: 110px;
      object-fit: contain;
      border-radius: 18px;
      background: rgba(255,255,255,.80);
      border: 1px solid var(--pgs-border);
      padding: 10px;
      box-shadow: 0 14px 26px rgba(2,6,23,.10);
    }
    @media (max-width: 575.98px){
      .pgs-welcome .media{ flex-direction: column; align-items: flex-start; }
      .pgs-welcome-logo{ width: 96px; height: 96px; }
    }
    .pgs-welcome h4{
      margin:0;
      font-weight: 1000;
      letter-spacing: -.02em;
      color: var(--pgs-black);
    }
    .pgs-welcome p{
      margin: 6px 0 0 0;
      color: var(--pgs-muted);
      font-weight: 700;
    }
    .pgs-support{
      border-radius: 999px !important;
      padding: 10px 14px !important;
      font-weight: 1000 !important;
      border: 1px solid rgba(11,15,20,.18) !important;
      background: linear-gradient(180deg, rgba(17,24,39,1), rgba(11,15,20,1)) !important;
      box-shadow: 0 12px 20px rgba(2,6,23,.22);
      color:#fff !important;
      transition: transform .18s ease, box-shadow .18s ease;
    }
    .pgs-support:hover{
      transform: translateY(-1px);
      box-shadow: 0 18px 30px rgba(2,6,23,.26);
      color:#fff !important;
    }

    .pgs-grid .col-xl-3,
    .pgs-grid .col-xxl-6,
    .pgs-grid .col-lg-6,
    .pgs-grid .col-sm-6{ margin-bottom: 14px; }
  </style>
</head>

<body>
  <div id="preloader">
    <div class="sk-three-bounce">
      <div class="sk-child sk-bounce1"></div>
      <div class="sk-child sk-bounce2"></div>
      <div class="sk-child sk-bounce3"></div>
    </div>
  </div>

  <?php include './admin/include/menu_movil_vistas.php'; ?>
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
                <?php echo htmlspecialchars(SessionData::getUserFullName()); ?>
              </span>
              <span class="pgs-chip">
                <i class="fa fa-shield"></i>
                Role: <?php echo htmlspecialchars(SessionData::getUserType()); ?>
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
              <a href="javascript:void(0);" class="btn pgs-btn-outline">
                Help Center
              </a>
            </div>
          </div>
        </div>

        <!-- ✅ Premium Welcome Banner -->
        <div class="alert alert-light alert-dismissible fade show pgs-alert" role="alert">
          <div class="pgs-alert-line">
            <strong>Welcome,</strong> <?php echo htmlspecialchars(SessionData::getUserFullName()); ?>
            <span class="pgs-badge">
              <i class="fa fa-shield"></i> <?php echo htmlspecialchars(SessionData::getUserType()); ?>
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
                      Days off available for <?php echo htmlspecialchars($nombreRestUser); ?>
                    </h3>
                    <p class="pgs-rest-sub">
                      This information is linked because employee CC and user employee ID match.
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
                    Employee ID: <?php echo htmlspecialchars($employeeIdRest); ?>
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


        <!-- ✅ Units Grid -->
        <div class="row pgs-grid">
          <?php if ($isvalid): ?>
            <?php
              $userUnidad = SessionData::getUnidadUser();
              $userType   = SessionData::getUserType();
            ?>
            <?php foreach ($arrUnidades as $unidad): ?>
              <?php
                $mostrarUnidad = (
                  $userType == Util::SuperAdmin() ||
                  ($userType == Util::Manager() && $userUnidad == $unidad['id']) ||
                  ($userType == Util::Staff() && $userUnidad == $unidad['id'])
                );
              ?>
              <?php if ($mostrarUnidad): ?>
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
                  <div class="widget-stat card pgs-unit-card">
                    <div class="card-body">
                      <div class="pgs-unit-head">
                        <div class="pgs-unit-icon" aria-hidden="true">
                          <i class="ti-id-badge"></i>
                        </div>
                        <div style="min-width:0;">
                          <p class="pgs-unit-title mb-0"><?php echo htmlspecialchars($unidad['nombre']); ?></p>
                          <p class="pgs-unit-meta">Unit access enabled • Quick action</p>
                        </div>
                      </div>

                      <div class="pgs-unit-actions">
                        <span class="pgs-pill">ID: <?php echo (int)$unidad['id']; ?></span>
                        <a href="report.php" class="btn pgs-go" title="Go to reports">
                          GO
                        </a>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- ✅ License / Brand Card -->
        <div class="row">
          <div class="col-xl-12">
            <div class="welcome-card pgs-welcome mt-2 mb-4 position-relative">
              <div class="bootstrap-media">
                <div class="media">
                  <img
                    src="<?php echo SessionData::getLogoEmpresa(); ?>"
                    class="pgs-welcome-logo"
                    alt="Company logo"
                  />

                  <div class="media-body">
                    <h4>Administrative System</h4>
                    <p>Licensed to <?php echo htmlspecialchars(SessionData::getConfigSistema()['empresa']); ?></p>

                    <div class="mt-3 d-flex flex-wrap" style="gap:10px;">
                      <a class="btn pgs-support" href="javascript:void(0);">
                        Contact Support <i class="las la-long-arrow-alt-right ml-2"></i>
                      </a>
                      <a class="btn pgs-btn-outline" href="javascript:void(0);">
                        System Status
                      </a>
                      <!-- <a class="btn-link text-dark ml-3" href="./configuracion.php">Settings</a> -->
                    </div>
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
  </div>
</body>

</html>