<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: election.php');
    exit;
}

if (!csrf_check($_POST['csrf_token'] ?? null)) {
    header('Location: election.php?error=' . urlencode('نشست شما منقضی شده است. دوباره تلاش کنید.'));
    exit;
}

$electionId = (int)($_POST['election_id'] ?? 0);
if ($electionId <= 0) {
    header('Location: election.php?error=' . urlencode('شناسه انتخابات نامعتبر است.'));
    exit;
}

$allowedGrades = [10, 11, 12];
$allowedFields = ['شبکه و نرم افزار', 'برق', 'الکترونیک'];
$eligibleInput = is_array($_POST['eligible'] ?? null) ? $_POST['eligible'] : [];
$votesPerStudentInput = is_array($_POST['votes_per_student'] ?? null) ? $_POST['votes_per_student'] : [];

$pdo = get_db();

$votesCountSql = <<<'SQL'
SELECT grade, field, COUNT(DISTINCT voter_key) AS voted_students
FROM votes
WHERE election_id = :election_id
  AND voter_key <> ''
GROUP BY grade, field
SQL;

$pdo->beginTransaction();
try {
    $votedMap = [];
    $votedStmt = $pdo->prepare($votesCountSql);
    $votedStmt->execute([':election_id' => $electionId]);
    foreach ($votedStmt->fetchAll() as $row) {
        $grade = (int)($row['grade'] ?? 0);
        $field = (string)($row['field'] ?? '');
        if (!in_array($grade, $allowedGrades, true) || !in_array($field, $allowedFields, true)) {
            continue;
        }
        $votedMap[$grade . '|' . $field] = max(0, (int)($row['voted_students'] ?? 0));
    }

    $deleteStmt = $pdo->prepare('DELETE FROM election_eligible WHERE election_id = :election_id');
    $deleteStmt->execute([':election_id' => $electionId]);

    $insertStmt = $pdo->prepare(
        'INSERT INTO election_eligible (election_id, grade, field, eligible_count, voted_count, votes_per_student)
         VALUES (:election_id, :grade, :field, :eligible_count, :voted_count, :votes_per_student)'
    );

    foreach ($allowedGrades as $grade) {
        foreach ($allowedFields as $field) {
            $eligibleCount = (int)($eligibleInput[$grade][$field] ?? 0);
            $eligibleCount = max(0, $eligibleCount);

            $votesPerStudent = (int)($votesPerStudentInput[$grade][$field] ?? 1);
            $votesPerStudent = max(1, min(10, $votesPerStudent));

            $votedCount = (int)($votedMap[$grade . '|' . $field] ?? 0);
            if ($votedCount > $eligibleCount) {
                $votedCount = $eligibleCount;
            }

            $insertStmt->execute([
                ':election_id' => $electionId,
                ':grade' => $grade,
                ':field' => $field,
                ':eligible_count' => $eligibleCount,
                ':voted_count' => $votedCount,
                ':votes_per_student' => $votesPerStudent,
            ]);
        }
    }

    $pdo->commit();
    header('Location: election.php?msg=' . urlencode('تنظیمات رأی با موفقیت ذخیره شد.'));
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Location: election.php?error=' . urlencode('ذخیره تنظیمات ناموفق بود.'));
    exit;
}
