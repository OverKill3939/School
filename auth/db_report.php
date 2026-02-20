<?php
declare(strict_types=1);

function get_report_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';
    $sqlitePath = $config['db_report']['sqlite_path'] ?? __DIR__ . '/../data/report_cards.sqlite';

    $sqliteDir = dirname($sqlitePath);
    if (!is_dir($sqliteDir)) {
        mkdir($sqliteDir, 0775, true);
    }

    $dsn = 'sqlite:' . $sqlitePath;
    $pdo = new PDO($dsn, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');     // بهتر برای عملکرد
    $pdo->exec('PRAGMA synchronous = NORMAL');   // تعادل بین سرعت و ایمنی

    ensure_report_schema($pdo);

    return $pdo;
}

function ensure_report_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS report_cards (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            uploader_id     INTEGER NOT NULL,
            national_code   TEXT NOT NULL CHECK(length(national_code) = 10),
            image_path      TEXT NOT NULL,
            grade           INTEGER,                    -- اختیاری: پایه تحصیلی
            term            TEXT,                       -- اختیاری: نیمسال/ترم
            uploaded_at     TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
            updated_at      TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
        )
    ");

    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_report_national_code 
        ON report_cards(national_code)
    ");

    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_report_uploader 
        ON report_cards(uploader_id)
    ");
}