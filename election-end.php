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

$pdo = get_db();
$checkStmt = $pdo->prepare('SELECT id FROM elections WHERE id = :id LIMIT 1');
$checkStmt->execute([':id' => $electionId]);
$election = $checkStmt->fetch();

if (!$election) {
    header('Location: election.php?error=' . urlencode('انتخابات پیدا نشد.'));
    exit;
}

$nowExpr = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite'
    ? "datetime('now','localtime')"
    : 'NOW()';

// Collect candidate photos for cleanup after DB commit.
$photoStmt = $pdo->prepare('SELECT photo_path FROM candidates WHERE election_id = :election_id');
$photoStmt->execute([':election_id' => $electionId]);
$photoPaths = [];
foreach ($photoStmt->fetchAll() as $row) {
    $photoPath = trim((string)($row['photo_path'] ?? ''));
    if ($photoPath !== '') {
        $photoPaths[] = $photoPath;
    }
}

$pdo->beginTransaction();
try {
    $deleteVotesStmt = $pdo->prepare('DELETE FROM votes WHERE election_id = :election_id');
    $deleteVotesStmt->execute([':election_id' => $electionId]);

    $deleteCandidatesStmt = $pdo->prepare('DELETE FROM candidates WHERE election_id = :election_id');
    $deleteCandidatesStmt->execute([':election_id' => $electionId]);

    $deleteEligibleStmt = $pdo->prepare('DELETE FROM election_eligible WHERE election_id = :election_id');
    $deleteEligibleStmt->execute([':election_id' => $electionId]);

    $updateElectionStmt = $pdo->prepare("UPDATE elections SET is_active = 0, updated_at = {$nowExpr} WHERE id = :id");
    $updateElectionStmt->execute([':id' => $electionId]);

    $pdo->commit();
} catch (Throwable) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Location: election.php?error=' . urlencode('پایان انتخابات انجام نشد. دوباره تلاش کنید.'));
    exit;
}

// Best-effort filesystem cleanup.
foreach ($photoPaths as $photoPath) {
    $primaryPath = __DIR__ . $photoPath;
    if (is_file($primaryPath)) {
        @unlink($primaryPath);
        continue;
    }

    $legacyPath = __DIR__ . '/public' . $photoPath;
    if (is_file($legacyPath)) {
        @unlink($legacyPath);
    }
}

header('Location: election.php?msg=' . urlencode('انتخابات پایان یافت و همه داده‌ها به حالت اولیه برگشت.'));
exit;