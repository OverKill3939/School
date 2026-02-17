<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_admin();

function normalize_date_input(?string $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
}

function format_payload(?string $payload): string
{
    if ($payload === null || trim($payload) === '') {
        return '-';
    }

    $decoded = json_decode($payload, true);
    if (!is_array($decoded)) {
        return $payload;
    }

    $pretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $pretty !== false ? $pretty : $payload;
}

$allowedActions = ['create', 'update', 'delete'];
$action = (string)($_GET['action'] ?? '');
if (!in_array($action, $allowedActions, true)) {
    $action = '';
}

$actorId = isset($_GET['actor_id']) ? (int)$_GET['actor_id'] : 0;
if ($actorId < 1) {
    $actorId = 0;
}

$fromDate = normalize_date_input(isset($_GET['from']) ? (string)$_GET['from'] : null);
$toDateInput = normalize_date_input(isset($_GET['to']) ? (string)$_GET['to'] : null);

$toDateExclusive = null;
if ($toDateInput !== null) {
    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $toDateInput);
    if ($parsed instanceof DateTimeImmutable) {
        $toDateExclusive = $parsed->modify('+1 day')->format('Y-m-d');
    }
}

$filters = [
    'action' => $action !== '' ? $action : null,
    'actor_id' => $actorId > 0 ? $actorId : null,
    'from_date' => $fromDate,
    'to_date' => $toDateExclusive,
];

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

$perPage = 10;
$total = count_event_logs($filters);
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;
$logs = list_event_logs($filters, $perPage, $offset);
$actors = list_users_for_log_filter();

// ───── Auth logs filters
$authEvent = (string)($_GET['auth_event'] ?? '');
if (!in_array($authEvent, ['login', 'register'], true)) {
    $authEvent = '';
}

$authSuccess = $_GET['auth_success'] ?? '';
if ($authSuccess === '1') {
    $authSuccess = true;
} elseif ($authSuccess === '0') {
    $authSuccess = false;
} else {
    $authSuccess = null;
}

$authUserId = isset($_GET['auth_user_id']) ? (int)$_GET['auth_user_id'] : 0;
if ($authUserId < 1) {
    $authUserId = 0;
}

$authFromDate = normalize_date_input(isset($_GET['auth_from']) ? (string)$_GET['auth_from'] : null);
$authToDateInput = normalize_date_input(isset($_GET['auth_to']) ? (string)$_GET['auth_to'] : null);

$authToDateExclusive = null;
if ($authToDateInput !== null) {
    $parsedAuth = DateTimeImmutable::createFromFormat('Y-m-d', $authToDateInput);
    if ($parsedAuth instanceof DateTimeImmutable) {
        $authToDateExclusive = $parsedAuth->modify('+1 day')->format('Y-m-d');
    }
}

$authFilters = [
    'event' => $authEvent !== '' ? $authEvent : null,
    'success' => $authSuccess,
    'user_id' => $authUserId > 0 ? $authUserId : null,
    'from_date' => $authFromDate,
    'to_date' => $authToDateExclusive,
];

$authPage = isset($_GET['auth_page']) ? (int)$_GET['auth_page'] : 1;
if ($authPage < 1) {
    $authPage = 1;
}

$authPerPage = 10;
$authTotal = count_auth_logs($authFilters);
$authTotalPages = max(1, (int)ceil($authTotal / $authPerPage));
if ($authPage > $authTotalPages) {
    $authPage = $authTotalPages;
}

$authOffset = ($authPage - 1) * $authPerPage;
$authLogs = list_auth_logs($authFilters, $authPerPage, $authOffset);

$authQueryBase = [];
if ($authEvent !== '') {
    $authQueryBase['auth_event'] = $authEvent;
}
if ($authSuccess !== null) {
    $authQueryBase['auth_success'] = $authSuccess ? '1' : '0';
}
if ($authUserId > 0) {
    $authQueryBase['auth_user_id'] = (string)$authUserId;
}
if ($authFromDate !== null) {
    $authQueryBase['auth_from'] = $authFromDate;
}
if ($authToDateInput !== null) {
    $authQueryBase['auth_to'] = $authToDateInput;
}

$buildAuthPageUrl = static function (int $targetPage) use ($authQueryBase): string {
    $params = $authQueryBase;
    $params['auth_page'] = (string)$targetPage;
    return 'logs.php?' . http_build_query($params);
};

$queryBase = [];
if ($action !== '') {
    $queryBase['action'] = $action;
}
if ($actorId > 0) {
    $queryBase['actor_id'] = (string)$actorId;
}
if ($fromDate !== null) {
    $queryBase['from'] = $fromDate;
}
if ($toDateInput !== null) {
    $queryBase['to'] = $toDateInput;
}

