<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
start_secure_session();

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
$nationalCode = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $error = 'درخواست نامعتبر است. لطفا دوباره تلاش کنید.';
    } else {
        $nationalCode = preg_replace('/\D+/', '', (string)($_POST['national_code'] ?? '')) ?? '';
        $password = (string)($_POST['password'] ?? '');

        if (!preg_match('/^\d{10}$/', $nationalCode)) {
            $error = 'کد ملی باید ۱۰ رقم باشد.';
            log_auth_event('login', false, null, $nationalCode);
        } else {
            $user = find_user_by_national_code($nationalCode);
            if (!$user || !password_verify($password, $user['password_hash'])) {
                $error = 'کد ملی یا رمز عبور اشتباه است.';
                log_auth_event('login', false, $user['id'] ?? null, $nationalCode);
            } else {
                login_user($user);
                log_auth_event('login', true, (int)$user['id'], $nationalCode);
                header('Location: index.php');
                exit;
            }
        }
    }
}

$pageTitle = 'ورود | هنرستان دارالفنون';
$extraStyles = ['css/auth.css'];

require __DIR__ . '/partials/header.php';
?>
<main class="auth-main">
  <section class="auth-shell">
    <aside class="auth-visual" aria-label="معرفی سامانه">
      <span class="auth-kicker">پنل آموزشی هنرستان دارالفنون</span>
      <h2 class="auth-hero-title">ورود امن به حساب کاربری مدرسه</h2>
      <p class="auth-hero-text">برای مشاهده تقویم آموزشی، اخبار و اعلان‌های هنرستان با کد ملی و رمز عبور وارد شوید.</p>
      <ul class="auth-points">
        <li>دسترسی سریع به تقویم و رویدادهای رسمی</li>
        <li>نمایش اعلان‌های آموزشی و اطلاعیه‌ها</li>
        <li>پنل مدیریت ویژه ادمین‌ها</li>
      </ul>
    </aside>

    <section class="auth-card" aria-label="فرم ورود">
      <div class="auth-card-head">
        <h1>ورود به سامانه</h1>
        <p class="auth-subtitle">کد ملی و رمز عبور خود را وارد کنید.</p>
      </div>

      <?php if ($error !== ''): ?>
        <div class="auth-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <form method="post" class="auth-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />

        <label for="national_code">کد ملی</label>
        <input id="national_code" name="national_code" inputmode="numeric" maxlength="10" placeholder="0123456789" required value="<?= htmlspecialchars($nationalCode, ENT_QUOTES, 'UTF-8') ?>" />

        <label for="password">رمز عبور</label>
        <input id="password" name="password" type="password" required />

        <button type="submit" class="cta auth-submit">ورود</button>
      </form>

      <p class="auth-switch">حساب ندارید؟ <a href="register.php">ثبت نام</a></p>
    </section>
  </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
