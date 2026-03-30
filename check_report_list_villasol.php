<?php
require './admin/include/generic_classes.php';
include './admin/classes/Producto.php';

// ✅ IMPORTANT: generic_header.php uses $modulo
$modulo = 'List Report Villasol HOA';

// Permissions
$view   = SessionData::getPermission(21);
$create = SessionData::getPermission(17);
$edit   = SessionData::getPermission(20);
$delete = SessionData::getPermission(18);
$enable = SessionData::getPermission(19);

if (!$view) { require 'permiso_denegado.php'; exit; }

$userUnidad = SessionData::getUnidadUser();
date_default_timezone_set('America/Bogota');

$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

$ajaxList = $basePath . '/admin/ajax/checklist_list_ajax.php';
$pdfUrl   = $basePath . '/admin/ajax/checklist_pdf.php';

$checklistId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include './admin/include/generic_head.php'; ?>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>

  <style>
    /* ==========================================================
      PGS – Saved Checklists (SaaS Premium) | Red + Black
      UI ONLY — no backend changes
    ========================================================== */
    .checklist-saas{
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

      color:var(--text) !important;
    }
    .checklist-saas *{ box-sizing:border-box; }

    .card-saas{
      border-radius: var(--r24);
      background: linear-gradient(180deg, #fff, #fbfcfe);
      border: 1px solid var(--border);
      box-shadow: var(--shadow);
      overflow:hidden;
      margin-bottom:14px;
    }
    .card-head{
      padding: 14px 16px;
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:12px;
      flex-wrap:wrap;
      background:
        radial-gradient(900px 220px at 10% 0%, rgba(225,29,46,.16), transparent 60%),
        radial-gradient(700px 200px at 95% 10%, rgba(11,15,20,.14), transparent 60%),
        linear-gradient(180deg, #ffffff, #f8fafc);
      border-bottom: 1px solid var(--border);
    }
    .card-head h5{
      margin:0;
      font-weight: 1000;
      letter-spacing:-.25px;
      line-height: 1.1;
    }
    .sec-badge{
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
    .body{ padding: 14px 16px; }

    .topbar{
      display:flex;
      gap:10px;
      align-items:center;
      justify-content:space-between;
      flex-wrap:wrap;
      margin-bottom:12px;
    }

    .meta{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      align-items:center;
    }
    .small-muted{ color:var(--muted); font-weight:800; font-size:12px; }
    .small-muted b{ color: var(--text); }

    .search{
      flex: 1 1 340px;
      display:flex;
      gap:10px;
      align-items:center;
      padding: 10px 12px;
      border:1px solid var(--border);
      border-radius: 16px;
      background: rgba(255,255,255,.92);
      box-shadow: 0 8px 18px rgba(2,6,23,.06);
      min-width: min(520px, 100%);
    }
    .search .icon{
      width:34px; height:34px;
      border-radius: 12px;
      display:grid; place-items:center;
      background: rgba(225,29,46,.10);
      border: 1px solid rgba(225,29,46,.18);
      font-weight: 1000;
      color: var(--pgs-red2);
    }
    .search input{
      border:0 !important;
      outline:none !important;
      width:100%;
      background: transparent !important;
      font-weight: 850;
      color: var(--text) !important;
    }
    .search input::placeholder{ color:#94a3b8; }

    .filters{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      align-items:center;
      justify-content:flex-end;
    }

    .selectx{
      border:1px solid var(--border);
      background:#fff;
      border-radius: 14px;
      padding: 10px 12px;
      font-weight: 850;
      color: var(--text);
      outline:none;
      min-width: 180px;
      box-shadow: 0 6px 16px rgba(2,6,23,.06);
    }
    .selectx:focus{
      border-color: rgba(225,29,46,.55);
      box-shadow: 0 0 0 4px rgba(225,29,46,.14);
    }

    .saved-wrap{
      overflow:auto;
      border:1px solid rgba(2,6,23,.08);
      border-radius: 18px;
      background:#fff;
    }

    /* Table desktop */
    .saved-table{
      width:100%;
      min-width: 980px;
      border-collapse:collapse;
    }
    .saved-table th, .saved-table td{
      padding: 10px 12px;
      border-bottom: 1px solid rgba(2,6,23,.06);
      font-size: 13px;
      vertical-align: middle;
    }
    .saved-table thead th{
      background: #f8fafc;
      font-weight: 1000;
      position: sticky;
      top: 0;
      z-index: 1;
      color: #111827;
    }
    .saved-table tbody tr:hover{ background:#fbfdff; }

    .id-pill{
      display:inline-flex;
      align-items:center;
      gap:8px;
      font-weight: 1000;
    }
    .id-pill .hash{
      width:26px; height:26px;
      border-radius: 10px;
      display:grid; place-items:center;
      background: rgba(11,15,20,.06);
      border: 1px solid rgba(11,15,20,.10);
      color: #111827;
      font-size: 12px;
    }

    .badge-mini{
      display:inline-flex;
      padding: 4px 8px;
      border-radius: 999px;
      font-weight: 1000;
      font-size: 12px;
      border:1px solid rgba(2,6,23,.10);
      background:#fff;
      white-space: nowrap;
    }
    .badge-mini.final{
      border-color: rgba(22,163,74,.25);
      background: rgba(22,163,74,.10);
      color: rgba(22,163,74,.95);
    }
    .badge-mini.draft{
      border-color: rgba(225,29,46,.22);
      background: rgba(225,29,46,.08);
      color: rgba(179,18,30,.95);
    }

    .progressx{
      display:flex;
      align-items:center;
      gap:10px;
      min-width: 120px;
    }
    .bar{
      flex:1;
      height: 10px;
      border-radius: 999px;
      background: #eef2f7;
      border:1px solid rgba(2,6,23,.08);
      overflow:hidden;
    }
    .bar > span{
      display:block;
      height:100%;
      width: 0%;
      background: linear-gradient(90deg, var(--pgs-red), var(--pgs-red2));
    }
    .pct{
      font-weight: 1000;
      font-size: 12px;
      color: #111827;
      white-space: nowrap;
    }

    .btnx{
      border:1px solid rgba(2,6,23,.12);
      background:#fff;
      padding:8px 10px;
      border-radius: 12px;
      font-weight:1000;
      font-size:12px;
      cursor:pointer;
      transition: transform .12s ease, box-shadow .12s ease, border-color .12s ease;
      white-space: nowrap;
      box-shadow: 0 6px 14px rgba(2,6,23,.06);
    }
    .btnx:hover{
      transform: translateY(-1px);
      border-color: rgba(225,29,46,.30);
      box-shadow: 0 10px 18px rgba(2,6,23,.10);
    }
    .btnx.pdf{
      border:none;
      background: linear-gradient(135deg, var(--pgs-red), var(--pgs-red2));
      color:#fff;
      box-shadow: 0 14px 32px rgba(225,29,46,.22);
    }
    .btnx.pdf:hover{ box-shadow: 0 18px 38px rgba(225,29,46,.28); }

    .btnx.black{
      border:none;
      background: linear-gradient(180deg, rgba(17,24,39,1), rgba(11,15,20,1));
      color:#fff;
      box-shadow: 0 14px 32px rgba(2,6,23,.20);
    }

    .actions-nowrap{ white-space:nowrap; text-align:right; }

    /* Mobile: convert rows into cards (no ugly horizontal scroll) */
    @media (max-width: 820px){
      .saved-wrap{ overflow: visible; border: 0; background: transparent; }
      .saved-table{ min-width: 0; }
      .saved-table thead{ display:none; }
      .saved-table, .saved-table tbody, .saved-table tr, .saved-table td{ display:block; width:100%; }
      .saved-table tr{
        background:#fff;
        border:1px solid rgba(2,6,23,.10);
        border-radius: 18px;
        box-shadow: 0 12px 30px rgba(2,6,23,.08);
        padding: 12px;
        margin-bottom: 12px;
      }
      .saved-table td{
        border:0;
        padding: 8px 0;
      }
      .saved-table td[data-label]{
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:12px;
      }
      .saved-table td[data-label]::before{
        content: attr(data-label);
        color: var(--muted);
        font-weight: 900;
        font-size: 12px;
        padding-top: 2px;
        max-width: 38%;
      }
      .actions-nowrap{
        text-align:left;
        padding-top: 10px !important;
      }
      .actions-nowrap .btnx{ width: 100%; justify-content:center; }
      .actions-nowrap .btnrow{
        display:grid;
        grid-template-columns: 1fr;
        gap:10px;
      }
      .search{ min-width: 100%; }
      .selectx{ width: 100%; min-width: 0; }
    }

    .hint{
      margin-top: 10px;
      padding: 10px 12px;
      border-radius: 16px;
      background: rgba(11,15,20,.04);
      border: 1px solid rgba(2,6,23,.08);
      font-weight: 800;
      color: #334155;
    }
  </style>
</head>

<body>
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
        <div class="checklist-saas">

          <!-- Saved Checklists -->
          <div class="card-saas">
            <div class="card-head">
              <div>
                <h5>Saved Checklists</h5>
                <div class="small-muted" style="margin-top:6px;">
                  Manage saved inspections and print evidence PDFs.
                </div>
              </div>
              <span class="sec-badge"><span id="savedCount">0</span> records</span>
            </div>

            <div class="body">

              <div class="topbar">
                <div class="meta">
                  <div class="small-muted">
                    Unit: <b><?php echo htmlspecialchars((string)$userUnidad); ?></b>
                  </div>
                  <div class="small-muted">
                    Data source: <span id="dataSource">-</span>
                  </div>
                </div>

                <div class="filters">
                  <div class="search">
                    <div class="icon">⌕</div>
                    <input id="qSaved" type="text" placeholder="Search by ID, user, date, status...">
                  </div>

                  <select id="fStatus" class="selectx">
                    <option value="all">All statuses</option>
                    <option value="draft">Draft</option>
                    <option value="final">Finalized</option>
                  </select>

                  <button type="button" class="btnx black" id="btnRefresh">Refresh</button>
                </div>
              </div>

              <div class="saved-wrap">
                <table class="saved-table">
                  <thead>
                    <tr>
                      <th style="width:130px;">CHECKLIST ID</th>
                      <th style="width:260px;">USER</th>
                      <th style="width:180px;">DATE</th>
                      <th style="width:130px;">STATUS</th>
                      <th style="width:170px;">PROGRESS</th>
                      <th class="actions-nowrap" style="width:260px;">ACTIONS</th>
                    </tr>
                  </thead>
                  <tbody id="tblSavedBody">
                    <tr><td colspan="6" class="small-muted">Loading...</td></tr>
                  </tbody>
                </table>
              </div>

              <div class="hint">
                • <b>View</b> opens the selected checklist in your checklist page. • <b>Print PDF</b> opens the PDF with photos.
              </div>
            </div>
          </div>

          <!-- ✅ Under this block you can render your checklist UI (no changes needed) -->

        </div>
      </div>
    </div>

    <?php include './admin/include/gerenic_footer.php'; ?>
  </div>

  <?php include './admin/include/gerenic_script.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script>
    const AJAX_LIST = <?php echo json_encode($ajaxList); ?>;
    const PDF_URL   = <?php echo json_encode($pdfUrl); ?>;

    function escapeHtml(s){
      return String(s ?? '')
        .replaceAll('&','&amp;')
        .replaceAll('<','&lt;')
        .replaceAll('>','&gt;')
        .replaceAll('"','&quot;')
        .replaceAll("'","&#039;");
    }

    function normalizeStatus(raw){
      raw = String(raw ?? '').toLowerCase().trim();
      // supports: finalizado/borrador or Finalized/Draft
      if (raw === 'finalizado' || raw === 'finalized' || raw === 'final') return { key:'final', label:'Finalized' };
      return { key:'draft', label:'Draft' };
    }

    async function fetchJson(url){
      const res = await fetch(url, { cache: 'no-store' });
      const txt = await res.text();
      try { return JSON.parse(txt); }
      catch(e){
        console.error("NON-JSON:", txt);
        return { ok:false, msg:"Backend did not return JSON." };
      }
    }

    let __allRows = [];

    function rowMatchesFilters(r){
      const q = (document.getElementById('qSaved')?.value || '').trim().toLowerCase();
      const fs = (document.getElementById('fStatus')?.value || 'all');

      const id = String(r.checklist_id || '');
      const user = String(r.user_name || r.user || '');
      const date = String(r.fecha || r.date || '');
      const st = normalizeStatus(r.estado).key;
      const pr = String(r.progress ?? '');

      const hay = (id + ' ' + user + ' ' + date + ' ' + st + ' ' + pr).toLowerCase();

      const qOk = !q || hay.includes(q);
      const sOk = (fs === 'all') || (fs === st);

      return qOk && sOk;
    }

    function renderSavedTable(rows){
      const body = document.getElementById('tblSavedBody');
      const countEl = document.getElementById('savedCount');

      countEl.textContent = String(rows.length);

      if(!rows.length){
        body.innerHTML = `<tr><td colspan="6" class="small-muted">No saved checklists found.</td></tr>`;
        return;
      }

      body.innerHTML = rows.map(r=>{
        const id = parseInt(r.checklist_id || 0, 10);
        const u  = r.user_name || r.user || 'User';
        const dt = r.fecha || r.date || '-';
        const st = normalizeStatus(r.estado);
        const pct = Math.max(0, Math.min(100, parseInt(r.progress || 0, 10) || 0));

        return `
          <tr>
            <td data-label="Checklist ID">
              <span class="id-pill"><span class="hash">#</span> <span><b>${id}</b></span></span>
            </td>

            <td data-label="User">${escapeHtml(u)}</td>

            <td data-label="Date">${escapeHtml(dt)}</td>

            <td data-label="Status">
              <span class="badge-mini ${st.key === 'final' ? 'final' : 'draft'}">${escapeHtml(st.label)}</span>
            </td>

            <td data-label="Progress">
              <div class="progressx">
                <div class="bar"><span style="width:${pct}%;"></span></div>
                <div class="pct">${pct}%</div>
              </div>
            </td>

            <td class="actions-nowrap" data-label="Actions">
              <div class="btnrow" style="display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap;">
                <button class="btnx" data-action="view" data-id="${id}">View</button>
                <button class="btnx pdf" data-action="pdf" data-id="${id}">Print PDF</button>
              </div>
            </td>
          </tr>
        `;
      }).join('');
    }

    function applyFilters(){
      const rows = (__allRows || []).filter(rowMatchesFilters);
      renderSavedTable(rows);
    }

    async function loadSavedList(){
      const data = await fetchJson(AJAX_LIST);

      if(!data.ok){
        document.getElementById('tblSavedBody').innerHTML =
          `<tr><td colspan="6" class="small-muted">Error loading list: ${escapeHtml(data.msg || 'Unknown')}</td></tr>`;
        return;
      }

      document.getElementById('dataSource').textContent = data.source || '-';

      __allRows = data.rows || [];
      applyFilters();

      if (data.hint) console.warn("HINT:", data.hint);
    }

    document.addEventListener('click', (e)=>{
      const btn = e.target.closest('button[data-action]');
      if(!btn) return;

      const action = btn.getAttribute('data-action');
      const id = parseInt(btn.getAttribute('data-id') || '0', 10);
      if(!id) return;

      if(action === 'pdf'){
        window.open(PDF_URL + '?id=' + encodeURIComponent(id), '_blank');
        return;
      }

      if(action === 'view'){
        // ✅ safest: open your checklist page with id (no dependency on other JS)
        // If you already have a specific file name, change "check_list.php" accordingly.
        // This is UI navigation only, backend unchanged.
        window.location.href = 'check_list.php?id=' + encodeURIComponent(id);
      }
    });

    document.getElementById('qSaved')?.addEventListener('input', applyFilters);
    document.getElementById('fStatus')?.addEventListener('change', applyFilters);
    document.getElementById('btnRefresh')?.addEventListener('click', loadSavedList);

    window.addEventListener('load', loadSavedList);
  </script>
</body>
</html>