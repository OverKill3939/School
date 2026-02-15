<?php
declare(strict_types=1);
require_once __DIR__ . '/auth/helpers.php';
require_admin();

$electionId = (int)($_GET['election_id'] ?? 0);
if ($electionId <= 0) die("شناسه انتخابات نامعتبر");

$pdo = get_db();
$election = $pdo->query("SELECT * FROM elections WHERE id = $electionId")->fetch();
if (!$election) die("انتخابات یافت نشد");

$errors = [];
$fullName = $grade = $field = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $grade    = (int)($_POST['grade'] ?? 0);
    $field    = trim($_POST['field'] ?? '');

    if (empty($fullName)) $errors[] = "نام و نام خانوادگی الزامی است";
    if (!in_array($grade, [10,11,12])) $errors[] = "پایه نامعتبر";
    if (!in_array($field, ['شبکه و نرم افزار','برق','الکترونیک'])) $errors[] = "رشته نامعتبر";

    $photoPath = null;
    if (!empty($_FILES['photo']['name'])) {
        // تابع آپلود مشابه upload_news_image
        $allowed = ['jpg','jpeg','png'];
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['photo']['error'] === 0) {
            $dir = __DIR__ . '/public/uploads/candidates/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $filename = 'cand_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $dir . $filename)) {
                $photoPath = '/uploads/candidates/' . $filename;
            } else {
                $errors[] = "آپلود عکس ناموفق";
            }
        } else {
            $errors[] = "فرمت عکس نامعتبر (jpg/png)";
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO candidates (election_id, full_name, grade, field, photo_path)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$electionId, $fullName, $grade, $field, $photoPath]);
        header("Location: election.php?candidate_added=1");
        exit;
    }
}
?>

<main class="container" style="max-width:700px;">
    <h1>افزودن کاندیدا</h1>
    <a href="election.php">← بازگشت</a>

    <?php if ($errors): ?>
    <div style="background:#fee2e2; padding:1rem; border-radius:8px; margin:1rem 0;">
        <?= implode('<br>', array_map('htmlspecialchars', $errors)) ?>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" style="margin-top:1.5rem;">
        <div class="form-group">
            <label>نام و نام خانوادگی</label>
            <input type="text" name="full_name" value="<?= htmlspecialchars($fullName) ?>" required>
        </div>

        <div class="form-group">
            <label>پایه</label>
            <select name="grade" required>
                <option value="10" <?= $grade==10?'selected':'' ?>>دهم</option>
                <option value="11" <?= $grade==11?'selected':'' ?>>یازدهم</option>
                <option value="12" <?= $grade==12?'selected':'' ?>>دوازدهم</option>
            </select>
        </div>

        <div class="form-group">
            <label>رشته</label>
            <select name="field" required>
                <option value="شبکه و نرم افزار" <?= $field=='شبکه و نرم افزار'?'selected':'' ?>>شبکه و نرم افزار</option>
                <option value="برق" <?= $field=='برق'?'selected':'' ?>>برق</option>
                <option value="الکترونیک" <?= $field=='الکترونیک'?'selected':'' ?>>الکترونیک</option>
            </select>
        </div>

        <div class="form-group">
            <label>عکس (اختیاری)</label>
            <input type="file" name="photo" accept="image/jpeg,image/png">
        </div>

        <button type="submit" class="btn btn-primary">ثبت کاندیدا</button>
    </form>
</main>