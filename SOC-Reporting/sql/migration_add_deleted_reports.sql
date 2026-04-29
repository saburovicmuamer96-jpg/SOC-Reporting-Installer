-- SOC Reporting System - Migration: Add Deleted Reports Tables
-- Run this on each machine database (soc_machine_1, soc_machine_2, soc_machine_3, soc_machine_4)
-- Usage: mysql -u [user] -p soc_machine_1 < migration_add_deleted_reports.sql
--        mysql -u [user] -p soc_machine_2 < migration_add_deleted_reports.sql
--        etc.

-- Deleted reports (soft delete archive)
CREATE TABLE IF NOT EXISTS deleted_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    original_report_id BIGINT UNSIGNED NOT NULL,
    event_id VARCHAR(30) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    username VARCHAR(50) NOT NULL,
    machine_slot TINYINT UNSIGNED NOT NULL,
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
