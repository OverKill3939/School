<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_admin();

$allowedGrades = [10, 11, 12];
$allowedFields = ['شبکه و نرم افزار', 'برق', 'الکترونیک'];

$pageTitle = 'مدیریت انتخابات | هنرستان دارالفنون';
$activeNav = 'election';
$extraStyles = ['css/election.css'];

$pdo = get_db();

$election = $pdo->query('SELECT * FROM elections WHERE is_active = 1 ORDER BY id DESC LIMIT 1')->fetch();
if (!$election) {
    $election = $pdo->query('SELECT * FROM elections ORDER BY id DESC LIMIT 1')->fetch();
}

if (!$election) {
    $year = persian_year_now();
    $createStmt = $pdo->prepare('INSERT INTO elections (title, year, is_active) VALUES (:title, :year, 0)');
    $createStmt->execute([
        ':title' => 'انتخابات شورای دانش آموزی',
        ':year' => $year,
    ]);
    $election = $pdo->query('SELECT * FROM elections ORDER BY id DESC LIMIT 1')->fetch();
}

$electionId = (int)($election['id'] ?? 0);
if ($electionId <= 0) {
    http_response_code(500);
    exit('خطا در بارگذاری انتخابات.');
}

$candidateCountStmt = $pdo->prepare('SELECT COUNT(*) FROM candidates WHERE election_id = :election_id');
$candidateCountStmt->execute([':election_id' => $electionId]);
$candidateCount = (int)$candidateCountStmt->fetchColumn();

$isActive = (int)($election['is_active'] ?? 0) === 1;
if ($candidateCount === 0 && $isActive) {
    $deactivateSql = "UPDATE elections SET is_active = 0, updated_at = "
        . (strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite'
            ? "datetime('now','localtime')"
            : 'NOW()')
        . ' WHERE id = :id';
    $deactivateStmt = $pdo->prepare($deactivateSql);
    $deactivateStmt->execute([':id' => $electionId]);
    $isActive = false;
}

$eligibleStmt = $pdo->prepare(
    'SELECT grade, field, eligible_count, voted_count, votes_per_student
     FROM election_eligible
     WHERE election_id = :election_id'
);
$eligibleStmt->execute([':election_id' => $electionId]);
$eligibleRows = $eligibleStmt->fetchAll();

$eligibleMap = [];
foreach ($eligibleRows as $row) {
    $grade = (int)($row['grade'] ?? 0);
    $field = (string)($row['field'] ?? '');
    if (!in_array($grade, $allowedGrades, true) || !in_array($field, $allowedFields, true)) {
        continue;
    }

    $eligibleMap[$grade . '|' . $field] = [
        'eligible_count' => max(0, (int)($row['eligible_count'] ?? 0)),
        'voted_count' => max(0, (int)($row['voted_count'] ?? 0)),
        'votes_per_student' => max(1, (int)($row['votes_per_student'] ?? 1)),
    ];
}

$candidatesStmt = $pdo->prepare(
    'SELECT id, full_name, grade, field, photo_path, votes
     FROM candidates
     WHERE election_id = :election_id
     ORDER BY field ASC, grade ASC, full_name ASC'
);
$candidatesStmt->execute([':election_id' => $electionId]);
$candidates = $candidatesStmt->fetchAll();

$statusText = $isActive ? 'فعال' : 'غیرفعال';
$statusClass = $isActive ? 'is-active' : 'is-inactive';
$displayYear = display_persian_year((int)($election['year'] ?? persian_year_now()));
$voteUrl = app_base_url() . '/vote.php';

$flashMessage = trim((string)($_GET['msg'] ?? ''));
$errorMessage = trim((string)($_GET['error'] ?? ''));

require __DIR__ . '/partials/header.php';
?>

