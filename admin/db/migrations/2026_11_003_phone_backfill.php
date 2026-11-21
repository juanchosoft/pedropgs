<?php

declare(strict_types=1);

use Pgs\Core\Clock;
use Pgs\Core\Phone;
use Pgs\Core\PrivateStorage;
use Pgs\Migrations\AbstractMigration;
use Pgs\Migrations\MigrationContext;

return new class extends AbstractMigration {
    private const BATCH = 500;

    public function id(): string
    {
        return '2026_11_003_phone_backfill';
    }

    public function description(): string
    {
        return 'Load property and staff phones from legacy columns into tec_phone_directory';
    }

    public function up(\PDO $pdo, MigrationContext $ctx): void
    {
        $now = Clock::nowUtcSql();
        $insDir = $pdo->prepare(
            'INSERT IGNORE INTO tec_phone_directory
             (kind, unidad_id, employee_id, phone_e164, label, source, legacy_field, is_active, created_at, updated_at)
             VALUES (:kind, :unidad, :emp, :phone, :label, "legacy", :field, 1, :now, :now)'
        );
        $insDisc = $pdo->prepare(
            'INSERT IGNORE INTO tec_phone_discards (source_table, source_id, field_name, raw_value, reason, created_at)
             VALUES (:table, :sid, :field, :raw, :reason, :now)'
        );

        $discarded = [];
        $added = 0;

        $lastId = 0;
        $unitStmt = $pdo->prepare('SELECT id, celular, telefono_emergencia FROM tbl_unidades WHERE id > :last ORDER BY id LIMIT ' . self::BATCH);
        while (true) {
            $unitStmt->execute([':last' => $lastId]);
            $rows = $unitStmt->fetchAll(\PDO::FETCH_ASSOC);
            if ($rows === []) {
                break;
            }
            foreach ($rows as $row) {
                $lastId = (int) $row['id'];
                foreach (['celular', 'telefono_emergencia'] as $field) {
                    foreach (self::tokens((string) ($row[$field] ?? '')) as $token) {
                        $e164 = self::normalize($token);
                        if ($e164 === null) {
                            $reason = self::junkReason($token);
                            $insDisc->execute([':table' => 'tbl_unidades', ':sid' => $row['id'], ':field' => $field, ':raw' => mb_substr($token, 0, 100), ':reason' => $reason, ':now' => $now]);
                            $discarded[] = ['tbl_unidades', $row['id'], $field, $token, $reason];
                            continue;
                        }
                        $insDir->execute([':kind' => 'property', ':unidad' => $row['id'], ':emp' => 0, ':phone' => $e164, ':label' => $field, ':field' => $field, ':now' => $now]);
                        $added += $insDir->rowCount();
                    }
                }
            }
        }

        $empStmt = $pdo->query('SELECT id, celular FROM tec_employee ORDER BY id');
        foreach ($empStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            foreach (self::tokens((string) ($row['celular'] ?? '')) as $token) {
                $e164 = self::normalize($token);
                if ($e164 === null) {
                    $reason = self::junkReason($token);
                    $insDisc->execute([':table' => 'tec_employee', ':sid' => $row['id'], ':field' => 'celular', ':raw' => mb_substr($token, 0, 100), ':reason' => $reason, ':now' => $now]);
                    $discarded[] = ['tec_employee', $row['id'], 'celular', $token, $reason];
                    continue;
                }
                $insDir->execute([':kind' => 'staff', ':unidad' => 0, ':emp' => $row['id'], ':phone' => $e164, ':label' => 'celular', ':field' => 'celular', ':now' => $now]);
                $added += $insDir->rowCount();
            }
        }

        self::writeCsv($discarded);
        $ctx->out("Phone directory: {$added} numbers loaded, " . count($discarded) . ' discarded (see phone_backfill_discarded.csv)');
    }

    /** @return list<string> */
    private static function tokens(string $raw): array
    {
        $parts = preg_split('/\s*(?:\/|,|;|\r?\n|\by\b|\band\b)\s*/iu', $raw) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $clean = trim((string) preg_replace('/(?:\bext\b|\bx\b|#).*$/iu', '', $part));
            if ($clean !== '') {
                $out[] = $clean;
            }
        }
        return $out;
    }

    private static function normalize(string $token): ?string
    {
        if (!preg_match('/\d/', $token)) {
            return null;
        }
        $e164 = Phone::toE164($token, 'US');
        if ($e164 === null) {
            return null;
        }
        return self::junkReason($token) === '' ? $e164 : null;
    }

    private static function junkReason(string $token): string
    {
        $digits = preg_replace('/\D/', '', $token) ?? '';
        if ($digits === '') {
            return 'no_digits';
        }
        if (strlen($digits) === 11 && $digits[0] === '1') {
            $digits = substr($digits, 1);
        }
        if (strlen($digits) !== 10) {
            return 'bad_length';
        }
        if (preg_match('/^(\d)\1{9}$/', $digits)) {
            return 'repeated_digits';
        }
        if ($digits === '1234567890') {
            return 'sequence';
        }
        if ($digits[0] === '0' || $digits[0] === '1' || $digits[3] === '0' || $digits[3] === '1') {
            return 'invalid_nanp';
        }
        return '';
    }

    /** @param list<array{0:string,1:mixed,2:string,3:string,4:string}> $rows */
    private static function writeCsv(array $rows): void
    {
        $dir = PrivateStorage::path('reports');
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            return;
        }
        $fh = @fopen($dir . '/phone_backfill_discarded.csv', 'w');
        if ($fh === false) {
            return;
        }
        fputcsv($fh, ['source_table', 'source_id', 'field', 'raw_value', 'reason']);
        foreach ($rows as $r) {
            fputcsv($fh, $r);
        }
        fclose($fh);
    }
};
