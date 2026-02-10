const calendarGrid = document.getElementById('calendar-grid');
const calendarTitle = document.getElementById('calendar-title');
const calendarSubtitle = document.getElementById('calendar-subtitle');
const calendarDetails = document.getElementById('calendar-details');
const calendarUpcoming = document.getElementById('calendar-upcoming');
const calendarWeekly = document.getElementById('calendar-weekly');
const calPrev = document.querySelector('[data-cal-prev]');
const calNext = document.querySelector('[data-cal-next]');
const todayButton = document.getElementById('calendar-today');
const filterInputs = document.querySelectorAll('[data-filter-type]');

const toggleEventPanel = document.getElementById('toggle-event-panel');
const eventPanel = document.getElementById('event-panel');
const eventForm = document.getElementById('event-form');
const eventList = document.getElementById('event-list');
const eventYearInput = document.getElementById('event-year');
const eventMonthInput = document.getElementById('event-month');
const eventDayInput = document.getElementById('event-day');
const eventTitleInput = document.getElementById('event-title');
const eventTypeInput = document.getElementById('event-type');
const eventNotesInput = document.getElementById('event-notes');
const eventResetButton = document.getElementById('event-reset');
const eventError = document.getElementById('event-error');

const monthNames = [
  'فروردین',
  'اردیبهشت',
  'خرداد',
  'تیر',
  'مرداد',
  'شهریور',
  'مهر',
  'آبان',
  'آذر',
  'دی',
  'بهمن',
  'اسفند'
];

const dayLabels = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

const typeLabels = {
  holiday: 'تعطیل رسمی',
  weekend: 'جمعه',
  exam: 'روز امتحان',
  event: 'رویداد',
  'extra-holiday': 'تعطیلی اضافه'
};

const typeOrder = {
  holiday: 1,
  weekend: 2,
  exam: 3,
  event: 4,
  'extra-holiday': 5
};

const customTypes = new Set(['exam', 'event', 'extra-holiday']);

const escapeHtml = (value) =>
  String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

const formatEventTitle = (item) => escapeHtml(item.title);

const div = (a, b) => Math.floor(a / b);

const jalCal = (jy) => {
  const breaks = [
    -61, 9, 38, 199, 426, 686, 756, 818, 1111,
    1181, 1210, 1635, 2060, 2097, 2192, 2262,
    2324, 2394, 2456, 3178
  ];

  let gy = jy + 621;
  let leapJ = -14;
  let jp = breaks[0];
  let jump = 0;

  if (jy < breaks[0] || jy >= breaks[breaks.length - 1]) {
    return { leap: 0, gy, march: 0 };
  }

  for (let i = 1; i < breaks.length; i += 1) {
    const jm = breaks[i];
    jump = jm - jp;
    if (jy < jm) break;
    leapJ += div(jump, 33) * 8 + div(jump % 33, 4);
    jp = jm;
  }

  let n = jy - jp;
  leapJ += div(n, 33) * 8 + div((n % 33) + 3, 4);
  if (jump % 33 === 4 && jump - n === 4) leapJ += 1;

  const leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150;
  const march = 20 + leapJ - leapG;

  if (jump - n < 6) n = n - jump + div(jump + 4, 33) * 33;
  let leap = ((n + 1) % 33 - 1) % 4;
  if (leap === -1) leap = 4;

  return { leap, gy, march };
};

const g2d = (gy, gm, gd) => {
  let d = div((gy + div(gm - 8, 6) + 100100) * 1461, 4)
    + div(153 * ((gm + 9) % 12) + 2, 5)
    + gd - 34840408;
  d = d - div(div(gy + 100100 + div(gm - 8, 6), 100) * 3, 4) + 752;
  return d;
};

const d2g = (jdn) => {
  let j = 4 * jdn + 139361631;
  j = j + div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
  const i = div(j % 1461, 4) * 5 + 308;
  const gd = div(i % 153, 5) + 1;
  const gm = div(i, 153) % 12 + 1;
  const gy = div(j, 1461) - 100100 + div(8 - gm, 6);
  return { gy, gm, gd };
};

const j2d = (jy, jm, jd) => {
  const r = jalCal(jy);
  return g2d(r.gy, 3, r.march) + (jm - 1) * 31 - div(jm, 7) * (jm - 7) + jd - 1;
};

