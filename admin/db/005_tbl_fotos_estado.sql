-- Estado del reporte de actividades (tbl_fotos)
-- Valores: creado | pendiente | finalizado

ALTER TABLE tbl_fotos
  ADD COLUMN estado VARCHAR(20) NOT NULL DEFAULT 'creado'
  AFTER observaciones;

-- Backfill: con foto después real = finalizado; resto = pendiente
UPDATE tbl_fotos
SET estado = CASE
  WHEN foto_despues IS NOT NULL
       AND foto_despues <> ''
       AND foto_despues <> 'no_image.png'
    THEN 'finalizado'
  ELSE 'pendiente'
END
WHERE estado = 'creado' OR estado IS NULL OR estado = '';
