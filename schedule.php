<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_login();

$pageTitle = 'برنامه هفتگی | هنرستان دارالفنون';
$activeNav = 'schedule';

// بررسی اینکه آیا کاربر ادمین است
$user = current_user();
$is_admin = ($user['role'] ?? '') === 'admin';

// بارگذاری دیتابیس
require_once __DIR__ . '/auth/db.php';
$pdo = get_db();

// ایجاد جدول اگر وجود نداشته باشد
try {
    $pdo->exec(<<<'SQL'
    CREATE TABLE IF NOT EXISTS schedules (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        grade INTEGER NOT NULL,
        field TEXT NOT NULL,
        day INTEGER NOT NULL,
        hour INTEGER NOT NULL,
        subject TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(grade, field, day, hour)
    )
    SQL);
} catch (Exception $e) {
    // جدول قبلاً وجود دارد
}

// دریافت برنامه‌ها
$schedules_data = [];
try {
    $stmt = $pdo->prepare('SELECT * FROM schedules ORDER BY grade, field, day, hour');
    $stmt->execute();
    $schedules = $stmt->fetchAll();
    
    foreach ($schedules as $schedule) {
        $key = $schedule['grade'] . '_' . $schedule['field'];
        $schedules_data[$key][$schedule['day']][$schedule['hour']] = $schedule['subject'];
    }
} catch (Exception $e) {
    $schedules_data = [];
}

require __DIR__ . '/partials/header.php';
?>

<main style="width:min(1120px,92vw);margin:0 auto 3rem;">
    <div style="background:var(--card);border:1px solid var(--stroke);border-radius:24px;padding:2rem;box-shadow:0 20px 45px rgba(15,23,42,0.12);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h1 style="margin: 0;">برنامه هفتگی</h1>
            <?php if ($is_admin): ?>
                <div style="background: #d4edda; color: #155724; padding: 0.75rem 1.5rem; border-radius: 8px; border: 1px solid #c3e6cb;">
                    ✓ شما می‌توانید برنامه را ویرایش کنید
                </div>
            <?php else: ?>
                <div style="background: #cfe2ff; color: #084298; padding: 0.75rem 1.5rem; border-radius: 8px; border: 1px solid #b6d4fe;">
                    ℹ شما فقط می‌توانید برنامه را مشاهده کنید
                </div>
            <?php endif; ?>
        </div>

        <!-- Tabs for grades -->
        <div style="margin-bottom: 2rem;">
            <div style="display: flex; gap: 1rem; border-bottom: 2px solid var(--stroke); margin-bottom: 2rem; overflow-x: auto;">
                <?php for ($g = 1; $g <= 3; $g++): ?>
                    <button class="grade-tab" onclick="switchGrade(<?php echo $g; ?>)" 
                            style="padding: 1rem; cursor: pointer; background: none; border: none; border-bottom: 3px solid transparent; color: var(--ink); transition: all 0.3s; <?php echo ($g === 1) ? 'border-bottom-color: var(--accent); color: var(--accent); font-weight: bold;' : ''; ?>"
                            data-grade="<?php echo $g; ?>">
                        <?php echo ['', 'پایه اول', 'پایه دوم', 'پایه سوم'][$g]; ?>
                    </button>
                <?php endfor; ?>
            </div>

            <!-- Grade content -->
            <?php for ($g = 1; $g <= 3; $g++): ?>
                <div id="grade-<?php echo $g; ?>" class="grade-content" style="<?php echo ($g === 1) ? 'display: block;' : 'display: none;'; ?>">
                    <!-- Field tabs -->
                    <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
                        <?php 
                        $fields = ['کامپیوتر', 'الکترونیک', 'برق'];
                        foreach ($fields as $f_idx => $field): 
                        ?>
                            <button class="field-tab" onclick="switchField(<?php echo $g; ?>, <?php echo $f_idx; ?>)" 
                                    style="padding: 0.75rem 1.5rem; cursor: pointer; background: <?php echo ($f_idx === 0) ? 'var(--accent)' : 'var(--stroke)'; ?>; color: <?php echo ($f_idx === 0) ? 'white' : 'var(--ink)'; ?>; border: none; border-radius: 8px; transition: all 0.3s; font-weight: 500;"
                                    data-grade="<?php echo $g; ?>" data-field="<?php echo $f_idx; ?>">
                                <?php echo $field; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <!-- Field content -->
                    <?php foreach ($fields as $f_idx => $field): ?>
                        <div id="grade-<?php echo $g; ?>-field-<?php echo $f_idx; ?>" class="field-content" style="<?php echo ($f_idx === 0) ? 'display: block;' : 'display: none;'; ?>">
                            <form method="POST" action="api/schedule_save.php" style="margin-top: 1.5rem;">
                                <input type="hidden" name="grade" value="<?php echo $g; ?>">
                                <input type="hidden" name="field" value="<?php echo $field; ?>">

                                <div style="overflow-x: auto;">
                                    <table style="width: 100%; border-collapse: collapse;">
                                        <thead>
                                            <tr style="background-color: var(--accent); color: white;">
                                                <th style="padding: 1rem; text-align: center; width: 12%; border: 1px solid var(--stroke);">روز</th>
                                                <?php 
                                                $hours = ['زنگ اول', 'زنگ دوم', 'زنگ سوم', 'زنگ چهارم'];
                                                foreach ($hours as $h_idx => $hour): 
                                                ?>
                                                    <th style="padding: 1rem; text-align: center; border: 1px solid var(--stroke);">
                                                        <?php echo $hour; ?>
                                                    </th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $days = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه'];
                                            $key = $g . '_' . $field;
                                            
                                            foreach ($days as $d_idx => $day): 
                                            ?>
                                                <tr>
                                                    <td style="padding: 1rem; text-align: center; background-color: var(--card-secondary); font-weight: bold; border: 1px solid var(--stroke);">
                                                        <?php echo $day; ?>
                                                    </td>
                                                    <?php foreach ($hours as $h_idx => $hour): 
                                                        $cell_value = $schedules_data[$key][$d_idx][$h_idx] ?? '';
                                                    ?>
                                                        <td style="padding: 0; border: 1px solid var(--stroke);">
                                                            <?php if ($is_admin): ?>
                                                                <input type="text" 
                                                                       name="schedule[<?php echo $g; ?>][<?php echo $field; ?>][<?php echo $d_idx; ?>][<?php echo $h_idx; ?>]"
                                                                       value="<?php echo htmlspecialchars($cell_value, ENT_QUOTES, 'UTF-8'); ?>"
                                                                       placeholder="درس"
                                                                       style="width: 100%; padding: 0.75rem; border: none; text-align: center; direction: rtl; font-size: 0.95rem; min-height: 50px; box-sizing: border-box;">
                                                            <?php else: ?>
                                                                <div style="padding: 0.75rem; text-align: center; min-height: 50px; display: flex; align-items: center; justify-content: center;">
                                                                    <?php echo htmlspecialchars($cell_value, ENT_QUOTES, 'UTF-8'); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php if ($is_admin): ?>
                                    <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                                        <button type="submit" style="padding: 0.75rem 2rem; background-color: var(--accent); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 1rem;">
                                            💾 ذخیره تغییرات
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</main>