const d2j = (jdn) => {
  const g = d2g(jdn);
  let jy = g.gy - 621;
  const r = jalCal(jy);
  const jdn1f = g2d(g.gy, 3, r.march);
  let jd, jm;
  let k = jdn - jdn1f;
  if (k >= 0) {
    if (k <= 185) {
      jm = 1 + div(k, 31);
      jd = (k % 31) + 1;
      return { jy, jm, jd };
    }
    k -= 186;
  } else {
    jy -= 1;
    k += 179;
    if (r.leap === 1) k += 1;
  }
  jm = 7 + div(k, 30);
  jd = (k % 30) + 1;
  return { jy, jm, jd };
};

const toJalali = (gy, gm, gd) => d2j(g2d(gy, gm, gd));
const toGregorian = (jy, jm, jd) => d2g(j2d(jy, jm, jd));

const isLeapJalaaliYear = (jy) => jalCal(jy).leap === 0;
const jalaaliMonthLength = (jy, jm) => {
  if (jm <= 6) return 31;
  if (jm <= 11) return 30;
  return isLeapJalaaliYear(jy) ? 30 : 29;
};

const getMonthStartWeekday = (jy, jm) => {
  const start = getFarvardin1Gregorian(jy);
  let offset = 0;
  for (let m = 1; m < jm; m += 1) {
    offset += jalaaliMonthLength(jy, m);
  }
  const date = new Date(Date.UTC(start.gy, start.gm - 1, start.gd));
  date.setUTCDate(date.getUTCDate() + offset);
  const dow = date.getUTCDay();
  return (dow + 1) % 7;
};

const digitMap = {
  '\u06F0': '0', '\u06F1': '1', '\u06F2': '2', '\u06F3': '3', '\u06F4': '4',
  '\u06F5': '5', '\u06F6': '6', '\u06F7': '7', '\u06F8': '8', '\u06F9': '9',
  '\u0660': '0', '\u0661': '1', '\u0662': '2', '\u0663': '3', '\u0664': '4',
  '\u0665': '5', '\u0666': '6', '\u0667': '7', '\u0668': '8', '\u0669': '9'
};
const toLatinNumber = (value) =>
  Number(
    String(value)
      .replace(/[\u06F0-\u06F9\u0660-\u0669]/g, (digit) => digitMap[digit] ?? digit)
      .replace(/[^\d]/g, '')
  );

const persianFormatter = new Intl.DateTimeFormat('fa-IR-u-ca-persian-nu-latn', {
  timeZone: 'Asia/Tehran',
  year: 'numeric',
  month: 'numeric',
  day: 'numeric'
});

const getPersianParts = (date) => {
  const parts = persianFormatter.formatToParts(date);
  const map = {};
  parts.forEach((part) => {
    if (part.type !== 'literal') {
      map[part.type] = toLatinNumber(part.value);
    }
  });
  return { jy: map.year, jm: map.month, jd: map.day };
};

const getIranToday = () => getPersianParts(new Date());

const getFarvardin1Gregorian = (jy) => {
  const gy = jy + 621;
  for (let day = 19; day <= 23; day += 1) {
    const date = new Date(Date.UTC(gy, 2, day));
    const parts = getPersianParts(date);
    if (parts.jy === jy && parts.jm === 1 && parts.jd === 1) {
      return { gy, gm: 3, gd: day };
    }
  }
  return { gy, gm: 3, gd: 21 };
};

let today = getIranToday();
let currentYear = today.jy;
let currentMonth = today.jm;
let selectedDay = null;
let forcedDay = null;
let activeTypes = new Set(['holiday', 'weekend', 'exam', 'event', 'extra-holiday']);

const syncFilterChips = () => {
  filterInputs.forEach((input) => {
    const label = input.closest('.filter-chip');
    if (!label) return;
    label.classList.toggle('active', input.checked);
  });
};

const HOLIDAY_YEARS = new Set([1404, 1405, 1406, 1407, 1408, 1409, 1410, 1411, 1412, 1413]);
const holidayYearCache = new Map();

const stripDatePrefix = (text) => {
  if (!text) return '';
  const raw = String(text).trim();
  const parts = raw.split(/\s+/);
  if (parts.length < 2) return raw;
  if (!/^\d+$/.test(parts[0])) return raw;
  if (monthNames.includes(parts[1])) {
    return parts.slice(2).join(' ').trim();
  }
  return parts.slice(1).join(' ').trim();
};

