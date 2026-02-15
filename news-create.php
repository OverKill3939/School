<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_admin();   // فقط ادمین

$pageTitle = 'ایجاد خبر جدید';
require __DIR__ . '/partials/header.php';

$errors = [];
$title = $content = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $csrf    = $_POST['csrf_token'] ?? '';

    if (!csrf_check($csrf)) {
        $errors[] = 'خطای امنیتی. لطفاً دوباره تلاش کنید.';
    }
    if (mb_strlen($title) < 5 || mb_strlen($title) > 200) {
        $errors[] = 'عنوان باید بین ۵ تا ۲۰۰ کاراکتر باشد.';
    }
    if (mb_strlen($content) < 20) {
        $errors[] = 'متن خبر خیلی کوتاه است.';
    }

    $imagePath = null;
    if (!empty($_FILES['image']['name'])) {
        $imagePath = upload_news_image($_FILES['image']);
        if (!$imagePath) {
            $errors[] = 'فایل عکس نامعتبر است (فقط jpg, png, webp مجاز است).';
        }
    }

    if (empty($errors)) {
        $pdo = get_db();
        $slug = strtolower(preg_replace('/[^a-z0-9]+/u', '-', $title . '-' . time()));
        $slug = trim($slug, '-');

        $stmt = $pdo->prepare("
            INSERT INTO news (title, slug, content, image_path, author_id)
            VALUES (:title, :slug, :content, :image, :author)
        ");
        $stmt->execute([
            'title'   => $title,
            'slug'    => $slug,
            'content' => $content,
            'image'   => $imagePath,
            'author'  => current_user()['id']
        ]);

        header("Location: news.php?success=1");
        exit;
    }
}
?>

<main class="container">
    <section class="page-header">
        <h1>ایجاد خبر جدید</h1>
    </section>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <ul style="margin:0; padding-right:1.5rem;">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="news-form" style="max-width:760px; margin:2rem auto;">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="form-group">
            <label for="title">عنوان خبر</label>
            <input type="text" id="title" name="title" value="<?= htmlspecialchars($title) ?>" required>
        </div>

        <div class="form-group">
            <label for="content">متن خبر</label>
            <textarea id="content" name="content" rows="12" required><?= htmlspecialchars($content) ?></textarea>
        </div>

        <div class="form-group">
            <label for="image">تصویر (اختیاری - jpg/png/webp)</label>
            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
        </div>

        <div class="form-actions" style="margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary">انتشار خبر</button>
            <a href="news.php" class="btn btn-secondary">انصراف</a>
        </div>
    </form>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>