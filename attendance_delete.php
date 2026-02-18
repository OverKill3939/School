<?php
// attendance_delete.php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_once __DIR__ . '/attendance_db.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

if (!csrf_check($_POST['csrf_token'] ?? '')) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'توکن CSRF نامعتبر']);
    exit;
}

$user = current_user();
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'دسترسی غیرمجاز']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'شناسه نامعتبر']);
    exit;
}

$pdo = get_attendance_db();

try {
    $stmt = $pdo->prepare("DELETE FROM attendance WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'رکورد پیدا نشد']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'رکورد با موفقیت حذف شد'
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'خطا در حذف: ' . $e->getMessage()
    ]);
}