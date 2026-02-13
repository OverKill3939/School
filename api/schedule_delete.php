<?php
session_start();
header('Content-Type: application/json');

// بررسی آیا کاربر ادمین است
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'دسترسی غیرمجاز']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Schedule.php';

try {
    $grade = (int)$_POST['grade'] ?? null;
    $field = $_POST['field'] ?? null;
    $day = (int)$_POST['day'] ?? null;
    $hour = (int)$_POST['hour'] ?? null;
    
    if (!$grade || !$field || $day === null || $hour === null) {
        echo json_encode(['success' => false, 'error' => 'اطلاعات نامعتبر']);
        exit;
    }
    
    $schedule = new Schedule($pdo);
    
    if ($schedule->deleteCell($grade, $field, $day, $hour, $_SESSION['user_id'])) {
        echo json_encode(['success' => true, 'message' => 'حذف شد']);
    } else {
        echo json_encode(['success' => false, 'error' => 'خطا در حذف']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>