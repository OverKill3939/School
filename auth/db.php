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

    $pdo->exec($usersSql);
    $pdo->exec($eventsSql);
    $pdo->exec($logsSql);
}

function ensure_schema_sqlite(PDO $pdo): void
{
    $usersSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    phone TEXT NOT NULL UNIQUE,
    national_code TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'user' CHECK(role IN ('admin', 'user')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
SQL;

    $eventsSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS calendar_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    year INTEGER NOT NULL,
    month INTEGER NOT NULL,
    day INTEGER NOT NULL,
    title TEXT NOT NULL,
    type TEXT NOT NULL CHECK(type IN ('exam', 'event', 'extra-holiday')),
    notes TEXT NOT NULL DEFAULT '',
    created_by INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);
SQL;

    $logsSql = <<<'SQL'
CREATE TABLE IF NOT EXISTS event_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    actor_user_id INTEGER NOT NULL,
    action TEXT NOT NULL,
    entity TEXT NOT NULL DEFAULT 'calendar_event',
    entity_id INTEGER NULL,
    before_data TEXT NULL,
    after_data TEXT NULL,
    ip_address TEXT NOT NULL DEFAULT '',
    user_agent TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE RESTRICT
);
SQL;

    $indexEventSql = <<<'SQL'
CREATE INDEX IF NOT EXISTS idx_calendar_month ON calendar_events(year, month, day);
SQL;

    $indexLogsCreatedSql = <<<'SQL'
CREATE INDEX IF NOT EXISTS idx_event_logs_created_at ON event_logs(created_at);
SQL;

    $indexLogsActorSql = <<<'SQL'
CREATE INDEX IF NOT EXISTS idx_event_logs_actor ON event_logs(actor_user_id);
SQL;

    $indexLogsActionSql = <<<'SQL'
CREATE INDEX IF NOT EXISTS idx_event_logs_action ON event_logs(action);
SQL;

    $pdo->exec($usersSql);
    $pdo->exec($eventsSql);
    $pdo->exec($logsSql);
    $pdo->exec($indexEventSql);
    $pdo->exec($indexLogsCreatedSql);
    $pdo->exec($indexLogsActorSql);
    $pdo->exec($indexLogsActionSql);
}
