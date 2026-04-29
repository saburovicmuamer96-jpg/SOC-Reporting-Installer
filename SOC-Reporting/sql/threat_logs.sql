-- SOC Reporting System - Threat Logs Table
-- Stores CVE/threat notifications sent to departments

USE soc_system;

CREATE TABLE IF NOT EXISTS threat_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cve_id VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    severity ENUM('critical', 'high', 'medium', 'low', 'info') NOT NULL DEFAULT 'medium',
    affected_systems TEXT NULL COMMENT 'Comma-separated list or JSON',
    departments_notified TEXT NULL COMMENT 'Comma-separated departments',
    mitigation TEXT NULL,
    status ENUM('new', 'notified', 'patched', 'mitigated', 'closed') NOT NULL DEFAULT 'new',
    cvss_score DECIMAL(3,1) NULL,
    published_date DATE NULL,
    notified_at DATETIME NULL,
    patched_at DATETIME NULL,
    mitigated_at DATETIME NULL,
    closed_at DATETIME NULL,
    created_by VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_cve_id (cve_id),
    INDEX idx_severity (severity),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_published_date (published_date)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