<style>
    button:hover {
        opacity: 0.9;
    }

    .grade-tab:hover {
        color: var(--accent);
    }

    .field-tab:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    input[type="text"]:focus {
        outline: 2px solid var(--accent);
        outline-offset: -2px;
    }

    table {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    @media (max-width: 768px) {
        table {
            font-size: 0.85rem;
        }
        
        th, td {
            padding: 0.5rem !important;
        }
    }
</style>

<script>
function switchGrade(grade) {
    // مخفی کردن همه پایه‌ها
    for (let g = 1; g <= 3; g++) {
        const element = document.getElementById('grade-' + g);
        if (element) {
            element.style.display = 'none';
        }
    }
    
    // نمایش پایه انتخاب شده
    document.getElementById('grade-' + grade).style.display = 'block';
    
    // بروزرسانی تب‌ها
    document.querySelectorAll('.grade-tab').forEach(tab => {
        if (parseInt(tab.dataset.grade) === grade) {
            tab.style.borderBottomColor = 'var(--accent)';
            tab.style.color = 'var(--accent)';
            tab.style.fontWeight = 'bold';
        } else {
            tab.style.borderBottomColor = 'transparent';
            tab.style.color = 'var(--ink)';
            tab.style.fontWeight = 'normal';
        }
    });
}

function switchField(grade, field) {
    // مخفی کردن همه رشته‌ها
    for (let f = 0; f < 3; f++) {
        const element = document.getElementById('grade-' + grade + '-field-' + f);
        if (element) {
            element.style.display = 'none';
        }
    }
    
    // نمایش رشته انتخاب شده
    document.getElementById('grade-' + grade + '-field-' + field).style.display = 'block';
    
    // بروزرسانی تب‌ها
    document.querySelectorAll('.field-tab[data-grade="' + grade + '"]').forEach(tab => {
        if (parseInt(tab.dataset.field) === field) {
            tab.style.background = 'var(--accent)';
            tab.style.color = 'white';
        } else {
            tab.style.background = 'var(--stroke)';
            tab.style.color = 'var(--ink)';
        }
    });
}
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>