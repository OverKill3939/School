<?php
// attendance_save.php
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

if ($date === null || $grade === null || $field === null) {
    attendance_handle_error('تاریخ، پایه یا رشته معتبر نیست.', $redirect, 422);
}

$students = $_POST['students'] ?? null;
if (!is_array($students) || $students === []) {
    attendance_handle_error('حداقل یک دانش آموز باید ثبت شود.', $redirect, 422);
}

$user = current_user();
$actorId = (int)($user['id'] ?? 0);
if ($actorId <= 0) {
    attendance_handle_error('دسترسی نامعتبر است.', $redirect, 403);
}

$preparedRows = [];

foreach ($students as $student) {
    if (!is_array($student)) {
        continue;
    }

    $name = normalize_attendance_student_name($student['name'] ?? '');
    if ($name === '') {
        continue;
    }

    $hours = sanitize_attendance_hours((array)($student['hours'] ?? []));
    if ($hours === []) {
        continue;
    }

    $notes = normalize_attendance_notes($student['notes'] ?? '');

    $key = function_exists('mb_strtolower')
        ? mb_strtolower($name, 'UTF-8')
        : strtolower($name);

    if (isset($preparedRows[$key])) {
        $preparedRows[$key]['hours'] = sanitize_attendance_hours(array_merge($preparedRows[$key]['hours'], $hours));
        if ($notes !== '') {
            $preparedRows[$key]['notes'] = $notes;
        }
        continue;
    }

    $preparedRows[$key] = [
        'name' => $name,
        'hours' => $hours,
        'notes' => $notes,
    ];
}

if ($preparedRows === []) {
    attendance_handle_error('ورودی معتبر برای ذخیره پیدا نشد. برای هر دانش آموز حداقل یک زنگ را انتخاب کنید.', $redirect, 422);
}

$pdo = get_attendance_db();

try {
    $stmt = $pdo->prepare(
        'INSERT INTO attendance (date, grade, field, student_name, absent_hours, notes, recorded_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON CONFLICT(date, grade, field, student_name)
         DO UPDATE SET
           absent_hours = excluded.absent_hours,
           notes = excluded.notes,
           recorded_by = excluded.recorded_by'
    );

    $pdo->beginTransaction();

    foreach ($preparedRows as $row) {
        $stmt->execute([
            $date,
            $grade,
            $field,
            $row['name'],
            implode(',', $row['hours']),
            $row['notes'],
            $actorId,
        ]);
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('attendance_save failed: ' . $exception->getMessage());
    attendance_handle_error('در ذخیره اطلاعات خطایی رخ داد. لطفا دوباره تلاش کنید.', $redirect, 500);
}

$count = count($preparedRows);

log_event_action(
    $user,
    'create',
    null,
    null,
    [
        'type' => 'attendance_bulk_save',
        'date' => $date,
        'grade' => $grade,
        'field' => $field,
        'count' => $count,
    ],
    'attendance'
);

attendance_handle_success("$count رکورد حضور و غیاب با موفقیت ذخیره شد.", $redirect, [
    'count' => $count,
]);
