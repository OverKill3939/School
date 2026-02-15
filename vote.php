<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
// بدون require_login → عمومی است

$pdo = get_db();

$election = $pdo->query("SELECT * FROM elections WHERE is_active = 1 LIMIT 1")->fetch();
if (!$election) {
    die("<h2 style='text-align:center; margin:100px 20px; color:#dc2626;'>در حال حاضر انتخابات فعالی وجود ندارد.</h2>");
}

$electionId = $election['id'];

$candStmt = $pdo->prepare("SELECT * FROM candidates WHERE election_id = ? ORDER BY field, grade, full_name");
$candStmt->execute([$electionId]);
$candidates = $candStmt->fetchAll();
?>

<main class="container" style="max-width:900px; margin:3rem auto;">
    <h1 style="text-align:center; margin-bottom:0.5rem;">رای‌گیری شورای دانش‌آموزی</h1>
    <p style="text-align:center; color:#64748b; margin-bottom:2.5rem;">
        هنرستان فنی دارالفنون شاهرود — <?= $election['title'] ?>
    </p>

    <?php if (empty($candidates)): ?>
        <div style="text-align:center; padding:3rem; background:#fef3f2; border-radius:12px;">
            هنوز کاندیدایی ثبت نشده است.
        </div>
    <?php else: ?>
        <form method="POST" action="vote-submit.php">
            <input type="hidden" name="election_id" value="<?= $electionId ?>">

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-bottom:2rem;">
                <div>
                    <label>پایه تحصیلی شما <span style="color:#ef4444;">*</span></label>
                    <select name="voter_grade" required style="width:100%; padding:0.8rem;">
                        <option value="">انتخاب کنید</option>
                        <option value="10">دهم</option>
                        <option value="11">یازدهم</option>
                        <option value="12">دوازدهم</option>
                    </select>
                </div>
                <div>
                    <label>رشته شما <span style="color:#ef4444;">*</span></label>
                    <select name="voter_field" required style="width:100%; padding:0.8rem;">
                        <option value="">انتخاب کنید</option>
                        <option value="شبکه و نرم افزار">شبکه و نرم افزار</option>
                        <option value="برق">برق</option>
                        <option value="الکترونیک">الکترونیک</option>
                    </select>
                </div>
            </div>

            <h3 style="margin:2rem 0 1rem;">کاندیدای مورد نظر خود را انتخاب کنید</h3>

            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px,1fr)); gap:1.2rem;">
                <?php foreach ($candidates as $c): ?>
                <label style="display:block; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:1rem; cursor:pointer;">
                    <input type="radio" name="candidate_id" value="<?= $c['id'] ?>" required style="margin-left:0.5rem;">
                    <div style="margin-right:2.5rem;">
                        <strong><?= htmlspecialchars($c['full_name']) ?></strong><br>
                        <small style="color:#64748b;"><?= $c['grade'] ?> — <?= $c['field'] ?></small>
                    </div>
                    <?php if ($c['photo_path']): ?>
                        <img src="<?= htmlspecialchars($c['photo_path']) ?>" alt="" style="width:80px; height:80px; object-fit:cover; border-radius:8px; margin-top:0.8rem;">
                    <?php endif; ?>
                </label>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:2.5rem; padding:1.1rem; font-size:1.15rem;">
                ثبت رای من
            </button>
        </form>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/partials/footer.php'; ?>