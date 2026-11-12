(() => {
  const createModal = document.getElementById('eventModal'),
    detailModal = document.getElementById('eventDetailModal'),
    images = [
      'photo-1511795409834-ef04bbd61622',
      'photo-1523580494863-6f3031224c94',
      'photo-1529156069898-49953e39b3ac',
    ];
  function open(m) {
    m.classList.remove('hidden');
    m.classList.add('flex');
    document.body.classList.add('overflow-hidden');
  }
  function close(m) {
    m.classList.add('hidden');
    m.classList.remove('flex');
    if (
      document.querySelectorAll('#eventModal.flex,#eventDetailModal.flex')
        .length === 0
    )
      document.body.classList.remove('overflow-hidden');
  }
  document
    .querySelector('[data-open-event]')
    .addEventListener('click', () => open(createModal));
  document
    .querySelectorAll('[data-close-event]')
    .forEach((b) => b.addEventListener('click', () => close(createModal)));
  createModal.addEventListener('click', (e) => {
    if (e.target === createModal) close(createModal);
  });
  document.querySelectorAll('[data-event-open]').forEach((card, index) =>
    card.addEventListener('click', () => {
      const e = JSON.parse(card.dataset.eventOpen),
        address = [e.address, e.postal_code, e.city, e.country]
          .filter(Boolean)
          .join(', ');
      const detailImage = document.getElementById('detailImage'),
        fallback = document.getElementById('detailImageFallback');
      if (e.image || e.avatar) {
        detailImage.src = e.image || e.avatar;
        detailImage.classList.remove('hidden');
        fallback.classList.add('hidden');
      } else {
        detailImage.classList.add('hidden');
        fallback.className =
          'grid h-48 place-items-center bg-gradient-to-br ' +
          e.color +
          ' text-7xl font-bold text-white sm:h-60';
        fallback.textContent = e.initial;
        fallback.classList.remove('hidden');
      }
      const organizerNode = document.getElementById('detailOrganizer'),
        organizerIcon = document.createElement('i');
      organizerIcon.className = 'fa-solid fa-user mr-1.5';
      organizerIcon.setAttribute('aria-hidden', 'true');
      organizerNode.replaceChildren(
        organizerIcon,
        document.createTextNode(' ' + e.organizer),
      );
      document.getElementById('eventDetailTitle').textContent = e.title;
      document.getElementById('detailDescription').textContent = e.description;
      const startAt = new Date(e.starts_at.replace(' ', 'T')),
        endAt = e.ends_at ? new Date(e.ends_at.replace(' ', 'T')) : null,
        dateFormat = new Intl.DateTimeFormat('fr-FR', {
          day: '2-digit',
          month: 'short',
        }),
        timeFormat = new Intl.DateTimeFormat('fr-FR', {
          hour: '2-digit',
          minute: '2-digit',
        });
      document.querySelector('#detailDate span').textContent =
        new Intl.DateTimeFormat('en-GB', {
          day: '2-digit',
          month: 'short',
        }).format(startAt) +
        ' - ' +
        timeFormat.format(startAt) +
        (endAt ? ' - ' + timeFormat.format(endAt) : '');
      document.getElementById('detailAddress').textContent = address;
      document.getElementById('detailMap').href =
        'https://www.google.com/maps/search/?api=1&query=' +
        encodeURIComponent(address);
      document.querySelector('#detailParticipants span').textContent =
        e.participants + ' participant' + (e.participants > 1 ? 's' : '');
      document.getElementById('participationEventId').value = e.id;
      document.getElementById('participationAction').value = e.joined
        ? 'campus-leave-event'
        : 'campus-join-event';
      document.getElementById('participationButton').innerHTML = e.joined
        ? '<i class=\"fa-solid fa-circle-check mr-2\">\n    </i>Tu participes - Annuler ma participation'
        : '<i class=\"fa-solid fa-check mr-2\">\n    </i>Je participe';
      open(detailModal);
    }),
  );
  document
    .querySelectorAll('[data-close-detail]')
    .forEach((b) => b.addEventListener('click', () => close(detailModal)));
  detailModal.addEventListener('click', (e) => {
    if (e.target === detailModal) close(detailModal);
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      close(createModal);
      close(detailModal);
    }
  });
})();
