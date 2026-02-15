<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/helpers.php';
start_secure_session();

$pageTitle = $pageTitle ?? 'هنرستان دارالفنون';
$activeNav = $activeNav ?? '';
$user = current_user();
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="css/styles.css" />
  <?php if (!empty($extraStyles)): ?>
    <?php foreach ($extraStyles as $stylePath): ?>
      <link rel="stylesheet" href="<?= htmlspecialchars($stylePath, ENT_QUOTES, 'UTF-8') ?>" />
    <?php endforeach; ?>
  <?php endif; ?>
</head>
<body>
  <header class="site-header">
    <div class="header-main">
      <div class="brand">
        <div class="mark" aria-label="نشان مدرسه">
          <img src="img/daralfonon.jpg" alt="لوگوی هنرستان دارالفنون" />
        </div>
        <div class="wordmark">
          <span class="name">هنرستان دارالفنون</span>
          <span class="tag">شبکه و نرم افزار • برق • الکترونیک</span>
        </div>
      </div>

      <nav class="nav">
        <a href="index.php" <?= $activeNav === 'home' ? 'style="color: var(--accent);"' : '' ?>>خانه</a>

        <!-- برگشت به حالت قبلی: دانش آموزان -->
        <details class="students-menu <?= in_array($activeNav, ['schedule'], true) ? 'is-active' : '' ?>">
          <summary>دانش آموزان</summary>
          <div class="students-menu-list">
            <a href="schedule.php" class="schedule <?= $activeNav === 'schedule' ? 'active' : '' ?>">برنامه هفتگی</a>
          </div>
        </details>

        <a href="news.php">اخبار</a>
        <a href="calendar.php" <?= $activeNav === 'calendar' ? 'style="color: var(--accent);"' : '' ?>>تقویم</a>
        <a href="about.php">درباره</a>

        <?php if (($user['role'] ?? '') === 'admin'): ?>
        <details class="admin-menu <?= in_array($activeNav, ['logs', 'users', 'election'], true) ? 'is-active' : '' ?>">
          <summary>مدیریت</summary>
          <div class="admin-menu-list">
            <a href="logs.php" class="logs <?= $activeNav === 'logs' ? 'active' : '' ?>">لاگ</a>
            <a href="users.php" class="users <?= $activeNav === 'users' ? 'active' : '' ?>">کاربران</a>
            <!-- گزینه جدید: انتخابات فقط برای ادمین -->
            <a href="election.php" class="election <?= $activeNav === 'election' ? 'active' : '' ?>">انتخابات</a>
          </div>
        </details>
        <?php endif; ?>
      </nav>

      <div class="header-actions">
        <?php if ($user): ?>
          <span class="user-chip">
            <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name'], ENT_QUOTES, 'UTF-8') ?>
            (<?= htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8') ?>)
          </span>
          <details class="profile-menu">
            <summary class="profile-trigger" aria-label="منوی پروفایل" title="منوی پروفایل">
              <img src="img/waguri111.jpg" alt="" aria-hidden="true" />
            </summary>
            <div class="profile-menu-list">
              <a href="#" class="profile-item settings">تنظیمات</a>
              <a href="logout.php" class="profile-item logout danger">خروج</a>
            </div>
          </details>
        <?php else: ?>
          <a href="login.php" class="cta cta-secondary">ورود</a>
          <a href="register.php" class="cta">ثبت نام</a>
        <?php endif; ?>
      </div>
    </div>
  </header>