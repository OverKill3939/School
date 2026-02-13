<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_login();

$pageTitle = 'خانه | هنرستان دارالفنون';
$activeNav = 'home';

require __DIR__ . '/partials/header.php';
?>
<main style="width:min(1120px,92vw);margin:0 auto 3rem;">
  <section style="background:var(--card);border:1px solid var(--stroke);border-radius:24px;padding:2rem;box-shadow:0 20px 45px rgba(15,23,42,0.12);">
    <h1 style="margin:0 0 0.8rem;">خوش آمدید</h1>
    <p style="margin:0 0 1.2rem;color:var(--ink-soft);">به پنل مدرسه خوش آمدید. برای مشاهده تقویم آموزشی روی گزینه تقویم در منو کلیک کنید.</p>
    <a class="cta" href="calendar.php">رفتن به تقویم</a>
  </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
