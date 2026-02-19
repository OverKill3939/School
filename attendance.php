<?php
// attendance.php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_once __DIR__ . '/attendance_db.php';

require_admin();

$grades = attendance_allowed_grades();
$fields = attendance_allowed_fields();

$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$defaultGrade = (int)array_key_first($grades);
$defaultField = $fields[0] ?? 'شبکه و نرم افزار';

$date = normalize_attendance_date((string)($_GET['date'] ?? '')) ?? $today;
$grade = normalize_attendance_grade($_GET['grade'] ?? null) ?? $defaultGrade;
$field = normalize_attendance_field((string)($_GET['field'] ?? '')) ?? $defaultField;

$pageTitle = 'حضور و غیاب روزانه | هنرستان دارالفنون';
$activeNav = 'attendance';
$extraStyles = ['css/attendance.css?v=' . filemtime(__DIR__ . '/css/attendance.css')];
$extraScripts = ['js/attendance.js?v=' . filemtime(__DIR__ . '/js/attendance.js')];

$pdoAtt = get_attendance_db();

$stmt = $pdoAtt->prepare(
    'SELECT id, date, grade, field, student_name, absent_hours, notes, recorded_by, created_at
     FROM attendance
     WHERE date = ? AND grade = ? AND field = ?
     ORDER BY student_name COLLATE NOCASE ASC, id DESC'
);
$stmt->execute([$date, $grade, $field]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$recorderNames = [];
if ($records !== []) {
    $ids = array_values(array_unique(array_map('intval', array_column($records, 'recorded_by'))));

    if ($ids !== []) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $userStmt = get_db()->prepare("SELECT id, first_name, last_name FROM users WHERE id IN ($placeholders)");
        $userStmt->execute($ids);

        foreach ($userStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $fullName = trim(((string)$row['first_name']) . ' ' . ((string)$row['last_name']));
            $recorderNames[(int)$row['id']] = $fullName !== '' ? $fullName : 'مدیر';
        }
    }
}

$flash = attendance_take_flash();
$recordsCount = count($records);

$todayUrl = attendance_redirect_url($today, $grade, $field);
$resetUrl = attendance_redirect_url($today, $defaultGrade, $defaultField);

