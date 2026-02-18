<?php
// attendance.php
// صفحه مدیریت حضور و غیاب روزانه - فقط ادمین

declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_once __DIR__ . '/attendance_db.php';


// فقط ادمین مجاز است
$user = current_user();
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    header('Location: index.php?msg=' . urlencode('دسترسی فقط برای مدیران امکان‌پذیر است'));
    exit;
}

$pageTitle    = 'حضور و غیاب روزانه';
$activeNav    = 'attendance';
$extraStyles  = ['css/attendance.css'];
$extraScripts = ['js/attendance.js'];   // اگر جاوااسکریپت جداگانه داری

// مقادیر پیش‌فرض
$today  = date('Y-m-d');
$date   = $_GET['date']  ?? $today;
$grade  = (int)($_GET['grade'] ?? 1);
$field  = $_GET['field'] ?? 'کامپیوتر';

$grades = [1 => 'دهم', 2 => 'یازدهم', 3 => 'دوازدهم'];
$fields = ['کامپیوتر', 'الکترونیک', 'برق'];

// اتصال به دیتابیس حضور و غیاب
$pdo_att = get_attendance_db();

// گرفتن رکوردهای غیبت
$stmt = $pdo_att->prepare("
    SELECT * FROM attendance 
    WHERE date = ? AND grade = ? AND field = ?
    ORDER BY student_name ASC
");
$stmt->execute([$date, $grade, $field]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// گرفتن نام ثبت‌کننده‌ها از دیتابیس اصلی
$recorderNames = [];
if ($records) {
    $pdo_main = get_db();  // دیتابیس اصلی (users)

    $ids = array_unique(array_column($records, 'recorded_by'));
    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $userStmt = $pdo_main->prepare("
            SELECT id, first_name, last_name 
            FROM users 
            WHERE id IN ($placeholders)
        ");
        $userStmt->execute($ids);

        foreach ($userStmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
            $recorderNames[$u['id']] = trim($u['first_name'] . ' ' . $u['last_name']) ?: 'ادمین';
        }
    }
}

require __DIR__ . '/partials/header.php';
?>
<?php
if (isset($_SESSION['attendance_message'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
    echo htmlspecialchars($_SESSION['attendance_message']);
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo '</div>';
    unset($_SESSION['attendance_message']);
}
?>
<main class="attendance-page container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">حضور و غیاب روزانه</h1>
    </div>

    <!-- فرم فیلتر -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">تاریخ</label>
                    <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">پایه</label>
                    <select name="grade" class="form-select" required>
                        <?php foreach ($grades as $g => $name): ?>
                            <option value="<?= $g ?>" <?= $grade === $g ? 'selected' : '' ?>>
                                <?= htmlspecialchars($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">رشته</label>
                    <select name="field" class="form-select" required>
                        <?php foreach ($fields as $f): ?>
                            <option value="<?= htmlspecialchars($f) ?>" <?= $field === $f ? 'selected' : '' ?>>
                                <?= htmlspecialchars($f) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">نمایش / ثبت</button>
                </div>
            </form>
        </div>
    </div>

    <!-- فرم ثبت غیبت جدید -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-light">
            <h5 class="mb-0">ثبت غایب جدید – <?= str_replace('-', '/', $date) ?></h5>
        </div>
        <div class="card-body">
            <form method="POST" action="attendance_save.php" id="attendanceForm">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="date"  value="<?= htmlspecialchars($date) ?>">
                <input type="hidden" name="grade" value="<?= $grade ?>">
                <input type="hidden" name="field" value="<?= htmlspecialchars($field) ?>">

                <div id="absent-rows" class="mb-3"></div>

                <div class="d-flex gap-2">
                    <button type="button" id="add-student" class="btn btn-outline-primary">
                        + اضافه کردن دانش‌آموز
                    </button>
                    <button type="submit" class="btn btn-success">
                        ذخیره غیبت‌ها
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- لیست غیبت‌های ثبت‌شده -->
    <?php if ($records): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0">غیبت‌های ثبت‌شده (<?= count($records) ?> مورد)</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>نام و نام خانوادگی</th>
                                <th>زنگ‌های غیبت</th>
                                <th>یادداشت</th>
                                <th>ثبت‌کننده</th>
                                <th class="text-end">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $r): 
                                $hoursArr = explode(',', $r['absent_hours']);
                                $hoursChecked = array_fill(1, 4, false);
                                foreach ($hoursArr as $h) {
                                    $h = (int)trim($h);
                                    if ($h >= 1 && $h <= 4) $hoursChecked[$h] = true;
                                }
                            ?>
                                <tr data-id="<?= $r['id'] ?>">
                                    <td><?= htmlspecialchars($r['student_name']) ?></td>
                                    <td>
                                        <?php for ($i = 1; $i <= 4; $i++): ?>
                                            <span class="badge <?= $hoursChecked[$i] ? 'bg-danger' : 'bg-secondary' ?> me-1">
                                                <?= $i ?>
                                            </span>
                                        <?php endfor; ?>
                                    </td>
                                    <td><?= htmlspecialchars($r['notes'] ?: '—') ?></td>
                                    <td><?= htmlspecialchars($recorderNames[$r['recorded_by']] ?? 'ادمین ناشناس') ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary edit-btn me-1"
                                                data-bs-toggle="modal" data-bs-target="#editModal"
                                                data-id="<?= $r['id'] ?>"
                                                data-name="<?= htmlspecialchars($r['student_name'], ENT_QUOTES) ?>"
                                                data-notes="<?= htmlspecialchars($r['notes'], ENT_QUOTES) ?>"
                                                data-hours='<?= json_encode($hoursChecked, JSON_UNESCAPED_UNICODE) ?>'>
                                            ویرایش
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger delete-btn"
                                                data-id="<?= $r['id'] ?>">
                                            حذف
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-info text-center py-4">
            هیچ غیبتی برای این تاریخ، پایه و رشته ثبت نشده است.
        </div>
    <?php endif; ?>

</main>


<!-- مودال ویرایش -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">ویرایش رکورد غیبت</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="attendance_update.php">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
                    <input type="hidden" name="grade" value="<?= $grade ?>">
                    <input type="hidden" name="field" value="<?= htmlspecialchars($field) ?>">

                    <div class="mb-3">
                        <label class="form-label">نام و نام خانوادگی</label>
                        <input type="text" class="form-control" id="edit_name" name="student_name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">زنگ‌های غیبت</label>
                        <div class="d-flex gap-3">
                            <?php for ($i = 1; $i <= 4; $i++): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="hours[]" value="<?= $i ?>" id="edit_h<?= $i ?>">
                                    <label class="form-check-label" for="edit_h<?= $i ?>">
                                        زنگ <?= $i ?>
                                    </label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">یادداشت</label>
                        <textarea class="form-control" name="notes" id="edit_notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>

<script>
// اضافه کردن ردیف جدید
let rowIndex = 0;

document.getElementById('add-student')?.addEventListener('click', () => {
    const container = document.getElementById('absent-rows');
    const row = document.createElement('div');
    row.className = 'absent-row mb-3 p-3 border rounded bg-light';
    row.innerHTML = `
        <div class="row g-2">
            <div class="col-md-4">
                <input type="text" name="students[${rowIndex}][name]" class="form-control" 
                       placeholder="نام و نام خانوادگی" required>
            </div>
            <div class="col-md-5">
                <div class="d-flex gap-2 flex-wrap">
                    ${[1,2,3,4].map(i => `
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="students[${rowIndex}][hours][]" value="${i}" id="h${rowIndex}_${i}">
                            <label class="form-check-label" for="h${rowIndex}_${i}">زنگ ${i}</label>
                        </div>
                    `).join('')}
                </div>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <input type="text" name="students[${rowIndex}][notes]" class="form-control" placeholder="یادداشت">
                <button type="button" class="btn btn-outline-danger btn-sm remove-row">حذف</button>
            </div>
        </div>
    `;
    container.appendChild(row);
    rowIndex++;
});

// حذف ردیف
document.addEventListener('click', e => {
    if (e.target.classList.contains('remove-row')) {
        e.target.closest('.absent-row')?.remove();
    }
});

// ویرایش - پر کردن مودال
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.getElementById('edit_id').value    = this.dataset.id;
        document.getElementById('edit_name').value  = this.dataset.name;
        document.getElementById('edit_notes').value = this.dataset.notes;

        const hours = JSON.parse(this.dataset.hours);
        for (let i = 1; i <= 4; i++) {
            document.getElementById(`edit_h${i}`).checked = !!hours[i];
        }
    });
});

// حذف با تأیید
document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        if (!confirm('آیا مطمئن هستید که می‌خواهید این غیبت را حذف کنید؟')) return;

        const id = this.dataset.id;
        fetch('attendance_delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `csrf_token=<?= urlencode(csrf_token()) ?>&id=${encodeURIComponent(id)}`
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                this.closest('tr').remove();
                alert('با موفقیت حذف شد');
            } else {
                alert(data.error || 'خطایی رخ داد');
            }
        })
        .catch(() => alert('خطا در ارتباط با سرور'));
    });
});

// اضافه کردن حداقل یک ردیف خالی در ابتدا
if (document.getElementById('add-student')) {
    document.getElementById('add-student').click();
}
</script>