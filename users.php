<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_admin();

$actor = current_user();
$actorId = (int)($actor['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $messageType = 'error';
    $messageText = 'درخواست نامعتبر است.';
    $action = (string)($_POST['action'] ?? '');
    $query = trim((string)($_POST['q'] ?? ''));
    $keepCreateOpen = false;
    $createFormForFlash = null;

    if ($action === 'create_user') {
        $createFormForFlash = [
            'first_name' => trim((string)($_POST['new_first_name'] ?? '')),
            'last_name' => trim((string)($_POST['new_last_name'] ?? '')),
            'phone' => trim((string)($_POST['new_phone'] ?? '')),
            'national_code' => preg_replace('/\D+/', '', (string)($_POST['new_national_code'] ?? '')) ?? '',
            'role' => (string)($_POST['new_role'] ?? 'user'),
        ];
        $keepCreateOpen = true;
    }

    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $messageText = 'توکن امنیتی معتبر نیست. لطفا دوباره تلاش کنید.';
    } else {
        try {
            if ($action === 'update_role') {
                $targetUserId = (int)($_POST['target_user_id'] ?? 0);
                $newRole = (string)($_POST['role'] ?? '');
                update_user_role_by_admin($targetUserId, $newRole, $actorId);
                $messageType = 'success';
                $messageText = 'نقش کاربر با موفقیت به‌روزرسانی شد.';
            } elseif ($action === 'delete_user') {
                $targetUserId = (int)($_POST['target_user_id'] ?? 0);
                delete_user_by_admin($targetUserId, $actorId);
                $messageType = 'success';
                $messageText = 'کاربر با موفقیت حذف شد.';
            } elseif ($action === 'create_user') {
                $form = is_array($createFormForFlash) ? $createFormForFlash : [
                    'first_name' => '',
                    'last_name' => '',
                    'phone' => '',
                    'national_code' => '',
                    'role' => 'user',
                ];

                $password = (string)($_POST['new_password'] ?? '');
                $passwordRepeat = (string)($_POST['new_password_repeat'] ?? '');
                $errors = [];

                if ($form['first_name'] === '' || mb_strlen($form['first_name']) < 2) {
                    $errors[] = 'نام معتبر وارد کنید.';
                }
                if ($form['last_name'] === '' || mb_strlen($form['last_name']) < 2) {
                    $errors[] = 'نام خانوادگی معتبر وارد کنید.';
                }

                $normalizedPhone = normalize_phone((string)$form['phone']);
                if ($normalizedPhone === null) {
                    $errors[] = 'شماره تلفن معتبر ایران وارد کنید (مثال: 09123456789).';
                }

                if (!valid_national_code((string)$form['national_code'])) {
                    $errors[] = 'کد ملی معتبر نیست.';
                }

                if (!valid_password($password)) {
                    $errors[] = 'رمز عبور باید حداقل 6 کاراکتر و شامل حرف بزرگ، حرف کوچک و عدد باشد.';
                }

                if ($password !== $passwordRepeat) {
                    $errors[] = 'تکرار رمز عبور با رمز عبور یکسان نیست.';
                }

                $role = (string)($form['role'] ?? '');
                if (!in_array($role, ['admin', 'user'], true)) {
                    $errors[] = 'نقش انتخاب شده معتبر نیست.';
                }

                if ($errors !== []) {
                    $messageText = implode(' ', $errors);
                } else {
                    $newUser = create_user_by_admin([
                        'first_name' => (string)$form['first_name'],
                        'last_name' => (string)$form['last_name'],
                        'phone' => (string)$normalizedPhone,
                        'national_code' => (string)$form['national_code'],
                        'password' => $password,
                        'role' => $role,
                    ]);
                    log_user_management_action($actor, 'create', [
                        'id' => (int)$newUser['id'],
                        'first_name' => (string)$form['first_name'],
                        'last_name' => (string)$form['last_name'],
                        'phone' => (string)$normalizedPhone,
                        'national_code' => (string)$form['national_code'],
                        'role' => $role,
                    ]);

                    $messageType = 'success';
                    $messageText = 'کاربر جدید با موفقیت ایجاد شد.';
                    $keepCreateOpen = false;
                    $createFormForFlash = null;
                }
            } else {
                $messageText = 'عملیات انتخاب شده معتبر نیست.';
            }
        } catch (PDOException $exception) {
            if ($action === 'create_user') {
                $message = $exception->getMessage();
                if (str_contains($message, 'national_code')) {
                    $messageText = 'این کد ملی قبلا ثبت شده است.';
                } elseif (str_contains($message, 'phone')) {
                    $messageText = 'این شماره تلفن قبلا ثبت شده است.';
                } else {
                    $messageText = 'خطای دیتابیس رخ داد. لطفا دوباره تلاش کنید.';
                }
            } else {
                $messageText = 'خطای دیتابیس رخ داد. لطفا دوباره تلاش کنید.';
            }
        } catch (RuntimeException $exception) {
            $messageText = $exception->getMessage();
        } catch (Throwable) {
            $messageText = 'خطای سیستمی رخ داد. لطفا دوباره تلاش کنید.';
        }
    }

    start_secure_session();
    $_SESSION['users_flash'] = [
        'type' => $messageType,
        'text' => $messageText,
    ];

    if (is_array($createFormForFlash)) {
        $_SESSION['users_create_form'] = $createFormForFlash;
    } else {
        unset($_SESSION['users_create_form']);
    }

    $redirectParams = [];
    if ($query !== '') {
        $redirectParams['q'] = $query;
    }
    if ($keepCreateOpen) {
        $redirectParams['show_add'] = '1';
    }

    $redirect = 'users.php';
    if ($redirectParams !== []) {
        $redirect .= '?' . http_build_query($redirectParams);
    }

    header('Location: ' . $redirect);
    exit;
}

