<?php
require './admin/include/generic_classes.php';
include './admin/classes/DailyReport.php';

// Permissions
$view   = SessionData::getPermission(7);
$create = SessionData::getPermission(7);
$edit   = SessionData::getPermission(7);
$delete = SessionData::getPermission(7);
$enable = SessionData::getPermission(7);

if (!$view) { require 'permiso_denegado.php'; }

$errors = [];

if (empty($_POST['hoa']) || $_POST['hoa'] == "seleccione") { $errors[] = "The field 'HOA' is required."; }
if (empty($_POST['f1'])) { $errors[] = "The field 'Date 1' is required."; }
if (empty($_POST['f2'])) { $errors[] = "The field 'Date 2' is required."; }

if (!empty($errors)) {
?>
<script type='text/javascript'>
  alert('All fields are required');
  window.location = 'report-list-group.php';
</script>
<?php
  exit;
}

if (!empty($_POST['hoa']) && isset($_POST['hoa']) && $_POST['hoa'] > 0) {
  $rqst = array('hoa' => $_POST['hoa'], 'f1' => $_POST['f1'], 'f2' => $_POST['f2']);
  $arr  = DailyReport::reportListGroupDownload($rqst);

  $isvalid  = $arr['output']['valid'];
  $data     = $arr['output']['response'];
  $dataShow = $data;

  if (count($data) > 0) {
    $data0    = $data[0];
    $id       = $data0['id'] ? $data0['id'] : '';
    $hoa      = isset($data0['nombre']) ? ($data0['nombre']) : '';
    $employee = isset($data0['usuario']) ? ($data0['usuario']) : '';
    $dtcreate = isset($data0['dtcreate']) ? ($data0['dtcreate']) : '';
    $email    = isset($data0['email']) ? ($data0['email']) : '';
    $manager  = isset($data0['administrador']) ? ($data0['administrador']) : '';
    $address  = isset($data0['ubicacion']) ? ($data0['ubicacion']) : '';
  } else {
?>
<script type='text/javascript'>
  alert('No results');
  window.location = 'report-list-group.php';
</script>
<?php
    exit;
  }
} else {
?>
<script type='text/javascript'>
  alert('You must send a report to generate the document');
  window.location = 'report-list-group.php';
</script>
<?php
  exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Job Report Print</title>

  <!-- Keep your libs (no backend change) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/2.0.3/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/select/2.0.0/css/select.bootstrap4.min.css">

  <script src="https://code.jquery.com/jquery-3.7.1.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.5.2/js/bootstrap.min.js"></script>
  <script src="https://cdn.datatables.net/2.0.3/js/dataTables.js"></script>
  <script src="https://cdn.datatables.net/2.0.3/js/dataTables.bootstrap4.js"></script>
  <script src="https://cdn.datatables.net/select/2.0.0/js/dataTables.select.js"></script>
  <script src="https://cdn.datatables.net/select/2.0.0/js/select.bootstrap4.js"></script>

  <style>
    /* ==========================================================
      PRINT A4 – Premium Report (Red + Black)
      UI ONLY — keeps PHP variables and logic intact
    ========================================================== */

    :root{
      --red:#E11D2E;
      --red2:#B3121E;
      --black:#0B0F14;
      --muted:#64748b;
      --border:#e5e7eb;
      --soft:#f8fafc;
    }

    /* Screen defaults */
    body{
      font-family: Arial, sans-serif;
      margin:0;
      padding: 18px;
      background: #fff;
      color: #111827;
    }

    /* ===== A4 Page setup ===== */
    @page{
      size: A4;
      margin: 12mm 12mm 14mm 12mm;
    }

    /* Each printed page wrapper */
    .print-page{
      page-break-after: always;
      break-after: page;
      padding: 0;
    }
    .print-page:last-child{
      page-break-after: auto;
      break-after: auto;
    }

    /* Card that fits A4 nicely */
    .print-card{
      border: 2px solid transparent;
      border-image: linear-gradient(90deg, var(--red), #111, #999) 1;
      border-radius: 12px;
      padding: 14px 14px 12px 14px;
      background: #fff;
    }

    /* Header (reusable per page) */
    .print-header{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap: 12px;
      padding-bottom: 10px;
      border-bottom: 3px solid var(--red);
      margin-bottom: 10px;
    }
    .brand{
      display:flex;
      gap: 12px;
      align-items:center;
      flex-wrap:wrap;
    }
    .brand img{
      height: 44px;
      width: auto;
      display:block;
    }
    .title-block h1{
      margin:0;
      font-size: 18px;
      font-weight: 900;
      color: var(--black);
      letter-spacing: -.2px;
      line-height: 1.1;
    }
    .title-block .sub{
      margin-top: 4px;
      font-size: 12px;
      font-weight: 700;
      color: var(--muted);
    }

    /* Meta block right */
    .meta{
      text-align:right;
      min-width: 210px;
      font-size: 12px;
    }
    .meta b{ font-weight: 900; }
    .meta .pill{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding: 6px 10px;
      border-radius: 999px;
      background: rgba(225,29,46,.08);
      border: 1px solid rgba(225,29,46,.18);
      color: var(--red2);
      font-weight: 900;
      margin-bottom: 8px;
      white-space: nowrap;
    }

    /* Tables */
    .table{
      margin: 10px 0 0 0;
      width:100%;
      border-collapse: collapse;
      page-break-inside: avoid;
      break-inside: avoid;
    }
    .table thead th{
      background: var(--soft);
      border: 1px solid var(--border);
      padding: 8px;
      font-size: 12px;
      font-weight: 900;
      color: #111827;
    }
    .table tbody td{
      border: 1px solid var(--border);
      padding: 8px;
      font-size: 12px;
      vertical-align: top;
    }

    /* Section title */
    .section-title{
      margin: 12px 0 8px;
      padding: 8px 10px;
      border-radius: 10px;
      background: linear-gradient(135deg, rgba(225,29,46,.10), rgba(0,0,0,.04));
      border: 1px solid rgba(2,6,23,.08);
      font-weight: 950;
      color: var(--black);
      text-transform: uppercase;
      letter-spacing: .3px;
      font-size: 12px;
    }

    /* Observations block */
    .observations{
      margin-top: 10px;
      padding: 10px 12px;
      border: 1px solid rgba(2,6,23,.10);
      border-radius: 12px;
      background: #fff;
      page-break-inside: avoid;
      break-inside: avoid;
    }
    .observations .lab{
      font-weight: 950;
      color: var(--black);
      margin-bottom: 6px;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: .25px;
    }
    .observations p{
      margin:0;
      font-size: 12px;
      color: #111827;
      white-space: pre-wrap;
      word-wrap: break-word;
    }

    /* Image grid */
    .img-grid{
      display:grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-top: 10px;
      page-break-inside: avoid;
      break-inside: avoid;
    }
    .img-box{
      border: 1px solid rgba(2,6,23,.10);
      border-radius: 12px;
      overflow: hidden;
      background: #fff;
    }
    .img-box .cap{
      padding: 8px 10px;
      background: var(--soft);
      border-bottom: 1px solid var(--border);
      font-weight: 950;
      text-transform: uppercase;
      font-size: 11px;
      color: var(--black);
      letter-spacing: .2px;
    }
 .img-box{
  position: relative;
}

.img-box img{
  width:40%;
  height: 50mm; /* fits A4 nicely */
  object-fit: cover;
  display:block;
}

/* ✅ When placeholder logo is used: keep it small and centered */
.img-box img.placeholder-logo{
  width: 100px !important;
  height: auto !important;
  object-fit: contain !important;
  position: absolute !important;
  top: 50% !important;
  left: 50% !important;
  transform: translate(-50%, -50%) !important;
  margin: 0 !important;
}
    /* Footer (per page) */
    .print-footer{
      margin-top: 10px;
      padding-top: 8px;
      border-top: 1px solid rgba(2,6,23,.10);
      display:flex;
      justify-content:space-between;
      align-items:center;
      font-size: 11px;
      color: var(--muted);
      font-weight: 800;
    }

    /* Make sure buttons/forms don't print */
    @media print{
      body{ padding: 0; }
      button, form, .no-print{ display:none !important; }

      /* Better colors on print */
      *{ -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    }

    /* Mobile (screen only) */
    @media (max-width: 760px){
      body{ padding: 12px; }
      .meta{ text-align:left; min-width:auto; }
      .img-grid{ grid-template-columns: 1fr; }
      .img-box img{ height: 70mm; }
    }
  </style>
</head>

<body>

<?php
  // Cover page (first page) – Summary + email button (screen only)
?>
<div class="print-page">
  <div class="print-card">

    <div class="print-header">
      <div class="brand">
        <img src="assets/img/logo3.png" alt="PGS Centrum Logo">
        <div class="title-block">
          <h1>Job Report #<?= htmlspecialchars($id); ?></h1>
          <div class="sub">Grouped Report • Printable A4 Format</div>
        </div>
      </div>

      <div class="meta">
        <div class="pill">A4 • Ready to Print</div>
        <div><b>Date:</b> <?= htmlspecialchars($dtcreate); ?></div>
        <div><b>Version:</b> 1</div>
      </div>
    </div>

    <div class="row mb-2">
      <div class="col-sm-6" style="font-size:12px;">
        <div><b>Property (HOA):</b> <?= htmlspecialchars($hoa); ?></div>
        <div><b>Manager:</b> <?= htmlspecialchars($manager); ?></div>
        <div><b>Address:</b> <?= htmlspecialchars($address); ?></div>
        <div><b>Email:</b> <?= htmlspecialchars($email); ?></div>
      </div>
      <div class="col-sm-6" style="font-size:12px;">
        <div><b>Employee:</b> <?= htmlspecialchars($employee); ?></div>
        <div><b>Date Range:</b> <?= htmlspecialchars($_POST['f1']); ?> → <?= htmlspecialchars($_POST['f2']); ?></div>

        <!-- Screen-only email button (does not print) -->
        <div class="no-print" style="margin-top:10px;">
          <form action="email.php" method="POST" style="display:inline;">
            <input id="hoa" name="hoa" type="hidden" value="<?php echo $_POST['hoa']; ?> ">
            <input id="f1"  name="f1"  type="hidden" value="<?php echo $_POST['f1'] ?> ">
            <input id="f2"  name="f2"  type="hidden" value="<?php echo $_POST['f2'] ?> ">
            <button type="submit" class="btn btn-success btn-sm" value="Submit">Send Report by Email</button>
          </form>
        </div>

      </div>
    </div>

    <table class="table table-striped">
      <thead>
        <tr>
          <th>Date Check</th>
          <th>HOA</th>
          <th>Employee</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><?= htmlspecialchars($dtcreate); ?></td>
          <td><?= htmlspecialchars($hoa); ?></td>
          <td><?= htmlspecialchars($employee); ?></td>
        </tr>
      </tbody>
    </table>

    <div class="print-footer">
      <div>PGS Centrum • Job Reports</div>
      <div>Cover Page</div>
    </div>

  </div>
</div>

<?php
  // Each item starts on its own A4 page (clean separation)
  $pageNum = 1;
  foreach ($dataShow as $data) :
    $pageNum++;

    $img  = (!empty($data["foto_antes"])  && $data["foto_antes"]  !== "no_image.png")
      ? "admin/js/camara/foto/{$data['foto_antes']}"
      : 'assets/img/logo-spiderP.png';

    $imga = (!empty($data["foto_despues"]) && $data["foto_despues"] !== "no_image.png")
      ? "admin/js/camara/foto/{$data['foto_despues']}"
      : 'assets/img/logo-spiderP.png';
?>
  <div class="print-page">
    <div class="print-card">

      <div class="print-header">
        <div class="brand">
          <img src="assets/img/logo3.png" alt="PGS Centrum Logo">
          <div class="title-block">
            <h1>Job Zone Reported</h1>
            <div class="sub"><?= htmlspecialchars($hoa); ?> • <?= htmlspecialchars($dtcreate); ?></div>
          </div>
        </div>

        <div class="meta">
          <div class="pill">Page <?= (int)$pageNum; ?></div>
          <div><b>Employee:</b> <?= htmlspecialchars($employee); ?></div>
          <div><b>Manager:</b> <?= htmlspecialchars($manager); ?></div>
        </div>
      </div>

      <div class="section-title">Job Details</div>
      <table class="table table-bordered table-sm">
        <thead>
          <tr>
            <th style="width:90px;">Item</th>
            <th style="width:150px;">Date</th>
            <th style="width:180px;">Zone</th>
            <th>Activities</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><?= htmlspecialchars($data['id']); ?></td>
            <td><?= htmlspecialchars($data['dtcreate']); ?></td>
            <td><?= htmlspecialchars($data['zone']); ?></td>
            <td><?= htmlspecialchars($data['actividades']); ?></td>
          </tr>
        </tbody>
      </table>

      <div class="observations">
        <div class="lab">Observations</div>
        <p><?= htmlspecialchars($data['observaciones']); ?></p>
      </div>

      <div class="section-title">Evidence Photos</div>
      <div class="img-grid">
        <div class="img-box">
          <div class="cap">Before</div>
          <img src="<?= $img ?>" alt="Before Image">
        </div>
        <div class="img-box">
          <div class="cap">After</div>
          <img src="<?= $imga ?>" alt="After Image">
        </div>
      </div>

      <div class="print-footer">
        <div>PGS Centrum • Job Report #<?= htmlspecialchars($id); ?></div>
        <div>Generated: <?= htmlspecialchars($dtcreate); ?></div>
      </div>

    </div>
  </div>
<?php endforeach; ?>

<script>
  // Auto print
  window.print();
</script>
</body>
</html>