<style>
    /* =========================
   SAAS HEADER (RED / BLACK)
   ========================= */
:root{
  --rb-bg: #0b0b0d;         /* negro base */
  --rb-bg2:#0f1014;         /* negro suave */
  --rb-line:#1d1f27;        /* borde sutil */
  --rb-red:#e11d2e;         /* rojo principal */
  --rb-red2:#ff2a3d;        /* rojo glow */
  --rb-txt:#e9e9ee;         /* texto */
  --rb-mut:#a9adb9;         /* texto muted */
  --rb-glow: 0 10px 30px rgba(225,29,46,.18);
}

/* NAV HEADER (lado izquierdo) */
.nav-header--saas{
  background: linear-gradient(180deg, rgba(15,16,20,.92), rgba(11,11,13,.92));
  border-right: 1px solid rgba(225,29,46,.15);
  position: relative;
}
.nav-header--saas::after{
  content:"";
  position:absolute; inset:0;
  background:
    radial-gradient(600px 140px at 10% 0%, rgba(225,29,46,.18), transparent 55%),
    radial-gradient(400px 140px at 85% 20%, rgba(255,42,61,.10), transparent 60%);
  pointer-events:none;
}

/* Brand */
.brand-logo--saas{
  display:flex;
  align-items:center;
  gap:12px;
  padding: 14px 14px;
}
.brand-logo--saas .brand-mark{
  width:44px; height:44px;
  display:grid; place-items:center;
  border-radius: 14px;
  background:  #fff !important;
  border: 1px solid rgba(225,29,46,.25);
  box-shadow: var(--rb-glow);
}
.brand-logo--saas .logo-abbr{
  max-width: 26px;
  height:auto;
  filter: drop-shadow(0 8px 16px rgba(225,29,46,.25));
}
.brand-logo--saas .brand-title{
  max-height: 22px;
  height:auto;
  opacity: .95;
  background:  color: #fff !important;
}

/* Hamburger */
.nav-control--saas .hamburger--saas .line{
  height:2px;
  border-radius: 12px;
  background: linear-gradient(90deg, rgba(233,233,238,.95), rgba(225,29,46,.9));
  box-shadow: 0 8px 18px rgba(225,29,46,.15);
  color: #fff !important;
}
.nav-control--saas .hamburger--saas:hover .line{
  background: linear-gradient(90deg, rgba(255,255,255,1), rgba(255,42,61,1));
}

/* HEADER TOP (barra superior) */
.header--saas{
  background: rgba(11,11,13,.72);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  border-bottom: 1px solid rgba(225,29,46,.18);
}
.header-content--saas{
  padding: 10px 14px;
}
.navbar--saas{
  min-height: 58px;
}

/* Title / Module */
.dashboard_bar--saas{
  display:flex;
  align-items:center;
  gap:10px;
 color: #fff !important;
  font-weight: 700;
  letter-spacing: .2px;
  padding: 10px 14px;
  border-radius: 14px;
  background: linear-gradient(180deg, rgba(15,16,20,.62), rgba(11,11,13,.42));
  border: 1px solid rgba(225,29,46,.16);
  box-shadow: 0 14px 40px rgba(0,0,0,.25);
}
.dashboard_bar--saas .modulo-dot{
  width:10px; height:10px;
  border-radius: 50%;
  background: radial-gradient(circle at 30% 30%, var(--rb-red2), var(--rb-red));
  box-shadow: 0 0 0 5px rgba(225,29,46,.12);
  color: #fff !important;
}

/* Right side */
.header-right--saas{
  gap:10px;
  align-items:center;
}

