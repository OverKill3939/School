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

$stmt = $pdo->prepare("SELECT id, image_path FROM news WHERE slug = ? AND is_published = 1 LIMIT 1");
$stmt->execute([$slug]);
$news = $stmt->fetch();

if (!$news) {
    die("خبر پیدا نشد");
}

// حذف عکس اگر وجود داشت
if ($news['image_path'] && file_exists(__DIR__ . '/public' . $news['image_path'])) {
    @unlink(__DIR__ . '/public' . $news['image_path']);
}

$deleteStmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
$deleteStmt->execute([$news['id']]);

header("Location: news.php?deleted=1");
exit;