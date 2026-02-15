<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: vote.php");
    exit;
}

$electionId   = (int)($_POST['election_id']   ?? 0);
$candidateId  = (int)($_POST['candidate_id']  ?? 0);
$grade        = (int)($_POST['voter_grade']   ?? 0);   // ← نام فیلد فرم
$field        = trim($_POST['voter_field']    ?? '');

if ($electionId <= 0 || $candidateId <= 0 || !in_array($grade, [10,11,12]) || !in_array($field, ['شبکه و نرم افزار','برق','الکترونیک'])) {
    die("داده‌های ارسالی نامعتبر است.");
}

$pdo = get_db();

// ۱. چک ظرفیت باقی‌مانده برای این پایه + رشته
$capStmt = $pdo->prepare("
    SELECT eligible_count, voted_count 
    FROM election_eligible 
    WHERE election_id = ? AND grade = ? AND field = ?
    LIMIT 1
");
$capStmt->execute([$electionId, $grade, $field]);
$capacity = $capStmt->fetch();

if (!$capacity) {
    die("این ترکیب پایه و رشته مجاز نیست.");
}

$maxAllowed = (int)$capacity['eligible_count'];
$currentVoted = (int)$capacity['voted_count'];

if ($currentVoted >= $maxAllowed) {
    die("<h2 style='text-align:center; margin:80px 20px; color:#dc2626;'>ظرفیت رأی‌گیری برای این پایه و رشته پر شده است.</h2>");
}

// ۲. ثبت رأی جدید
$pdo->beginTransaction();
try {
    // ← اینجا مهم است: نام ستون‌ها باید grade و field باشد (نه voter_grade)
    $voteStmt = $pdo->prepare("
        INSERT INTO votes (election_id, candidate_id, grade, field)
        VALUES (?, ?, ?, ?)
    ");
    $voteStmt->execute([$electionId, $candidateId, $grade, $field]);

    // افزایش تعداد آرا برای کاندیدا
    $pdo->prepare("UPDATE candidates SET votes = votes + 1 WHERE id = ?")
        ->execute([$candidateId]);

    // افزایش شمارنده voted_count
    $pdo->prepare("
        UPDATE election_eligible 
        SET voted_count = voted_count + 1 
        WHERE election_id = ? AND grade = ? AND field = ?
    ")->execute([$electionId, $grade, $field]);

    $pdo->commit();

    header("Location: vote-success.php");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die("خطا در ثبت رأی: " . htmlspecialchars($e->getMessage()));
}