-- Bongabong Visitor Management System - database schema
-- MySQL / MariaDB. Run through install.php, or paste into phpMyAdmin.

CREATE TABLE IF NOT EXISTS visitors (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  ref_no        VARCHAR(20)  NOT NULL UNIQUE,
  queue_no      INT          NOT NULL DEFAULT 0,
  name          VARCHAR(150) NOT NULL,
  sex           ENUM('Male','Female') NOT NULL,
  age           TINYINT UNSIGNED NOT NULL,
  contact       VARCHAR(11)  DEFAULT NULL,
  address       TEXT         NOT NULL,
  purpose       VARCHAR(60)  NOT NULL,
  purpose_other VARCHAR(200) DEFAULT NULL,
  signature     LONGTEXT     DEFAULT NULL,
  consent       TINYINT(1)   NOT NULL DEFAULT 0,
  visit_date    DATE         NOT NULL,
  time_in       DATETIME     NOT NULL,
  time_out      DATETIME     DEFAULT NULL,
  attended_at   DATETIME     DEFAULT NULL,
  status        ENUM('Waiting','Attended','Completed','Left') NOT NULL DEFAULT 'Waiting',
  is_demo       TINYINT(1)   NOT NULL DEFAULT 0,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_visit_date (visit_date),
  INDEX idx_purpose (purpose),
  INDEX idx_status (status),
  INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The offices and departments of the Citizen's Charter Handbook 2026.
-- code is the handbook's own id for the office, e.g. "mayor", "civil-registrar".
CREATE TABLE IF NOT EXISTS charter_offices (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  code        VARCHAR(40)  NOT NULL UNIQUE,
  name        VARCHAR(150) NOT NULL,
  short_name  VARCHAR(60)  NOT NULL,
  page        INT          NOT NULL DEFAULT 0,
  address     VARCHAR(255) NOT NULL DEFAULT '',
  contact     VARCHAR(255) NOT NULL DEFAULT '',
  sort_order  INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per service an office lists. service_type is the handbook's External
-- or Internal list; group_name is set only where an office lists its services
-- under program groups (the Agriculture Office). A service with no steps and no
-- requirements is listed without a write-up.
CREATE TABLE IF NOT EXISTS services (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  service_no       INT          NOT NULL,
  name             VARCHAR(255) NOT NULL,
  description      TEXT         NOT NULL,
  office           VARCHAR(150) NOT NULL,
  classification   VARCHAR(120) NOT NULL,
  transaction_type VARCHAR(255) NOT NULL,
  who_may_avail    TEXT         NOT NULL,
  total_fee        VARCHAR(500) NOT NULL DEFAULT 'PHP 0',
  total_time       VARCHAR(255) NOT NULL,
  sort_order       INT          NOT NULL DEFAULT 0,
  active           TINYINT(1)   NOT NULL DEFAULT 1,
  office_code      VARCHAR(40)  DEFAULT NULL,
  service_type     ENUM('external','internal') NOT NULL DEFAULT 'external',
  group_name       VARCHAR(150) DEFAULT NULL,
  updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_office (office),
  INDEX idx_office_code (office_code),
  INDEX idx_classification (classification)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_requirements (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  service_id      INT  NOT NULL,
  requirement     TEXT NOT NULL,
  where_to_secure TEXT NOT NULL,
  sort_order      INT  NOT NULL DEFAULT 0,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_steps (
  id                 INT AUTO_INCREMENT PRIMARY KEY,
  service_id         INT          NOT NULL,
  client_step        TEXT         NOT NULL,
  agency_action      TEXT         NOT NULL,
  fees               VARCHAR(500) NOT NULL DEFAULT 'None',
  processing_time    VARCHAR(255) NOT NULL,
  person_responsible TEXT         NOT NULL,
  sort_order         INT          NOT NULL DEFAULT 0,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS charter_content (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  foreword         MEDIUMTEXT   NOT NULL,
  signatory_title  VARCHAR(150) NOT NULL,
  signatory_office VARCHAR(150) NOT NULL,
  signatory_place  VARCHAR(150) NOT NULL,
  signatory_year   VARCHAR(10)  NOT NULL,
  vision           TEXT         NOT NULL,
  mission          TEXT         NOT NULL,
  address          VARCHAR(255) NOT NULL,
  contact_number   VARCHAR(60)  NOT NULL,
  email            VARCHAR(120) NOT NULL,
  office_hours     VARCHAR(120) NOT NULL,
  updated_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS concerns (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  ref_no            VARCHAR(20)  NOT NULL UNIQUE,
  name              VARCHAR(150) NOT NULL,
  email             VARCHAR(150) NOT NULL,
  contact           VARCHAR(30)  DEFAULT NULL,
  concern_type      VARCHAR(80)  NOT NULL,
  subject           VARCHAR(255) NOT NULL,
  description       TEXT         NOT NULL,
  status            ENUM('Received','Under Review','In Progress','Resolved','Closed') NOT NULL DEFAULT 'Received',
  assigned_office   VARCHAR(150) DEFAULT NULL,
  official_response TEXT         DEFAULT NULL,
  date_received     DATETIME     NOT NULL,
  date_resolved     DATETIME     DEFAULT NULL,
  is_demo           TINYINT(1)   NOT NULL DEFAULT 0,
  INDEX idx_status (status),
  INDEX idx_type (concern_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS feedback (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  ref_no            VARCHAR(20)  NOT NULL UNIQUE,
  name              VARCHAR(150) NOT NULL,
  email             VARCHAR(150) NOT NULL,
  contact           VARCHAR(30)  DEFAULT NULL,
  feedback_type     VARCHAR(80)  NOT NULL,
  message           TEXT         NOT NULL,
  status            ENUM('Received','Under Review','In Progress','Resolved','Closed') NOT NULL DEFAULT 'Received',
  official_response TEXT         DEFAULT NULL,
  date_received     DATETIME     NOT NULL,
  date_responded    DATETIME     DEFAULT NULL,
  is_demo           TINYINT(1)   NOT NULL DEFAULT 0,
  INDEX idx_status (status),
  INDEX idx_type (feedback_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS faq_entries (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  question   VARCHAR(255) NOT NULL,
  answer     TEXT         NOT NULL,
  category   VARCHAR(80)  NOT NULL DEFAULT 'General',
  sort_order INT          NOT NULL DEFAULT 0,
  active     TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CSM responses imported from the linked Google Sheet (CSV export).
CREATE TABLE IF NOT EXISTS csm_responses (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  response_date DATE         DEFAULT NULL,
  client_type   VARCHAR(80)  DEFAULT NULL,
  service       VARCHAR(255) DEFAULT NULL,
  sex           VARCHAR(20)  DEFAULT NULL,
  age           VARCHAR(20)  DEFAULT NULL,
  cc1           VARCHAR(120) DEFAULT NULL,
  cc2           VARCHAR(120) DEFAULT NULL,
  cc3           VARCHAR(120) DEFAULT NULL,
  sqd0 TINYINT DEFAULT NULL, sqd1 TINYINT DEFAULT NULL, sqd2 TINYINT DEFAULT NULL,
  sqd3 TINYINT DEFAULT NULL, sqd4 TINYINT DEFAULT NULL, sqd5 TINYINT DEFAULT NULL,
  sqd6 TINYINT DEFAULT NULL, sqd7 TINYINT DEFAULT NULL, sqd8 TINYINT DEFAULT NULL,
  remarks       TEXT         DEFAULT NULL,
  imported_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_response_date (response_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admins (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(60)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(150) NOT NULL,
  last_login    DATETIME     DEFAULT NULL,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_log (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  actor      VARCHAR(60)  NOT NULL,
  action     VARCHAR(80)  NOT NULL,
  entity     VARCHAR(60)  NOT NULL,
  entity_id  VARCHAR(40)  DEFAULT NULL,
  details    TEXT         DEFAULT NULL,
  ip_address VARCHAR(45)  DEFAULT NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Simple per-IP throttle for public form submissions.
CREATE TABLE IF NOT EXISTS rate_limit (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  ip_address VARCHAR(45) NOT NULL,
  action     VARCHAR(40) NOT NULL,
  created_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip_action (ip_address, action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
