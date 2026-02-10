const yearEl = document.getElementById("year");
if (yearEl) {
  yearEl.textContent = new Date().getFullYear();
}

window.addEventListener("load", () => {
  document.body.classList.add("is-loaded");
});
function checkAuth() {
    const token = localStorage.getItem('user_token');
    if (!token && !window.location.href.includes('login.html')) {
        window.location.href = 'login.html';
    }
}

function logout() {
    localStorage.removeItem('user_token');
    window.location.href = 'login.html';
}

document.addEventListener('DOMContentLoaded', () => {
    document.body.classList.add('is-loaded');
});