<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_login();

$pageTitle   = 'داشبورد | هنرستان دارالفنون';
$activeNav   = 'home';
$extraStyles = ['css/home.css?v=' . filemtime(__DIR__ . '/css/home.css')];
$extraScripts = ['js/home-entrance.js?v=' . filemtime(__DIR__ . '/js/home-entrance.js')];

$user    = current_user();
$isAdmin = ($user['role'] === 'admin');

$pdo          = get_db();
$todayEvents  = (int)$pdo->query("SELECT COUNT(*) FROM calendar_events WHERE year = " . date('Y') . " AND month = " . date('n') . " AND day = " . date('j'))->fetchColumn();
$newsCount    = (int)$pdo->query("SELECT COUNT(*) FROM news WHERE is_published = 1")->fetchColumn();
$latestNews   = $pdo->query("SELECT title, slug, published_at FROM news WHERE is_published = 1 ORDER BY published_at DESC LIMIT 3")->fetchAll();
$studentCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$classCount   = (int)$pdo->query("SELECT COUNT(DISTINCT field) FROM schedules")->fetchColumn();

function fa_date(string $date): string
{
    $ts = strtotime($date);
    if ($ts === false) {
        return $date;
    }

    if (class_exists('IntlDateFormatter')) {
        $fmt = new IntlDateFormatter('fa_IR', IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE, null, IntlDateFormatter::GREGORIAN, 'd MMMM y');
        $formatted = $fmt->format($ts);
        if ($formatted !== false) {
            return $formatted;
        }
    }

    return date('Y/m/d', $ts);
}

require __DIR__ . '/partials/header.php';
?>

<main class="home-page">

  <section class="home-hero">
    <div class="hero-copy">
      <h1>سلام <?= htmlspecialchars($user['first_name']) ?> 👋</h1>
      <p>خوش آمدید به پنل مدیریت هنرستان دارالفنون</p>
      <div class="hero-actions">
        <a href="calendar.php" class="cta cta-primary">تقویم آموزشی</a>
        <a href="news.php" class="cta cta-success">اخبار جدید</a>
        <?php if ($isAdmin): ?>
          <a href="users.php" class="cta cta-purple">مدیریت کاربران</a>
        <?php endif; ?>
      </div>
    </div>
    <div class="hero-ghost" aria-hidden="true">🏫</div>
  </section>

  <section class="home-stats">
    <article class="stat-card">
      <div class="stat-emoji">📰</div>
      <div class="stat-value"><?= $newsCount ?></div>
      <div class="stat-label">خبر منتشر شده</div>
    </article>
    <article class="stat-card">
      <div class="stat-emoji">📅</div>
      <div class="stat-value"><?= $todayEvents ?></div>
      <div class="stat-label">رویداد امروز</div>
    </article>
    <article class="stat-card">
      <div class="stat-emoji">👨‍🎓</div>
      <div class="stat-value"><?= $studentCount ?></div>
      <div class="stat-label">دانش‌آموز فعال</div>
    </article>
    <article class="stat-card">
      <div class="stat-emoji">⚡</div>
      <div class="stat-value"><?= $classCount ?></div>
      <div class="stat-label">کلاس فعال</div>
    </article>
  </section>

  <section class="home-news">
    <div class="section-head">
      <h2>آخرین اخبار</h2>
      <a href="news.php" class="link-more">مشاهده همه ←</a>
    </div>

    <?php if ($latestNews === []): ?>
      <div class="empty-state">هنوز خبری منتشر نشده است.</div>
    <?php else: ?>
    <div class="news-grid">
      <?php foreach ($latestNews as $n): ?>
      <a href="news-detail.php?slug=<?= urlencode($n['slug']) ?>" class="news-card">
        <div class="news-meta"><?= fa_date($n['published_at']) ?></div>
        <h3><?= htmlspecialchars($n['title']) ?></h3>
        <span class="news-link">مطالعه خبر →</span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>

  <section class="home-quick">
    <h2>دسترسی سریع</h2>
    <div class="quick-grid">
      <a href="calendar.php" class="quick-link quick-blue">📅 تقویم آموزشی</a>
      <a href="news.php" class="quick-link quick-green">📰 اخبار و اطلاعیه‌ها</a>
      <a href="schedule.php" class="quick-link quick-amber">📋 برنامه هفتگی</a>
      <?php if ($isAdmin): ?>
      <a href="users.php" class="quick-link quick-purple">👥 مدیریت کاربران</a>
      <a href="logs.php" class="quick-link quick-red">📝 لاگ فعالیت‌ها</a>
      <?php endif; ?>
    </div>
  </section>

</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
