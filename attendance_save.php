<?php
// attendance_save.php
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
    echo json_encode(['success' => false, 'error' => 'توکن CSRF نامعتبر است']);
    exit;
}

$user = current_user();
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'دسترسی غیرمجاز']);
    exit;
}

$date   = $_POST['date']   ?? '';
$grade  = (int)($_POST['grade']  ?? 0);
$field  = $_POST['field']  ?? '';
$students = $_POST['students'] ?? [];

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ||
    !in_array($grade, [1,2,3], true) ||
    !in_array($field, ['کامپیوتر','الکترونیک','برق'], true) ||
    !is_array($students) || empty($students)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'داده‌های ورودی نامعتبر']);
    exit;
}

$pdo = get_attendance_db();
$recorded_by = (int)$user['id'];

try {
    $stmt = $pdo->prepare("
        INSERT INTO attendance 
        (date, grade, field, student_name, absent_hours, notes, recorded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $pdo->beginTransaction();
    $count = 0;

    foreach ($students as $s) {
        $name = trim($s['name'] ?? '');
        if (empty($name)) continue;

        $hours = (array)($s['hours'] ?? []);
        $hours = array_filter($hours, fn($h) => in_array($h, ['1','2','3','4']));
        if (empty($hours)) continue;

        sort($hours);
        $hours_str = implode(',', $hours);

        $notes = trim($s['notes'] ?? '');

        $stmt->execute([
            $date,
            $grade,
            $field,
            $name,
            $hours_str,
            $notes,
            $recorded_by
        ]);

        $count++;
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "تعداد $count رکورد با موفقیت ذخیره شد",
        'redirect' => "attendance.php?date=$date&grade=$grade&field=" . urlencode($field)
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'خطا در ذخیره‌سازی: ' . $e->getMessage()
    ]);
}
$_SESSION['attendance_message'] = "تعداد $count رکورد با موفقیت ذخیره شد";
header("Location: attendance.php?date=$date&grade=$grade&field=" . urlencode($field));
exit;