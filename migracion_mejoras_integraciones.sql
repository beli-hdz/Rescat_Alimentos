USE rescat_alimentos;

-- Coordenadas opcionales de la persona que publica una donación.
ALTER TABLE donations
  ADD COLUMN latitude DECIMAL(10,7) NULL,
  ADD COLUMN longitude DECIMAL(10,7) NULL;

-- Archivos adicionales (fotografías y PDF) asociados a cada donación.
CREATE TABLE IF NOT EXISTS donation_attachments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  donation_id INT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_donation_attachments_donation (donation_id),
  CONSTRAINT fk_donation_attachments_donation
    FOREIGN KEY (donation_id) REFERENCES donations(id) ON DELETE CASCADE
) ENGINE=InnoDB;
