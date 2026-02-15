<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: election.php');
    exit;
}

$electionId = (int)($_POST['election_id'] ?? 0);

if ($electionId <= 0) {
    die('شناسه نامعتبر');
}

$pdo = get_db();

// چک کنیم که انتخابات فعال باشد
$check = $pdo->prepare("SELECT is_active FROM elections WHERE id = ?");
$check->execute([$electionId]);
$row = $check->fetch();

if (!$row || $row['is_active'] != 1) {
    die('انتخابات فعال نیست یا یافت نشد');
}

// غیرفعال کردن
$update = $pdo->prepare("UPDATE elections SET is_active = 0, updated_at = datetime('now', 'localtime') WHERE id = ?");
$update->execute([$electionId]);

header("Location: election.php?msg=انتخابات با موفقیت پایان یافت");
exit;