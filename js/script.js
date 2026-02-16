const yearEl = document.getElementById('year');
if (yearEl) {
  yearEl.textContent = new Date().getFullYear();
}

const updateScrolledState = () => {
  document.body.classList.toggle('is-scrolled', window.scrollY > 8);
};

window.addEventListener('load', () => {
  document.body.classList.add('is-loaded');
  updateScrolledState();
});

window.addEventListener('scroll', updateScrolledState, { passive: true });

const bodyEl = document.body;
const isLoggedIn = bodyEl?.dataset?.userLoggedIn === '1';
const shouldPromptNotificationPermission = bodyEl?.dataset?.notifyPermissionPrompt === '1';

const supportsBrowserNotification = typeof window !== 'undefined' && 'Notification' in window;
const NEWS_POLL_INTERVAL_MS = 30000;
const LATEST_NEWS_KEY_STORAGE = 'school_latest_news_key_v1';

function makeNewsKey(news) {
  const publishedAt = String(news?.published_at ?? '');
  const id = String(news?.id ?? '');
  return `${publishedAt}|${id}`;
}

function getStoredLatestNewsKey() {
  try {
    return localStorage.getItem(LATEST_NEWS_KEY_STORAGE);
  } catch (error) {
    return null;
  }
}

function setStoredLatestNewsKey(value) {
  try {
    localStorage.setItem(LATEST_NEWS_KEY_STORAGE, value);
  } catch (error) {
    // Ignore storage issues to keep main UX stable.
  }
}

async function requestNotificationPermissionAfterLogin() {
  if (!isLoggedIn || !shouldPromptNotificationPermission || !supportsBrowserNotification) {
    return;
  }

  if (!window.isSecureContext && location.hostname !== 'localhost') {
    return;
  }

  if (Notification.permission !== 'default') {
    return;
  }

  try {
    await Notification.requestPermission();
  } catch (error) {
    // Some browsers may block repeated prompts.
  }
}

async function fetchLatestPublishedNews() {
  try {
    const response = await fetch('api/latest-news.php', {
      method: 'GET',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: {
        Accept: 'application/json',
      },
    });

    if (!response.ok) {
      return null;
    }

    const payload = await response.json();
    if (!payload || payload.ok !== true || !payload.news) {
      return null;
    }

    return payload.news;
  } catch (error) {
    return null;
  }
}

function showNewsBrowserNotification(news) {
  if (!supportsBrowserNotification || Notification.permission !== 'granted') {
    return;
  }

  const notification = new Notification('خبر جدید منتشر شد', {
    body: String(news.title ?? ''),
    icon: 'img/daralfonon.jpg',
    tag: 'school-latest-news',
  });

  notification.onclick = () => {
    window.focus();
    const slug = encodeURIComponent(String(news.slug ?? ''));
    if (slug !== '') {
      window.location.href = `news-detail.php?slug=${slug}`;
      return;
    }
    window.location.href = 'news.php';
  };
}

async function checkLatestNewsAndNotify() {
  if (!isLoggedIn) {
    return;
  }

  const latestNews = await fetchLatestPublishedNews();
  if (!latestNews) {
    return;
  }

  const latestKey = makeNewsKey(latestNews);
  if (latestKey === '|') {
    return;
  }

  const storedKey = getStoredLatestNewsKey();
  if (!storedKey) {
    setStoredLatestNewsKey(latestKey);
    return;
  }

  if (storedKey === latestKey) {
    return;
  }

  setStoredLatestNewsKey(latestKey);
  showNewsBrowserNotification(latestNews);
}

async function startNewsNotificationFlow() {
  if (!isLoggedIn) {
    return;
  }

  await requestNotificationPermissionAfterLogin();
  await checkLatestNewsAndNotify();

  window.setInterval(() => {
    checkLatestNewsAndNotify();
  }, NEWS_POLL_INTERVAL_MS);
}

startNewsNotificationFlow();
