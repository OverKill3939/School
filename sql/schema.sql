CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL UNIQUE,
    national_code CHAR(10) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    profile_image_path VARCHAR(255) NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS calendar_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    year SMALLINT UNSIGNED NOT NULL,
    month TINYINT UNSIGNED NOT NULL,
    day TINYINT UNSIGNED NOT NULL,
    title VARCHAR(120) NOT NULL,
    type ENUM('exam', 'event', 'extra-holiday') NOT NULL,
    notes VARCHAR(255) NOT NULL DEFAULT '',
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_calendar_month (year, month, day),
    CONSTRAINT fk_calendar_events_user FOREIGN KEY (created_by)
        REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT UNSIGNED NOT NULL,
    action VARCHAR(20) NOT NULL,
    entity VARCHAR(40) NOT NULL DEFAULT 'calendar_event',
    entity_id BIGINT UNSIGNED NULL,
    before_data TEXT NULL,
    after_data TEXT NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_logs_created_at (created_at),
    INDEX idx_event_logs_actor (actor_user_id),
    INDEX idx_event_logs_action (action),
    CONSTRAINT fk_event_logs_user FOREIGN KEY (actor_user_id)
        REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_management_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT UNSIGNED NOT NULL,
    action ENUM('create', 'delete') NOT NULL,
    target_user_id INT UNSIGNED NULL,
    target_first_name VARCHAR(100) NOT NULL DEFAULT '',
    target_last_name VARCHAR(100) NOT NULL DEFAULT '',
    target_phone VARCHAR(20) NOT NULL DEFAULT '',
    target_national_code CHAR(10) NOT NULL DEFAULT '',
    target_role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_mgmt_logs_created_at (created_at),
    INDEX idx_user_mgmt_logs_actor (actor_user_id),
    INDEX idx_user_mgmt_logs_action (action),
    CONSTRAINT fk_user_mgmt_logs_actor FOREIGN KEY (actor_user_id)
        REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schedules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    grade TINYINT UNSIGNED NOT NULL,
    field VARCHAR(50) NOT NULL,
    day TINYINT UNSIGNED NOT NULL,
    hour TINYINT UNSIGNED NOT NULL,
    subject VARCHAR(150) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_schedules_slot (grade, field, day, hour),
    INDEX idx_schedules_grade_field (grade, field)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schedule_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    schedule_id BIGINT UNSIGNED NOT NULL,
    admin_id INT UNSIGNED NOT NULL,
    grade TINYINT UNSIGNED NULL,
    field_name VARCHAR(50) NULL,
    day TINYINT UNSIGNED NULL,
    hour TINYINT UNSIGNED NULL,
    action VARCHAR(20) NOT NULL DEFAULT 'update',
    old_subject VARCHAR(150) NULL,
    new_subject VARCHAR(150) NULL,
    changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_schedule_history_schedule (schedule_id),
    INDEX idx_schedule_history_admin (admin_id),
    INDEX idx_schedule_history_action (action),
    CONSTRAINT fk_schedule_history_schedule FOREIGN KEY (schedule_id)
        REFERENCES schedules(id) ON DELETE CASCADE,
    CONSTRAINT fk_schedule_history_admin FOREIGN KEY (admin_id)
        REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