start_secure_session();
$flash = $_SESSION['users_flash'] ?? null;
$flashCreateForm = $_SESSION['users_create_form'] ?? null;
unset($_SESSION['users_flash'], $_SESSION['users_create_form']);

$search = trim((string)($_GET['q'] ?? ''));
$showAddForm = ((string)($_GET['show_add'] ?? '') === '1');

$createForm = [
    'first_name' => '',
    'last_name' => '',
    'phone' => '',
    'national_code' => '',
    'role' => 'user',
];

if (is_array($flashCreateForm)) {
    $createForm = array_merge($createForm, $flashCreateForm);
    $showAddForm = true;
}

$users = list_users_for_admin($search);

$totalUsers = count(list_users_for_admin(null));
$totalAdmins = count_users_by_role('admin');
$totalNormalUsers = count_users_by_role('user');

$usersListLink = 'users.php';
if ($search !== '') {
    $usersListLink .= '?' . http_build_query(['q' => $search]);
}

$addUserLinkParams = ['show_add' => '1'];
if ($search !== '') {
    $addUserLinkParams['q'] = $search;
}
$addUserLink = 'users.php?' . http_build_query($addUserLinkParams);

$pageTitle = 'مدیریت کاربران | هنرستان دارالفنون';
$activeNav = 'users';
$extraStyles = ['css/users.css?v=' . filemtime(__DIR__ . '/css/users.css')];
$extraScripts = ['js/users-entrance.js?v=' . filemtime(__DIR__ . '/js/users-entrance.js')];

