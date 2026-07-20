<?php
require './admin/include/generic_classes.php';
include './admin/classes/Usuario.php';
include './admin/classes/Unidades.php';

// Permissions
$view    = SessionData::getPermission(1);
$create  = SessionData::getPermission(2);
$edit    = SessionData::getPermission(3);
$delete  = SessionData::getPermission(4);
$enable  = SessionData::getPermission(5);
$permits = SessionData::getPermission(6);

// Validation
if (!$view) { require 'permiso_denegado.php'; }

// HOA Units options
$arrUnidades = Unidades::getAll(null);
$isvalidUni  = $arrUnidades['output']['valid'];
$arrUnidades = $arrUnidades['output']['response'];

$optionUnidades = '';
foreach ($arrUnidades as $val) {
  $optionUnidades .= "<option value='" . $val['id'] . "'>" . $val['nombre'] . "</option>";
}

$arr = Usuario::getAll(null);
$isvalid = $arr['output']['valid'];
$arr = $arr['output']['response'];


/* ==========================================================
   Rest days by Employee ID
   This allows the edit modal to remember if the user is an employee
   and show the remaining rest days registered in tec_employee.
========================================================== */
$employeeRestDaysByEmployeeId = array();

if ($isvalid && is_array($arr)) {
  $employeeIds = array();

  foreach ($arr as $u) {
    $empIdTmp = isset($u['employee_id']) ? trim((string)$u['employee_id']) : '';
    if ($empIdTmp !== '') {
      $employeeIds[$empIdTmp] = $empIdTmp;
    }
  }

  if (count($employeeIds) > 0) {
    try {
      $dbEmp = new DbConection();
      $pdoEmp = $dbEmp->openConect();

      $placeholders = array();
      $params = array();
      $idx = 0;

      foreach ($employeeIds as $empId) {
        $key = ':emp_' . $idx;
        $placeholders[] = $key;
        $params[$key] = $empId;
        $idx++;
      }

      $qEmp = "SELECT employee_id, dias_descanso
               FROM " . $dbEmp->getTable('tec_employee') . "
               WHERE employee_id IN (" . implode(',', $placeholders) . ")";

      $stmtEmp = $pdoEmp->prepare($qEmp);
      $stmtEmp->execute($params);

      while ($rowEmp = $stmtEmp->fetch(PDO::FETCH_ASSOC)) {
        $employeeRestDaysByEmployeeId[trim((string)$rowEmp['employee_id'])] = isset($rowEmp['dias_descanso']) ? $rowEmp['dias_descanso'] : '';
      }

      $dbEmp->closeConect();
    } catch (Exception $e) {
      $employeeRestDaysByEmployeeId = array();
    }
  }
}

$totalUsuarios = 0;
$totalActivos = 0;
$totalInactivos = 0;
$totalManagers = 0;

if ($isvalid && is_array($arr)) {
  foreach ($arr as $u) {
    if (($u["tipo"] ?? "") !== "SuperAdministrador") {
      $totalUsuarios++;
      $hab = strtolower(trim((string)($u["habilitado"] ?? "")));
      if ($hab === "yes") {
        $totalActivos++;
      } else {
        $totalInactivos++;
      }

      if (($u["tipo"] ?? "") === "Administrador") {
        $totalManagers++;
      }
    }
  }
}

