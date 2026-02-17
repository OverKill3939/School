(() => {
  const page = document.querySelector('main.home-page');
  if (!page) {
    return;
  }

  const sections = Array.from(page.querySelectorAll(':scope > section'));
  if (sections.length === 0) {
    return;
  }

  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const coarsePointer = window.matchMedia('(pointer: coarse)').matches;
  const lowCpu = typeof navigator.hardwareConcurrency === 'number' && navigator.hardwareConcurrency <= 4;
  const lowMemory = typeof navigator.deviceMemory === 'number' && navigator.deviceMemory <= 4;
  const saveData = navigator.connection?.saveData === true;
  const liteMode = coarsePointer || lowCpu || lowMemory || saveData;
  const animateItemsLimit = liteMode ? 8 : 24;

  if (liteMode) {
    page.classList.add('home-entrance-lite');
  }

  sections.forEach((section, sectionIndex) => {
    section.style.setProperty('--section-delay', `${sectionIndex * (liteMode ? 140 : 200)}ms`);
  });

  const setDelayWithCap = (elements, stepNormal, stepLite) => {
    elements.forEach((element, index) => {
      if (index < animateItemsLimit) {
        element.style.setProperty('--item-delay', `${index * (liteMode ? stepLite : stepNormal)}ms`);
      } else {
        element.classList.add('home-item-static');
      }
    });
  };

  setDelayWithCap(Array.from(page.querySelectorAll('.hero-actions .cta')), 52, 34);
  setDelayWithCap(Array.from(page.querySelectorAll('.stat-card')), 42, 28);
  setDelayWithCap(Array.from(page.querySelectorAll('.home-news .news-card')), 34, 22);
  setDelayWithCap(Array.from(page.querySelectorAll('.quick-link')), 28, 18);

  page.classList.add('home-entrance-ready');

  const playEntrance = () => {
    if (page.classList.contains('home-entrance-play')) {
      return;
    }

    window.requestAnimationFrame(() => {
      window.requestAnimationFrame(() => {
        page.classList.add('home-entrance-play');
      });
    });
  };

  if (document.body.classList.contains('is-loaded')) {
    playEntrance();
  } else {
    window.addEventListener('load', playEntrance, { once: true });
  }

  window.setTimeout(() => {
    page.classList.remove('home-entrance-ready');
    page.classList.add('home-entrance-done');
  }, liteMode ? 1100 : 1900);

  if (reducedMotion) {
    return;
  }
})();
