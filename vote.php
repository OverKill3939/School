<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
start_secure_session();

$allowedGrades = [10, 11, 12];
$allowedFields = ['شبکه و نرم افزار', 'برق', 'الکترونیک'];

$pageTitle = 'رأی گیری شورای دانش آموزی';
$activeNav = 'vote';
$extraStyles = ['css/vote.css'];

$voteError = trim((string)($_SESSION['vote_error'] ?? ''));
unset($_SESSION['vote_error']);

$pdo = get_db();
$election = $pdo->query('SELECT * FROM elections WHERE is_active = 1 ORDER BY id DESC LIMIT 1')->fetch();

if ($election) {
    $candidateCountStmt = $pdo->prepare('SELECT COUNT(*) FROM candidates WHERE election_id = :election_id');
    $candidateCountStmt->execute([':election_id' => (int)$election['id']]);
    if ((int)$candidateCountStmt->fetchColumn() === 0) {
        $nowExpr = strtolower((string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)) === 'sqlite'
            ? "datetime('now','localtime')"
            : 'NOW()';
        $deactivateStmt = $pdo->prepare("UPDATE elections SET is_active = 0, updated_at = {$nowExpr} WHERE id = :id");
        $deactivateStmt->execute([':id' => (int)$election['id']]);
        $election = false;
    }
}

$electionId = $election ? (int)$election['id'] : 0;
$candidates = [];
$eligibilityMap = [];

if ($electionId > 0) {
    $eligibleStmt = $pdo->prepare(
        'SELECT grade, field, eligible_count, voted_count, votes_per_student
         FROM election_eligible
         WHERE election_id = :election_id'
    );
    $eligibleStmt->execute([':election_id' => $electionId]);

    foreach ($eligibleStmt->fetchAll() as $row) {
        $grade = (int)($row['grade'] ?? 0);
        $field = (string)($row['field'] ?? '');
        if (!in_array($grade, $allowedGrades, true) || !in_array($field, $allowedFields, true)) {
            continue;
        }

        $key = $grade . '|' . $field;
        $eligibilityMap[$key] = [
            'eligible_count' => max(0, (int)($row['eligible_count'] ?? 0)),
            'voted_count' => max(0, (int)($row['voted_count'] ?? 0)),
            'votes_per_student' => max(1, (int)($row['votes_per_student'] ?? 1)),
        ];
    }

    $candidateStmt = $pdo->prepare(
        'SELECT id, full_name, grade, field, photo_path, votes
         FROM candidates
         WHERE election_id = :election_id
         ORDER BY field ASC, grade ASC, full_name ASC'
    );
    $candidateStmt->execute([':election_id' => $electionId]);
    $candidates = $candidateStmt->fetchAll();
}

require __DIR__ . '/partials/header.php';
?>

<main class="vote-page">
    <?php if (!$election): ?>
        <section class="vote-card empty">
            <h1>رأی گیری در دسترس نیست</h1>
            <p>در حال حاضر انتخابات فعالی برای رأی گیری وجود ندارد.</p>
        </section>
    <?php elseif ($candidates === []): ?>
        <section class="vote-card empty">
            <h1>رأی گیری غیرفعال شد</h1>
            <p>هیچ کاندیدایی ثبت نشده است؛ وضعیت انتخابات به صورت خودکار غیر فعال شد.</p>
        </section>
    <?php else: ?>
        <section class="vote-hero">
            <h1>رأی گیری شورای دانش آموزی</h1>
            <p><?= htmlspecialchars((string)$election['title'], ENT_QUOTES, 'UTF-8') ?> - سال <?= display_persian_year((int)$election['year']) ?></p>
        </section>

        <?php if ($voteError !== ''): ?>
            <section class="vote-card">
                <div class="vote-error-box"><?= htmlspecialchars($voteError, ENT_QUOTES, 'UTF-8') ?></div>
            </section>
        <?php endif; ?>

        <section class="vote-card">
            <form id="vote-form" method="post" action="vote-submit.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="election_id" value="<?= $electionId ?>">

                <div class="vote-meta-grid">
                    <label>
                        پایه تحصیلی
                        <select name="voter_grade" id="voter-grade" required>
                            <option value="">انتخاب کنید</option>
                            <?php foreach ($allowedGrades as $grade): ?>
                                <option value="<?= $grade ?>"><?= htmlspecialchars(grade_label($grade), ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        رشته
                        <select name="voter_field" id="voter-field" required>
                            <option value="">انتخاب کنید</option>
                            <?php foreach ($allowedFields as $field): ?>
                                <option value="<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        کد دانش آموز
                        <input type="text" name="voter_identifier" id="voter-identifier" required maxlength="32" placeholder="مثال: 10125">
                    </label>
                </div>

                <div id="vote-limit-info" class="vote-limit-info">برای مشاهده تعداد رأی مجاز، پایه و رشته را انتخاب کنید.</div>

                <div id="candidate-grid" class="candidate-grid">
                    <?php foreach ($candidates as $candidate): ?>
                        <?php
                            $candidateId = (int)($candidate['id'] ?? 0);
                            $candidateGrade = (int)($candidate['grade'] ?? 0);
                            $candidateField = (string)($candidate['field'] ?? '');
                        ?>
                        <label class="candidate-option">
                            <input type="checkbox" name="candidate_ids[]" value="<?= $candidateId ?>" class="candidate-checkbox">
                            <div class="candidate-content">
                                <?php if (!empty($candidate['photo_path'])): ?>
                                    <img
                                        src="<?= htmlspecialchars((string)$candidate['photo_path'], ENT_QUOTES, 'UTF-8') ?>"
                                        alt="<?= htmlspecialchars((string)$candidate['full_name'], ENT_QUOTES, 'UTF-8') ?>"
                                        class="candidate-image"
                                        loading="lazy"
                                    >
                                <?php else: ?>
                                    <div class="candidate-image placeholder">بدون تصویر</div>
                                <?php endif; ?>

                                <div class="candidate-info">
                                    <strong><?= htmlspecialchars((string)$candidate['full_name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <span><?= htmlspecialchars(grade_label($candidateGrade), ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars($candidateField, ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn-submit">ثبت رأی</button>
            </form>
        </section>

        <script>
            window.voteFormConfig = {
                eligibility: <?= json_encode($eligibilityMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                fields: <?= json_encode($allowedFields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                grades: <?= json_encode($allowedGrades, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
            };
        </script>
        <script src="public/js/vote-form.js"></script>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>