<main class="election-page">
    <section class="election-hero-card">
        <div>
            <h1>مدیریت انتخابات شورای دانش آموزی</h1>
            <p class="subtitle">
                <?= htmlspecialchars((string)($election['title'] ?? 'انتخابات شورای دانش آموزی'), ENT_QUOTES, 'UTF-8') ?>
                - سال تحصیلی <?= $displayYear ?>
            </p>
        </div>
        <div class="status-chip <?= $statusClass ?>">
            وضعیت: <?= $statusText ?>
        </div>
    </section>

    <?php if ($flashMessage !== ''): ?>
        <div class="flash success"><?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($errorMessage !== ''): ?>
        <div class="flash error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <section class="election-card">
        <div class="section-head">
            <h2>تنظیم تعداد مجاز رأی</h2>
            <p>تعداد دانش آموز مجاز و تعداد رأی هر دانش آموز را برای هر پایه/رشته مشخص کنید.</p>
        </div>

        <form method="post" action="election-save-eligible.php" class="eligible-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="election_id" value="<?= $electionId ?>">

            <div class="table-wrap">
                <table class="eligible-table">
                    <thead>
                        <tr>
                            <th>پایه</th>
                            <th>رشته</th>
                            <th>تعداد دانش آموز مجاز</th>
                            <th>تعداد رأی هر دانش آموز</th>
                            <th>دانش آموز رأی داده</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($allowedGrades as $grade): ?>
                        <?php foreach ($allowedFields as $field): ?>
                            <?php
                                $key = $grade . '|' . $field;
                                $row = $eligibleMap[$key] ?? ['eligible_count' => 0, 'voted_count' => 0, 'votes_per_student' => 1];
                            ?>
                            <tr>
                                <td><?= $grade ?></td>
                                <td><?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <input
                                        type="number"
                                        min="0"
                                        name="eligible[<?= $grade ?>][<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>]"
                                        value="<?= (int)$row['eligible_count'] ?>"
                                        class="cell-input"
                                    >
                                </td>
                                <td>
                                    <input
                                        type="number"
                                        min="1"
                                        max="10"
                                        name="votes_per_student[<?= $grade ?>][<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>]"
                                        value="<?= (int)$row['votes_per_student'] ?>"
                                        class="cell-input"
                                    >
                                </td>
                                <td class="voted-cell"><?= (int)$row['voted_count'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <button type="submit" class="btn btn-primary">ذخیره تنظیمات</button>
        </form>
    </section>

    <section class="election-card">
        <div class="section-head with-action">
            <div>
                <h2>کاندیداها</h2>
                <p>برای فعال ماندن انتخابات، حداقل یک کاندیدا باید ثبت شده باشد.</p>
            </div>
            <a class="btn btn-primary" href="candidate-add.php?election_id=<?= $electionId ?>">افزودن کاندیدا</a>
        </div>

        <?php if ($candidateCount === 0): ?>
            <div class="empty-state">
                هیچ کاندیدایی ثبت نشده است. تا زمان افزودن کاندیدا، وضعیت انتخابات غیر فعال می‌ماند.
            </div>
        <?php else: ?>
            <div class="candidate-grid">
                <?php foreach ($candidates as $candidate): ?>
                    <article class="candidate-card">
                        <?php if (!empty($candidate['photo_path'])): ?>
                            <img
                                class="candidate-photo"
                                src="<?= htmlspecialchars((string)$candidate['photo_path'], ENT_QUOTES, 'UTF-8') ?>"
                                alt="<?= htmlspecialchars((string)$candidate['full_name'], ENT_QUOTES, 'UTF-8') ?>"
                                loading="lazy"
                            >
                        <?php else: ?>
                            <div class="candidate-photo placeholder">بدون تصویر</div>
                        <?php endif; ?>

                        <div class="candidate-body">
                            <h3><?= htmlspecialchars((string)$candidate['full_name'], ENT_QUOTES, 'UTF-8') ?></h3>
                            <p><?= htmlspecialchars(grade_label((int)$candidate['grade']), ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars((string)$candidate['field'], ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="votes">آرا: <?= number_format((int)$candidate['votes']) ?></p>

                            <div class="candidate-actions">
                                <a class="btn btn-ghost" href="candidate-edit.php?id=<?= (int)$candidate['id'] ?>">ویرایش</a>
                                <form method="post" action="candidate-delete.php" onsubmit="return confirm('کاندیدا حذف شود؟');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="id" value="<?= (int)$candidate['id'] ?>">
                                    <button type="submit" class="btn btn-danger">حذف</button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="election-card vote-link-card">
        <h2>لینک رأی گیری</h2>
        <p>این لینک را روی سیستم‌های باجه رأی گیری باز کنید.</p>

        <div class="copy-row">
            <input id="vote-url" type="text" value="<?= htmlspecialchars($voteUrl, ENT_QUOTES, 'UTF-8') ?>" readonly>
            <button type="button" id="copy-vote-url" class="btn btn-ghost">کپی لینک</button>
        </div>
    </section>

    <?php if ($isActive): ?>
        <section class="election-card danger-zone">
            <h2>پایان انتخابات</h2>
            <p>بعد از پایان، صفحه رأی گیری دیگر رأی جدید ثبت نمی‌کند.</p>
            <form method="post" action="election-end.php" onsubmit="return confirm('انتخابات پایان یابد؟');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="election_id" value="<?= $electionId ?>">
                <button type="submit" class="btn btn-danger">پایان انتخابات</button>
            </form>
        </section>
    <?php endif; ?>
</main>

<script>
(function () {
    const input = document.getElementById('vote-url');
    const button = document.getElementById('copy-vote-url');
    if (!input || !button) {
        return;
    }

    button.addEventListener('click', async function () {
        const original = button.textContent;
        try {
            await navigator.clipboard.writeText(input.value);
            button.textContent = 'کپی شد';
        } catch (error) {
            input.select();
            document.execCommand('copy');
            button.textContent = 'کپی شد';
        }

        setTimeout(function () {
            button.textContent = original;
        }, 1200);
    });
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
