<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

if (!csrf_check($_POST['csrf_token'] ?? '')) {
    exit('درخواست نامعتبر (CSRF)');
}

$slug = trim((string)($_POST['slug'] ?? ''));
if ($slug === '') {
    exit('شناسه خبر نامعتبر');
}

$pdo = get_db();

$stmt = $pdo->prepare("SELECT id FROM news WHERE slug = :slug LIMIT 1");
$stmt->execute([':slug' => $slug]);
$news = $stmt->fetch();

if (!$news) {
    exit('خبر پیدا نشد');
}

$update = $pdo->prepare("
    UPDATE news
    SET is_published = 1,
        published_at = :published_at,
        updated_at = datetime('now','localtime')
    WHERE id = :id
");

$update->execute([
    ':published_at' => date('Y-m-d H:i:s'),
    ':id' => $news['id'],
]);

header('Location: news.php?published=1');
exit;
