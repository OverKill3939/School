<?php
session_start();
header('Content-Type: application/json');

// فقط ادمین‌ها می‌توانند تاریخچه را ببینند
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'دسترسی غیرمجاز']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Schedule.php';

try {
    $schedule = new Schedule($pdo);
    $history = $schedule->getHistory(50);
    
    echo json_encode([
        'success' => true,
        'data' => $history
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>