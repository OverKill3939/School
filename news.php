<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_login();

$pageTitle = 'اخبار | هنرستان دارالفنون';
$activeNav = 'news';

require __DIR__ . '/partials/header.php';

$pdo = get_db();

$stmt = $pdo->prepare("
    SELECT n.id, n.title, n.slug, n.image_path, n.published_at, 
           u.first_name, u.last_name
    FROM news n
    JOIN users u ON n.author_id = u.id
    WHERE n.is_published = 1
    ORDER BY n.published_at DESC
    LIMIT 20
");
$stmt->execute();
$newsItems = $stmt->fetchAll() ?: [];
?>

<main class="container" style="margin: 2rem auto; max-width: 1200px;">
    <h1 style="text-align: center; margin-bottom: 1.5rem;">اخبار و اطلاعیه‌ها</h1>

    <?php if (current_user() && current_user()['role'] === 'admin'): ?>
        <div style="text-align: center; margin-bottom: 2rem;">
            <a href="news-create.php" class="btn btn-primary">ایجاد خبر جدید</a>
        </div>
    <?php endif; ?>

    <?php if (empty($newsItems)): ?>
        <div style="text-align: center; padding: 4rem 1rem; color: #64748b; font-size: 1.1rem;">
            هنوز خبری منتشر نشده است.
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.6rem;">
            <?php foreach ($newsItems as $item): ?>
                <div class="news-card" style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08); transition: transform 0.2s;">
                    <?php if (!empty($item['image_path'])): ?>
                        <img src="<?= htmlspecialchars($item['image_path']) ?>" 
                             alt="<?= htmlspecialchars($item['title']) ?>"
                             style="width: 100%; height: 180px; object-fit: cover;">
                    <?php endif; ?>
                    <div style="padding: 1.25rem;">
                        <h3 style="margin: 0 0 0.6rem; font-size: 1.25rem;">
                            <?= htmlspecialchars($item['title']) ?>
                        </h3>
                        <div style="color: #64748b; font-size: 0.9rem; margin-bottom: 0.8rem;">
                            <?= date('Y/m/d', strtotime($item['published_at'])) ?> • 
                            <?= htmlspecialchars($item['first_name'] . ' ' . $item['last_name']) ?>
                        </div>
                        <a href="news-detail.php?slug=<?= urlencode($item['slug']) ?>" ...> 
                           style="color: #2563eb; font-weight: 500; text-decoration: none;">
                            مشاهده خبر →
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>