const loadHolidayYear = async (year) => {
  if (holidayYearCache.has(year)) return holidayYearCache.get(year);
  if (!HOLIDAY_YEARS.has(year)) {
    holidayYearCache.set(year, null);
    return null;
  }

  try {
    const response = await fetch(`data/events-${year}.json`, { cache: 'no-store' });
    if (!response.ok) {
      holidayYearCache.set(year, null);
      return null;
    }
    const data = await response.json();
    if (!Array.isArray(data)) {
      holidayYearCache.set(year, null);
      return null;
    }
    holidayYearCache.set(year, data);
    return data;
  } catch {
    holidayYearCache.set(year, null);
    return null;
  }
};

const getMonthHolidays = async (year, month) => {
  const data = await loadHolidayYear(year);
  if (!data) {
    return { events: [], status: 'missing' };
  }
  const mm = String(month).padStart(2, '0');
  const monthEvents = data
    .filter((item) => item.isHoliday && item.jDate?.startsWith(`${year}/${mm}/`))
    .map((item) => ({
      day: Number(item.jDay),
      title: stripDatePrefix(item.text) || item.text || 'تعطیل رسمی',
      type: 'holiday',
      time: 'تمام روز'
    }))
    .sort((a, b) => a.day - b.day);
  return { events: monthEvents, status: 'ok' };
};

const CUSTOM_EVENTS_KEY = 'calendar-custom-events-v1';

const normalizeCustomEvent = (item) => ({
  id: String(item.id ?? ''),
  year: Number(item.year),
  month: Number(item.month),
  day: Number(item.day),
  title: String(item.title ?? ''),
  type: String(item.type ?? 'event'),
  notes: String(item.notes ?? ''),
  time: 'تمام روز'
});

const loadCustomEvents = () => {
  try {
    const raw = localStorage.getItem(CUSTOM_EVENTS_KEY);
    if (!raw) return [];
    const data = JSON.parse(raw);
    if (!Array.isArray(data)) return [];
    return data.map(normalizeCustomEvent).filter((item) => item.id && customTypes.has(item.type));
  } catch {
    return [];
  }
};

const saveCustomEvents = (items) => {
  try {
    localStorage.setItem(CUSTOM_EVENTS_KEY, JSON.stringify(items));
  } catch {
    return;
  }
};

const getCustomEventsForMonth = (year, month) =>
  loadCustomEvents().filter((item) => item.year === year && item.month === month);

const addCustomEvent = (item) => {
  const current = loadCustomEvents();
  current.push(item);
  saveCustomEvents(current);
};

const removeCustomEvent = (id) => {
  const current = loadCustomEvents().filter((item) => item.id !== id);
  saveCustomEvents(current);
};

const setFormDefaults = (day = today.jd) => {
  if (!eventYearInput || !eventMonthInput || !eventDayInput) return;
  eventYearInput.value = currentYear;
  eventMonthInput.value = String(currentMonth);
  eventDayInput.value = day;
  if (eventTypeInput && !eventTypeInput.value) {
    eventTypeInput.value = 'event';
  }
};

const showFormError = (message) => {
  if (!eventError) return;
  if (message) {
    eventError.textContent = message;
    eventError.hidden = false;
  } else {
    eventError.textContent = '';
    eventError.hidden = true;
  }
};

const renderCustomEventsList = (year, month) => {
  if (!eventList) return;
  const events = getCustomEventsForMonth(year, month).sort((a, b) => a.day - b.day);
  if (events.length === 0) {
    eventList.innerHTML = '<p>رویدادی برای این ماه ثبت نشده است.</p>';
    return;
  }

  const list = document.createElement('div');
  list.className = 'event-items';
  events.forEach((item) => {
    const row = document.createElement('div');
    row.className = 'event-item';

    const meta = document.createElement('div');
    meta.className = 'event-meta';

    const title = document.createElement('strong');
    title.textContent = item.title;

    const dateText = document.createElement('span');
    dateText.textContent = `${item.day} ${monthNames[month - 1]} ${year}`;

    const badge = document.createElement('span');
    badge.className = `badge ${item.type}`;
    badge.textContent = typeLabels[item.type] || item.type;

    meta.appendChild(title);
    meta.appendChild(dateText);
    meta.appendChild(badge);

    const delButton = document.createElement('button');
    delButton.type = 'button';
    delButton.className = 'btn ghost small';
    delButton.textContent = 'حذف';
    delButton.setAttribute('data-event-delete', item.id);

    row.appendChild(meta);
    row.appendChild(delButton);
    list.appendChild(row);
  });

  eventList.innerHTML = '';
  eventList.appendChild(list);
};

