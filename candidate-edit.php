<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_admin();

$allowedGrades = [10, 11, 12];
$allowedFields = ['شبکه و نرم افزار', 'برق', 'الکترونیک'];

$candidateId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($candidateId <= 0) {
    header('Location: election.php?error=' . urlencode('شناسه کاندیدا نامعتبر است.'));
    exit;
}

$pdo = get_db();
$candidateStmt = $pdo->prepare('SELECT * FROM candidates WHERE id = :id LIMIT 1');
$candidateStmt->execute([':id' => $candidateId]);
$candidate = $candidateStmt->fetch();

if (!$candidate) {
    header('Location: election.php?error=' . urlencode('کاندیدا پیدا نشد.'));
    exit;
}

$pageTitle = 'ویرایش کاندیدا | انتخابات';
$activeNav = 'election';
$extraStyles = ['css/election.css'];

$formData = [
    'full_name' => trim((string)($_POST['full_name'] ?? $candidate['full_name'] ?? '')),
    'grade' => (int)($_POST['grade'] ?? $candidate['grade'] ?? 10),
    'field' => trim((string)($_POST['field'] ?? $candidate['field'] ?? $allowedFields[0])),
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $errors[] = 'نشست شما منقضی شده است. دوباره تلاش کنید.';
    }

    if ($formData['full_name'] === '' || mb_strlen($formData['full_name']) < 3) {
        $errors[] = 'نام و نام خانوادگی معتبر وارد کنید.';
    }

    if (!in_array($formData['grade'], $allowedGrades, true)) {
        $errors[] = 'پایه انتخاب شده معتبر نیست.';
    }

    if (!in_array($formData['field'], $allowedFields, true)) {
        $errors[] = 'رشته انتخاب شده معتبر نیست.';
    }

    $photoPath = (string)($candidate['photo_path'] ?? '');

    if (isset($_FILES['photo']) && (int)($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $photoError = (int)($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($photoError !== UPLOAD_ERR_OK) {
            $errors[] = 'آپلود تصویر با خطا مواجه شد.';
        } else {
            $maxBytes = 4 * 1024 * 1024;
            $size = (int)($_FILES['photo']['size'] ?? 0);
            if ($size <= 0 || $size > $maxBytes) {
                $errors[] = 'حجم تصویر باید کمتر از 4 مگابایت باشد.';
            }

            $ext = strtolower((string)pathinfo((string)($_FILES['photo']['name'] ?? ''), PATHINFO_EXTENSION));
            $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
            if (!in_array($ext, $allowedExt, true)) {
                $errors[] = 'فرمت تصویر باید jpg، png یا webp باشد.';
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file((string)($_FILES['photo']['tmp_name'] ?? ''));
            $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
            if ($mime === false || !in_array($mime, $allowedMime, true)) {
                $errors[] = 'نوع فایل تصویر معتبر نیست.';
            }

            if ($errors === []) {
                $dir = __DIR__ . '/uploads/candidates';
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                $filename = 'candidate_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $destination = $dir . '/' . $filename;

                if (!move_uploaded_file((string)$_FILES['photo']['tmp_name'], $destination)) {
                    $errors[] = 'ذخیره تصویر انجام نشد.';
                } else {
                    $oldPath = (string)($candidate['photo_path'] ?? '');
                    if ($oldPath !== '') {
                        $oldAbsolute = __DIR__ . $oldPath;
                        if (is_file($oldAbsolute)) {
                            @unlink($oldAbsolute);
                        }
                    }
                    $photoPath = '/uploads/candidates/' . $filename;
                }
            }
        }
    }

    if ($errors === []) {
        $updateStmt = $pdo->prepare(
            'UPDATE candidates
             SET full_name = :full_name,
                 grade = :grade,
                 field = :field,
                 photo_path = :photo_path
             WHERE id = :id'
        );
        $updateStmt->execute([
            ':id' => $candidateId,
            ':full_name' => $formData['full_name'],
            ':grade' => $formData['grade'],
            ':field' => $formData['field'],
            ':photo_path' => $photoPath !== '' ? $photoPath : null,
        ]);

        header('Location: election.php?msg=' . urlencode('ویرایش کاندیدا با موفقیت انجام شد.'));
        exit;
    }
}

require __DIR__ . '/partials/header.php';
?>

<main class="election-form-page">
    <section class="election-card form-card">
        <div class="section-head with-action">
            <div>
                <h1>ویرایش کاندیدا</h1>
                <p>شناسه کاندیدا: <?= $candidateId ?></p>
            </div>
            <a href="election.php" class="btn btn-ghost">بازگشت</a>
        </div>

        <?php if ($errors !== []): ?>
            <div class="flash error">
                <?= htmlspecialchars(implode(' ', $errors), ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="candidate-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="id" value="<?= $candidateId ?>">

            <label>
                نام و نام خانوادگی
                <input type="text" name="full_name" required maxlength="180" value="<?= htmlspecialchars($formData['full_name'], ENT_QUOTES, 'UTF-8') ?>">
            </label>

            <label>
                پایه
                <select name="grade" required>
                    <?php foreach ($allowedGrades as $grade): ?>
                        <option value="<?= $grade ?>" <?= $formData['grade'] === $grade ? 'selected' : '' ?>><?= htmlspecialchars(grade_label($grade), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                رشته
                <select name="field" required>
                    <?php foreach ($allowedFields as $field): ?>
                        <option value="<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>" <?= $formData['field'] === $field ? 'selected' : '' ?>>
                            <?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <?php if (!empty($candidate['photo_path'])): ?>
                <div class="current-photo-wrap">
                    <span>تصویر فعلی</span>
                    <img src="<?= htmlspecialchars((string)$candidate['photo_path'], ENT_QUOTES, 'UTF-8') ?>" alt="candidate photo" class="current-photo">
                </div>
            <?php endif; ?>

            <label>
                تصویر جدید (اختیاری)
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp">
            </label>

            <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
        </form>
    </section>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
