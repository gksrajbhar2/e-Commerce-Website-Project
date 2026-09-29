-- =====================================================================
-- CircuitHub (eSewa E-Commerce) — Database Schema
-- Import this file into MySQL/MariaDB before running the site, e.g.:
--   mysql -u root -p < database/schema.sql
--
-- SAFE TO RE-IMPORT: it drops and recreates every table, so importing it
-- again gives you a clean store with the electronics demo catalog.
-- WARNING: this erases existing users, products and orders.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS esewa_ecommerce
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE esewa_ecommerce;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS payments, order_items, orders, products, categories, users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Users (customers + admin). Role distinguishes admin dashboard access.
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(150)  NOT NULL,
  email         VARCHAR(150)  NOT NULL UNIQUE,
  password      VARCHAR(255)  NOT NULL,   -- bcrypt hash (password_hash)
  phone         VARCHAR(20)   DEFAULT NULL,
  address       VARCHAR(255)  DEFAULT NULL,
  role          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Categories
-- ---------------------------------------------------------------------
CREATE TABLE categories (
  id      INT AUTO_INCREMENT PRIMARY KEY,
  name    VARCHAR(100) NOT NULL,
  slug    VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Products
-- ---------------------------------------------------------------------
CREATE TABLE products (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  category_id   INT DEFAULT NULL,
  name          VARCHAR(200) NOT NULL,
  slug          VARCHAR(200) NOT NULL UNIQUE,
  description   TEXT,
  price         DECIMAL(10,2) NOT NULL,
  stock         INT NOT NULL DEFAULT 0,
  image         VARCHAR(255) NOT NULL DEFAULT 'no-image.svg',
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Orders — one row per checkout. transaction_uuid is what gets signed
-- and sent to eSewa, and is how the callback is matched back to an order.
-- ---------------------------------------------------------------------
CREATE TABLE orders (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  user_id           INT DEFAULT NULL,
  transaction_uuid  VARCHAR(100) NOT NULL UNIQUE,
  full_name         VARCHAR(150) NOT NULL,
  email             VARCHAR(150) NOT NULL,
  phone             VARCHAR(20)  NOT NULL,
  address           VARCHAR(255) NOT NULL,
  city              VARCHAR(100) NOT NULL,
  subtotal          DECIMAL(10,2) NOT NULL,
  delivery_charge   DECIMAL(10,2) NOT NULL DEFAULT 0,
  total_amount      DECIMAL(10,2) NOT NULL,
  payment_method    ENUM('esewa','cod') NOT NULL DEFAULT 'esewa',
  payment_status    ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
  order_status      ENUM('pending','processing','shipped','completed','cancelled') NOT NULL DEFAULT 'pending',
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Order line items (snapshot of product name/price at time of order)
-- ---------------------------------------------------------------------
CREATE TABLE order_items (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  order_id      INT NOT NULL,
  product_id    INT DEFAULT NULL,
  product_name  VARCHAR(200) NOT NULL,
  price         DECIMAL(10,2) NOT NULL,
  quantity      INT NOT NULL,
  subtotal      DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Raw payment log — every eSewa callback / status-check response is
-- stored here for auditing and troubleshooting.
-- ---------------------------------------------------------------------
CREATE TABLE payments (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  order_id          INT NOT NULL,
  transaction_uuid  VARCHAR(100) NOT NULL,
  transaction_code  VARCHAR(100) DEFAULT NULL,
  status            VARCHAR(50)  DEFAULT NULL,
  raw_response      TEXT,
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- Seed data
-- =====================================================================

-- Default admin login: admin@example.com / admin123
-- (bcrypt hash below was generated with PHP's password_hash())
INSERT INTO users (full_name, email, password, role) VALUES
('Site Admin', 'admin@example.com', '$2y$10$Zz2pPrXGMGx0vqloAg6psujIcQmoRjfsU7wqKM1iUsugFaoXxes.2', 'admin');

INSERT INTO categories (name, slug) VALUES
('Smart Devices', 'smart-devices'),
('Computer Accessories', 'computer-accessories'),
('Audio & Gaming', 'audio-gaming'),
('Storage & Memory', 'storage-memory'),
('Power & Connectivity', 'power-connectivity');

INSERT INTO products (category_id, name, slug, description, price, stock, image) VALUES
(1, 'Nova X12', 'nova-x12', '6.5" AMOLED display, 5000mAh battery, triple camera with night mode. Unlocked, dual-SIM.', 85000.00, 12, 'no-image.svg'),
(1, 'Pulse Lite 5G', 'pulse-lite-5g', 'Budget-friendly 5G smartphone with a 90Hz display and all-day battery life.', 42000.00, 20, 'no-image.svg'),
(1, 'AeroBook Pro 14', 'aerobook-pro-14', '14" laptop with a 12-core processor, 16GB RAM, 512GB SSD, and a 16-hour battery.', 145000.00, 8, 'no-image.svg'),
(1, 'StudyBook Air', 'studybook-air', 'Lightweight 13" laptop built for everyday work and study — 8GB RAM, 256GB SSD.', 68000.00, 14, 'no-image.svg'),
(1, 'FitTrack Watch', 'fittrack-watch', 'Fitness tracking smartwatch with heart-rate monitor, GPS, and 7-day battery.', 9800.00, 18, 'no-image.svg'),
(2, 'Silent Wireless Mouse', 'silent-wireless-mouse', 'Ergonomic wireless mouse with silent clicks and a rechargeable battery.', 1800.00, 50, 'no-image.svg'),
(2, 'Mecha Click Keyboard', 'mecha-click-keyboard', 'Compact mechanical keyboard with hot-swappable switches and per-key backlighting.', 4500.00, 22, 'no-image.svg'),
(3, 'AirWave ANC Headphones', 'airwave-anc-headphones', 'Over-ear Bluetooth headphones with active noise cancellation and 30-hour battery life.', 8500.00, 25, 'no-image.svg'),
(3, 'Boom Mini Speaker', 'boom-mini-speaker', 'Compact waterproof Bluetooth speaker with rich bass and 12-hour playback.', 3200.00, 30, 'no-image.svg'),
(3, 'StrikePad Gaming Controller', 'strikepad-gaming-controller', 'Wireless controller with hair-trigger buttons and programmable back paddles.', 5200.00, 20, 'no-image.svg'),
(4, 'Velocity 1TB Portable SSD', 'velocity-1tb-portable-ssd', 'Pocket-sized USB-C SSD with read speeds up to 1050MB/s — plug-and-play for any laptop.', 9500.00, 28, 'no-image.svg'),
(4, 'FlashDrive 128GB USB-C', 'flashdrive-128gb-usb-c', 'Dual USB-C/USB-A flash drive for fast transfers between phones, laptops and tablets.', 1600.00, 40, 'no-image.svg'),
(5, '65W Fast Charger', '65w-fast-charger', 'Compact GaN charger that fast-charges laptops and phones alike, single USB-C port.', 2200.00, 45, 'no-image.svg'),
(5, 'USB-C Hub 7-in-1', 'usb-c-hub-7-in-1', 'HDMI, 2x USB-A, SD/microSD, Ethernet and 100W passthrough charging in one compact hub.', 3500.00, 35, 'no-image.svg');
