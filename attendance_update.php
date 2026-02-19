<?php
// attendance_update.php
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
$name = normalize_attendance_student_name($_POST['student_name'] ?? '');
$hours = sanitize_attendance_hours((array)($_POST['hours'] ?? []));
$notes = normalize_attendance_notes($_POST['notes'] ?? '');

if ($id <= 0) {
    attendance_handle_error('شناسه رکورد معتبر نیست.', $redirect, 422);
}

if ($name === '') {
    attendance_handle_error('نام دانش آموز معتبر نیست.', $redirect, 422);
}

if ($hours === []) {
    attendance_handle_error('حداقل یک زنگ باید انتخاب شود.', $redirect, 422);
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

$existingHours = sanitize_attendance_hours(explode(',', (string)($existing['absent_hours'] ?? '')));
$newHoursString = implode(',', $hours);
$oldHoursString = implode(',', $existingHours);

$hasChanges =
    normalize_attendance_student_name((string)($existing['student_name'] ?? '')) !== $name ||
    $oldHoursString !== $newHoursString ||
    normalize_attendance_notes((string)($existing['notes'] ?? '')) !== $notes;

if (!$hasChanges) {
    attendance_handle_success('تغییری برای ذخیره وجود نداشت.', $redirect, [
        'id' => $id,
        'changed' => false,
    ]);
}

try {
    $updateStmt = $pdo->prepare(
        'UPDATE attendance
         SET student_name = ?,
             absent_hours = ?,
             notes = ?,
             recorded_by = ?
         WHERE id = ?'
    );

    $updateStmt->execute([
        $name,
        $newHoursString,
        $notes,
        $actorId,
        $id,
    ]);
} catch (Throwable $exception) {
    error_log('attendance_update failed: ' . $exception->getMessage());
    attendance_handle_error('در به روزرسانی اطلاعات خطایی رخ داد. لطفا دوباره تلاش کنید.', $redirect, 500);
}

log_event_action(
    $user,
    'update',
    $id,
    [
        'student_name' => (string)($existing['student_name'] ?? ''),
        'absent_hours' => (string)($existing['absent_hours'] ?? ''),
        'notes' => (string)($existing['notes'] ?? ''),
        'date' => (string)($existing['date'] ?? ''),
        'grade' => (int)($existing['grade'] ?? 0),
        'field' => (string)($existing['field'] ?? ''),
    ],
    [
        'student_name' => $name,
        'absent_hours' => $newHoursString,
        'notes' => $notes,
        'date' => $existingDate,
        'grade' => $existingGrade,
        'field' => $existingField,
    ],
    'attendance'
);

attendance_handle_success('رکورد غیبت با موفقیت ویرایش شد.', $redirect, [
    'id' => $id,
    'changed' => true,
]);