const renderUpcomingList = (monthName, monthEvents) => {
  if (!calendarUpcoming) return;
  if (monthEvents.length === 0) {
    calendarUpcoming.innerHTML = '<p>رویدادی برای این ماه ثبت نشده است.</p>';
    return;
  }
  const list = document.createElement('div');
  list.className = 'calendar-upcoming-list';
  monthEvents.forEach((item) => {
    const row = document.createElement('div');
    row.className = 'calendar-upcoming-item';
    row.innerHTML = `<strong>${formatEventTitle(item)}</strong><span>${item.day} ${monthName} · ${item.time}</span>`;
    list.appendChild(row);
  });
  calendarUpcoming.innerHTML = '';
  calendarUpcoming.appendChild(list);
};

const renderWeeklyList = (monthName, eventsByDay, focusDay, start, days) => {
  if (!calendarWeekly) return;
  if (!focusDay) {
    calendarWeekly.innerHTML = '<p>برای مشاهده رویدادهای هفته، یک روز را انتخاب کنید.</p>';
    return;
  }
  const weekIndex = Math.floor((start + focusDay - 1) / 7);
  const weekStartDay = Math.max(1, weekIndex * 7 - start + 1);
  const weekEndDay = Math.min(days, weekStartDay + 6);

  const items = [];
  for (let day = weekStartDay; day <= weekEndDay; day += 1) {
    const dayEvents = eventsByDay[day] || [];
    dayEvents.forEach((eventItem) => {
      items.push({ day, ...eventItem });
    });
  }

  if (items.length === 0) {
    calendarWeekly.innerHTML = '<p>رویدادی در این هفته ثبت نشده است.</p>';
    return;
  }

  const list = document.createElement('div');
  list.className = 'calendar-weekly-list';
  items.forEach((item) => {
    const row = document.createElement('div');
    row.className = 'calendar-weekly-item';
    row.innerHTML = `<strong>${formatEventTitle(item)}</strong><span>${item.day} ${monthName} · ${item.time}</span>`;
    list.appendChild(row);
  });
  calendarWeekly.innerHTML = '';
  calendarWeekly.appendChild(list);
};

const updateSelectedDay = (day, monthName, eventsByDay, start, days) => {
  if (!calendarDetails) return;
  selectedDay = day;
  const dayEvents = eventsByDay[day] || [];
  document.querySelectorAll('.calendar-day').forEach((d) => d.classList.remove('selected'));
  const selectedCell = Array.from(document.querySelectorAll('.calendar-day')).find((cell) => {
    const number = cell.querySelector('.day-number');
    return number && Number(number.textContent) === day;
  });
  if (selectedCell) selectedCell.classList.add('selected');

  if (eventDayInput && eventMonthInput && eventYearInput) {
    eventYearInput.value = currentYear;
    eventMonthInput.value = String(currentMonth);
    eventDayInput.value = day;
  }

  if (dayEvents.length === 0) {
    calendarDetails.innerHTML = `<h3>روز ${day} ${monthName}</h3><p>برای این روز رویدادی ثبت نشده است.</p>`;
  } else {
    const items = dayEvents
      .map((item) => {
        const notes = item.notes ? `<div class="event-note">${escapeHtml(item.notes)}</div>` : '';
        return `<li><div class="event-text"><div class="event-title"><span class="event-dot ${item.type}"></span><span class="event-title-text">${formatEventTitle(item)}</span><span class="event-time"> - ${item.time}</span></div>${notes}</div></li>`;
      })
      .join('');
    calendarDetails.innerHTML = `<h3>روز ${day} ${monthName}</h3><ul>${items}</ul>`;
  }

  renderWeeklyList(monthName, eventsByDay, day, start, days);
};

