USE rescat_alimentos;

-- Ejecuta este archivo una sola vez en instalaciones existentes.
ALTER TABLE donations
  ADD COLUMN contact_phone VARCHAR(25) NOT NULL DEFAULT '' AFTER location,
  ADD COLUMN delivery_date DATE NULL AFTER contact_phone;

ALTER TABLE forum_posts
  ADD COLUMN image_path VARCHAR(255) NULL AFTER body;

-- La fecha queda NULL para donaciones antiguas porque no se conoce su fecha programada.
