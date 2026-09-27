let authReturnFocus = null;
function openAuthModal(tab = 'login') {
  const modal = document.getElementById('authModal');
  if (!modal) { window.location.href = 'index.php#' + tab; return; }
  authReturnFocus = document.activeElement;
  modal.classList.add('open');
  modal.setAttribute('role', 'dialog');
  modal.setAttribute('aria-modal', 'true');
  modal.setAttribute('aria-label', 'Account access');
  const notice = document.querySelector('.app-status .app-notice');
  if (notice) modal.querySelector('.modal-form-side').prepend(notice);
  switchAuthTab(tab);
}
function closeAuthModal() {
  document.getElementById('authModal')?.classList.remove('open');
  if (['#login', '#register'].includes(location.hash)) history.replaceState(null, '', location.pathname + location.search);
  authReturnFocus?.focus();
}
function switchAuthTab(tab) {
  const register = tab === 'register';
  document.getElementById('loginForm')?.classList.toggle('hide', register);
  document.getElementById('registerForm')?.classList.toggle('hide', !register);
  document.getElementById('tabLoginBtn')?.classList.toggle('active', !register);
  document.getElementById('tabRegisterBtn')?.classList.toggle('active', register);
  document.querySelector(register ? '#registerForm input[type=email]' : '#loginForm input[type=email]')?.focus();
}
function openAuthHash() {
  if (['#login', '#register'].includes(location.hash)) openAuthModal(location.hash.slice(1));
}
window.addEventListener('hashchange', openAuthHash);
document.addEventListener('DOMContentLoaded', openAuthHash);
document.addEventListener('keydown', event => {
  const modal = document.querySelector('#authModal.open');
  if (!modal) return;
  if (event.key === 'Escape') closeAuthModal();
  if (event.key === 'Tab') {
    const items = [...modal.querySelectorAll('button, input, a[href]')].filter(el => el.getClientRects().length && !el.disabled && el.type !== 'hidden');
    const first = items[0], last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
  }
});
