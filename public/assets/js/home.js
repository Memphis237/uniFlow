(() => {
  const carousel = document.querySelector('.event-carousel');
  if (!carousel) return;
  const slides = [...carousel.querySelectorAll('[data-event-slide]')],
    dots = [...carousel.querySelectorAll('[data-event-dot]')];
  if (slides.length < 2) return;
  let active = 0;
  function show(index) {
    active = (index + slides.length) % slides.length;
    slides.forEach((slide, i) =>
      slide.classList.toggle('hidden', i !== active),
    );
    dots.forEach((dot, i) => {
      dot.classList.toggle('bg-teal-600', i === active);
      dot.classList.toggle('bg-slate-300', i !== active);
      dot.classList.toggle('w-5', i === active);
      dot.classList.toggle('w-2', i !== active);
    });
  }
  carousel
    .querySelector('.event-prev')
    .addEventListener('click', () => show(active - 1));
  carousel
    .querySelector('.event-next')
    .addEventListener('click', () => show(active + 1));
  dots.forEach((dot, index) =>
    dot.addEventListener('click', () => show(index)),
  );
  let timer = setInterval(() => show(active + 1), 6000);
  carousel.addEventListener('mouseenter', () => clearInterval(timer));
  carousel.addEventListener(
    'mouseleave',
    () => (timer = setInterval(() => show(active + 1), 6000)),
  );
})();
