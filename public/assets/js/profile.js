(() => {
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
