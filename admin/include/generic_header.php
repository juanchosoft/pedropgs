<?php
// Header PGS Centrum. Mantiene la estructura que utiliza el tema para el menú.
$pgsHeaderTitle = isset($modulo) ? (string) $modulo : 'PGS Centrum';
$pgsHeaderUnit = isset($_SESSION['session_user']['unidad']) ? (string) $_SESSION['session_user']['unidad'] : '';
$pgsHeaderRole = (string) SessionData::getUserType();
$pgsHeaderAvatar = (string) SessionData::getAvatar();
$pgsEsc = static function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };
?>
<style>
/* PGS Header 2026: estilos aislados para no interferir con MetisMenu y la plantilla */
:root { --pgsh-red:#e11d2e; --pgsh-dark:#17151b; }
/* No usar position:relative sobre .nav-header: el tema lo posiciona independientemente. */
.nav-header.nav-header--saas {
  background:linear-gradient(150deg,#26121a 0%,#17171b 70%,#111318 100%) !important;
  border-right:1px solid rgba(255,255,255,.08);
  z-index:9100;
}
.nav-header--saas .brand-logo--saas {
  display:flex;align-items:center;justify-content:center;
  padding:10px 12px;min-height:72px;text-decoration:none;
}
.brand-logo--saas .brand-word { display:flex;align-items:center;justify-content:center; }
.brand-logo--saas .brand-title { display:block;max-width:188px;max-height:62px;width:auto;height:auto;object-fit:contain; }
.brand-logo--saas .brand-mark {display:none;}
.brand-logo--saas img {opacity:1;visibility:visible;}
.header.header--saas {
  background:linear-gradient(100deg,#242328,#15171c) !important;
  border-bottom:1px solid rgba(225,29,46,.22);
  color:#fff;
  z-index:9000;
}
.header--saas .header-content--saas { padding:8px 16px !important; }
.header--saas .navbar--saas { min-height:56px;padding:0; }
.header--saas .navbar-collapse {align-items:center;gap:14px;min-width:0;}
.header--saas .header-left--saas {flex:1 1 auto;min-width:0;}
.header--saas .dashboard_bar--saas {
  display:inline-flex;align-items:center;gap:10px;min-width:0;max-width:100%;
  padding:10px 14px;border:1px solid rgba(225,29,46,.20);border-radius:12px;
  background:rgba(0,0,0,.20);color:#fff !important;
  font-size:clamp(14px,1.2vw,21px);font-weight:750;line-height:1.25;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.header--saas .modulo-dot {width:9px;height:9px;flex:0 0 9px;border-radius:50%;background:#f02b43;box-shadow:0 0 0 4px rgba(240,43,67,.13);}
.header--saas .header-right--saas {display:flex !important;align-items:center;justify-content:flex-end;gap:8px;margin:0;flex:0 0 auto;min-width:0;}
.header--saas .header-right--saas > li {margin:0 !important;display:flex;align-items:center;}
.header--saas .event-btn--saas {background:transparent !important;border:0 !important;padding:0 !important;box-shadow:none !important;text-decoration:none;}
.header--saas .pgsh-chip {
  display:flex;align-items:center;gap:8px;min-height:40px;
  padding:8px 11px;border:1px solid rgba(255,255,255,.12);border-radius:100px;
  background:rgba(0,0,0,.23);color:#f7f7fa;
}
.header--saas .pgsh-chip-label {font-size:10px;font-weight:800;letter-spacing:.05em;color:#b9b9c3;}
.header--saas .pgsh-chip-value {max-width:230px;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;font-weight:750;}
.header--saas .event-btn--danger {
  display:inline-flex;align-items:center;justify-content:center;gap:7px;
  min-height:40px;padding:9px 12px !important;border-radius:100px !important;
  border:1px solid rgba(255,80,99,.34) !important;
  background:linear-gradient(125deg,#e11d2e,#b50d25) !important;
  color:#fff !important;box-shadow:none !important;text-decoration:none;
  font-size:12px;font-weight:800;white-space:nowrap;
}
.header--saas .pgsh-iconlink {display:grid !important;place-items:center;width:40px;height:40px;min-width:40px;border:1px solid rgba(255,255,255,.14);border-radius:12px;background:rgba(255,255,255,.07);color:#fff !important;text-decoration:none;}
.header--saas .pgsh-iconlink:hover {background:rgba(255,255,255,.15);}
.header--saas .avatar-wrap {display:grid;place-items:center;width:34px;height:34px;border-radius:10px;overflow:hidden;}
.header--saas .avatar-wrap img {width:30px !important;height:30px !important;object-fit:cover;border-radius:9px;}
/* El control hamburguesa permanece en generic_header para que el tema lo reconozca. */
.nav-control--saas .hamburger--saas .line {background:#fff !important;border-radius:4px;}
@media(max-width:1199.98px){
 .header--saas .pgsh-optional {display:none !important;}
 .header--saas .header-content--saas {padding:7px 10px !important;}
}
@media(max-width:991.98px){
 .brand-logo--saas .brand-word{display:none !important;}
 .brand-logo--saas .brand-mark{display:grid !important;place-items:center;width:44px;height:44px;border-radius:14px;background:rgba(255,255,255,.06);border:1px solid rgba(225,29,46,.30);}
 .brand-logo--saas .logo-abbr{display:block !important;max-width:28px;max-height:32px;width:auto;height:auto;object-fit:contain;}
 .nav-header--saas .brand-logo--saas{padding:7px;min-height:64px;}
 .header--saas .header-left--saas{display:none !important;}
 .header--saas .navbar-collapse{justify-content:flex-end !important;}
 .header--saas .event-btn--danger {width:40px;min-width:40px;padding:8px !important;}
 .header--saas .pgsh-emergency-text{display:none !important;}
 .header--saas .header-right--saas{gap:7px;}
}
@media(max-width:575.98px){
 .header--saas .pgsh-avatar-item {display:none !important;}
 .header--saas .header-content--saas {padding:7px 10px !important;}
 .header--saas .navbar--saas {min-height:48px;}
 .header--saas .pgsh-iconlink,.header--saas .event-btn--danger{width:40px;height:40px;min-height:40px;}
}
</style>
<div class="nav-header nav-header--saas">
  <a href="main.php" class="brand-logo brand-logo--saas" title="PGS Centrum - Home" aria-label="PGS Centrum home">
    <span class="brand-mark"><img src="assets/img/logo1.png" class="logo-abbr" alt="PGS Centrum"></span>
    <span class="brand-word"><img src="assets/img/logo1a.png" class="brand-title" alt="PGS Centrum"></span>
  </a>
  <div class="nav-control nav-control--saas" aria-label="Toggle navigation menu">
    <div class="hamburger hamburger--saas"><span class="line"></span><span class="line"></span><span class="line"></span></div>
  </div>
</div>
<div class="header header--saas">
  <div class="header-content header-content--saas">
    <nav class="navbar navbar-expand navbar--saas" aria-label="Top navigation">
      <div class="collapse navbar-collapse justify-content-between">
        <div class="header-left header-left--saas">
          <div class="dashboard_bar dashboard_bar--saas" title="<?php echo $pgsEsc($pgsHeaderTitle); ?>"><span class="modulo-dot" aria-hidden="true"></span><?php echo $pgsEsc($pgsHeaderTitle); ?></div>
        </div>
        <ul class="navbar-nav header-right header-right--saas">
          <li class="pgsh-optional"><div class="pgsh-chip" title="HOA: <?php echo $pgsEsc($pgsHeaderUnit); ?>"><span class="pgsh-chip-label">HOA</span><span class="pgsh-chip-value"><?php echo $pgsEsc($pgsHeaderUnit); ?></span></div></li>
          <li class="pgsh-optional"><div class="pgsh-chip" title="Role: <?php echo $pgsEsc($pgsHeaderRole); ?>"><span class="pgsh-chip-label">ROLE</span><span class="pgsh-chip-value"><?php echo $pgsEsc($pgsHeaderRole); ?></span></div></li>
          <li><a href="tel:" class="event-btn--danger" aria-label="Emergency call" title="Emergency call"><i class="fa fa-phone" aria-hidden="true"></i><span class="pgsh-emergency-text">EMERGENCY CALL</span></a></li>
          <li class="pgsh-avatar-item"><span class="pgsh-iconlink" title="Account avatar"><span class="avatar-wrap"><img src="<?php echo $pgsEsc($pgsHeaderAvatar); ?>" alt="User"></span></span></li>
          <li><a class="pgsh-iconlink" href="logout.php" aria-label="Logout" title="Logout"><i class="fa fa-power-off" aria-hidden="true"></i></a></li>
        </ul>
      </div>
    </nav>
  </div>
</div>
