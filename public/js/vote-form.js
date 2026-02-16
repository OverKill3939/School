(function () {
  const config = window.voteFormConfig || {};
  const eligibility = config.eligibility || {};

  const form = document.getElementById('vote-form');
  const gradeSelect = document.getElementById('voter-grade');
  const fieldSelect = document.getElementById('voter-field');
  const infoBox = document.getElementById('vote-limit-info');
  const candidateOptions = Array.from(document.querySelectorAll('.candidate-option'));

  if (!form || !gradeSelect || !fieldSelect || !infoBox || candidateOptions.length === 0) {
    return;
  }

  const candidateCheckboxes = candidateOptions
    .map((option) => option.querySelector('.candidate-checkbox'))
    .filter(Boolean);

  function selectedKey() {
    const grade = String(gradeSelect.value || '').trim();
    const field = String(fieldSelect.value || '').trim();
    if (!grade || !field) {
      return null;
    }
    return grade + '|' + field;
  }

  function selectedCount() {
    let count = 0;
    candidateCheckboxes.forEach((checkbox) => {
      if (checkbox.checked) {
        count += 1;
      }
    });
    return count;
  }

  function uncheckAll() {
    candidateCheckboxes.forEach((checkbox) => {
      checkbox.checked = false;
    });
  }

  function applyDisabledState(maxVotes, hardDisable) {
    const checked = selectedCount();

    candidateOptions.forEach((option) => {
      const checkbox = option.querySelector('.candidate-checkbox');
      if (!checkbox) {
        return;
      }

      if (hardDisable) {
        checkbox.disabled = true;
        option.classList.add('is-disabled');
        return;
      }

      if (checkbox.checked) {
        checkbox.disabled = false;
        option.classList.remove('is-disabled');
        return;
      }

      if (checked >= maxVotes) {
        checkbox.disabled = true;
        option.classList.add('is-disabled');
      } else {
        checkbox.disabled = false;
        option.classList.remove('is-disabled');
      }
    });
  }

  function updateLimitAndState() {
    const key = selectedKey();

    if (!key) {
      infoBox.textContent = 'برای مشاهده تعداد رأی مجاز، پایه و رشته را انتخاب کنید.';
      uncheckAll();
      applyDisabledState(0, true);
      return;
    }

    if (!eligibility[key]) {
      infoBox.textContent = 'برای این پایه و رشته تنظیم رأی وجود ندارد. لطفا با مدیر هماهنگ کنید.';
      uncheckAll();
      applyDisabledState(0, true);
      return;
    }

    const rule = eligibility[key];
    const eligibleCount = Number(rule.eligible_count || 0);
    const votedCount = Number(rule.voted_count || 0);
    const votesPerStudent = Math.max(1, Number(rule.votes_per_student || 1));
    const remaining = Math.max(0, eligibleCount - votedCount);

    if (remaining <= 0) {
      infoBox.textContent = 'ظرفیت رأی گیری برای این پایه و رشته تکمیل شده است.';
      uncheckAll();
      applyDisabledState(0, true);
      return;
    }

    infoBox.textContent =
      'رأی مجاز شما: ' + votesPerStudent +
      ' | ظرفیت باقی مانده این پایه/رشته: ' + remaining;

    applyDisabledState(votesPerStudent, false);
  }

  gradeSelect.addEventListener('change', updateLimitAndState);
  fieldSelect.addEventListener('change', updateLimitAndState);

  candidateCheckboxes.forEach((checkbox) => {
    checkbox.addEventListener('change', function () {
      const key = selectedKey();
      if (!key || !eligibility[key]) {
        checkbox.checked = false;
        return;
      }

      const maxVotes = Math.max(1, Number(eligibility[key].votes_per_student || 1));
      const checked = selectedCount();

      if (checked > maxVotes) {
        checkbox.checked = false;
        window.alert('تعداد انتخاب‌ها بیشتر از حد مجاز است.');
      }

      applyDisabledState(maxVotes, false);
    });
  });

  form.addEventListener('submit', function (event) {
    const key = selectedKey();
    if (!key || !eligibility[key]) {
      event.preventDefault();
      window.alert('برای پایه و رشته انتخاب‌شده، تنظیم رأی ثبت نشده است.');
      return;
    }

    const eligibleCount = Number(eligibility[key].eligible_count || 0);
    const votedCount = Number(eligibility[key].voted_count || 0);
    const remaining = Math.max(0, eligibleCount - votedCount);

    if (remaining <= 0) {
      event.preventDefault();
      window.alert('ظرفیت رأی گیری این پایه و رشته تکمیل شده است.');
      return;
    }

    const maxVotes = Math.max(1, Number(eligibility[key].votes_per_student || 1));
    const checked = selectedCount();

    if (checked <= 0) {
      event.preventDefault();
      window.alert('حداقل یک کاندیدا را انتخاب کنید.');
      return;
    }

    if (checked > maxVotes) {
      event.preventDefault();
      window.alert('تعداد انتخاب‌ها از حد مجاز بیشتر است.');
    }
  });

  updateLimitAndState();
})();