require __DIR__ . '/partials/header.php';
?>
<main class="users-page">
  <section class="users-card">
    <div class="users-head">
      <h1>مدیریت کاربران</h1>
      <p>مشاهده اطلاعات کامل کاربران، کنترل نقش‌ها و افزودن کاربر جدید</p>
    </div>

    <div class="users-stats">
      <div class="stat-item">
        <strong><?= $totalUsers ?></strong>
        <span>کل کاربران</span>
      </div>
      <div class="stat-item">
        <strong><?= $totalAdmins ?></strong>
        <span>مدیرها</span>
      </div>
      <div class="stat-item">
        <strong><?= $totalNormalUsers ?></strong>
        <span>کاربران عادی</span>
      </div>
      <div class="stat-item">
        <strong><?= count($users) ?></strong>
        <span>نتیجه فیلتر</span>
      </div>
    </div>

    <div class="users-toolbar">
      <?php if (is_array($flash) && !empty($flash['text'])): ?>
        <div class="alert <?= ($flash['type'] ?? '') === 'success' ? 'alert-success' : 'alert-error' ?>">
          <?= htmlspecialchars((string)$flash['text'], ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <div class="toolbar-actions">
        <a class="btn-primary btn-add-user" href="<?= htmlspecialchars($addUserLink, ENT_QUOTES, 'UTF-8') ?>">افزودن کاربر</a>
        <?php if ($showAddForm): ?>
          <a class="btn-secondary" href="<?= htmlspecialchars($usersListLink, ENT_QUOTES, 'UTF-8') ?>">بستن فرم افزودن</a>
        <?php endif; ?>
      </div>

      <form method="get" class="users-filters">
      <label>
        جستجو
        <input
          type="text"
          name="q"
          value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
          placeholder="نام، نام خانوادگی، کد ملی یا شماره تلفن"
        />
      </label>
      <div class="filter-actions">
        <button type="submit" class="btn-primary">اعمال فیلتر</button>
        <a class="btn-secondary" href="users.php">حذف فیلتر</a>
      </div>
      </form>
    </div>

    <?php if ($showAddForm): ?>
      <section class="add-user-panel" aria-label="فرم افزودن کاربر">
        <h2>افزودن کاربر جدید</h2>
        <form method="post" class="add-user-form" novalidate>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />
          <input type="hidden" name="action" value="create_user" />
          <input type="hidden" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" />

          <div class="add-user-grid">
            <label>
              نام
              <input type="text" name="new_first_name" required value="<?= htmlspecialchars((string)$createForm['first_name'], ENT_QUOTES, 'UTF-8') ?>" />
            </label>
            <label>
              نام خانوادگی
              <input type="text" name="new_last_name" required value="<?= htmlspecialchars((string)$createForm['last_name'], ENT_QUOTES, 'UTF-8') ?>" />
            </label>
            <label>
              نقش
              <select name="new_role" required>
                <option value="user" <?= ((string)$createForm['role'] === 'user') ? 'selected' : '' ?>>کاربر</option>
                <option value="admin" <?= ((string)$createForm['role'] === 'admin') ? 'selected' : '' ?>>مدیر</option>
              </select>
            </label>

            <label>
              شماره تلفن
              <input type="tel" name="new_phone" inputmode="tel" placeholder="09123456789" required value="<?= htmlspecialchars((string)$createForm['phone'], ENT_QUOTES, 'UTF-8') ?>" />
            </label>
            <label>
              کد ملی
              <input type="text" name="new_national_code" inputmode="numeric" maxlength="10" placeholder="0123456789" required value="<?= htmlspecialchars((string)$createForm['national_code'], ENT_QUOTES, 'UTF-8') ?>" />
            </label>
            <label>
              رمز عبور
              <input type="password" name="new_password" required />
            </label>

            <label class="full">
              تکرار رمز عبور
              <input type="password" name="new_password_repeat" required />
            </label>
          </div>

          <div class="add-user-actions">
            <button type="submit" class="btn-primary">ثبت کاربر</button>
            <a class="btn-secondary" href="<?= htmlspecialchars($usersListLink, ENT_QUOTES, 'UTF-8') ?>">انصراف</a>
          </div>
        </form>
      </section>
    <?php endif; ?>

    <div class="users-table-wrap">
      <table class="users-table">
        <thead>
          <tr>
            <th>#</th>
            <th>تصویر</th>
            <th>نام</th>
            <th>نام خانوادگی</th>
            <th>کد ملی</th>
            <th>شماره تلفن</th>
            <th>مسیر تصویر</th>
            <th>نقش</th>
            <th>تاریخ عضویت</th>
            <th>کنترل</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($users === []): ?>
            <tr>
              <td class="empty-cell" colspan="10">کاربری یافت نشد.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($users as $row): ?>
              <?php
                $uid = (int)$row['id'];
                $isCurrentUser = $uid === $actorId;
                $role = (string)$row['role'];
                $profilePath = sanitize_profile_image_path((string)($row['profile_image_path'] ?? ''));
                $profileUrl = user_profile_image_url($row);
                $fullName = trim((string)$row['first_name'] . ' ' . (string)$row['last_name']);
              ?>
              <tr>
                <td class="cell-id" data-label="شناسه"><?= $uid ?></td>
                <td class="cell-avatar" data-label="تصویر">
                  <img
                    src="<?= htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') ?>"
                    alt="<?= htmlspecialchars($fullName !== '' ? $fullName : 'کاربر', ENT_QUOTES, 'UTF-8') ?>"
                  />
                </td>
                <td class="cell-first-name" data-label="نام"><?= htmlspecialchars((string)$row['first_name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="cell-last-name" data-label="نام خانوادگی"><?= htmlspecialchars((string)$row['last_name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="cell-national" data-label="کد ملی"><?= htmlspecialchars((string)$row['national_code'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="cell-phone" data-label="شماره تلفن"><?= htmlspecialchars((string)$row['phone'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="cell-image-path" data-label="مسیر تصویر"><?= htmlspecialchars($profilePath ?? 'ندارد', ENT_QUOTES, 'UTF-8') ?></td>
                <td class="cell-role" data-label="نقش">
                  <span class="role-badge role-<?= $role === 'admin' ? 'admin' : 'user' ?>">
                    <?= $role === 'admin' ? 'مدیر' : 'کاربر' ?>
                  </span>
                </td>
                <td class="cell-date" data-label="تاریخ عضویت"><?= htmlspecialchars((string)$row['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="cell-actions" data-label="کنترل">
                  <?php if ($isCurrentUser): ?>
                    <span class="self-label">حساب فعلی</span>
                  <?php else: ?>
                    <div class="actions-stack">
                      <form method="post" class="inline-form role-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />
                        <input type="hidden" name="action" value="update_role" />
                        <input type="hidden" name="target_user_id" value="<?= $uid ?>" />
                        <input type="hidden" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" />
                        <select name="role" class="role-select">
                          <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>کاربر</option>
                          <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>مدیر</option>
                        </select>
                        <button type="submit" class="btn-small">ذخیره نقش</button>
                      </form>

                      <?php if ($role !== 'admin'): ?>
                        <form method="post" class="inline-form delete-form" onsubmit="return confirm('آیا از حذف این کاربر مطمئن هستید؟');">
                          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />
                          <input type="hidden" name="action" value="delete_user" />
                          <input type="hidden" name="target_user_id" value="<?= $uid ?>" />
                          <input type="hidden" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" />
                          <button type="submit" class="btn-danger">حذف کاربر</button>
                        </form>
                      <?php else: ?>
                        <span class="admin-lock-label">حذف مدیر مجاز نیست</span>
                      <?php endif; ?>
                    </div>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
