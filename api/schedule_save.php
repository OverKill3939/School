<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/helpers.php';
require_admin();

require_once __DIR__ . '/../auth/db.php';
$pdo = get_db();

try {
    $grade = (int)($_POST['grade'] ?? 0);
    $field = $_POST['field'] ?? '';
    $schedule_data = $_POST['schedule'][$grade][$field] ?? [];

    if ($grade <= 0 || empty($field) || empty($schedule_data)) {
        header('Location: ../schedule.php?error=invalid');
        exit;
    }

    // شروع تراکنش
    $pdo->beginTransaction();

    // درج یا بروزرسانی برنامه‌ها
    foreach ($schedule_data as $day => $hours) {
        foreach ($hours as $hour => $subject) {
            $subject = trim((string)$subject);
            $day = (int)$day;
            $hour = (int)$hour;

            // بررسی وجود
            $stmt = $pdo->prepare('SELECT id FROM schedules WHERE grade = ? AND field = ? AND day = ? AND hour = ?');
            $stmt->execute([$grade, $field, $day, $hour]);
            $existing = $stmt->fetch();

            if ($existing && empty($subject)) {
                // حذف اگر خالی شده باشد
                $stmt = $pdo->prepare('DELETE FROM schedules WHERE id = ?');
                $stmt->execute([$existing['id']]);
            } elseif ($existing && !empty($subject)) {
                // بروزرسانی
                $stmt = $pdo->prepare('UPDATE schedules SET subject = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
                $stmt->execute([$subject, $existing['id']]);
            } elseif (!$existing && !empty($subject)) {
                // درج
                $stmt = $pdo->prepare('INSERT INTO schedules (grade, field, day, hour, subject) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$grade, $field, $day, $hour, $subject]);
            }
        }
    }

    $pdo->commit();
    header('Location: ../schedule.php?success=1');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: ../schedule.php?error=database');
    exit;
}
?>