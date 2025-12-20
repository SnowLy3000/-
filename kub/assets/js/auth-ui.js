(function(){
  const root = document.querySelector('.auth-page');
  if(!root) return;

  const loginForm = document.getElementById('formLogin');
  const regForm   = document.getElementById('formRegister');

  // ==== switch forms (fade) ====
  function show(form){
    if(form === 'login'){
      regForm?.classList.remove('active');
      loginForm?.classList.add('active');
    }else{
      loginForm?.classList.remove('active');
      regForm?.classList.add('active');
    }
  }

  document.querySelectorAll('[data-switch]').forEach(a=>{
    a.addEventListener('click', (e)=>{
      e.preventDefault();
      show(a.getAttribute('data-switch'));
    });
  });

  // ==== theme toggle ====
  const btn = document.getElementById('themeToggle');
  const ico = btn?.querySelector('.theme-ico');

  function setIco(theme){
    if(!ico) return;
    ico.textContent = (theme === 'dark') ? '🌙' : '🌞';
  }

  setIco(root.dataset.theme || 'light');

  btn?.addEventListener('click', async ()=>{
    const next = (root.dataset.theme === 'dark') ? 'light' : 'dark';
    root.dataset.theme = next;
    setIco(next);

    // сохраняем на сервер (в сессию гостя)
    try{
      await fetch('/public/theme_set.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'theme=' + encodeURIComponent(next)
      });
    }catch(e){
      // если сервер не ответил — не ломаем UI
    }
  });

})();