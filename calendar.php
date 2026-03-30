<?php
require './admin/include/generic_classes.php';
include './admin/classes/Check.php';

// Permissions
$view   = SessionData::getPermission(21);
$create = SessionData::getPermission(17);
$edit   = SessionData::getPermission(20);
$delete = SessionData::getPermission(18);
$enable = SessionData::getPermission(19);

if (!$view) { require 'permiso_denegado.php'; }

$arr = Check::getAll(null);
$isvalid = $arr['output']['valid'];
$arr = $arr['output']['response'];

// ✅ IMPORTANT: generic_header.php uses $modulo
$modulo = 'Calendar';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include './admin/include/generic_head.php'; ?>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>

  <style>
    /* ==========================================================
      PGS – Calendar (SaaS Premium) | Red + Black
      UI ONLY — no backend changes
    ========================================================== */
    .pgs-cal{
      --pgs-red:#E11D2E;
      --pgs-red2:#B3121E;
      --pgs-black:#0B0F14;

      --bg:#f6f7fb;
      --card:#ffffff;
      --text:#0f172a;
      --muted:#64748b;

      --border: rgba(2,6,23,.10);
      --border2: rgba(2,6,23,.06);

      --shadow: 0 18px 60px rgba(2,6,23,.10);
      --shadow2: 0 10px 26px rgba(2,6,23,.08);

      --r14:14px;
      --r18:18px;
      --r24:24px;
    }
    .pgs-cal *{ box-sizing:border-box; }

    /* Page spacing */
    .pgs-cal .wrap{ padding: 8px 0 18px; }

    /* Hero */
    .pgs-cal .hero{
      border-radius: var(--r24);
      padding: 16px;
      background:
        radial-gradient(900px 260px at 10% 0%, rgba(225,29,46,.18), transparent 60%),
        radial-gradient(780px 240px at 95% 10%, rgba(11,15,20,.16), transparent 60%),
        linear-gradient(135deg, #ffffff 0%, #fbfdff 55%, #f7f9ff 100%);
      border: 1px solid var(--border);
      box-shadow: var(--shadow);
      overflow:hidden;
      margin-bottom: 14px;
      position: relative;
    }
    .pgs-cal .hero-top{
      display:flex;
      gap:12px;
      align-items:flex-start;
      justify-content:space-between;
      flex-wrap:wrap;
      position: relative;
      z-index: 1;
    }
    .pgs-cal .pill{
      display:inline-flex;
      align-items:center;
      gap:10px;
      padding: 10px 12px;
      border-radius: 999px;
      background: rgba(255,255,255,.9);
      border: 1px solid var(--border);
      font-weight: 950;
      box-shadow: var(--shadow2);
      color: var(--pgs-black);
    }
    .pgs-cal .pill .dot{
      width:10px; height:10px; border-radius:999px;
      background: linear-gradient(135deg, var(--pgs-red), var(--pgs-red2));
      box-shadow: 0 0 0 4px rgba(225,29,46,.14);
    }
    .pgs-cal .h-title{
      font-weight: 1000;
      letter-spacing: -.3px;
      font-size: 20px;
      line-height: 1.1;
      color: var(--pgs-black);
      margin: 0;
    }
    .pgs-cal .h-sub{
      margin-top: 6px;
      font-weight: 800;
      font-size: 12px;
      color: var(--muted);
    }

    /* Actions */
    .pgs-cal .actions{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      justify-content:flex-end;
      align-items:center;
    }
    .pgs-cal .btnx{
      border:1px solid rgba(2,6,23,.12);
      background:#fff;
      padding:10px 12px;
      border-radius: 14px;
      font-weight:1000;
      font-size:12px;
      cursor:pointer;
      transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease, filter .12s ease;
      box-shadow: 0 6px 14px rgba(2,6,23,.06);
      white-space: nowrap;
    }
    .pgs-cal .btnx:hover{
      transform: translateY(-1px);
      border-color: rgba(225,29,46,.30);
      box-shadow: 0 10px 18px rgba(2,6,23,.10);
    }
    .pgs-cal .btnx.primary{
      border:none;
      background: linear-gradient(135deg, var(--pgs-red), var(--pgs-red2));
      color:#fff;
      box-shadow: 0 14px 32px rgba(225,29,46,.22);
    }
    .pgs-cal .btnx.primary:hover{ box-shadow: 0 18px 38px rgba(225,29,46,.28); }

    /* Card */
    .pgs-cal .card-saas{
      border-radius: var(--r24);
      background: linear-gradient(180deg, #fff, #fbfcfe);
      border: 1px solid var(--border);
      box-shadow: var(--shadow);
      overflow:hidden;
    }
    .pgs-cal .card-head{
      padding: 14px 16px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:10px;
      flex-wrap:wrap;
      background: linear-gradient(180deg, #ffffff, #f8fafc);
      border-bottom: 1px solid var(--border);
    }
    .pgs-cal .card-head h5{
      margin:0;
      font-weight: 1000;
      letter-spacing:-.25px;
      color: var(--pgs-black);
    }
    .pgs-cal .badge{
      display:inline-flex;
      align-items:center;
      gap:8px;
      font-weight: 950;
      font-size: 12px;
      padding: 7px 10px;
      border-radius: 999px;
      background: rgba(225,29,46,.08);
      border:1px solid rgba(225,29,46,.18);
      color: var(--pgs-red2);
      white-space: nowrap;
    }
    .pgs-cal .body{ padding: 14px 16px; }

    /* Tabs (SuperAdmin) */
    .pgs-cal .tabs{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      margin-bottom: 12px;
    }
    .pgs-cal .tab{
      border:1px solid rgba(2,6,23,.12);
      background:#fff;
      padding:10px 12px;
      border-radius: 999px;
      font-weight: 1000;
      font-size: 12px;
      cursor:pointer;
      transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
      box-shadow: 0 6px 14px rgba(2,6,23,.06);
      display:inline-flex;
      align-items:center;
      gap:8px;
    }
    .pgs-cal .tab:hover{ transform: translateY(-1px); border-color: rgba(225,29,46,.30); box-shadow: 0 10px 18px rgba(2,6,23,.10); }
    .pgs-cal .tab.active{
      border:none;
      background: linear-gradient(135deg, var(--pgs-red), var(--pgs-red2));
      color:#fff;
      box-shadow: 0 14px 32px rgba(225,29,46,.22);
    }

    /* Calendar frame */
    .pgs-cal .frame-wrap{
      position: relative;
      border-radius: var(--r18);
      overflow:hidden;
      border: 1px solid rgba(2,6,23,.10);
      background:
        radial-gradient(900px 260px at 0% 0%, rgba(225,29,46,.06), transparent 60%),
        radial-gradient(900px 260px at 100% 0%, rgba(11,15,20,.06), transparent 60%),
        #fff;
      box-shadow: var(--shadow2);
    }
    .pgs-cal iframe{
      width: 100%;
      height: 680px;
      border: 0;
      display:block;
      background:#fff;
    }
    @media (max-width: 992px){
      .pgs-cal iframe{ height: 640px; }
    }
    @media (max-width: 576px){
      .pgs-cal iframe{ height: 620px; }
    }

    /* Loader overlay */
    .pgs-cal .loading{
      position:absolute;
      inset:0;
      display:flex;
      align-items:center;
      justify-content:center;
      background: rgba(255,255,255,.86);
      backdrop-filter: blur(6px);
      z-index: 5;
      gap: 10px;
      padding: 16px;
      text-align:center;
    }
    .pgs-cal .spinner{
      width: 18px;
      height: 18px;
      border-radius: 999px;
      border: 3px solid rgba(225,29,46,.18);
      border-top-color: rgba(225,29,46,.95);
      animation: spin .75s linear infinite;
    }
    @keyframes spin{ to{ transform: rotate(360deg); } }
    .pgs-cal .loading .txt{
      font-weight: 950;
      color: var(--pgs-black);
      font-size: 12px;
      line-height: 1.2;
    }

    /* Small helper */
    .pgs-cal .hint{
      margin-top: 12px;
      padding: 10px 12px;
      border-radius: 16px;
      background: rgba(11,15,20,.04);
      border: 1px solid rgba(2,6,23,.08);
      color: #334155;
      font-weight: 850;
      font-size: 12px;
    }
    .pgs-cal .hint b{ color: var(--pgs-black); }

    /* Breadcrumb tune (keeps existing HTML but improves visual) */
    .page-titles .breadcrumb .breadcrumb-item a{ font-weight: 900; }
  </style>
</head>
<?php date_default_timezone_set('America/Bogota'); ?>

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
        <div class="page-titles">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="javascript:void(0)">Home</a></li>
            <li class="breadcrumb-item active"><a href="javascript:void(0)"><?php echo htmlspecialchars($modulo); ?></a></li>
          </ol>
        </div>

        <div class="pgs-cal">
          <div class="wrap">

            <!-- HERO -->
            <div class="hero">
              <div class="hero-top">
                <div style="display:flex; gap:12px; align-items:flex-start; flex-wrap:wrap;">
                  <span class="pill"><span class="dot"></span> Calendar</span>
                  <div>
                    <h3 class="h-title">Team Schedule</h3>
                    <div class="h-sub">
                      View events, tasks, and operational timelines in one place.
                    </div>
                  </div>
                </div>

                <div class="actions">
                  <button type="button" class="btnx" id="btnToday">Go to Today</button>
                  <button type="button" class="btnx primary" id="btnReload">Reload</button>
                </div>
              </div>
            </div>

            <!-- CARD -->
            <div class="card-saas">
              <div class="card-head">
                <h5>Calendar View</h5>
                <span class="badge" id="calBadge">Loading…</span>
              </div>

              <div class="body">
                <?php
                  $unidad_id = isset($_SESSION['session_user']['tbl_unidad_id']) ? $_SESSION['session_user']['tbl_unidad_id'] : null;
                  $tipo = isset($_SESSION['session_user']['tipo']) ? $_SESSION['session_user']['tipo'] : null;

                  // Calendars
                  $calendars = [
                    'unidad_8' => 'https://calendar.google.com/calendar/embed?height=600&wkst=1&ctz=America%2FNew_York&src=Y18zYzA4YTczNDRmNmNjNmIwNDJiZWNhM2Y1YjZhZGFhYzU5MDlmZDk4MjM2YjU4MDg3Mzg4NDdkY2NkMGNlYzZlQGdyb3VwLmNhbGVuZGFyLmdvb2dsZS5jb20&src=ZXMudXNhI2hvbGlkYXlAZ3JvdXAudi5jYWxlbmRhci5nb29nbGUuY29t&src=cGF1bGFjZGQyNEBnbWFpbC5jb20&color=%234285F4&color=%23DB4437&color=%23F4B400',
                    'unidad_7' => 'https://calendar.google.com/calendar/embed?height=600&wkst=1&ctz=America%2FNew_York&src=c_916634f2cd23eafbeb6c91a0d0765fec996396bdaaf9e72c7ad62eee48ccb66e%40group.calendar.google.com&ctz=America%2FNew_York'
                  ];

                  function renderCalendarFrame($src, $id, $label){
                    echo '<div class="frame-wrap cal-frame" data-label="'.htmlspecialchars($label).'" style="display:none;">';
                    echo '  <div class="loading"><div class="spinner"></div><div class="txt">Loading calendar…<br><span style="opacity:.75">'.htmlspecialchars($label).'</span></div></div>';
                    echo '  <iframe id="'.htmlspecialchars($id).'" src="'.htmlspecialchars($src).'" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>';
                    echo '</div>';
                  }

                  // Decide what to show (same logic, better UI)
                  $isSuper = ($tipo === 'SuperAdministrador');
                ?>

                <?php if ($isSuper): ?>
                  <div class="tabs" id="calTabs">
                    <button type="button" class="tab" data-target="calBoth">Split View</button>
                  </div>

                  <?php
                    renderCalendarFrame($calendars['unidad_8'], 'cal8', 'Unit 8 Calendar');
                    renderCalendarFrame($calendars['unidad_7'], 'cal7', 'Unit 7 Calendar');
                  ?>

                  <div class="frame-wrap cal-frame" id="calBoth" data-label="Split View" style="display:none;">
                    <div class="loading"><div class="spinner"></div><div class="txt">Loading calendars…<br><span style="opacity:.75">Split View</span></div></div>
                    <div style="display:grid; grid-template-columns: 1fr; gap:12px; padding:12px;">
                      <div style="border:1px solid rgba(2,6,23,.08); border-radius:16px; overflow:hidden;">
                        <iframe id="cal8b" src="<?php echo htmlspecialchars($calendars['unidad_8']); ?>" loading="lazy" style="height:520px;"></iframe>
                      </div>
                      <div style="border:1px solid rgba(2,6,23,.08); border-radius:16px; overflow:hidden;">
                        <iframe id="cal7b" src="<?php echo htmlspecialchars($calendars['unidad_7']); ?>" loading="lazy" style="height:520px;"></iframe>
                      </div>
                    </div>
                  </div>

                <?php else: ?>
                  <?php
                    if ($unidad_id == 8) {
                      renderCalendarFrame($calendars['unidad_8'], 'calSingle', 'Unit 8 Calendar');
                    } elseif ($unidad_id == 7) {
                      renderCalendarFrame($calendars['unidad_7'], 'calSingle', 'Unit 7 Calendar');
                    } else {
                      echo '<div class="hint"><b>No calendar available</b> for your unit. Please contact the administrator.</div>';
                    }
                  ?>
                <?php endif; ?>

                <div class="hint">
                  <b>Tip:</b> Use <b>Reload</b> if Google Calendar takes too long to render. This view is optimized for desktop, tablet, and mobile.
                </div>
              </div>
            </div>

          </div><!-- wrap -->
        </div><!-- pgs-cal -->
      </div>
    </div>
  </div>

  <?php include './admin/include/gerenic_footer.php'; ?>
  <?php include './admin/include/gerenic_script.php'; ?>
  <?php include './admin/include/generic_search.php'; ?>
  <?php include './admin/include/generic_dataTables.php'; ?>

  <script>
  (function(){
    const badge = document.getElementById('calBadge');

    function setBadge(txt){ if(badge) badge.textContent = txt; }

    function hideLoaders(){
      document.querySelectorAll('.pgs-cal .cal-frame').forEach(frame=>{
        const ifr = frame.querySelector('iframe');
        const loader = frame.querySelector('.loading');
        if(!ifr || !loader) return;

        ifr.addEventListener('load', ()=> loader.style.display = 'none', { once:false });
        setTimeout(()=>{ loader.style.display = 'none'; }, 3500);
      });
    }

    function showFrame(targetId){
      const frames = document.querySelectorAll('.pgs-cal .cal-frame');
      frames.forEach(f=> f.style.display = 'none');

      let el = document.getElementById(targetId);
      if(!el){
        el = document.querySelector('.pgs-cal #' + CSS.escape(targetId));
      }
      if(!el){
        el = document.querySelector('.pgs-cal .cal-frame iframe#' + CSS.escape(targetId))?.closest('.cal-frame');
      }

      if(el){
        el.style.display = 'block';
        setBadge(el.getAttribute('data-label') || 'Calendar');
      }
    }

    // SuperAdmin tabs
    const tabs = document.getElementById('calTabs');
    if(tabs){
      tabs.addEventListener('click', (e)=>{
        const btn = e.target.closest('.tab');
        if(!btn) return;

        tabs.querySelectorAll('.tab').forEach(t=> t.classList.remove('active'));
        btn.classList.add('active');

        const tId = btn.getAttribute('data-target');
        if(tId === 'cal8') showFrame('cal8');
        if(tId === 'cal7') showFrame('cal7');
        if(tId === 'calBoth') showFrame('calBoth');
      });

      // ✅ DEFAULT = Split View
      const splitBtn = tabs.querySelector('.tab[data-target="calBoth"]');
      if(splitBtn){
        tabs.querySelectorAll('.tab').forEach(t=> t.classList.remove('active'));
        splitBtn.classList.add('active');
      }
      showFrame('calBoth');

    } else {
      // non-super: show first available frame
      const single = document.querySelector('.pgs-cal .cal-frame');
      if(single){
        single.style.display = 'block';
        setBadge(single.getAttribute('data-label') || 'Calendar');
      } else {
        setBadge('Calendar');
      }
    }

    // Buttons
    document.getElementById('btnReload')?.addEventListener('click', ()=> location.reload());

    document.getElementById('btnToday')?.addEventListener('click', ()=>{
      document.querySelectorAll('.pgs-cal iframe').forEach(ifr=>{
        try{
          const src = ifr.getAttribute('src') || '';
          if(!src) return;
          const u = new URL(src, window.location.href);
          u.searchParams.set('ts', String(Date.now()));
          ifr.setAttribute('src', u.toString());
        }catch(err){}
      });
      setBadge('Today');
    });

    hideLoaders();
  })();
</script>
</body>
</html>