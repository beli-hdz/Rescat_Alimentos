CREATE DATABASE IF NOT EXISTS rescat_alimentos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rescat_alimentos;
CREATE TABLE IF NOT EXISTS users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, email VARCHAR(150) NOT NULL UNIQUE,
 username VARCHAR(30) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL,
 role ENUM('user','admin') NOT NULL DEFAULT 'user', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS products (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, description TEXT NOT NULL,
 category VARCHAR(60) NOT NULL, price DECIMAL(10,2) NOT NULL DEFAULT 0, stock INT UNSIGNED NOT NULL DEFAULT 0,
 emoji VARCHAR(12) NOT NULL DEFAULT '🥫', active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS centers (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(160) NOT NULL, center_type ENUM('center','pickup_point') NOT NULL DEFAULT 'center', description TEXT NULL,
 address VARCHAR(255) NOT NULL, phone VARCHAR(60) NULL, website VARCHAR(255) NULL,
 contact_person VARCHAR(120) NULL, email VARCHAR(150) NULL, opening_hours VARCHAR(180) NULL,
 latitude DECIMAL(10,7) NULL, longitude DECIMAL(10,7) NULL,
 active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS donations (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, food_name VARCHAR(120) NOT NULL,
 quantity VARCHAR(80) NOT NULL, category VARCHAR(60) NOT NULL DEFAULT 'Otros', image_path VARCHAR(255) NULL,
 location VARCHAR(180) NOT NULL, contact_phone VARCHAR(25) NOT NULL, delivery_date DATE NOT NULL, pickup_point_id INT UNSIGNED NULL, center_id INT UNSIGNED NULL, expiry_date DATE NULL, notes TEXT NULL,
 status ENUM('available','in_process','picked_up','delivered','completed','cancelled') NOT NULL DEFAULT 'available',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_donations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_donations_center FOREIGN KEY (center_id) REFERENCES centers(id) ON DELETE SET NULL,
 CONSTRAINT fk_donations_pickup_point FOREIGN KEY (pickup_point_id) REFERENCES centers(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS forum_posts (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, donation_id INT UNSIGNED NULL,
 title VARCHAR(160) NOT NULL, body TEXT NOT NULL, image_path VARCHAR(255) NULL,
 post_type ENUM('community','status','delivered') NOT NULL DEFAULT 'community', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_forum_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_forum_donation FOREIGN KEY (donation_id) REFERENCES donations(id) ON DELETE SET NULL
) ENGINE=InnoDB;
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
CREATE TABLE IF NOT EXISTS delivery_updates (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, donation_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL,
 status ENUM('in_process','picked_up','delivered','completed','cancelled') NOT NULL,
 note VARCHAR(1000) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_updates_donation FOREIGN KEY (donation_id) REFERENCES donations(id) ON DELETE CASCADE,
 CONSTRAINT fk_updates_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS orders (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL, total DECIMAL(10,2) NOT NULL DEFAULT 0,
 status VARCHAR(30) NOT NULL DEFAULT 'Registrado', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS order_items (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_id INT UNSIGNED NOT NULL, product_id INT UNSIGNED NOT NULL,
 quantity INT UNSIGNED NOT NULL, unit_price DECIMAL(10,2) NOT NULL,
 CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
 CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;
-- RescatAlimentos es una plataforma de donaciones: no se cargan productos ni precios de venta.

CREATE TABLE IF NOT EXISTS notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 title VARCHAR(160) NOT NULL,
 message VARCHAR(500) NOT NULL,
 url VARCHAR(255) NOT NULL DEFAULT 'notificaciones.php',
 type ENUM('donation','forum','pickup','system') NOT NULL DEFAULT 'system',
 is_read TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_notifications_user_read (user_id,is_read,created_at),
 CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
