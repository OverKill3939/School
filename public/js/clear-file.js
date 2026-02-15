document.addEventListener('click', (e) => {
  const btn = e.target.closest('.btn-clear');
  if (!btn) return;
  const targetName = btn.dataset.target;
  if (!targetName) return;
  const inputs = document.querySelectorAll(`input[type="file"][name="${CSS.escape(targetName)}"]`);
  inputs.forEach(inp => {
    inp.value = '';
    inp.dispatchEvent(new Event('change', { bubbles: true }));
  });
});
