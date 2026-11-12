-- Ejecutar una sola vez en phpMyAdmin sobre la base rescat_alimentos.
USE rescat_alimentos;

-- Vincula las donaciones antiguas con el centro/punto registrado cuyo nombre
-- coincide con la ubicación que ya estaba guardada en la donación.
UPDATE donations d
INNER JOIN centers c
  ON LOWER(TRIM(d.location)) = LOWER(TRIM(c.name))
  AND c.active = 1
SET d.center_id = c.id
WHERE d.center_id IS NULL;

-- A partir de esta migración, publicar_donacion.php guarda center_id
-- automáticamente para todas las nuevas donaciones.
