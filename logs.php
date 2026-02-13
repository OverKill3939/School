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
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
