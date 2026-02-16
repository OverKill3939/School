<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';

$pageTitle = 'ثبت موفق رأی';
$extraStyles = ['css/vote.css'];

$count = max(1, (int)($_GET['count'] ?? 1));

require __DIR__ . '/partials/header.php';
?>

<main class="vote-page">
    <section class="vote-card success-state">
        <h1>رأی شما با موفقیت ثبت شد</h1>
        <p>تعداد رأی ثبت شده در این مرحله: <strong><?= $count ?></strong></p>
        <a href="vote.php" class="btn-submit">بازگشت به صفحه رأی گیری</a>
    </section>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
