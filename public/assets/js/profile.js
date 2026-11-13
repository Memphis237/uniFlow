(() => {
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
  const modal = document.getElementById('profileModal');
  function open(focus) {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    if (focus === 'avatar')
      setTimeout(() => document.getElementById('avatarUpload').focus(), 80);
    if (focus === 'cover')
      setTimeout(() => document.getElementById('coverUpload').focus(), 80);
  }
  function close() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
  if (modal?.dataset.openOnLoad === 'true') open('');
  document
    .querySelectorAll('[data-open-profile]')
    .forEach((button) =>
      button.addEventListener('click', () => open(button.dataset.openProfile)),
    );
  document.getElementById('closeProfileModal').addEventListener('click', close);
  document
    .getElementById('cancelProfileModal')
    .addEventListener('click', close);
  modal.addEventListener('click', (event) => {
    if (event.target === modal) close();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') close();
  });
  document.querySelectorAll('[data-tab]').forEach((tab) =>
    tab.addEventListener('click', () => {
      document
        .querySelectorAll('[data-tab]')
        .forEach((item) =>
          item.setAttribute('aria-selected', String(item === tab)),
        );
      document.querySelectorAll('.profile-tab-panel').forEach((panel) => {
        const active = panel.id === tab.getAttribute('aria-controls');
        panel.hidden = !active;
        panel.classList.toggle('hidden', !active);
      });
    }),
  );
  if (window.lucide) lucide.createIcons();
})();
