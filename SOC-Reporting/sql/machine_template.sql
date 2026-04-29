-- SOC Reporting System - Machine Database Schema Template
-- Replace {N} with machine number (1-4)
-- Database: soc_machine_{N}

CREATE DATABASE IF NOT EXISTS soc_machine_{N}
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE soc_machine_{N};

-- Reports table
CREATE TABLE IF NOT EXISTS reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(30) NOT NULL UNIQUE,
    user_id INT UNSIGNED NOT NULL,
    username VARCHAR(50) NOT NULL,
    machine_slot TINYINT UNSIGNED NOT NULL DEFAULT {N},
    status ENUM('draft', 'submitted', 'reviewed', 'closed') NOT NULL DEFAULT 'submitted',
    notes TEXT NULL,
    incident_time DATETIME NULL,
    reaction_time_seconds INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_event_id (event_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_date_time (created_at, event_id)
) ENGINE=InnoDB;

-- Report field data (stores dynamic form values)
CREATE TABLE IF NOT EXISTS report_data (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT UNSIGNED NOT NULL,
    field_id INT UNSIGNED NOT NULL,
    field_name VARCHAR(50) NOT NULL,
    field_value TEXT NULL,
    INDEX idx_report (report_id),
    INDEX idx_field (field_id),
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Event ID counter (for sequential numbering per day)
CREATE TABLE IF NOT EXISTS event_counters (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date_key DATE NOT NULL UNIQUE,
    last_sequence INT UNSIGNED NOT NULL DEFAULT 0,
    INDEX idx_date (date_key)
) ENGINE=InnoDB;

-- Deleted reports (soft delete archive)
CREATE TABLE IF NOT EXISTS deleted_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    original_report_id BIGINT UNSIGNED NOT NULL,
    event_id VARCHAR(30) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    username VARCHAR(50) NOT NULL,
    machine_slot TINYINT UNSIGNED NOT NULL DEFAULT {N},
    status ENUM('draft', 'submitted', 'reviewed', 'closed') NOT NULL DEFAULT 'submitted',
    notes TEXT NULL,
    incident_time DATETIME NULL,
    reaction_time_seconds INT NULL,
    created_at DATETIME NOT NULL,
    deleted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_by VARCHAR(50) NOT NULL,
    INDEX idx_event_id (event_id),
    INDEX idx_deleted_at (deleted_at),
    INDEX idx_deleted_by (deleted_by)
) ENGINE=InnoDB;

-- Deleted report field data
CREATE TABLE IF NOT EXISTS deleted_report_data (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    deleted_report_id BIGINT UNSIGNED NOT NULL,
    field_id INT UNSIGNED NOT NULL,
    field_name VARCHAR(50) NOT NULL,
    field_value TEXT NULL,
    INDEX idx_deleted_report (deleted_report_id),
    INDEX idx_field (field_id),
    FOREIGN KEY (deleted_report_id) REFERENCES deleted_reports(id) ON DELETE CASCADE
) ENGINE=InnoDB;
