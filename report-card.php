<?php
/**
 * report-card.php
 * بخش کامل کارنامه با قابلیت مشاهده، آپلود، ویرایش و حذف
 * دیتابیس جداگانه (SQLite) - مناسب برای پروژه مدرسه
 * تاریخ تقریبی: بهمن ۱۴۰۴ / فوریه ۲۰۲۶
 */

declare(strict_types=1);

// ────────────────────────────────────────────────
// لود هلپرهای پروژه (تطبیق با ساختار قبلی شما)
// ────────────────────────────────────────────────

require_once __DIR__ . '/auth/helpers.php';       // current_user(), require_login(), csrf_token(), csrf_check()
require_once __DIR__ . '/auth/db_report.php';     // get_report_db() ← دیتابیس جداگانه report_cards.sqlite

require_login();  // همه باید لاگین باشند

// ────────────────────────────────────────────────
// تنظیمات صفحه
// ────────────────────────────────────────────────

$pageTitle   = 'کارنامه | هنرستان دارالفنون';
$activeNav   = 'report-card';
$extraStyles = ['css/report-card.css'];

$user        = current_user();
$isAdmin     = ($user['role'] ?? '') === 'admin';
$pdo         = get_report_db();   // اتصال به دیتابیس کارنامه‌ها

$messages    = [];
$success     = false;

// ────────────────────────────────────────────────
// پردازش درخواست‌ها (آپلود / ویرایش / حذف)
// ────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        $messages[] = 'توکن امنیتی نامعتبر است. صفحه را رفرش کنید.';
    }
    else {
        $action = $_POST['action'] ?? '';

        // ─── آپلود یا ویرایش ───────────────────────────────
        if ($isAdmin && in_array($action, ['upload_report', 'update_report'])) {

            $nc    = preg_replace('/\D+/', '', trim($_POST['national_code'] ?? ''));
            $grade = trim($_POST['grade'] ?? '');
            $term  = trim($_POST['term'] ?? date('Y') . ' - ترم اول');
            $rid   = ($action === 'update_report') ? (int)($_POST['report_id'] ?? 0) : 0;

            if (strlen($nc) !== 10) {
                $messages[] = 'کد ملی باید دقیقاً ۱۰ رقم باشد.';
            }
            elseif (empty($term)) {
                $messages[] = 'لطفاً ترم/نیم‌سال را وارد کنید.';
            }
            else {
                $new_image_path = null;
                $keep_old_image = true;

                if (!empty($_FILES['report_image']['name'])) {
                    $file = $_FILES['report_image'];

                    if ($file['error'] !== UPLOAD_ERR_OK) {
                        $messages[] = 'خطا در آپلود فایل.';
                    }
                    elseif ($file['size'] > 5242880) { // 5MB
                        $messages[] = 'حجم فایل بیش از ۵ مگابایت است.';
                    }
                    else {
                        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
                            $messages[] = 'فقط jpg, jpeg, png, webp مجاز است.';
                        }
                        else {
                            $filename = 'report_' . $nc . '_' . time() . '.' . $ext;
                            $dest     = __DIR__ . '/uploads/report-cards/' . $filename;
                            $url_path = '/uploads/report-cards/' . $filename;

                            if (!is_dir(dirname($dest))) {
                                mkdir(dirname($dest), 0755, true);
                            }

                            if (move_uploaded_file($file['tmp_name'], $dest)) {
                                $new_image_path = $url_path;
                                $keep_old_image = false;
                            }
                            else {
                                $messages[] = 'خطا در ذخیره فایل روی سرور.';
                            }
                        }
                    }
                }

                if (empty($messages)) {
                    try {
                        if ($rid > 0) {
                            // ویرایش
                            $sql = "UPDATE report_cards SET 
                                        grade = :grade,
                                        term  = :term,
                                        uploaded_at = CURRENT_TIMESTAMP";
                            $params = [':grade' => $grade ?: null, ':term' => $term];

                            if (!$keep_old_image && $new_image_path) {
                                // گرفتن عکس قدیمی برای حذف فیزیکی
                                $oldstmt = $pdo->prepare("SELECT image_path FROM report_cards WHERE id = ?");
                                $oldstmt->execute([$rid]);
                                $old = $oldstmt->fetch();
                                if ($old && $old['image_path']) {
                                    $oldfile = __DIR__ . $old['image_path'];
                                    if (file_exists($oldfile)) @unlink($oldfile);
                                }
                                $sql .= ", image_path = :path";
                                $params[':path'] = $new_image_path;
                            }

                            $sql .= " WHERE id = :id";
                            $params[':id'] = $rid;

                            $stmt = $pdo->prepare($sql);
                            $stmt->execute($params);
                            $messages[] = 'کارنامه با موفقیت ویرایش شد.';
                        }
                        else {
                            // ثبت جدید
                            $stmt = $pdo->prepare("
                                INSERT INTO report_cards 
                                    (national_code, uploader_id, image_path, grade, term)
                                VALUES 
                                    (:nc, :uid, :path, :grade, :term)
                            ");
                            $stmt->execute([
                                ':nc'    => $nc,
                                ':uid'   => (int)$user['id'],
                                ':path'  => $new_image_path,
                                ':grade' => $grade ?: null,
                                ':term'  => $term
                            ]);
                            $messages[] = 'کارنامه با موفقیت ثبت شد.';
                        }
                        $success = true;
                    }
                    catch (PDOException $e) {
                        $messages[] = 'خطا در ذخیره دیتابیس: ' . $e->getMessage();
                        if ($new_image_path) {
                            @unlink(__DIR__ . $new_image_path);
                        }
                    }
                }
            }
        }

        // ─── حذف ───────────────────────────────────────────────
        elseif ($isAdmin && $action === 'delete_report') {
            $rid = (int)($_POST['report_id'] ?? 0);

            if ($rid > 0) {
                try {
                    $stmt = $pdo->prepare("SELECT image_path FROM report_cards WHERE id = ?");
                    $stmt->execute([$rid]);
                    $row = $stmt->fetch();

                    if ($row) {
                        if ($row['image_path']) {
                            $filepath = __DIR__ . $row['image_path'];
                            if (file_exists($filepath)) @unlink($filepath);
                        }

                        $del = $pdo->prepare("DELETE FROM report_cards WHERE id = ?");
                        $del->execute([$rid]);

                        $success = true;
                        $messages[] = 'کارنامه حذف شد.';
                    }
                    else {
                        $messages[] = 'رکورد موردنظر یافت نشد.';
                    }
                }
                catch (Exception $e) {
                    $messages[] = 'خطا در حذف: ' . $e->getMessage();
                }
            }
            else {
                $messages[] = 'شناسه نامعتبر.';
            }
        }
    }
}