const buildCalendar = async () => {
  if (!calendarGrid || !calendarTitle || !calendarDetails) return;
  calendarGrid.innerHTML = '';
  today = getIranToday();

  dayLabels.forEach((label) => {
    const cell = document.createElement('div');
    cell.className = 'calendar-label';
    cell.textContent = label;
    calendarGrid.appendChild(cell);
  });

  const monthName = `${monthNames[currentMonth - 1]} ${currentYear}`;
  const days = jalaaliMonthLength(currentYear, currentMonth);
  const start = getMonthStartWeekday(currentYear, currentMonth);
  calendarTitle.textContent = monthName;
  if (calendarSubtitle) calendarSubtitle.textContent = 'رویدادهای ماه';

  for (let i = 0; i < start; i += 1) {
    const emptyCell = document.createElement('div');
    emptyCell.className = 'calendar-day empty';
    emptyCell.setAttribute('aria-hidden', 'true');
    calendarGrid.appendChild(emptyCell);
  }

  for (let day = 1; day <= days; day += 1) {
    const cell = document.createElement('button');
    cell.type = 'button';
    cell.className = 'calendar-day';

    if (currentYear === today.jy && currentMonth === today.jm && day === today.jd) {
      cell.classList.add('today');
    }

    const dayNumber = document.createElement('span');
    dayNumber.className = 'day-number';
    dayNumber.textContent = day;
    cell.appendChild(dayNumber);

    cell.addEventListener('click', () => {
      updateSelectedDay(day, monthName, {}, start, days);
    });

    calendarGrid.appendChild(cell);
  }

  calendarDetails.innerHTML = '<h3>جزئیات روز انتخاب شده</h3><p>در حال بارگذاری رویدادها...</p>';
  if (calendarUpcoming) calendarUpcoming.innerHTML = '<p>در حال بارگذاری رویدادها...</p>';
  if (calendarWeekly) calendarWeekly.innerHTML = '<p>در حال بارگذاری رویدادها...</p>';

  const monthData = await getMonthHolidays(currentYear, currentMonth);
  const holidayEvents = monthData.events;

  if (calendarSubtitle && monthData.status === 'missing') {
    calendarSubtitle.textContent = 'فایل تعطیلات رسمی برای این سال پیدا نشد.';
  }

  const weekendEvents = [];
  for (let day = 1; day <= days; day += 1) {
    const dayOfWeek = (start + day - 1) % 7;
    if (dayOfWeek === 6) {
      weekendEvents.push({
        day,
        title: 'تعطیل هفتگی (جمعه)',
        type: 'weekend',
        time: 'تمام روز'
      });
    }
  }

  const customEvents = getCustomEventsForMonth(currentYear, currentMonth).map((item) => ({
    day: item.day,
    title: item.title,
    type: item.type,
    time: item.time || 'تمام روز',
    notes: item.notes || ''
  }));

  const filteredCustomEvents = customEvents.filter((item) => activeTypes.has(item.type));

  const eventsByDay = {};
  const pushEvent = (item) => {
    if (!eventsByDay[item.day]) eventsByDay[item.day] = [];
    eventsByDay[item.day].push(item);
  };

  if (activeTypes.has('holiday')) holidayEvents.forEach(pushEvent);
  if (activeTypes.has('weekend')) weekendEvents.forEach(pushEvent);
  filteredCustomEvents.forEach(pushEvent);

  const filtered = [];
  if (activeTypes.has('holiday')) filtered.push(...holidayEvents);
  if (activeTypes.has('weekend')) filtered.push(...weekendEvents);
  filtered.push(...filteredCustomEvents);
  filtered.sort((a, b) => {
    if (a.day !== b.day) return a.day - b.day;
    return (typeOrder[a.type] || 99) - (typeOrder[b.type] || 99);
  });

  document.querySelectorAll('.calendar-day').forEach((cell) => {
    const number = cell.querySelector('.day-number');
    if (!number) return;
    const day = Number(number.textContent);
    const dayEvents = eventsByDay[day] || [];

    if (dayEvents.length > 0) {
      cell.classList.add('has-event');
      if (dayEvents.some((item) => item.type === 'holiday')) {
        cell.classList.add('holiday');
      }
      if (dayEvents.some((item) => item.type === 'weekend')) {
        cell.classList.add('weekend');
      }
      if (dayEvents.some((item) => item.type === 'exam')) {
        cell.classList.add('exam');
      }
      if (dayEvents.some((item) => item.type === 'event')) {
        cell.classList.add('event');
      }
      if (dayEvents.some((item) => item.type === 'extra-holiday')) {
        cell.classList.add('extra-holiday');
      }
      const dotsWrap = document.createElement('div');
      dotsWrap.className = 'event-dots';
      dayEvents.slice(0, 3).forEach((eventItem) => {
        const dot = document.createElement('span');
        dot.className = `event-dot ${eventItem.type}`;
        dotsWrap.appendChild(dot);
      });
      cell.appendChild(dotsWrap);
    }

    cell.addEventListener('click', () => {
      updateSelectedDay(day, monthName, eventsByDay, start, days);
    });
  });

  renderUpcomingList(monthName, filtered);
  renderCustomEventsList(currentYear, currentMonth);

  const defaultDay = forcedDay ?? (currentYear === today.jy && currentMonth === today.jm ? today.jd : (filtered[0]?.day ?? null));
  forcedDay = null;

  if (defaultDay && defaultDay <= days) {
    updateSelectedDay(defaultDay, monthName, eventsByDay, start, days);
  } else {
    calendarDetails.innerHTML = '<h3>جزئیات روز انتخاب شده</h3><p>در این ماه رویدادی ثبت نشده است.</p>';
    renderWeeklyList(monthName, eventsByDay, null, start, days);
  }

  setFormDefaults(selectedDay || today.jd);
};

