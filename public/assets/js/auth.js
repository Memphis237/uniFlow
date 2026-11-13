const loginLockedUntil = Number(document.body.dataset.loginLockedUntil || 0);
if (loginLockedUntil > Math.floor(Date.now() / 1000)) {
  const overlay = document.createElement('div');
  overlay.className = 'lock-modal-backdrop';
  overlay.setAttribute('role', 'dialog');
  overlay.setAttribute('aria-modal', 'true');
  overlay.setAttribute('aria-labelledby', 'lockModalTitle');
  overlay.innerHTML =
    '<section class="lock-modal"><div class="lock-icon"><i class="fa-solid fa-shield-halved"></i></div><p class="lock-eyebrow">SÉCURITÉ UNIFLOW</p><h2 id="lockModalTitle">Connexion temporairement bloquée</h2><p>Trop de tentatives incorrectes. Vous pourrez réessayer dans :</p><div class="lock-countdown" aria-live="polite"><span id="lockTimer">15:00</span></div><small>Cette mesure protège votre compte.</small></section>';
  document.body.append(overlay);
  document
    .querySelectorAll('.auth-form input, .auth-form button[type="submit"]')
    .forEach((element) => (element.disabled = true));
  const timer = document.getElementById('lockTimer');
  const updateLockTimer = () => {
    const remaining = Math.max(
      0,
      loginLockedUntil - Math.floor(Date.now() / 1000),
    );
    timer.textContent =
      String(Math.floor(remaining / 60)).padStart(2, '0') +
      ':' +
      String(remaining % 60).padStart(2, '0');
    if (remaining === 0) window.location.reload();
  };
  updateLockTimer();
  window.setInterval(updateLockTimer, 1000);
}
document.querySelector('.reveal')?.addEventListener('click', function () {
  const field = document.querySelector('#password');
  const shown = field.type === 'password';
  field.type = shown ? 'text' : 'password';
  this.setAttribute(
    'aria-label',
    shown ? 'Masquer le mot de passe' : 'Afficher le mot de passe',
  );
  this.setAttribute(
    'title',
    shown ? 'Masquer le mot de passe' : 'Afficher le mot de passe',
  );
  this.classList.toggle('is-visible', shown);
});
document.querySelectorAll('[data-resend-countdown]').forEach((timer) => {
  const button = timer.closest('form')?.querySelector('[data-resend-button]');
  const resendAt = Number(timer.dataset.resendCountdown || 0);
  const updateCountdown = () => {
    const seconds = Math.max(0, resendAt - Math.floor(Date.now() / 1000));
    if (seconds === 0) {
      button.disabled = false;
      timer.textContent = 'Vous pouvez demander un nouveau code.';
      return;
    }
    button.disabled = true;
    timer.textContent = `Nouveau code disponible dans ${String(
      Math.floor(seconds / 60),
    ).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
    window.setTimeout(updateCountdown, 1000);
  };
  updateCountdown();
});
