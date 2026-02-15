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
    WHERE n.slug = ? AND n.is_published = 1
    LIMIT 1
");
$stmt->execute([$slug]);
$news = $stmt->fetch();

if (!$news) {
    http_response_code(404);
    echo "<h1>خبر پیدا نشد یا منتشر نشده</h1>";
    exit;
}

$pageTitle = htmlspecialchars($news['title']);
require __DIR__ . '/partials/header.php';
?>

<main class="container" style="max-width: 900px; margin: 2rem auto; line-height: 1.8;">
    <a href="news.php" style="display: inline-block; margin-bottom: 1.5rem; color: #2563eb;">← بازگشت به لیست اخبار</a>
    
    <article>
        <h1 style="margin-bottom: 0.8rem;"><?= htmlspecialchars($news['title']) ?></h1>
        
        <div style="color: #64748b; margin-bottom: 2rem; font-size: 0.95rem;">
            <?= date('Y/m/d – H:i', strtotime($news['published_at'])) ?> • 
            <?= htmlspecialchars($news['first_name'] . ' ' . $news['last_name']) ?>
        </div>

        <?php if ($news['image_path']): ?>
            <div style="margin-bottom: 2rem; text-align: center;">
                <img src="<?= htmlspecialchars($news['image_path']) ?>" 
                     alt="<?= htmlspecialchars($news['title']) ?>"
                     style="max-width: 100%; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,0.1);">
            </div>
        <?php endif; ?>

        <div class="news-content" style="font-size: 1.05rem;">
            <?= nl2br(htmlspecialchars($news['content'])) ?>
        </div>
    </article>
<?php if (current_user() && current_user()['role'] === 'admin'): ?>
    <div style="margin-top: 3rem; padding-top: 1.5rem; border-top: 1px solid #e2e8f0; display: flex; gap: 1rem; justify-content: flex-end;">
        <a href="news-edit.php?slug=<?= urlencode($news['slug']) ?>" 
           class="btn btn-secondary" 
           style="background: #3b82f6; color: white;">
            ویرایش خبر
        </a>
        
        <form method="POST" action="news-delete.php" onsubmit="return confirm('واقعاً می‌خوای این خبر رو حذف کنی؟');">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="slug" value="<?= htmlspecialchars($news['slug']) ?>">
            <button type="submit" class="btn btn-danger" 
                    style="background: #ef4444; color: white; border: none; padding: 0.6rem 1.2rem; border-radius: 6px; cursor: pointer;">
                حذف خبر
            </button>
        </form>
    </div>
<?php endif; ?>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>