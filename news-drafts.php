<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_admin();

$pageTitle   = 'پیش‌نویس اخبار';
$activeNav   = 'news';
$extraStyles = ['css/news.css'];

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
$perPage = 12;
$offset = ($page - 1) * $perPage;

$total = (int)$pdo->query("SELECT COUNT(*) FROM news WHERE is_published = 0")->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

$stmt = $pdo->prepare("
    SELECT id, title, slug, excerpt, created_at, image_path, video_path
    FROM news
    WHERE is_published = 0
    ORDER BY created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$drafts = $stmt->fetchAll() ?: [];

require __DIR__ . '/partials/header.php';
?>

<main class="news-page">
  <div class="news-head">
    <div>
      <p class="kicker">پیش‌نویس‌ها</p>
      <h1>اخبار منتشرنشده</h1>
    </div>
    <a class="btn-secondary" href="news.php">← فهرست اخبار</a>
  </div>

  <?php if ($drafts === []): ?>
    <div class="empty-state">پیش‌نویسی وجود ندارد.</div>
  <?php else: ?>
    <div class="news-grid">
      <?php foreach ($drafts as $item): ?>
        <article class="news-card">
          <?php if (!empty($item['image_path'])): ?>
            <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" loading="lazy" />
          <?php endif; ?>
          <div class="news-body">
            <div class="news-meta">
              <span>ایجاد: <?= fa_date((string)$item['created_at']) ?></span>
            </div>
            <h3><?= htmlspecialchars($item['title']) ?></h3>
            <?php if (!empty($item['excerpt'])): ?>
              <p class="news-excerpt"><?= htmlspecialchars($item['excerpt']) ?></p>
            <?php endif; ?>
            <div class="draft-actions">
              <a class="news-link" href="news-detail.php?slug=<?= urlencode($item['slug']) ?>">مشاهده پیش‌نمایش</a>
              <div class="draft-buttons">
                <form method="post" action="news-publish.php" class="inline-form">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="slug" value="<?= htmlspecialchars($item['slug']) ?>">
                  <button type="submit" class="btn-primary">انتشار</button>
                </form>
                <form method="post" action="news-delete.php" class="inline-form" onsubmit="return confirm('این پیش‌نویس حذف شود؟');">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="slug" value="<?= htmlspecialchars($item['slug']) ?>">
                  <button type="submit" class="btn-danger">حذف</button>
                </form>
              </div>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <div class="pagination">
      <?php if ($page > 1): ?>
        <a class="page-btn" href="news-drafts.php?page=<?= $page - 1 ?>">قبلی</a>
      <?php endif; ?>
      <span class="page-info">صفحه <?= $page ?> از <?= $totalPages ?></span>
      <?php if ($page < $totalPages): ?>
        <a class="page-btn" href="news-drafts.php?page=<?= $page + 1 ?>">بعدی</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