require __DIR__ . '/partials/header.php';
?>
<main class="attendance-page">
  <section class="attendance-card">
    <div class="attendance-head">
      <h1>مدیریت حضور و غیاب</h1>
      <p>ثبت غیبت روزانه، ویرایش سریع رکوردها و حذف ایمن با حفظ سازگاری کامل با پنل مدیریت.</p>
    </div>

    <?php if (is_array($flash)): ?>
      <div class="alert <?= ($flash['type'] ?? '') === 'success' ? 'alert-success' : 'alert-error' ?>" role="status">
        <?= htmlspecialchars((string)$flash['text'], ENT_QUOTES, 'UTF-8') ?>
      </div>
    <?php endif; ?>

    <form method="get" class="attendance-filters" novalidate>
      <label>
        تاریخ
        <input type="date" name="date" value="<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>" required />
      </label>

      <label>
        پایه
        <select name="grade" required>
          <?php foreach ($grades as $gradeValue => $gradeLabel): ?>
            <option value="<?= $gradeValue ?>" <?= $grade === $gradeValue ? 'selected' : '' ?>>
              <?= htmlspecialchars($gradeLabel, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>
        رشته
        <select name="field" required>
          <?php foreach ($fields as $fieldLabel): ?>
            <option value="<?= htmlspecialchars($fieldLabel, ENT_QUOTES, 'UTF-8') ?>" <?= $field === $fieldLabel ? 'selected' : '' ?>>
              <?= htmlspecialchars($fieldLabel, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <div class="filter-actions">
        <button type="submit" class="btn-primary">نمایش اطلاعات</button>
        <a href="<?= htmlspecialchars($todayUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn-secondary">امروز</a>
        <a href="<?= htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn-secondary">بازنشانی</a>
      </div>
    </form>

    <div class="attendance-meta">
      <span>تاریخ فعال: <?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?></span>
      <span>تعداد رکوردهای امروز: <strong id="recordCount"><?= $recordsCount ?></strong></span>
    </div>

    <section class="attendance-create" aria-label="ثبت غیبت جدید">
      <div class="section-head">
        <h2>ثبت غیبت جدید</h2>
        <p>برای هر دانش آموز حداقل یک زنگ را انتخاب کنید. ثبت تکراری، به صورت هوشمند بروزرسانی می‌شود.</p>
      </div>

      <form method="post" action="attendance_save.php" id="attendanceCreateForm" class="attendance-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />
        <input type="hidden" name="date" value="<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>" />
        <input type="hidden" name="grade" value="<?= $grade ?>" />
        <input type="hidden" name="field" value="<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>" />

        <div id="absentRows" class="absent-rows">
          <div class="absent-row" data-row-index="0">
            <div class="row-grid">
              <label>
                نام و نام خانوادگی
                <input type="text" name="students[0][name]" maxlength="120" required placeholder="مثال: علی رضایی" />
              </label>

              <fieldset class="hours-fieldset">
                <legend>زنگ های غیبت</legend>
                <div class="hours-grid">
                  <?php for ($hour = 1; $hour <= 4; $hour++): ?>
                    <label class="hour-checkbox" for="create_h_0_<?= $hour ?>">
                      <input id="create_h_0_<?= $hour ?>" type="checkbox" name="students[0][hours][]" value="<?= $hour ?>" />
                      <span>زنگ <?= $hour ?></span>
                    </label>
                  <?php endfor; ?>
                </div>
              </fieldset>

              <label>
                یادداشت
                <input type="text" name="students[0][notes]" maxlength="500" placeholder="اختیاری" />
              </label>

              <div class="row-actions">
                <button type="button" class="btn-danger remove-row" aria-label="حذف این ردیف">حذف</button>
              </div>
            </div>
          </div>
        </div>

        <div class="create-actions">
          <button type="button" id="addStudentRow" class="btn-secondary">افزودن دانش آموز</button>
          <button type="submit" class="btn-primary">ذخیره غیبت ها</button>
        </div>
      </form>
    </section>

    <section class="attendance-list" aria-label="لیست غیبت های ثبت شده">
      <div class="section-head">
        <h2>غیبت های ثبت شده</h2>
        <p>رکوردهای فیلتر جاری را می‌توانید ویرایش یا حذف کنید.</p>
      </div>

      <?php if ($records === []): ?>
        <div id="attendanceEmptyState" class="empty-state">برای فیلتر انتخابی، رکوردی ثبت نشده است.</div>
      <?php else: ?>
        <div id="attendanceEmptyState" class="empty-state is-hidden">برای فیلتر انتخابی، رکوردی ثبت نشده است.</div>
        <div class="attendance-table-wrap">
          <table class="attendance-table">
            <thead>
              <tr>
                <th>نام دانش آموز</th>
                <th>زنگ ها</th>
                <th>یادداشت</th>
                <th>ثبت کننده</th>
                <th>زمان ثبت</th>
                <th>عملیات</th>
              </tr>
            </thead>
            <tbody id="attendanceRowsBody">
              <?php foreach ($records as $record): ?>
                <?php
                  $hours = sanitize_attendance_hours(explode(',', (string)$record['absent_hours']));
                  $hoursMap = array_fill(1, 4, false);
                  foreach ($hours as $hour) {
                      $hoursMap[$hour] = true;
                  }
                ?>
                <tr data-record-id="<?= (int)$record['id'] ?>">
                  <td data-label="نام دانش آموز" class="cell-name">
                    <?= htmlspecialchars((string)$record['student_name'], ENT_QUOTES, 'UTF-8') ?>
                  </td>
                  <td data-label="زنگ ها">
                    <div class="hours-badges">
                      <?php for ($hour = 1; $hour <= 4; $hour++): ?>
                        <span class="hour-badge <?= $hoursMap[$hour] ? 'is-absent' : 'is-present' ?>">زنگ <?= $hour ?></span>
                      <?php endfor; ?>
                    </div>
                  </td>
                  <td data-label="یادداشت" class="cell-notes">
                    <?= htmlspecialchars((string)($record['notes'] !== '' ? $record['notes'] : '-'), ENT_QUOTES, 'UTF-8') ?>
                  </td>
                  <td data-label="ثبت کننده" class="cell-recorder">
                    <?= htmlspecialchars((string)($recorderNames[(int)$record['recorded_by']] ?? 'مدیر نامشخص'), ENT_QUOTES, 'UTF-8') ?>
                  </td>
                  <td data-label="زمان ثبت" class="cell-date">
                    <?= htmlspecialchars((string)$record['created_at'], ENT_QUOTES, 'UTF-8') ?>
                  </td>
                  <td data-label="عملیات" class="cell-actions">
                    <button
                      type="button"
                      class="btn-action btn-edit edit-btn"
                      data-id="<?= (int)$record['id'] ?>"
                      data-name="<?= htmlspecialchars((string)$record['student_name'], ENT_QUOTES, 'UTF-8') ?>"
                      data-hours="<?= htmlspecialchars((string)$record['absent_hours'], ENT_QUOTES, 'UTF-8') ?>"
                      data-notes="<?= htmlspecialchars((string)$record['notes'], ENT_QUOTES, 'UTF-8') ?>"
                      data-date="<?= htmlspecialchars((string)$record['date'], ENT_QUOTES, 'UTF-8') ?>"
                      data-grade="<?= (int)$record['grade'] ?>"
                      data-field="<?= htmlspecialchars((string)$record['field'], ENT_QUOTES, 'UTF-8') ?>"
                    >ویرایش</button>

                    <form method="post" action="attendance_delete.php" class="delete-form" data-record-id="<?= (int)$record['id'] ?>">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />
                      <input type="hidden" name="id" value="<?= (int)$record['id'] ?>" />
                      <input type="hidden" name="date" value="<?= htmlspecialchars((string)$record['date'], ENT_QUOTES, 'UTF-8') ?>" />
                      <input type="hidden" name="grade" value="<?= (int)$record['grade'] ?>" />
                      <input type="hidden" name="field" value="<?= htmlspecialchars((string)$record['field'], ENT_QUOTES, 'UTF-8') ?>" />
                      <button type="submit" class="btn-action btn-delete">حذف</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </section>

  <div id="attendanceEditModal" class="attendance-modal" hidden>
    <div class="attendance-modal-backdrop" data-modal-close></div>
    <div class="attendance-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="attendanceEditTitle">
      <div class="attendance-modal-head">
        <h3 id="attendanceEditTitle">ویرایش رکورد غیبت</h3>
        <button type="button" class="icon-close" data-modal-close aria-label="بستن">×</button>
      </div>

      <form method="post" action="attendance_update.php" id="attendanceEditForm" class="attendance-modal-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>" />
        <input type="hidden" name="id" id="editRecordId" />
        <input type="hidden" name="date" id="editRecordDate" value="<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>" />
        <input type="hidden" name="grade" id="editRecordGrade" value="<?= $grade ?>" />
        <input type="hidden" name="field" id="editRecordField" value="<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>" />

        <label>
          نام و نام خانوادگی
          <input type="text" id="editStudentName" name="student_name" maxlength="120" required />
        </label>

        <fieldset class="hours-fieldset">
          <legend>زنگ های غیبت</legend>
          <div class="hours-grid">
            <?php for ($hour = 1; $hour <= 4; $hour++): ?>
              <label class="hour-checkbox" for="editHour<?= $hour ?>">
                <input id="editHour<?= $hour ?>" type="checkbox" name="hours[]" value="<?= $hour ?>" />
                <span>زنگ <?= $hour ?></span>
              </label>
            <?php endfor; ?>
          </div>
        </fieldset>

        <label>
          یادداشت
          <textarea id="editNotes" name="notes" rows="3" maxlength="500" placeholder="اختیاری"></textarea>
        </label>

        <div class="modal-actions">
          <button type="button" class="btn-secondary" data-modal-close>انصراف</button>
          <button type="submit" class="btn-primary">ذخیره تغییرات</button>
        </div>
      </form>
    </div>
  </div>
</main>
<?php require __DIR__ . '/partials/footer.php'; ?>
