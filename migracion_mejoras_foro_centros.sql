USE rescat_alimentos;

-- Ejecuta este archivo una sola vez en phpMyAdmin sobre la base existente.
ALTER TABLE centers
  ADD COLUMN center_type ENUM('center','pickup_point') NOT NULL DEFAULT 'center';

CREATE TABLE IF NOT EXISTS forum_replies (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 post_id INT UNSIGNED NOT NULL,
 user_id INT UNSIGNED NOT NULL,
 body TEXT NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_forum_replies_post_created (post_id,created_at),
 CONSTRAINT fk_forum_replies_post FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
 CONSTRAINT fk_forum_replies_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
