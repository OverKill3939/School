<?php
// attendance_db.php
declare(strict_types=1);

function get_attendance_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $path = __DIR__ . '/../data/attendance.sqlite';

    // ساخت پوشه در صورت نبودن
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $pdo = new PDO("sqlite:" . $path, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    $pdo->exec("PRAGMA foreign_keys = ON;");

    // ساخت جدول در صورت نبودن
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS attendance (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            date            TEXT NOT NULL,                  -- '2025-02-17'
            grade           INTEGER NOT NULL CHECK(grade IN (1,2,3)),
            field           TEXT NOT NULL,
            student_name    TEXT NOT NULL COLLATE NOCASE,
            absent_hours    TEXT NOT NULL,                  -- '1,2,4'
            notes           TEXT DEFAULT '',
            recorded_by     INTEGER NOT NULL,
            created_at      TEXT DEFAULT (datetime('now','localtime')),
            UNIQUE (date, grade, field, student_name COLLATE NOCASE)
        )
    ");

    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_att_date_grade_field 
        ON attendance (date, grade, field)
    ");

    return $pdo;
}