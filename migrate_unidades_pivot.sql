-- Migration: Many-to-many relationship between users and HOA units
-- Creates a pivot table and migrates existing single-unidad data
--
-- Las FK se omiten intencionalmente porque los tipos exactos de las columnas `id`
-- en las tablas padre no están definidos en el código (CREATE TABLE está en la BD).
-- La aplicación ya maneja la integridad referencial vía PHP (DELETE antes de INSERT).

CREATE TABLE IF NOT EXISTS tec_usuarios_has_tbl_unidades (
  tec_usuarios_id INT UNSIGNED NOT NULL,
  tbl_unidades_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (tec_usuarios_id, tbl_unidades_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migrate existing single-unidad assignments to the pivot table
INSERT IGNORE INTO tec_usuarios_has_tbl_unidades (tec_usuarios_id, tbl_unidades_id)
SELECT id, tbl_unidad_id FROM tec_usuarios WHERE tbl_unidad_id > 0;
