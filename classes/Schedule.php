<?php
class Schedule {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * دریافت برنامه یک پایه و رشته
     */
    public function getSchedule($grade, $field) {
        $sql = "SELECT * FROM schedules WHERE grade = ? AND field = ? ORDER BY day, hour";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$grade, $field]);
        return $stmt->fetchAll();
    }
    
    /**
     * دریافت تمام برنامه‌ها
     */
    public function getAllSchedules() {
        $sql = "SELECT * FROM schedules ORDER BY grade, field, day, hour";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * ذخیره یا بروزرسانی یک سلول
     */
    public function saveCell($grade, $field, $day, $hour, $subject, $admin_id) {
        try {
            // بررسی اینکه آیا این سلول وجود دارد یا نه
            $sql = "SELECT id, subject FROM schedules WHERE grade = ? AND field = ? AND day = ? AND hour = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$grade, $field, $day, $hour]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // بروزرسانی
                $old_subject = $existing['subject'];
                $sql = "UPDATE schedules SET subject = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$subject, $existing['id']]);
                
                // ثبت در تاریخچه
                $this->logChange($existing['id'], $admin_id, $old_subject, $subject);
            } else {
                // درج جدید
                $sql = "INSERT INTO schedules (grade, field, day, hour, subject) VALUES (?, ?, ?, ?, ?)";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$grade, $field, $day, $hour, $subject]);
                
                // ثبت در تاریخچه
                $schedule_id = $this->pdo->lastInsertId();
                $this->logChange($schedule_id, $admin_id, null, $subject);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("خطا در ذخیره‌سازی: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * ذخیره تمام سلول‌های یک برنامه
     */
    public function saveSchedule($grade, $field, $data, $admin_id) {
        try {
            $this->pdo->beginTransaction();
            
            foreach ($data as $day => $hours) {
                foreach ($hours as $hour => $subject) {
                    if (trim($subject) !== '') {
                        $this->saveCell($grade, $field, $day, $hour, $subject, $admin_id);
                    }
                }
            }
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("خطا در ذخیره‌سازی برنامه: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * حذف یک سلول
     */
    public function deleteCell($grade, $field, $day, $hour, $admin_id) {
        try {
            $sql = "SELECT id, subject FROM schedules WHERE grade = ? AND field = ? AND day = ? AND hour = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$grade, $field, $day, $hour]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $sql = "DELETE FROM schedules WHERE id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$existing['id']]);
                
                $this->logChange($existing['id'], $admin_id, $existing['subject'], null);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("خطا در حذف: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * دریافت تاریخچه تغییرات
     */
    public function getHistory($limit = 50) {
        $sql = "SELECT sh.*, u.name as admin_name FROM schedule_history sh 
                LEFT JOIN users u ON sh.admin_id = u.id 
                ORDER BY sh.changed_at DESC LIMIT ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * ثبت تغییر در تاریخچه
     */
    private function logChange($schedule_id, $admin_id, $old_subject, $new_subject) {
        $sql = "INSERT INTO schedule_history (schedule_id, admin_id, old_subject, new_subject) 
                VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$schedule_id, $admin_id, $old_subject, $new_subject]);
    }
}
?>