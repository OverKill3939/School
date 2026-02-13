<?php
session_start();
header('Content-Type: application/json');

// بررسی آیا کاربر وارد شده است
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'شما وارد سیستم نشده‌اید']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Schedule.php';

try {
    $grade = (int)($_GET['grade'] ?? 0);
    $field = $_GET['field'] ?? '';
    
    if ($grade <= 0 || empty($field)) {
        echo json_encode(['success' => false, 'error' => 'اطلاعات نامعتبر']);
        exit;
    }
    
    $schedule = new Schedule($pdo);
    $schedules = $schedule->getSchedule($grade, $field);
    
    // تنظیم داده‌ها در فرمت مناسب
    $formatted = [];
    foreach ($schedules as $item) {
        $day = $item['day'];
        $hour = $item['hour'];
        $formatted[$day][$hour] = $item['subject'];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $formatted
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'خطای سرور: ' . $e->getMessage()
    ]);
}
?>