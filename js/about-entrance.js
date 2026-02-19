(() => {
  const page = document.querySelector('main.about-page');
  if (!page) {
    return;
  }

  const sections = Array.from(page.children).filter((el) => el.tagName === 'SECTION');
  if (sections.length === 0) {
    return;
  }

  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const coarsePointer = window.matchMedia('(pointer: coarse)').matches;
  const lowCpu = typeof navigator.hardwareConcurrency === 'number' && navigator.hardwareConcurrency <= 4;
  const lowMemory = typeof navigator.deviceMemory === 'number' && navigator.deviceMemory <= 4;
  const saveData = navigator.connection?.saveData === true;
  const liteMode = coarsePointer || lowCpu || lowMemory || saveData;
  const animateItemsLimit = liteMode ? 12 : 36;

  if (liteMode) {
    page.classList.add('about-entrance-lite');
  }

  const baseSectionDelay = liteMode ? 60 : 90;
  sections.forEach((section, sectionIndex) => {
    section.style.setProperty('--section-delay', `${baseSectionDelay + sectionIndex * (liteMode ? 140 : 200)}ms`);
  });

  const setDelayWithCap = (elements, stepNormal, stepLite) => {
    elements.forEach((element, index) => {
      if (index < animateItemsLimit) {
        element.style.setProperty('--item-delay', `${index * (liteMode ? stepLite : stepNormal)}ms`);
      } else {
        element.classList.add('about-item-static');
      }
    });
  };

  setDelayWithCap(Array.from(page.querySelectorAll('.about-hero > *')), 36, 24);
  setDelayWithCap(Array.from(page.querySelectorAll('.about-card h2, .about-card h3')), 26, 18);
  setDelayWithCap(Array.from(page.querySelectorAll('.about-card p')), 22, 16);
  setDelayWithCap(Array.from(page.querySelectorAll('.about-card ul li')), 18, 12);
  setDelayWithCap(Array.from(page.querySelectorAll('.amenity-item')), 24, 16);
  setDelayWithCap(Array.from(page.querySelectorAll('.about-contact h2, .about-contact p')), 24, 16);

  page.classList.add('about-entrance-ready');

  const playEntrance = () => {
    if (page.classList.contains('about-entrance-play')) {
      return;
    }

    window.requestAnimationFrame(() => {
      window.requestAnimationFrame(() => {
        page.classList.add('about-entrance-play');
      });
    });
  };

  if (document.body.classList.contains('is-loaded')) {
    playEntrance();
  } else {
    window.addEventListener('load', playEntrance, { once: true });
  }

  window.setTimeout(() => {
    page.classList.remove('about-entrance-ready');
    page.classList.add('about-entrance-done');
  }, liteMode ? 1200 : 2000);

  if (reducedMotion) {
    return;
  }
})();
