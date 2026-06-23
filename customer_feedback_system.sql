-- Database: customer_feedback_system

CREATE DATABASE IF NOT EXISTS customer_feedback_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE customer_feedback_system;

-- Drop existing tables if needed
-- CAUTION: Running these DROP statements will DELETE ALL existing data in these tables.
-- If you have existing data you want to keep, DO NOT run these DROP statements.
-- Instead, just run the ALTER TABLE and UPDATE statements below.
DROP TABLE IF EXISTS feedback;
DROP TABLE IF EXISTS flavors;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS suggestions;

-- Customers Table
CREATE TABLE IF NOT EXISTS customers (
  customer_id INT AUTO_INCREMENT PRIMARY KEY,
  fullname VARCHAR(100) NOT NULL,
  age INT UNSIGNED NOT NULL,
  gender ENUM('Male', 'Female', 'Other') NOT NULL,
  telephone VARCHAR(20) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Flavors Table (with image_path)
CREATE TABLE IF NOT EXISTS flavors (
  flavor_id INT AUTO_INCREMENT PRIMARY KEY,
  category VARCHAR(50) NOT NULL,
  flavor_name VARCHAR(100) NOT NULL,
  image_path VARCHAR(255) NULL -- Added image_path column, NULLable initially
) ENGINE=InnoDB;

-- Feedback Table with descriptive primary key name
CREATE TABLE IF NOT EXISTS feedback (
  feedback_id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT DEFAULT NULL,
  flavor_id INT NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  comment TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE SET NULL,
  FOREIGN KEY (flavor_id) REFERENCES flavors(flavor_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Suggestions Table with descriptive primary key name
CREATE TABLE IF NOT EXISTS suggestions (
  suggestion_id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT DEFAULT NULL,
  suggestion_text TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(customer_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Insert default flavors
INSERT INTO flavors (category, flavor_name) VALUES
('Milk Tea', 'Classic Milk Tea'),
('Milk Tea', 'Okinawa Milk Tea'),
('Milk Tea', 'Matsa Milk Tea'),
('Milk Tea', 'Cookies Milk Tea'),
('Milk Tea', 'Taro Milk Tea'),
('Milk Tea', 'Honeydew Milk Tea'),
('Fruit Tea', 'Strawberry Fruit Tea'),
('Fruit Tea', 'Peach Fruit Tea'),
('Fruit Tea', 'Apple Fruit Tea'),
('Snacks', 'French Fries'),
('Snacks', 'Spring Rolls'),
('Snacks', 'Chicken Nuggets'),
('Snacks', 'Burger');


-- UPDATE statements to add image paths for existing flavors
-- IMPORTANT: Replace 'uploads/filename.jpg' with the actual path and filename of your images.
-- Make sure the filenames match the ones in your 'uploads' folder (e.g., Apple.jpg, Burger.jpg, etc.)

UPDATE flavors SET image_path = 'uploads/Classic.jpg' WHERE flavor_name = 'Classic Milk Tea';
UPDATE flavors SET image_path = 'uploads/Okinawa.jpg' WHERE flavor_name = 'Okinawa Milk Tea';
UPDATE flavors SET image_path = 'uploads/Matsa.jpg' WHERE flavor_name = 'Matsa Milk Tea';
UPDATE flavors SET image_path = 'uploads/Cookies.jpg' WHERE flavor_name = 'Cookies Milk Tea';
UPDATE flavors SET image_path = 'uploads/Taro.jpg' WHERE flavor_name = 'Taro Milk Tea';
UPDATE flavors SET image_path = 'uploads/Honey.jpg' WHERE flavor_name = 'Honeydew Milk Tea';
UPDATE flavors SET image_path = 'uploads/Strawberry.jpg' WHERE flavor_name = 'Strawberry Fruit Tea';
UPDATE flavors SET image_path = 'uploads/Peach.jpg' WHERE flavor_name = 'Peach Fruit Tea';
UPDATE flavors SET image_path = 'uploads/Apple.jpg' WHERE flavor_name = 'Apple Fruit Tea'; -- Assuming Apple Fruit Tea, adjust if this is actually Milk Tea
UPDATE flavors SET image_path = 'uploads/Fries.jpg' WHERE flavor_name = 'French Fries';
UPDATE flavors SET image_path = 'uploads/SpringRolls.jpg' WHERE flavor_name = 'Spring Rolls';
UPDATE flavors SET image_path = 'uploads/ChickenNuggets.jpg' WHERE flavor_name = 'Chicken Nuggets';
UPDATE flavors SET image_path = 'uploads/Burger.jpg' WHERE flavor_name = 'Burger'; -- Corrected 'Buger.jpg' to 'Burger.jpg'