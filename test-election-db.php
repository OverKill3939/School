<?php
require_once __DIR__ . '/auth/db.php';
$pdo = get_db();

echo "<pre dir='ltr'>";

// ۱. انتخابات فعال هست؟
$stmt = $pdo->query("SELECT * FROM elections WHERE is_active = 1 LIMIT 1");
$election = $stmt->fetch();
echo "انتخابات فعال: " . ($election ? "بله (ID: {$election['id']}, عنوان: {$election['title']})" : "خیر") . "\n\n";

// ۲. تعداد واجدین شرایط
$stmt = $pdo->query("SELECT * FROM election_eligible ORDER BY grade, field");
echo "واجدین شرایط:\n";
print_r($stmt->fetchAll());

// ۳. کاندیداها
$stmt = $pdo->query("SELECT * FROM candidates ORDER BY field, grade");
echo "\nکاندیداها:\n";
print_r($stmt->fetchAll());

// ۴. آرای ثبت‌شده (اگر هست)
$stmt = $pdo->query("SELECT COUNT(*) as total_votes FROM votes");
echo "\nتعداد کل آرای ثبت‌شده: " . $stmt->fetchColumn() . "\n";

echo "</pre>";