$buildPageUrl = static function (int $targetPage) use ($queryBase): string {
    $params = $queryBase;
    $params['page'] = (string)$targetPage;
    return 'logs.php?' . http_build_query($params);
};

$actionLabels = [
    'create' => 'ایجاد',
    'update' => 'ویرایش',
    'delete' => 'حذف',
];

$pageTitle = 'لاگ فعالیت‌ها | هنرستان دارالفنون';
$activeNav = 'logs';
$extraStyles = ['css/logs.css?v=' . filemtime(__DIR__ . '/css/logs.css')];
$extraScripts = ['js/logs-entrance.js?v=' . filemtime(__DIR__ . '/js/logs-entrance.js')];

require __DIR__ . '/partials/header.php';
?>
<main class="logs-page">
  <section class="logs-card">
    <div class="logs-head">
      <h1>لاگ فعالیت‌های تقویم</h1>
      <p>تمام عملیات ایجاد، ویرایش و حذف رویدادها اینجا ثبت می‌شود.</p>
    </div>

    <form method="get" class="logs-filters">
      <label>
        عملیات
        <select name="action">
          <option value="">همه</option>
          <option value="create" <?= $action === 'create' ? 'selected' : '' ?>>ایجاد</option>
          <option value="update" <?= $action === 'update' ? 'selected' : '' ?>>ویرایش</option>
          <option value="delete" <?= $action === 'delete' ? 'selected' : '' ?>>حذف</option>
        </select>
      </label>

      <label>
        کاربر
        <select name="actor_id">
          <option value="">همه</option>
          <?php foreach ($actors as $actor): ?>
            <?php $aid = (int)$actor['id']; ?>
            <option value="<?= $aid ?>" <?= $actorId === $aid ? 'selected' : '' ?>>
              <?= htmlspecialchars(($actor['first_name'] . ' ' . $actor['last_name'] . ' (' . $actor['role'] . ')'), ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>
        از تاریخ
        <input type="date" name="from" value="<?= htmlspecialchars((string)($fromDate ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
      </label>

      <label>
        تا تاریخ
        <input type="date" name="to" value="<?= htmlspecialchars((string)($toDateInput ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
      </label>

      <div class="filter-actions">
        <button type="submit" class="btn-primary">اعمال</button>
        <a href="logs.php" class="btn-secondary">بازنشانی</a>
      </div>
    </form>

    <div class="logs-meta">
      <span>نتایج: <?= $total ?></span>
      <span>صفحه <?= $page ?> از <?= $totalPages ?></span>
    </div>

    <div class="logs-table-wrap">
      <table class="logs-table">
        <thead>
          <tr>
            <th>#</th>
            <th>زمان ثبت</th>
            <th>کاربر</th>
            <th>عملیات</th>
            <th>شناسه رویداد</th>
            <th>قبل</th>
            <th>بعد</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($logs === []): ?>
            <tr>
              <td colspan="7" class="empty-cell">لاگی پیدا نشد.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($logs as $row): ?>
              <tr>
                <td class="cell-id" data-label="ردیف"><?= (int)$row['id'] ?></td>
                <td class="cell-time" data-label="زمان ثبت"><?= htmlspecialchars((string)$row['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="cell-user" data-label="کاربر"><?= htmlspecialchars(($row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['role'] . ')'), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="cell-action" data-label="عملیات">
                  <span class="action-badge action-<?= htmlspecialchars((string)$row['action'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($actionLabels[$row['action']] ?? (string)$row['action'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                </td>
                <td class="cell-event" data-label="شناسه رویداد"><?= htmlspecialchars((string)($row['entity_id'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="cell-before" data-label="قبل">
                  <details class="payload-details">
                    <summary>نمایش</summary>
                    <pre><?= htmlspecialchars(format_payload($row['before_data'] ?? null), ENT_QUOTES, 'UTF-8') ?></pre>
                  </details>
                </td>
                <td class="cell-after" data-label="بعد">
                  <details class="payload-details">
                    <summary>نمایش</summary>
                    <pre><?= htmlspecialchars(format_payload($row['after_data'] ?? null), ENT_QUOTES, 'UTF-8') ?></pre>
                  </details>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="logs-pagination">
      <?php if ($page > 1): ?>
        <a class="page-btn" href="<?= htmlspecialchars($buildPageUrl($page - 1), ENT_QUOTES, 'UTF-8') ?>">قبلی</a>
      <?php endif; ?>
      <?php if ($page < $totalPages): ?>
        <a class="page-btn" href="<?= htmlspecialchars($buildPageUrl($page + 1), ENT_QUOTES, 'UTF-8') ?>">بعدی</a>
      <?php endif; ?>
    </div>
  </section>

  <section class="logs-card auth-logs-card">
    <div class="logs-head">
      <h2>لاگ ورود و ثبت‌نام</h2>
      <p>تلاش‌های ورود و ثبت‌نام (موفق و ناموفق) برای پایش امنیتی.</p>
    </div>

    <form method="get" class="logs-filters">
      <label>
        رویداد
        <select name="auth_event">
          <option value="">همه</option>
          <option value="login" <?= $authEvent === 'login' ? 'selected' : '' ?>>ورود</option>
          <option value="register" <?= $authEvent === 'register' ? 'selected' : '' ?>>ثبت‌نام</option>
        </select>
      </label>

      <label>
        نتیجه
        <select name="auth_success">
          <option value="">همه</option>
          <option value="1" <?= $authSuccess === true ? 'selected' : '' ?>>موفق</option>
          <option value="0" <?= $authSuccess === false ? 'selected' : '' ?>>ناموفق</option>
        </select>
      </label>

      <label>
        کاربر
        <select name="auth_user_id">
          <option value="">همه</option>
          <?php foreach ($actors as $actor): ?>
            <?php $aid = (int)$actor['id']; ?>
            <option value="<?= $aid ?>" <?= $authUserId === $aid ? 'selected' : '' ?>>
              <?= htmlspecialchars(($actor['first_name'] . ' ' . $actor['last_name'] . ' (' . $actor['role'] . ')'), ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>
        از تاریخ
        <input type="date" name="auth_from" value="<?= htmlspecialchars((string)($authFromDate ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
      </label>

      <label>
        تا تاریخ
        <input type="date" name="auth_to" value="<?= htmlspecialchars((string)($authToDateInput ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
      </label>

      <div class="filter-actions">
        <button type="submit" class="btn-primary">اعمال</button>
        <a href="logs.php#auth-logs" class="btn-secondary">بازنشانی</a>
      </div>
    </form>

    <div class="logs-meta">
      <span>نتایج: <?= $authTotal ?></span>
      <span>صفحه <?= $authPage ?> از <?= $authTotalPages ?></span>
    </div>

    <div class="logs-table-wrap" id="auth-logs">
      <table class="logs-table">
        <thead>
          <tr>
            <th>#</th>
            <th>زمان ثبت</th>
            <th>رویداد</th>
            <th>نتیجه</th>
            <th>کاربر</th>
            <th>کد ملی</th>
            <th>IP</th>
            <th>User Agent</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($authLogs === []): ?>
            <tr>
              <td colspan="8" class="empty-cell">موردی یافت نشد.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($authLogs as $row): ?>
              <tr>
                <td class="cell-id" data-label="ردیف"><?= (int)$row['id'] ?></td>
                <td class="cell-time" data-label="زمان ثبت"><?= htmlspecialchars((string)$row['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                <td data-label="رویداد">
                  <span class="action-badge action-<?= htmlspecialchars((string)$row['event'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= $row['event'] === 'login' ? 'ورود' : 'ثبت‌نام' ?>
                  </span>
                </td>
                <td data-label="نتیجه">
                  <span class="action-badge <?= $row['success'] ? 'action-success' : 'action-fail' ?>">
                    <?= $row['success'] ? 'موفق' : 'ناموفق' ?>
                  </span>
                </td>
                <td class="cell-user" data-label="کاربر">
                  <?php if (!empty($row['user_id'])): ?>
                    <?= htmlspecialchars(($row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['role'] . ')'), ENT_QUOTES, 'UTF-8') ?>
                  <?php else: ?>
                    -
                  <?php endif; ?>
                </td>
                <td data-label="کد ملی"><?= htmlspecialchars((string)$row['national_code'], ENT_QUOTES, 'UTF-8') ?></td>
                <td data-label="IP"><?= htmlspecialchars((string)$row['ip_address'], ENT_QUOTES, 'UTF-8') ?></td>
                <td data-label="User Agent">
                  <details class="payload-details">
                    <summary>نمایش</summary>
                    <pre><?= htmlspecialchars((string)$row['user_agent'], ENT_QUOTES, 'UTF-8') ?></pre>
                  </details>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="logs-pagination">
      <?php if ($authPage > 1): ?>
        <a class="page-btn" href="<?= htmlspecialchars($buildAuthPageUrl($authPage - 1), ENT_QUOTES, 'UTF-8') ?>">قبلی</a>
      <?php endif; ?>
      <?php if ($authPage < $authTotalPages): ?>
        <a class="page-btn" href="<?= htmlspecialchars($buildAuthPageUrl($authPage + 1), ENT_QUOTES, 'UTF-8') ?>">بعدی</a>
      <?php endif; ?>
    </div>
  </section>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
