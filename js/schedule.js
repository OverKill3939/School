(() => {
  const gradeTabs = Array.from(document.querySelectorAll('.grade-tab'));
  const fieldTabs = Array.from(document.querySelectorAll('.field-tab'));
  const gradeSections = Array.from(document.querySelectorAll('.grade-content'));
  const forms = Array.from(document.querySelectorAll('.schedule-grid-form'));

  if (forms.length === 0) return;

  const stateByKey = new Map();

  const keyFor = (grade, field) => `${grade}|${field}`;

  const getFormState = (form) => {
    const grade = Number(form.dataset.grade || 0);
    const field = String(form.querySelector('input[name="field"]')?.value || '');
    const key = keyFor(grade, field);

    if (!stateByKey.has(key)) {
      stateByKey.set(key, {
        loaded: false,
        loadingPromise: null,
        original: new Map(),
        dirty: new Map(),
      });
    }

    return stateByKey.get(key);
  };

  const formIsAdmin = (form) => form.dataset.isAdmin === '1';

  const setFormMessage = (form, text, type = 'info') => {
    const messageEl = form.querySelector('.schedule-form-message');
    if (!messageEl) return;

    messageEl.textContent = text;
    messageEl.classList.remove('success', 'error');
    if (type === 'success') messageEl.classList.add('success');
    if (type === 'error') messageEl.classList.add('error');
  };

  const sortCellKeys = (a, b) => {
    const [dayA, hourA] = a.split(':').map(Number);
    const [dayB, hourB] = b.split(':').map(Number);
    if (dayA !== dayB) return dayA - dayB;
    return hourA - hourB;
  };

  const updateDirtyStateUI = (form) => {
    if (!formIsAdmin(form)) return;

    const state = getFormState(form);
    const saveButton = form.querySelector('.schedule-save-btn');

    if (saveButton) {
      saveButton.disabled = form.classList.contains('is-loading');
    }

    if (state.dirty.size === 0) {
      if (!form.classList.contains('is-loading')) {
        setFormMessage(form, '');
      }
      return;
    }

    if (!form.classList.contains('is-loading')) {
      setFormMessage(form, `${state.dirty.size} تغییر برای ذخیره دارید.`);
    }
  };

  const readApiError = async (response) => {
    try {
      const data = await response.json();
      if (data && typeof data.error === 'string' && data.error.trim() !== '') {
        return data.error.trim();
      }
      if (data && typeof data.message === 'string' && data.message.trim() !== '') {
        return data.message.trim();
      }
      return 'خطا در ارتباط با سرور.';
    } catch {
      return 'خطا در ارتباط با سرور.';
    }
  };

  const applyScheduleToForm = (form, scheduleMap) => {
    const state = getFormState(form);
    state.original.clear();
    state.dirty.clear();

    const isAdmin = formIsAdmin(form);
    const cells = isAdmin
      ? Array.from(form.querySelectorAll('.schedule-cell-input'))
      : Array.from(form.querySelectorAll('.schedule-view-cell'));

    cells.forEach((cell) => {
      const day = Number(cell.dataset.day || 0);
      const hour = Number(cell.dataset.hour || 0);
      const value = String(scheduleMap?.[day]?.[hour] ?? '').trim();
      const key = `${day}:${hour}`;

      if (isAdmin) {
        cell.value = value;
      } else {
        cell.textContent = value === '' ? '-' : value;
      }

      state.original.set(key, value);
    });

    updateDirtyStateUI(form);
  };

  const loadFormSchedule = async (form, { force = false } = {}) => {
    const state = getFormState(form);

    if (state.loaded && !force) return;

    if (state.loadingPromise) {
      await state.loadingPromise;
      return;
    }

    const grade = Number(form.dataset.grade || 0);
    const field = String(form.querySelector('input[name="field"]')?.value || '');

    form.classList.add('is-loading');
    setFormMessage(form, 'در حال دریافت برنامه...');
    updateDirtyStateUI(form);

    const url = `api/schedule_get.php?grade=${encodeURIComponent(grade)}&field=${encodeURIComponent(field)}`;

    state.loadingPromise = (async () => {
      const response = await fetch(url, {
        method: 'GET',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        cache: 'no-store',
      });

      if (!response.ok) {
        throw new Error(await readApiError(response));
      }

      const payload = await response.json();
      if (!payload || payload.success !== true || typeof payload.data !== 'object') {
        throw new Error('داده دریافتی معتبر نیست.');
      }

      applyScheduleToForm(form, payload.data);
      state.loaded = true;
      setFormMessage(form, '');
    })();

    try {
      await state.loadingPromise;
    } catch (error) {
      state.loaded = false;
      setFormMessage(form, error instanceof Error ? error.message : 'خطا در بارگذاری برنامه.', 'error');
    } finally {
      state.loadingPromise = null;
      form.classList.remove('is-loading');
      updateDirtyStateUI(form);
    }
  };

  const getActiveFieldIndex = (grade) => {
    const tabs = fieldTabs.filter((tab) => Number(tab.dataset.grade || 0) === grade);
    const active = tabs.find((tab) => tab.classList.contains('is-active'));
    return Number(active?.dataset.fieldIndex || 0);
  };

  const showGrade = (grade) => {
    gradeSections.forEach((section) => {
      const sectionGrade = Number(section.id.replace('grade-', ''));
      section.style.display = sectionGrade === grade ? 'block' : 'none';
    });

    gradeTabs.forEach((tab) => {
      const isActive = Number(tab.dataset.grade || 0) === grade;
      tab.classList.toggle('is-active', isActive);
      tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });
  };

  const showField = (grade, fieldIndex) => {
    const panels = Array.from(document.querySelectorAll(`#grade-${grade} .field-content`));
    panels.forEach((panel, idx) => {
      panel.style.display = idx === fieldIndex ? 'block' : 'none';
    });

    fieldTabs
      .filter((tab) => Number(tab.dataset.grade || 0) === grade)
      .forEach((tab) => {
        const isActive = Number(tab.dataset.fieldIndex || 0) === fieldIndex;
        tab.classList.toggle('is-active', isActive);
      });

    const form = document.querySelector(`#grade-${grade}-field-${fieldIndex} .schedule-grid-form`);
    if (form) {
      loadFormSchedule(form).catch(() => {
        // handled in loadFormSchedule
      });
    }
  };

  const switchGrade = (grade) => {
    showGrade(grade);
    showField(grade, getActiveFieldIndex(grade));
  };

  gradeTabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      const grade = Number(tab.dataset.grade || 0);
      if (grade > 0) switchGrade(grade);
    });
  });

  fieldTabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      const grade = Number(tab.dataset.grade || 0);
      const fieldIndex = Number(tab.dataset.fieldIndex || 0);
      if (grade > 0) showField(grade, fieldIndex);
    });
  });

  forms.forEach((form) => {
    if (!formIsAdmin(form)) return;

    const inputs = Array.from(form.querySelectorAll('.schedule-cell-input'));

    inputs.forEach((input) => {
      input.addEventListener('input', () => {
        const state = getFormState(form);
        const day = Number(input.dataset.day || 0);
        const hour = Number(input.dataset.hour || 0);
        const key = `${day}:${hour}`;

        const currentValue = input.value.trim();
        const originalValue = state.original.get(key) ?? '';

        if (currentValue === originalValue) {
          state.dirty.delete(key);
        } else {
          state.dirty.set(key, currentValue);
        }

        updateDirtyStateUI(form);
      });
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();

      const state = getFormState(form);
      if (state.loadingPromise) return;

      if (state.dirty.size === 0) {
        setFormMessage(form, 'تغییری برای ذخیره وجود ندارد.');
        return;
      }

      const grade = Number(form.dataset.grade || 0);
      const field = String(form.querySelector('input[name="field"]')?.value || '');
      const csrfToken = String(form.querySelector('input[name="csrf_token"]')?.value || '');

      const changes = Array.from(state.dirty.entries())
        .sort((a, b) => sortCellKeys(a[0], b[0]))
        .map(([key, subject]) => {
          const [day, hour] = key.split(':').map(Number);
          return { day, hour, subject };
        });

      const body = new FormData();
      body.append('grade', String(grade));
      body.append('field', field);
      body.append('csrf_token', csrfToken);
      body.append('changes_json', JSON.stringify(changes));

      form.classList.add('is-loading');
      setFormMessage(form, 'در حال ذخیره تغییرات...');
      updateDirtyStateUI(form);

      try {
        const response = await fetch('api/schedule_save.php', {
          method: 'POST',
          body,
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
        });

        const payload = await response.json().catch(() => null);
        if (!response.ok || !payload || payload.success !== true) {
          throw new Error(payload?.error || payload?.message || 'خطا در ذخیره برنامه.');
        }

        const changed = Number(payload.changed ?? changes.length);
        setFormMessage(form, `ذخیره انجام شد. تعداد تغییرات: ${changed}`, 'success');
        await loadFormSchedule(form, { force: true });
      } catch (error) {
        setFormMessage(form, error instanceof Error ? error.message : 'خطا در ذخیره برنامه.', 'error');
      } finally {
        form.classList.remove('is-loading');
        updateDirtyStateUI(form);
      }
    });
  });

  switchGrade(1);
})();
