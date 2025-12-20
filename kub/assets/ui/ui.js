document.addEventListener('click', e => {
  if (e.target.dataset.openRegister) {
    document.body.classList.add('show-register');
  }
});