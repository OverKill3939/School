<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: election.php');
    exit;
}

$electionId = (int)($_POST['election_id'] ?? 0);
$data = $_POST['eligible'] ?? [];

if ($electionId <= 0 || empty($data)) {
    die('داده نامعتبر');
}

$pdo = get_db();

$pdo->beginTransaction();
try {
    // پاک کردن رکوردهای قبلی
    $pdo->prepare("DELETE FROM election_eligible WHERE election_id = ?")
        ->execute([$electionId]);

    $insert = $pdo->prepare("
        INSERT INTO election_eligible
        (election_id, grade, field, eligible_count, voted_count)
        VALUES (?, ?, ?, ?, 0)
    ");

    foreach ($data as $grade => $fields) {
        foreach ($fields as $field => $count) {
            $count = max(0, (int)$count);
            $insert->execute([$electionId, $grade, $field, $count]);
        }
    }

    $pdo->commit();
    header("Location: election.php?msg=تعداد مجاز ذخیره شد");
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    die("خطا: " . htmlspecialchars($e->getMessage()));
}