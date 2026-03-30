<style>
  :root{
    --m-red:#e11d2e;
    --m-red2:#ff2a3d;
    --m-bg:#07090d;
    --m-panel:#0b0f16;
    --m-card:rgba(255,255,255,.06);
    --m-line:rgba(255,255,255,.10);
    --m-text:#f8fafc;
    --m-muted:rgba(248,250,252,.72);
    --m-shadow: 0 24px 70px rgba(0,0,0,.55);
    --r14:14px; --r16:16px; --r20:20px;
  }

  /* ==== TOP BAR (mobile/tablet) ==== */
  .menu-movil-container{
    position: sticky;
    top: 0;
    z-index: 3000;
    background: radial-gradient(1200px 600px at 0% 0%, rgba(225,29,46,.20) 0%, rgba(0,0,0,.78) 55%, rgba(0,0,0,.90) 100%);
    backdrop-filter: blur(14px);
    border-bottom: 1px solid var(--m-line);
  }

  .menu-movil-container > .menu-movil-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    padding: 10px 12px;
  }

  /* button hamburger */
  .menu-movil-btn{
    display:flex;
    align-items:center;
    gap:10px;
    background: rgba(255,255,255,.06);
    color: var(--m-text);
    border: 1px solid var(--m-line);
    border-radius: var(--r16);
    padding: 10px 12px;
    font-weight: 950;
    letter-spacing:.2px;
    line-height:1;
    box-shadow: 0 12px 28px rgba(0,0,0,.25);
  }

  .menu-movil-btn .menu-ico{
    width: 42px;
    height: 42px;
    display:grid;
    place-items:center;
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(225,29,46,.95), rgba(255,42,61,.70));
    box-shadow: 0 14px 30px rgba(225,29,46,.22);
    color:#fff;
    font-size: 20px;
  }

  .menu-movil-btn .menu-label{
    display:flex;
    flex-direction:column;
    gap:2px;
    text-align:left;
  }
  .menu-movil-btn .menu-label b{ font-size: 13px; color:var(--m-text); }
  .menu-movil-btn .menu-label span{ font-size: 11px; color: var(--m-muted); font-weight:900; }

  /* old close inside button OFF */
  .cerrar-menu-movil{ display:none !important; }

  /* emergency */
  .call-movil{
    display:inline-flex !important;
    align-items:center;
    gap:10px;
    border-radius: var(--r16);
    border:1px solid var(--m-line);
    background: rgba(255,255,255,.06);
    color: var(--m-text) !important;
    padding: 10px 12px;
    font-weight: 950;
    text-decoration:none !important;
    white-space:nowrap;
  }
  .call-movil i{ color: var(--m-red); }

  /* logo */
  .brand-logo.logo-tamaño{
    display:flex;
    align-items:center;
    justify-content:center;
    margin:0;
    padding:0;
  }
  .brand-logo.logo-tamaño img{
    height: 38px;
    width:auto;
    display:block;
    filter: drop-shadow(0 10px 20px rgba(0,0,0,.35));
  }

  /* ==== OVERLAY ==== */
  #overlay{
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.58);
    backdrop-filter: blur(8px);
    display:none;
    z-index: 2999;
  }

  /* ==== DRAWER (menu) ==== */
  .menu-movil{
    position: fixed;
    top: 0;
    left: -460px;
    height: 100vh;
    width: min(460px, 92vw);
    z-index: 3001;
    background: radial-gradient(1200px 800px at 0% 0%, rgba(225,29,46,.20) 0%, rgba(0,0,0,.82) 55%, rgba(0,0,0,.92) 100%);
    border-right: 1px solid var(--m-line);
    box-shadow: var(--m-shadow);
    transition: left .22s ease;
    padding: 14px;
    overflow:auto;
  }

  /* drawer header */
  .menu-movil .drawer-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(255,255,255,.10);
  }

  .drawer-title{
    display:flex;
    align-items:center;
    gap:10px;
    color: var(--m-text);
  }
  .drawer-title .chip{
    width:44px;height:44px;border-radius:14px;
    display:grid;place-items:center;
    background: rgba(255,255,255,.06);
    border:1px solid rgba(255,255,255,.10);
  }
  .drawer-title b{ font-size: 14px; }
  .drawer-title span{ display:block; margin-top:2px; font-size: 11px; color: var(--m-muted); font-weight:900; }

  .drawer-close{
    width:44px;height:44px;border-radius:14px;
    border:1px solid rgba(255,255,255,.12);
    background: rgba(255,255,255,.06);
    color:#fff;
    display:grid;place-items:center;
    cursor:pointer;
    font-size: 20px;
    line-height: 1;
  }

  /* ==== GRID ==== */
  .menu-grid{
    list-style:none;
    padding:0;
    margin:0;
    display:grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }

  /* tablet: 3 columns */
  @media (min-width: 768px){
    .menu-grid{ grid-template-columns: repeat(3, minmax(0, 1fr)); }
  }

  .menu-item a{
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    gap: 10px;
    padding: 16px 12px;
    border-radius: 18px;
    text-decoration:none !important;
    color: var(--m-text) !important;
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.10);
    box-shadow: 0 18px 45px rgba(0,0,0,.35);
    transition: transform .12s ease, border-color .12s ease, background .12s ease;
    min-height: 112px;
  }
  .menu-item a:hover{
    transform: translateY(-1px);
    border-color: rgba(225,29,46,.35);
    background: rgba(225,29,46,.08);
  }

  .icon-box{
    width: 56px;
    height: 56px;
    border-radius: 18px;
    display:grid;
    place-items:center;
    background: linear-gradient(135deg, rgba(225,29,46,.92), rgba(255,42,61,.60));
    box-shadow: 0 14px 30px rgba(225,29,46,.22);
    border: 1px solid rgba(255,255,255,.14);
  }
  .icon-box i{
    font-size: 22px;
    color:#fff;
    line-height: 1;
  }

  .menu-label-movil{
    font-weight: 950;
    letter-spacing: .7px;
    font-size: 12px;
    text-transform: uppercase;
    color: rgba(0, 0, 0, 0.92);
    text-align:center;
  }
  .menu-item small{
    color: var(--m-muted);
    font-weight: 900;
    font-size: 11px;
    margin-top: -6px;
    text-align:center;
  }

  /* ==== LOGOUT ==== */
  .drawer-footer{
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid rgba(255,255,255,.10);
  }
  .logout-btn{
    width:100%;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:10px;
    padding: 12px 14px;
    border-radius: 16px;
    background: linear-gradient(135deg, var(--m-red), var(--m-red2));
    border: none;
    color:#fff !important;
    font-weight: 950;
    text-decoration:none !important;
  }

  /* ==== MODAL centered ==== */
  .modal-mobile{
    position: fixed;
    inset: 0;
    display:none;
    z-index: 4000;
    background: rgba(0,0,0,.60);
    backdrop-filter: blur(10px);
    padding: 18px;
  }
  .modal-mobile.is-open{
    display:flex;
    align-items:center;
    justify-content:center;
  }
  .modal-content-mobile{
    width: min(560px, 100%);
    background: radial-gradient(900px 600px at 0% 0%, rgba(225,29,46,.18) 0%, rgba(10,14,20,.92) 55%, rgba(5,7,10,.96) 100%);
    border: 1px solid rgba(255,255,255,.10);
    border-radius: 18px;
    box-shadow: var(--m-shadow);
    overflow:hidden;
  }

  .modal-head{
    padding: 16px;
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap: 10px;
    border-bottom: 1px solid rgba(255,255,255,.10);
  }
  .modal-head .kicker{
    color: rgba(248,250,252,.65);
    font-weight: 950;
    font-size: 11px;
    letter-spacing: 1px;
    text-transform: uppercase;
  }
  .titulo-modal-movil{
    margin: 6px 0 0 0;
    color: #fff;
    font-weight: 950;
    letter-spacing: -.3px;
    font-size: 22px;
  }

  .close-modal-mobile{
    width:44px;height:44px;border-radius:14px;
    border:1px solid rgba(255,255,255,.12);
    background: rgba(255,255,255,.06);
    color:#fff;
    display:grid;place-items:center;
    cursor:pointer;
    font-size: 20px;
    line-height: 1;
  }

  .modal-body{
    padding: 14px 16px 16px;
  }

  .modal-list{
    list-style:none;
    padding:0;
    margin:0;
    display:flex;
    flex-direction:column;
    gap: 10px;
  }
  .modal-list a{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap: 10px;
    padding: 12px 14px;
    border-radius: 16px;
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.10);
    color: #fff !important;
    text-decoration:none !important;
    font-weight: 950;
  }
  .modal-list a:hover{
    border-color: rgba(225,29,46,.35);
    background: rgba(225,29,46,.08);
  }
  .modal-list a .chev{ opacity:.85; }

  .no-scroll{ overflow:hidden !important; }
