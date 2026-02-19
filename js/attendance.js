(() => {
  const page = document.querySelector('main.attendance-page');
  if (!page) {
    return;
  }

  const rowsContainer = document.getElementById('absentRows');
  const addRowButton = document.getElementById('addStudentRow');

  const createRowMarkup = (rowIndex) => `
    <div class="absent-row" data-row-index="${rowIndex}">
      <div class="row-grid">
        <label>
          نام و نام خانوادگی
          <input type="text" name="students[${rowIndex}][name]" maxlength="120" required placeholder="مثال: علی رضایی" />
        </label>

        <fieldset class="hours-fieldset">
          <legend>زنگ های غیبت</legend>
          <div class="hours-grid">
            ${[1, 2, 3, 4]
              .map(
                (hour) => `
                  <label class="hour-checkbox" for="create_h_${rowIndex}_${hour}">
                    <input id="create_h_${rowIndex}_${hour}" type="checkbox" name="students[${rowIndex}][hours][]" value="${hour}" />
                    <span>زنگ ${hour}</span>
                  </label>
                `
              )
              .join('')}
          </div>
        </fieldset>

        <label>
          یادداشت
          <input type="text" name="students[${rowIndex}][notes]" maxlength="500" placeholder="اختیاری" />
        </label>

        <div class="row-actions">
          <button type="button" class="btn-danger remove-row" aria-label="حذف این ردیف">حذف</button>
        </div>
      </div>
    </div>
  `;

  const nextRowIndex = () => {
    if (!rowsContainer) {
      return 0;
    }

    const rows = Array.from(rowsContainer.querySelectorAll('.absent-row[data-row-index]'));
    if (rows.length === 0) {
      return 0;
    }

    const maxIndex = rows.reduce((max, row) => {
      const rowIndex = Number.parseInt(row.getAttribute('data-row-index') || '0', 10);
      return Number.isFinite(rowIndex) ? Math.max(max, rowIndex) : max;
    }, 0);

    return maxIndex + 1;
  };

  const addRow = () => {
    if (!rowsContainer) {
      return;
    }

    const rowIndex = nextRowIndex();
    rowsContainer.insertAdjacentHTML('beforeend', createRowMarkup(rowIndex));
  };

  const syncHourCheckboxState = (input) => {
    if (!(input instanceof HTMLInputElement)) {
      return;
    }

    const label = input.closest('.hour-checkbox');
    if (!label) {
      return;
    }

    label.classList.toggle('is-checked', input.checked);
  };

  if (addRowButton && rowsContainer) {
    addRowButton.addEventListener('click', addRow);

    rowsContainer.addEventListener('click', (event) => {
      const removeButton = event.target.closest('.remove-row');
      if (!removeButton) {
        return;
      }

      const targetRow = removeButton.closest('.absent-row');
      if (!targetRow) {
        return;
      }

      const totalRows = rowsContainer.querySelectorAll('.absent-row').length;
      if (totalRows <= 1) {
        targetRow.querySelectorAll('input').forEach((input) => {
          if (input.type === 'checkbox') {
            input.checked = false;
            return;
          }

          input.value = '';
        });
        return;
      }

      targetRow.remove();
    });
  }

  page.querySelectorAll('.hour-checkbox input[type=\"checkbox\"]').forEach((input) => {
    syncHourCheckboxState(input);
  });

  page.addEventListener('change', (event) => {
    const target = event.target;
    if (target instanceof HTMLInputElement && target.matches('.hour-checkbox input[type=\"checkbox\"]')) {
      syncHourCheckboxState(target);
    }
  });

  const modal = document.getElementById('attendanceEditModal');
  const editButtons = Array.from(document.querySelectorAll('.edit-btn'));
  const editRecordId = document.getElementById('editRecordId');
  const editRecordDate = document.getElementById('editRecordDate');
  const editRecordGrade = document.getElementById('editRecordGrade');
  const editRecordField = document.getElementById('editRecordField');
  const editStudentName = document.getElementById('editStudentName');
  const editNotes = document.getElementById('editNotes');

  const openModal = () => {
    if (!modal) {
      return;
    }

    modal.hidden = false;
    modal.classList.add('is-open');
    document.body.classList.add('attendance-modal-open');
  };

  const closeModal = () => {
    if (!modal) {
      return;
    }

    modal.classList.remove('is-open');
    modal.hidden = true;
    document.body.classList.remove('attendance-modal-open');
  };

  if (modal) {
    modal.addEventListener('click', (event) => {
      const shouldClose = event.target.closest('[data-modal-close]');
      if (shouldClose) {
        closeModal();
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && modal.classList.contains('is-open')) {
        closeModal();
      }
    });
  }

  editButtons.forEach((button) => {
    button.addEventListener('click', () => {
      if (!modal || !editRecordId || !editStudentName || !editNotes) {
        return;
      }

      const recordId = button.dataset.id || '';
      const studentName = button.dataset.name || '';
      const notes = button.dataset.notes || '';
      const hoursCsv = button.dataset.hours || '';

      editRecordId.value = recordId;
      editStudentName.value = studentName;
      editNotes.value = notes;

      if (editRecordDate) {
        editRecordDate.value = button.dataset.date || editRecordDate.value;
      }
      if (editRecordGrade) {
        editRecordGrade.value = button.dataset.grade || editRecordGrade.value;
      }
      if (editRecordField) {
        editRecordField.value = button.dataset.field || editRecordField.value;
      }

      const selectedHours = new Set(
        hoursCsv
          .split(',')
          .map((hour) => Number.parseInt(hour.trim(), 10))
          .filter((hour) => Number.isInteger(hour) && hour >= 1 && hour <= 4)
      );

      for (let hour = 1; hour <= 4; hour += 1) {
        const checkbox = document.getElementById(`editHour${hour}`);
        if (checkbox) {
          checkbox.checked = selectedHours.has(hour);
          syncHourCheckboxState(checkbox);
        }
      }

      openModal();
      editStudentName.focus();
      editStudentName.select();
    });
  });

  const rowsBody = document.getElementById('attendanceRowsBody');
  const recordCountElement = document.getElementById('recordCount');
  const emptyState = document.getElementById('attendanceEmptyState');

  const updateTableState = () => {
    if (!rowsBody) {
      return;
    }

    const count = rowsBody.querySelectorAll('tr').length;

    if (recordCountElement) {
      recordCountElement.textContent = String(count);
    }

    if (emptyState) {
      emptyState.classList.toggle('is-hidden', count !== 0);
    }
  };

  const deleteForms = Array.from(document.querySelectorAll('.delete-form'));
  const supportsAjaxDelete = typeof window.fetch === 'function' && typeof window.FormData !== 'undefined' && typeof window.URLSearchParams !== 'undefined';

  if (supportsAjaxDelete) {
    deleteForms.forEach((form) => {
      form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const confirmed = window.confirm('آیا از حذف این رکورد مطمئن هستید؟');
        if (!confirmed) {
          return;
        }

        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton instanceof HTMLButtonElement) {
          submitButton.disabled = true;
        }

        try {
          const formData = new FormData(form);
          const body = new URLSearchParams();
          formData.forEach((value, key) => {
            body.append(key, String(value));
          });

          const response = await fetch(form.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              Accept: 'application/json',
              'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
              'X-Requested-With': 'XMLHttpRequest',
            },
            body,
          });

          const payload = await response.json().catch(() => null);

          if (!response.ok || !payload || payload.success !== true) {
            const message = payload && typeof payload.error === 'string' ? payload.error : 'حذف رکورد با خطا روبه رو شد.';
            throw new Error(message);
          }

          const row = form.closest('tr');
          if (row) {
            row.remove();
          }

          updateTableState();
        } catch (error) {
          const message = error instanceof Error ? error.message : 'ارتباط با سرور با خطا روبه رو شد.';
          window.alert(message);
        } finally {
          if (submitButton instanceof HTMLButtonElement) {
            submitButton.disabled = false;
          }
        }
      });
    });
  }

  page.classList.add('attendance-entrance-ready');

  const playEntrance = () => {
    if (page.classList.contains('attendance-entrance-play')) {
      return;
    }

    window.requestAnimationFrame(() => {
      window.requestAnimationFrame(() => {
        page.classList.add('attendance-entrance-play');
      });
    });
  };

  if (document.body.classList.contains('is-loaded')) {
    playEntrance();
  } else {
    window.addEventListener('load', playEntrance, { once: true });
  }

  window.setTimeout(() => {
    page.classList.remove('attendance-entrance-ready');
    page.classList.add('attendance-entrance-done');
  }, 1600);
})();
