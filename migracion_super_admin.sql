-- Ejecutar una sola vez en phpMyAdmin después de seleccionar rescat_alimentos.
-- Permite distinguir administradores normales de super administradores.
ALTER TABLE users MODIFY role ENUM('user','admin','super_admin') NOT NULL DEFAULT 'user';

-- Después de crear una cuenta normal desde el sitio, conviértela en super admin:
-- Sustituye 'TU_USUARIO' por el nombre de usuario de esa cuenta.
-- UPDATE users SET role='super_admin' WHERE username='TU_USUARIO';
