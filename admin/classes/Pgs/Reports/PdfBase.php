<?php

declare(strict_types=1);

namespace Pgs\Reports;

use Pgs\Auth\Principal;
use Pgs\Core\PrivateStorage;

abstract class PdfBase
{
    /**
     * Renders the report and saves it to private storage.
     *
     * @return array{path:string, filename:string, pages:int, size:int}
     */
    abstract public function render(Principal $p, int $unitId, string $from, string $to, string $lang = 'en'): array;

    protected function createTcpdf(string $title, string $subject): \TCPDF
    {
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('PGS Centrum');
        $pdf->SetAuthor('PGS Centrum');
        $pdf->SetTitle($title);
        $pdf->SetSubject($subject);

        // Remove default header and footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Margins: 15mm left/right, 15mm top
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        return $pdf;
    }

    protected function saveToStorage(\TCPDF $pdf, string $filenamePrefix): array
    {
        $pages = max(1, $pdf->getNumPages());
        $tmpPath = tempnam(sys_get_temp_dir(), 'rpt_') . '.pdf';
        $pdf->Output($tmpPath, 'F');

        $size = (int) filesize($tmpPath);

        $relPath = PrivateStorage::put('reports', 'pdf', $tmpPath);
        @unlink($tmpPath);

        $filename = sprintf('%s_%s.pdf', $filenamePrefix, date('Ymd_His'));

        return [
            'path' => $relPath,
            'filename' => $filename,
            'pages' => $pages,
            'size' => $size,
        ];
    }
}
