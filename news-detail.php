<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_login();

$pdo = get_db();

$slug = $_GET['slug'] ?? '';
if ($slug === '') {
    http_response_code(404);
    echo "<h1>خبر پیدا نشد</h1>";
    exit;
}

$stmt = $pdo->prepare("
    SELECT n.*, u.first_name, u.last_name
    FROM news n
    JOIN users u ON n.author_id = u.id
    WHERE n.slug = ?
    LIMIT 1
");
$stmt->execute([$slug]);
$news = $stmt->fetch();
if (!$news) {
    http_response_code(404);
    echo "<h1>خبر پیدا نشد</h1>";
    exit;
}

// گالری
$mediaStmt = $pdo->prepare("SELECT media_path, media_type, position FROM news_media WHERE news_id = :id ORDER BY position ASC, id ASC");
$mediaStmt->execute([':id' => (int)$news['id']]);
$gallery = $mediaStmt->fetchAll() ?: [];

// جدا کردن عکس‌ها و ویدیوها
$images = [];
$videos = [];
foreach ($gallery as $g) {
    if (($g['media_type'] ?? 'image') === 'video') {
        $videos[] = $g;
    } else {
        $images[] = $g;
    }
}

// تعیین رسانه شاخص
$heroImage = $news['image_path'] ?: null;
$heroVideo = $news['video_path'] ?: null;

if (!$heroImage && !empty($images)) {
    $heroImage = $images[0]['media_path'];
    array_shift($images);
}
if (!$heroVideo && !empty($videos)) {
    $heroVideo = $videos[0]['media_path'];
    array_shift($videos);
}

$displayImages = $images;
if ($heroImage) {
    $hasHeroInGallery = false;
    foreach ($displayImages as $img) {
        if (($img['media_path'] ?? '') === $heroImage) {
            $hasHeroInGallery = true;
            break;
        }
    }
    if (!$hasHeroInGallery) {
        array_unshift($displayImages, ['media_path' => $heroImage, 'media_type' => 'image']);
    }
}

// اگر منتشر نشده و کاربر ادمین نیست، اجازه نمایش نده
if (!$news['is_published'] && (current_user()['role'] ?? '') !== 'admin') {
    http_response_code(404);
    echo "<h1>خبر پیدا نشد یا منتشر نشده</h1>";
    exit;
}

$pageTitle = htmlspecialchars($news['title']);
$extraStyles = ['css/news.css'];

function fa_date(string $date): string
{
    $ts = strtotime($date);
    if ($ts === false) {
        return $date;
    }
    if (class_exists('IntlDateFormatter')) {
        $fmt = new IntlDateFormatter('fa_IR', IntlDateFormatter::MEDIUM, IntlDateFormatter::SHORT, null, IntlDateFormatter::GREGORIAN, 'd MMMM y، H:mm');
        $out = $fmt->format($ts);
        if ($out !== false) {
            return $out;
        }
    }
    return date('Y/m/d - H:i', $ts);
}

require __DIR__ . '/partials/header.php';
?>

<main class="news-detail">
  <a href="news.php" class="back-link">← بازگشت به لیست اخبار</a>

  <article class="detail-card">
    <header>
      <h1><?= htmlspecialchars($news['title']) ?></h1>
      <div class="news-meta">
        <span><?= fa_date((string)$news['published_at']) ?></span>
        <span>•</span>
        <span><?= htmlspecialchars($news['first_name'] . ' ' . $news['last_name']) ?></span>
      </div>
    </header>

    <?php if ($heroVideo): ?>
      <div class="detail-media hero-media">
        <video id="heroVideo" controls preload="metadata" src="<?= htmlspecialchars($heroVideo) ?>"></video>
      </div>
      <?php if (!empty($videos)): ?>
        <div class="video-controls">
          <?php foreach ([$heroVideo, ...array_column($videos, 'media_path')] as $idx => $src): ?>
            <button class="video-btn" data-src="<?= htmlspecialchars($src) ?>">ویدیو <?= $idx + 1 ?></button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php elseif ($heroImage): ?>
      <div class="detail-media hero-media">
        <img src="<?= htmlspecialchars($heroImage) ?>" alt="<?= htmlspecialchars($news['title']) ?>" loading="lazy" />
      </div>
    <?php endif; ?>

    <?php if (!empty($displayImages)): ?>
      <div class="gallery">
        <?php foreach ($displayImages as $g): ?>
          <div class="gallery-card">
            <img src="<?= htmlspecialchars($g['media_path']) ?>" alt="" loading="lazy" />
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($videos)): ?>
      <div class="video-stack muted-note">ویدیوها در کارت بالا قابل جابه‌جایی هستند.</div>
    <?php endif; ?>

    <div class="news-content">
      <?= nl2br(htmlspecialchars($news['content'])) ?>
    </div>
  </article>

  <?php if (current_user() && current_user()['role'] === 'admin'): ?>
    <div class="detail-actions">
      <a href="news-edit.php?slug=<?= urlencode($news['slug']) ?>" class="btn-primary">ویرایش خبر</a>
      <form method="POST" action="news-delete.php" onsubmit="return confirm('واقعاً می‌خوای این خبر رو حذف کنی؟');">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="slug" value="<?= htmlspecialchars($news['slug']) ?>">
        <button type="submit" class="btn-danger">حذف خبر</button>
      </form>
    </div>
  <?php endif; ?>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>

<?php if ($heroVideo && (!empty($videos) || true)): ?>
<script>
  const heroVideoEl = document.getElementById('heroVideo');
  const btns = document.querySelectorAll('.video-btn');
  btns.forEach(btn => {
    btn.addEventListener('click', () => {
      const src = btn.dataset.src;
      if (!src || !heroVideoEl) return;
      heroVideoEl.pause();
      heroVideoEl.src = src;
      heroVideoEl.load();
      heroVideoEl.play().catch(() => {});
    });
  });
</script>
<?php endif; ?>
