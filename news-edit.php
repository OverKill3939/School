<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
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
$content = $news['content'];
$current_image = $news['image_path'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $errors[] = 'خطای توکن امنیتی';
    }

    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (strlen($title) < 3 || strlen($title) > 200) {
        $errors[] = 'عنوان باید بین ۳ تا ۲۰۰ کاراکتر باشد';
    }
    if (strlen($content) < 20) {
        $errors[] = 'محتوا خیلی کوتاه است';
    }

    $image_path = $current_image;
    if (!empty($_FILES['image']['name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploaded = upload_news_image($_FILES['image']);
        if ($uploaded) {
            // حذف عکس قدیمی اگر وجود داشت
            if ($current_image && file_exists(__DIR__ . '/public' . $current_image)) {
                @unlink(__DIR__ . '/public' . $current_image);
            }
            $image_path = $uploaded;
        } else {
            $errors[] = 'آپلود عکس ناموفق بود';
        }
    }

    if (empty($errors)) {
        $updateStmt = $pdo->prepare("
            UPDATE news 
            SET title = ?, content = ?, image_path = ?, updated_at = datetime('now', 'localtime')
            WHERE id = ?
        ");
        $updateStmt->execute([$title, $content, $image_path, $news['id']]);

        header("Location: news-detail.php?slug=" . urlencode($slug) . "&updated=1");
        exit;
    }
}

$pageTitle = 'ویرایش خبر: ' . htmlspecialchars($title);
require __DIR__ . '/partials/header.php';
?>

<main class="container" style="max-width: 900px; margin: 2rem auto;">
    <h1>ویرایش خبر</h1>
    <a href="news-detail.php?slug=<?= urlencode($slug) ?>" style="display: inline-block; margin: 1rem 0;">← بازگشت به خبر</a>

    <?php if (!empty($errors)): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <ul style="margin: 0; padding-right: 1.5rem;">
                <?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['updated'])): ?>
        <div style="background: #d1fae5; color: #065f46; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
            خبر با موفقیت به‌روزرسانی شد.
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div style="margin-bottom: 1.5rem;">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: bold;">عنوان خبر</label>
            <input type="text" name="title" value="<?= htmlspecialchars($title) ?>" required 
                   style="width: 100%; padding: 0.7rem; border: 1px solid #cbd5e1; border-radius: 6px;">
        </div>

        <div style="margin-bottom: 1.5rem;">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: bold;">محتوای خبر</label>
            <textarea name="content" rows="12" required 
                      style="width: 100%; padding: 0.7rem; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit;">
                <?= htmlspecialchars($content) ?>
            </textarea>
        </div>

        <div style="margin-bottom: 1.5rem;">
            <label style="display: block; margin-bottom: 0.5rem; font-weight: bold;">تصویر فعلی</label>
            <?php if ($current_image): ?>
                <img src="<?= htmlspecialchars($current_image) ?>" alt="تصویر فعلی" style="max-width: 400px; border-radius: 8px; margin-bottom: 0.8rem;">
            <?php else: ?>
                <p style="color: #64748b;">تصویری تنظیم نشده است</p>
            <?php endif; ?>
            
            <label style="display: block; margin: 1rem 0 0.5rem; font-weight: bold;">تصویر جدید (اختیاری)</label>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
        </div>

        <div style="display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary" style="padding: 0.7rem 1.4rem; background: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer;">
                ذخیره تغییرات
            </button>
            <a href="news-detail.php?slug=<?= urlencode($slug) ?>" 
               style="padding: 0.7rem 1.4rem; background: #e2e8f0; color: #1e293b; border-radius: 6px; text-decoration: none; display: inline-block;">
                انصراف
            </a>
        </div>
    </form>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>