if (calPrev && calNext) {
  calPrev.addEventListener('click', () => {
    currentMonth -= 1;
    if (currentMonth < 1) {
      currentMonth = 12;
      currentYear -= 1;
    }
    buildCalendar();
  });
  calNext.addEventListener('click', () => {
    currentMonth += 1;
    if (currentMonth > 12) {
      currentMonth = 1;
      currentYear += 1;
    }
    buildCalendar();
  });
}

if (todayButton) {
  todayButton.addEventListener('click', () => {
    today = getIranToday();
    currentYear = today.jy;
    currentMonth = today.jm;
    forcedDay = today.jd;
    buildCalendar();
  });
}

if (filterInputs.length > 0) {
  syncFilterChips();
  filterInputs.forEach((input) => {
    input.addEventListener('change', () => {
      activeTypes = new Set(
        Array.from(filterInputs)
          .filter((item) => item.checked)
          .map((item) => item.value)
      );
      syncFilterChips();
      forcedDay = selectedDay;
      buildCalendar();
    });
  });
}

if (toggleEventPanel && eventPanel) {
  toggleEventPanel.addEventListener('click', () => {
    eventPanel.hidden = !eventPanel.hidden;
    toggleEventPanel.textContent = eventPanel.hidden ? 'افزودن رویداد' : 'بستن فرم';
    if (!eventPanel.hidden) {
      setFormDefaults(selectedDay || today.jd);
      if (eventTitleInput) eventTitleInput.focus();
    }
  });
}

if (eventForm) {
  eventForm.addEventListener('submit', (event) => {
    event.preventDefault();
    if (!eventYearInput || !eventMonthInput || !eventDayInput || !eventTitleInput || !eventTypeInput) return;

    const year = Number(eventYearInput.value);
    const month = Number(eventMonthInput.value);
    const day = Number(eventDayInput.value);
    const title = eventTitleInput.value.trim();
    const type = eventTypeInput.value;
    const notes = eventNotesInput ? eventNotesInput.value.trim() : '';

    if (!year || year < 1300) {
      showFormError('سال وارد شده معتبر نیست.');
      return;
    }
    if (!month || month < 1 || month > 12) {
      showFormError('ماه وارد شده معتبر نیست.');
      return;
    }
    const maxDay = jalaaliMonthLength(year, month);
    if (!day || day < 1 || day > maxDay) {
      showFormError(`روز باید بین 1 تا ${maxDay} باشد.`);
      return;
    }
    if (!title) {
      showFormError('عنوان رویداد را وارد کنید.');
      return;
    }
    if (!customTypes.has(type)) {
      showFormError('نوع رویداد معتبر نیست.');
      return;
    }

    const newEvent = {
      id: `evt_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 7)}`,
      year,
      month,
      day,
      title,
      type,
      notes,
      time: 'تمام روز'
    };

    addCustomEvent(newEvent);
    showFormError('');
    eventForm.reset();
    setFormDefaults(day);
    buildCalendar();
  });
}

if (eventResetButton) {
  eventResetButton.addEventListener('click', () => {
    if (eventForm) eventForm.reset();
    showFormError('');
    setFormDefaults(selectedDay || today.jd);
  });
}

if (eventList) {
  eventList.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-event-delete]');
    if (!button) return;
    const id = button.getAttribute('data-event-delete');
    if (!id) return;
    removeCustomEvent(id);
    buildCalendar();
  });
}

buildCalendar();
