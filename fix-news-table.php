<?php
require_once __DIR__ . '/auth/db.php';
require_once __DIR__ . '/auth/helpers.php';

$pdo = get_db();

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS news (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            title       TEXT NOT NULL,
            slug        TEXT NOT NULL UNIQUE,
            content     TEXT NOT NULL,
            image_path  TEXT,
            author_id   INTEGER NOT NULL,
            published_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
            is_published INTEGER NOT NULL DEFAULT 1,
            created_at  TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
            updated_at  TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
            FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE RESTRICT
        )
    ");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_news_published ON news(is_published, published_at DESC)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_news_author    ON news(author_id)");

    echo "<div style='direction:rtl; font-family:sans-serif; padding:2rem; background:#d1fae5; border-radius:12px;'>
            <h2 style='color:#065f46;'>عملیات موفق</h2>
            <p>جدول news ساخته شد (یا از قبل وجود داشت).<br>
               حالا می‌تونی این فایل رو حذف کنی و news.php رو باز کنی.</p>
          </div>";

} catch (Exception $e) {
    echo "<div style='direction:rtl; padding:2rem; background:#fee2e2; border-radius:12px;'>
            <h2 style='color:#991b1b;'>خطا</h2>
            <pre>" . htmlspecialchars($e->getMessage()) . "</pre>
          </div>";
}