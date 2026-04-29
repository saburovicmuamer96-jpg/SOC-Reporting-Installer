-- SOC Reporting System - System Database Schema
-- Database: soc_system

CREATE DATABASE IF NOT EXISTS soc_system
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE soc_system;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    language ENUM('en', 'de') NOT NULL DEFAULT 'en',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- Sessions table
CREATE TABLE IF NOT EXISTS sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token VARCHAR(128) NOT NULL UNIQUE,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    INDEX idx_token (token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Admin sessions (separate auth gate)
CREATE TABLE IF NOT EXISTS admin_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token VARCHAR(128) NOT NULL UNIQUE,
    ip_address VARCHAR(45) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    INDEX idx_token (token),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Audit log
CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    username VARCHAR(50) NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- Machines configuration
CREATE TABLE IF NOT EXISTS machines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slot_number TINYINT UNSIGNED NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL DEFAULT 'Unnamed Machine',
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slot (slot_number),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- Form fields configuration
CREATE TABLE IF NOT EXISTS form_fields (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    machine_id INT UNSIGNED NOT NULL,
    field_name VARCHAR(50) NOT NULL,
    field_label_en VARCHAR(100) NOT NULL,
    field_label_de VARCHAR(100) NOT NULL,
    field_type ENUM('text', 'textarea', 'number', 'dropdown', 'checkbox') NOT NULL DEFAULT 'text',
    field_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    options_json JSON NULL COMMENT 'For checkbox: default labels; for other config',
    placeholder_en VARCHAR(200) NULL,
    placeholder_de VARCHAR(200) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_machine (machine_id),
    INDEX idx_order (field_order),
    INDEX idx_active (is_active),
    FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Dropdown options
CREATE TABLE IF NOT EXISTS dropdown_options (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    field_id INT UNSIGNED NOT NULL,
    value VARCHAR(100) NOT NULL,
    label_en VARCHAR(200) NOT NULL,
    label_de VARCHAR(200) NOT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_field (field_id),
    INDEX idx_sort (sort_order),
    FOREIGN KEY (field_id) REFERENCES form_fields(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Instructions content
CREATE TABLE IF NOT EXISTS instructions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('admin', 'user') NOT NULL,
    language ENUM('en', 'de') NOT NULL,
    title VARCHAR(200) NOT NULL DEFAULT '',
    content_html LONGTEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_type_lang (type, language)
) ENGINE=InnoDB;

-- CSRF tokens
CREATE TABLE IF NOT EXISTS csrf_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_token VARCHAR(128) NOT NULL,
    csrf_token VARCHAR(128) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    INDEX idx_session (session_token),
    INDEX idx_csrf (csrf_token)
) ENGINE=InnoDB;

-- Assets table (device inventory)
CREATE TABLE IF NOT EXISTS assets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor VARCHAR(100) NOT NULL,
    model VARCHAR(150) NOT NULL,
    product_version VARCHAR(100) NULL,
    software_version VARCHAR(100) NULL,
    hostname VARCHAR(150) NULL,
    ip_address VARCHAR(45) NULL,
    machine_id INT UNSIGNED NULL,
    notes TEXT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_vendor (vendor),
    INDEX idx_machine (machine_id),
    FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Insert default machine slots
INSERT INTO machines (slot_number, name, description) VALUES
(1, 'Machine 1', 'Configure this machine name and description in the admin panel.'),
(2, 'Machine 2', 'Configure this machine name and description in the admin panel.'),
(3, 'Machine 3', 'Configure this machine name and description in the admin panel.'),
(4, 'Machine 4', 'Configure this machine name and description in the admin panel.');

-- Insert default instructions placeholders
INSERT INTO instructions (type, language, title, content_html) VALUES
('admin', 'en', 'Administrator Guide', '<h2>Administrator Guide</h2><p>Edit this content in the admin panel.</p>'),
('admin', 'de', 'Administratorhandbuch', '<h2>Administratorhandbuch</h2><p>Bearbeiten Sie diesen Inhalt im Admin-Bereich.</p>'),
('user', 'en', 'User Guide', '<h2>User Guide</h2><p>Edit this content in the admin panel.</p>'),
('user', 'de', 'Benutzerhandbuch', '<h2>Benutzerhandbuch</h2><p>Bearbeiten Sie diesen Inhalt im Admin-Bereich.</p>');

-- Create default admin user (password: Admin@SOC2024 - CHANGE IMMEDIATELY)
INSERT INTO users (username, password_hash, display_name, role) VALUES
('admin', '$2y$12$Rx9YuygputBdulp6/zG6OuShtHPrCMCRrcQPR3yVAbgcAmBXoWGAq', 'System Administrator', 'admin');
