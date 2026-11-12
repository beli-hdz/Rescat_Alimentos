-- Respalda primero la base desde phpMyAdmin > Exportar.
USE rescat_alimentos;
-- Ejecuta estas líneas una sola vez si las columnas aún no existen.
ALTER TABLE donations ADD COLUMN category VARCHAR(60) NOT NULL DEFAULT 'Otros';
ALTER TABLE donations ADD COLUMN image_path VARCHAR(255) NULL;
-- Los puntos permitidos se validan en el formulario y también en el servidor PHP.
-- Los usuarios solo consultan sus propias donaciones; el administrador conserva acceso al inventario completo.
