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

$candidateId = (int)($_POST['id'] ?? 0);
if ($candidateId <= 0) {
    header('Location: election.php?error=' . urlencode('شناسه کاندیدا نامعتبر است.'));
    exit;
}

$pdo = get_db();
$candidateStmt = $pdo->prepare('SELECT id, election_id, photo_path FROM candidates WHERE id = :id LIMIT 1');
$candidateStmt->execute([':id' => $candidateId]);
$candidate = $candidateStmt->fetch();

if (!$candidate) {
    header('Location: election.php?error=' . urlencode('کاندیدا پیدا نشد.'));
    exit;
}

$electionId = (int)($candidate['election_id'] ?? 0);
$photoPath = (string)($candidate['photo_path'] ?? '');

$nowExpr = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite'
    ? "datetime('now','localtime')"
    : 'NOW()';

$pdo->beginTransaction();
try {
    $deleteStmt = $pdo->prepare('DELETE FROM candidates WHERE id = :id');
    $deleteStmt->execute([':id' => $candidateId]);

    $recountVotesStmt = $pdo->prepare(
        "SELECT grade, field, COUNT(DISTINCT voter_key) AS voted_students
         FROM votes
         WHERE election_id = :election_id
           AND voter_key <> ''
         GROUP BY grade, field"
    );
    $recountVotesStmt->execute([':election_id' => $electionId]);

    $votedMap = [];
    foreach ($recountVotesStmt->fetchAll() as $row) {
        $grade = (int)($row['grade'] ?? 0);
        $field = (string)($row['field'] ?? '');
        $votedMap[$grade . '|' . $field] = max(0, (int)($row['voted_students'] ?? 0));
    }

    $eligibleRowsStmt = $pdo->prepare('SELECT id, grade, field, eligible_count FROM election_eligible WHERE election_id = :election_id');
    $eligibleRowsStmt->execute([':election_id' => $electionId]);
    $eligibleRows = $eligibleRowsStmt->fetchAll();

    $updateEligibleStmt = $pdo->prepare(
        'UPDATE election_eligible SET voted_count = :voted_count WHERE id = :id'
    );

    foreach ($eligibleRows as $row) {
        $rowId = (int)($row['id'] ?? 0);
        $grade = (int)($row['grade'] ?? 0);
        $field = (string)($row['field'] ?? '');
        $eligibleCount = max(0, (int)($row['eligible_count'] ?? 0));

        $votedCount = (int)($votedMap[$grade . '|' . $field] ?? 0);
        if ($votedCount > $eligibleCount) {
            $votedCount = $eligibleCount;
        }

        $updateEligibleStmt->execute([
            ':id' => $rowId,
            ':voted_count' => $votedCount,
        ]);
    }

    $remainingCandidatesStmt = $pdo->prepare('SELECT COUNT(*) FROM candidates WHERE election_id = :election_id');
    $remainingCandidatesStmt->execute([':election_id' => $electionId]);
    $remainingCandidates = (int)$remainingCandidatesStmt->fetchColumn();

    if ($remainingCandidates === 0) {
        $deactivateStmt = $pdo->prepare("UPDATE elections SET is_active = 0, updated_at = {$nowExpr} WHERE id = :id");
        $deactivateStmt->execute([':id' => $electionId]);
    }

    $pdo->commit();
} catch (Throwable) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Location: election.php?error=' . urlencode('حذف کاندیدا ناموفق بود.'));
    exit;
}

if ($photoPath !== '') {
    $absolute = __DIR__ . $photoPath;
    if (is_file($absolute)) {
        @unlink($absolute);
    }
}

header('Location: election.php?msg=' . urlencode('کاندیدا حذف شد.'));
exit;
