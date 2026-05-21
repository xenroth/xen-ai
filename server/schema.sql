-- XEN A.I Pro license database schema
-- Run this once on your MySQL server before deploying license-api.php

CREATE DATABASE IF NOT EXISTS xenroth_licenses
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE xenroth_licenses;

-- ── License keys (one row per key you sell) ───────────────────────────────────
CREATE TABLE IF NOT EXISTS license_keys (
  id               INT          UNSIGNED NOT NULL AUTO_INCREMENT,
  `key`            VARCHAR(64)  NOT NULL,            -- e.g. XEN-AB12-CD34-EF56-GH78
  status           ENUM('active','suspended','expired') NOT NULL DEFAULT 'active',
  max_activations  TINYINT      UNSIGNED NOT NULL DEFAULT 1,
  customer_email   VARCHAR(191) DEFAULT NULL,
  customer_name    VARCHAR(191) DEFAULT NULL,
  notes            TEXT         DEFAULT NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Per-domain activations ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS license_activations (
  id           INT          UNSIGNED NOT NULL AUTO_INCREMENT,
  license_id   INT          UNSIGNED NOT NULL,
  domain       VARCHAR(191) NOT NULL,               -- bare domain, no scheme/port/www
  activated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_activation (license_id, domain),
  CONSTRAINT fk_activation_key
    FOREIGN KEY (license_id) REFERENCES license_keys (id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Sample key (for local testing) ────────────────────────────────────────────
-- Password: replace status with 'suspended' to revoke without deleting
INSERT INTO license_keys (`key`, status, max_activations, customer_email, customer_name, notes)
VALUES ('XEN-TEST-0000-0000-0000', 'active', 1, 'test@example.com', 'Test User', 'Local dev key');
