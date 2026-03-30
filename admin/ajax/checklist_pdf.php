<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Bogota');

require_once __DIR__ . '/../classes/DbConection.php';
require_once __DIR__ . '/../classes/SessionData.php';
require_once __DIR__ . '/../../vendor/autoload.php';

$view = SessionData::getPermission(21);
if (!$view) {
    die('No permission');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die('Invalid ID');
}

$db  = new DbConection();
$pdo = $db->openConect();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$st = $pdo->prepare("SELECT * FROM tbl_checklists WHERE id = ? LIMIT 1");
$st->execute([$id]);
$check = $st->fetch();

if (!$check) {
    die('Checklist not found');
}

$it = $pdo->prepare("
    SELECT *
    FROM tbl_checklist_items
    WHERE checklist_id = ?
    ORDER BY id ASC
");
$it->execute([$id]);
$items = $it->fetchAll();

$fs = $pdo->prepare("
    SELECT *
    FROM tbl_checklist_files
    WHERE checklist_id = ?
    ORDER BY id ASC
");
$fs->execute([$id]);
$files = $fs->fetchAll();

/* ================= HELPERS ================= */
function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function safeSlug(string $str): string
{
    $str = trim($str);
    $tmp = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
    if ($tmp !== false) {
        $str = $tmp;
    }
    $str = preg_replace('/[^a-zA-Z0-9]+/', '_', $str);
    $str = trim((string)$str, '_');

    return $str !== '' ? $str : 'item';
}

function normalizeKeyPart(string $text): string
{
    $text = trim($text);
    $text = preg_replace('/\s+/', ' ', $text);
    return $text;
}

function statusMeta(string $status): array
{
    $s = strtolower(trim($status));

    if ($s === 'cumple' || $s === 'pass') {
        return ['PASS', '#16a34a'];
    }
    if ($s === 'no_cumple' || $s === 'fail') {
        return ['FAIL', '#dc2626'];
    }
    if ($s === 'na' || $s === 'n/a') {
        return ['N/A', '#d97706'];
    }

    return ['PENDING', '#475569'];
}

function buildAbsolutePathFromWeb(string $rel): string
{
    $rel = ltrim(str_replace('\\', '/', (string)$rel), '/');
    return __DIR__ . '/../../' . $rel;
}

function groupItemsBySection(array $items): array
{
    $grouped = [];

    foreach ($items as $row) {
        $section = normalizeKeyPart((string)($row['section'] ?? ''));
        if ($section === '') {
            $section = 'General';
        }

        if (!isset($grouped[$section])) {
            $grouped[$section] = [];
        }

        $grouped[$section][] = $row;
    }

    return $grouped;
}

function indexPhotosByItemId(array $files): array
{
    $idx = [];

    foreach ($files as $f) {
        $itemId = isset($f['item_id']) ? (int)$f['item_id'] : 0;
        if ($itemId <= 0) {
            continue;
        }

        if (!isset($idx[$itemId])) {
            $idx[$itemId] = [];
        }

        $idx[$itemId][] = $f;
    }

    return $idx;
}

function indexPhotosByPrefix(array $files): array
{
    $idx = [];

    foreach ($files as $f) {
        $name = (string)($f['file_name'] ?? '');
        $path = (string)($f['file_path'] ?? '');

        if ($name === '' || $path === '') {
            continue;
        }

        $parts = explode('__', $name);
        if (count($parts) < 3) {
            continue;
        }

        $prefix = $parts[0] . '__' . $parts[1] . '__';

        if (!isset($idx[$prefix])) {
            $idx[$prefix] = [];
        }

        $idx[$prefix][] = $f;
    }

    return $idx;
}

function getItemPhotos(array $row, array $photosByItemId, array $photosByPrefix): array
{
    $itemId = isset($row['id']) ? (int)$row['id'] : 0;
    if ($itemId > 0 && isset($photosByItemId[$itemId])) {
        return $photosByItemId[$itemId];
    }

    $section = (string)($row['section'] ?? '');
    $label   = (string)($row['item_label'] ?? '');

    $secSlug = safeSlug($section);
    $labSlug = safeSlug($label);
    $prefix  = $secSlug . '__' . $labSlug . '__';

    return $photosByPrefix[$prefix] ?? [];
}

/* ===== TCPDF-friendly HTML builders ===== */
function badgeHTML(string $text, string $bg): string
{
    return '
    <table cellpadding="1" cellspacing="0" border="0" style="display:inline;">
        <tr>
            <td bgcolor="' . $bg . '" style="color:#ffffff;font-weight:bold;font-size:7px;line-height:8px;padding:2px 6px;">
                ' . h($text) . '
            </td>
        </tr>
    </table>';
}

function sectionHeaderHTML(string $section): string
{
    $section = trim($section) !== '' ? $section : 'SECTION';

    return '
    <table width="100%" cellpadding="4" cellspacing="0" border="0" style="margin-top:2px;">
        <tr>
            <td bgcolor="#f1f5f9" style="border:1px solid #e2e8f0;font-size:8.6px;font-weight:bold;color:#0f172a;line-height:10px;">
                ' . h($section) . '
            </td>
        </tr>
    </table>';
}

function qaCardHTML(string $question, string $statusText, string $statusColor, string $comment): string
{
    $question = trim($question) !== '' ? $question : '—';

    $commentBlock = '';
    if (trim($comment) !== '') {
        $commentBlock = '
        <tr>
            <td style="font-size:7px;color:#334155;line-height:9px;padding-top:2px;">
                <table width="100%" cellpadding="3" cellspacing="0" border="0">
                    <tr>
                        <td bgcolor="#ffffff" style="border:1px dashed #cbd5e1;">
                            <b>Comment:</b><br>' . nl2br(h($comment)) . '
                        </td>
                    </tr>
                </table>
            </td>
        </tr>';
    }

    return '
    <table width="100%" cellpadding="4" cellspacing="0" border="0" style="margin-top:1px;">
        <tr>
            <td style="border:1px solid #e2e8f0;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td width="70%" style="font-size:6.8px;color:#64748b;line-height:8px;">
                            <b>Item</b>
                        </td>
                        <td width="30%" align="right">
                            ' . badgeHTML($statusText, $statusColor) . '
                        </td>
                    </tr>
                </table>

                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:2px;">
                    <tr>
                        <td style="font-size:6.6px;color:#64748b;line-height:8px;"><b>QUESTION</b></td>
                    </tr>
                    <tr>
                        <td style="font-size:7.6px;color:#0f172a;font-weight:bold;line-height:9px;">' . h($question) . '</td>
                    </tr>
                </table>

                <table width="100%" cellpadding="3" cellspacing="0" border="0" style="margin-top:2px;">
                    <tr>
                        <td bgcolor="#f8fafc" style="border:1px solid #e2e8f0;">
                            <span style="font-size:6.6px;color:#64748b;line-height:8px;"><b>ANSWER</b></span><br>
                            <span style="font-size:7.2px;color:#0f172a;line-height:9px;"><b>Status:</b> ' . h($statusText) . '</span>
                        </td>
                    </tr>
                </table>

                ' . $commentBlock . '
            </td>
        </tr>
    </table>';
}

/* ================= PDF ================= */
class MYPDF extends TCPDF
{
    public ?string $logoPath = null;
    public string $brand = 'PGS Centrum • Inspection & Compliance';
    public string $rightMeta = '';
    public bool $watermark = true;

    public function Header()
    {
        $this->SetFillColor(8, 47, 73);
        $this->Rect(0, 0, 210, 22, 'F');

        $this->SetFillColor(14, 165, 233);
        $this->Rect(0, 21.2, 210, 0.8, 'F');

        if ($this->logoPath && is_file($this->logoPath)) {
            $this->Image($this->logoPath, 12, 5.0, 30, 0, '', '', '', true, 300);
        }

        $this->SetTextColor(255, 255, 255);
        $this->SetFont('helvetica', 'B', 11);
        $this->SetXY(46, 5.6);
        $this->Cell(0, 6, 'INSPECTION CHECKLIST REPORT', 0, 1, 'L');

        $this->SetFont('helvetica', '', 8.0);
        $this->SetTextColor(226, 232, 240);
        $this->SetXY(46, 12.0);
        $this->Cell(0, 5, $this->brand, 0, 0, 'L');

        if ($this->rightMeta !== '') {
            $this->SetXY(12, 6.0);
            $this->Cell(186, 6, $this->rightMeta, 0, 0, 'R');
        }

        if ($this->watermark) {
            $this->SetAlpha(0.05);
            $this->SetTextColor(2, 6, 23);
            $this->SetFont('helvetica', 'B', 46);
            $this->StartTransform();
            $this->Rotate(25, 110, 150);
            $this->Text(18, 150, 'CONFIDENTIAL');
            $this->StopTransform();
            $this->SetAlpha(1);
        }
    }

    public function Footer()
    {
        $this->SetY(-11);
        $this->SetFont('helvetica', '', 7.2);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(
            0,
            8,
            'Generated • ' . date('Y-m-d H:i') . ' • Page ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(),
            0,
            0,
            'C'
        );
    }
}

/* ================= INIT ================= */
$brandLogo = __DIR__ . '/../../assets/img/logo3.png';

$pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Checklist');
$pdf->SetAuthor('System');
$pdf->SetTitle('Checklist #' . $id);

$pdf->SetMargins(10, 26, 10);
$pdf->SetAutoPageBreak(true, 12);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

$pdf->logoPath  = is_file($brandLogo) ? $brandLogo : null;
$pdf->rightMeta = 'Report #' . $id . ' • ' . date('Y-m-d');

$pdf->SetFont('helvetica', '', 7.8);
$pdf->AddPage();

/* ================= DATA ================= */
$title     = trim((string)($check['title'] ?? ''));
$unidad    = (string)($check['unidad'] ?? '');
$inspector = (string)($check['inspector_name'] ?? '');
$comments  = (string)($check['general_comments'] ?? '');

if ($title === '') {
    $title = 'Checklist #' . $id;
}

// Stats
$stats = [
    'pass'    => 0,
    'fail'    => 0,
    'na'      => 0,
    'pending' => 0
];

foreach ($items as $r) {
    $s = strtolower(trim((string)($r['status'] ?? '')));

    if ($s === 'cumple' || $s === 'pass') {
        $stats['pass']++;
    } elseif ($s === 'no_cumple' || $s === 'fail') {
        $stats['fail']++;
    } elseif ($s === 'na' || $s === 'n/a') {
        $stats['na']++;
    } else {
        $stats['pending']++;
    }
}

$totalItems = count($items);
$photosByItemId = indexPhotosByItemId($files);
$photosByPrefix = indexPhotosByPrefix($files);

/* ================= SUMMARY ================= */
$summary = '
<table width="100%" cellpadding="5" cellspacing="0" border="0">
    <tr>
        <td style="border:1px solid #e2e8f0;">
            <span style="font-size:10px;font-weight:bold;color:#0f172a;line-height:11px;">' . h($title) . '</span><br>
            <span style="font-size:7.2px;color:#64748b;line-height:9px;">
                Report ID: #' . (int)$check['id'] . ' • Unit: ' . h($unidad ?: '—') . ' • Inspector: ' . h($inspector ?: '—') . ' • Items: ' . (int)$totalItems . '
            </span><br><br>
            ' . badgeHTML('PASS ' . $stats['pass'], '#16a34a') . ' &nbsp; ' .
                 badgeHTML('FAIL ' . $stats['fail'], '#dc2626') . ' &nbsp; ' .
                 badgeHTML('N/A ' . $stats['na'], '#d97706') . ' &nbsp; ' .
                 badgeHTML('PENDING ' . $stats['pending'], '#475569') . '
        </td>
    </tr>
</table>';

if (trim($comments) !== '') {
    $summary .= '
    <table width="100%" cellpadding="5" cellspacing="0" border="0" style="margin-top:2px;">
        <tr>
            <td bgcolor="#ffffff" style="border:1px dashed #cbd5e1;font-size:7.2px;color:#334155;line-height:9px;">
                <b>General comments:</b><br>' . nl2br(h($comments)) . '
            </td>
        </tr>
    </table>';
}

$pdf->writeHTML($summary, true, false, true, false, '');

/* ================= ITEMS BY SECTION ================= */
$itemsBySection = groupItemsBySection($items);

/* ================= TWO COLUMNS ================= */
$colW   = 92;
$gap    = 6;
$leftX  = 10;
$rightX = 10 + $colW + $gap;

$renderColumn = function (array $arr) use ($pdf, $photosByItemId, $photosByPrefix, $colW) {
    foreach ($arr as $row) {
        $label   = (string)($row['item_label'] ?? '');
        $comment = (string)($row['comment'] ?? '');
        $status  = (string)($row['status'] ?? '');

        [$lbl, $color] = statusMeta($status);

        $qa = qaCardHTML($label, $lbl, $color, $comment);
        $pdf->writeHTMLCell($colW, 0, $pdf->GetX(), $pdf->GetY(), $qa, 0, 1, false, true, 'L', true);

        $photos = getItemPhotos($row, $photosByItemId, $photosByPrefix);

        if (!empty($photos)) {
            $pdf->writeHTMLCell(
                $colW,
                0,
                $pdf->GetX(),
                $pdf->GetY(),
                '<span style="font-size:7px;font-weight:bold;color:#0f172a;">Photos</span>',
                0,
                1,
                false,
                true,
                'L',
                true
            );

            $imgW = (int)floor(($colW - 4) / 2);
            $imgH = 30;
            $pad  = 1.2;

            $startX = $pdf->GetX();
            $startY = $pdf->GetY();

            $col = 0;
            $x0 = $startX;
            $y0 = $startY;

            $pageH  = $pdf->getPageHeight();
            $bottom = 12;

            foreach ($photos as $ph) {
                $abs = buildAbsolutePathFromWeb((string)($ph['file_path'] ?? ''));
                if (!is_file($abs)) {
                    continue;
                }

                if (($y0 + $imgH + 2) > ($pageH - $bottom)) {
                    $pdf->AddPage();
                    $startX = $pdf->GetX();
                    $startY = $pdf->GetY();
                    $x0 = $startX;
                    $y0 = $startY;
                    $col = 0;
                }

                $px = $x0 + ($col * ($imgW + 4));
                $py = $y0;

                $pdf->SetDrawColor(226, 232, 240);
                $pdf->Rect($px, $py, $imgW, $imgH);
                $pdf->Image($abs, $px + $pad, $py + $pad, $imgW - ($pad * 2), $imgH - ($pad * 2), '', '', '', true, 300);

                $col++;
                if ($col >= 2) {
                    $col = 0;
                    $y0 += ($imgH + 3.0);
                }
            }

            if ($col > 0) {
                $y0 += ($imgH + 3.0);
            }

            $pdf->SetXY($startX, $y0);
        }

        $pdf->Ln(0.8);
    }

    return $pdf->GetY();
};

foreach ($itemsBySection as $section => $secItems) {
    $pdf->writeHTML(sectionHeaderHTML($section), true, false, true, false, '');

    $total = count($secItems);
    $mid   = (int)ceil($total / 2);

    $leftItems  = array_slice($secItems, 0, $mid);
    $rightItems = array_slice($secItems, $mid);

    $startY = $pdf->GetY();

    $pdf->SetXY($leftX, $startY);
    $yLeftEnd = $renderColumn($leftItems);

    $pdf->SetXY($rightX, $startY);
    $yRightEnd = $renderColumn($rightItems);

    $pdf->SetXY($leftX, max($yLeftEnd, $yRightEnd) + 1.2);
}

/* ================= OUTPUT ================= */
$pdf->Output('checklist_' . $id . '.pdf', 'I');