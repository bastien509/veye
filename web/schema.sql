-- Veye (petiht.com/veye) MySQL schema. Safe to run more than once.
-- Veye never stores the text or screenshots people check: scans keep only
-- the verdict, where the AI ran and how long it took.

CREATE TABLE IF NOT EXISTS veye_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(40) NOT NULL,
  display_name VARCHAR(80) NOT NULL,
  pass_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','user','demo') NOT NULL DEFAULT 'user',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  daily_limit INT UNSIGNED NOT NULL DEFAULT 200,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_veye_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS veye_settings (
  k VARCHAR(64) NOT NULL,
  v MEDIUMTEXT NOT NULL,
  is_secret TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by INT UNSIGNED NULL,
  PRIMARY KEY (k)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS veye_ext_tokens (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  label VARCHAR(60) NOT NULL DEFAULT 'Chrome extension',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME NULL,
  expires_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_veye_ext_tokens_hash (token_hash),
  KEY ix_veye_ext_tokens_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS veye_scans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  client ENUM('web','extension','android') NOT NULL DEFAULT 'web',
  source ENUM('on_device','in_cloud','rules_only') NOT NULL,
  mode VARCHAR(20) NOT NULL,
  verdict ENUM('safe','suspicious','scam') NOT NULL,
  risk TINYINT UNSIGNED NOT NULL DEFAULT 0,
  scam_type VARCHAR(40) NOT NULL DEFAULT 'none',
  lang CHAR(2) NOT NULL DEFAULT 'en',
  ms INT UNSIGNED NOT NULL DEFAULT 0,
  had_image TINYINT(1) NOT NULL DEFAULT 0,
  redacted_items SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_veye_scans_user_time (user_id, created_at),
  KEY ix_veye_scans_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS veye_reports (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  scam_type VARCHAR(40) NOT NULL,
  verdict ENUM('safe','suspicious','scam') NOT NULL,
  risk TINYINT UNSIGNED NOT NULL DEFAULT 0,
  domain VARCHAR(190) NOT NULL DEFAULT '',
  snippet VARCHAR(400) NOT NULL DEFAULT '',
  red_flags VARCHAR(1000) NOT NULL DEFAULT '',
  lang CHAR(2) NOT NULL DEFAULT 'en',
  status ENUM('pending','approved','hidden') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_veye_reports_status (status, created_at),
  KEY ix_veye_reports_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS veye_rate_limits (
  bucket CHAR(40) NOT NULL,
  window_start INT UNSIGNED NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (bucket, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS veye_audit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  action VARCHAR(40) NOT NULL,
  detail VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_veye_audit_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
