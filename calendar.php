<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/helpers.php';
require_login();

$viewer = current_user();
$canManageEvents = (($viewer['role'] ?? '') === 'admin');

$pageTitle = 'تقویم آموزشی | هنرستان دارالفنون';
$activeNav = 'calendar';
$extraStyles = ['css/calendar.css'];
$extraScripts = ['js/calendar.js?v=' . filemtime(__DIR__ . '/js/calendar.js')];

require __DIR__ . '/partials/header.php';
?>
<main class="page">
  <header class="page-header">
    <div class="container">
      <h1>تقویم آموزشی</h1>
      <p>نمایش تعطیلات رسمی و رویدادهای ماهانه مدرسه.</p>
    </div>
  </header>

  <section class="calendar-section">
    <div class="container">
      <div class="calendar-card">
        <div class="calendar-top">
          <div class="calendar-nav">
            <button class="nav-btn" data-cal-prev aria-label="ماه قبل">‹</button>
            <div class="calendar-title">
              <h2 id="calendar-title">بهمن 1404</h2>
              <span id="calendar-subtitle">رویدادهای ماه</span>
            </div>
            <button class="nav-btn" data-cal-next aria-label="ماه بعد">›</button>
          </div>

          <div class="calendar-actions">
            <div class="calendar-filters">
              <label class="filter-chip holiday">
                <input type="checkbox" value="holiday" checked data-filter-type />
                تعطیل رسمی
              </label>
              <label class="filter-chip weekend">
                <input type="checkbox" value="weekend" checked data-filter-type />
                تعطیل هفتگی
              </label>
              <label class="filter-chip exam">
                <input type="checkbox" value="exam" checked data-filter-type />
                روز امتحان
              </label>
              <label class="filter-chip event">
                <input type="checkbox" value="event" checked data-filter-type />
                رویداد
              </label>
              <label class="filter-chip extra-holiday">
                <input type="checkbox" value="extra-holiday" checked data-filter-type />
                تعطیلی اضافه
              </label>
            </div>
            <div class="calendar-action-buttons">
              <button class="btn ghost" id="calendar-today">برو به امروز</button>
              <?php if ($canManageEvents): ?>
              <button class="btn add-event-icon-btn" id="toggle-event-panel" type="button" aria-label="&#1575;&#1601;&#1586;&#1608;&#1583;&#1606; &#1585;&#1608;&#1740;&#1583;&#1575;&#1583;" title="&#1575;&#1601;&#1586;&#1608;&#1583;&#1606; &#1585;&#1608;&#1740;&#1583;&#1575;&#1583;"><img class="add-event-icon add-event-icon-plus" src="img/icons8-plus-50.png" alt="" aria-hidden="true" /><img class="add-event-icon add-event-icon-minus" src="img/icons8-minus-50.png" alt="" aria-hidden="true" /></button>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="calendar-layout">
          <section class="calendar-board">
            <div class="calendar-grid" id="calendar-grid" aria-live="polite"></div>

            <div class="calendar-details" id="calendar-details">
              <h3>جزئیات روز انتخاب شده</h3>
              <p>برای مشاهده جزئیات، یکی از روزهای دارای رویداد را انتخاب کنید.</p>
            </div>

            <?php if ($canManageEvents): ?>
            <section class="event-panel" id="event-panel" hidden>
              <h3 id="event-panel-title">ثبت رویداد جدید</h3>
              <p class="panel-hint" id="event-panel-hint">این رویدادها در پایگاه‌داده ذخیره می‌شوند.</p>

              <form class="event-form" id="event-form" autocomplete="off">
                <div class="form-grid">
                  <div class="form-group">
                    <label for="event-year">تاریخ (شمسی)</label>
                    <div class="date-row">
                      <input id="event-year" type="number" min="1400" max="1413" placeholder="سال" required />
                      <select id="event-month" required>
                        <option value="1">فروردین</option>
                        <option value="2">اردیبهشت</option>
                        <option value="3">خرداد</option>
                        <option value="4">تیر</option>
                        <option value="5">مرداد</option>
                        <option value="6">شهریور</option>
                        <option value="7">مهر</option>
                        <option value="8">آبان</option>
                        <option value="9">آذر</option>
                        <option value="10">دی</option>
                        <option value="11">بهمن</option>
                        <option value="12">اسفند</option>
                      </select>
                      <input id="event-day" type="number" min="1" max="31" placeholder="روز" required />
                    </div>
                  </div>

                  <div class="form-group">
                    <label for="event-title">عنوان</label>
                    <input id="event-title" type="text" maxlength="60" required placeholder="عنوان رویداد" />
                  </div>

                  <div class="form-group">
                    <label for="event-type">نوع</label>
                    <select id="event-type" required>
                      <option value="exam">روز امتحان</option>
                      <option value="event">رویداد</option>
                      <option value="extra-holiday">تعطیلی اضافه</option>
                    </select>
                  </div>

                  <div class="form-group">
                    <label for="event-notes">توضیح (اختیاری)</label>
                    <textarea id="event-notes" rows="2" maxlength="120" placeholder="توضیحات کوتاه"></textarea>
                  </div>
                </div>

                <p class="form-error" id="event-error" hidden></p>

                <div class="form-actions">
                  <button class="btn" id="event-submit" type="submit">ثبت رویداد</button>
                  <button class="btn ghost" type="button" id="event-reset">پاک کردن فرم</button>
                </div>
              </form>

              <div class="event-list" id="event-list"></div>
            </section>
            <?php endif; ?>
          </section>

          <aside class="calendar-side">
            <div class="side-card">
              <h3>راهنما</h3>
              <div class="legend-item"><span class="event-dot holiday"></span> تعطیل رسمی</div>
              <div class="legend-item"><span class="event-dot weekend"></span> جمعه</div>
              <div class="legend-item"><span class="event-dot exam"></span> روز امتحان</div>
              <div class="legend-item"><span class="event-dot event"></span> رویداد</div>
              <div class="legend-item"><span class="event-dot extra-holiday"></span> تعطیلی اضافه</div>
            </div>

            <div class="side-card">
              <h3>رویدادهای این ماه</h3>
              <div id="calendar-upcoming"></div>
            </div>

            <div class="side-card">
              <h3>رویدادهای هفته انتخاب شده</h3>
              <div id="calendar-weekly"></div>
            </div>
          </aside>
        </div>
      </div>
    </div>
  </section>
</main>
<script>window.CALENDAR_CAN_MANAGE_EVENTS = <?= $canManageEvents ? 'true' : 'false' ?>; window.CALENDAR_EVENTS_API = 'api/calendar-events.php';</script>
<?php require __DIR__ . '/partials/footer.php'; ?>
