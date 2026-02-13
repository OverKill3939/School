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

if (!csrf_check($_POST['csrf_token'] ?? null)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'error' => 'توکن امنیتی معتبر نیست.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$grade = isset($_POST['grade']) ? (int)$_POST['grade'] : 0;
$field = trim((string)($_POST['field'] ?? ''));
$allowedFields = ['کامپیوتر', 'الکترونیک', 'برق'];

if ($grade < 1 || $grade > 3 || !in_array($field, $allowedFields, true)) {
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

$changes = [];
$changesJson = trim((string)($_POST['changes_json'] ?? ''));

if ($changesJson !== '') {
    $decoded = json_decode($changesJson, true);
    if (!is_array($decoded)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'فرمت تغییرات معتبر نیست.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }

        $day = isset($item['day']) ? (int)$item['day'] : -1;
        $hour = isset($item['hour']) ? (int)$item['hour'] : -1;
        $subject = trim((string)($item['subject'] ?? ''));

        if ($day < 0 || $day > 4 || $hour < 0 || $hour > 3) {
            http_response_code(422);
            echo json_encode([
                'success' => false,
                'error' => 'روز یا ساعت معتبر نیست.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (mb_strlen($subject) > 150) {
            $subject = mb_substr($subject, 0, 150);
        }

        $slotKey = $day . ':' . $hour;
        $changes[$slotKey] = [
            'day' => $day,
            'hour' => $hour,
            'subject' => $subject,
        ];
    }
} else {
    // مسیر سازگاری با نسخه قدیمی فرم
    $scheduleData = $_POST['schedule'][$grade][$field] ?? null;
    if (!is_array($scheduleData)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'error' => 'هیچ تغییری برای ذخیره ارسال نشده است.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    for ($day = 0; $day <= 4; $day++) {
        $hours = $scheduleData[$day] ?? [];
        if (!is_array($hours)) {
            continue;
        }

        for ($hour = 0; $hour <= 3; $hour++) {
            $subject = trim((string)($hours[$hour] ?? ''));
            if (mb_strlen($subject) > 150) {
                $subject = mb_substr($subject, 0, 150);
            }

            $slotKey = $day . ':' . $hour;
            $changes[$slotKey] = [
                'day' => $day,
                'hour' => $hour,
                'subject' => $subject,
            ];
        }
    }
}

if ($changes === []) {
    echo json_encode([
        'success' => true,
        'changed' => 0,
        'message' => 'تغییری برای ذخیره وجود ندارد.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = get_db();
$schedule = new Schedule($pdo);

try {
    $pdo->beginTransaction();

    foreach ($changes as $change) {
        $schedule->saveCell(
            $grade,
            $field,
            (int)$change['day'],
            (int)$change['hour'],
            (string)$change['subject'],
            $adminId
        );
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'changed' => count($changes),
        'message' => 'برنامه با موفقیت ذخیره شد.',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'خطا در ذخیره برنامه رخ داد.',
    ], JSON_UNESCAPED_UNICODE);
}
