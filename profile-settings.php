<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_login();

$user = current_user();
if ($user === null) {
    header('Location: login.php');
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors[] = 'درخواست نامعتبر است. لطفا دوباره تلاش کنید.';
    } else {
        $result = save_user_profile_image((int)$user['id'], $_FILES['profile_image'] ?? []);
        if (($result['ok'] ?? false) === true) {
            $success = 'عکس پروفایل با موفقیت ذخیره شد.';
            $user = current_user() ?? $user;
        } else {
            $errors[] = (string)($result['message'] ?? 'ذخیره عکس پروفایل انجام نشد.');
        }
    }
}

$pageTitle = 'تنظیمات پروفایل | هنرستان دارالفنون';
$activeNav = 'profile-settings';
$extraStyles = ['css/profile-settings.css'];

require __DIR__ . '/partials/header.php';
?>
<main class="profile-settings-main">
  <section class="profile-settings-card" aria-label="تنظیمات عکس پروفایل">
    <h1>تنظیمات عکس پروفایل</h1>
    <p class="profile-settings-subtitle">
      بعد از ثبت نام می‌توانید عکس پروفایل خودتان را از همین بخش آپلود کنید. تصویر جدید در دیتابیس ثبت می‌شود.
    </p>

    <?php if (!empty($errors)): ?>
      <div class="profile-settings-alert error"><?= htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
      <div class="profile-settings-alert success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <div class="profile-settings-preview">
      <img src="<?= htmlspecialchars(user_profile_image_url($user), ENT_QUOTES, 'UTF-8') ?>" alt="عکس پروفایل کاربر" />
    </div>

    <form method="post" enctype="multipart/form-data" class="profile-settings-form">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />

      <label for="profile_image">انتخاب عکس جدید</label>
      <input
        id="profile_image"
        name="profile_image"
        type="file"
        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
        required
      />

      <p class="profile-settings-help">فرمت‌های مجاز: JPG، PNG، WEBP - حداکثر حجم فایل: 3 مگابایت</p>
      <button type="submit" class="cta">ذخیره عکس پروفایل</button>
    </form>
  </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
