<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_admin();

$pageTitle = 'مدیریت انتخابات | هنرستان دارالفنون';
$activeNav = 'election';

require __DIR__ . '/partials/header.php';

$pdo = get_db();

// پیدا کردن یا ساخت انتخابات فعال
$stmt = $pdo->query("SELECT * FROM elections WHERE is_active = 1 LIMIT 1");
$election = $stmt->fetch();

if (!$election) {
    $stmt = $pdo->prepare("INSERT INTO elections (title, year, is_active) VALUES (?, ?, 1)");
    $stmt->execute(['شورای دانش آموزی ' . date('Y') . '-' . (date('Y')+1), date('Y')]);
    $election = $pdo->query("SELECT * FROM elections WHERE is_active = 1 LIMIT 1")->fetch();
}

$electionId = $election['id'];

// بارگذاری واجدین شرایط
$eligibleStmt = $pdo->prepare("SELECT grade, field, eligible_count, voted_count FROM election_eligible WHERE election_id = ?");
$eligibleStmt->execute([$electionId]);
$eligiblesRaw = $eligibleStmt->fetchAll();

$eligibles = [];
foreach ($eligiblesRaw as $row) {
    $key = $row['grade'] . '|' . $row['field'];
    $eligibles[$key] = $row;
}

// بارگذاری کاندیداها
$candStmt = $pdo->prepare("SELECT id, full_name, grade, field, photo_path, votes FROM candidates WHERE election_id = ? ORDER BY field, grade, full_name");
$candStmt->execute([$electionId]);
$candidates = $candStmt->fetchAll();
?>

<main class="container" style="max-width: 1180px; margin: 2rem auto 5rem;">

    <h1 style="margin-bottom: 0.4rem;">مدیریت انتخابات شورای دانش‌آموزی</h1>
    <p style="color: #64748b; margin-bottom: 2.5rem;">
        <?= htmlspecialchars($election['title']) ?> — سال تحصیلی <?= $election['year'] ?>–<?= $election['year']+1 ?>
    </p>

    <!-- بخش ۱: تعداد واجدین شرایط + دکمه ذخیره -->
    <section class="election-card">
        <h2 class="election-section-title">تعداد مجاز رأی‌دهی در هر پایه و رشته</h2>
        <p style="color: #6b7280; margin-bottom: 1.5rem;">
            باجه‌ها می‌توانند تا سقف این اعداد رأی ثبت کنند.
        </p>

        <form method="post" action="election-save-eligible.php">
            <input type="hidden" name="election_id" value="<?= $electionId ?>">

            <table style="width:100%; border-collapse: collapse; margin-bottom: 1.5rem;">
                <thead>
                    <tr style="background:#f1f5f9;">
                        <th style="padding:0.9rem; text-align:right;">پایه</th>
                        <th style="padding:0.9rem; text-align:right;">رشته</th>
                        <th style="padding:0.9rem; text-align:center;">تعداد مجاز</th>
                        <th style="padding:0.9rem; text-align:center;">رأی ثبت‌شده</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $grades = [10, 11, 12];
                    $fields = ['شبکه و نرم افزار', 'برق', 'الکترونیک'];
                    foreach ($grades as $g):
                        foreach ($fields as $f):
                            $key = $g . '|' . $f;
                            $data = $eligibles[$key] ?? ['eligible_count' => 0, 'voted_count' => 0];
                    ?>
                    <tr style="border-bottom:1px solid #e5e7eb;">
                        <td style="padding:0.9rem;"><?= $g ?></td>
                        <td style="padding:0.9rem;"><?= $f ?></td>
                        <td style="text-align:center;">
                            <input type="number" name="eligible[<?= $g ?>][<?= $f ?>]"
                                   value="<?= $data['eligible_count'] ?>" min="0" style="width:90px; text-align:center;">
                        </td>
                        <td style="text-align:center;"><?= $data['voted_count'] ?></td>
                    </tr>
                    <?php endforeach; endforeach; ?>
                </tbody>
            </table>

            <button type="submit" class="btn btn-primary">ذخیره تعداد مجاز</button>
        </form>
    </section>

    <!-- بخش ۲: کاندیداها + دکمه افزودن -->
    <section class="election-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.4rem;">
            <h2 class="election-section-title">کاندیداهای فعلی</h2>
            <a href="candidate-add.php?election_id=<?= $electionId ?>" class="btn btn-primary">+ افزودن کاندیدا</a>
        </div>

        <?php if (empty($candidates)): ?>
            <div style="text-align:center; padding:2rem; background:#f8fafc; border-radius:12px; color:#64748b;">
                هنوز هیچ کاندیدایی ثبت نشده است.
            </div>
        <?php else: ?>
            <div class="candidate-grid">
                <?php foreach ($candidates as $c): ?>
                <div class="candidate-card">
                    <?php if ($c['photo_path']): ?>
                        <img src="<?= htmlspecialchars($c['photo_path']) ?>" alt="" style="width:100%; height:140px; object-fit:cover;">
                    <?php endif; ?>
                    <div class="candidate-info">
                        <h3 class="candidate-name"><?= htmlspecialchars($c['full_name']) ?></h3>
                        <div class="candidate-meta">
                            پایه <?= $c['grade'] ?> — <?= $c['field'] ?>
                        </div>
                        <div class="candidate-votes">
                            آرا: <?= number_format((int)$c['votes']) ?>
                        </div>
                        <div style="margin-top:1rem; display:flex; gap:0.8rem;">
                            <a href="candidate-edit.php?id=<?= $c['id'] ?>" class="btn btn-small">ویرایش</a>
                            <form method="post" action="candidate-delete.php" style="display:inline;" onsubmit="return confirm('حذف شود؟');">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-small">حذف</button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- بخش ۳: دکمه پایان انتخابات -->
    <?php if ($election['is_active'] == 1): ?>
    <section class="election-card" style="text-align:center; margin-top:3rem;">
        <h2 class="election-section-title" style="color:#991b1b;">پایان دادن به انتخابات</h2>
        <p style="margin:1rem 0 1.8rem; color:#475569;">
            با پایان دادن، دیگر امکان ثبت رأی جدید وجود نخواهد داشت.<br>
            این عملیات غیرقابل بازگشت است.
        </p>
        <form method="POST" action="election-end.php" onsubmit="return confirm('مطمئن هستید؟');">
            <input type="hidden" name="election_id" value="<?= $electionId ?>">
            <button type="submit" class="btn btn-danger" style="padding:0.9rem 2rem; font-size:1.05rem;">
                پایان انتخابات
            </button>
        </form>
    </section>
    <?php endif; ?>

