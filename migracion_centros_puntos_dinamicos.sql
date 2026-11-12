-- Ejecutar una sola vez en phpMyAdmin sobre la base rescat_alimentos.
USE rescat_alimentos;
ALTER TABLE centers
  ADD COLUMN contact_person VARCHAR(120) NULL AFTER website,
  ADD COLUMN email VARCHAR(150) NULL AFTER contact_person,
  ADD COLUMN opening_hours VARCHAR(180) NULL AFTER email;
