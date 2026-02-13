<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../classes/Schedule.php';

require_login();
header('Content-Type: application/json; charset=UTF-8');

$grade = isset($_GET['grade']) ? (int)$_GET['grade'] : 0;
$field = trim((string)($_GET['field'] ?? ''));
$allowedFields = ['کامپیوتر', 'الکترونیک', 'برق'];

if ($grade < 1 || $grade > 3 || !in_array($field, $allowedFields, true)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'اطلاعات ورودی معتبر نیست.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $schedule = new Schedule(get_db());
    $rows = $schedule->getSchedule($grade, $field);

    $formatted = [];
    foreach ($rows as $item) {
        $day = (int)$item['day'];
        $hour = (int)$item['hour'];
        $formatted[$day][$hour] = (string)$item['subject'];
    }

    echo json_encode([
        'success' => true,
        'data' => $formatted,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'خطای داخلی سرور رخ داد.',
    ], JSON_UNESCAPED_UNICODE);
}
