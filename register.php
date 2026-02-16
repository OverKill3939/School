<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
start_secure_session();

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$errors = [];
$form = [
    'first_name' => '',
    'last_name' => '',
    'phone' => '',
    'national_code' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors[] = 'درخواست نامعتبر است. لطفا دوباره تلاش کنید.';
    } else {
        $form['first_name'] = trim((string)($_POST['first_name'] ?? ''));
        $form['last_name'] = trim((string)($_POST['last_name'] ?? ''));
        $form['phone'] = trim((string)($_POST['phone'] ?? ''));
        $form['national_code'] = preg_replace('/\D+/', '', (string)($_POST['national_code'] ?? '')) ?? '';

        $password = (string)($_POST['password'] ?? '');
        $passwordRepeat = (string)($_POST['password_repeat'] ?? '');

        if ($form['first_name'] === '' || mb_strlen($form['first_name']) < 2) {
            $errors[] = 'نام معتبر وارد کنید.';
        }

        if ($form['last_name'] === '' || mb_strlen($form['last_name']) < 2) {
            $errors[] = 'نام خانوادگی معتبر وارد کنید.';
        }

        $normalizedPhone = normalize_phone($form['phone']);
        if ($normalizedPhone === null) {
            $errors[] = 'شماره تلفن معتبر ایران وارد کنید (مثال: 09123456789).';
        }

        if (!valid_national_code($form['national_code'])) {
            $errors[] = 'کد ملی معتبر نیست.';
        }

        if (!valid_password($password)) {
            $errors[] = 'رمز عبور باید حداقل ۶ کاراکتر و شامل حرف بزرگ، حرف کوچک و عدد باشد.';
        }

        if ($password !== $passwordRepeat) {
            $errors[] = 'تکرار رمز عبور با رمز عبور یکسان نیست.';
        }

        if (empty($errors)) {
            try {
                $newUser = create_user([
                    'first_name' => $form['first_name'],
                    'last_name' => $form['last_name'],
                    'phone' => $normalizedPhone,
                    'national_code' => $form['national_code'],
                    'password' => $password,
                ]);

                login_user($newUser);
                mark_notification_permission_prompt();
                log_auth_event('register', true, (int)$newUser['id'], $form['national_code']);
                header('Location: index.php');
                exit;
            } catch (PDOException $exception) {
                $message = $exception->getMessage();
                if (str_contains($message, 'national_code')) {
                    $errors[] = 'این کد ملی قبلا ثبت شده است.';
                } elseif (str_contains($message, 'phone')) {
                    $errors[] = 'این شماره تلفن قبلا ثبت شده است.';
                } else {
                    $errors[] = 'خطای دیتابیس رخ داد. اتصال دیتابیس را بررسی کنید.';
                }
                log_auth_event('register', false, null, $form['national_code']);
            }
        }
    }
}

$pageTitle = 'ثبت نام | هنرستان دارالفنون';
$extraStyles = ['css/auth.css'];

require __DIR__ . '/partials/header.php';
?>
<main class="auth-main">
  <section class="auth-shell">
    <aside class="auth-visual" aria-label="معرفی ثبت نام">
      <span class="auth-kicker">ساخت حساب جدید</span>
      <h2 class="auth-hero-title">ثبت نام در سامانه هنرستان دارالفنون</h2>
      <p class="auth-hero-text">اطلاعات خود را دقیق وارد کنید. اولین حساب ثبت‌شده مدیر خواهد بود و حساب‌های بعدی به صورت کاربر عادی ساخته می‌شوند.</p>
      <ul class="auth-points">
        <li>ثبت نام سریع با شماره تلفن و کد ملی</li>
        <li>اعتبارسنجی کامل اطلاعات قبل از ذخیره</li>
        <li>ورود مستقیم پس از ساخت حساب</li>
      </ul>
    </aside>

    <section class="auth-card" aria-label="فرم ثبت نام">
      <div class="auth-card-head">
        <h1>ثبت نام</h1>
        <p class="auth-subtitle">فرم زیر را کامل کنید تا حساب شما ایجاد شود.</p>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="auth-error"><?= htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <form method="post" class="auth-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />

        <div class="auth-grid-two">
          <label for="first_name">نام</label>
          <label for="last_name">نام خانوادگی</label>
          <input id="first_name" name="first_name" required value="<?= htmlspecialchars($form['first_name'], ENT_QUOTES, 'UTF-8') ?>" />
          <input id="last_name" name="last_name" required value="<?= htmlspecialchars($form['last_name'], ENT_QUOTES, 'UTF-8') ?>" />

          <label for="phone">شماره تلفن</label>
          <label for="national_code">کد ملی</label>
          <input id="phone" name="phone" inputmode="tel" placeholder="09123456789" required value="<?= htmlspecialchars($form['phone'], ENT_QUOTES, 'UTF-8') ?>" />
          <input id="national_code" name="national_code" inputmode="numeric" maxlength="10" placeholder="0123456789" required value="<?= htmlspecialchars($form['national_code'], ENT_QUOTES, 'UTF-8') ?>" />

          <label for="password" class="full">رمز عبور</label>
          <input id="password" class="full" name="password" type="password" required />

          <label for="password_repeat" class="full">تکرار رمز عبور</label>
          <input id="password_repeat" class="full" name="password_repeat" type="password" required />
        </div>

        <button type="submit" class="cta auth-submit">ایجاد حساب</button>
      </form>

      <p class="auth-switch">قبلا حساب ساخته‌اید؟ <a href="login.php">ورود</a></p>
    </section>
  </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
