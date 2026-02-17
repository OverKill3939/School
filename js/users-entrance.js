(() => {
  const page = document.querySelector('main.users-page');
  if (!page) {
    return;
  }

  const cards = Array.from(page.querySelectorAll(':scope > section.users-card'));
  if (cards.length === 0) {
    return;
  }

  const reducedMotionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
  const coarsePointerQuery = window.matchMedia('(pointer: coarse)');
  const reducedMotion = reducedMotionQuery.matches;
  const coarsePointer = coarsePointerQuery.matches;
  const lowCpu = typeof navigator.hardwareConcurrency === 'number' && navigator.hardwareConcurrency <= 4;
  const lowMemory = typeof navigator.deviceMemory === 'number' && navigator.deviceMemory <= 4;
  const saveData = navigator.connection?.saveData === true;
  const liteMode = coarsePointer || lowCpu || lowMemory || saveData;
  const animateRowsLimit = liteMode ? 8 : 22;

  if (liteMode) {
    page.classList.add('users-entrance-lite');
  }

  cards.forEach((card, cardIndex) => {
    card.style.setProperty('--card-delay', `${cardIndex * (liteMode ? 160 : 220)}ms`);

    const statItems = Array.from(card.querySelectorAll('.users-stats .stat-item'));
    statItems.forEach((item, itemIndex) => {
      item.style.setProperty('--stats-delay', `${itemIndex * (liteMode ? 40 : 52)}ms`);
    });

    const filterItems = Array.from(card.querySelectorAll('.users-filters > *'));
    filterItems.forEach((item, itemIndex) => {
      item.style.setProperty('--filter-delay', `${itemIndex * (liteMode ? 35 : 50)}ms`);
    });

    const rows = Array.from(card.querySelectorAll('.users-table tbody tr'));
    rows.forEach((row, rowIndex) => {
      if (rowIndex < animateRowsLimit) {
        row.style.setProperty('--row-delay', `${rowIndex * (liteMode ? 18 : 28)}ms`);
      } else {
        row.classList.add('users-row-static');
      }
    });
  });

  page.classList.add('users-entrance-ready');

  const playEntrance = () => {
    if (page.classList.contains('users-entrance-play')) {
      return;
    }

    window.requestAnimationFrame(() => {
      window.requestAnimationFrame(() => {
        page.classList.add('users-entrance-play');
      });
    });
  };

  if (document.body.classList.contains('is-loaded')) {
    playEntrance();
  } else {
    window.addEventListener('load', playEntrance, { once: true });
  }

  window.setTimeout(() => {
    page.classList.remove('users-entrance-ready');
    page.classList.add('users-entrance-done');
  }, liteMode ? 1200 : 1900);

  if (reducedMotion || liteMode) {
    return;
  }

  let pointerFrame = 0;
  const setPointer = (clientX, clientY) => {
    if (pointerFrame !== 0) {
      return;
    }

    pointerFrame = window.requestAnimationFrame(() => {
      pointerFrame = 0;
      const rect = page.getBoundingClientRect();
      const width = Math.max(rect.width, 1);
      const height = Math.max(rect.height, 1);
      const x = ((clientX - rect.left) / width) * 100;
      const y = ((clientY - rect.top) / height) * 100;
      const boundedX = Math.max(0, Math.min(100, x));
      const boundedY = Math.max(0, Math.min(100, y));

      page.style.setProperty('--pointer-x', `${boundedX.toFixed(2)}%`);
      page.style.setProperty('--pointer-y', `${boundedY.toFixed(2)}%`);
    });
  };

  page.addEventListener(
    'pointermove',
    (event) => {
      setPointer(event.clientX, event.clientY);
    },
    { passive: true }
  );

  page.addEventListener(
    'pointerleave',
    () => {
      page.style.setProperty('--pointer-x', '50%');
      page.style.setProperty('--pointer-y', '0%');
    },
    { passive: true }
  );
})();
