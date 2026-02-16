<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';

function vote_error(string $message): void
{
    start_secure_session();
    $_SESSION['vote_error'] = $message;
    header('Location: vote.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: vote.php');
    exit;
}

if (!csrf_check($_POST['csrf_token'] ?? null)) {
    vote_error('نشست شما منقضی شده است. لطفا دوباره فرم را ارسال کنید.');
}

$allowedGrades = [10, 11, 12];
$allowedFields = ['شبکه و نرم افزار', 'برق', 'الکترونیک'];

$electionId = (int)($_POST['election_id'] ?? 0);
$grade = (int)($_POST['voter_grade'] ?? 0);
$field = trim((string)($_POST['voter_field'] ?? ''));
$voterIdentifierRaw = trim((string)($_POST['voter_identifier'] ?? ''));
$candidateIdsRaw = $_POST['candidate_ids'] ?? [];

if ($electionId <= 0) {
    vote_error('شناسه انتخابات نامعتبر است.');
}

if (!in_array($grade, $allowedGrades, true) || !in_array($field, $allowedFields, true)) {
    vote_error('پایه یا رشته معتبر نیست.');
}

if (!is_array($candidateIdsRaw) || $candidateIdsRaw === []) {
    vote_error('حداقل یک کاندیدا را انتخاب کنید.');
}

$voterIdentifier = preg_replace('/[^0-9A-Za-z\-_]/', '', $voterIdentifierRaw) ?? '';
$voterIdentifier = substr($voterIdentifier, 0, 32);
if (strlen($voterIdentifier) < 3) {
    vote_error('کد دانش آموز نامعتبر است.');
}

$candidateIds = [];
foreach ($candidateIdsRaw as $value) {
    $id = (int)$value;
    if ($id > 0) {
        $candidateIds[] = $id;
    }
}

if ($candidateIds === []) {
    vote_error('کاندیداهای انتخاب شده نامعتبر هستند.');
}

$uniqueCandidateIds = array_values(array_unique($candidateIds));
if (count($uniqueCandidateIds) !== count($candidateIds)) {
    vote_error('امکان انتخاب تکراری یک کاندیدا در یک ارسال وجود ندارد.');
}

$voterKey = hash('sha256', strtolower($voterIdentifier));

$pdo = get_db();
$pdo->beginTransaction();

try {
    $activeStmt = $pdo->prepare('SELECT id FROM elections WHERE id = :id AND is_active = 1 LIMIT 1');
    $activeStmt->execute([':id' => $electionId]);
    $activeElection = $activeStmt->fetch();
    if (!$activeElection) {
        throw new RuntimeException('این انتخابات فعال نیست.');
    }

    $eligibleStmt = $pdo->prepare(
        'SELECT id, eligible_count, voted_count, votes_per_student
         FROM election_eligible
         WHERE election_id = :election_id AND grade = :grade AND field = :field
         LIMIT 1'
    );
    $eligibleStmt->execute([
        ':election_id' => $electionId,
        ':grade' => $grade,
        ':field' => $field,
    ]);
    $eligible = $eligibleStmt->fetch();

    if (!$eligible) {
        throw new RuntimeException('برای این پایه و رشته، مجوز رأی گیری ثبت نشده است.');
    }

    $eligibleCount = max(0, (int)($eligible['eligible_count'] ?? 0));
    $votedCount = max(0, (int)($eligible['voted_count'] ?? 0));
    $votesPerStudent = max(1, (int)($eligible['votes_per_student'] ?? 1));

    if ($eligibleCount <= 0) {
        throw new RuntimeException('رأی گیری برای این پایه و رشته فعال نیست.');
    }

    $placeholders = implode(', ', array_fill(0, count($uniqueCandidateIds), '?'));
    $candidateSql = "SELECT id FROM candidates WHERE election_id = ? AND id IN ({$placeholders})";
    $candidateParams = array_merge([$electionId], $uniqueCandidateIds);
    $candidateStmt = $pdo->prepare($candidateSql);
    $candidateStmt->execute($candidateParams);
    $validCandidateIds = array_map('intval', array_column($candidateStmt->fetchAll(), 'id'));

    sort($validCandidateIds);
    $sortedInput = $uniqueCandidateIds;
    sort($sortedInput);
    if ($validCandidateIds !== $sortedInput) {
        throw new RuntimeException('یکی از کاندیداهای انتخاب شده نامعتبر است.');
    }

    $existingStmt = $pdo->prepare(
        'SELECT candidate_id
         FROM votes
         WHERE election_id = :election_id
           AND grade = :grade
           AND field = :field
           AND voter_key = :voter_key'
    );
    $existingStmt->execute([
        ':election_id' => $electionId,
        ':grade' => $grade,
        ':field' => $field,
        ':voter_key' => $voterKey,
    ]);

    $existingCandidateIds = array_map('intval', array_column($existingStmt->fetchAll(), 'candidate_id'));
    $existingCandidateIds = array_values(array_unique($existingCandidateIds));

    foreach ($uniqueCandidateIds as $candidateId) {
        if (in_array($candidateId, $existingCandidateIds, true)) {
            throw new RuntimeException('برای هر دانش آموز، رأی تکراری به یک کاندیدا مجاز نیست.');
        }
    }

    $newTotalVotes = count($existingCandidateIds) + count($uniqueCandidateIds);
    if ($newTotalVotes > $votesPerStudent) {
        throw new RuntimeException('تعداد رأی انتخاب شده از حد مجاز شما بیشتر است.');
    }

    $firstParticipation = count($existingCandidateIds) === 0;
    if ($firstParticipation) {
        if ($votedCount >= $eligibleCount) {
            throw new RuntimeException('ظرفیت رأی گیری این پایه و رشته تکمیل شده است.');
        }

        $capacityStmt = $pdo->prepare(
            'UPDATE election_eligible
             SET voted_count = voted_count + 1
             WHERE id = :id AND voted_count < eligible_count'
        );
        $capacityStmt->execute([':id' => (int)$eligible['id']]);
        if ($capacityStmt->rowCount() === 0) {
            throw new RuntimeException('ظرفیت رأی گیری تکمیل شده است.');
        }
    }

    $insertStmt = $pdo->prepare(
        'INSERT INTO votes (election_id, candidate_id, grade, field, voter_key)
         VALUES (:election_id, :candidate_id, :grade, :field, :voter_key)'
    );

    $updateCandidateStmt = $pdo->prepare('UPDATE candidates SET votes = votes + 1 WHERE id = :id');

    foreach ($uniqueCandidateIds as $candidateId) {
        $insertStmt->execute([
            ':election_id' => $electionId,
            ':candidate_id' => $candidateId,
            ':grade' => $grade,
            ':field' => $field,
            ':voter_key' => $voterKey,
        ]);

        $updateCandidateStmt->execute([':id' => $candidateId]);
    }

    $pdo->commit();

    header('Location: vote-success.php?count=' . count($uniqueCandidateIds));
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    vote_error($e->getMessage());
}