</style>

<div class="menu-movil-container">
  <div class="menu-movil-top">

    <button type="button" class="menu-movil-btn" onclick="abrirMenu()">
      <span class="menu-ico">☰</span>
      <span class="menu-label">
        <b>Quick Access</b>
        <span>Navigation & modules</span>
      </span>
    </button>

    <a href="tel:" class="call-movil" target="_blank">
      <i class="fa fa-phone"></i>
      <span>EMERGENCY CALL</span>
    </a>

    <a href="#" class="brand-logo logo-tamaño">
      <img src="assets/img/logo1.png" class="logo-abbr" alt="">
    </a>

  </div>

  <nav class="menu-movil" id="menuMovil" aria-hidden="true">
    <div class="drawer-head">
      <div class="drawer-title">
        <div class="chip"><i class="fa fa-th-large" style="color:#fff"></i></div>
        <div>
          <b>Quick Access</b>
          <span>Navigation & modules</span>
        </div>
      </div>
      <button class="drawer-close" type="button" onclick="cerrarMenu()" aria-label="Close">✕</button>
    </div>

    <ul class="menu-grid">
      <?php
        $userUnidad = SessionData::getUnidadUser();
        $userType = SessionData::getUserType();
        $esSuperAdmin = ($userType == Util::SuperAdmin());
        $esManagerOStaff = ($userType == Util::Manager() || $userType == Util::Staff());
        $esManager =  $userType == Util::Staff();
      ?>

      <li class="menu-item">
        <a href="./main.php">
          <div class="icon-box"><i class="fa fa-home"></i></div>
          <div class="menu-label-movil">Home</div>
          <small>Dashboard</small>
        </a>
      </li>

      <li class="menu-item">
        <a href="javascript:void(0)" onclick="abrirModal('REPORTS')">
          <div class="icon-box"><i class="fa fa-file-text-o"></i></div>
          <div class="menu-label-movil">Reports</div>
          <small>Checklist & jobs</small>
        </a>
      </li>

      <?php if (SessionData::getPermission(12)): ?>
      <li class="menu-item">
        <a href="places_customers.php">
          <div class="icon-box"><i class="fa fa-address-book-o"></i></div>
          <div class="menu-label-movil">Customers</div>
          <small>HOA</small>
        </a>
      </li>
      <?php endif; ?>

      <?php if (SessionData::getPermission(27)): ?>
      <li class="menu-item">
        <a href="javascript:void(0)" onclick="abrirModal('EMPLOYEES')">
          <div class="icon-box"><i class="fa fa-address-card-o"></i></div>
          <div class="menu-label-movil">Employees</div>
          <small>Time & uniforms</small>
        </a>
      </li>

      <li class="menu-item">
        <a href="calendar.php">
          <div class="icon-box"><i class="fa fa-calendar"></i></div>
          <div class="menu-label-movil">Calendar</div>
          <small>Schedule</small>
        </a>
      </li>
      <?php endif; ?>
    </ul>

    <div class="drawer-footer">
      <a class="logout-btn" href="logout.php">
        <i class="fa fa-power-off"></i> Logout
      </a>
    </div>
  </nav>

  <div id="overlay" onclick="cerrarMenu()"></div>
