<?php
require_once __DIR__ . '/auth/db.php';
$pdo = get_db();

try {
    // چک کردن ستون‌های فعلی
    $stmt = $pdo->query("PRAGMA table_info(votes)");
    $columns = [];
    foreach ($stmt->fetchAll() as $row) {
        $columns[$row['name']] = true;
    }

    $added = false;

    if (!isset($columns['grade'])) {
        $pdo->exec("ALTER TABLE votes ADD COLUMN grade INTEGER NOT NULL DEFAULT 0");
        $added = true;
    }

    if (!isset($columns['field'])) {
        $pdo->exec("ALTER TABLE votes ADD COLUMN field TEXT NOT NULL DEFAULT ''");
        $added = true;
    }

    if ($added) {
        echo "<div style='padding:2rem; background:#d1fae5; color:#065f46; border-radius:12px; direction:rtl; font-family:sans-serif;'>
                <h2>عملیات موفق</h2>
                <p>ستون‌های grade و field به جدول votes اضافه شدند.</p>
                <p>حالا می‌توانید این فایل را حذف کنید و دوباره رای‌گیری را تست کنید.</p>
              </div>";
    } else {
        echo "<div style='padding:2rem; background:#fee2e2; color:#991b1b; border-radius:12px; direction:rtl;'>
                ستون‌ها از قبل وجود داشتند. مشکلی نیست.
              </div>";
    }
} catch (Exception $e) {
    echo "<div style='padding:2rem; background:#fee2e2; color:#991b1b; border-radius:12px; direction:rtl;'>
            خطا: " . htmlspecialchars($e->getMessage()) . "
          </div>";
}