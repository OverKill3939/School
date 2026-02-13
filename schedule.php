<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_login();

$pageTitle = 'برنامه هفتگی | هنرستان دارالفنون';
$activeNav = 'schedule';
$extraScripts = ['js/schedule.js?v=' . filemtime(__DIR__ . '/js/schedule.js')];

$user = current_user();
$isAdmin = ($user['role'] ?? '') === 'admin';

$grades = [
    1 => 'پایه دهم',
    2 => 'پایه یازدهم',
    3 => 'پایه دوازدهم',
];

$fields = ['کامپیوتر', 'الکترونیک', 'برق'];
$hours = ['زنگ اول', 'زنگ دوم', 'زنگ سوم', 'زنگ چهارم'];
$days = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه'];

require __DIR__ . '/partials/header.php';
?>

<main class="schedule-page">
  <section class="schedule-shell">
    <div class="schedule-head">
      <h1>برنامه هفتگی</h1>
      <?php if ($isAdmin): ?>
        <p class="schedule-badge schedule-badge--admin">شما مدیر هستید و امکان ویرایش برنامه را دارید.</p>
      <?php else: ?>
        <p class="schedule-badge schedule-badge--user">شما فقط مجاز به مشاهده برنامه هستید.</p>
      <?php endif; ?>
    </div>

    <div class="grade-tabs" role="tablist" aria-label="انتخاب پایه">
      <?php foreach ($grades as $gradeValue => $gradeLabel): ?>
        <button
          class="grade-tab<?= $gradeValue === 1 ? ' is-active' : '' ?>"
          type="button"
          data-grade="<?= $gradeValue ?>"
          role="tab"
          aria-selected="<?= $gradeValue === 1 ? 'true' : 'false' ?>"
        >
          <?= htmlspecialchars($gradeLabel, ENT_QUOTES, 'UTF-8') ?>
        </button>
      <?php endforeach; ?>
    </div>

    <?php foreach ($grades as $gradeValue => $gradeLabel): ?>
      <section id="grade-<?= $gradeValue ?>" class="grade-content" style="<?= $gradeValue === 1 ? 'display:block;' : 'display:none;' ?>">
        <div class="field-tabs" role="tablist" aria-label="انتخاب رشته">
          <?php foreach ($fields as $fieldIndex => $fieldName): ?>
            <button
              class="field-tab<?= $fieldIndex === 0 ? ' is-active' : '' ?>"
              type="button"
              data-grade="<?= $gradeValue ?>"
              data-field-index="<?= $fieldIndex ?>"
            >
              <?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>
            </button>
          <?php endforeach; ?>
        </div>

        <?php foreach ($fields as $fieldIndex => $fieldName): ?>
          <div id="grade-<?= $gradeValue ?>-field-<?= $fieldIndex ?>" class="field-content" style="<?= $fieldIndex === 0 ? 'display:block;' : 'display:none;' ?>">
            <form
              class="schedule-grid-form"
              method="post"
              action="api/schedule_save.php"
              data-grade="<?= $gradeValue ?>"
              data-field-index="<?= $fieldIndex ?>"
              data-is-admin="<?= $isAdmin ? '1' : '0' ?>"
            >
              <input type="hidden" name="grade" value="<?= $gradeValue ?>">
              <input type="hidden" name="field" value="<?= htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') ?>">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

              <div class="schedule-table-wrap">
                <table class="schedule-table">
                  <thead>
                    <tr>
                      <th>روز</th>
                      <?php foreach ($hours as $hourLabel): ?>
                        <th><?= htmlspecialchars($hourLabel, ENT_QUOTES, 'UTF-8') ?></th>
                      <?php endforeach; ?>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($days as $dayIndex => $dayLabel): ?>
                      <tr>
                        <td class="day-cell"><?= htmlspecialchars($dayLabel, ENT_QUOTES, 'UTF-8') ?></td>
                        <?php foreach ($hours as $hourIndex => $unusedHour): ?>
                          <td>
                            <?php if ($isAdmin): ?>
                              <input
                                class="schedule-cell-input"
                                type="text"
                                data-day="<?= $dayIndex ?>"
                                data-hour="<?= $hourIndex ?>"
                                placeholder="عنوان درس"
                              >
                            <?php else: ?>
                              <div class="schedule-view-cell" data-day="<?= $dayIndex ?>" data-hour="<?= $hourIndex ?>">-</div>
                            <?php endif; ?>
                          </td>
                        <?php endforeach; ?>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <p class="schedule-form-message" aria-live="polite"></p>

              <?php if ($isAdmin): ?>
                <div class="schedule-actions">
                  <button class="schedule-save-btn" type="submit">ذخیره تغییرات</button>
                </div>
              <?php endif; ?>
            </form>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>
  </section>
</main>

