-- Poultry Farm Management System - schema (MySQL 5.7+/MariaDB 10.3+)
CREATE DATABASE IF NOT EXISTS `poultry_farm` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `poultry_farm`;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL,
  full_name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','worker') NOT NULL DEFAULT 'worker',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS houses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  house_number SMALLINT UNSIGNED NOT NULL,
  status ENUM('active','empty','cleaning','maintenance') NOT NULL DEFAULT 'empty',
  notes VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_houses_number (house_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS worker_houses (
  user_id INT UNSIGNED NOT NULL,
  house_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, house_id),
  CONSTRAINT fk_wh_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_wh_house FOREIGN KEY (house_id) REFERENCES houses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS production_cycles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  house_id INT UNSIGNED NOT NULL,
  cycle_name VARCHAR(120) NOT NULL,
  initial_population INT UNSIGNED NOT NULL,
  placement_date DATE NOT NULL,
  expected_days SMALLINT UNSIGNED NOT NULL DEFAULT 70,
  supplier VARCHAR(160) NULL,
  notes TEXT NULL,
  status ENUM('active','closed') NOT NULL DEFAULT 'active',
  closed_date DATE NULL,
  close_notes TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  active_house_id INT UNSIGNED GENERATED ALWAYS AS (IF(status = 'active', house_id, NULL)) STORED,
  UNIQUE KEY uq_one_active_cycle_per_house (active_house_id),
  KEY idx_cycles_house (house_id, status),
  KEY idx_cycles_placement (placement_date),
  CONSTRAINT fk_cycle_house FOREIGN KEY (house_id) REFERENCES houses(id),
  CONSTRAINT fk_cycle_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE production_cycles SET status = 'closed' WHERE status = 'archived';
ALTER TABLE production_cycles MODIFY status ENUM('active','closed') NOT NULL DEFAULT 'active';

CREATE TABLE IF NOT EXISTS death_causes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  requires_detail TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_death_cause_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS work_task_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order SMALLINT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_task_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS daily_reports (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cycle_id INT UNSIGNED NOT NULL,
  report_date DATE NOT NULL,
  worker_id INT UNSIGNED NOT NULL,
  feed_bags DECIMAL(8,2) NOT NULL DEFAULT 0,
  deaths INT UNSIGNED NOT NULL DEFAULT 0,
  standard_deaths INT UNSIGNED NULL,
  death_cause_id INT UNSIGNED NULL,
  death_cause_detail VARCHAR(500) NULL,
  work_description TEXT NULL,
  notes TEXT NULL,
  status ENUM('submitted','voided') NOT NULL DEFAULT 'submitted',
  revision INT UNSIGNED NOT NULL DEFAULT 0,
  submission_token CHAR(64) NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_one_report_per_cycle_day (cycle_id, report_date),
  UNIQUE KEY uq_submission_token (submission_token),
  KEY idx_reports_date (report_date),
  KEY idx_reports_worker (worker_id),
  CONSTRAINT chk_reports_feed CHECK (feed_bags >= 0),
  CONSTRAINT fk_report_cycle FOREIGN KEY (cycle_id) REFERENCES production_cycles(id),
  CONSTRAINT fk_report_worker FOREIGN KEY (worker_id) REFERENCES users(id),
  CONSTRAINT fk_report_creator FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT fk_report_cause FOREIGN KEY (death_cause_id) REFERENCES death_causes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS daily_report_tasks (
  report_id INT UNSIGNED NOT NULL,
  task_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (report_id, task_id),
  CONSTRAINT fk_drt_report FOREIGN KEY (report_id) REFERENCES daily_reports(id) ON DELETE CASCADE,
  CONSTRAINT fk_drt_task FOREIGN KEY (task_id) REFERENCES work_task_categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS population_adjustments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cycle_id INT UNSIGNED NOT NULL,
  adjustment_date DATE NOT NULL,
  type ENUM('arrival','removal') NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  reason VARCHAR(500) NOT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_adj_cycle (cycle_id),
  CONSTRAINT chk_adj_qty CHECK (quantity > 0),
  CONSTRAINT fk_adj_cycle FOREIGN KEY (cycle_id) REFERENCES production_cycles(id),
  CONSTRAINT fk_adj_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feed_inventory_transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  txn_date DATE NOT NULL,
  type ENUM('opening','purchase','adjustment') NOT NULL,
  bags DECIMAL(10,2) NOT NULL,
  reference VARCHAR(120) NULL,
  notes VARCHAR(500) NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_feed_txn_date (txn_date),
  CONSTRAINT fk_feedtxn_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS finance_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type ENUM('expense','revenue') NOT NULL,
  name VARCHAR(80) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_fin_cat (type, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS expenses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  txn_date DATE NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  house_id INT UNSIGNED NULL,
  cycle_id INT UNSIGNED NULL,
  payment_method VARCHAR(60) NULL,
  reference VARCHAR(120) NULL,
  notes VARCHAR(500) NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_exp_date (txn_date),
  KEY idx_exp_cycle (cycle_id),
  CONSTRAINT chk_exp_amount CHECK (amount > 0),
  CONSTRAINT fk_exp_cat FOREIGN KEY (category_id) REFERENCES finance_categories(id),
  CONSTRAINT fk_exp_house FOREIGN KEY (house_id) REFERENCES houses(id),
  CONSTRAINT fk_exp_cycle FOREIGN KEY (cycle_id) REFERENCES production_cycles(id),
  CONSTRAINT fk_exp_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS revenue (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  txn_date DATE NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  description VARCHAR(255) NOT NULL,
  amount DECIMAL(15,2) NOT NULL,
  house_id INT UNSIGNED NULL,
  cycle_id INT UNSIGNED NULL,
  payment_method VARCHAR(60) NULL,
  reference VARCHAR(120) NULL,
  notes VARCHAR(500) NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_rev_date (txn_date),
  KEY idx_rev_cycle (cycle_id),
  CONSTRAINT chk_rev_amount CHECK (amount > 0),
  CONSTRAINT fk_rev_cat FOREIGN KEY (category_id) REFERENCES finance_categories(id),
  CONSTRAINT fk_rev_house FOREIGN KEY (house_id) REFERENCES houses(id),
  CONSTRAINT fk_rev_cycle FOREIGN KEY (cycle_id) REFERENCES production_cycles(id),
  CONSTRAINT fk_rev_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS farm_settings (
  setting_key VARCHAR(60) NOT NULL PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,
  entity_type VARCHAR(40) NOT NULL,
  entity_id INT UNSIGNED NULL,
  old_values LONGTEXT NULL,
  new_values LONGTEXT NULL,
  reason VARCHAR(500) NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_entity (entity_type, entity_id),
  KEY idx_audit_created (created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_la_user (username, attempted_at),
  KEY idx_la_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Single source of truth for chicken population:
-- living = placed + arrivals - removals - deaths (voided reports are excluded)
CREATE OR REPLACE VIEW v_cycle_stats AS
SELECT c.id, c.house_id, h.house_number, c.cycle_name, c.initial_population, c.placement_date,
  c.expected_days, c.supplier, c.notes, c.status, c.closed_date, c.close_notes, c.created_at,
  COALESCE(a.arrivals,0) AS arrivals, COALESCE(a.removals,0) AS removals,
  COALESCE(r.deaths,0) AS deaths, COALESCE(r.feed_total,0) AS feed_total,
  COALESCE(r.report_count,0) AS report_count, r.last_report_date,
  (CAST(c.initial_population AS SIGNED) + COALESCE(a.arrivals,0) - COALESCE(a.removals,0) - COALESCE(r.deaths,0)) AS living
FROM production_cycles c
JOIN houses h ON h.id = c.house_id
LEFT JOIN (SELECT cycle_id,
    SUM(CASE WHEN type='arrival' THEN quantity ELSE 0 END) AS arrivals,
    SUM(CASE WHEN type='removal' THEN quantity ELSE 0 END) AS removals
  FROM population_adjustments GROUP BY cycle_id) a ON a.cycle_id = c.id
LEFT JOIN (SELECT cycle_id, SUM(deaths) AS deaths, SUM(feed_bags) AS feed_total,
    COUNT(*) AS report_count, MAX(report_date) AS last_report_date
  FROM daily_reports WHERE status = 'submitted' GROUP BY cycle_id) r ON r.cycle_id = c.id;
