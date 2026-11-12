-- Ejecutar UNA SOLA VEZ en phpMyAdmin sobre la base rescat_alimentos.
USE rescat_alimentos;

ALTER TABLE centers
  ADD COLUMN latitude DECIMAL(10,7) NULL AFTER opening_hours,
  ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude;
