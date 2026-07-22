<?php
/**
 * Aplica admin/db/005_tbl_fotos_estado.sql
 * Uso: php admin/db/run_005_tbl_fotos_estado.php
 */
require_once __DIR__ . '/../classes/DbConection.php';

$db = new DbConection();
$pdo = $db->openConect();

$statements = [
    "ALTER TABLE tbl_fotos ADD COLUMN estado VARCHAR(20) NOT NULL DEFAULT 'creado' AFTER observaciones",
    "UPDATE tbl_fotos
     SET estado = CASE
       WHEN foto_despues IS NOT NULL
            AND foto_despues <> ''
            AND foto_despues <> 'no_image.png'
         THEN 'finalizado'
       ELSE 'pendiente'
     END
     WHERE estado = 'creado' OR estado IS NULL OR estado = ''",
];

foreach ($statements as $i => $sql) {
    try {
        $pdo->exec($sql);
        echo ($i + 1) . ") OK\n";
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        if (stripos($msg, 'Duplicate column') !== false) {
            echo ($i + 1) . ") SKIP (column already exists)\n";
            continue;
        }
        echo ($i + 1) . ") ERROR: " . $msg . "\n";
        exit(1);
    }
}

echo "Done.\n";
