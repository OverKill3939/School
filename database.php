<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_once __DIR__ . '/auth/database_maintenance.php';

require_admin();

function database_format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    $units = ['KB', 'MB', 'GB', 'TB'];
    $size = $bytes / 1024;
    $unitIndex = 0;

    while ($size >= 1024 && $unitIndex < count($units) - 1) {
        $size /= 1024;
        $unitIndex++;
    }

    return number_format($size, 2) . ' ' . $units[$unitIndex];
}

function database_format_datetime(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '-';
    }

    try {
        $dt = new DateTimeImmutable($value);
    } catch (Throwable) {
        return '-';
    }

    if (class_exists('IntlDateFormatter')) {
        $formatter = new IntlDateFormatter(
            'fa_IR',
            IntlDateFormatter::MEDIUM,
            IntlDateFormatter::SHORT,
            date_default_timezone_get(),
            IntlDateFormatter::GREGORIAN
        );
        $formatted = $formatter->format($dt);
        if ($formatted !== false) {
            return (string)$formatted;
        }
    }

    return $dt->format('Y/m/d H:i');
}

function database_health_summary(array $result): string
{
    $driver = (string)($result['driver'] ?? 'unknown');
    $tableCount = (int)($result['table_count'] ?? 0);
    $integrity = (string)($result['integrity'] ?? 'unknown');
    $fileSize = (int)($result['db_file_size'] ?? 0);

    $summary = 'درایور: ' . strtoupper($driver) . ' | تعداد جدول: ' . $tableCount . ' | وضعیت: ' . $integrity;
    if ($driver === 'sqlite') {
        $summary .= ' | حجم فایل: ' . database_format_bytes($fileSize);
    }

    return $summary;
}

