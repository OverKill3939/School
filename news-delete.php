<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Method Not Allowed");
}

if (!csrf_check($_POST['csrf_token'] ?? '')) {
    die("خطای امنیتی");
}

$slug = $_POST['slug'] ?? '';
if (!$slug) {
    die("شناسه نامعتبر");
}

$pdo = get_db();

$stmt = $pdo->prepare("SELECT id, image_path, video_path FROM news WHERE slug = ? LIMIT 1");
$stmt->execute([$slug]);
$news = $stmt->fetch();

if (!$news) {
    die("خبر پیدا نشد");
}

// جمع‌آوری تمام رسانه‌های خبر (اصلی + گالری)
$mediaStmt = $pdo->prepare("SELECT media_path FROM news_media WHERE news_id = ?");
$mediaStmt->execute([$news['id']]);
$galleryPaths = array_column($mediaStmt->fetchAll() ?: [], 'media_path');

// حذف فایل‌ها اگر وجود داشت (اول مسیر جدید، سپس مسیر قدیمی public برای تطبیق نسخه‌های قبلی)
$paths = [];
if (!empty($news['image_path'])) {
    $paths[] = __DIR__ . $news['image_path'];            // /uploads/...
    $paths[] = __DIR__ . '/public' . $news['image_path']; // مسیر قدیمی
}
if (!empty($news['video_path'])) {
    $paths[] = __DIR__ . $news['video_path'];
    $paths[] = __DIR__ . '/public' . $news['video_path'];
}
foreach ($galleryPaths as $mediaPath) {
    if (!$mediaPath) {
        continue;
    }
    $paths[] = __DIR__ . $mediaPath;
    $paths[] = __DIR__ . '/public' . $mediaPath;
}
foreach ($paths as $p) {
    if (file_exists($p)) {
        @unlink($p);
    }
}

$deleteStmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
$deleteStmt->execute([$news['id']]);

header("Location: news.php?deleted=1");
exit;
