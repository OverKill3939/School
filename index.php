<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_login();

$pageTitle = 'داشبورد | هنرستان دارالفنون';
$activeNav = 'home';

require __DIR__ . '/partials/header.php';

$user = current_user();
$isAdmin = ($user['role'] === 'admin');

// آمار ساده (بعداً می‌تونی از دیتابیس بکشی)
$pdo = get_db();
$todayEvents = $pdo->query("SELECT COUNT(*) FROM calendar_events WHERE year = " . date('Y') . " AND month = " . date('n') . " AND day = " . date('j'))->fetchColumn();
$newsCount   = $pdo->query("SELECT COUNT(*) FROM news WHERE is_published = 1")->fetchColumn();
$latestNews  = $pdo->query("SELECT title, slug, published_at FROM news WHERE is_published = 1 ORDER BY published_at DESC LIMIT 3")->fetchAll();
?>

<main class="dashboard" style="width:min(1180px,92vw);margin:0 auto 4rem;">

    <!-- Hero -->
    <section class="hero" style="background: linear-gradient(135deg, #1e40af, #3b82f6); color: white; border-radius: 24px; padding: 3rem 2.5rem; margin-bottom: 2.5rem; position: relative; overflow: hidden;">
        <div style="position: relative; z-index: 2;">
            <h1 style="margin:0 0 0.4rem; font-size: clamp(2rem, 4vw, 2.8rem);">
                سلام <?= htmlspecialchars($user['first_name']) ?> 👋
            </h1>
            <p style="margin:0; opacity:0.9; font-size:1.15rem;">
                خوش آمدید به پنل مدیریت هنرستان دارالفنون
            </p>
        </div>
        
        <div style="position: absolute; bottom: -40px; right: -40px; font-size: 180px; opacity: 0.08; pointer-events: none;">🏫</div>
        
        <div style="margin-top: 2rem; display: flex; gap: 1rem; flex-wrap: wrap;">
            <a href="calendar.php" class="cta" style="padding: 0.9rem 2rem; font-size:1.05rem;">تقویم آموزشی</a>
            <a href="news.php" class="cta" style="background:#10b981; padding: 0.9rem 2rem; font-size:1.05rem;">اخبار جدید</a>
            <?php if ($isAdmin): ?>
                <a href="users.php" class="cta" style="background:#8b5cf6; padding: 0.9rem 2rem; font-size:1.05rem;">مدیریت کاربران</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
        <div class="stat-card" style="background:var(--card); border-radius:20px; padding:1.5rem; box-shadow:0 10px 30px rgba(15,23,42,0.08); text-align:center;">
            <div style="font-size:2.8rem; margin-bottom:0.5rem;">🏫</div>
            <div style="font-size:2.4rem; font-weight:700; color:#1e40af;"><?= $newsCount ?></div>
            <div style="color:var(--ink-soft);">خبر منتشر شده</div>
        </div>
        <div class="stat-card" style="background:var(--card); border-radius:20px; padding:1.5rem; box-shadow:0 10px 30px rgba(15,23,42,0.08); text-align:center;">
            <div style="font-size:2.8rem; margin-bottom:0.5rem;">📅</div>
            <div style="font-size:2.4rem; font-weight:700; color:#2563eb;"><?= $todayEvents ?></div>
            <div style="color:var(--ink-soft);">رویداد امروز</div>
        </div>
        <div class="stat-card" style="background:var(--card); border-radius:20px; padding:1.5rem; box-shadow:0 10px 30px rgba(15,23,42,0.08); text-align:center;">
            <div style="font-size:2.8rem; margin-bottom:0.5rem;">👨‍🎓</div>
            <div style="font-size:2.4rem; font-weight:700; color:#10b981;">۲۲۷</div>
            <div style="color:var(--ink-soft);">دانش‌آموز فعال</div>
        </div>
        <div class="stat-card" style="background:var(--card); border-radius:20px; padding:1.5rem; box-shadow:0 10px 30px rgba(15,23,42,0.08); text-align:center;">
            <div style="font-size:2.8rem; margin-bottom:0.5rem;">⚡</div>
            <div style="font-size:2.4rem; font-weight:700; color:#f59e0b;">۱۳</div>
            <div style="color:var(--ink-soft);">کلاس فعال</div>
        </div>
    </div>

    <!-- Latest News -->
    <section style="margin-bottom: 3rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
            <h2 style="margin:0; font-size:1.6rem;">آخرین اخبار</h2>
            <a href="news.php" style="color:#2563eb; font-weight:600; text-decoration:none;">مشاهده همه ←</a>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem;">
            <?php foreach ($latestNews as $n): ?>
            <a href="news-detail.php?slug=<?= urlencode($n['slug']) ?>" style="text-decoration:none; color:inherit;">
                <div style="background:var(--card); border-radius:16px; padding:1.4rem; height:100%; box-shadow:0 6px 20px rgba(15,23,42,0.08); transition:transform .2s;">
                    <div style="font-size:0.9rem; color:#64748b; margin-bottom:0.6rem;">
                        <?= date('d F Y', strtotime($n['published_at'])) ?>
                    </div>
                    <h3 style="margin:0 0 0.8rem; font-size:1.25rem; line-height:1.4;"><?= htmlspecialchars($n['title']) ?></h3>
                    <span style="color:#2563eb; font-weight:600;">مطالعه خبر →</span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Quick Links -->
    <section>
        <h2 style="margin-bottom:1rem; font-size:1.6rem;">دسترسی سریع</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;">
            <a href="calendar.php" class="quick-link" style="background:#eff6ff; border-radius:16px; padding:1.8rem 1.4rem; text-align:center; text-decoration:none; color:#1e40af; font-weight:600; box-shadow:0 6px 16px rgba(37,99,235,0.1);">📅 تقویم آموزشی</a>
            <a href="news.php" class="quick-link" style="background:#ecfdf5; border-radius:16px; padding:1.8rem 1.4rem; text-align:center; text-decoration:none; color:#047857; font-weight:600; box-shadow:0 6px 16px rgba(16,185,129,0.1);">📰 اخبار و اطلاعیه‌ها</a>
            <a href="schedule.php" class="quick-link" style="background:#fefce8; border-radius:16px; padding:1.8rem 1.4rem; text-align:center; text-decoration:none; color:#854d0e; font-weight:600; box-shadow:0 6px 16px rgba(234,179,8,0.1);">📋 برنامه هفتگی</a>
            <?php if ($isAdmin): ?>
            <a href="users.php" class="quick-link" style="background:#f3e8ff; border-radius:16px; padding:1.8rem 1.4rem; text-align:center; text-decoration:none; color:#6b21a8; font-weight:600; box-shadow:0 6px 16px rgba(147,51,234,0.1);">👥 مدیریت کاربران</a>
            <a href="logs.php" class="quick-link" style="background:#fee2e2; border-radius:16px; padding:1.8rem 1.4rem; text-align:center; text-decoration:none; color:#9f1239; font-weight:600; box-shadow:0 6px 16px rgba(239,68,68,0.1);">📜 لاگ فعالیت‌ها</a>
            <?php endif; ?>
        </div>
    </section>

</main>

<?php require __DIR__ . '/partials/footer.php'; ?>

<style>
.quick-link:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 28px rgba(0,0,0,0.12) !important;
}

.stat-card:hover {
    transform: scale(1.03);
    transition: transform 0.25s ease;
}
<style>