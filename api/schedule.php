<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../classes/Schedule.php';

require_admin();
header('Content-Type: application/json; charset=UTF-8');

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$limit = max(1, min(200, $limit));

try {
    $schedule = new Schedule(get_db());
    $history = $schedule->getHistory($limit);

    echo json_encode([
        'success' => true,
        'data' => $history,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'خطای داخلی سرور رخ داد.',
    ], JSON_UNESCAPED_UNICODE);
}
