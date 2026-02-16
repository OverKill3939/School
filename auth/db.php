<?php
declare(strict_types=1);

function get_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';
    $db = $config['db'];
    $driver = strtolower((string)($db['driver'] ?? 'sqlite'));

    if ($driver === 'sqlite') {
        $sqlitePath = (string)$db['sqlite_path'];
        $sqliteDir = dirname($sqlitePath);

        if (!is_dir($sqliteDir)) {
            mkdir($sqliteDir, 0775, true);
        }

        $dsn = 'sqlite:' . $sqlitePath;
        $pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $pdo->exec('PRAGMA foreign_keys = ON');
        ensure_schema_sqlite($pdo);
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $db['host'],
        $db['port'],
        $db['name'],
        $db['charset']
    );

    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    ensure_schema_mysql($pdo);
    return $pdo;
}

function ensure_schema_mysql(PDO $pdo): void
{
    $usersSql = <<<'SQL'
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
SQL;

    $eventsSql = <<<'SQL'
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
SQL;

    $logsSql = <<<'SQL'
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
SQL;

    $authLogsSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS auth_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event ENUM('login', 'register') NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    user_id INT UNSIGNED NULL,
    national_code CHAR(10) NOT NULL DEFAULT '',
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_auth_logs_created_at (created_at),
    INDEX idx_auth_logs_event (event),
    INDEX idx_auth_logs_user (user_id),
    CONSTRAINT fk_auth_logs_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

    $newsSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS news (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    excerpt TEXT NULL,
    content MEDIUMTEXT NOT NULL,
    image_path VARCHAR(255) NULL,
    video_path VARCHAR(255) NULL,
    author_id INT UNSIGNED NOT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_news_published_at (published_at),
    INDEX idx_news_is_published (is_published),
    CONSTRAINT fk_news_user FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

    $newsMediaSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS news_media (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    news_id BIGINT UNSIGNED NOT NULL,
    media_path VARCHAR(255) NOT NULL,
    media_type ENUM('image','video') NOT NULL DEFAULT 'image',
    position SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_news_media_news (news_id, position),
    CONSTRAINT fk_news_media_news FOREIGN KEY (news_id) REFERENCES news(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

    $schedulesSql = <<<'SQL'
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
SQL;

    $scheduleHistorySql = <<<'SQL'
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
SQL;

    $electionsSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS elections (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL DEFAULT 'Student Council Election',
    year SMALLINT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_elections_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

    $eligibleSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS election_eligible (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    election_id BIGINT UNSIGNED NOT NULL,
    grade TINYINT UNSIGNED NOT NULL,
    field VARCHAR(80) NOT NULL,
    eligible_count INT UNSIGNED NOT NULL DEFAULT 0,
    voted_count INT UNSIGNED NOT NULL DEFAULT 0,
    votes_per_student TINYINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_eligible_election_grade_field (election_id, grade, field),
    INDEX idx_eligible_election (election_id),
    CONSTRAINT fk_eligible_election FOREIGN KEY (election_id)
        REFERENCES elections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

    $candidatesSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS candidates (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    election_id BIGINT UNSIGNED NOT NULL,
    full_name VARCHAR(180) NOT NULL,
    grade TINYINT UNSIGNED NOT NULL,
    field VARCHAR(80) NOT NULL,
    photo_path VARCHAR(255) NULL,
    votes INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_candidates_election (election_id),
    CONSTRAINT fk_candidates_election FOREIGN KEY (election_id)
        REFERENCES elections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

    $votesSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS votes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    election_id BIGINT UNSIGNED NOT NULL,
    candidate_id BIGINT UNSIGNED NOT NULL,
    grade TINYINT UNSIGNED NOT NULL,
    field VARCHAR(80) NOT NULL,
    voter_key VARCHAR(64) NOT NULL DEFAULT '',
    voted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_votes_election_candidate (election_id, candidate_id),
    INDEX idx_votes_grade_field (grade, field),
    INDEX idx_votes_voter (election_id, grade, field, voter_key),
    CONSTRAINT fk_votes_election FOREIGN KEY (election_id)
        REFERENCES elections(id) ON DELETE CASCADE,
    CONSTRAINT fk_votes_candidate FOREIGN KEY (candidate_id)
        REFERENCES candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

    $pdo->exec($usersSql);
    $pdo->exec($eventsSql);
    $pdo->exec($logsSql);
    $pdo->exec($authLogsSql);
    $pdo->exec($newsSql);
    $pdo->exec($newsMediaSql);
    $pdo->exec($schedulesSql);
    $pdo->exec($scheduleHistorySql);
    $pdo->exec($electionsSql);
    $pdo->exec($eligibleSql);
    $pdo->exec($candidatesSql);
    $pdo->exec($votesSql);

    ensure_schedule_history_columns_mysql($pdo);
    ensure_news_columns_mysql($pdo);
    ensure_news_media_columns_mysql($pdo);
    ensure_election_columns_mysql($pdo);
}

function ensure_schedule_history_columns_mysql(PDO $pdo): void
{
    $columns = [];
    $stmt = $pdo->query('SHOW COLUMNS FROM schedule_history');
    foreach ($stmt->fetchAll() as $row) {
        $name = (string)($row['Field'] ?? '');
        if ($name !== '') {
            $columns[$name] = true;
        }
    }

    if (!isset($columns['grade'])) {
        $pdo->exec('ALTER TABLE schedule_history ADD COLUMN grade TINYINT UNSIGNED NULL AFTER admin_id');
    }
    if (!isset($columns['field_name'])) {
        $pdo->exec('ALTER TABLE schedule_history ADD COLUMN field_name VARCHAR(50) NULL AFTER grade');
    }
    if (!isset($columns['day'])) {
        $pdo->exec('ALTER TABLE schedule_history ADD COLUMN day TINYINT UNSIGNED NULL AFTER field_name');
    }
    if (!isset($columns['hour'])) {
        $pdo->exec('ALTER TABLE schedule_history ADD COLUMN hour TINYINT UNSIGNED NULL AFTER day');
    }
    if (!isset($columns['action'])) {
        $pdo->exec("ALTER TABLE schedule_history ADD COLUMN action VARCHAR(20) NOT NULL DEFAULT 'update' AFTER hour");
    }

    $indexExistsStmt = $pdo->prepare("SHOW INDEX FROM schedule_history WHERE Key_name = :key_name");
    $indexExistsStmt->execute([':key_name' => 'idx_schedule_history_action']);
    $indexExists = $indexExistsStmt->fetch();
    if ($indexExists === false) {
        $pdo->exec('CREATE INDEX idx_schedule_history_action ON schedule_history(action)');
    }
}

function ensure_election_columns_mysql(PDO $pdo): void
{
    $eligibleColumns = [];
    $eligibleStmt = $pdo->query('SHOW COLUMNS FROM election_eligible');
    foreach ($eligibleStmt->fetchAll() as $row) {
        $name = (string)($row['Field'] ?? '');
        if ($name !== '') {
            $eligibleColumns[$name] = true;
        }
    }

    if (!isset($eligibleColumns['votes_per_student'])) {
        $pdo->exec('ALTER TABLE election_eligible ADD COLUMN votes_per_student TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER voted_count');
    }

    $voteColumns = [];
    $voteStmt = $pdo->query('SHOW COLUMNS FROM votes');
    foreach ($voteStmt->fetchAll() as $row) {
        $name = (string)($row['Field'] ?? '');
        if ($name !== '') {
            $voteColumns[$name] = true;
        }
    }

    if (!isset($voteColumns['voter_key'])) {
        $pdo->exec("ALTER TABLE votes ADD COLUMN voter_key VARCHAR(64) NOT NULL DEFAULT '' AFTER field");
    }

    $indexExistsStmt = $pdo->prepare("SHOW INDEX FROM votes WHERE Key_name = :key_name");
    $indexExistsStmt->execute([':key_name' => 'idx_votes_voter']);
    $indexExists = $indexExistsStmt->fetch();
    if ($indexExists === false) {
        $pdo->exec('CREATE INDEX idx_votes_voter ON votes(election_id, grade, field, voter_key)');
    }
}

function ensure_schema_sqlite(PDO $pdo): void
{
    // ────────────────────────────────────────────────────────────────
    // جدول کاربران
    // ────────────────────────────────────────────────────────────────
    $usersSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    first_name      TEXT NOT NULL,
    last_name       TEXT NOT NULL,
    phone           TEXT NOT NULL UNIQUE,
    national_code   TEXT NOT NULL UNIQUE,
    password_hash   TEXT NOT NULL,
    role            TEXT NOT NULL DEFAULT 'user' CHECK(role IN ('admin', 'user')),
    created_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
SQL;

    // ────────────────────────────────────────────────────────────────
    // تقویم / رویدادها
    // ────────────────────────────────────────────────────────────────
    $eventsSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS calendar_events (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    year        INTEGER NOT NULL,
    month       INTEGER NOT NULL,
    day         INTEGER NOT NULL,
    title       TEXT NOT NULL,
    type        TEXT NOT NULL CHECK(type IN ('exam', 'event', 'extra-holiday')),
    notes       TEXT NOT NULL DEFAULT '',
    created_by  INTEGER NOT NULL,
    created_at  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);
SQL;

    // ────────────────────────────────────────────────────────────────
    // لاگ فعالیت‌ها
    // ────────────────────────────────────────────────────────────────
$logsSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS event_logs (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    actor_user_id   INTEGER NOT NULL,
    action          TEXT NOT NULL,
    entity          TEXT NOT NULL DEFAULT 'calendar_event',
    entity_id       INTEGER,
    before_data     TEXT,
    after_data      TEXT,
    ip_address      TEXT NOT NULL DEFAULT '',
    user_agent      TEXT NOT NULL DEFAULT '',
    created_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT
);
SQL;

$authLogsSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS auth_logs (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    event           TEXT NOT NULL CHECK(event IN ('login', 'register')),
    success         INTEGER NOT NULL DEFAULT 0,
    user_id         INTEGER,
    national_code   TEXT NOT NULL DEFAULT '',
    ip_address      TEXT NOT NULL DEFAULT '',
    user_agent      TEXT NOT NULL DEFAULT '',
    created_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);
SQL;

$newsSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS news (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    title         TEXT NOT NULL,
    slug          TEXT NOT NULL UNIQUE,
    excerpt       TEXT,
    content       TEXT NOT NULL,
    image_path    TEXT,
    video_path    TEXT,
    author_id     INTEGER NOT NULL,
    is_published  INTEGER NOT NULL DEFAULT 0,
    published_at  TEXT,
    created_at    TEXT NOT NULL DEFAULT (datetime('now','localtime')),
    updated_at    TEXT NOT NULL DEFAULT (datetime('now','localtime')),
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE RESTRICT
);
SQL;

$newsMediaSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS news_media (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    news_id     INTEGER NOT NULL,
    media_path  TEXT NOT NULL,
    media_type  TEXT NOT NULL DEFAULT 'image',
    position    INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT NOT NULL DEFAULT (datetime('now','localtime')),
    FOREIGN KEY (news_id) REFERENCES news(id) ON DELETE CASCADE
);
SQL;

// ────────────────────────────────────────────────────────────────
// برنامه کلاسی (ساعتی)
// ────────────────────────────────────────────────────────────────
$schedulesSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS schedules (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    grade       INTEGER NOT NULL,
    field       TEXT NOT NULL,
    day         INTEGER NOT NULL,
    hour        INTEGER NOT NULL,
    subject     TEXT NOT NULL,
    created_at  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (grade, field, day, hour)
);
SQL;

    // ────────────────────────────────────────────────────────────────
    // تاریخچه تغییرات برنامه کلاسی
    // ────────────────────────────────────────────────────────────────
    $scheduleHistorySql = <<<'SQL'
CREATE TABLE IF NOT EXISTS schedule_history (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    schedule_id     INTEGER NOT NULL,
    admin_id        INTEGER NOT NULL,
    grade           INTEGER,
    field_name      TEXT,
    day             INTEGER,
    hour            INTEGER,
    action          TEXT NOT NULL DEFAULT 'update',
    old_subject     TEXT,
    new_subject     TEXT,
    changed_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id)    REFERENCES users(id)    ON DELETE RESTRICT
);
SQL;

    // ────────────────────────────────────────────────────────────────
    // سیستم رای‌گیری شورای دانش‌آموزی (جدید)
    // ────────────────────────────────────────────────────────────────

    // انتخابات (هر دوره یک رکورد)
    $electionsSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS elections (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    title       TEXT NOT NULL DEFAULT 'شورای دانش آموزی',
    year        INTEGER NOT NULL,
    is_active   INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at  TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);
SQL;

    // تعداد مجاز رأی‌دهی برای هر پایه + رشته
    $eligibleSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS election_eligible (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    election_id     INTEGER NOT NULL,
    grade           INTEGER NOT NULL CHECK(grade IN (10, 11, 12)),
    field           TEXT NOT NULL CHECK(field IN ('شبکه و نرم افزار', 'برق', 'الکترونیک')),
    eligible_count  INTEGER NOT NULL CHECK(eligible_count >= 0),
    voted_count     INTEGER NOT NULL DEFAULT 0 CHECK(voted_count >= 0),
    votes_per_student INTEGER NOT NULL DEFAULT 1 CHECK(votes_per_student >= 1),
    created_at      TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    UNIQUE(election_id, grade, field)
);
SQL;

    // لیست کاندیداها
    $candidatesSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS candidates (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    election_id INTEGER NOT NULL,
    full_name   TEXT NOT NULL,
    grade       INTEGER NOT NULL CHECK(grade IN (10, 11, 12)),
    field       TEXT NOT NULL,
    photo_path  TEXT,
    votes       INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE
);
SQL;

    // ثبت تک‌تک آراء (بدون ستون ip_address چون دیگر برای محدود کردن استفاده نمی‌شود)
    $votesSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS votes (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    election_id  INTEGER NOT NULL,
    candidate_id INTEGER NOT NULL,
    grade        INTEGER NOT NULL,
    field        TEXT NOT NULL,
    voter_key    TEXT NOT NULL DEFAULT '',
    voted_at     TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (election_id)  REFERENCES elections(id)   ON DELETE CASCADE,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id)  ON DELETE CASCADE
);
SQL;

    // ────────────────────────────────────────────────────────────────
    // اجرای تمام CREATE TABLE ها
    // ────────────────────────────────────────────────────────────────
    $pdo->exec($usersSql);
    $pdo->exec($eventsSql);
    $pdo->exec($logsSql);
    $pdo->exec($authLogsSql);
    $pdo->exec($newsSql);
    $pdo->exec($newsMediaSql);
    $pdo->exec($schedulesSql);
    $pdo->exec($scheduleHistorySql);
    $pdo->exec($electionsSql);
    $pdo->exec($eligibleSql);
    $pdo->exec($candidatesSql);
    $pdo->exec($votesSql);

    // ────────────────────────────────────────────────────────────────
    // ایندکس‌ها
    // ────────────────────────────────────────────────────────────────
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_calendar_month            ON calendar_events(year, month, day)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_event_logs_created_at     ON event_logs(created_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_event_logs_actor          ON event_logs(actor_user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_event_logs_action         ON event_logs(action)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_auth_logs_created_at      ON auth_logs(created_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_auth_logs_event           ON auth_logs(event)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_auth_logs_user            ON auth_logs(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_news_published_at         ON news(published_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_news_is_published         ON news(is_published)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_news_media_news           ON news_media(news_id, position)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_schedules_grade_field      ON schedules(grade, field)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_schedule_history_schedule  ON schedule_history(schedule_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_schedule_history_admin     ON schedule_history(admin_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_schedule_history_action    ON schedule_history(action)");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_votes_election_candidate   ON votes(election_id, candidate_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_votes_grade_field          ON votes(grade, field)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_eligible_election          ON election_eligible(election_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_candidates_election        ON candidates(election_id)");

    // ────────────────────────────────────────────────────────────────
    // اضافه کردن ستون‌های جدید به schedule_history در صورت نبود
    // ────────────────────────────────────────────────────────────────
    ensure_schedule_history_columns_sqlite($pdo);
    ensure_news_columns_sqlite($pdo);
    ensure_election_columns_sqlite($pdo);
}

function ensure_news_columns_mysql(PDO $pdo): void
{
    $columns = [];
    $stmt = $pdo->query('SHOW COLUMNS FROM news');
    foreach ($stmt->fetchAll() as $row) {
        $name = (string)($row['Field'] ?? '');
        if ($name !== '') {
            $columns[$name] = true;
        }
    }

    $alter = [];
    if (!isset($columns['excerpt'])) {
        $alter[] = "ADD COLUMN excerpt TEXT NULL AFTER slug";
    }
    if (!isset($columns['video_path'])) {
        $alter[] = "ADD COLUMN video_path VARCHAR(255) NULL AFTER image_path";
    }
    if (!isset($columns['is_published'])) {
        $alter[] = "ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 0 AFTER author_id";
    }
    if (!isset($columns['published_at'])) {
        $alter[] = "ADD COLUMN published_at DATETIME NULL AFTER is_published";
    }
    if ($alter !== []) {
        $sql = "ALTER TABLE news " . implode(', ', $alter);
        $pdo->exec($sql);
    }
}

function ensure_news_columns_sqlite(PDO $pdo): void
{
    $columns = [];
    $stmt = $pdo->query('PRAGMA table_info(news)');
    foreach ($stmt->fetchAll() as $row) {
        $name = (string)($row['name'] ?? '');
        if ($name !== '') {
            $columns[$name] = true;
        }
    }

    if (!isset($columns['excerpt'])) {
        $pdo->exec("ALTER TABLE news ADD COLUMN excerpt TEXT NULL");
    }
    if (!isset($columns['video_path'])) {
        $pdo->exec("ALTER TABLE news ADD COLUMN video_path TEXT NULL");
    }
    if (!isset($columns['is_published'])) {
        $pdo->exec("ALTER TABLE news ADD COLUMN is_published INTEGER NOT NULL DEFAULT 0");
    }
    if (!isset($columns['published_at'])) {
        $pdo->exec("ALTER TABLE news ADD COLUMN published_at TEXT NULL");
    }
}

function ensure_news_media_columns_mysql(PDO $pdo): void
{
    // جدول خودکار ایجاد می‌شود در ensure_schema_mysql؛ این تابع برای ایندکس/سازگاری آینده است.
}

function ensure_news_media_columns_sqlite(PDO $pdo): void
{
    // جدول خودکار ایجاد می‌شود در ensure_schema_sqlite؛ این تابع برای سازگاری آینده است.
}
function ensure_schedule_history_columns_sqlite(PDO $pdo): void
{
    $columns = [];
    $stmt = $pdo->query('PRAGMA table_info(schedule_history)');
    foreach ($stmt->fetchAll() as $row) {
        $name = (string)($row['name'] ?? '');
        if ($name !== '') {
            $columns[$name] = true;
        }
    }

    if (!isset($columns['grade'])) {
        $pdo->exec('ALTER TABLE schedule_history ADD COLUMN grade INTEGER NULL');
    }
    if (!isset($columns['field_name'])) {
        $pdo->exec('ALTER TABLE schedule_history ADD COLUMN field_name TEXT NULL');
    }
    if (!isset($columns['day'])) {
        $pdo->exec('ALTER TABLE schedule_history ADD COLUMN day INTEGER NULL');
    }
    if (!isset($columns['hour'])) {
        $pdo->exec('ALTER TABLE schedule_history ADD COLUMN hour INTEGER NULL');
    }
    if (!isset($columns['action'])) {
        $pdo->exec("ALTER TABLE schedule_history ADD COLUMN action TEXT NOT NULL DEFAULT 'update'");
    }
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_schedule_history_action ON schedule_history(action)');
}

function ensure_election_columns_sqlite(PDO $pdo): void
{
    $eligibleColumns = [];
    $eligibleStmt = $pdo->query('PRAGMA table_info(election_eligible)');
    foreach ($eligibleStmt->fetchAll() as $row) {
        $name = (string)($row['name'] ?? '');
        if ($name !== '') {
            $eligibleColumns[$name] = true;
        }
    }

    if (!isset($eligibleColumns['votes_per_student'])) {
        $pdo->exec("ALTER TABLE election_eligible ADD COLUMN votes_per_student INTEGER NOT NULL DEFAULT 1");
    }

    $voteColumns = [];
    $voteStmt = $pdo->query('PRAGMA table_info(votes)');
    foreach ($voteStmt->fetchAll() as $row) {
        $name = (string)($row['name'] ?? '');
        if ($name !== '') {
            $voteColumns[$name] = true;
        }
    }

    if (!isset($voteColumns['voter_key'])) {
        $pdo->exec("ALTER TABLE votes ADD COLUMN voter_key TEXT NOT NULL DEFAULT ''");
    }

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_votes_voter ON votes(election_id, grade, field, voter_key)');
}