<style>
  .schedule-page {
    width: min(1120px, 92vw);
    margin: 0 auto 3rem;
    animation: fadeIn 0.5s ease forwards;
    opacity: 0;
  }

  @keyframes fadeIn {
    from {
      opacity: 0;
      transform: translateY(20px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .schedule-shell {
    background: var(--card);
    border: 1px solid var(--stroke);
    border-radius: 24px;
    padding: 2rem;
    box-shadow: 0 20px 45px rgba(15, 23, 42, 0.12);
    animation: slideUp 0.6s ease 0.2s forwards;
    opacity: 0;
  }

  @keyframes slideUp {
    from {
      opacity: 0;
      transform: translateY(30px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .schedule-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 2rem;
  }

  .schedule-head h1 {
    margin: 0;
  }

  .schedule-badge {
    margin: 0;
    padding: 0.7rem 1rem;
    border-radius: 12px;
    border: 1px solid;
    font-size: 0.92rem;
  }

  .schedule-badge--admin {
    background: #d4edda;
    color: #155724;
    border-color: #c3e6cb;
  }

  .schedule-badge--user {
    background: #cfe2ff;
    color: #084298;
    border-color: #b6d4fe;
  }

  .grade-tabs {
    display: flex;
    gap: 0.75rem;
    border-bottom: 2px solid var(--stroke);
    margin-bottom: 1.5rem;
    overflow-x: auto;
    padding-bottom: 0.25rem;
    animation: fadeIn 0.5s ease 0.4s forwards;
    opacity: 0;
  }

  .grade-content {
    animation: fadeIn 0.5s ease 0.6s forwards;
    opacity: 0;
  }

  .grade-tab {
    padding: 0.85rem 1rem;
    cursor: pointer;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    color: var(--ink-soft);
    transition: all 0.2s ease;
    font: inherit;
    white-space: nowrap;
  }

  .grade-tab:hover,
  .grade-tab.is-active {
    color: var(--accent);
    border-bottom-color: var(--accent);
    font-weight: 700;
  }

  .grade-tab:hover {
    color: var(--accent);
  }

  .field-tabs {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 1.25rem;
    animation: fadeIn 0.5s ease 0.8s forwards;
    opacity: 0;
  }

  .field-content {
    animation: fadeIn 0.5s ease 1s forwards;
    opacity: 0;
  }

  .field-tab {
    padding: 0.7rem 1.2rem;
    cursor: pointer;
    background: var(--stroke);
    color: var(--ink);
    border: 1px solid transparent;
    border-radius: 10px;
    transition: all 0.2s ease;
    font: inherit;
    font-weight: 500;
  }

  .field-tab:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  }

  .field-tab.is-active {
    background: var(--accent);
    color: #fff;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  }

  .schedule-table-wrap {
    overflow-x: auto;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  }

  .schedule-table {
    width: 100%;
    border-collapse: collapse;
  }

  .schedule-table th {
    padding: 0.9rem;
    text-align: center;
    border: 1px solid var(--stroke);
    background: var(--accent);
    color: #fff;
    min-width: 120px;
  }

  .schedule-table td {
    border: 1px solid var(--stroke);
    padding: 0;
    transition: background-color 0.2s;
  }

  .schedule-table tbody tr:hover td {
    background-color: rgba(0, 0, 0, 0.02);
  }

  .schedule-table .day-cell {
    padding: 0.9rem;
    min-width: 110px;
    text-align: center;
    background: var(--card-secondary);
    font-weight: 700;
  }

  .schedule-cell-input,
  .schedule-view-cell {
    width: 100%;
    min-height: 52px;
    padding: 0.75rem;
    text-align: center;
    box-sizing: border-box;
    direction: rtl;
  }

  .schedule-cell-input {
    border: none;
    outline: none;
    background: transparent;
    font: inherit;
    border-radius: 8px;
    transition: all 0.2s ease;
  }

  .schedule-cell-input::placeholder {
    color: #9ca3af;
    font-style: italic;
  }

  .schedule-cell-input:focus {
    background-color: rgba(0, 0, 0, 0.03);
    box-shadow: inset 0 0 0 2px var(--accent);
  }

  .schedule-view-cell {
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .schedule-form-message {
    margin: 0.9rem 0 0;
    color: var(--ink-soft);
    font-size: 0.95rem;
    min-height: 1.4rem;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    animation: slideIn 0.3s ease;
  }

  .schedule-form-message.success {
    background: #dcfce7;
    border-right: 4px solid #22c55e;
    color: #166534;
  }

  .schedule-form-message.error {
    background: #fee2e2;
    border-right: 4px solid #ef4444;
    color: #991b1b;
  }

  @keyframes slideIn {
    from {
      opacity: 0;
      transform: translateY(-10px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .schedule-actions {
    margin-top: 1rem;
    display: flex;
    gap: 0.75rem;
    animation: fadeIn 0.5s ease 1.2s forwards;
    opacity: 0;
  }

  .schedule-save-btn {
    padding: 0.75rem 2rem;
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    font-weight: 700;
    font: inherit;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
  }

  .schedule-save-btn:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
  }

  .schedule-save-btn:active:not(:disabled) {
    transform: translateY(0);
  }

  .schedule-grid-form.is-loading {
    opacity: 0.75;
    pointer-events: none;
  }

  .schedule-save-btn[disabled] {
    cursor: not-allowed;
    opacity: 0.6;
  }

  @media (max-width: 768px) {
    .schedule-shell {
      padding: 1rem;
      border-radius: 16px;
    }

    .schedule-head {
      margin-bottom: 1.2rem;
    }

    .grade-tabs {
      gap: 0.5rem;
    }

    .grade-tab {
      padding: 0.75rem 1rem;
      font-size: 0.9rem;
    }

    .field-tab {
      padding: 0.6rem 1rem;
      font-size: 0.9rem;
    }

    .schedule-table th,
    .schedule-table .day-cell {
      min-width: 88px;
      font-size: 0.85rem;
      padding: 0.65rem;
    }

    .schedule-cell-input,
    .schedule-view-cell {
      min-height: 46px;
      padding: 0.55rem;
      font-size: 0.9rem;
    }

    .schedule-save-btn {
      padding: 0.65rem 1.5rem;
      font-size: 0.95rem;
    }
  }
</style>

<?php require __DIR__ . '/partials/footer.php'; ?>
