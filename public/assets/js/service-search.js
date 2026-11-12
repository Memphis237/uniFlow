document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('serviceSearchForm');

  if (!form) {
    return;
  }

  const toggle = document.getElementById('filterToggle');
  const panel = document.getElementById('filterPanel');
  const budget = document.getElementById('budgetFilter');
  const value = document.getElementById('budgetValue');
  const category = document.getElementById('categoryFilter');

  toggle.addEventListener('click', () => {
    const open = panel.classList.contains('hidden');
    panel.classList.toggle('hidden');
    toggle.setAttribute('aria-expanded', String(open));
  });
  budget.addEventListener('input', () => (value.textContent = budget.value));
  budget.addEventListener('change', () => form.requestSubmit());
  document
    .getElementById('sortFilter')
    .addEventListener('change', () => form.requestSubmit());

  document.querySelectorAll('.category-button').forEach((button) => {
    button.addEventListener('click', () => {
      category.value = button.dataset.category;
      form.requestSubmit();
    });
  });

  document.getElementById('resetFilters').addEventListener('click', () => {
    form.elements.q.value = '';
    category.value = 'all';
    budget.value = '50';
    document.getElementById('sortFilter').value = 'recommended';
    form.requestSubmit();
  });
});