</div>

<!-- Modal -->
<div id="myModal-movil" class="modal-mobile" aria-hidden="true">
  <div class="modal-content-mobile" role="dialog" aria-modal="true">
    <div class="modal-head">
      <div>
        <div class="kicker">Module</div>
        <h2 class="titulo-modal-movil" id="modalTitle">Module</h2>
      </div>
      <button class="close-modal-mobile" type="button" aria-label="Close">✕</button>
    </div>
    <div class="modal-body" id="modalContent"></div>
  </div>
</div>

<script>
  const menuMovil = document.getElementById("menuMovil");
  const overlay = document.getElementById("overlay");
  const modal = document.getElementById("myModal-movil");
  const modalCloseBtn = document.querySelector(".close-modal-mobile");

  function lockScroll(lock){
    document.documentElement.classList.toggle("no-scroll", !!lock);
    document.body.classList.toggle("no-scroll", !!lock);
  }

  function abrirMenu() {
    menuMovil.style.left = "0";
    menuMovil.setAttribute("aria-hidden", "false");
    overlay.style.display = "block";
    lockScroll(true);
  }

  function cerrarMenu() {
    menuMovil.style.left = "-460px";
    menuMovil.setAttribute("aria-hidden", "true");
    overlay.style.display = "none";
    lockScroll(false);
  }

  function abrirModal(titulo) {
    document.getElementById("modalTitle").innerText = titulo;

    if (titulo === "EMPLOYEES") {
      document.getElementById("modalContent").innerHTML = `
        <ul class="modal-list">
          <li><a href="./empleados.php">View Employees <span class="chev">›</span></a></li>
          <li><a href="./reloj.php">Record time <span class="chev">›</span></a></li>
          <li><a href="./informe_salidas.php">Entry - Exit <span class="chev">›</span></a></li>
          <li><a href="./uniformes.php">Uniforms <span class="chev">›</span></a></li>
        </ul>
      `;
    } else if (titulo === "REPORTS") {
      document.getElementById("modalContent").innerHTML = `
        <ul class="modal-list">
          <?php if (($esSuperAdmin || $esManager) && SessionData::getPermission(7)): ?>
            <li><a href="./report.php">Enter Report <span class="chev">›</span></a></li>
          <?php endif; ?>

          <?php if (($esSuperAdmin || $esManager) && SessionData::getPermission(9)): ?>
            <li><a href="./report-list.php">Edit Report <span class="chev">›</span></a></li>
          <?php endif; ?>

          <?php if (($esSuperAdmin || $esManager) && SessionData::getPermission(21)): ?>
            <li><a href="./check_list.php">Check List Report <span class="chev">›</span></a></li>
          <?php endif; ?>

          <?php if (($esSuperAdmin || $esManagerOStaff) && SessionData::getPermission(22)): ?>
            <li><a href="./check_report_list.php">Show Check List Report <span class="chev">›</span></a></li>
            <li><a href="./calendar.php">Calendar <span class="chev">›</span></a></li>
            <li><a href="./report-list-group.php">Report List Group Download <span class="chev">›</span></a></li>
          <?php endif; ?>
        </ul>
      `;
    } else {
      document.getElementById("modalContent").innerHTML = `<div style="color:#fff;font-weight:950">No options</div>`;
    }

    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");
    cerrarMenu();
    lockScroll(true);
  }

  function cerrarModal(){
    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");
    lockScroll(false);
  }

  modalCloseBtn.addEventListener("click", cerrarModal);

  modal.addEventListener("click", function(e){
    if(e.target === modal) cerrarModal();
  });

  document.addEventListener("keydown", function(e){
    if(e.key === "Escape"){
      if(modal.classList.contains("is-open")) cerrarModal();
      else cerrarMenu();
    }
  });
</script>menu-label-movil