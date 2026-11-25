const signin = document.querySelector('[data-newtic-admin-signin]');
if (signin) {
  const password = signin.querySelector('#newtic-admin-password');
  const toggle = signin.querySelector('[data-admin-password-toggle]');
  if (password && toggle) {
    toggle.hidden = false;
    const reset = () => {
      password.type = 'password';
      toggle.setAttribute('aria-pressed','false');
      toggle.setAttribute('aria-label','Şifreyi göster');
      toggle.querySelector('[data-password-slash]')?.setAttribute('hidden','');
    };
    toggle.addEventListener('click', () => {
      if (password.type === 'text') { reset(); return; }
      password.type = 'text';
      toggle.setAttribute('aria-pressed','true');
      toggle.setAttribute('aria-label','Şifreyi gizle');
      toggle.querySelector('[data-password-slash]')?.removeAttribute('hidden');
    });
    signin.querySelector('form')?.addEventListener('submit', reset);
    window.addEventListener('pagehide', reset);
  }
}
