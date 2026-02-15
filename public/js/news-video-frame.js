(() => {
  const videos = document.querySelectorAll('video[data-frame-preview]');
  if (videos.length === 0) return;

  const seekToPreviewFrame = (video) => {
    const duration = Number(video.duration) || 0;
    const target = duration > 1 ? Math.min(1, duration / 3) : 0.1;
    if (Number.isNaN(target) || !Number.isFinite(target)) return;

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
  };

  videos.forEach((video) => {
    if (video.readyState >= 1) {
      seekToPreviewFrame(video);
      return;
    }

    video.addEventListener(
      'loadedmetadata',
      () => {
        seekToPreviewFrame(video);
      },
      { once: true }
    );
  });
})();