</main>
<!-- بخش لینک باجه رای‌گیری -->
<section class="election-card" style="text-align: center; padding: 2.5rem 2rem; margin-top: 3rem; background: linear-gradient(135deg, #eff6ff, #dbeafe);">
    <h2 class="election-section-title" style="margin-bottom: 1.2rem; color: var(--primary);">لینک باجه‌های رأی‌گیری</h2>
    
    <div style="
        font-size: 1.35rem;
        font-family: monospace;
        background: white;
        padding: 1.2rem 1.8rem;
        border-radius: 12px;
        box-shadow: 0 4px 14px rgba(0,0,0,0.08);
        display: inline-block;
        direction: ltr;
        margin: 1rem 0;
        word-break: break-all;
    ">
        http<?= !empty($_SERVER['HTTPS']) ? 's' : '' ?>://<?= $_SERVER['HTTP_HOST'] ?>/vote.php
    </div>

    <p style="margin-top: 1.4rem; color: #475569; font-size: 1.05rem;">
        این لینک را در تمام کامپیوترهای باجه باز کنید.<br>
        هر باجه می‌تواند تا سقف تعداد مجاز هر پایه + رشته رأی ثبت کند.
    </p>

    <p style="margin-top: 1rem; color: #dc2626; font-weight: 500; font-size: 0.95rem;">
        نکته: قبل از دادن لینک به دانش‌آموزان، حتماً تعداد واجدین را ذخیره کرده و حداقل یک کاندیدا اضافه کنید.
    </p>
</section>
<!-- QR کد لینک رای‌گیری -->
<div style="margin-top: 2rem;">
    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode('http' . (!empty($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/vote.php') ?>" 
         alt="QR کد باجه رای‌گیری" 
         style="border: 8px solid white; box-shadow: 0 6px 16px rgba(0,0,0,0.12); border-radius: 12px;">
    <p style="margin-top: 0.8rem; color: #64748b;">اسکن کنید تا مستقیم به صفحه رای‌گیری بروید</p>
</div>
<?php require __DIR__ . '/partials/footer.php'; ?>