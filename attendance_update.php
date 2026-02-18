<?php
// attendance_update.php
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

$id     = (int)($_POST['id'] ?? 0);
$date   = $_POST['date']   ?? '';
$grade  = (int)($_POST['grade']  ?? 0);
$field  = $_POST['field']  ?? '';
$name   = trim($_POST['student_name'] ?? '');
$hours  = (array)($_POST['hours'] ?? []);
$notes  = trim($_POST['notes'] ?? '');

if ($id <= 0 ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ||
    !in_array($grade, [1,2,3], true) ||
    !in_array($field, ['کامپیوتر','الکترونیک','برق'], true) ||
    empty($name)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'داده‌های ورودی نامعتبر']);
    exit;
}

$hours = array_filter($hours, fn($h) => in_array($h, ['1','2','3','4']));
if (empty($hours)) {
    echo json_encode(['success' => false, 'error' => 'حداقل یک زنگ باید انتخاب شود']);
    exit;
}

sort($hours);
$hours_str = implode(',', $hours);

$pdo = get_attendance_db();

try {
    $stmt = $pdo->prepare("
        UPDATE attendance 
        SET student_name = ?,
            absent_hours = ?,
            notes = ?,
            recorded_by = ?
        WHERE id = ? 
          AND date = ? 
          AND grade = ? 
          AND field = ?
    ");

    $updated = $stmt->execute([
        $name,
        $hours_str,
        $notes,
        (int)$user['id'],
        $id,
        $date,
        $grade,
        $field
    ]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => 'رکورد پیدا نشد یا تغییری اعمال نشد']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'تغییرات با موفقیت ذخیره شد',
        'redirect' => "attendance.php?date=$date&grade=$grade&field=" . urlencode($field)
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'خطا در به‌روزرسانی: ' . $e->getMessage()
    ]);
}