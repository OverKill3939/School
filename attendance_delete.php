<?php
// attendance_delete.php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_once __DIR__ . '/attendance_db.php';

require_admin();

$grades = attendance_allowed_grades();
$fields = attendance_allowed_fields();

$defaultDate = (new DateTimeImmutable('today'))->format('Y-m-d');
$defaultGrade = (int)array_key_first($grades);
$defaultField = $fields[0] ?? 'شبکه و نرم افزار';

$date = normalize_attendance_date((string)($_POST['date'] ?? ''));
$grade = normalize_attendance_grade($_POST['grade'] ?? null);
$field = normalize_attendance_field((string)($_POST['field'] ?? ''));

$redirect = attendance_redirect_url(
    $date ?? $defaultDate,
    $grade ?? $defaultGrade,
    $field ?? $defaultField
);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    attendance_handle_error('روش درخواست نامعتبر است.', $redirect, 405);
}

if (!csrf_check($_POST['csrf_token'] ?? null)) {
    attendance_handle_error('توکن امنیتی معتبر نیست. لطفا دوباره تلاش کنید.', $redirect, 422);
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    attendance_handle_error('شناسه رکورد معتبر نیست.', $redirect, 422);
}

$user = current_user();
$actorId = (int)($user['id'] ?? 0);
if ($actorId <= 0) {
    attendance_handle_error('دسترسی نامعتبر است.', $redirect, 403);
}

$pdo = get_attendance_db();

$findStmt = $pdo->prepare('SELECT * FROM attendance WHERE id = ? LIMIT 1');
$findStmt->execute([$id]);
$existing = $findStmt->fetch(PDO::FETCH_ASSOC);

if (!is_array($existing)) {
    attendance_handle_error('رکورد موردنظر پیدا نشد.', $redirect, 404);
}

$existingDate = normalize_attendance_date((string)($existing['date'] ?? '')) ?? $defaultDate;
$existingGrade = normalize_attendance_grade($existing['grade'] ?? null) ?? $defaultGrade;
$existingField = normalize_attendance_field((string)($existing['field'] ?? '')) ?? $defaultField;
$redirect = attendance_redirect_url($existingDate, $existingGrade, $existingField);

try {
    $deleteStmt = $pdo->prepare('DELETE FROM attendance WHERE id = ?');
    $deleteStmt->execute([$id]);

    if ($deleteStmt->rowCount() < 1) {
        attendance_handle_error('رکورد موردنظر حذف نشد.', $redirect, 404);
    }
} catch (Throwable $exception) {
    error_log('attendance_delete failed: ' . $exception->getMessage());
    attendance_handle_error('در حذف رکورد خطایی رخ داد. لطفا دوباره تلاش کنید.', $redirect, 500);
}

log_event_action(
    $user,
    'delete',
    $id,
    [
        'student_name' => (string)($existing['student_name'] ?? ''),
        'absent_hours' => (string)($existing['absent_hours'] ?? ''),
        'notes' => (string)($existing['notes'] ?? ''),
        'date' => (string)($existing['date'] ?? ''),
        'grade' => (int)($existing['grade'] ?? 0),
        'field' => (string)($existing['field'] ?? ''),
    ],
    null,
    'attendance'
);

attendance_handle_success('رکورد غیبت با موفقیت حذف شد.', $redirect, [
    'id' => $id,
]);