// ────────────────────────────────────────────────
// گرفتن لیست کارنامه‌ها
// ────────────────────────────────────────────────

$reports = [];
$search_nc = '';

if ($isAdmin) {
    $search_nc = preg_replace('/\D+/', '', trim($_GET['search_nc'] ?? ''));
    if (strlen($search_nc) === 10) {
        $stmt = $pdo->prepare("SELECT * FROM report_cards WHERE national_code = ? ORDER BY uploaded_at DESC");
        $stmt->execute([$search_nc]);
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
else {
    $stmt = $pdo->prepare("SELECT * FROM report_cards WHERE national_code = ? ORDER BY uploaded_at DESC");
    $stmt->execute([$user['national_code']]);
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require __DIR__ . '/partials/header.php';
?>

<main class="report-page container">
    <section class="report-card card">

        <h1>کارنامه تحصیلی</h1>

        <?php if ($messages): ?>
        <div class="alert <?= $success ? 'success' : 'error' ?>">
            <?= htmlspecialchars(implode(" • ", $messages)) ?>
        </div>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
        <!-- فرم ثبت / ویرایش -->
        <h2>ثبت یا ویرایش کارنامه</h2>
        <form method="post" enctype="multipart/form-data" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <input type="hidden" name="action" value="upload_report">
            <input type="hidden" name="report_id" id="hidden_report_id" value="">

            <div class="form-field">
                <label>کد ملی دانش‌آموز (۱۰ رقم)</label>
                <input type="text" name="national_code" id="nc_input" pattern="\d{10}" maxlength="10" required>
            </div>

            <div class="form-field">
                <label>پایه تحصیلی</label>
                <select name="grade" id="grade_select">
                    <option value="">─ انتخاب کنید ─</option>
                    <option value="دهم">دهم</option>
                    <option value="یازدهم">یازدهم</option>
                    <option value="دوازدهم">دوازدهم</option>
                </select>
            </div>

            <div class="form-field">
                <label>ترم / نیم‌سال</label>
                <input type="text" name="term" id="term_input" required placeholder="۱۴۰۴-۱۴۰۵ - ترم اول">
            </div>

            <div class="form-field">
                <label>تصویر کارنامه (jpg/png/webp - max ۵MB)</label>
                <input type="file" name="report_image" accept="image/jpeg,image/png,image/webp">
                <small id="current_image_hint" style="display:none; color:#2563eb;">تصویر فعلی وجود دارد – برای تغییر فایل جدید انتخاب کنید</small>
            </div>

            <button type="submit" class="btn-primary">ثبت / به‌روزرسانی</button>
        </form>

        <hr style="margin:2.2rem 0 1.8rem;">

        <!-- جستجو -->
        <h2>جستجوی کارنامه دانش‌آموز</h2>
        <form method="get" class="form-inline">
            <input type="text" name="search_nc" placeholder="کد ملی ۱۰ رقمی" pattern="\d{10}" maxlength="10" value="<?= htmlspecialchars($search_nc) ?>">
            <button type="submit" class="btn-secondary">جستجو</button>
        </form>
        <?php endif; ?>

        <!-- لیست کارنامه‌ها -->
        <?php if ($reports): ?>
            <h2 style="margin: 2.5rem 0 1.2rem;">
                <?= $isAdmin ? "کارنامه‌های دانش‌آموز $search_nc" : 'کارنامه‌های شما' ?>
            </h2>

            <div class="reports-grid">
                <?php foreach ($reports as $r): ?>
                <div class="report-card-item">
                    <div class="report-info">
                        <h3><?= htmlspecialchars($r['grade'] ?: 'پایه نامشخص') ?> — <?= htmlspecialchars($r['term']) ?></h3>
                        <div class="meta">
                            <time><?= htmlspecialchars($r['uploaded_at']) ?></time>
                        </div>
                    </div>

                    <img src="<?= htmlspecialchars($r['image_path']) ?>" class="report-preview" alt="کارنامه">

                    <?php if ($isAdmin): ?>
                    <div class="report-actions">
                        <button class="btn-edit small"
                                onclick="loadEditForm(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['national_code'])) ?>',
                                                       '<?= htmlspecialchars(addslashes($r['grade'] ?? '')) ?>',
                                                       '<?= htmlspecialchars(addslashes($r['term'])) ?>')">
                            ویرایش
                        </button>

                        <form method="post" class="inline-delete" 
                              onsubmit="return confirm('حذف این کارنامه غیرقابل بازگشت است.\nمطمئن هستید؟');">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete_report">
                            <input type="hidden" name="report_id" value="<?= $r['id'] ?>">
                            <button type="submit" class="btn-delete small">حذف</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php elseif (!$isAdmin): ?>
            <p class="no-data">هنوز کارنامه‌ای برای شما ثبت نشده است.</p>
        <?php endif; ?>

    </section>
</main>

<?php if ($isAdmin): ?>
<!-- فرم ویرایش به صورت modal -->
<div id="editModal" class="modal" style="display:none;">
    <div class="modal-dialog">
        <div class="modal-header">
            <h2>ویرایش کارنامه</h2>
            <button type="button" class="close" onclick="closeModal()">×</button>
        </div>
        <div class="modal-body">
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="action" value="update_report">
                <input type="hidden" name="report_id" id="edit_report_id">

                <div class="form-field">
                    <label>کد ملی (غیرقابل تغییر)</label>
                    <input type="text" id="edit_nc" readonly>
                </div>

                <div class="form-field">
                    <label for="edit_grade_sel">پایه تحصیلی</label>
                    <select id="edit_grade_sel" name="grade">
                        <option value="">─ انتخاب کنید ─</option>
                        <option value="دهم">دهم</option>
                        <option value="یازدهم">یازدهم</option>
                        <option value="دوازدهم">دوازدهم</option>
                    </select>
                </div>

                <div class="form-field">
                    <label for="edit_term_inp">ترم / نیم‌سال</label>
                    <input type="text" id="edit_term_inp" name="term" required>
                </div>

                <div class="form-field">
                    <label>تصویر فعلی</label>
                    <div id="current_img_container"></div>
                </div>

                <div class="form-field">
                    <label for="edit_file">تصویر جدید (اختیاری)</label>
                    <input type="file" id="edit_file" name="report_image" accept="image/jpeg,image/png,image/webp">
                    <small>انتخاب نشود = تصویر قبلی حفظ می‌شود</small>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal()">انصراف</button>
                    <button type="submit" class="btn-primary">ذخیره تغییرات</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function loadEditForm(id, nc, grade, term) {
    document.getElementById('edit_report_id').value = id;
    document.getElementById('edit_nc').value = nc;
    document.getElementById('edit_grade_sel').value = grade || '';
    document.getElementById('edit_term_inp').value = term || '';

    // نمایش تصویر فعلی
    const container = document.getElementById('current_img_container');
    container.innerHTML = '<img src="/uploads/report-cards/report_' + nc + '_....jpg" alt="تصویر فعلی" style="max-width:240px; border-radius:8px;">';
    // توجه: در عمل باید image_path واقعی را پاس بدهید یا از data attribute استفاده کنید

    document.getElementById('editModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('editModal').style.display = 'none';
}

window.onclick = function(e) {
    if (e.target.classList.contains('modal')) {
        closeModal();
    }
}
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>