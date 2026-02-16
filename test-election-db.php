<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';

if (PHP_SAPI !== 'cli') {
    require_admin();
}

$pdo = get_db();

echo "<pre dir='rtl'>";

echo "=== وضعیت انتخابات ===\n";
$electionStmt = $pdo->query('SELECT id, title, year, is_active FROM elections ORDER BY id DESC LIMIT 1');
$election = $electionStmt->fetch();

if (!$election) {
    echo "هیچ انتخاباتی ثبت نشده است.\n";
    echo "</pre>";
    exit;
}

$electionId = (int)$election['id'];
echo "ID: {$electionId}\n";
echo 'عنوان: ' . (string)$election['title'] . "\n";
echo 'سال: ' . display_persian_year((int)$election['year']) . "\n";
echo 'وضعیت: ' . ((int)$election['is_active'] === 1 ? 'فعال' : 'غیرفعال') . "\n\n";

echo "=== تنظیمات واجدین شرایط ===\n";
$eligibleStmt = $pdo->prepare(
    'SELECT grade, field, eligible_count, voted_count, votes_per_student
     FROM election_eligible
     WHERE election_id = :election_id
     ORDER BY grade, field'
);
$eligibleStmt->execute([':election_id' => $electionId]);
$eligibleRows = $eligibleStmt->fetchAll();

if ($eligibleRows === []) {
    echo "هیچ تنظیمی ثبت نشده است.\n\n";
} else {
    foreach ($eligibleRows as $row) {
        echo sprintf(
            "پایه %d | %s | مجاز: %d | رأی داده: %d | رأی هر دانش آموز: %d\n",
            (int)$row['grade'],
            (string)$row['field'],
            (int)$row['eligible_count'],
            (int)$row['voted_count'],
            (int)$row['votes_per_student']
        );
    }
    echo "\n";
}

echo "=== کاندیداها ===\n";
$candidateStmt = $pdo->prepare(
    'SELECT id, full_name, grade, field, votes
     FROM candidates
     WHERE election_id = :election_id
     ORDER BY field, grade, full_name'
);
$candidateStmt->execute([':election_id' => $electionId]);
$candidates = $candidateStmt->fetchAll();

if ($candidates === []) {
    echo "هیچ کاندیدایی ثبت نشده است.\n\n";
} else {
    foreach ($candidates as $row) {
        echo sprintf(
            "#%d | %s | پایه %d | %s | آرا: %d\n",
            (int)$row['id'],
            (string)$row['full_name'],
            (int)$row['grade'],
            (string)$row['field'],
            (int)$row['votes']
        );
    }
    echo "\n";
}

echo "=== آمار رأی ===\n";
$totalVotesStmt = $pdo->prepare('SELECT COUNT(*) FROM votes WHERE election_id = :election_id');
$totalVotesStmt->execute([':election_id' => $electionId]);
$totalVotes = (int)$totalVotesStmt->fetchColumn();

$totalStudentsStmt = $pdo->prepare(
    "SELECT COUNT(DISTINCT voter_key) FROM votes WHERE election_id = :election_id AND voter_key <> ''"
);
$totalStudentsStmt->execute([':election_id' => $electionId]);
$totalStudents = (int)$totalStudentsStmt->fetchColumn();

echo "کل رأی‌های ثبت‌شده: {$totalVotes}\n";
echo "تعداد دانش آموز رأی‌دهنده (بر اساس voter_key): {$totalStudents}\n";

echo "</pre>";
