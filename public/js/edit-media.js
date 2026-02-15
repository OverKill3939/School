(() => {
  const form = document.querySelector('.news-form');
  if (!form) return;

  const cardSelector = '.thumb-select[data-media-id]';
  const submitInputClass = 'delete-media-submit';
  const collectionInputSelector = 'input[data-preview-collection]';
  const mainInputSelector = 'input[data-preview-main]';
  const replaceInputSelector = 'input[data-replace-media]';
  const pendingWrap = document.getElementById('pendingMediaWrap');
  const pendingGrid = document.getElementById('pendingMediaGrid');
  const previewUrls = [];
  const canHover = window.matchMedia('(hover: hover)').matches;

  const setupHoverVideoControls = (video) => {
    if (!(video instanceof HTMLVideoElement) || video.dataset.hoverReady === '1') {
      return;
    }

    video.dataset.hoverReady = '1';
    if (!canHover) {
      video.controls = true;
      return;
    }

    video.controls = false;
    video.addEventListener('mouseenter', () => {
      video.controls = true;
    });
    video.addEventListener('mouseleave', () => {
      if (document.activeElement !== video) {
        video.controls = false;
      }
    });
    video.addEventListener('focus', () => {
      video.controls = true;
    });
    video.addEventListener('blur', () => {
      video.controls = false;
    });
  };

  const setupExistingHoverVideos = () => {
    form.querySelectorAll('video[data-hover-controls]').forEach((video) => {
      setupHoverVideoControls(video);
    });
  };

  const syncDeleteButton = (button, isMarked) => {
    button.textContent = isMarked ? 'لغو حذف' : 'حذف';
    button.classList.toggle('btn-delete-cancel', isMarked);
    button.setAttribute('aria-pressed', isMarked ? 'true' : 'false');
  };

  const clearPreviewUrls = () => {
    while (previewUrls.length > 0) {
      const url = previewUrls.pop();
      URL.revokeObjectURL(url);
    }
  };

  const createPreviewNode = (file, type) => {
    const url = URL.createObjectURL(file);
    previewUrls.push(url);

    if (type === 'video') {
      const video = document.createElement('video');
      video.src = url;
      video.preload = 'metadata';
      video.muted = true;
      video.playsInline = true;
      video.controls = true;
      video.dataset.hoverControls = '1';
      video.addEventListener(
        'loadedmetadata',
        () => {
          const duration = Number(video.duration) || 0;
          const target = duration > 1 ? Math.min(1, duration / 3) : 0.1;
          const onSeeked = () => {
            video.pause();
            video.removeEventListener('seeked', onSeeked);
          };
          video.addEventListener('seeked', onSeeked);
          try {
            video.currentTime = target;
          } catch (_error) {
            video.removeEventListener('seeked', onSeeked);
          }
        },
        { once: true }
      );
      setupHoverVideoControls(video);
      return video;
    }

    const image = document.createElement('img');
    image.src = url;
    image.alt = '';
    return image;
  };

  const renderPendingMedia = () => {
    if (!pendingWrap || !pendingGrid) return;

    clearPreviewUrls();
    pendingGrid.innerHTML = '';

    let total = 0;
    form.querySelectorAll(collectionInputSelector).forEach((input) => {
      const type = input.dataset.previewCollection === 'video' ? 'video' : 'image';
      const files = Array.from(input.files || []);
      files.forEach((file) => {
        const card = document.createElement('div');
        card.className = 'thumb-select thumb-select--pending';

        const top = document.createElement('div');
        top.className = 'thumb-top';

        const badge = document.createElement('span');
        badge.className = `media-badge ${type === 'video' ? 'badge-video' : 'badge-image'}`;
        badge.textContent = type === 'video' ? 'ویدیو جدید' : 'تصویر جدید';
        top.appendChild(badge);

        const name = document.createElement('span');
        name.className = 'pending-name';
        name.textContent = file.name;
        top.appendChild(name);

        card.appendChild(top);
        card.appendChild(createPreviewNode(file, type));
        pendingGrid.appendChild(card);
        total += 1;
      });
    });

    pendingWrap.hidden = total === 0;
  };

  const previewMainMedia = (input) => {
    const target = input.dataset.previewMain;
    if (target !== 'image' && target !== 'video') return;
    if (!input.files || input.files.length === 0) return;

    const card = input.closest('.media-card');
    const preview = card?.querySelector('.media-preview');
    if (!card || !preview) return;

    preview.innerHTML = '';
    preview.appendChild(createPreviewNode(input.files[0], target));

    const hiddenDeleteInput = document.getElementById(`delete_main_${target}`);
    if (hiddenDeleteInput) {
      hiddenDeleteInput.value = '0';
    }

    card.classList.remove('marked-delete');
    const deleteButton = card.querySelector(`.btn-delete[data-main-delete="${target}"]`);
    if (deleteButton) {
      syncDeleteButton(deleteButton, false);
    }
  };

  const previewReplaceMedia = (input) => {
    const target = input.dataset.replaceMedia;
    if (target !== 'image' && target !== 'video') return;
    if (!input.files || input.files.length === 0) return;

    const card = input.closest(cardSelector);
    if (!card) return;

    const mediaNode = card.querySelector('img, video');
    if (!mediaNode) return;

    const nextNode = createPreviewNode(input.files[0], target);
    mediaNode.replaceWith(nextNode);

    card.classList.remove('marked-delete');
    const deleteBtn = card.querySelector('.card-delete');
    if (deleteBtn) {
      syncDeleteButton(deleteBtn, false);
    }
  };

  document.addEventListener('click', (event) => {
    const cardDelete = event.target.closest('.card-delete');
    if (cardDelete) {
      const card = cardDelete.closest(cardSelector);
      if (!card) return;

      const isMarked = card.classList.toggle('marked-delete');
      syncDeleteButton(cardDelete, isMarked);
      event.preventDefault();
      return;
    }

    const mainDeleteBtn = event.target.closest('.btn-delete[data-main-delete]');
    if (!mainDeleteBtn) return;

    const target = mainDeleteBtn.dataset.mainDelete;
    const input = document.getElementById(`delete_main_${target}`);
    const card = mainDeleteBtn.closest('.media-card');
    if (!input || !card) return;

    const next = input.value === '1' ? '0' : '1';
    input.value = next;
    const marked = next === '1';
    card.classList.toggle('marked-delete', marked);
    syncDeleteButton(mainDeleteBtn, marked);

    event.preventDefault();
  });

  form.addEventListener('change', (event) => {
    const collectionInput = event.target.closest(collectionInputSelector);
    if (collectionInput) {
      renderPendingMedia();
      return;
    }

    const mainInput = event.target.closest(mainInputSelector);
    if (mainInput) {
      previewMainMedia(mainInput);
      return;
    }

    const replaceInput = event.target.closest(replaceInputSelector);
    if (replaceInput) {
      previewReplaceMedia(replaceInput);
    }
  });

  form.addEventListener('submit', () => {
    form.querySelectorAll(`input.${submitInputClass}`).forEach((node) => node.remove());

    form.querySelectorAll(`${cardSelector}.marked-delete`).forEach((card) => {
      const id = (card.getAttribute('data-media-id') || '').trim();
      if (!id) return;

      const hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'delete_media[]';
      hidden.value = id;
      hidden.className = submitInputClass;
      form.appendChild(hidden);
    });
  });

  window.addEventListener('beforeunload', clearPreviewUrls);
  setupExistingHoverVideos();
  renderPendingMedia();
})();
