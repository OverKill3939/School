<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/helpers.php';
require_login();

if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}

try {
    $pdo = get_db();
    $stmt = $pdo->query(
        "SELECT id, title, slug, published_at
         FROM news
         WHERE is_published = 1
         ORDER BY published_at DESC, id DESC
         LIMIT 1"
    );
    $news = $stmt->fetch();

    if (!is_array($news) || empty($news)) {
        echo json_encode([
            'ok' => true,
            'news' => null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'news' => [
            'id' => (int)$news['id'],
            'title' => (string)$news['title'],
            'slug' => (string)$news['slug'],
            'published_at' => (string)$news['published_at'],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Unable to fetch latest news.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
