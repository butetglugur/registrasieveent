-- Skema database Presensi Event v2
-- Dijalankan otomatis oleh installer (/install). Bisa juga diimpor manual via phpMyAdmin.
-- Nama tabel sengaja berbeda dari versi lama (attendees/admins/settings) agar data lama aman.

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  username VARCHAR(40) NOT NULL,
  email VARCHAR(150) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(20) NOT NULL DEFAULT 'staff',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  last_login_ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY users_username_unique (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS events (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug VARCHAR(80) NOT NULL,
  title VARCHAR(150) NOT NULL,
  subtitle VARCHAR(200) NULL,
  description TEXT NULL,
  location VARCHAR(200) NULL,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'open',
  quota INT UNSIGNED NULL,
  closes_at DATETIME NULL,
  group_link VARCHAR(255) NULL,
  success_message TEXT NULL,
  redirect_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  theme VARCHAR(20) NOT NULL DEFAULT 'violet',
  dedupe_wa TINYINT(1) NOT NULL DEFAULT 1,
  show_email TINYINT(1) NOT NULL DEFAULT 0,
  show_address TINYINT(1) NOT NULL DEFAULT 1,
  show_representative TINYINT(1) NOT NULL DEFAULT 1,
  require_email TINYINT(1) NOT NULL DEFAULT 0,
  require_address TINYINT(1) NOT NULL DEFAULT 0,
  require_representative TINYINT(1) NOT NULL DEFAULT 0,
  representative_label VARCHAR(60) NULL,
  fields TEXT NULL,
  send_reminder TINYINT(1) NOT NULL DEFAULT 1,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY events_slug_unique (slug),
  KEY events_status_index (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registrations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_id INT UNSIGNED NOT NULL,
  code VARCHAR(16) NOT NULL,
  name VARCHAR(120) NOT NULL,
  wa VARCHAR(20) NOT NULL,
  email VARCHAR(150) NULL,
  address VARCHAR(255) NULL,
  representative VARCHAR(150) NULL,
  extra TEXT NULL,
  ip VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  checked_in_at DATETIME NULL,
  checked_in_by INT UNSIGNED NULL,
  reminded_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY registrations_code_unique (code),
  KEY registrations_event_created_index (event_id, created_at),
  KEY registrations_event_wa_index (event_id, wa),
  KEY registrations_created_index (created_at),
  KEY registrations_checkin_index (event_id, checked_in_at),
  CONSTRAINT registrations_event_fk FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_settings (
  `key` VARCHAR(60) NOT NULL,
  value TEXT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  `key` CHAR(64) NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  reset_at INT UNSIGNED NOT NULL,
  PRIMARY KEY (`key`),
  KEY rate_limits_reset_index (reset_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NULL,
  action VARCHAR(50) NOT NULL,
  description VARCHAR(255) NOT NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY activity_logs_created_index (created_at),
  KEY activity_logs_user_index (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- v2.1: antrean & riwayat notifikasi ke peserta (WhatsApp / email / webhook)
CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  registration_id INT UNSIGNED NULL,
  kind VARCHAR(20) NOT NULL DEFAULT 'registration',
  channel VARCHAR(20) NOT NULL,
  provider VARCHAR(20) NOT NULL,
  recipient VARCHAR(190) NOT NULL,
  subject VARCHAR(200) NULL,
  message TEXT NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'pending',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_error VARCHAR(255) NULL,
  next_attempt_at DATETIME NULL,
  sent_at DATETIME NULL,
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY notifications_queue_index (status, next_attempt_at),
  KEY notifications_registration_index (registration_id),
  KEY notifications_created_index (created_at),
  CONSTRAINT notifications_registration_fk FOREIGN KEY (registration_id) REFERENCES registrations (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
