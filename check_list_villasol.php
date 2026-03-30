<?php
require './admin/include/generic_classes.php';
include './admin/classes/Producto.php';

// Permissions
$view   = SessionData::getPermission(21);
$create = SessionData::getPermission(17);
$edit   = SessionData::getPermission(20);
$delete = SessionData::getPermission(18);
$enable = SessionData::getPermission(19);

if (!$view) { require 'permiso_denegado.php'; exit; }
$modulo = 'Check List Villasol HOA';

$userUnidad = SessionData::getUnidadUser();
$userType   = SessionData::getUserType();

date_default_timezone_set('America/Bogota');

$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$ajaxSave = $basePath . '/admin/ajax/checklist_save_ajax.php';
$ajaxLoad = $basePath . '/admin/ajax/checklist_load_ajax.php';
$ajaxUp   = $basePath . '/admin/ajax/checklist_upload_ajax.php';
$pdfUrl   = $basePath . '/admin/ajax/checklist_pdf.php';

// Optional edit: check_list.php?id=12
$checklistId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Template (UI text in English)
$TEMPLATE = [
  "1. Site and Surroundings" => [
    "Grading slopes away from foundation",
    "Proper drainage (no standing water)",
    "Vegetation trimmed away from structure",
    "Walkways, driveways, and patios in good condition",
    "Retaining walls stable and undamaged",
  ],
  "2. Foundation" => [
    "No visible cracks or movement",
    "No signs of water intrusion",
    "Vents unobstructed (if applicable)",
    "Proper clearance from soil to siding",
  ],
  "3. Exterior Walls" => [
    "Stucco in good condition",
    "No cracks, bulges, or signs of movement",
    "Paint or finish intact (no peeling or blistering)",
    "No signs of pest infestation or damage",
    "Flashing properly installed and sealed",
  ],
  "4. Windows and Doors" => [
    "Frames and sills in good condition",
    "No broken or cracked glass",
    "Proper sealing and caulking",
    "Operable and lockable",
    "Weatherstripping intact",
  ],
  "5. Roof (Visual from ground or ladder)" => [
    "Shingles intact and secure",
    "No sagging or uneven areas",
    "Flashing on chimneys and vents",
    "Gutters and downspouts secured",
    "No debris in gutters",
  ],
  "6. Eaves, Soffits & Fascia" => [
    "No damage or missing sections",
    "Proper ventilation",
    "Paint/finish in good condition",
  ],
  "7. Vents" => [
    "No cracks or deterioration",
    "Vents are clear",
  ],
  "9. Utilities & Equipment" => [
    "Electrical meter secured",
    "HVAC unit leveled and clean",
    "Outdoor outlets with GFCI protection",
  ],
  "10. Safety & Compliance" => [
    "House number visible from the street",
    "Outdoor lighting functional",
  ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include './admin/include/generic_head.php'; ?>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>

  <style>
/* ==========================================================
   PGS CHECKLIST – SaaS Premium (RED + BLACK)
   UI ONLY (no changes to back / ids / js logic)
========================================================== */

.checklist-saas{
  --pgs-red:#E11D2E;
  --pgs-red2:#B3121E;
  --pgs-black:#0B0F14;

  --bg:#f6f7fb;
  --card:#ffffff;
  --card2:#fbfcfe;

  --text:#0f172a;
  --muted:#64748b;

  --border: rgba(2,6,23,.10);
  --border2: rgba(2,6,23,.06);

  --shadow: 0 18px 60px rgba(2,6,23,.10);
  --shadow2: 0 10px 26px rgba(2,6,23,.08);

  --ring: 0 0 0 4px rgba(225,29,46,.16);

  --ok:#16a34a;
  --bad:#ef4444;
  --warn:#f59e0b;
  --pending:#94a3b8;

  --r12:12px;
  --r16:16px;
  --r20:20px;
  --r24:24px;

  color:var(--text) !important;
}
.checklist-saas *{ box-sizing:border-box; }
.checklist-saas a{ color:inherit; text-decoration:none; }

.checklist-saas .wrap{
  padding: 8px 0 22px 0;
}

/* ===== HERO ===== */
.checklist-saas .hero{
  position:relative;
  border-radius: var(--r24);
  padding: 16px;
  background:
    radial-gradient(980px 280px at 10% 0%, rgba(225,29,46,.22), transparent 60%),
    radial-gradient(820px 260px at 95% 10%, rgba(11,15,20,.18), transparent 60%),
    linear-gradient(135deg, #ffffff 0%, #fbfdff 55%, #f7f8fb 100%);
  border: 1px solid var(--border);
  box-shadow: var(--shadow);
  overflow:hidden;
  margin-bottom: 12px;
}
.checklist-saas .hero:before{
  content:"";
  position:absolute; inset:0;
  background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(255,255,255,0));
  pointer-events:none;
  opacity:.72;
}

.checklist-saas .hero-top{
  position:relative;
  display:flex;
  gap:14px;
  align-items:flex-start;
  justify-content:space-between;
  flex-wrap:wrap;
}

.checklist-saas .brandline{
  display:flex;
  gap:12px;
  align-items:flex-start;
}

.checklist-saas .pill{
  display:inline-flex;
  align-items:center;
  gap:10px;
  padding: 10px 12px;
  border-radius: 999px;
  background: rgba(255,255,255,.92);
  border: 1px solid var(--border);
  font-weight: 900;
  box-shadow: var(--shadow2);
}

.checklist-saas .pill .dot{
  width:10px; height:10px; border-radius:999px;
  background: linear-gradient(135deg, var(--pgs-red), var(--pgs-red2));
  box-shadow: 0 0 0 4px rgba(225,29,46,.14);
}

.checklist-saas .h-title{
  font-weight: 1000;
  letter-spacing: -.3px;
  font-size: 20px;
  line-height:1.1;
  color: var(--text);
}
.checklist-saas .h-sub{
  margin-top:4px;
  font-weight: 750;
  font-size: 12px;
  color: var(--muted);
}

/* ===== Buttons premium ===== */
.checklist-saas .hero-actions{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  justify-content:flex-end;
}

.checklist-saas .btn-saas{
  border: 1px solid var(--border) !important;
  background: rgba(255,255,255,.92) !important;
  color: var(--text) !important;
  padding: 10px 14px !important;
  border-radius: 14px !important;
  font-weight: 950 !important;
  transition: transform .15s ease, filter .15s ease, box-shadow .15s ease, border-color .15s ease;
  box-shadow: 0 6px 18px rgba(2,6,23,.06);
}
.checklist-saas .btn-saas:hover{
  transform: translateY(-1px);
  filter: brightness(1.02);
  border-color: rgba(225,29,46,.35) !important;
  box-shadow: 0 10px 22px rgba(2,6,23,.10);
}
.checklist-saas .btn-saas:active{ transform: translateY(0px) scale(.99); }

.checklist-saas .btn-saas.btn-sm{
  padding: 8px 10px !important;
  border-radius: 12px !important;
  font-size: 12px !important;
  line-height: 1 !important;
}

.checklist-saas .btn-brand{
  background: linear-gradient(135deg, var(--pgs-red), var(--pgs-red2)) !important;
  color:#fff !important;
  border: none !important;
  box-shadow: 0 14px 34px rgba(225,29,46,.22);
}
.checklist-saas .btn-brand:hover{
  filter: brightness(1.03);
  box-shadow: 0 18px 40px rgba(225,29,46,.28);
}

.checklist-saas .btn-black{
  background: linear-gradient(180deg, rgba(17,24,39,1), rgba(11,15,20,1)) !important;
  color:#fff !important;
  border: none !important;
  box-shadow: 0 14px 34px rgba(2,6,23,.20);
}
.checklist-saas .btn-black:hover{
  filter: brightness(1.03);
  box-shadow: 0 18px 40px rgba(2,6,23,.26);
}
.checklist-saas .btn-ghost{
  background: rgba(255,255,255,.76) !important;
}

/* Hide native file input */
.checklist-saas .photo-pick{
  position: relative;
  overflow: hidden;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
}
.checklist-saas .photo-pick input[type="file"]{
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}

/* ===== HERO GRID ===== */
.checklist-saas .hero-grid{
  position:relative;
  margin-top: 12px;
  display:grid;
  grid-template-columns: 1fr;
  gap: 12px;
}
@media (min-width: 992px){
  .checklist-saas .hero-grid{
    grid-template-columns: 1.25fr .75fr;
    align-items:stretch;
  }
}

.checklist-saas .glass{
  border-radius: var(--r20);
  background: rgba(255,255,255,.92);
  border: 1px solid var(--border);
  padding: 12px;
  box-shadow: var(--shadow2);
}

.checklist-saas .progress-row{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  flex-wrap:wrap;
}

.checklist-saas .kpi{
  display:flex;
  align-items:center;
  gap:10px;
}
.checklist-saas .kpi .num{
  font-size: 20px;
  font-weight: 1000;
  color: var(--text);
  line-height:1;
}
.checklist-saas .kpi .lab{
  font-size: 12px;
  font-weight: 800;
  color: var(--muted);
}

.checklist-saas .chips{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  justify-content:flex-end;
}
.checklist-saas .chip{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding: 8px 10px;
  border-radius: 999px;
  border: 1px solid var(--border);
  background: rgba(255,255,255,.86);
  font-weight: 900;
  font-size: 12px;
}
.checklist-saas .chip .m{ color: var(--muted); font-weight: 800; }
.checklist-saas .chip .b{ color: var(--text); font-weight: 1000; }

/* Progress bar */
.checklist-saas .progress{
  height: 12px !important;
  background: #eef2f7 !important;
  border-radius: 999px !important;
  overflow:hidden;
  border:1px solid var(--border);
}
.checklist-saas .progress-bar{
  height: 12px !important;
  width:0%;
  background: linear-gradient(90deg, var(--ok), var(--pgs-red)) !important;
  border-radius: 999px !important;
  transition: width .25s ease;
}

.checklist-saas .helper{
  font-weight: 750;
  font-size: 12px;
  color: var(--muted);
  line-height: 1.45;
}

/* ===== Toolbar ===== */
.checklist-saas .toolbar{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  flex-wrap:wrap;
  margin: 10px 0 14px 0;
}

.checklist-saas .search{
  flex: 1 1 340px;
  display:flex;
  align-items:center;
  gap:10px;
  padding: 10px 12px;
  border-radius: 14px;
  border: 1px solid var(--border);
  background: rgba(255,255,255,.92);
  box-shadow: 0 8px 18px rgba(2,6,23,.06);
}
.checklist-saas .search .icon{
  width:34px; height:34px; border-radius: 12px;
  display:grid; place-items:center;
  background: rgba(225,29,46,.10);
  border: 1px solid rgba(225,29,46,.18);
  font-weight: 1000;
  color: var(--pgs-red2);
}
.checklist-saas .search input{
  border:0 !important;
  outline:none !important;
  background: transparent !important;
  color: var(--text) !important;
  width:100%;
  font-weight: 800;
}
.checklist-saas .search input::placeholder{ color: #94a3b8; }

/* ===== Layout cards ===== */
.checklist-saas .grid{
  display:grid;
  grid-template-columns: 1fr;
  gap: 14px;
}
@media (min-width: 1100px){
  .checklist-saas .grid{ grid-template-columns: 1.1fr .9fr; }
}

.checklist-saas .card-saas{
  border-radius: var(--r24);
  background: linear-gradient(180deg, var(--card), var(--card2));
  border: 1px solid var(--border);
  box-shadow: var(--shadow);
  overflow:hidden;
}

.checklist-saas .card-head{
  padding: 14px 16px;
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  flex-wrap:wrap;
  background: linear-gradient(180deg, #ffffff, #f8fafc);
  border-bottom: 1px solid var(--border);
}
.checklist-saas .card-head h5{
  margin:0;
  font-weight: 1000;
  color: var(--text);
  letter-spacing:-.2px;
}
.checklist-saas .sec-badge{
  display:inline-flex;
  align-items:center;
  gap:8px;
  font-weight: 900;
  font-size: 12px;
  padding: 7px 10px;
  border-radius: 999px;
  background: rgba(225,29,46,.08);
  border:1px solid rgba(225,29,46,.18);
  color: var(--pgs-red2);
}
.checklist-saas .body{ padding: 14px 16px; }

/* ===== Sections ===== */
.checklist-saas .section{
  border-radius: var(--r20);
  background: #fff;
  border: 1px solid var(--border);
  margin-bottom: 12px;
  overflow:hidden;
  box-shadow: 0 8px 18px rgba(2,6,23,.05);
}
.checklist-saas .section-title{
  padding: 12px 14px;
  font-weight: 1000;
  display:flex;
  justify-content:space-between;
  align-items:center;
  background: linear-gradient(180deg, #ffffff, #f7fafc);
  border-bottom: 1px solid var(--border);
  color: var(--text);
  gap:10px;
  flex-wrap:wrap;
  cursor:pointer;
  user-select:none;
}
.checklist-saas .section-title .left{
  display:flex; gap:10px; align-items:center; flex-wrap:wrap;
}
.checklist-saas .caret{
  width:28px; height:28px; border-radius: 12px;
  display:grid; place-items:center;
  background: rgba(225,29,46,.10);
  border: 1px solid rgba(225,29,46,.18);
  font-weight: 1000;
  color: var(--pgs-red2);
  transition: transform .18s ease;
}
.checklist-saas .section[data-collapsed="1"] .caret{ transform: rotate(-90deg); }
.checklist-saas .section .items{ padding: 10px 12px; }
.checklist-saas .section[data-collapsed="1"] .items{ display:none; }

/* ===== Items ===== */
.checklist-saas .item{
  display:grid;
  grid-template-columns: 1fr;
  gap:12px;
  padding: 12px 0;
  border-bottom: 1px dashed #e6edf5;
}
.checklist-saas .item:last-child{ border-bottom:none; }
@media (min-width: 900px){
  .checklist-saas .item{ grid-template-columns: 1.2fr .8fr; align-items:start; }
}
.checklist-saas .item-label{
  font-weight: 950;
  color: var(--text);
  font-size: 13.5px;
}
.checklist-saas .subrow{
  margin-top: 10px;
  display:grid;
  grid-template-columns: 1fr;
  gap: 10px;
}

/* ===== Inputs ===== */
.checklist-saas .control{
  width: 100% !important;
  background: rgba(255,255,255,.95) !important;
  border: 1px solid var(--border) !important;
  color: var(--text) !important;
  border-radius: 14px !important;
  padding: 10px 12px !important;
  outline:none !important;
  font-weight: 750;
}
.checklist-saas .control::placeholder{ color:#94a3b8 !important; }
.checklist-saas .control:focus{
  border-color: rgba(225,29,46,.55) !important;
  box-shadow: var(--ring) !important;
}
.checklist-saas textarea.control{ resize: vertical; min-height: 110px; }

/* ===== Choice pills ===== */
.checklist-saas .choices{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
}
.checklist-saas .choice{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding: 9px 10px;
  border-radius: 999px;
  border: 1px solid var(--border);
  background: #fff;
  font-weight: 950;
  cursor:pointer;
  user-select:none;
  transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
  box-shadow: 0 6px 14px rgba(2,6,23,.05);
}
.checklist-saas .choice:hover{
  transform: translateY(-1px);
  box-shadow: 0 10px 18px rgba(2,6,23,.08);
  border-color: rgba(225,29,46,.25);
}
.checklist-saas .choice input{ accent-color: var(--pgs-red); cursor:pointer; }

.checklist-saas .choice.ok{ border-color: rgba(22,163,74,.25); }
.checklist-saas .choice.bad{ border-color: rgba(239,68,68,.25); }
.checklist-saas .choice.na{ border-color: rgba(245,158,11,.25); }

.checklist-saas .choice.ok:has(input:checked){
  background: rgba(22,163,74,.10);
  border-color: rgba(22,163,74,.40);
}
.checklist-saas .choice.bad:has(input:checked){
  background: rgba(239,68,68,.10);
  border-color: rgba(239,68,68,.40);
}
.checklist-saas .choice.na:has(input:checked){
  background: rgba(245,158,11,.12);
  border-color: rgba(245,158,11,.45);
}
.checklist-saas .choice:has(input:checked){
  background: rgba(225,29,46,.08);
  border-color: rgba(225,29,46,.28);
}

/* ===== Photo area ===== */
.checklist-saas .photo-box{
  border-radius: var(--r16);
  border: 1px dashed rgba(2,6,23,.18);
  background: #f8fafc;
  padding: 10px;
}
.checklist-saas .photo-actions{
  display:flex;
  gap:10px;
  flex-wrap:wrap;
  align-items:center;
}
.checklist-saas .photo-preview{
  margin-top:10px;
  display:grid;
  grid-template-columns: repeat(4, 1fr);
  gap:8px;
}
@media (max-width: 520px){
  .checklist-saas .photo-preview{ grid-template-columns: repeat(3, 1fr); }
}
.checklist-saas .thumb{
  border-radius: 12px;
  overflow:hidden;
  border:1px solid var(--border);
  background: #fff;
  aspect-ratio: 1 / 1;
  box-shadow: 0 8px 16px rgba(2,6,23,.06);
}
.checklist-saas .thumb img{
  width:100%;
  height:100%;
  object-fit:cover;
  display:block;
}

/* ===== Right sticky ===== */
.checklist-saas .sticky-bar{ position: sticky; top: 88px; }
@media (max-width: 1099px){ .checklist-saas .sticky-bar{ position: relative; top:auto; } }

/* ===== KPI tiles ===== */
.checklist-saas .kpi-grid{
  display:grid;
  grid-template-columns: repeat(2, 1fr);
  gap:10px;
}
.checklist-saas .tile{
  border-radius: var(--r20);
  border: 1px solid var(--border);
  background: #fff;
  padding: 12px 12px;
  box-shadow: 0 10px 22px rgba(2,6,23,.06);
}
.checklist-saas .tile .t{
  font-size: 11px;
  font-weight: 950;
  color: var(--muted);
  letter-spacing:.2px;
}
.checklist-saas .tile .v{
  margin-top:6px;
  font-size: 18px;
  font-weight: 1000;
  color: var(--text);
  line-height:1;
}
.checklist-saas .tile.pass .v{ color: rgba(22,163,74,.95); }
.checklist-saas .tile.fail .v{ color: rgba(239,68,68,.95); }
.checklist-saas .tile.na .v{ color: rgba(245,158,11,.95); }
.checklist-saas .tile.pending .v{ color: rgba(100,116,139,.95); }

/* ===== Toast ===== */
.checklist-saas .toastx{
  position: fixed;
  right: 16px;
  bottom: 18px;
  z-index: 99999;
  padding: 10px 12px;
  border-radius: 14px;
  background: rgba(11,15,20,.94);
  border: 1px solid rgba(255,255,255,.14);
  color: #fff;
  font-weight: 950;
  box-shadow: var(--shadow);
  display:none;
  max-width: min(420px, calc(100vw - 32px));
}

/* ===== Mobile sticky action bar ===== */
.checklist-saas .mobile-bar{
  position: fixed;
  left: 12px;
  right: 12px;
  bottom: 12px;
  z-index: 99998;
  padding: 10px;
  border-radius: 18px;
  background: rgba(255,255,255,.92);
  border: 1px solid var(--border);
  backdrop-filter: blur(10px);
  box-shadow: var(--shadow);
  display:none;
}
.checklist-saas .mobile-bar .row{
  display:flex;
  gap:10px;
  align-items:center;
  justify-content:space-between;
  flex-wrap:wrap;
}
.checklist-saas .mobile-bar .pct{
  font-weight: 1000; color: var(--text);
}
@media (max-width: 768px){
  .checklist-saas .mobile-bar{ display:block; }
  .checklist-saas .mobile-bar-spacer{ height: 84px; }
}

.checklist-saas #badgeStatus{ font-weight: 1000; }
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
      <div class="container-fluid saas-wrap">
        <div class="checklist-saas">
          <div class="wrap">

            <!-- HERO -->
            <div class="hero">
              <div class="hero-top">
                <div class="brandline">
                  <span class="pill"><span class="dot"></span> Checklist</span>
                  <div>
                    <div class="h-title">Exterior Inspection Checklist</div>
                    <div class="h-sub">
                      Unit: <?php echo htmlspecialchars((string)$userUnidad); ?>
                      • ID: <span id="uiChecklistId"><?php echo (int)$checklistId; ?></span>
                    </div>
                  </div>
                </div>

                <div class="hero-actions">
                  <button type="button" class="btn btn-saas btn-ghost" id="btnLoad">Load</button>
                  <button type="button" class="btn btn-saas btn-black" id="btnPdf">PDF</button>
                  <button type="button" class="btn btn-saas btn-brand" id="btnSave">Save</button>
                </div>
              </div>

              <div class="hero-grid">
                <div class="glass">
                  <div class="progress-row">
                    <div class="kpi">
                      <div class="num"><span id="progressPct">0</span>%</div>
                      <div class="lab">Auto progress (completed items)</div>
                    </div>

                    <div class="chips">
                      <div class="chip"><span class="m">Mode:</span> <span class="b" id="badgeAuto">Auto</span></div>
                      <div class="chip"><span class="m">Status:</span> <span class="b" id="badgeStatus" data-estado="borrador">Draft</span></div>
                    </div>
                  </div>

                  <div class="progress" style="margin-top:10px;">
                    <div class="progress-bar" id="progressBar"></div>
                  </div>

                  <div class="helper" style="margin-top:10px;">
                    • Saving is instant (AJAX). • Photos auto-upload on selection. • Print PDF with evidence anytime.
                  </div>
                </div>

                <div class="glass">
                  <div class="helper" style="font-weight:1000; color: var(--text);">Quick navigation</div>
                  <div class="helper" style="margin-top:8px;">
                    Use the search bar to filter items instantly.<br>
                    Click any section title to collapse/expand.
                  </div>
                </div>
              </div>
            </div>

            <!-- TOOLBAR -->
            <div class="toolbar">
              <div class="search">
                <div class="icon">⌕</div>
                <input id="qSearch" type="text" placeholder="Search sections or items... (e.g., gutter, HVAC, lighting)">
              </div>
              <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <button type="button" class="btn btn-saas btn-ghost" id="btnExpandAll">Expand all</button>
                <button type="button" class="btn btn-saas btn-ghost" id="btnCollapseAll">Collapse all</button>
              </div>
            </div>

            <div class="grid">

              <!-- LEFT -->
              <div class="card-saas">
                <div class="card-head">
                  <h5>Checklist Items</h5>
                  <span class="sec-badge" id="badgeStatusTop">Status: <span id="statusTextTop">Draft</span></span>
                </div>

                <div class="body">
                  <input type="hidden" id="checklist_id" value="<?php echo (int)$checklistId; ?>">
                  <input type="hidden" id="unidad" value="<?php echo htmlspecialchars((string)$userUnidad); ?>">
                  <input type="hidden" id="created_by" value="<?php echo (int)SessionData::getUserId(); ?>">

                  <?php
                    $idx = 0;
                    foreach ($TEMPLATE as $section => $items) {
                      echo '<div class="section" data-section="'.htmlspecialchars($section).'" data-collapsed="0">';
                      echo '  <div class="section-title" title="Click to collapse/expand">';
                      echo '    <div class="left">';
                      echo '      <span class="caret">›</span>';
                      echo '      <span>'.htmlspecialchars($section).'</span>';
                      echo '    </div>';
                      echo '    <span class="sec-badge"><span class="secDone">0</span>/<span class="secTotal">'.count($items).'</span> completed</span>';
                      echo '  </div>';
                      echo '  <div class="items">';
                      foreach ($items as $label) {
                        $idx++;
                        $key = 'it_'.$idx;

                        echo '
                          <div class="item" data-itemkey="'.$key.'">
                            <div>
                              <div class="item-label">'.htmlspecialchars($label).'</div>

                              <div class="subrow">
                                <input class="control item-comment" type="text" placeholder="Comment (optional)">

                                <div class="photo-box">
                                  <div class="photo-actions">
                                    <label class="btn btn-saas btn-sm btn-ghost photo-pick">
                                      Choose files
                                      <input type="file" class="item-photo" accept="image/*" multiple>
                                    </label>
                                    <span class="helper" style="margin:0;">Auto-upload on selection</span>
                                  </div>
                                  <div class="photo-preview"></div>
                                  <div class="helper" style="margin-top:8px;">
                                    Tip: take a photo and upload it here (linked to this item).
                                  </div>
                                </div>
                              </div>
                            </div>

                            <div class="choices">
                              <label class="choice ok"><input type="radio" name="'.$key.'" value="cumple"> Pass</label>
                              <label class="choice bad"><input type="radio" name="'.$key.'" value="no_cumple"> Fail</label>
                              <label class="choice na"><input type="radio" name="'.$key.'" value="na"> N/A</label>
                              <label class="choice"><input type="radio" name="'.$key.'" value="pendiente" checked> Pending</label>
                            </div>
                          </div>
                        ';
                      }
                      echo '  </div>';
                      echo '</div>';
                    }
                  ?>
                </div>
              </div>

              <!-- RIGHT -->
              <div class="sticky-bar">
                <div class="card-saas">
                  <div class="card-head">
                    <h5>Summary</h5>
                    <span class="sec-badge">Live KPIs</span>
                  </div>

                  <div class="body">
                    <div class="kpi-grid" style="margin-bottom:12px;">
                      <div class="tile pass">
                        <div class="t">PASS</div>
                        <div class="v" id="kpiPass">0</div>
                      </div>
                      <div class="tile fail">
                        <div class="t">FAIL</div>
                        <div class="v" id="kpiFail">0</div>
                      </div>
                      <div class="tile na">
                        <div class="t">N/A</div>
                        <div class="v" id="kpiNa">0</div>
                      </div>
                      <div class="tile pending">
                        <div class="t">PENDING</div>
                        <div class="v" id="kpiPending">0</div>
                      </div>
                    </div>

                    <div style="display:grid; gap:10px;">
                      <div>
                        <div class="helper" style="font-weight:1000; color: var(--text);">Title</div>
                        <input class="control" id="title" value="Exterior Inspection Checklist" />
                      </div>

                      <div>
                        <div class="helper" style="font-weight:1000; color: var(--text);">Inspector</div>
                        <input class="control" id="inspector_name" placeholder="Inspector name" />
                      </div>

                      <div>
                        <div class="helper" style="font-weight:1000; color: var(--text);">General comments</div>
                        <textarea class="control" id="general_comments" rows="4" placeholder="General notes..."></textarea>
                      </div>

                      <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <button type="button" class="btn btn-saas btn-brand" id="btnFinalize">Finalize</button>
                        <button type="button" class="btn btn-saas btn-ghost" id="btnDraft">Keep as draft</button>
                      </div>

                      <div class="helper">
                        ✅ Save first to generate an ID.<br>
                        📸 Select photos per item (auto-upload), then print the PDF when ready.
                      </div>

                    </div>
                  </div>
                </div>
              </div>

            </div><!-- grid -->

            <div class="mobile-bar-spacer"></div>

          </div>

          <!-- Toast -->
          <div class="toastx" id="toastx"></div>

          <!-- Mobile sticky action bar -->
          <div class="mobile-bar" id="mobileBar">
            <div class="row">
              <div class="left">
                <div class="pct"><span id="mPct">0</span>%</div>
                <div class="helper" style="margin:0;">Progress</div>
              </div>
              <div style="display:flex; gap:10px;">
                <button type="button" class="btn btn-saas btn-ghost" id="mLoad">Load</button>
                <button type="button" class="btn btn-saas btn-brand" id="mSave">Save</button>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <?php include './admin/include/gerenic_footer.php'; ?>
  </div>

  <?php include './admin/include/gerenic_script.php'; ?>
  <?php
    if (!isset($arr) || !is_array($arr)) { $arr = []; }
    if (!isset($arrSearch) || !is_array($arrSearch)) { $arrSearch = []; }
    if (!isset($optionSearch) || !is_array($optionSearch)) { $optionSearch = []; }
  ?>
  <?php include './admin/include/generic_search.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script>
    const AJAX_SAVE = <?php echo json_encode($ajaxSave); ?>;
    const AJAX_LOAD = <?php echo json_encode($ajaxLoad); ?>;
    const AJAX_UP   = <?php echo json_encode($ajaxUp); ?>;
    const PDF_URL   = <?php echo json_encode($pdfUrl); ?>;

    const toast = (msg) => {
      const t = document.getElementById('toastx');
      t.textContent = msg;
      t.style.display = 'block';
      clearTimeout(window.__toastTimer);
      window.__toastTimer = setTimeout(()=>{ t.style.display='none'; }, 2400);
    };

    function syncStatusBadges(){
      const badge = document.getElementById('badgeStatus');
      const raw = (badge && badge.dataset && badge.dataset.estado) ? badge.dataset.estado : 'borrador';
      const label = (raw === 'finalizado') ? 'Finalized' : 'Draft';

      badge.textContent = label;

      const topText = document.getElementById('statusTextTop');
      if(topText) topText.textContent = label;
    }

    function calcKpis(){
      let pass=0, fail=0, na=0, pending=0;

      document.querySelectorAll('.item').forEach(it=>{
        const val = (it.querySelector('input[type=radio]:checked') || {}).value || 'pendiente';
        if(val === 'cumple') pass++;
        else if(val === 'no_cumple') fail++;
        else if(val === 'na') na++;
        else pending++;
      });

      const elPass = document.getElementById('kpiPass');
      const elFail = document.getElementById('kpiFail');
      const elNa   = document.getElementById('kpiNa');
      const elPen  = document.getElementById('kpiPending');

      if(elPass) elPass.textContent = pass;
      if(elFail) elFail.textContent = fail;
      if(elNa)   elNa.textContent   = na;
      if(elPen)  elPen.textContent  = pending;
    }

    function getChecklistPayload(){
      const sections = [];
      document.querySelectorAll('.section').forEach(sec=>{
        const sectionName = sec.getAttribute('data-section');
        const items = [];
        sec.querySelectorAll('.item').forEach(it=>{
          const key = it.getAttribute('data-itemkey');
          const label = it.querySelector('.item-label').textContent.trim();
          const status = (it.querySelector('input[type=radio]:checked') || {}).value || 'pendiente';
          const comment = (it.querySelector('.item-comment') || {}).value || '';
          items.push({ key, label, status, comment });
        });
        sections.push({ section: sectionName, items });
      });

      return {
        checklist_id: parseInt(document.getElementById('checklist_id').value || '0', 10),
        title: document.getElementById('title').value || 'Checklist',
        unidad: document.getElementById('unidad').value || '',
        estado: document.getElementById('badgeStatus').dataset.estado || 'borrador',
        general_comments: document.getElementById('general_comments').value || '',
        inspector_name: document.getElementById('inspector_name').value || '',
        progress: parseInt(document.getElementById('progressPct').textContent || '0', 10),
        sections
      };
    }

    function applyProgress(){
      let total = 0, done = 0;

      document.querySelectorAll('.section').forEach(sec=>{
        const items = sec.querySelectorAll('.item');
        const sTotal = items.length;
        let sDone = 0;

        items.forEach(it=>{
          const val = (it.querySelector('input[type=radio]:checked') || {}).value || 'pendiente';
          total++;
          if(val !== 'pendiente'){ done++; sDone++; }
        });

        sec.querySelector('.secDone').textContent = sDone;
        sec.querySelector('.secTotal').textContent = sTotal;
      });

      const pct = total ? Math.round((done/total)*100) : 0;
      document.getElementById('progressPct').textContent = pct;
      document.getElementById('progressBar').style.width = pct + '%';

      const mPct = document.getElementById('mPct');
      if(mPct) mPct.textContent = pct;

      calcKpis();
    }

    function applySearchFilter(q){
      q = (q || '').trim().toLowerCase();

      document.querySelectorAll('.section').forEach(sec=>{
        const sectionName = (sec.getAttribute('data-section') || '').toLowerCase();
        let anyVisible = false;

        sec.querySelectorAll('.item').forEach(it=>{
          const label = (it.querySelector('.item-label')?.textContent || '').toLowerCase();
          const comment = (it.querySelector('.item-comment')?.value || '').toLowerCase();
          const hit = !q || sectionName.includes(q) || label.includes(q) || comment.includes(q);

          it.style.display = hit ? '' : 'none';
          if(hit) anyVisible = true;
        });

        if(q && sectionName.includes(q)){
          sec.querySelectorAll('.item').forEach(it=> it.style.display = '');
          anyVisible = true;
        }

        sec.style.display = anyVisible ? '' : 'none';
      });
    }

    function toggleSection(sec){
      const collapsed = sec.getAttribute('data-collapsed') === '1';
      sec.setAttribute('data-collapsed', collapsed ? '0' : '1');
    }
    function setAllSections(collapsed){
      document.querySelectorAll('.section').forEach(sec=>{
        sec.setAttribute('data-collapsed', collapsed ? '1' : '0');
      });
    }

    document.addEventListener('click', (e)=>{
      const st = e.target.closest('.section-title');
      if(st && st.closest('.section')){
        toggleSection(st.closest('.section'));
      }
    });

    document.getElementById('btnExpandAll').addEventListener('click', ()=> setAllSections(false));
    document.getElementById('btnCollapseAll').addEventListener('click', ()=> setAllSections(true));

    const qSearch = document.getElementById('qSearch');
    qSearch.addEventListener('input', ()=> applySearchFilter(qSearch.value));

    async function fireSwal(opts){
      if (typeof Swal === "undefined") {
        alert((opts.title || 'Message') + (opts.text ? "\n\n" + opts.text : ""));
        return { isConfirmed: true };
      }
      return await Swal.fire(opts);
    }

    function resetChecklistForm() {
      const idInput = document.getElementById('checklist_id');
      const idLabel = document.getElementById('uiChecklistId');
      if (idInput) idInput.value = '0';
      if (idLabel) idLabel.textContent = '0';

      const badge = document.getElementById('badgeStatus');
      if (badge) badge.dataset.estado = 'borrador';
      syncStatusBadges();

      document.querySelectorAll('.item').forEach(item => {
        const pending = item.querySelector('input[type=radio][value="pendiente"]');
        if (pending) pending.checked = true;

        const comment = item.querySelector('.item-comment');
        if (comment) comment.value = '';

        const preview = item.querySelector('.photo-preview');
        if (preview) preview.innerHTML = '';

        const fileInput = item.querySelector('.item-photo');
        if (fileInput) fileInput.value = '';

        item.style.display = '';
      });

      const inspector = document.getElementById('inspector_name');
      const comments  = document.getElementById('general_comments');
      if (inspector) inspector.value = '';
      if (comments) comments.value = '';

      const title = document.getElementById('title');
      if (title) title.value = 'Exterior Inspection Checklist';

      const q = document.getElementById('qSearch');
      if (q) q.value = '';
      document.querySelectorAll('.section').forEach(sec => sec.style.display = '');
      setAllSections(false);

      const progressBar = document.getElementById('progressBar');
      const progressPct = document.getElementById('progressPct');
      if (progressBar) progressBar.style.width = '0%';
      if (progressPct) progressPct.textContent = '0';

      document.querySelectorAll('.section').forEach(sec => {
        const done = sec.querySelector('.secDone');
        if (done) done.textContent = '0';
      });

      applyProgress();
      toast('Ready for a new checklist ✅');
    }

    async function saveChecklist({ silent = false, resetAfter = true } = {}) {
      const payload = getChecklistPayload();

      const btn = document.getElementById('btnSave');
      const originalText = btn ? btn.textContent : 'Save';

      if (btn) {
        btn.disabled = true;
        btn.textContent = 'Saving...';
      }

      try {
        const res = await fetch(AJAX_SAVE, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });

        const txt = await res.text();
        let data;

        try { data = JSON.parse(txt); }
        catch (e) {
          console.error('NON-JSON RESPONSE:', txt);
          if (!silent) {
            await fireSwal({
              icon: 'error',
              title: 'Technical Error',
              text: 'The server did not return valid JSON.',
              confirmButtonColor: '#d33',
            });
          } else {
            toast('Backend error: invalid JSON response.');
          }
          return false;
        }

        if (!data || data.ok !== true) {
          if (!silent) {
            await fireSwal({
              icon: 'error',
              title: 'Save Failed',
              text: data?.msg || 'An unexpected error occurred.',
              confirmButtonColor: '#d33',
            });
          } else {
            toast(data?.msg || 'Save failed.');
          }
          return false;
        }

        const newId = parseInt(data.checklist_id, 10) || 0;

        const idInput = document.getElementById('checklist_id');
        const idLabel = document.getElementById('uiChecklistId');
        if (idInput) idInput.value = newId;
        if (idLabel) idLabel.textContent = String(newId);

        if (!silent) {
          const result = await fireSwal({
            icon: 'success',
            title: 'Checklist Saved Successfully',
            html: `<b>ID:</b> #${newId}<br><small style="opacity:.8">A new blank form will be ready after closing.</small>`,
            confirmButtonText: 'OK',
            confirmButtonColor: '#E11D2E',
            backdrop: true,
            allowOutsideClick: false
          });

          if (result.isConfirmed && resetAfter) {
            resetChecklistForm();
          }
        } else {
          toast(`Saved ✅ ID #${newId}`);
        }

        return true;

      } catch (err) {
        console.error(err);
        if (!silent) {
          await fireSwal({
            icon: 'error',
            title: 'Connection Error',
            text: 'Unable to communicate with the server.',
            confirmButtonColor: '#d33',
          });
        } else {
          toast('Connection error.');
        }
        return false;

      } finally {
        if (btn) {
          btn.disabled = false;
          btn.textContent = originalText;
        }
      }
    }

    async function loadChecklist(){
      const id = parseInt(document.getElementById('checklist_id').value || '0', 10);
      if(!id){ toast('Enter a valid ID.'); return; }

      try{
        const res = await fetch(AJAX_LOAD + '?id=' + encodeURIComponent(id));
        const txt = await res.text();
        let data = null;

        try { data = JSON.parse(txt); }
        catch(e){
          console.error("NON-JSON:", txt);
          toast("Backend error: response is not JSON (check console).");
          return;
        }

        if(!data || !data.ok){
          toast(data?.msg || 'Unable to load.');
          return;
        }

        document.getElementById('title').value = data.checklist.title || '';
        document.getElementById('inspector_name').value = data.checklist.inspector_name || '';
        document.getElementById('general_comments').value = data.checklist.general_comments || '';

        const rawState = (data.checklist.estado || 'borrador');
        const badge = document.getElementById('badgeStatus');
        badge.dataset.estado = rawState;
        syncStatusBadges();

        const map = {};
        (data.items || []).forEach(it=>{
          map[(it.section || '') + '||' + (it.item_label || '')] = it;
        });

        document.querySelectorAll('.section').forEach(sec=>{
          const sectionName = sec.getAttribute('data-section');
          sec.querySelectorAll('.item').forEach(domItem=>{
            const label = domItem.querySelector('.item-label').textContent.trim();
            const found = map[sectionName + '||' + label];

            if(found){
              const val = found.status || 'pendiente';
              const radio = domItem.querySelector(`input[type=radio][value="${val}"]`);
              if(radio) radio.checked = true;

              domItem.querySelector('.item-comment').value = found.comment || '';

              const prev = domItem.querySelector('.photo-preview');
              prev.innerHTML = '';
              if(found.files && found.files.length){
                found.files.slice(0,8).forEach(f=>{
                  const div = document.createElement('div');
                  div.className = 'thumb';
                  div.innerHTML = `<img src="${f.url}" alt="photo">`;
                  prev.appendChild(div);
                });
              }
            }
          });
        });

        applyProgress();
        toast('Loaded ✅');
      }catch(err){
        console.error(err);
        toast('Load error.');
      }
    }

    async function uploadPhotosFromItem(itemEl){
      const input = itemEl.querySelector('.item-photo');
      const files = input?.files;

      if(!files || !files.length){
        toast('Attach at least 1 photo.');
        return;
      }

      let checklist_id = parseInt(document.getElementById('checklist_id').value || '0', 10);

      if(!checklist_id){
        toast('Generating ID…');
        const ok = await saveChecklist({ silent: true, resetAfter: false });
        if(!ok){ toast('Unable to create checklist.'); return; }
        checklist_id = parseInt(document.getElementById('checklist_id').value || '0', 10);
        if(!checklist_id){ toast('Unable to create checklist.'); return; }
      }

      const section = itemEl.closest('.section').getAttribute('data-section');
      const label   = itemEl.querySelector('.item-label').textContent.trim();

      const fd = new FormData();
      fd.append('checklist_id', checklist_id);
      fd.append('section', section);
      fd.append('item_label', label);
      [...files].forEach(f => fd.append('files[]', f));

      toast('Uploading photos…');

      try{
        const res = await fetch(AJAX_UP, { method:'POST', body: fd });
        const txt = await res.text();
        let data;

        try { data = JSON.parse(txt); }
        catch(e){
          console.error("UPLOAD NON-JSON:", txt);
          toast("Backend did not return JSON. Check console (F12).");
          return;
        }

        if(!data || !data.ok){
          toast(data?.msg || 'Upload failed.');
          return;
        }

        const prev = itemEl.querySelector('.photo-preview');
        prev.innerHTML = '';
        (data.files || []).slice(0,8).forEach(f=>{
          const div = document.createElement('div');
          div.className = 'thumb';
          div.innerHTML = `<img src="${f.url}" alt="photo">`;
          prev.appendChild(div);
        });

        input.value = '';
        toast('Uploaded ✅');

      }catch(err){
        console.error(err);
        toast('Upload error.');
      }
    }

    document.addEventListener('change', (e)=>{
      if(e.target.matches('input[type=radio], .item-comment')){
        applyProgress();
      }

      if(e.target.matches('.item-photo')){
        const itemEl = e.target.closest('.item');

        const prev = itemEl.querySelector('.photo-preview');
        prev.innerHTML = '';
        [...e.target.files].slice(0,8).forEach(file=>{
          const url = URL.createObjectURL(file);
          const div = document.createElement('div');
          div.className = 'thumb';
          div.innerHTML = `<img src="${url}" alt="photo">`;
          prev.appendChild(div);
        });

        uploadPhotosFromItem(itemEl);
      }
    });

    document.getElementById('btnSave').addEventListener('click', ()=> saveChecklist({ silent:false, resetAfter:true }));
    document.getElementById('btnLoad').addEventListener('click', loadChecklist);

    document.getElementById('btnFinalize').addEventListener('click', async ()=>{
      const badge = document.getElementById('badgeStatus');
      badge.dataset.estado = 'finalizado';
      syncStatusBadges();
      await saveChecklist({ silent:false, resetAfter:true });
    });

    document.getElementById('btnDraft').addEventListener('click', async ()=>{
      const badge = document.getElementById('badgeStatus');
      badge.dataset.estado = 'borrador';
      syncStatusBadges();
      await saveChecklist({ silent:false, resetAfter:true });
    });

    document.getElementById('btnPdf').addEventListener('click', ()=>{
      const id = parseInt(document.getElementById('checklist_id').value || '0', 10);
      if(!id){ toast('Save first to generate an ID.'); return; }
      window.open(PDF_URL + '?id=' + encodeURIComponent(id), '_blank');
    });

    document.getElementById('mSave').addEventListener('click', ()=> document.getElementById('btnSave').click());
    document.getElementById('mLoad').addEventListener('click', ()=> document.getElementById('btnLoad').click());

    window.addEventListener('load', ()=>{
      applyProgress();
      syncStatusBadges();

      const id = parseInt(document.getElementById('checklist_id').value || '0', 10);
      if(id){ loadChecklist(); }

      const mobileBar = document.getElementById('mobileBar');
      let last = 0;
      window.addEventListener('scroll', ()=>{
        const y = window.scrollY || 0;
        if(y > 140 && y > last){ mobileBar.style.opacity = '1'; }
        last = y;
      }, { passive:true });
    });
  </script>
</body>
</html>