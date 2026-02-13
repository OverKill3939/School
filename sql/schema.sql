CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL UNIQUE,
    national_code CHAR(10) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
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
