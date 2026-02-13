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