$modulo = 'Users';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include './admin/include/generic_head.php'; ?>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>

  <style>

    /* ==========================================================
       PGS CENTRUM – Users | SaaS Premium WOW UI
       SOLO DISEÑO: no cambia ids, names, JS, backend ni permisos
    ========================================================== */
    :root{
      --pgs-brand:#E10600;
      --pgs-brand-dark:#B30500;
      --pgs-dark:#070B12;
      --pgs-ink:#111827;
      --pgs-muted:#667085;
      --pgs-bg:#F3F5F9;
      --pgs-card:#FFFFFF;
      --pgs-border:#E7EAF1;
      --pgs-border-soft:#F0F2F7;
      --pgs-success:#16A34A;
      --pgs-warning:#F59E0B;
      --pgs-info:#0284C7;
      --pgs-danger:#DC2626;
      --pgs-shadow:0 24px 70px rgba(2,6,23,.12);
      --pgs-shadow-soft:0 12px 30px rgba(2,6,23,.08);
      --pgs-radius:26px;
      --pgs-radius-md:18px;
      --pgs-radius-sm:14px;
      --pgs-ring:0 0 0 4px rgba(225,6,0,.14);
    }

    body{
      background:
        radial-gradient(900px 420px at 15% 0%, rgba(225,6,0,.08), transparent 58%),
        radial-gradient(700px 360px at 92% 12%, rgba(15,23,42,.08), transparent 52%),
        linear-gradient(180deg, #F8FAFC 0%, #F3F5F9 100%) !important;
    }

    .pgs-ui{
      color:var(--pgs-ink) !important;
      position:relative;
    }

    .pgs-ui *{ box-sizing:border-box; }
    .pgs-ui a{ color:inherit; text-decoration:none; }

    .pgs-ui .page-wrap{
      padding:10px 0 24px;
      position:relative;
    }

    .pgs-ui .page-titles{
      margin-bottom:14px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
    }

    .pgs-ui .breadcrumb{
      background:transparent !important;
      padding:0 !important;
      margin:0 !important;
    }

    .pgs-ui .breadcrumb .breadcrumb-item,
    .pgs-ui .breadcrumb .breadcrumb-item a{
      font-weight:900;
      color:#64748B;
      letter-spacing:-.01em;
    }

    .pgs-ui .breadcrumb .breadcrumb-item.active a{
      color:#0F172A;
    }

    .pgs-ui .hero{
      position:relative;
      overflow:hidden;
      border-radius:var(--pgs-radius);
      padding:22px;
      margin-bottom:18px;
      color:#fff;
      border:1px solid rgba(255,255,255,.12);
      box-shadow:var(--pgs-shadow);
      background:
        radial-gradient(780px 260px at 12% -20%, rgba(225,6,0,.68), transparent 64%),
        radial-gradient(780px 260px at 88% 0%, rgba(255,255,255,.12), transparent 62%),
        linear-gradient(135deg, #080B12 0%, #111827 48%, #05070C 100%);
      isolation:isolate;
    }

    .pgs-ui .hero:before{
      content:"";
      position:absolute;
      inset:-2px;
      background:
        linear-gradient(120deg, transparent 0%, rgba(255,255,255,.08) 38%, transparent 70%),
        repeating-linear-gradient(90deg, rgba(255,255,255,.045) 0 1px, transparent 1px 72px);
      opacity:.78;
      pointer-events:none;
      z-index:-1;
    }

    .pgs-ui .hero:after{
      content:"";
      position:absolute;
      width:230px;
      height:230px;
      right:-80px;
      bottom:-110px;
      border-radius:999px;
      background:rgba(225,6,0,.35);
      filter:blur(8px);
      pointer-events:none;
      z-index:-1;
    }

    .pgs-ui .hero-top{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:18px;
      flex-wrap:wrap;
    }

    .pgs-ui .pill{
      display:inline-flex;
      align-items:center;
      gap:10px;
      padding:9px 13px;
      border-radius:999px;
      border:1px solid rgba(255,255,255,.16);
      background:rgba(255,255,255,.09);
      color:rgba(255,255,255,.92);
      font-weight:950;
      backdrop-filter:blur(12px);
      box-shadow:0 12px 30px rgba(0,0,0,.12);
    }

    .pgs-ui .pill .dot{
      width:10px;
      height:10px;
      border-radius:999px;
      background:linear-gradient(135deg, #FF433B, #FF9A8F);
      box-shadow:0 0 0 6px rgba(225,6,0,.18), 0 0 22px rgba(225,6,0,.8);
    }

    .pgs-ui .h-title{
      margin:12px 0 0;
      font-weight:1000;
      letter-spacing:-.055em;
      font-size:clamp(25px, 3.1vw, 42px);
      line-height:1;
      color:#fff;
    }

    .pgs-ui .h-sub{
      margin-top:10px;
      max-width:680px;
      color:rgba(255,255,255,.72);
      font-weight:750;
      font-size:14px;
      line-height:1.45;
    }

    .pgs-ui .hero-actions{
      display:flex;
      align-items:center;
      justify-content:flex-end;
      gap:10px;
      flex-wrap:wrap;
    }

    .pgs-ui .stats-grid{
      display:grid;
      grid-template-columns:repeat(4, minmax(140px, 1fr));
      gap:12px;
      margin-top:18px;
    }

    .pgs-ui .stat-card{
      position:relative;
      overflow:hidden;
      border-radius:20px;
      padding:15px;
      border:1px solid rgba(255,255,255,.12);
      background:rgba(255,255,255,.08);
      backdrop-filter:blur(14px);
      box-shadow:0 14px 32px rgba(0,0,0,.16);
    }

    .pgs-ui .stat-card:after{
      content:"";
      position:absolute;
      right:-28px;
      top:-28px;
      width:78px;
      height:78px;
      border-radius:999px;
      background:rgba(255,255,255,.08);
    }

    .pgs-ui .stat-label{
      display:block;
      color:rgba(255,255,255,.70);
      font-weight:900;
      font-size:11px;
      letter-spacing:.06em;
      text-transform:uppercase;
      margin-bottom:7px;
    }

    .pgs-ui .stat-value{
      display:block;
      color:#fff;
      font-weight:1000;
      font-size:27px;
      line-height:1;
      letter-spacing:-.04em;
    }

    .pgs-ui .stat-hint{
      display:block;
      color:rgba(255,255,255,.58);
      font-weight:750;
      font-size:12px;
      margin-top:7px;
    }

    .pgs-ui .btn-saas{
      display:inline-flex !important;
      align-items:center !important;
      justify-content:center !important;
      gap:8px !important;
      border:1px solid rgba(15,23,42,.10) !important;
      background:rgba(255,255,255,.96) !important;
      color:#111827 !important;
      padding:11px 15px !important;
      min-height:43px;
      border-radius:15px !important;
      font-weight:950 !important;
      letter-spacing:-.01em;
      box-shadow:0 12px 28px rgba(2,6,23,.10);
      transition:transform .16s ease, box-shadow .16s ease, filter .16s ease, border-color .16s ease;
      white-space:nowrap;
    }

    .pgs-ui .btn-saas:hover{
      transform:translateY(-2px);
      border-color:rgba(225,6,0,.25) !important;
      box-shadow:0 18px 34px rgba(2,6,23,.14);
      filter:brightness(1.02);
    }

    .pgs-ui .btn-brand{
      color:#fff !important;
      border:none !important;
      background:linear-gradient(135deg, var(--pgs-brand), var(--pgs-brand-dark)) !important;
      box-shadow:0 18px 38px rgba(225,6,0,.28);
    }

    .pgs-ui .btn-brand:hover{
      box-shadow:0 22px 46px rgba(225,6,0,.35);
    }

    .pgs-ui .card.pgs-card{
      border:1px solid rgba(226,232,240,.92) !important;
      border-radius:var(--pgs-radius) !important;
      box-shadow:var(--pgs-shadow) !important;
      overflow:hidden;
      background:rgba(255,255,255,.96) !important;
      backdrop-filter:blur(14px);
    }

    .pgs-ui .card-header{
      background:
        radial-gradient(580px 140px at 12% 0%, rgba(225,6,0,.08), transparent 55%),
        linear-gradient(180deg, #fff, #F8FAFC) !important;
      border-bottom:1px solid var(--pgs-border) !important;
      padding:18px 20px !important;
    }

    .pgs-ui .card-title{
      margin:0 !important;
      font-weight:1000 !important;
      letter-spacing:-.04em;
      color:#0F172A;
      font-size:20px;
    }

    .pgs-ui .card-body{
      padding:18px !important;
    }

    .pgs-ui .table-wrap{
      overflow:auto;
      border:1px solid var(--pgs-border-soft);
      border-radius:22px;
      background:#fff;
      box-shadow:inset 0 1px 0 rgba(255,255,255,.7);
    }

    .pgs-ui #dynamictable{
      width:100% !important;
      border-collapse:separate !important;
      border-spacing:0 !important;
      margin:0 !important;
    }

    .pgs-ui #dynamictable thead th{
      position:sticky;
      top:0;
      z-index:2;
      padding:14px 14px !important;
      background:#F8FAFC !important;
      color:#334155 !important;
      border-bottom:1px solid var(--pgs-border-soft) !important;
      font-size:11px !important;
      font-weight:1000 !important;
      letter-spacing:.07em;
      text-transform:uppercase;
      white-space:nowrap;
    }

    .pgs-ui #dynamictable tbody td{
      padding:14px 14px !important;
      border-bottom:1px solid #F1F5F9 !important;
      vertical-align:middle !important;
      color:#0F172A;
      font-size:13px;
      font-weight:760;
      white-space:nowrap;
    }

    .pgs-ui #dynamictable tbody tr{
      transition:background .16s ease, transform .16s ease;
    }

    .pgs-ui #dynamictable tbody tr:hover{
      background:linear-gradient(90deg, rgba(225,6,0,.035), rgba(15,23,42,.018)) !important;
    }

    .pgs-ui .avatar{
      width:54px;
      height:54px;
      display:grid;
      place-items:center;
      overflow:hidden;
      border-radius:17px;
      border:1px solid #E5E7EB;
      background:
        radial-gradient(circle at 30% 15%, #fff 0%, #F8FAFC 55%, #EEF2F7 100%);
      box-shadow:0 12px 24px rgba(2,6,23,.10);
    }

    .pgs-ui .avatar img{
      width:100%;
      height:100%;
      display:block;
      object-fit:cover;
    }

    .pgs-ui .avatar img.is-logo{
      width:62%;
      height:62%;
      object-fit:contain;
      filter:drop-shadow(0 6px 10px rgba(2,6,23,.12));
    }

    .pgs-ui .badge-pillx{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:7px;
      padding:7px 11px;
      border-radius:999px;
      font-size:12px;
      line-height:1;
      font-weight:1000;
      border:1px solid var(--pgs-border);
      background:#fff;
      box-shadow:0 8px 18px rgba(2,6,23,.04);
    }

    .pgs-ui .badge-pillx:before{
      content:"";
      width:7px;
      height:7px;
      border-radius:999px;
      background:currentColor;
      box-shadow:0 0 0 4px color-mix(in srgb, currentColor 12%, transparent);
    }

    .pgs-ui .badge-on{
      border-color:rgba(22,163,74,.22);
      background:rgba(22,163,74,.08);
      color:var(--pgs-success);
    }

    .pgs-ui .badge-off{
      border-color:rgba(220,38,38,.22);
      background:rgba(220,38,38,.08);
      color:var(--pgs-danger);
    }

    .pgs-ui .badge-role{
      border-color:rgba(15,23,42,.14);
      background:rgba(15,23,42,.045);
      color:#0F172A;
    }

    .pgs-ui .btn-outline-primary.btn-sm,
    .pgs-ui .btn-outline-danger.btn-sm,
    .pgs-ui .btn-outline-warning.btn-sm,
    .pgs-ui .btn-outline-info.btn-sm{
      width:34px;
      height:34px;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:0 !important;
      margin:2px !important;
      border-radius:12px !important;
      font-weight:950 !important;
      background:#fff !important;
      box-shadow:0 8px 18px rgba(2,6,23,.06);
      transition:transform .15s ease, box-shadow .15s ease, background .15s ease;
    }

    .pgs-ui .btn-outline-primary.btn-sm:hover,
    .pgs-ui .btn-outline-danger.btn-sm:hover,
    .pgs-ui .btn-outline-warning.btn-sm:hover,
    .pgs-ui .btn-outline-info.btn-sm:hover{
      transform:translateY(-2px);
      box-shadow:0 12px 22px rgba(2,6,23,.12);
    }

    .pgs-ui .btn-outline-primary.btn-sm{ border-color:rgba(37,99,235,.22) !important; color:#2563EB !important; }
    .pgs-ui .btn-outline-danger.btn-sm{ border-color:rgba(220,38,38,.22) !important; color:#DC2626 !important; }
    .pgs-ui .btn-outline-warning.btn-sm{ border-color:rgba(245,158,11,.30) !important; color:#B45309 !important; }
    .pgs-ui .btn-outline-info.btn-sm{ border-color:rgba(2,132,199,.24) !important; color:#0284C7 !important; }

    /* DataTables */
    .pgs-ui .dataTables_wrapper{
      color:#475569;
      font-weight:800;
    }

    .pgs-ui .dataTables_wrapper .dataTables_filter,
    .pgs-ui .dataTables_wrapper .dataTables_length{
      margin-bottom:12px;
    }

    .pgs-ui .dataTables_wrapper .dataTables_filter input,
    .pgs-ui .dataTables_wrapper .dataTables_length select{
      min-height:39px;
      border-radius:14px !important;
      border:1px solid var(--pgs-border) !important;
      background:#fff !important;
      color:#0F172A !important;
      padding:8px 11px !important;
      font-weight:900;
      outline:none !important;
      box-shadow:0 8px 18px rgba(2,6,23,.04);
    }

    .pgs-ui .dataTables_wrapper .dataTables_filter input:focus,
    .pgs-ui .dataTables_wrapper .dataTables_length select:focus{
      border-color:rgba(225,6,0,.45) !important;
      box-shadow:var(--pgs-ring) !important;
    }

    .pgs-ui .dataTables_wrapper .dataTables_paginate .paginate_button{
      border-radius:13px !important;
      margin:0 3px !important;
      border:1px solid var(--pgs-border) !important;
      background:#fff !important;
      font-weight:950;
      box-shadow:0 6px 14px rgba(2,6,23,.04);
    }

    .pgs-ui .dataTables_wrapper .dataTables_paginate .paginate_button.current,
    .pgs-ui .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover{
      color:#fff !important;
      border:none !important;
      background:linear-gradient(135deg, var(--pgs-brand), var(--pgs-brand-dark)) !important;
      box-shadow:0 14px 28px rgba(225,6,0,.20);
    }

    /* Modal WOW */
    .modal-backdrop.show{
      opacity:.72 !important;
      backdrop-filter:blur(8px);
    }

    .pgs-ui.modal-content{
      overflow:hidden;
      border:none !important;
      border-radius:28px !important;
      box-shadow:0 36px 100px rgba(2,6,23,.35) !important;
      background:#fff !important;
    }

    .pgs-ui .modal-header.card-header.card-header-danger{
      position:relative;
      overflow:hidden;
      align-items:flex-start;
      gap:14px;
      padding:22px 24px !important;
      border-bottom:1px solid rgba(255,255,255,.12) !important;
      color:#fff !important;
      background:
        radial-gradient(740px 260px at 12% -20%, rgba(225,6,0,.70), transparent 62%),
        radial-gradient(640px 240px at 90% 0%, rgba(255,255,255,.13), transparent 60%),
        linear-gradient(135deg, #080B12 0%, #111827 58%, #05070C 100%) !important;
    }

    .pgs-ui .modal-header.card-header.card-header-danger:after{
      content:"";
      position:absolute;
      inset:0;
      background:repeating-linear-gradient(90deg, rgba(255,255,255,.045) 0 1px, transparent 1px 65px);
      pointer-events:none;
    }

    .pgs-ui .modal-title{
      position:relative;
      z-index:2;
      margin:0 !important;
      color:#fff !important;
      font-weight:1000 !important;
      letter-spacing:-.045em;
      font-size:clamp(22px, 2.4vw, 31px);
      line-height:1;
    }

    .pgs-ui .modal-title:before{
      content:"";
      display:inline-block;
      width:10px;
      height:10px;
      margin-right:10px;
      border-radius:999px;
      background:#FF433B;
      box-shadow:0 0 0 6px rgba(225,6,0,.20), 0 0 22px rgba(225,6,0,.85);
      vertical-align:middle;
    }

    .pgs-ui .close{
      position:relative;
      z-index:3;
      width:42px;
      height:42px;
      display:flex;
      align-items:center;
      justify-content:center;
      margin:0 !important;
      padding:0 !important;
      border-radius:15px;
      opacity:1 !important;
      color:#fff !important;
      text-shadow:none !important;
      background:rgba(255,255,255,.11) !important;
      border:1px solid rgba(255,255,255,.14) !important;
      transition:transform .15s ease, background .15s ease;
    }

    .pgs-ui .close:hover{
      transform:rotate(6deg) scale(1.03);
      background:rgba(225,6,0,.42) !important;
    }

    .pgs-ui .modal-body{
      padding:22px 24px !important;
      background:
        radial-gradient(620px 220px at 8% 0%, rgba(225,6,0,.045), transparent 60%),
        linear-gradient(180deg, #fff 0%, #F8FAFC 100%);
    }

    .pgs-ui .validateTips{
      position:relative;
      margin:0 0 15px !important;
      padding:12px 14px 12px 42px;
      border-radius:17px;
      border:1px solid #E8EDF5;
      background:#fff;
      color:#64748B;
      font-size:13px;
      font-weight:850;
      box-shadow:0 12px 28px rgba(2,6,23,.05);
    }

    .pgs-ui .validateTips:before{
      content:"!";
      position:absolute;
      left:13px;
      top:50%;
      transform:translateY(-50%);
      width:20px;
      height:20px;
      display:grid;
      place-items:center;
      border-radius:999px;
      color:#fff;
      font-size:12px;
      font-weight:1000;
      background:linear-gradient(135deg, var(--pgs-brand), var(--pgs-brand-dark));
    }

    .pgs-ui .form-group{
      margin-bottom:16px !important;
    }

    .pgs-ui .form-group label{
      display:block;
      margin-bottom:7px;
      color:#111827;
      font-size:12px;
      font-weight:1000;
      letter-spacing:.035em;
      text-transform:uppercase;
    }

    .pgs-ui .errLbl{
      color:var(--pgs-brand);
      font-weight:1000;
    }

    .pgs-ui .form-control{
      width:100%;
      min-height:46px;
      padding:11px 13px !important;
      border-radius:16px !important;
      border:1px solid #DEE5EF !important;
      color:#0F172A !important;
      background:#fff !important;
      font-weight:850;
      box-shadow:0 10px 24px rgba(2,6,23,.045);
      transition:border-color .16s ease, box-shadow .16s ease, transform .16s ease;
    }

    .pgs-ui select.form-control{
      cursor:pointer;
    }

    .pgs-ui .form-control:hover{
      border-color:#CBD5E1 !important;
    }

    .pgs-ui .form-control:focus{
      transform:translateY(-1px);
      border-color:rgba(225,6,0,.52) !important;
      box-shadow:var(--pgs-ring), 0 14px 26px rgba(2,6,23,.07) !important;
      outline:none !important;
    }

    .pgs-ui iframe#ifm{
      width:100% !important;
      max-width:320px;
      height:72px !important;
      border-radius:16px;
      border:1px dashed #CBD5E1 !important;
      background:#fff;
      box-shadow:0 10px 22px rgba(2,6,23,.05);
    }

    .pgs-ui .modal-footer{
      display:flex;
      align-items:center;
      justify-content:flex-end;
      gap:10px;
      padding:16px 24px !important;
      border-top:1px solid var(--pgs-border) !important;
      background:linear-gradient(180deg, #fff, #F8FAFC) !important;
    }

    .pgs-ui .employee-switch-card{
      min-height:72px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:15px;
      padding:14px 15px;
      border:1px solid #E2E8F0;
      border-radius:20px;
      background:
        radial-gradient(180px 70px at 15% 0%, rgba(225,6,0,.07), transparent 68%),
        linear-gradient(135deg, rgba(255,255,255,.98), rgba(248,250,252,.98));
      box-shadow:0 14px 32px rgba(2,6,23,.07);
    }

    .pgs-ui .employee-switch-info{
      display:flex;
      flex-direction:column;
      gap:3px;
      min-width:0;
    }

    .pgs-ui .employee-switch-title{
      margin:0;
      color:#0F172A;
      font-size:12px;
      line-height:1.1;
      font-weight:1000;
      letter-spacing:.035em;
      text-transform:uppercase;
      cursor:pointer;
    }

    .pgs-ui .employee-switch-info small{
      color:#64748B;
      font-size:12px;
      line-height:1.25;
      font-weight:760;
    }

    .pgs-ui .employee-switch{
      display:inline-flex;
      align-items:center;
      gap:10px;
      margin:0;
      cursor:pointer;
      user-select:none;
      white-space:nowrap;
    }

    .pgs-ui .employee-switch input{
      display:none;
    }

    .pgs-ui .employee-slider{
      position:relative;
      display:inline-block;
      width:56px;
      height:31px;
      border-radius:999px;
      background:#CBD5E1;
      box-shadow:inset 0 2px 7px rgba(15,23,42,.20);
      transition:background .2s ease, box-shadow .2s ease;
    }

    .pgs-ui .employee-slider:before{
      content:"";
      position:absolute;
      width:25px;
      height:25px;
      left:3px;
      top:3px;
      border-radius:999px;
      background:#fff;
      box-shadow:0 7px 15px rgba(15,23,42,.24);
      transition:transform .2s ease;
    }

    .pgs-ui .employee-switch input:checked + .employee-slider{
      background:linear-gradient(135deg, var(--pgs-brand), var(--pgs-brand-dark));
      box-shadow:0 0 0 5px rgba(225,6,0,.12), inset 0 2px 7px rgba(0,0,0,.12);
    }

    .pgs-ui .employee-switch input:checked + .employee-slider:before{
      transform:translateX(25px);
    }

    .pgs-ui .employee-switch strong{
      min-width:26px;
      color:#0F172A;
      font-weight:1000;
    }

    .pgs-ui #employee_id_wrap{
      transition:opacity .2s ease, transform .2s ease;
    }


    .pgs-ui #rest_days_wrap{
      display:none;
      transition:opacity .2s ease, transform .2s ease;
    }

    .pgs-ui .rest-days-card{
      min-height:72px;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:14px;
      padding:14px 15px;
      border:1px solid rgba(22,163,74,.18);
      border-radius:20px;
      background:
        radial-gradient(180px 70px at 15% 0%, rgba(22,163,74,.10), transparent 68%),
        linear-gradient(135deg, #FFFFFF, #F8FAFC);
      box-shadow:0 14px 32px rgba(2,6,23,.07);
    }

    .pgs-ui .rest-days-icon{
      width:44px;
      height:44px;
      min-width:44px;
      display:flex;
      align-items:center;
      justify-content:center;
      border-radius:16px;
      color:#fff;
      background:linear-gradient(135deg, #16A34A, #15803D);
      box-shadow:0 12px 24px rgba(22,163,74,.22);
      font-size:18px;
    }

    .pgs-ui .rest-days-content{
      flex:1;
      min-width:0;
    }

    .pgs-ui .rest-days-content small{
      display:block;
      color:#64748B;
      font-size:11px;
      line-height:1.25;
      font-weight:850;
      letter-spacing:.035em;
      text-transform:uppercase;
    }

    .pgs-ui .rest-days-content strong{
      display:block;
      margin-top:2px;
      color:#0F172A;
      font-size:18px;
      line-height:1.15;
      font-weight:1000;
      letter-spacing:-.03em;
    }

    .pgs-ui .rest-days-content span{
      display:block;
      margin-top:2px;
      color:#667085;
      font-size:12px;
      line-height:1.25;
      font-weight:750;
    }

    /* Modal permisos */
    .pgs-ui #myModalPermisos .card,
    #myModalPermisos .pgs-ui .card{
      width:100%;
      border:1px solid #E2E8F0 !important;
      border-radius:22px !important;
      overflow:hidden;
      box-shadow:0 16px 38px rgba(2,6,23,.08) !important;
      background:#fff !important;
    }

    .pgs-ui #myModalPermisos .card-header.card-header-tabs.card-header.card-header-spider,
    #myModalPermisos .pgs-ui .card-header.card-header-tabs.card-header.card-header-spider{
      color:#fff !important;
      background:
        radial-gradient(400px 160px at 10% 0%, rgba(225,6,0,.35), transparent 62%),
        linear-gradient(135deg, #0B0F19, #111827) !important;
      border-bottom:1px solid rgba(255,255,255,.10) !important;
    }

    .pgs-ui #myModalPermisos .nav-tabs-title,
    #myModalPermisos .pgs-ui .nav-tabs-title{
      color:#fff;
      font-weight:1000;
      letter-spacing:-.02em;
    }

    #myModalPermisos .table th,
    #myModalPermisos .table td{
      vertical-align:middle !important;
      border-color:#F1F5F9 !important;
      font-weight:850;
    }

    /* Responsive PRO */
    @media (max-width: 991px){
      .pgs-ui .stats-grid{
        grid-template-columns:repeat(2, minmax(0, 1fr));
      }

      .pgs-ui .hero-actions{
        width:100%;
        justify-content:flex-start;
      }
    }

    @media (max-width: 768px){
      .content-body .container-fluid{
        padding-left:12px !important;
        padding-right:12px !important;
      }

      .pgs-ui .page-wrap{
        padding-top:4px;
      }

      .pgs-ui .hero{
        padding:18px;
        border-radius:22px;
      }

      .pgs-ui .h-sub{
        font-size:13px;
      }

      .pgs-ui .stats-grid{
        grid-template-columns:1fr 1fr;
        gap:10px;
      }

      .pgs-ui .stat-card{
        padding:13px;
        border-radius:18px;
      }

      .pgs-ui .stat-value{
        font-size:23px;
      }

      .pgs-ui .btn-saas{
        width:100%;
      }

      .pgs-ui .card-header,
      .pgs-ui .card-body{
        padding:15px !important;
      }

      .pgs-ui .table-wrap{
        border:none;
        overflow:visible;
        background:transparent;
      }

      .pgs-ui #dynamictable,
      .pgs-ui #dynamictable thead,
      .pgs-ui #dynamictable tbody,
      .pgs-ui #dynamictable th,
      .pgs-ui #dynamictable td,
      .pgs-ui #dynamictable tr{
        display:block;
      }

      .pgs-ui #dynamictable thead{
        display:none;
      }

      .pgs-ui #dynamictable tbody tr{
        position:relative;
        margin-bottom:13px;
        padding:14px;
        border:1px solid #E7EAF1;
        border-radius:22px;
        background:#fff;
        box-shadow:0 14px 32px rgba(2,6,23,.08);
      }

      .pgs-ui #dynamictable tbody td{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:14px;
        width:100%;
        min-height:38px;
        padding:9px 0 !important;
        white-space:normal !important;
        border-bottom:1px solid #F1F5F9 !important;
        text-align:right;
      }

      .pgs-ui #dynamictable tbody td:last-child{
        border-bottom:none !important;
        justify-content:flex-end;
        padding-top:12px !important;
      }

      .pgs-ui #dynamictable tbody td:before{
        content:attr(data-label);
        flex:0 0 38%;
        text-align:left;
        color:#64748B;
        font-size:11px;
        font-weight:1000;
        letter-spacing:.06em;
        text-transform:uppercase;
      }

      .pgs-ui #dynamictable tbody td:first-child{
        justify-content:center;
        padding-bottom:12px !important;
      }

      .pgs-ui #dynamictable tbody td:first-child:before{
        display:none;
      }

      .pgs-ui .avatar{
        width:68px;
        height:68px;
        border-radius:20px;
      }

      .pgs-ui .modal-dialog{
        margin:10px !important;
      }

      .pgs-ui.modal-content,
      .pgs-ui .modal-content{
        border-radius:24px !important;
      }

      .pgs-ui .modal-header.card-header.card-header-danger,
      .pgs-ui .modal-body,
      .pgs-ui .modal-footer{
        padding-left:16px !important;
        padding-right:16px !important;
      }

      .pgs-ui .employee-switch-card{
        align-items:flex-start;
        flex-direction:column;
      }

      .pgs-ui .employee-switch{
        width:100%;
        justify-content:space-between;
      }

      .pgs-ui .modal-footer{
        flex-direction:column-reverse;
      }

      .pgs-ui .modal-footer .btn{
        width:100%;
      }
    }

    @media (max-width: 430px){
      .pgs-ui .stats-grid{
        grid-template-columns:1fr;
      }

      .pgs-ui .hero{
        padding:16px;
      }

      .pgs-ui .pill{
        width:100%;
        justify-content:center;
      }

      .pgs-ui #dynamictable tbody td{
        align-items:flex-start;
        flex-direction:column;
        gap:5px;
        text-align:left;
      }

      .pgs-ui #dynamictable tbody td:last-child{
        flex-direction:row;
        flex-wrap:wrap;
        justify-content:center;
      }

      .pgs-ui #dynamictable tbody td:before{
        flex:auto;
      }
    }

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


  <div id="main-wrapper">
    <?php include './admin/include/generic_header.php'; ?>

    <div class="deznav">
      <div class="deznav-scroll">
        <?php include './admin/include/generic_navbar.php'; ?>
      </div>
    </div>

    <div class="content-body">
      <div class="container-fluid">
        <div class="pgs-ui">
          <div class="page-wrap">

            <div class="page-titles">
              <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="javascript:void(0)">Home</a></li>
                <li class="breadcrumb-item active"><a href="javascript:void(0)"><?php echo $modulo ?></a></li>
              </ol>
            </div>

            <!-- HERO -->
            <div class="hero">
              <div class="hero-top">
                <div>
                  <span class="pill"><span class="dot"></span> Access Control</span>
                  <h3 class="h-title">Users</h3>
                  <div class="h-sub">Manage accounts, enable/disable access, assign permissions and keep the platform organized with a clean professional control panel.</div>
                </div>

                <div class="hero-actions mt-2 mt-sm-0">
                  <?php if ($create) { ?>
                    <button class="btn btn-saas btn-brand" data-target="#myModal" data-toggle="modal" data-backdrop="static" data-keyboard="false">
                      <i class="fa fa-plus"></i> New User
                    </button>
                  <?php } ?>
                </div>
              </div>

              <div class="stats-grid">
                <div class="stat-card">
                  <span class="stat-label">Total users</span>
                  <span class="stat-value"><?php echo number_format($totalUsuarios); ?></span>
                  <span class="stat-hint">Registered accounts</span>
                </div>

                <div class="stat-card">
                  <span class="stat-label">Enabled</span>
                  <span class="stat-value"><?php echo number_format($totalActivos); ?></span>
                  <span class="stat-hint">Active access</span>
                </div>

                <div class="stat-card">
                  <span class="stat-label">Disabled</span>
                  <span class="stat-value"><?php echo number_format($totalInactivos); ?></span>
                  <span class="stat-hint">Blocked access</span>
                </div>

                <div class="stat-card">
                  <span class="stat-label">Managers</span>
                  <span class="stat-value"><?php echo number_format($totalManagers); ?></span>
                  <span class="stat-hint">Administrative role</span>
                </div>
              </div>
            </div>

            <!-- TABLE CARD -->
            <div class="row">
              <div class="col-12">
                <div class="card pgs-card">
                  <div class="card-header">
                    <h4 class="card-title">Users registered in the system</h4>
                  </div>

                  <div class="card-body">
                    <div class="table-wrap">
                      <table id="dynamictable" class="table table-hover table-responsive-sm mb-0">
                        <thead>
                          <tr>
                            <th style="width:90px;">Picture</th>
                            <th>User</th>
                            <th>Name</th>
                            <th>Last Name</th>
                            <th>Type</th>
                            <th>Enable</th>
                            <th style="width:190px;">Action</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php
                          $c = count($arr);
                          if ($isvalid) {
                            for ($i = 0; $i < $c; $i++) {
                              if ($arr[$i]["tipo"] !== "SuperAdministrador") {

                                $imgRaw = $arr[$i]["img"];
                                $hasImg = ($imgRaw !== null && $imgRaw !== "" && $imgRaw !== "no_image.png");

                                $img = $hasImg ? ("assets/img/admin/" . $imgRaw) : 'assets/img/logo-spiderP.png';
                          ?>
                                <tr id="prod<?php echo $arr[$i]['id'] ?>">
                                  <td class="text-primary" data-label="Picture">
                                    <div class="avatar">
                                      <img
                                        src="<?php echo $img; ?>"
                                        alt="User picture"
                                        <?php echo $hasImg ? '' : 'class="is-logo"'; ?>
                                      />
                                    </div>
                                  </td>
                                  <td class="text-primary" data-label="User"><b><?php echo $arr[$i]['nickname']; ?></b></td>
                                  <td class="text-primary" data-label="Name"><?php echo $arr[$i]['nombre']; ?></td>
                                  <td class="text-primary" data-label="Last Name"><?php echo $arr[$i]['apellido']; ?></td>
                                  <td data-label="Type">
                                    <span class="badge-pillx badge-role"><?php echo $arr[$i]['tipo']; ?></span>
                                  </td>
                                  <td data-label="Enable">
                                    <?php
                                      $en = strtolower(trim((string)$arr[$i]['habilitado']));
                                      $isOn = ($en === 'yes');
                                    ?>
                                    <span class="badge-pillx <?php echo $isOn ? 'badge-on' : 'badge-off'; ?>">
                                      <?php echo $isOn ? 'Enabled' : 'Disabled'; ?>
                                    </span>
                                  </td>
                                  <td data-label="Action" style="white-space:nowrap;">
                                    <?php if ($edit) { ?>
                                      <button type="button" class="btn btn-outline-primary btn-sm"
                                        onclick="USUARIO.editdata(<?php echo $arr[$i]['id']; ?>);" title="Edit">
                                        <i class="fa fa-pencil"></i>
                                      </button>
                                    <?php } ?>

                                    <?php if ($delete) { ?>
                                      <button type="button" class="btn btn-outline-danger btn-sm"
                                        onclick="USUARIO.deletedata(<?php echo $arr[$i]['id']; ?>);" title="Delete">
                                        <i class="fa fa-times"></i>
                                      </button>
                                    <?php } ?>

                                    <?php if ($enable) { ?>
                                      <button type="button" class="btn btn-outline-warning btn-sm"
                                        onclick="USUARIO.enabledata(<?php echo $arr[$i]['id']; ?>, '<?php echo $arr[$i]['habilitado']; ?>');" title="Enable/Disable">
                                        <i class="fa fa-unlock"></i>
                                      </button>
                                    <?php } ?>

                                    <?php /* Individual permission checkboxes removed — use Roles & Permissions */ ?>
                                    <?php if (false && $permits) { ?>
                                      <button type="button" class="btn btn-outline-info btn-sm"
                                        onclick="PERMISOS.editpermission(<?php echo $arr[$i]['id']; ?>);" title="Permits">
                                        <i class="fa fa-check"></i>
                                      </button>
                                    <?php } ?>
                                  </td>
                                </tr>
                          <?php
                              }
                            }
                          }
                          ?>
                        </tbody>
                      </table>
                    </div>
                  </div>

                </div>
              </div>
            </div>

          </div><!-- /page-wrap -->
        </div><!-- /pgs-ui -->
      </div>
    </div>
  </div>

  <!-- MODAL: Create/Edit User -->
  <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="lbcondiciones_rgpd" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content pgs-ui">
        <div class="modal-header card-header card-header-danger">
          <h4 class="modal-title">Create User</h4>
          <button type="button" onclick="UTIL.clearForm('formcreate');" class="close" data-dismiss="modal">&times;</button>
        </div>

        <div class="modal-body">
          <form id="formcreate" autocomplete="off">
            <input type="hidden" name="op" id="op" />
            <input type="hidden" name="id" id="id" />

            <div class="row">
              <div class="col-sm-12">
                <p class="validateTips"></p>
              </div>

              <div class="col-sm-6">
                <div class="form-group">
                  <label class="bmd-label-floating">Name <b class="errLbl">*</b></label>
                  <input type="text" class="form-control" id="nombre" name="nombre">
                </div>
              </div>

              <div class="col-sm-6">
                <div class="form-group employee-switch-card">
                  <div class="employee-switch-info">
                    <label for="es_empleado" class="employee-switch-title">Is employee?</label>
                    <small>Turn on to associate or create an employee for this user.</small>
                  </div>

                  <label class="employee-switch">
                    <input type="checkbox" id="es_empleado" name="es_empleado" value="1">
                    <span class="employee-slider"></span>
                    <strong id="es_empleado_text">No</strong>
                  </label>
                </div>
              </div>

              <input type="hidden" id="employee_id" name="employee_id" value="">
              <input type="hidden" id="emp_mode" name="emp_mode" value="">

              <div class="col-sm-12" id="emp_linked_wrap" style="display:none;">
                <div class="form-group" style="background:#f8fafc;border:1px solid #e7eaf1;border-radius:12px;padding:12px 14px;">
                  <label class="bmd-label-floating" style="margin:0;">Associated employee</label>
                  <div id="emp_linked_text" style="font-weight:900;color:#0B0F19;margin-top:4px;">—</div>
                  <small class="text-muted">This user is already linked to an employee. Search is hidden.</small>
                </div>
              </div>

              <div class="col-sm-12" id="emp_choice_wrap" style="display:none;">
                <div class="form-group" style="background:#fff;border:1px solid #e7eaf1;border-radius:12px;padding:12px 14px;">
                  <label class="bmd-label-floating">Employee association <b class="errLbl">*</b></label>
                  <div class="d-flex flex-wrap" style="gap:10px;margin-top:8px;">
                    <button type="button" class="btn btn-outline-dark btn-sm" id="emp_btn_existing" data-emp-choice="existing" style="font-weight:800;">
                      Associate existing
                    </button>
                    <button type="button" class="btn btn-outline-dark btn-sm" id="emp_btn_create" data-emp-choice="create" style="font-weight:800;">
                      Create new employee
                    </button>
                  </div>
                  <small class="text-muted d-block mt-2">Choose one option to continue.</small>
                </div>
              </div>

              <div class="col-sm-12" id="emp_select_wrap" style="display:none;">
                <div class="form-group">
                  <label class="bmd-label-floating">Search employee <b class="errLbl">*</b></label>
                  <select class="form-control" id="emp_select" name="emp_select" style="width:100%;">
                    <option value="">Select employee…</option>
                  </select>
                </div>
              </div>

              <div class="col-sm-12" id="emp_create_wrap" style="display:none;">
                <div style="border:1px solid #e7eaf1;border-radius:14px;padding:14px 14px 4px;margin-bottom:8px;background:#fbfcfe;">
                  <div style="font-weight:1000;margin-bottom:10px;color:#0B0F19;">New employee data</div>
                  <div class="row">
                    <div class="col-sm-4">
                      <div class="form-group">
                        <label class="bmd-label-floating">Hire date <b class="errLbl">*</b></label>
                        <input type="date" class="form-control" id="emp_fecha_ingreso" name="emp_fecha_ingreso">
                      </div>
                    </div>
                    <div class="col-sm-4">
                      <div class="form-group">
                        <label class="bmd-label-floating">Cell phone <b class="errLbl">*</b></label>
                        <input type="text" class="form-control js-only-numbers" id="emp_celular" name="emp_celular" inputmode="numeric" autocomplete="off">
                      </div>
                    </div>
                    <div class="col-sm-4">
                      <div class="form-group">
                        <label class="bmd-label-floating">Address</label>
                        <input type="text" class="form-control" id="emp_direccion" name="emp_direccion">
                      </div>
                    </div>
                    <div class="col-sm-6">
                      <div class="form-group">
                        <label class="bmd-label-floating">Gender</label>
                        <select class="form-control" id="emp_genero" name="emp_genero">
                          <option value="seleccione">Select a option</option>
                          <option value="Male">Male</option>
                          <option value="Female">Female</option>
                          <option value="Other">Other</option>
                        </select>
                      </div>
                    </div>
                    <div class="col-sm-3">
                      <div class="form-group">
                        <label class="bmd-label-floating">Shirt</label>
                        <select class="form-control" id="emp_camisa" name="emp_camisa">
                          <option value="seleccione">Select</option>
                          <option value="XS">XS</option>
                          <option value="S">S</option>
                          <option value="M">M</option>
                          <option value="L">L</option>
                          <option value="XL">XL</option>
                          <option value="XXL">XXL</option>
                          <option value="XXXL">XXXL</option>
                        </select>
                      </div>
                    </div>
                    <div class="col-sm-3">
                      <div class="form-group">
                        <label class="bmd-label-floating">Pants</label>
                        <select class="form-control" id="emp_pantalon" name="emp_pantalon">
                          <option value="seleccione">Select</option>
                          <option value="28">28</option>
                          <option value="30">30</option>
                          <option value="32">32</option>
                          <option value="34">34</option>
                          <option value="36">36</option>
                          <option value="38">38</option>
                          <option value="40">40</option>
                          <option value="42">42</option>
                        </select>
                      </div>
                    </div>
                    <div class="col-sm-3">
                      <div class="form-group">
                        <label class="bmd-label-floating">Shoes</label>
                        <select class="form-control" id="emp_calzado" name="emp_calzado">
                          <option value="seleccione">Select</option>
                          <option value="36">36</option>
                          <option value="37">37</option>
                          <option value="38">38</option>
                          <option value="39">39</option>
                          <option value="40">40</option>
                          <option value="41">41</option>
                          <option value="42">42</option>
                          <option value="43">43</option>
                          <option value="44">44</option>
                        </select>
                      </div>
                    </div>
                    <div class="col-sm-3">
                      <div class="form-group">
                        <label class="bmd-label-floating">Uniform delivery</label>
                        <input type="date" class="form-control" id="emp_entrega_uniforme" name="emp_entrega_uniforme">
                      </div>
                    </div>
                  </div>
                  <small class="text-muted d-block mb-2">Name and email are taken from the user fields above. Employee Id is generated automatically.</small>
                </div>
              </div>

              <div class="col-sm-6" id="rest_days_wrap" style="display:none;">
                <div class="form-group rest-days-card">
                  <div class="rest-days-icon">
                    <i class="fa fa-calendar-check-o"></i>
                  </div>
                  <div class="rest-days-content">
                    <small>Remaining rest days</small>
                    <strong id="dias_descanso_text">0 days</strong>
                    <span id="dias_descanso_hint">This information is linked to the employee record.</span>
                    <input type="hidden" id="dias_descanso" name="dias_descanso" value="">
                  </div>
                </div>
              </div>

              <div class="col-sm-6">
                <div class="form-group">
                  <label class="bmd-label-floating">Last Name <b class="errLbl">*</b></label>
                  <input type="text" class="form-control" id="apellido" name="apellido">
                </div>
              </div>

              <div class="col-sm-6">
                <div class="form-group">
                  <label class="bmd-label-floating">Type</label>
                  <select class="form-control" id="tipo" name="tipo">
                    <option value="Administrador">Manager</option>
                    <option value="Staff">Staff</option>
                    <?php if (SessionData::superAdministrador()) { ?>
                      <option value="SuperAdministrador">Super Manager</option>
                    <?php } ?>
                  </select>
                </div>
              </div>

              <div class="col-sm-6">
                <div class="form-group">
                  <label class="bmd-label-floating">Role <b class="errLbl">*</b></label>
                  <select class="form-control" id="role_id" name="role_id">
                    <option value="">Select role…</option>
                    <?php
                    require_once './admin/classes/Role.php';
                    $rolesRes = Role::getAll([]);
                    if (!empty($rolesRes['output']['valid'])) {
                      foreach ($rolesRes['output']['response'] as $roleRow) {
                        echo '<option value="' . (int)$roleRow['id'] . '">' . htmlspecialchars($roleRow['name']) . '</option>';
                      }
                    }
                    ?>
                  </select>
                  <small class="text-muted">Permissions come from the selected role.</small>
                </div>
              </div>

              <div class="col-sm-6">
                <div class="form-group">
                  <label for="habilitado" class="bmd-label-floating">Enable</label>
                  <select class="form-control" id="habilitado" name="habilitado">
                    <option value="yes">Yes</option>
                    <option value="no">No</option>
                  </select>
                </div>
              </div>

              <div class="col-sm-6">
                <div class="form-group">
                  <label class="bmd-label-floating">HOA designated (Name) <b class="errLbl">*</b></label>
                  <select class="form-control" id="tbl_unidad_id" name="tbl_unidad_id[]" multiple>
                    <?php echo $optionUnidades; ?>
                  </select>
                  <small class="form-text text-muted">You can select one or multiple units.</small>
                </div>
              </div>

              <div class="col-sm-6">
                <div class="form-group">
                  <label class="bmd-label-floating">User name <b class="errLbl">*</b></label>
                  <input type="email" class="form-control" id="nickname" name="nickname" value="">
                  <input type="hidden" class="form-control" name="nickname2" id="nickname2">
                </div>
              </div>

              <div class="col-sm-6">
                <div class="form-group">
                  <label class="bmd-label-floating">Password <b class="errLbl">*</b></label>
                  <input type="password" class="form-control" id="hashpass" name="hashpass">
                </div>
              </div>

              <div class="col-sm-6">
                <div class="form-group">
                  <label class="bmd-label-floating">Retype password <b class="errLbl">*</b></label>
                  <input type="password" class="form-control" id="hashpass1" name="hashpass1">
                </div>
              </div>

              <div class="col-sm-6">
                <div class="form-group">
                  <label class="bmd-label-floating" for="exampleInputName2">File</label>
                  <div class="controls">
                    <iframe id='ifm' name='ifm' src="upload.php" width="200" height="60" scrolling="no" frameborder="0"></iframe>
                  </div>
                </div>
              </div>

            </div>
          </form>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-saas" onclick="UTIL.clearForm('formcreate');" data-dismiss="modal">Cancel</button>
          <button type="button" onclick="USUARIO.validateData();" class="btn btn-saas btn-brand">Save</button>
        </div>
      </div>
    </div>
  </div>

  <?php /* Legacy per-user permission modal disabled — use Roles & Permissions */ if (false): ?>
  <!-- MODAL: Permission Assignment -->
  <div class="modal fade" id="myModalPermisos" tabindex="-1" role="dialog" aria-labelledby="lbcondiciones_rgpd" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content pgs-ui">

        <div class="modal-header card-header card-header-danger">
          <h4 class="modal-title">Permission Assignment</h4>
          <button type="button" onclick="UTIL.clearForm('formpermission');" class="close" data-dismiss="modal">&times;</button>
        </div>

        <div class="modal-body">
          <form id="formpermission" autocomplete="off">
            <div class="container-fluid">
              <div class="row">
                <div class="card w-100">
                  <div class="card-header card-header-tabs card-header card-header-spider">
                    <div class="nav-tabs-navigation">
                      <div class="nav-tabs-wrapper">
                        <span class="nav-tabs-title">Permissions</span>
                      </div>
                    </div>
                  </div>

                  <div class="card-body">
                    <div class="table-responsive">
                      <table class="table mb-0">
                        <thead class="text-primary">
                          <tr>
                            <th style="width:60px;">
                              <div class="form-check">
                                <label class="form-check-label">
                                  <input class="form-check-input" onChange="PERMISOS.checkAll();" name="check_permisos" id="check_permisos" type="checkbox" value="">
                                  <span class="form-check-sign">
                                    <span class="check"></span>
                                  </span>
                                </label>
                              </div>
                            </th>
                            <th>Permissions</th>
                          </tr>
                        </thead>
                        <tbody id="permission"></tbody>
                      </table>
                    </div>
                  </div>

                </div>
              </div>
            </div>
          </form>

          <div class="modal-footer">
            <button type="button" class="btn btn-saas" onclick="UTIL.clearForm('formpermission');" data-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-saas btn-brand" onclick="PERMISOS.savepermission();">Save</button>
          </div>

        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php include './admin/include/gerenic_footer.php'; ?>
  <?php include './admin/include/generic_search.php'; ?>

  <?php include './admin/include/gerenic_script.php'; ?>
  <?php include './admin/include/generic_dataTables.php'; ?>

  <script type="text/javascript" src="./admin/js/lib/data-md5.js"></script>
  <script type="text/javascript" src="./admin/js/usuario.js"></script>
  <?php /* permisos.js disabled with per-user permission modal */ ?>

  <script>
    window.PGS_EMPLOYEE_REST_DAYS = <?php echo json_encode($employeeRestDaysByEmployeeId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    /**
     * Bloqueo seguro para campos numéricos del modal.
     * No cambia nombres, ids, formulario, backend ni lógica existente.
     * Solo permite números, incluso al pegar texto desde el portapapeles.
     */
    (function () {
      'use strict';

      function limpiarSoloNumeros(input) {
        if (!input) return;
        var limpio = String(input.value || '').replace(/\D+/g, '');
        if (input.value !== limpio) {
          input.value = limpio;
        }
      }

      document.addEventListener('keydown', function (event) {
        var input = event.target;
        if (!input || !input.matches('.js-only-numbers, [data-only-numbers="true"]')) return;

        var teclasPermitidas = [
          'Backspace', 'Delete', 'Tab', 'Escape', 'Enter',
          'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown',
          'Home', 'End'
        ];

        if (teclasPermitidas.indexOf(event.key) !== -1 || event.ctrlKey || event.metaKey) {
          return;
        }

        if (!/^[0-9]$/.test(event.key)) {
          event.preventDefault();
        }
      });

      document.addEventListener('input', function (event) {
        var input = event.target;
        if (!input || !input.matches('.js-only-numbers, [data-only-numbers="true"]')) return;
        limpiarSoloNumeros(input);
      });

      document.addEventListener('paste', function (event) {
        var input = event.target;
        if (!input || !input.matches('.js-only-numbers, [data-only-numbers="true"]')) return;

        event.preventDefault();
        var texto = (event.clipboardData || window.clipboardData).getData('text') || '';
        var numeros = texto.replace(/\D+/g, '');

        if (document.queryCommandSupported && document.queryCommandSupported('insertText')) {
          document.execCommand('insertText', false, numeros);
        } else {
          input.value += numeros;
          limpiarSoloNumeros(input);
        }
      });

      document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.js-only-numbers, [data-only-numbers="true"]').forEach(limpiarSoloNumeros);
      });
    })();


    /**
     * Asociación usuario ↔ empleado.
     * Vínculo: tec_usuarios.employee_id = tec_employee.cc
     * - Sin asociación: elegir existente (Select2 valor=cc) o crear nuevo.
     * - Ya asociado: no mostrar Select2.
     */
    (function () {
      'use strict';

      var empSelectInitTimer = null;

      function qs(selector) {
        return document.querySelector(selector);
      }

      function normalizeValue(value) {
        return String(value == null ? '' : value).trim();
      }

      function optionHas(options, key) {
        return options && Object.prototype.hasOwnProperty.call(options, key);
      }

      function setEmpModeValue(mode) {
        var input = qs('#emp_mode');
        if (input) input.value = mode || '';
      }

      function getCurrentUserId() {
        return normalizeValue(qs('#id') ? qs('#id').value : '');
      }

      function getModalParent() {
        var $modal = jQuery('#myModal');
        var $content = $modal.find('.modal-content').first();
        return $content.length ? $content : $modal;
      }

      function destroyEmpSelect2() {
        if (empSelectInitTimer) {
          clearTimeout(empSelectInitTimer);
          empSelectInitTimer = null;
        }
        if (!window.jQuery) return;
        var $el = jQuery('#emp_select');
        if ($el.length && $el.hasClass('select2-hidden-accessible')) {
          try {
            $el.off('.pgsEmp');
            $el.select2('close');
            $el.select2('destroy');
          } catch (e) {}
        }
      }

      function initEmpSelect2() {
        if (!window.jQuery || !jQuery.fn.select2) return;

        if (empSelectInitTimer) {
          clearTimeout(empSelectInitTimer);
          empSelectInitTimer = null;
        }

        // Diferir: evita pelea de foco con el click del radio / enforceFocus del modal
        empSelectInitTimer = setTimeout(function () {
          empSelectInitTimer = null;
          var $el = jQuery('#emp_select');
          if (!$el.length) return;

          if ($el.hasClass('select2-hidden-accessible')) {
            try { $el.select2('destroy'); } catch (e) {}
          }

          $el.select2({
            placeholder: 'Search employee by name or CC…',
            allowClear: true,
            width: '100%',
            dropdownParent: getModalParent(),
            minimumInputLength: 0,
            ajax: {
              url: 'admin/ajax/rqst.php',
              dataType: 'json',
              delay: 250,
              data: function (params) {
                return {
                  op: 'pms_empleadosearch',
                  q: params.term || '',
                  exclude_user_id: getCurrentUserId() || 0
                };
              },
              processResults: function (data) {
                var rows = (data && data.output && data.output.valid) ? (data.output.response || []) : [];
                return {
                  results: rows.map(function (r) {
                    return { id: String(r.id), text: r.text || r.nombre || ('Employee ' + r.id) };
                  })
                };
              },
              cache: true
            }
          });

          $el.off('change.pgsEmp select2:select.pgsEmp select2:clear.pgsEmp')
            .on('change.pgsEmp select2:select.pgsEmp select2:clear.pgsEmp', function () {
              var val = jQuery(this).val();
              var empInput = qs('#employee_id');
              if (empInput) empInput.value = val || '';
            });
        }, 50);
      }

      function hideAllEmpPanels() {
        ['#emp_choice_wrap', '#emp_select_wrap', '#emp_create_wrap', '#emp_linked_wrap', '#rest_days_wrap'].forEach(function (sel) {
          var el = qs(sel);
          if (el) el.style.display = 'none';
        });
      }

      function clearCreateFields() {
        ['#emp_fecha_ingreso', '#emp_celular', '#emp_direccion', '#emp_entrega_uniforme'].forEach(function (sel) {
          var el = qs(sel);
          if (el) el.value = '';
        });
        ['#emp_genero', '#emp_camisa', '#emp_pantalon', '#emp_calzado'].forEach(function (sel) {
          var el = qs(sel);
          if (el) el.value = 'seleccione';
        });
      }

      function clearChoiceRadios() {
        // Compat: limpia estado visual de botones de elección
        setChoiceButtonsActive('');
      }

      function setChoiceButtonsActive(choice) {
        var btnExisting = qs('#emp_btn_existing');
        var btnCreate = qs('#emp_btn_create');
        if (btnExisting) {
          btnExisting.classList.toggle('btn-dark', choice === 'existing');
          btnExisting.classList.toggle('btn-outline-dark', choice !== 'existing');
        }
        if (btnCreate) {
          btnCreate.classList.toggle('btn-dark', choice === 'create');
          btnCreate.classList.toggle('btn-outline-dark', choice !== 'create');
        }
      }

      function applyChoice(choice) {
        choice = normalizeValue(choice);
        var selectWrap = qs('#emp_select_wrap');
        var createWrap = qs('#emp_create_wrap');
        if (selectWrap) selectWrap.style.display = 'none';
        if (createWrap) createWrap.style.display = 'none';

        setChoiceButtonsActive(choice);

        if (choice === 'existing') {
          setEmpModeValue('existing');
          if (selectWrap) selectWrap.style.display = '';
          if (window.jQuery) {
            try { jQuery('#emp_select').val(''); } catch (e) {}
          }
          var empInput = qs('#employee_id');
          if (empInput) empInput.value = '';
          initEmpSelect2();
        } else if (choice === 'create') {
          setEmpModeValue('create');
          destroyEmpSelect2();
          if (window.jQuery) {
            try { jQuery('#emp_select').val(null); } catch (e) {}
          }
          var empInput2 = qs('#employee_id');
          if (empInput2) empInput2.value = '';
          if (createWrap) createWrap.style.display = '';
        } else {
          setEmpModeValue('');
        }
      }

      function setEmployeeMode(isEmployee, options) {
        options = options || {};
        var check = qs('#es_empleado');
        var text = qs('#es_empleado_text');
        // linkedId: '' debe respetarse (no caer al valor previo de #employee_id)
        var linkedId = normalizeValue(
          optionHas(options, 'linkedId')
            ? options.linkedId
            : (qs('#employee_id') ? qs('#employee_id').value : '')
        );
        var linkedName = normalizeValue(
          optionHas(options, 'linkedName') ? options.linkedName : ''
        );

        if (!check) return;

        check.checked = !!isEmployee;
        if (text) text.textContent = check.checked ? 'Yes' : 'No';

        hideAllEmpPanels();
        destroyEmpSelect2();

        if (!check.checked) {
          if (!options.keepValue) {
            var empInput = qs('#employee_id');
            if (empInput) empInput.value = '';
            setEmpModeValue('');
            clearChoiceRadios();
            clearCreateFields();
          }
          return;
        }

        // Ya asociado: no Select2
        if (linkedId !== '') {
          var empKeep = qs('#employee_id');
          if (empKeep) empKeep.value = linkedId;
          setEmpModeValue('keep');
          clearChoiceRadios();
          var linkedWrap = qs('#emp_linked_wrap');
          var linkedText = qs('#emp_linked_text');
          if (linkedWrap) linkedWrap.style.display = '';
          if (linkedText) {
            linkedText.textContent = linkedName !== ''
              ? linkedName
              : 'Employee linked';
          }
          return;
        }

        // Sin asociación: preguntar existente vs nuevo
        clearChoiceRadios();
        var choiceWrap = qs('#emp_choice_wrap');
        if (choiceWrap) choiceWrap.style.display = '';
        setEmpModeValue('');
      }

      function validateEmployeeBeforeSave(event) {
        var check = qs('#es_empleado');
        if (!check || !check.checked) {
          var empClear = qs('#employee_id');
          if (empClear) empClear.value = '';
          setEmpModeValue('');
          return true;
        }

        var mode = normalizeValue(qs('#emp_mode').value);
        var msg = '';

        if (mode === 'keep') {
          if (normalizeValue(qs('#employee_id').value) === '') {
            msg = 'This user should keep the associated employee.';
          }
        } else if (mode === 'existing') {
          if (normalizeValue(qs('#employee_id').value) === '') {
            msg = 'Please select an existing employee.';
          }
        } else if (mode === 'create') {
          if (normalizeValue(qs('#emp_fecha_ingreso').value) === '') msg = 'Hire date is required.';
          else if (normalizeValue(qs('#emp_celular').value) === '') msg = 'Cell phone is required.';
        } else {
          msg = 'Choose whether to associate an existing employee or create a new one.';
        }

        if (msg) {
          if (event) {
            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === 'function') event.stopImmediatePropagation();
          }
          if (window.Swal) {
            Swal.fire({ icon: 'warning', title: 'Employee required', text: msg, confirmButtonText: 'OK' });
          } else {
            alert(msg);
          }
          return false;
        }
        return true;
      }

      function resetEmployeeUi() {
        var check = qs('#es_empleado');
        // clearForm puede vaciar value del checkbox; lo restauramos
        if (check) check.value = '1';
        setEmployeeMode(false, { keepValue: false, linkedId: '', linkedName: '' });
      }

      /**
       * Bootstrap modal enforceFocus cierra el dropdown de Select2 al abrir.
       * Permitimos foco dentro de .select2-container / .select2-dropdown.
       */
      function patchModalFocusForSelect2() {
        if (!window.jQuery || !jQuery.fn.modal) return;
        var Modal = jQuery.fn.modal.Constructor;
        if (!Modal || Modal.prototype.__pgsSelect2Patched) return;

        Modal.prototype.enforceFocus = function () {
          var that = this;
          jQuery(document)
            .off('focusin.bs.modal')
            .on('focusin.bs.modal', function (e) {
              var $target = jQuery(e.target);
              if ($target.closest('.select2-container').length || $target.closest('.select2-dropdown').length) {
                return;
              }
              if (that.$element[0] !== e.target && !that.$element.has(e.target).length) {
                that.$element.trigger('focus');
              }
            });
        };
        Modal.prototype.__pgsSelect2Patched = true;
      }

      document.addEventListener('DOMContentLoaded', function () {
        var check = qs('#es_empleado');
        var form = qs('#formcreate');

        patchModalFocusForSelect2();
        resetEmployeeUi();

        if (check) {
          check.addEventListener('change', function () {
            setEmployeeMode(this.checked, { keepValue: false, linkedId: '', linkedName: '' });
          });
        }

        // Botones (no radios): clearForm hace .val('') y destruía value="existing"/"create"
        if (window.jQuery) {
          jQuery(document)
            .off('click.pgsEmpChoice', '[data-emp-choice]')
            .on('click.pgsEmpChoice', '[data-emp-choice]', function (e) {
              e.preventDefault();
              e.stopPropagation();
              applyChoice(this.getAttribute('data-emp-choice'));
            });
        } else {
          document.querySelectorAll('[data-emp-choice]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
              e.preventDefault();
              applyChoice(this.getAttribute('data-emp-choice'));
            });
          });
        }

        if (form) {
          form.addEventListener('submit', function (event) {
            validateEmployeeBeforeSave(event);
          }, true);
        }

        if (window.jQuery) {
          jQuery('#myModal').on('show.bs.modal', function () {
            if (!normalizeValue(qs('#id').value)) {
              resetEmployeeUi();
            }
          });
          jQuery('#myModal').on('hidden.bs.modal', function () {
            resetEmployeeUi();
          });

          if (jQuery.fn.select2) {
            jQuery('#tbl_unidad_id').select2({
              placeholder: 'Select one or more units…',
              allowClear: true,
              width: '100%',
              dropdownParent: getModalParent()
            });
          }
        }
      });

      document.addEventListener('click', function (event) {
        var btn = event.target.closest('button');
        if (!btn) return;
        var onclick = btn.getAttribute('onclick') || '';

        if (onclick.indexOf('USUARIO.validateData') !== -1) {
          validateEmployeeBeforeSave(event);
        }
        if (onclick.indexOf("UTIL.clearForm('formcreate')") !== -1 || onclick.indexOf('UTIL.clearForm("formcreate")') !== -1) {
          setTimeout(resetEmployeeUi, 80);
        }
      }, true);

      window.PGS_setEmployeeMode = setEmployeeMode;
      window.PGS_resetEmployeeUi = resetEmployeeUi;
      window.PGS_validateEmployeeBeforeSave = validateEmployeeBeforeSave;
      window.PGS_applyEmpChoice = applyChoice;
    })();
  </script>


</body>
</html>