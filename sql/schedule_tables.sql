-- Weekly schedule tables (MySQL)
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
