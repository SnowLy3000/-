const toggle = document.getElementById('themeToggle');

toggle.onclick = () => {
  const body = document.body;
  const next = body.dataset.theme === 'dark' ? 'light' : 'dark';
  body.dataset.theme = next;

  fetch('/cabinet/save_theme.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({theme: next})
  });
};