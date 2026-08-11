<?php

/**
 * Clase que contiene todas las operaciones utilizadas sobre la base de datos
 * @author SPIDERSOFTWARE
 */
class InformeTiempo
{

    public function __construct()
    {
    }

    /**
     * Lista cada entrada emparejada con su salida correspondiente
     * (siguiente salida después de esa entrada y antes de la siguiente entrada).
     * Soporta múltiples check-in / check-out el mismo día.
     */
    public static function getAll($rqst)
    {
        $db = new DbConection();
        $pdo = $db->openConect();

        $employeeTable = $db->getTable('tec_employee');
        $entryTable = $db->getTable('tec_entry');
        $exitTable = $db->getTable('tec_exit');

        $q = "SELECT
                emp.nombre,
                emp.cc,
                te.entrada AS entrada,
                te.ip AS ip_entrada,
                te.coords AS coords_entrada,
                (
                    SELECT tx.salida
                    FROM {$exitTable} tx
                    WHERE tx.cc = te.cc
                      AND tx.salida >= te.entrada
                      AND tx.salida < COALESCE(
                          (
                              SELECT MIN(te2.entrada)
                              FROM {$entryTable} te2
                              WHERE te2.cc = te.cc
                                AND te2.entrada > te.entrada
                          ),
                          '9999-12-31 23:59:59'
                      )
                    ORDER BY tx.salida ASC
                    LIMIT 1
                ) AS salida,
                (
                    SELECT tx.ip
                    FROM {$exitTable} tx
                    WHERE tx.cc = te.cc
                      AND tx.salida >= te.entrada
                      AND tx.salida < COALESCE(
                          (
                              SELECT MIN(te2.entrada)
                              FROM {$entryTable} te2
                              WHERE te2.cc = te.cc
                                AND te2.entrada > te.entrada
                          ),
                          '9999-12-31 23:59:59'
                      )
                    ORDER BY tx.salida ASC
                    LIMIT 1
                ) AS ip_salida,
                (
                    SELECT tx.coords
                    FROM {$exitTable} tx
                    WHERE tx.cc = te.cc
                      AND tx.salida >= te.entrada
                      AND tx.salida < COALESCE(
                          (
                              SELECT MIN(te2.entrada)
                              FROM {$entryTable} te2
                              WHERE te2.cc = te.cc
                                AND te2.entrada > te.entrada
                          ),
                          '9999-12-31 23:59:59'
                      )
                    ORDER BY tx.salida ASC
                    LIMIT 1
                ) AS coords_salida
              FROM {$employeeTable} emp
              INNER JOIN {$entryTable} te ON emp.cc = te.cc
              ORDER BY te.entrada DESC";

        try {
            $result = $pdo->query($q);
            $arr = [];
            if ($result) {
                foreach ($result as $valor) {
                    $arr[] = $valor;
                }
                $arrjson = ['output' => ['valid' => true, 'response' => $arr]];
            } else {
                $arrjson = Util::error_no_result();
            }
        } catch (Exception $e) {
            $arrjson = Util::error_general('Error loading time report: ' . $e->getMessage());
        }

        $db->closeConect();
        return $arrjson;
    }
}
