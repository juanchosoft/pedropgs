<?php
require './admin/include/generic_classes.php';
require_once './admin/classes/Unidades.php';
$view = SessionData::getPermission(21);
$create = SessionData::getPermission(17);
$edit = SessionData::getPermission(20);
$delete = SessionData::getPermission(18);
$enable = SessionData::getPermission(19);
if (!$view) { require 'permiso_denegado.php'; exit; }
$modulo = 'Check List';
$userType = SessionData::getUserType();
$userUnidades = array_map('intval', (array)SessionData::getUnidadesUser());
$optionUnidades = '<option value="seleccione">Select a property...</option>';
try {
  $resUnidades = Unidades::getAll(null);
  $unidades = !empty($resUnidades['output']['valid']) ? (array)($resUnidades['output']['response'] ?? []) : [];
  foreach ($unidades as $u) {
    if (!is_array($u)) continue;
    $uid=(int)($u['id']??0);
    if ($uid < 1) continue;
    if ($userType != Util::SuperAdmin() && !(($userType == Util::Manager() || $userType == Util::Staff()) && in_array($uid,$userUnidades,true))) continue;
    $label=htmlspecialchars((string)($u['nombre']??''),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $optionUnidades .= '<option value="'.$uid.'">'.$label.'</option>';
  }
} catch (Throwable $e) { error_log('PGS checklist units: '.$e->getMessage()); }
?>
<!doctype html><html lang="en"><head>
<?php include './admin/include/generic_head.php'; ?>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<style>
:root{--ck-red:#cf1730;--ck-ink:#172032;--ck-muted:#657286;--ck-line:#dce3eb;--ck-bg:#f3f5f8}
#preloader{display:none!important}
#main-wrapper{position:relative!important}
#main-wrapper>.nav-header.nav-header--saas{position:fixed!important;top:0!important;left:0!important;margin:0!important;transform:none!important;z-index:1300!important}
.content-body{background:var(--ck-bg)!important;min-height:100vh}
.content-body .container-fluid{max-width:1420px;padding:18px 18px 90px}
.ck-hero{background:#202a39;color:white;border-radius:18px;padding:20px 22px;margin-bottom:16px}
.ck-hero h1{font-size:clamp(20px,2.8vw,27px);line-height:1.2;font-weight:850;color:#fff;margin:0 0 5px}
.ck-hero p{margin:0;color:#e5e9f1;font-size:14px}
.ck-top{background:#fff;border:1px solid var(--ck-line);border-radius:18px;padding:18px;margin-bottom:14px}
.ck-top label{font-weight:800;color:var(--ck-ink);margin-bottom:8px;display:block}
.ck-top select{width:100%;height:52px;border:1px solid #cbd5e1;border-radius:12px;padding:0 12px;background:white;color:var(--ck-ink);font-size:16px}
.ck-progress{background:#fff;border:1px solid var(--ck-line);border-radius:18px;padding:16px 18px;margin-bottom:14px;position:sticky;top:8px;z-index:8;box-shadow:0 5px 18px #1e293b10}
.ck-progress-head{display:flex;align-items:center;justify-content:space-between;gap:12px;font-weight:800;color:var(--ck-ink);font-size:14px}
.ck-track{height:9px;background:#e8edf3;border-radius:999px;overflow:hidden;margin-top:11px}.ck-bar{width:0;height:100%;background:var(--ck-red);transition:width .18s}
.ck-filterbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:12px}
.ck-filter{border:1px solid #d3dce7;background:#fff;border-radius:999px;color:#344054;font-weight:750;padding:7px 13px;min-height:38px}
.ck-filter.active{color:#fff;background:#1c2636;border-color:#1c2636}
.ck-sections{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;align-items:start}
.ck-section{border:1px solid var(--ck-line);border-radius:17px;background:#fff;overflow:hidden;min-width:0;box-shadow:0 4px 14px #1018280b}
.ck-section>summary{cursor:pointer;list-style:none;display:flex;justify-content:space-between;align-items:center;gap:12px;padding:16px;background:#fff;border-bottom:1px solid var(--ck-line);color:var(--ck-ink)}
.ck-section>summary::-webkit-details-marker{display:none}
.ck-section-name{font-weight:850;font-size:16px;line-height:1.25}.ck-section-count{font-size:12px;color:#64748b;background:#f0f3f8;border-radius:20px;padding:6px 9px;white-space:nowrap}
.ck-section>summary:after{content:'⌄';font-size:22px;color:var(--ck-red);line-height:1}.ck-section:not([open])>summary:after{content:'›'}
.ck-question{display:grid;grid-template-columns:minmax(0,1fr) 130px;align-items:center;gap:12px;padding:13px 16px;border-bottom:1px solid #edf1f5;min-width:0}
.ck-question:last-child{border-bottom:0}.ck-question label{font-weight:700;font-size:14px;line-height:1.35;color:#1e293b;margin:0}
.ck-question select{font-size:14px;border:1px solid #cbd5e1;border-radius:11px;background:#fff;color:#25334b;padding:9px 10px;min-height:45px;width:100%;font-weight:750}
.ck-question select[data-answer="OK"]{background:#ecfdf3;border-color:#a9e4c1;color:#116533}.ck-question select[data-answer="Not OK"]{background:#fff1f2;border-color:#f5b3bd;color:#aa1731}
.ck-question select:focus-visible,.ck-top select:focus-visible,.ck-filter:focus-visible{outline:3px solid #f9bac5;outline-offset:1px}
.ck-footer{margin-top:16px;border:1px solid var(--ck-line);border-radius:18px;background:#fff;padding:18px}
.ck-footer label{font-weight:800;color:var(--ck-ink);display:block;margin-bottom:8px}.ck-footer textarea{width:100%;min-height:110px;border:1px solid #cbd5e1;border-radius:12px;padding:13px;resize:vertical;font-size:16px;line-height:1.5}
.ck-footer-actions{display:flex;justify-content:flex-end;align-items:center;gap:12px;margin-top:14px}.ck-save{border:0;background:var(--ck-red);border-radius:12px;padding:13px 22px;min-height:50px;color:#fff;font-weight:800;font-size:15px;cursor:pointer}.ck-save:focus-visible{outline:3px solid #fb9eac}
.ck-note{font-size:12px;color:#667085}.ck-empty{padding:20px;background:#fff;border-radius:14px;text-align:center;color:#667085}
/* This page uses the shared header; avoid making it participate in document flow. */
#main-wrapper>.nav-header.nav-header--saas{position:fixed!important;top:0!important;left:0!important;z-index:1300!important}
#main-wrapper>.header.header--saas{position:fixed!important;top:0!important;right:0!important;left:17.1875rem!important;margin:0!important;transform:none!important;z-index:1100!important;min-height:76px!important}
#main-wrapper>.content-body{margin-top:0!important;padding-top:85px!important;transform:none!important;min-height:100vh!important}
@media(min-width:992px){
  #main-wrapper>.content-body{padding-top:85px!important}
  #pgsMobileMenuButton{display:none!important}
}
@media(max-width:991.98px){
 html,body{max-width:100%;overflow-x:hidden}
 #main-wrapper>.nav-header.nav-header--saas{width:132px!important;height:70px!important;min-height:70px!important}
 /* Hide duplicate desktop toolbar on phones; the brand and mobile menu remain accessible. */
 #main-wrapper>.header.header--saas{display:none!important}
 #main-wrapper .nav-control.nav-control--saas{display:none!important}
 #main-wrapper>.content-body{margin:0!important;padding:82px 0 0!important;left:0!important;width:100%!important;max-width:100%!important}
 #main-wrapper .content-body>.container-fluid{max-width:100%!important;width:100%!important;padding:12px 11px 90px!important;box-sizing:border-box!important}
 #pgsMobileMenuButton{display:flex!important;position:fixed!important;top:11px!important;left:140px!important;width:47px!important;height:46px!important;align-items:center!important;justify-content:center!important;background:#15171d!important;color:#fff!important;border:1px solid #555663!important;border-radius:12px!important;z-index:2147483000!important;cursor:pointer}
 #main-wrapper .deznav#pgsMainNavigation{position:fixed!important;display:block!important;top:70px!important;bottom:0!important;left:0!important;width:min(310px,88vw)!important;height:calc(100dvh - 70px)!important;overflow-y:auto!important;visibility:visible!important;opacity:1!important;transform:translateX(-110%)!important;transition:transform .2s!important;z-index:2147482000!important;pointer-events:none!important}
 body.pgs-nav-open #main-wrapper .deznav#pgsMainNavigation{transform:translateX(0)!important;pointer-events:auto!important}
 #pgsMobileBackdrop{position:fixed!important;inset:70px 0 0!important;background:#07101dbb!important;z-index:2147481500!important;display:none!important}
 body.pgs-nav-open #pgsMobileBackdrop{display:block!important}
 .ck-sections{grid-template-columns:1fr}
}
@media(max-width:575.98px){.ck-hero{padding:16px;border-radius:15px}.ck-top{padding:14px}.ck-progress{top:5px;padding:13px}.ck-question{grid-template-columns:minmax(0,1fr) 112px;gap:9px;padding:12px}.ck-question label{font-size:13px}.ck-question select{font-size:13px;padding:8px 6px}.ck-section>summary{padding:14px}.ck-footer-actions{display:block}.ck-save{width:100%}}
</style></head><body>
<div id="main-wrapper">
<?php include './admin/include/generic_header.php'; ?>
<div class="deznav"><div class="deznav-scroll"><?php include './admin/include/generic_navbar.php'; ?></div></div>
<button type="button" id="pgsMobileMenuButton" aria-label="Open navigation menu" aria-controls="pgsMainNavigation" aria-expanded="false" style="display:none"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
<div id="pgsMobileBackdrop"></div>
<div class="content-body"><div class="container-fluid">
<header class="ck-hero"><h1>Checklist Inspection</h1><p>Inspect each area, mark its condition and submit your results.</p></header>
<form action="guardar.php" method="post" id="ckForm" novalidate>
<section class="ck-top"><label for="tbl_unidad_id">HOA Designated (Name) *</label><select id="tbl_unidad_id" name="tbl_unidad_id" required><?= $optionUnidades ?></select></section>
<section class="ck-progress" aria-label="Inspection progress"><div class="ck-progress-head"><span>Inspection progress</span><span id="ckProgressText">0 / 24 completed</span></div><div class="ck-track"><div class="ck-bar" id="ckProgressBar"></div></div><div class="ck-filterbar"><button type="button" class="ck-filter active" data-filter="all">All questions</button><button type="button" class="ck-filter" data-filter="pending">Unanswered</button><button type="button" class="ck-filter" data-filter="notok">Not OK</button></div></section>
<div class="ck-sections" id="ckSections">
<details class="ck-section" open><summary><span class="ck-section-name">Individual Floors</span><span class="ck-section-count" data-count>0 / 10</span></summary><div class="ck-questions">
<div class="ck-question" data-question><label for="overall_appearance">Overall appearance</label><select name="overall_appearance" id="overall_appearance" data-check-answer><option value="" selected>Select</option><option value="OK">OK</option><option value="Not OK">Not OK</option></select></div>
<div class="ck-question" data-question><label for="condition_walls">Condition of walls</label><select name="condition_walls" id="condition_walls" data-check-answer><option value="" selected>Select</option><option value="OK">OK</option><option value="Not OK">Not OK</option></select></div>
<div class="ck-question" data-question><label for="condition_paint">Condition of paint</label><select name="condition_paint" id="condition_paint" data-check-answer><option value="" selected>Select</option><option value="OK">OK</option><option value="Not OK">Not OK</option></select></div>
<div class="ck-question" data-question><label for="wall_lights">Wall lights working</label><select name="wall_lights" id="wall_lights" data-check-answer><option value="" selected>Select</option><option value="OK">OK</option><option value="Not OK">Not OK</option></select></div>
<div class="ck-question" data-question><label for="ceiling_lights">Ceiling lights working</label><select name="ceiling_lights" id="ceiling_lights" data-check-answer><option value="" selected>Select</option><option value="OK">OK</option><option value="Not OK">Not OK</option></select></div>
<div class="ck-question" data-question><label for="carpet">Condition of carpet</label><select name="carpet" id="carpet" data-check-answer><option value="" selected>Select</option><option value="OK">OK</option><option value="Not OK">Not OK</option></select></div>
<div class="ck-question" data-question><label for="door_clean">Unit exterior door clean</label><select name="door_clean" id="door_clean" data-check-answer><option value="" selected>Select</option><option value="OK">OK</option><option value="Not OK">Not OK</option></select></div>
<div class="ck-question" data-question><label for="spot_lights">Door spot lights working</label><select name="spot_lights" id="spot_lights" data-check-answer><option value="" selected>Select</option><option value="OK">OK</option><option value="Not OK">Not OK</option></select></div>
<div class="ck-question" data-question><label for="lit">Exit sign lit</label><select name="lit" id="lit" data-check-answer><option value="" selected>Select</option><option value="OK">OK</option><option value="Not OK">Not OK</option></select></div>
<div class="ck-question" data-question><label for="extinguisher_charged">Fire extinguisher charged</label><select name="extinguisher_charged" id="extinguisher_charged" data-check-answer><option value="" selected>Select</option><option value="OK">OK</option><option value="Not OK">Not OK</option></select></div>
</div></details>
<details class="ck-section" open><summary><span class="ck-section-name">Trash Room Floor</span><span class="ck-section-count" data-count>0 / 5</span></summary><div class="ck-questions">
<div class="ck-question" data-question><label for="shute_door">Chute door opens/closes</label><select name="shute_door" id="shute_door" data-check-answer><option value="" selected>Select</option><option value="OK">OK</option><option value="Not OK">Not OK</option></select></div>
<div class="ck-question" data-question><label for="free_storage">Free of storage</label><select name="free_storage" id="free_storage" data-check-answer><option value="" selected>Select</option><option value="OK">OK</option><option value="Not OK">Not OK</option></select></div>
<div class="ck-question" data-question><label for="hazardous_materials">Free of hazardous materials</label><select name="hazardous_materials" id="hazardous_materials" data-check-answer><option value="" selected>Select</option><option value="OK">OK</option><option value="Not OK">Not OK</option></select></div>
<div class="ck-question" data-question><label for="debris">Chute free of debris</label><select name="debris" id="debris" data-check-answer><option value="" selected>Select</option><option value="OK">OK</option><option value="Not OK">Not OK</option></select></div>
<div class="ck-question" data-question><label for="inspection_visible1">Inspection sheet visible</label><select name="inspection_visible1" id="inspection_visible1" data-check-answer><option value="" selected>Select</option><option value="OK">OK</option><option value="Not OK">Not OK</option></select></div>
</div></details>
<details class="ck-section" open><summary><span class="ck-section-name">Maintenance Janitorial Room</span><span class="ck-section-count" data-count>0 / 7</span></summary><div class="ck-questions">
<div class="ck-question" data-question><label for="supplies_stored">Supplies properly stored</label><select name="supplies_stored" id="supplies_stored" data-check-answer><option value="" selected>Select</option><option value="OK">OK</option><option value="Not OK">Not OK</option></select></div>
<div class="ck-question" data-question><label for="chemical_labeled">Chemicals properly labeled</label><select name="chemical_labeled" id="chemical_labeled" data-check-answer><option value="" selected>Select</option><option value="OK">OK</option><option value="Not OK">Not OK</option></select></div>
<div class="ck-question" data-question><label for="paint_labeled">Paints properly labeled</label><select name="paint_labeled" id="paint_labeled" data-check-answer><option value="" selected>Select</option><option value="OK">OK</option><option value="Not OK">Not OK</option></select></div>
<div class="ck-question" data-question><label for="fire_charged">Fire extinguisher charged</label><select name="fire_charged" id="fire_charged" data-check-answer><option value="" selected>Select</option><option value="OK">OK</option><option value="Not OK">Not OK</option></select></div>
<div class="ck-question" data-question><label for="ladders_stored">Ladders properly stored</label><select name="ladders_stored" id="ladders_stored" data-check-answer><option value="" selected>Select</option><option value="OK">OK</option><option value="Not OK">Not OK</option></select></div>
<div class="ck-question" data-question><label for="debrisj">Area free of debris</label><select name="debrisj" id="debrisj" data-check-answer><option value="" selected>Select</option><option value="OK">OK</option><option value="Not OK">Not OK</option></select></div>
<div class="ck-question" data-question><label for="inventory_labeled">Inventory properly labeled</label><select name="inventory_labeled" id="inventory_labeled" data-check-answer><option value="" selected>Select</option><option value="OK">OK</option><option value="Not OK">Not OK</option></select></div>
</div></details>
<details class="ck-section" open><summary><span class="ck-section-name">Fire Control Panel Room</span><span class="ck-section-count" data-count>0 / 2</span></summary><div class="ck-questions">
<div class="ck-question" data-question><label for="equipment_tested">Equipment tested and working</label><select name="equipment_tested" id="equipment_tested" data-check-answer><option value="" selected>Select</option><option value="OK">OK</option><option value="Not OK">Not OK</option></select></div>
<div class="ck-question" data-question><label for="inspectionf">Inspection sheet visible</label><select name="inspectionf" id="inspectionf" data-check-answer><option value="" selected>Select</option><option value="OK">OK</option><option value="Not OK">Not OK</option></select></div>
</div></details>
</div>
<section class="ck-footer"><label for="observations">Observations</label><textarea placeholder="Write observations here..." name="observations" id="observations"></textarea><div class="ck-footer-actions"><span class="ck-note">Review your responses before submitting.</span><?php if ($edit): ?><button class="ck-save" type="button" id="ckSave" onclick="if(window.CHECK && typeof CHECK.validateData==='function'){CHECK.validateData()}else{document.getElementById('ckScriptError').hidden=false}">Save &amp; Send</button><?php endif; ?></div><p id="ckScriptError" role="alert" hidden style="color:#b42318">The checklist saving module did not load. Please refresh this page.</p></section>
</form></div></div></div>
<?php include './admin/include/gerenic_footer.php'; ?>
<?php include './admin/include/gerenic_script.php'; ?>
<!-- generic_search.php excluded: expects $arr and fatals on checklist -->
<script src="./admin/js/check.js"></script>
<script>
(function(){
  'use strict';
  var menu=document.querySelector('#main-wrapper .deznav'),toggle=document.getElementById('pgsMobileMenuButton'),backdrop=document.getElementById('pgsMobileBackdrop');
  if(menu&&toggle&&backdrop){
    menu.id='pgsMainNavigation';
    function close(){document.body.classList.remove('pgs-nav-open');toggle.setAttribute('aria-expanded','false')}
    toggle.addEventListener('click',function(e){e.stopPropagation();var open=!document.body.classList.contains('pgs-nav-open');document.body.classList.toggle('pgs-nav-open',open);toggle.setAttribute('aria-expanded',String(open))});
    backdrop.addEventListener('click',close);menu.addEventListener('click',function(e){if(e.target.closest('a'))close()});document.addEventListener('keydown',function(e){if(e.key==='Escape')close()});window.addEventListener('resize',function(){if(window.innerWidth>=992)close()});
  }
  var fields=Array.from(document.querySelectorAll('[data-check-answer]'));
  var questions=Array.from(document.querySelectorAll('[data-question]'));
  var sections=Array.from(document.querySelectorAll('.ck-section'));
  var filter='all';
  function refresh(){
    var done=fields.filter(function(s){return s.value==='OK'||s.value==='Not OK'}).length;
    var count=document.getElementById('ckProgressText'),bar=document.getElementById('ckProgressBar');
    if(count)count.textContent=done+' / '+fields.length+' completed';if(bar)bar.style.width=fields.length?Math.round(done/fields.length*100)+'%':'0%';
    fields.forEach(function(s){s.dataset.answer=s.value});
    sections.forEach(function(section){var children=Array.from(section.querySelectorAll('[data-question]')),answered=children.filter(function(q){return !!q.querySelector('select').value}).length;var status=section.querySelector('[data-count]');if(status)status.textContent=answered+' / '+children.length;var anyVisible=false;children.forEach(function(q){var v=q.querySelector('select').value;var visible=filter==='all'||(filter==='pending'?!v:v==='Not OK');q.hidden=!visible;if(visible)anyVisible=true});section.hidden=!anyVisible;if(filter!=='all'&&anyVisible)section.open=true});
  }
  document.addEventListener('change',function(e){if(e.target.matches('[data-check-answer]'))refresh()});
  document.querySelectorAll('[data-filter]').forEach(function(button){button.addEventListener('click',function(){filter=button.dataset.filter;document.querySelectorAll('[data-filter]').forEach(function(b){b.classList.toggle('active',b===button)});refresh()})});
  refresh();
})();
</script></body></html>
