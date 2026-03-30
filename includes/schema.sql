-- ============================================================
--  WHOIS SORGULAMA SİTESİ - Veritabanı Şeması
-- ============================================================

CREATE DATABASE IF NOT EXISTS `alanadiy_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `alanadiy_db`;

-- WHOIS sorgu geçmişi
CREATE TABLE IF NOT EXISTS `whois_queries` (
    `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `domain`        VARCHAR(255) NOT NULL,
    `tld`           VARCHAR(50)  NOT NULL,
    `sld`           VARCHAR(200) NOT NULL,
    `ip_address`    VARCHAR(45)  NOT NULL,
    `is_available`  TINYINT(1)   DEFAULT NULL COMMENT '1=müsait, 0=kayıtlı, NULL=bilinmiyor',
    `whois_raw`     MEDIUMTEXT   DEFAULT NULL,
    `registrar`     VARCHAR(255) DEFAULT NULL,
    `expiry_date`   DATE         DEFAULT NULL,
    `created_at`    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_domain`     (`domain`),
    INDEX `idx_ip`         (`ip_address`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- WHOIS cache tablosu
CREATE TABLE IF NOT EXISTS `whois_cache` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `domain`        VARCHAR(255) NOT NULL UNIQUE,
    `is_available`  TINYINT(1)   DEFAULT NULL,
    `whois_raw`     MEDIUMTEXT   DEFAULT NULL,
    `registrar`     VARCHAR(255) DEFAULT NULL,
    `registrant`    VARCHAR(255) DEFAULT NULL,
    `creation_date` VARCHAR(100) DEFAULT NULL,
    `expiry_date`   VARCHAR(100) DEFAULT NULL,
    `name_servers`  TEXT         DEFAULT NULL,
    `status`        VARCHAR(500) DEFAULT NULL,
    `cached_at`     DATETIME     DEFAULT CURRENT_TIMESTAMP,
    `expires_at`    DATETIME     NOT NULL,
    INDEX `idx_domain`  (`domain`),
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- IP rate limiting
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45)  NOT NULL,
    `query_date` DATE         NOT NULL,
    `count`      INT UNSIGNED DEFAULT 1,
    UNIQUE KEY `ip_date` (`ip_address`, `query_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Popüler domain sorguları istatistik
CREATE TABLE IF NOT EXISTS `domain_stats` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tld`         VARCHAR(50)  NOT NULL UNIQUE,
    `search_count` BIGINT UNSIGNED DEFAULT 0,
    `updated_at`  DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Örnek TLD istatistik verileri
INSERT IGNORE INTO `domain_stats` (`tld`, `search_count`) VALUES
('.com', 0), ('.net', 0), ('.org', 0), ('.com.tr', 0), ('.net.tr', 0), ('.org.tr', 0),
('.co', 0), ('.io', 0), ('.app', 0), ('.dev', 0);

-- Site ayarları tablosu (admin panelden yönetim için)
CREATE TABLE IF NOT EXISTS `site_settings` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `skey`       VARCHAR(100) NOT NULL UNIQUE,
    `sval`       TEXT         DEFAULT NULL,
    `updated_at` DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
