<?php
declare(strict_types=1);

final class Schedule
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getSchedule(int $grade, string $field): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, grade, field, day, hour, subject, created_at, updated_at
             FROM schedules
             WHERE grade = :grade AND field = :field
             ORDER BY day ASC, hour ASC'
        );

        $stmt->execute([
            ':grade' => $grade,
            ':field' => $field,
        ]);

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function getAllSchedules(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, grade, field, day, hour, subject, created_at, updated_at
             FROM schedules
             ORDER BY grade ASC, field ASC, day ASC, hour ASC'
        );

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    public function saveCell(int $grade, string $field, int $day, int $hour, string $subject, int $adminId): bool
    {
        $field = trim($field);
        $subject = trim($subject);

        if ($grade < 1 || $grade > 3 || $day < 0 || $day > 4 || $hour < 0 || $hour > 3 || $field === '' || $adminId <= 0) {
            throw new RuntimeException('Invalid schedule payload.');
        }

        $existing = $this->findCell($grade, $field, $day, $hour);

        if ($subject === '') {
            if (!$existing) {
                return true;
            }

            // ⚠️ ابتدا لاگ کن، بعد حذف کن (به دلیل ON DELETE CASCADE در history)
            $this->logChange(
                (int)$existing['id'],
                $adminId,
                $grade,
                $field,
                $day,
                $hour,
                'delete',
                (string)$existing['subject'],
                null
            );

            $delete = $this->pdo->prepare('DELETE FROM schedules WHERE id = :id');
            $delete->execute([':id' => (int)$existing['id']]);

            return true;
        }

        if ($existing) {
            $oldSubject = (string)$existing['subject'];
            if ($oldSubject === $subject) {
                return true;
            }

            $update = $this->pdo->prepare(
                'UPDATE schedules
                 SET subject = :subject, updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );
            $update->execute([
                ':subject' => $subject,
                ':id' => (int)$existing['id'],
            ]);

            $this->logChange(
                (int)$existing['id'],
                $adminId,
                $grade,
                $field,
                $day,
                $hour,
                'update',
                $oldSubject,
                $subject
            );

            return true;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO schedules (grade, field, day, hour, subject)
             VALUES (:grade, :field, :day, :hour, :subject)'
        );
        $insert->execute([
            ':grade' => $grade,
            ':field' => $field,
            ':day' => $day,
            ':hour' => $hour,
            ':subject' => $subject,
        ]);

        $scheduleId = (int)$this->pdo->lastInsertId();

        $this->logChange(
            $scheduleId,
            $adminId,
            $grade,
            $field,
            $day,
            $hour,
            'create',
            null,
            $subject
        );

        return true;
    }

    public function deleteCell(int $grade, string $field, int $day, int $hour, int $adminId): bool
    {
        return $this->saveCell($grade, $field, $day, $hour, '', $adminId);
    }

    public function getHistory(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        $stmt = $this->pdo->prepare(
            'SELECT sh.id,
                    sh.schedule_id,
                    sh.admin_id,
                    sh.grade,
                    sh.field_name,
                    sh.day,
                    sh.hour,
                    sh.action,
                    sh.old_subject,
                    sh.new_subject,
                    sh.changed_at,
                    u.first_name,
                    u.last_name,
                    u.role
             FROM schedule_history sh
             LEFT JOIN users u ON u.id = sh.admin_id
             ORDER BY sh.changed_at DESC, sh.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    private function findCell(int $grade, string $field, int $day, int $hour): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, subject
             FROM schedules
             WHERE grade = :grade AND field = :field AND day = :day AND hour = :hour
             LIMIT 1'
        );
        $stmt->execute([
            ':grade' => $grade,
            ':field' => $field,
            ':day' => $day,
            ':hour' => $hour,
        ]);

        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    private function logChange(
        int $scheduleId,
        int $adminId,
        int $grade,
        string $field,
        int $day,
        int $hour,
        string $action,
        ?string $oldSubject,
        ?string $newSubject
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO schedule_history
                (schedule_id, admin_id, grade, field_name, day, hour, action, old_subject, new_subject)
             VALUES
                (:schedule_id, :admin_id, :grade, :field_name, :day, :hour, :action, :old_subject, :new_subject)'
        );

        $stmt->bindValue(':schedule_id', $scheduleId, PDO::PARAM_INT);
        $stmt->bindValue(':admin_id', $adminId, PDO::PARAM_INT);
        $stmt->bindValue(':grade', $grade, PDO::PARAM_INT);
        $stmt->bindValue(':field_name', $field, PDO::PARAM_STR);
        $stmt->bindValue(':day', $day, PDO::PARAM_INT);
        $stmt->bindValue(':hour', $hour, PDO::PARAM_INT);
        $stmt->bindValue(':action', $action, PDO::PARAM_STR);

        if ($oldSubject === null) {
            $stmt->bindValue(':old_subject', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':old_subject', $oldSubject, PDO::PARAM_STR);
        }

        if ($newSubject === null) {
            $stmt->bindValue(':new_subject', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':new_subject', $newSubject, PDO::PARAM_STR);
        }

        $stmt->execute();
    }
}
