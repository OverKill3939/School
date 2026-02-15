<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_login();

$pageTitle = 'اخبار | هنرستان دارالفنون';
$activeNav = 'news';
$extraStyles = ['css/news.css'];
$extraScripts = ['public/js/news-video-frame.js'];

function fa_date(string $date): string
{
    $ts = strtotime($date);
    if ($ts === false) {
        return $date;
    }
    if (class_exists('IntlDateFormatter')) {
        $fmt = new IntlDateFormatter('fa_IR', IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE, null, IntlDateFormatter::GREGORIAN, 'd MMMM y');
        $out = $fmt->format($ts);
        if ($out !== false) {
            return $out;
        }
    }
    return date('Y/m/d', $ts);
}

$pdo = get_db();
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9;
$offset = ($page - 1) * $perPage;

$totalStmt = $pdo->query("SELECT COUNT(*) FROM news WHERE is_published = 1");
$total = (int)$totalStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$stmt = $pdo->prepare("
    SELECT n.id, n.title, n.slug, n.excerpt, n.image_path, n.video_path, n.published_at,
           u.first_name, u.last_name
    FROM news n
    JOIN users u ON n.author_id = u.id
    WHERE n.is_published = 1
    ORDER BY n.published_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$newsItems = $stmt->fetchAll() ?: [];

$user = current_user();

require __DIR__ . '/partials/header.php';
?>

<main class="news-page">
  <div class="news-head">
    <div>
      <p class="kicker">مرکز اطلاع‌رسانی</p>
      <h1>اخبار و اطلاعیه‌ها</h1>
    </div>
    <?php if (($user = current_user()) && ($user['role'] ?? '') === 'admin'): ?>
      <div class="news-head-actions">
        <a class="btn-primary" href="news-create.php">ایجاد خبر جدید</a>
        <a class="btn-secondary" href="news-drafts.php">پیش‌نویس‌ها</a>
      </div>
    <?php endif; ?>
  </div>

  <?php if (empty($newsItems)): ?>
    <div class="empty-state">هنوز خبری منتشر نشده است.</div>
  <?php else: ?>
    <div class="news-grid">
      <?php foreach ($newsItems as $item): ?>
        <article class="news-card">
          <?php if (!empty($item['image_path'])): ?>
            <img src="<?= htmlspecialchars($item['image_path']) ?>"
                 alt="<?= htmlspecialchars($item['title']) ?>" loading="lazy" />
          <?php elseif (!empty($item['video_path'])): ?>
            <div class="news-media-wrap">
              <video class="news-video-preview" src="<?= htmlspecialchars($item['video_path']) ?>" preload="metadata" muted playsinline data-frame-preview></video>
              <span class="media-play-icon">▶</span>
            </div>
          <?php endif; ?>
          <div class="news-body">
            <div class="news-meta">
              <span><?= fa_date((string)$item['published_at']) ?></span>
              <span>•</span>
              <span><?= htmlspecialchars($item['first_name'] . ' ' . $item['last_name']) ?></span>
            </div>
            <h3><?= htmlspecialchars($item['title']) ?></h3>
            <?php if (!empty($item['excerpt'])): ?>
              <p class="news-excerpt"><?= htmlspecialchars($item['excerpt']) ?></p>
            <?php endif; ?>
            <a class="news-link" href="news-detail.php?slug=<?= urlencode($item['slug']) ?>">مشاهده خبر →</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <div class="pagination">
      <?php if ($page > 1): ?>
        <a class="page-btn" href="news.php?page=<?= $page - 1 ?>">قبلی</a>
      <?php endif; ?>
      <span class="page-info">صفحه <?= $page ?> از <?= $totalPages ?></span>
      <?php if ($page < $totalPages): ?>
        <a class="page-btn" href="news.php?page=<?= $page + 1 ?>">بعدی</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
