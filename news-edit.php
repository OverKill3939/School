<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_once __DIR__ . '/auth/upload.php';
require_admin();  // فقط ادمین

$pdo = get_db();

$slug = $_GET['slug'] ?? '';
if (!$slug) {
    http_response_code(404);
    die("شناسه خبر نامعتبر");
}

$stmt = $pdo->prepare("SELECT * FROM news WHERE slug = ? LIMIT 1");
$stmt->execute([$slug]);
$news = $stmt->fetch();

if (!$news) {
    http_response_code(404);
    die("خبر پیدا نشد");
}

$errors = [];
$title = $news['title'];
$excerpt = $news['excerpt'] ?? '';
$content = $news['content'];
$current_image = $news['image_path'];
$current_video = $news['video_path'];
// دریافت گالری
$mediaStmt = $pdo->prepare("SELECT id, media_path, media_type, position FROM news_media WHERE news_id = :id ORDER BY position ASC, id ASC");
$mediaStmt->execute([':id' => $news['id']]);
$gallery = $mediaStmt->fetchAll() ?: [];
$galleryById = [];
foreach ($gallery as $g) {
    $galleryById[(int)$g['id']] = $g;
}
$isPublished = (bool)$news['is_published'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'خطای توکن امنیتی';
    }

    $title       = trim($_POST['title'] ?? '');
    $excerpt     = trim($_POST['excerpt'] ?? '');
    $content     = trim($_POST['content'] ?? '');
    $isPublished = isset($_POST['is_published']);
    $deleteMainImage = (string)($_POST['delete_main_image'] ?? '0') === '1';
    $deleteMainVideo = (string)($_POST['delete_main_video'] ?? '0') === '1';
    $deleteIds = array_values(array_unique(array_filter(
        array_map('intval', $_POST['delete_media'] ?? []),
        static fn (int $id): bool => $id > 0
    )));
    $replaceMediaPaths = [];

    if (mb_strlen($title) < 3 || mb_strlen($title) > 200) {
        $errors[] = 'عنوان باید بین 3 تا 200 کاراکتر باشد';
    }
    if ($excerpt !== '' && mb_strlen($excerpt) > 300) {
        $errors[] = 'خلاصه نباید بیشتر از ۳۰۰ کاراکتر باشد';
    }
    if (mb_strlen($content) < 50) {
        $errors[] = 'محتوا خیلی کوتاه است';
    }

    $image_path = $current_image;
    $mainImagePath = null;
    if (!empty($_FILES['main_image']['name']) && (int)($_FILES['main_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $mainImagePath = upload_news_image($_FILES['main_image']);
        if (!$mainImagePath) {
            $errors[] = 'تصویر اصلی نامعتبر است (jpg/png/webp، حداکثر ۴ مگابایت).';
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

    $video_path = $current_video;
    $mainVideoPath = null;
    if (!empty($_FILES['main_video']['name']) && (int)($_FILES['main_video']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $mainVideoPath = upload_news_video($_FILES['main_video']);
        if (!$mainVideoPath) {
            $errors[] = 'ویدیوی اصلی نامعتبر است (mp4/webm/mov، حداکثر ۲۰ مگابایت).';
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
            $uploadedVideo = upload_news_video($single);
            if ($uploadedVideo) {
                $videoPaths[] = $uploadedVideo;
            } else {
                $errors[] = 'یک یا چند ویدیو نامعتبر است (mp4/webm/mov، حداکثر ۲۰ مگابایت).';
                break;
            }
        }
    }

    if (isset($_FILES['replace_media']) && is_array($_FILES['replace_media']['name'] ?? null)) {
        foreach ($gallery as $g) {
            $mediaId = (int)$g['id'];
            if (in_array($mediaId, $deleteIds, true)) {
                continue;
            }

            $fileError = (int)($_FILES['replace_media']['error'][$mediaId] ?? UPLOAD_ERR_NO_FILE);
            if ($fileError === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $single = [
                'name' => $_FILES['replace_media']['name'][$mediaId] ?? '',
                'type' => $_FILES['replace_media']['type'][$mediaId] ?? '',
                'tmp_name' => $_FILES['replace_media']['tmp_name'][$mediaId] ?? '',
                'error' => $fileError,
                'size' => (int)($_FILES['replace_media']['size'][$mediaId] ?? 0),
            ];

            $uploadedPath = ($g['media_type'] === 'video')
                ? upload_news_video($single)
                : upload_news_image($single);

            if (!$uploadedPath) {
                $errors[] = ($g['media_type'] === 'video')
                    ? 'فایل تعویض ویدیو نامعتبر است (mp4/webm/mov، حداکثر ۲۰ مگابایت).'
                    : 'فایل تعویض تصویر نامعتبر است (jpg/png/webp، حداکثر ۴ مگابایت).';
                break;
            }

            $replaceMediaPaths[$mediaId] = $uploadedPath;
        }
    }

    if (empty($errors)) {
        // حذف رسانه‌های انتخاب‌شده
        if ($deleteIds !== []) {
            $in = implode(',', array_fill(0, count($deleteIds), '?'));
            $sel = $pdo->prepare("SELECT id, media_path FROM news_media WHERE id IN ($in) AND news_id = ?");
            $sel->execute([...$deleteIds, $news['id']]);
            foreach ($sel->fetchAll() as $row) {
                $paths = [
                    __DIR__ . $row['media_path'],
                    __DIR__ . '/public' . $row['media_path'],
                ];
                foreach ($paths as $p) {
                    if ($p && file_exists($p)) {
                        @unlink($p);
                    }
                }
            }
            $del = $pdo->prepare("DELETE FROM news_media WHERE id IN ($in) AND news_id = ?");
            $del->execute([...$deleteIds, $news['id']]);
        }

        if ($replaceMediaPaths !== []) {
            $ids = array_keys($replaceMediaPaths);
            $in = implode(',', array_fill(0, count($ids), '?'));
            $sel = $pdo->prepare("SELECT id, media_path FROM news_media WHERE id IN ($in) AND news_id = ?");
            $sel->execute([...$ids, $news['id']]);
            $rows = $sel->fetchAll() ?: [];

            $updateMedia = $pdo->prepare("UPDATE news_media SET media_path = ? WHERE id = ? AND news_id = ?");
            foreach ($rows as $row) {
                $mediaId = (int)$row['id'];
                if (!isset($replaceMediaPaths[$mediaId])) {
                    continue;
                }

                $oldPath = (string)($row['media_path'] ?? '');
                $paths = [__DIR__ . $oldPath, __DIR__ . '/public' . $oldPath];
                foreach ($paths as $p) {
                    if ($p && file_exists($p)) {
                        @unlink($p);
                    }
                }

                $updateMedia->execute([$replaceMediaPaths[$mediaId], $mediaId, $news['id']]);
            }
        }

        if ($mainImagePath) {
            if ($current_image) {
                $paths = [__DIR__ . $current_image, __DIR__ . '/public' . $current_image];
                foreach ($paths as $p) {
                    if ($p && file_exists($p)) {
                        @unlink($p);
                    }
                }
            }
            $image_path = $mainImagePath;
            $deleteMainImage = false;
        }

        // حذف تصویر اصلی
        if ($deleteMainImage && $image_path) {
            $paths = [__DIR__ . $current_image, __DIR__ . '/public' . $current_image];
            foreach ($paths as $p) {
                if ($p && file_exists($p)) {
                    @unlink($p);
                }
            }
            $image_path = null;
        }

        if ($mainVideoPath) {
            if ($current_video) {
                $paths = [__DIR__ . $current_video, __DIR__ . '/public' . $current_video];
                foreach ($paths as $p) {
                    if ($p && file_exists($p)) {
                        @unlink($p);
                    }
                }
            }
            $video_path = $mainVideoPath;
            $deleteMainVideo = false;
        }

        // حذف ویدیوی اصلی
        if ($deleteMainVideo && $video_path) {
            $paths = [__DIR__ . $current_video, __DIR__ . '/public' . $current_video];
            foreach ($paths as $p) {
                if ($p && file_exists($p)) {
                    @unlink($p);
                }
            }
            $video_path = null;
        }

        $publishedAt = $news['published_at'] ?: date('Y-m-d H:i:s');
        if ($isPublished && !$news['is_published']) {
            $publishedAt = date('Y-m-d H:i:s');
        }

        $updateStmt = $pdo->prepare("
            UPDATE news 
            SET title = :title,
                excerpt = :excerpt,
                content = :content,
                image_path = :image,
                video_path = :video,
                is_published = :is_published,
                published_at = :published_at,
                updated_at = datetime('now', 'localtime')
            WHERE id = :id
        ");
        $updateStmt->execute([
            ':title'        => $title,
            ':excerpt'      => $excerpt !== '' ? $excerpt : null,
            ':content'      => $content,
            ':image'        => $image_path,
            ':video'        => $video_path,
            ':is_published' => $isPublished ? 1 : 0,
            ':published_at' => $publishedAt,
            ':id'           => $news['id'],
        ]);

        // اضافه کردن تصاویر جدید به گالری
        if (!empty($imagesPaths)) {
            $pos = (int)$pdo->query("SELECT COALESCE(MAX(position), -1) FROM news_media WHERE news_id = " . (int)$news['id'])->fetchColumn() + 1;
            $insertMedia = $pdo->prepare("
                INSERT INTO news_media (news_id, media_path, media_type, position)
                VALUES (:news_id, :path, 'image', :position)
            ");
            foreach ($imagesPaths as $p) {
                $insertMedia->execute([
                    ':news_id' => $news['id'],
                    ':path' => $p,
                    ':position' => $pos++,
                ]);
            }
        }

        // اضافه کردن ویدیوهای جدید به گالری
        if (!empty($videoPaths)) {
            $pos = (int)$pdo->query("SELECT COALESCE(MAX(position), -1) FROM news_media WHERE news_id = " . (int)$news['id'] . " AND media_type = 'video'")->fetchColumn() + 1;
            $insertMedia = $pdo->prepare("
                INSERT INTO news_media (news_id, media_path, media_type, position)
                VALUES (:news_id, :path, 'video', :position)
            ");
            foreach ($videoPaths as $p) {
                $insertMedia->execute([
                    ':news_id' => $news['id'],
                    ':path' => $p,
                    ':position' => $pos++,
                ]);
            }
        }

        header("Location: news-detail.php?slug=" . urlencode($slug) . "&updated=1");
        exit;
    }
}

$pageTitle = 'ویرایش خبر: ' . htmlspecialchars($title);
$extraStyles = ['css/news.css'];
$extraScripts = ['public/js/edit-media.js', 'public/js/news-video-frame.js'];
require __DIR__ . '/partials/header.php';
?>

<main class="news-form-page">
  <div class="form-head">
    <div>
      <p class="kicker">مدیریت خبر</p>
      <h1>ویرایش خبر</h1>
    </div>
    <a class="link-back" href="news-detail.php?slug=<?= urlencode($slug) ?>">← بازگشت به خبر</a>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
      <ul><?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success">خبر با موفقیت به‌روزرسانی شد.</div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="news-form">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <label class="form-field">
      <span>عنوان خبر</span>
      <input type="text" name="title" value="<?= htmlspecialchars($title) ?>" required maxlength="200">
    </label>

    <label class="form-field">
      <span>خلاصه کوتاه (اختیاری)</span>
      <textarea name="excerpt" rows="3" maxlength="300"><?= htmlspecialchars($excerpt) ?></textarea>
    </label>

    <label class="form-field">
      <span>محتوای خبر</span>
      <textarea name="content" rows="10" required><?= htmlspecialchars($content) ?></textarea>
    </label>

    <div class="form-grid">
      <div class="form-field">
        <div class="media-head">
          <span class="section-title">رسانه‌ها</span>
        </div>

        <div class="main-media-grid">
          <div class="media-card media-card--main">
            <div class="media-card-head">
              <div class="badge badge-image">تصویر اصلی</div>
              <button type="button" class="btn-delete" data-main-delete="image">حذف</button>
            </div>
            <div class="media-preview">
              <?php if ($current_image): ?>
                <img src="<?= htmlspecialchars($current_image) ?>" alt="">
              <?php else: ?>
                <p class="muted">ثبت نشده</p>
              <?php endif; ?>
            </div>
            <label class="file-btn file-btn--small file-btn--replace-image">
              <span><?= $current_image ? 'تعویض تصویر اصلی' : 'افزودن تصویر اصلی' ?></span>
              <input type="file" name="main_image" accept="image/jpeg,image/png,image/webp" data-preview-main="image">
            </label>
            <input type="hidden" name="delete_main_image" id="delete_main_image" value="0">
          </div>

          <div class="media-card media-card--main">
            <div class="media-card-head">
              <div class="badge badge-video">ویدیوی اصلی</div>
              <button type="button" class="btn-delete" data-main-delete="video">حذف</button>
            </div>
            <div class="media-preview">
              <?php if ($current_video): ?>
                <video src="<?= htmlspecialchars($current_video) ?>" preload="metadata" controls muted playsinline data-frame-preview data-hover-controls="1"></video>
              <?php else: ?>
                <p class="muted">ثبت نشده</p>
              <?php endif; ?>
            </div>
            <label class="file-btn file-btn--small file-btn--replace-video">
              <span><?= $current_video ? 'تعویض ویدیوی اصلی' : 'افزودن ویدیوی اصلی' ?></span>
              <input type="file" name="main_video" accept="video/mp4,video/webm,video/quicktime" data-preview-main="video">
            </label>
            <input type="hidden" name="delete_main_video" id="delete_main_video" value="0">
          </div>
        </div>

        <?php if ($gallery !== []): ?>
          <div class="gallery-thumbs selectable">
            <?php foreach ($gallery as $g): ?>
              <div class="thumb-select" data-media-id="<?= (int)$g['id'] ?>">
                <div class="thumb-top">
                  <span class="media-badge <?= $g['media_type']==='video' ? 'badge-video' : 'badge-image' ?>">
                    <?= $g['media_type']==='video' ? 'ویدیو' : 'تصویر' ?>
                  </span>
                  <button type="button" class="btn-delete card-delete" data-id="<?= (int)$g['id'] ?>">حذف</button>
                </div>

                <?php if ($g['media_type'] === 'video'): ?>
                  <video src="<?= htmlspecialchars($g['media_path']) ?>" controls preload="metadata" muted playsinline data-hover-controls="1"></video>
                <?php else: ?>
                  <img src="<?= htmlspecialchars($g['media_path']) ?>" alt="">
                <?php endif; ?>
                <label class="file-btn file-btn--small <?= $g['media_type']==='video' ? 'file-btn--replace-video' : 'file-btn--replace-image' ?>">
                  <span><?= $g['media_type']==='video' ? 'تعویض ویدیو' : 'تعویض تصویر' ?></span>
                  <input
                    type="file"
                    name="replace_media[<?= (int)$g['id'] ?>]"
                    accept="<?= $g['media_type']==='video' ? 'video/mp4,video/webm,video/quicktime' : 'image/jpeg,image/png,image/webp' ?>"
                    data-replace-media="<?= $g['media_type']==='video' ? 'video' : 'image' ?>"
                  >
                </label>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="muted">رسانه‌ای در گالری نیست</p>
        <?php endif; ?>

        <div class="upload-pair">
          <div class="file-inline">
            <label class="file-btn file-btn--add-image">
              <span>افزودن تصاویر (چندتایی)</span>
              <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple data-preview-collection="image">
            </label>
          </div>
          <div class="file-inline">
            <label class="file-btn file-btn--add-video">
              <span>افزودن ویدیوها (چندتایی)</span>
              <input type="file" name="videos[]" accept="video/mp4,video/webm,video/quicktime" multiple data-preview-collection="video">
            </label>
          </div>
        </div>

        <div class="pending-media" id="pendingMediaWrap" hidden>
          <p class="muted">پیش‌نمایش فایل‌های جدید (بعد از ذخیره به خبر اضافه می‌شوند)</p>
          <div class="gallery-thumbs pending-media-grid" id="pendingMediaGrid"></div>
        </div>
      </div>
    </div>

    <label class="checkbox">
      <input type="checkbox" name="is_published" <?= $isPublished ? 'checked' : '' ?> />
      <span>انتشار</span>
    </label>

    <div class="form-actions">
      <button type="submit" class="btn-primary">ذخیره تغییرات</button>
      <a href="news-detail.php?slug=<?= urlencode($slug) ?>" class="btn-secondary">انصراف</a>
    </div>
  </form>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