$actor = current_user();
$driver = database_backup_driver();
$flashKey = 'database_flash';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $messageType = 'error';
    $messageText = 'درخواست نامعتبر است.';
    $action = trim((string)($_POST['action'] ?? ''));

    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $messageText = 'توکن امنیتی معتبر نیست. لطفا دوباره تلاش کنید.';
    } else {
        try {
            if ($action === 'create_backup') {
                $result = create_database_backup('manual', $actor);
                $messageType = 'success';
                $messageText = 'بکاپ دستی با موفقیت ساخته شد: ' . (string)$result['file_name'];
            } elseif ($action === 'restore_backup') {
                $fileName = trim((string)($_POST['file_name'] ?? ''));
                if ($fileName === '') {
                    throw new RuntimeException('نام فایل بکاپ نامعتبر است.');
                }

                $createSafetyBackup = ((string)($_POST['create_safety_backup'] ?? '1') === '1');
                $result = restore_database_from_backup($fileName, $actor, $createSafetyBackup);

                $messageType = 'success';
                $messageText = 'بازگردانی دیتابیس با موفقیت انجام شد: ' . (string)$result['restored_file'];
                if (!empty($result['safety_backup'])) {
                    $messageText .= ' | بکاپ ایمنی: ' . (string)$result['safety_backup'];
                }
            } elseif ($action === 'run_auto_now') {
                $settings = load_database_backup_settings();
                $result = create_database_backup('auto', $actor);
                prune_database_backups((int)($settings['retention_count'] ?? 14));

                $settings['last_attempt_at'] = date(DATE_ATOM);
                $settings['last_success_at'] = date(DATE_ATOM);
                $settings['last_error'] = null;
                save_database_backup_settings($settings);

                $messageType = 'success';
                $messageText = 'بکاپ خودکار با موفقیت اجرا شد: ' . (string)$result['file_name'];
            } elseif ($action === 'save_auto_settings') {
                $enabled = ((string)($_POST['auto_enabled'] ?? '') === '1');
                $intervalHours = (int)($_POST['interval_hours'] ?? 24);
                $retentionCount = (int)($_POST['retention_count'] ?? 14);

                if ($intervalHours < 1 || $intervalHours > 720) {
                    throw new RuntimeException('بازه بکاپ خودکار باید بین 1 تا 720 ساعت باشد.');
                }

                if ($retentionCount < 1 || $retentionCount > 365) {
                    throw new RuntimeException('تعداد نگهداری بکاپ باید بین 1 تا 365 فایل باشد.');
                }

                $before = load_database_backup_settings();
                $updated = $before;
                $updated['enabled'] = $enabled;
                $updated['interval_hours'] = $intervalHours;
                $updated['retention_count'] = $retentionCount;

                save_database_backup_settings($updated);

                if (function_exists('log_event_action')) {
                    log_event_action($actor, 'update', null, $before, $updated, 'database_backup_settings');
                }

                $messageType = 'success';
                $messageText = 'تنظیمات بکاپ خودکار ذخیره شد.';
            } elseif ($action === 'prune_old_auto_backups') {
                $settings = load_database_backup_settings();
                $deletedCount = prune_database_backups((int)($settings['retention_count'] ?? 14));

                $messageType = 'success';
                if ($deletedCount > 0) {
                    $messageText = 'پاکسازی بکاپ‌های خودکار انجام شد. تعداد حذف شده: ' . $deletedCount;
                } else {
                    $messageText = 'بکاپ خودکار اضافی برای حذف وجود نداشت.';
                }
            } elseif ($action === 'run_health_check') {
                $result = run_database_health_check();
                if (!empty($result['ok'])) {
                    $messageType = 'success';
                    $messageText = 'بررسی سلامت موفق بود. ' . database_health_summary($result);
                } else {
                    $messageType = 'error';
                    $messageText = 'بررسی سلامت خطا داد. ' . database_health_summary($result);
                }
            } elseif ($action === 'clear_last_backup_error') {
                $before = load_database_backup_settings();
                $updated = $before;
                $updated['last_error'] = null;
                save_database_backup_settings($updated);

                if (function_exists('log_event_action')) {
                    log_event_action($actor, 'update', null, $before, $updated, 'database_backup_settings');
                }

                $messageType = 'success';
                $messageText = 'آخرین خطای ثبت‌شده پاک شد.';
            } elseif ($action === 'delete_backup') {
                $fileName = trim((string)($_POST['file_name'] ?? ''));
                if ($fileName === '') {
                    throw new RuntimeException('نام فایل بکاپ نامعتبر است.');
                }

                if (!delete_database_backup($fileName, $actor)) {
                    throw new RuntimeException('حذف بکاپ انجام نشد یا فایل وجود ندارد.');
                }

                $messageType = 'success';
                $messageText = 'فایل بکاپ حذف شد.';
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
    $_SESSION[$flashKey] = [
        'type' => $messageType,
        'text' => $messageText,
    ];

    header('Location: database.php');
    exit;
}

start_secure_session();
$flash = $_SESSION[$flashKey] ?? null;
unset($_SESSION[$flashKey]);

$settings = load_database_backup_settings();
$backups = list_database_backups();
$totalSize = 0;
$manualCount = 0;
$autoCount = 0;
$lastBackupAt = null;

foreach ($backups as $backup) {
    $totalSize += (int)($backup['size'] ?? 0);
    if (($backup['mode'] ?? '') === 'auto') {
        $autoCount++;
    } else {
        $manualCount++;
    }

    if ($lastBackupAt === null && !empty($backup['modified_at'])) {
        $lastBackupAt = (string)$backup['modified_at'];
    }
}

$pageTitle = 'مدیریت دیتابیس | هنرستان دارالفنون';
$activeNav = 'database';
$extraStyles = ['css/database.css?v=' . filemtime(__DIR__ . '/css/database.css')];

require __DIR__ . '/partials/header.php';
?>

<main class="database-page">
  <section class="database-card">
    <div class="database-head">
      <h1>مدیریت دیتابیس</h1>
      <p>ساخت بکاپ دستی، تنظیم بکاپ خودکار و مدیریت فایل‌های پشتیبان</p>
    </div>

    <?php if (is_array($flash) && !empty($flash['text'])): ?>
      <div class="alert <?= ($flash['type'] ?? '') === 'success' ? 'alert-success' : 'alert-error' ?>">
        <?= htmlspecialchars((string)$flash['text'], ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <div class="database-stats">
      <div class="stat-item">
        <strong><?= count($backups) ?></strong>
        <span>کل بکاپ‌ها</span>
      </div>
      <div class="stat-item">
        <strong><?= $manualCount ?></strong>
        <span>بکاپ دستی</span>
      </div>
      <div class="stat-item">
        <strong><?= $autoCount ?></strong>
        <span>بکاپ خودکار</span>
      </div>
      <div class="stat-item">
        <strong><?= htmlspecialchars(database_format_bytes($totalSize), ENT_QUOTES, 'UTF-8') ?></strong>
        <span>حجم کل</span>
      </div>
      <div class="stat-item">
        <strong><?= htmlspecialchars(database_format_datetime($lastBackupAt), ENT_QUOTES, 'UTF-8') ?></strong>
        <span>آخرین زمان بکاپ</span>
      </div>
    </div>

    <div class="database-tools">
      <article class="tool-card">
        <h2>بکاپ دستی</h2>
        <p>برای ایجاد فوری یک نسخه پشتیبان از دیتابیس استفاده کنید.</p>
        <form method="post" class="tool-form">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />
          <input type="hidden" name="action" value="create_backup" />
          <button type="submit" class="btn btn-primary">ایجاد بکاپ دستی</button>
        </form>
      </article>

      <article class="tool-card">
        <h2>تنظیم بکاپ خودکار</h2>
        <p>بکاپ خودکار در زمان بازدید مدیر از پنل بررسی و اجرا می‌شود.</p>
        <form method="post" class="tool-form grid-form">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />
          <input type="hidden" name="action" value="save_auto_settings" />

          <label class="checkbox-row switch-field">
            <input type="checkbox" name="auto_enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : '' ?> />
            <span class="switch-ui" aria-hidden="true"></span>
            <span>فعال‌سازی بکاپ خودکار</span>
          </label>

          <label>
            <span>فاصله زمانی (ساعت)</span>
            <input
              type="number"
              min="1"
              max="720"
              name="interval_hours"
              value="<?= (int)($settings['interval_hours'] ?? 24) ?>"
              required
            />
          </label>

          <label>
            <span>تعداد نگهداری بکاپ خودکار</span>
            <input
              type="number"
              min="1"
              max="365"
              name="retention_count"
              value="<?= (int)($settings['retention_count'] ?? 14) ?>"
              required
            />
          </label>

          <button type="submit" class="btn btn-primary">ذخیره تنظیمات</button>
        </form>

        <form method="post" class="tool-form inline-form">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />
          <input type="hidden" name="action" value="run_auto_now" />
          <button type="submit" class="btn btn-secondary">اجرای بکاپ خودکار الان</button>
        </form>

        <div class="meta-list">
          <div><span>آخرین تلاش:</span> <?= htmlspecialchars(database_format_datetime($settings['last_attempt_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></div>
          <div><span>آخرین موفق:</span> <?= htmlspecialchars(database_format_datetime($settings['last_success_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></div>
          <div><span>وضعیت:</span> <?= !empty($settings['enabled']) ? 'فعال' : 'غیرفعال' ?></div>
          <?php if (!empty($settings['last_error'])): ?>
            <div class="error-text"><span>آخرین خطا:</span> <?= htmlspecialchars((string)$settings['last_error'], ENT_QUOTES, 'UTF-8') ?></div>
          <?php endif; ?>
        </div>
      </article>

      <article class="tool-card">
        <h2>ابزار نگهداری</h2>
        <p>برای کنترل سلامت دیتابیس و مدیریت بکاپ‌های قدیمی از این ابزارها استفاده کنید.</p>

        <form method="post" class="tool-form">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />
          <input type="hidden" name="action" value="run_health_check" />
          <button type="submit" class="btn btn-secondary">بررسی سلامت دیتابیس</button>
        </form>

        <form method="post" class="tool-form inline-form">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />
          <input type="hidden" name="action" value="prune_old_auto_backups" />
          <button type="submit" class="btn btn-secondary">پاکسازی بکاپ‌های خودکار قدیمی</button>
        </form>

        <?php if (!empty($settings['last_error'])): ?>
          <form method="post" class="tool-form inline-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />
            <input type="hidden" name="action" value="clear_last_backup_error" />
            <button type="submit" class="btn btn-secondary">پاک کردن آخرین خطای بکاپ</button>
          </form>
        <?php endif; ?>
      </article>
    </div>

    <section class="backup-list">
      <div class="list-head">
        <h2>فایل‌های بکاپ</h2>
        <span class="driver-chip">نوع دیتابیس فعال: <?= htmlspecialchars(strtoupper($driver), ENT_QUOTES, 'UTF-8') ?></span>
      </div>
      <p class="security-note">دانلود بکاپ برای امنیت غیرفعال شده است. از گزینه بازگردانی داخلی استفاده کنید.</p>
      <p class="restore-note">بازگردانی، دیتابیس فعلی را با فایل انتخابی جایگزین می‌کند و قبل از آن یک بکاپ ایمنی می‌سازد.</p>

      <?php if ($backups === []): ?>
        <p class="empty-state">هنوز هیچ فایل بکاپی ثبت نشده است.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="backup-table">
            <thead>
              <tr>
                <th>نام فایل</th>
                <th>نوع</th>
                <th>فرمت</th>
                <th>حجم</th>
                <th>تاریخ</th>
                <th>عملیات</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($backups as $backup): ?>
                <?php
                $fileName = (string)($backup['file_name'] ?? '');
                $mode = (string)($backup['mode'] ?? 'manual');
                $driverLabel = (($backup['driver'] ?? 'sqlite') === 'mysql') ? 'SQL' : 'SQLite';
                $modeLabel = $mode === 'auto' ? 'خودکار' : 'دستی';
                $backupDriver = database_backup_driver_from_filename($fileName) ?? '';
                $canRestore = ($driver === 'sqlite' && $backupDriver === 'sqlite');
                ?>
                <tr>
                  <td><code><?= htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8') ?></code></td>
                  <td><?= htmlspecialchars($modeLabel, ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($driverLabel, ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars(database_format_bytes((int)($backup['size'] ?? 0)), ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars(database_format_datetime((string)($backup['modified_at'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                  <td>
                    <div class="row-actions">
                      <form method="post" onsubmit="return confirm('این بکاپ روی دیتابیس فعلی بازگردانی شود؟');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />
                        <input type="hidden" name="action" value="restore_backup" />
                        <input type="hidden" name="file_name" value="<?= htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8') ?>" />
                        <input type="hidden" name="create_safety_backup" value="1" />
                        <button type="submit" class="btn btn-warning btn-sm" <?= $canRestore ? '' : 'disabled' ?>>بازگردانی</button>
                      </form>
                      <form method="post" onsubmit="return confirm('فایل بکاپ حذف شود؟');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />
                        <input type="hidden" name="action" value="delete_backup" />
                        <input type="hidden" name="file_name" value="<?= htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8') ?>" />
                        <button type="submit" class="btn btn-danger btn-sm">حذف</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </section>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
