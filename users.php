<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_admin();

$actor = current_user();
$actorId = (int)($actor['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $messageType = 'error';
    $messageText = 'درخواست نامعتبر است.';

    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $messageText = 'توکن امنیتی معتبر نیست. لطفا دوباره تلاش کنید.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);

        try {
            if ($action === 'update_role') {
                $newRole = (string)($_POST['role'] ?? '');
                update_user_role_by_admin($targetUserId, $newRole, $actorId);
                $messageType = 'success';
                $messageText = 'نقش کاربر با موفقیت به‌روزرسانی شد.';
            } elseif ($action === 'delete_user') {
                delete_user_by_admin($targetUserId, $actorId);
                $messageType = 'success';
                $messageText = 'کاربر با موفقیت حذف شد.';
            } else {
                $messageText = 'عملیات انتخاب شده معتبر نیست.';
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

    $query = trim((string)($_POST['q'] ?? ''));
    $redirect = 'users.php';
    if ($query !== '') {
        $redirect .= '?' . http_build_query(['q' => $query]);
    }

    header('Location: ' . $redirect);
    exit;
}

start_secure_session();
$flash = $_SESSION['users_flash'] ?? null;
unset($_SESSION['users_flash']);

$search = trim((string)($_GET['q'] ?? ''));
$users = list_users_for_admin($search);

$totalUsers = count(list_users_for_admin(null));
$totalAdmins = count_users_by_role('admin');
$totalNormalUsers = count_users_by_role('user');

$pageTitle = 'مدیریت کاربران | هنرستان دارالفنون';
$activeNav = 'users';
$extraStyles = ['css/users.css?v=' . filemtime(__DIR__ . '/css/users.css')];

require __DIR__ . '/partials/header.php';
?>
<main class="users-page">
  <section class="users-card">
    <div class="users-head">
      <h1>مدیریت کاربران</h1>
      <p>مشاهده اطلاعات کامل کاربران و کنترل نقش یا حذف حساب‌ها</p>
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

    <div class="users-table-wrap">
      <table class="users-table">
        <thead>
          <tr>
            <th>#</th>
            <th>نام و نام خانوادگی</th>
            <th>کد ملی</th>
            <th>شماره تلفن</th>
            <th>نقش</th>
            <th>تاریخ عضویت</th>
            <th>کنترل</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($users === []): ?>
            <tr>
              <td class="empty-cell" colspan="7">کاربری یافت نشد.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($users as $row): ?>
              <?php
                $uid = (int)$row['id'];
                $isCurrentUser = $uid === $actorId;
                $role = (string)$row['role'];
              ?>
              <tr>
                <td class="cell-id" data-label="شناسه"><?= $uid ?></td>
                <td class="cell-name" data-label="نام و نام خانوادگی"><?= htmlspecialchars((string)$row['first_name'] . ' ' . (string)$row['last_name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="cell-national" data-label="کد ملی"><?= htmlspecialchars((string)$row['national_code'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="cell-phone" data-label="شماره تلفن"><?= htmlspecialchars((string)$row['phone'], ENT_QUOTES, 'UTF-8') ?></td>
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

                      <form method="post" class="inline-form delete-form" onsubmit="return confirm('آیا از حذف این کاربر مطمئن هستید؟');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />
                        <input type="hidden" name="action" value="delete_user" />
                        <input type="hidden" name="target_user_id" value="<?= $uid ?>" />
                        <input type="hidden" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" />
                        <button type="submit" class="btn-danger">حذف کاربر</button>
                      </form>
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