-- Ejecutar UNA SOLA VEZ en phpMyAdmin sobre la base rescat_alimentos.
-- Separa el punto elegido por el donante del centro de donaciones asignado por administración.
USE rescat_alimentos;

ALTER TABLE donations
  ADD COLUMN pickup_point_id INT UNSIGNED NULL AFTER delivery_date,
  ADD CONSTRAINT fk_donations_pickup_point
    FOREIGN KEY (pickup_point_id) REFERENCES centers(id) ON DELETE SET NULL;

-- Recupera las asignaciones antiguas que en realidad eran puntos de recolección.
UPDATE donations d
INNER JOIN centers p ON p.id = d.center_id AND p.center_type = 'pickup_point'
SET d.pickup_point_id = p.id, d.location = p.name, d.center_id = NULL;

-- Para donaciones antiguas guardadas solo por nombre, vincula únicamente puntos
-- de recolección al nuevo campo. Los centros quedan independientes.
UPDATE donations d
INNER JOIN centers p ON p.center_type = 'pickup_point'
  AND LOWER(TRIM(d.location)) = LOWER(TRIM(p.name))
SET d.pickup_point_id = p.id
WHERE d.pickup_point_id IS NULL;