/* Buttons look premium */
.event-btn--saas{
  background: transparent !important;
  border: 0 !important;
  padding: 0 !important;
  box-shadow: none !important;
}
.chip{
  display:inline-flex;
  align-items:center;
  gap:10px;
  padding: 10px 12px;
  border-radius: 999px;
  border: 1px solid rgba(225,29,46,.18);
  background: linear-gradient(180deg, rgba(15,16,20,.70), rgba(11,11,13,.55));
  color: var(--rb-txt);
  box-shadow: 0 14px 40px rgba(0,0,0,.22);
}
.chip-k{
  font-size: 11px;
  letter-spacing: .9px;
  color: var(--rb-mut);
  text-transform: uppercase;
}
.chip-v{
  font-size: 13px;
  font-weight: 700;
  color: var(--rb-txt);
  max-width: 220px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Emergency button */
.event-btn--danger{
  padding: 10px 14px !important;
  border-radius: 999px !important;
  border: 1px solid rgba(255,42,61,.35) !important;
  background: linear-gradient(180deg, rgba(225,29,46,.95), rgba(160,10,22,.95)) !important;
  color: #fff !important;
  box-shadow: 0 18px 50px rgba(225,29,46,.25);
}
.event-btn--danger i{ opacity:.95; }
.event-btn--danger:hover{
  transform: translateY(-1px);
  filter: brightness(1.05);
}

/* Avatar */
.header-profile--saas .avatar-wrap{
  width: 38px;
  height: 38px;
  border-radius: 14px;
  display:grid;
  place-items:center;
  background: linear-gradient(180deg, rgba(15,16,20,.70), rgba(11,11,13,.55));
  border: 1px solid rgba(225,29,46,.20);
  box-shadow: 0 14px 40px rgba(0,0,0,.18);
  overflow:hidden;
}
.header-profile--saas img{
  width: 28px !important;
  height: 28px !important;
  border-radius: 10px;
  object-fit: cover;
}

/* Logout */
.logout--saas{
  width: 38px;
  height: 38px;
  border-radius: 14px;
  display:grid !important;
  place-items:center;
  background: linear-gradient(180deg, rgba(15,16,20,.70), rgba(11,11,13,.55));
  border: 1px solid rgba(225,29,46,.20);
  color: var(--rb-txt) !important;
  transition: .2s ease;
}
.logout--saas:hover{
  border-color: rgba(255,42,61,.45);
  box-shadow: var(--rb-glow);
  transform: translateY(-1px);
}

/* Responsive: compact */
@media (max-width: 991.98px){
  .header-content--saas{ padding: 8px 10px; }
  .dashboard_bar--saas{ padding: 9px 12px; }
  .chip{ padding: 9px 10px; }
  .chip-v{ max-width: 140px; }
}
@media (max-width: 575.98px){
  .dashboard_bar--saas{
    max-width: 60vw;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
}
</style>
<style>
    /* =========================
   FIX LOGOS (PC vs MOBILE)
   ========================= */

/* Default (PC): solo logo grande */
.brand-logo--saas .logo-abbr{ 
  display:none !important; 
}
.brand-logo--saas .brand-title{ 
  display:block !important; 
}

/* Mobile/Tablet: solo logo pequeño */
@media (max-width: 991.98px){
  .brand-logo--saas .brand-title{ 
    display:none !important; 
  }
  .brand-logo--saas .logo-abbr{ 
    display:block !important; 
  }

  /* Ajuste visual para que no quede enorme el cuadro */
  .brand-logo--saas .brand-mark{
    width:40px; height:40px;
  }
  .brand-logo--saas .logo-abbr{
    max-width:24px;
  }
}
/* =========================================
   FIX DEFINITIVO LOGOS (PC vs MOBILE)
   - PC: solo logo grande (brand-title)
   - Mobile: solo logo pequeño (logo-abbr)
   ========================================= */

/* Reset de imgs para que SIEMPRE se vean */
.brand-logo--saas img{
  display: block !important;
  opacity: 1 !important;
  visibility: visible !important;
  filter: none !important;
}

/* PC (default): ocultar el cuadrito del abbr y mostrar solo el logo grande */
.brand-logo--saas .brand-mark{
  display: none !important;
}
.brand-logo--saas .brand-word{
  display: flex !important;
  align-items: center;
}
.brand-logo--saas .brand-title{
  max-height: 60px;
  height: auto;
  width: auto;
  opacity: 1 !important;
  /* si tu logo es oscuro y se pierde en fondo negro, activa ESTA línea: */
  /* filter: drop-shadow(0 10px 18px rgba(225,29,46,.20)) brightness(1.15) !important; */
}

/* Mobile/Tablet: mostrar solo logo pequeño */
@media (max-width: 991.98px){
  .brand-logo--saas .brand-word{
    display: none !important;
  }
  .brand-logo--saas .brand-mark{
    display: grid !important;
    place-items: center;
    width: 44px;
    height: 44px;
    border-radius: 14px;

    /* Evitar el “cuadro blanco” */
    background: rgba(255,255,255,.06) !important;
    border: 1px solid rgba(225,29,46,.28) !important;
    box-shadow: 0 12px 30px rgba(0,0,0,.25);
  }

  .brand-logo--saas .logo-abbr{
    max-width: 26px !important;
    height: auto !important;
    opacity: 1 !important;

    /* si tu logo pequeño es oscuro y se pierde, activa ESTA línea: */
    /* filter: drop-shadow(0 10px 16px rgba(225,29,46,.20)) brightness(1.2) !important; */
  }
}
</style>
<div class="nav-header nav-header--saas">
    <a href="#" class="brand-logo brand-logo--saas">
        <span class="brand-mark">
            <img src="assets/img/logo1.png" class="logo-abbr" alt="">
        </span>
        <span class="brand-word">
            <img src="assets/img/logo1a.png" class="brand-title" alt="">
        </span>
    </a>

    <div class="nav-control nav-control--saas">
        <div class="hamburger hamburger--saas">
            <span class="line"></span><span class="line"></span><span class="line"></span>
        </div>
    </div>
</div>

<div class="header header--saas">
    <div class="header-content header-content--saas">
        <nav class="navbar navbar-expand navbar--saas">
            <div class="collapse navbar-collapse justify-content-between">

                <div class="header-left header-left--saas">
                    <div class="dashboard_bar dashboard_bar--saas">
                        <span class="modulo-dot"></span>
                        <?php echo $modulo; ?>
                    </div>
                </div>

                <ul class="navbar-nav header-right header-right--saas">

                    <li class="dropdown schedule-event-inner">
                        <a href="#" class="btn btn-primary btn-rounded event-btn event-btn--saas">
                            <span class="chip chip--dark">
                                <span class="chip-k">HOA</span>
                                <span class="chip-v d-none d-lg-inline-block"><?php ECHO  $_SESSION['session_user']['unidad']; ?></span>
                            </span>
                        </a>
                    </li>

                    <li class="dropdown schedule-event-inner">
                        <a href="#" class="btn btn-primary btn-rounded event-btn event-btn--saas">
                            <span class="chip chip--dark">
                                <span class="chip-k">ROLE</span>
                                <span class="chip-v d-none d-lg-inline-block"><?php echo SessionData::getUserType(); ?></span>
                            </span>
                        </a>
                    </li>

                    <li class="dropdown schedule-event-inner">
                        <a href="tel:" class="btn btn-warning btn-rounded event-btn event-btn--saas event-btn--danger" target="_blank">
                            <i class="fa fa-phone"></i>
                            <span class="d-none d-xl-inline-block">EMERGENCY</span>
                            <span class="d-none d-lg-inline-block">CALL</span>
                            <i class="fa fa-caret-right scale3 ml-2 d-none d-sm-inline-block"></i>
                        </a>
                    </li>

                    <li class="nav-item dropdown header-profile header-profile--saas">
                        <a class="nav-link nav-link--saas" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <span class="avatar-wrap">
                                <img src="<?php echo SessionData::getAvatar(); ?>" width="20" alt="" />
                            </span>
                        </a>
                    </li>

                    <li class="nav-item notification_dropdown">
                        <a class="nav-link bell bell-link primary nav-link--saas logout--saas" href="logout.php" title="Logout">
                            <i class="fa fa-power-off"></i>
                        </a>
                    </li>

                </ul>
            </div>
        </nav>
    </div>
</div>