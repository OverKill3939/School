<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_once __DIR__ . '/auth/upload.php';
require_admin();   // فقط ادمین

$pageTitle = 'ایجاد خبر جدید';
$extraStyles = ['css/news.css'];
require __DIR__ . '/partials/header.php';

$errors = [];
$title = $content = $excerpt = '';
$imagePath = $videoPath = null;
$isPublished = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $excerpt = trim($_POST['excerpt'] ?? '');
    $isPublished = isset($_POST['is_published']);
    $csrf    = $_POST['csrf_token'] ?? '';

    if (!csrf_check($csrf)) {
        $errors[] = 'خطای امنیتی. لطفاً دوباره تلاش کنید.';
    }
    if (mb_strlen($title) < 5 || mb_strlen($title) > 200) {
        $errors[] = 'عنوان باید بین ۵ تا ۲۰۰ کاراکتر باشد.';
    }
    if ($excerpt !== '' && mb_strlen($excerpt) > 300) {
        $errors[] = 'خلاصه نباید بیش از ۳۰۰ کاراکتر باشد.';
    }
    if (mb_strlen($content) < 50) {
        $errors[] = 'متن خبر خیلی کوتاه است.';
    }

    $mainImagePath = null;
    if (!empty($_FILES['main_image']['name']) && (int)($_FILES['main_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $mainImagePath = upload_news_image($_FILES['main_image']);
        if (!$mainImagePath) {
            $errors[] = 'تصویر اصلی نامعتبر است (jpg/png/webp، حداکثر ۴ مگابایت).';
        }
    }

    $mainVideoPath = null;
    if (!empty($_FILES['main_video']['name']) && (int)($_FILES['main_video']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $mainVideoPath = upload_news_video($_FILES['main_video']);
        if (!$mainVideoPath) {
            $errors[] = 'ویدیوی اصلی نامعتبر است (mp4/webm/mov، حداکثر ۲۰ مگابایت).';
        }
    }

    $imagesPaths = [];
    if (!empty($_FILES['images']['name'][0])) {
        $files = $_FILES['images'];
        for ($i = 0, $count = count($files['name']); $i < $count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $single = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ];
            $path = upload_news_image($single);
            if ($path) {
                $imagesPaths[] = $path;
            } else {
                $errors[] = 'یک یا چند تصویر نامعتبر است (jpg/png/webp، حداکثر ۴ مگابایت).';
                break;
            }
        }
    }

    $videoPaths = [];
    if (!empty($_FILES['videos']['name'][0])) {
        $files = $_FILES['videos'];
        for ($i = 0, $count = count($files['name']); $i < $count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $single = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ];
            $path = upload_news_video($single);
            if ($path) {
                $videoPaths[] = $path;
            } else {
                $errors[] = 'یک یا چند ویدیو نامعتبر است (mp4/webm/mov، حداکثر ۲۰ مگابایت).';
                break;
            }
        }
    }

    if (empty($errors)) {
        $pdo = get_db();
        $slugBase = preg_replace('/[^\p{L}\p{N}]+/u', '-', $title);
        $slug = strtolower(trim($slugBase, '-'));
        if ($slug === '') {
            $slug = 'news-' . time();
        }

        $galleryImages = $imagesPaths;
        $galleryVideos = $videoPaths;
        $primaryImagePath = $mainImagePath;
        $primaryVideoPath = $mainVideoPath;

        if (!$primaryImagePath && !empty($galleryImages)) {
            $primaryImagePath = array_shift($galleryImages);
        }
        if (!$primaryVideoPath && !empty($galleryVideos)) {
            $primaryVideoPath = array_shift($galleryVideos);
        }

        // اگر ستون published_at در DB نال‌پذیر نباشد، زمان فعلی مقداردهی می‌شود
        $publishedAt = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("
            INSERT INTO news (title, slug, excerpt, content, image_path, video_path, author_id, is_published, published_at)
            VALUES (:title, :slug, :excerpt, :content, :image, :video, :author, :is_published, :published_at)
        ");
        try {
            $stmt->execute([
                'title'        => $title,
                'slug'         => $slug,
                'excerpt'      => $excerpt !== '' ? $excerpt : null,
                'content'      => $content,
                'image'        => $primaryImagePath,
                'video'        => $primaryVideoPath,
                'author'       => current_user()['id'],
                'is_published' => $isPublished ? 1 : 0,
                'published_at' => $publishedAt,
            ]);

            // ذخیره سایر تصاویر در جدول news_media
            $newsId = (int)$pdo->lastInsertId();
            if (!empty($galleryImages)) {
                $newsId = (int)$pdo->lastInsertId();
                $pos = 0;
                $insertMedia = $pdo->prepare("
                    INSERT INTO news_media (news_id, media_path, media_type, position)
                    VALUES (:news_id, :path, 'image', :position)
                ");
                foreach ($galleryImages as $p) {
                    $insertMedia->execute([
                        ':news_id' => $newsId,
                        ':path' => $p,
                        ':position' => $pos++,
                    ]);
                }
            }

            // ذخیره ویدیوهای اضافه
            if (!empty($galleryVideos)) {
                $pos = 0;
                $insertMedia = $pdo->prepare("
                    INSERT INTO news_media (news_id, media_path, media_type, position)
                    VALUES (:news_id, :path, 'video', :position)
                ");
                foreach ($galleryVideos as $p) {
                    $insertMedia->execute([
                        ':news_id' => $newsId,
                        ':path' => $p,
                        ':position' => $pos++,
                    ]);
                }
            }

            header("Location: news.php?success=1");
            exit;
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'slug')) {
                $errors[] = 'عنوان تکراری است. لطفاً عنوان دیگری وارد کنید.';
            } else {
                $errors[] = 'خطای دیتابیس در ذخیره خبر.';
            }
        }
    }
}
?>

<main class="news-form-page">
  <div class="form-head">
    <div>
      <p class="kicker">مدیریت خبر</p>
      <h1>ایجاد خبر جدید</h1>
    </div>
    <a class="link-back" href="news.php">← بازگشت</a>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
      <ul>
        <?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="news-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <label class="form-field">
      <span>عنوان خبر</span>
      <input type="text" name="title" value="<?= htmlspecialchars($title) ?>" required maxlength="200">
    </label>

    <label class="form-field">
      <span>خلاصه کوتاه (اختیاری)</span>
      <textarea name="excerpt" rows="3" maxlength="300" placeholder="۲ تا ۳ خط توضیح"><?= htmlspecialchars($excerpt) ?></textarea>
    </label>

    <label class="form-field">
      <span>متن خبر</span>
      <textarea name="content" rows="10" required><?= htmlspecialchars($content) ?></textarea>
    </label>

    <div class="form-grid">
      <label class="form-field">
        <span>تصویر اصلی خبر (اختیاری)</span>
        <input type="file" name="main_image" accept="image/jpeg,image/png,image/webp">
      </label>
      <label class="form-field">
        <span>ویدیوی اصلی خبر (اختیاری)</span>
        <input type="file" name="main_video" accept="video/mp4,video/webm,video/quicktime">
      </label>
    </div>

    <div class="form-grid">
      <label class="form-field">
        <span>تصاویر (چندتایی - jpg/png/webp - حداکثر ۴ مگابایت هرکدام)</span>
        <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
      </label>
      <label class="form-field">
        <span>ویدیو (mp4/webm/mov - حداکثر ۲۰ مگابایت)</span>
        <input type="file" name="videos[]" accept="video/mp4,video/webm,video/quicktime" multiple>
      </label>
    </div>

    <label class="checkbox">
      <input type="checkbox" name="is_published" <?= $isPublished ? 'checked' : '' ?> />
      <span>انتشار فوری</span>
    </label>

    <div class="form-actions">
      <button type="submit" class="btn-primary">ذخیره</button>
      <a href="news.php" class="btn-secondary">انصراف</a>
    </div>
  </form>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
