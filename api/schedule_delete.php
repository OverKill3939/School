<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../auth/db.php';
require_once __DIR__ . '/../classes/Schedule.php';

require_admin();
header('Content-Type: application/json; charset=UTF-8');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method Not Allowed',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$csrfToken = (string)($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
if ($csrfToken !== '' && !csrf_check($csrfToken)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'توکن امنیتی معتبر نیست.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$grade = isset($_POST['grade']) ? (int)$_POST['grade'] : -1;
$field = trim((string)($_POST['field'] ?? ''));
$day = isset($_POST['day']) ? (int)$_POST['day'] : -1;
$hour = isset($_POST['hour']) ? (int)$_POST['hour'] : -1;
$allowedFields = ['کامپیوتر', 'الکترونیک', 'برق'];

if ($grade < 1 || $grade > 3 || !in_array($field, $allowedFields, true) || $day < 0 || $day > 4 || $hour < 0 || $hour > 3) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'اطلاعات ورودی معتبر نیست.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = current_user();
$adminId = (int)($user['id'] ?? 0);
if ($adminId <= 0) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'نشست کاربر معتبر نیست.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $schedule = new Schedule(get_db());
    $schedule->deleteCell($grade, $field, $day, $hour, $adminId);

    echo json_encode([
        'success' => true,
        'message' => 'حذف با موفقیت انجام شد.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'خطای داخلی سرور رخ داد.',
    ], JSON_UNESCAPED_UNICODE);
}
