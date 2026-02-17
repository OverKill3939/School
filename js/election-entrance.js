(() => {
  const page = document.querySelector('main.election-page');
  if (!page) {
    return;
  }

  const sections = Array.from(page.querySelectorAll(':scope > section'));
  if (sections.length === 0) {
    return;
  }

  const flashes = Array.from(page.querySelectorAll(':scope > .flash'));
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const coarsePointer = window.matchMedia('(pointer: coarse)').matches;
  const lowCpu = typeof navigator.hardwareConcurrency === 'number' && navigator.hardwareConcurrency <= 4;
  const lowMemory = typeof navigator.deviceMemory === 'number' && navigator.deviceMemory <= 4;
  const saveData = navigator.connection?.saveData === true;
  const liteMode = coarsePointer || lowCpu || lowMemory || saveData;
  const animateRowsLimit = liteMode ? 8 : 22;
  const animateCandidatesLimit = liteMode ? 8 : 20;

  if (liteMode) {
    page.classList.add('election-entrance-lite');
  }

  sections.forEach((section, sectionIndex) => {
    section.style.setProperty('--section-delay', `${sectionIndex * (liteMode ? 150 : 210)}ms`);

    const rows = Array.from(section.querySelectorAll('.eligible-table tbody tr'));
    rows.forEach((row, rowIndex) => {
      if (rowIndex < animateRowsLimit) {
        row.style.setProperty('--row-delay', `${rowIndex * (liteMode ? 18 : 28)}ms`);
      } else {
        row.classList.add('election-row-static');
      }
    });

    const candidates = Array.from(section.querySelectorAll('.candidate-card'));
    candidates.forEach((card, cardIndex) => {
      if (cardIndex < animateCandidatesLimit) {
        card.style.setProperty('--candidate-delay', `${cardIndex * (liteMode ? 22 : 34)}ms`);
      } else {
        card.classList.add('election-candidate-static');
      }
    });
  });

  flashes.forEach((flash, index) => {
    flash.style.setProperty('--section-delay', `${index * (liteMode ? 70 : 110)}ms`);
  });

  page.classList.add('election-entrance-ready');

  const playEntrance = () => {
    if (page.classList.contains('election-entrance-play')) {
      return;
    }

    window.requestAnimationFrame(() => {
      window.requestAnimationFrame(() => {
        page.classList.add('election-entrance-play');
      });
    });
  };

  if (document.body.classList.contains('is-loaded')) {
    playEntrance();
  } else {
    window.addEventListener('load', playEntrance, { once: true });
  }

  window.setTimeout(() => {
    page.classList.remove('election-entrance-ready');
    page.classList.add('election-entrance-done');
  }, liteMode ? 1200 : 2100);

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
