<?php
$filters = $serviceFilters ?? [
    'q' => '',
    'category' => 'all',
    'max_price' => 50,
    'sort' => 'recommended',
];
$categoryButtons = [
    'all' => ['Tous les services', 'layout-grid'],
    'coiffure' => ['Coiffure & beaute', 'scissors'],
    'cours' => ['Cours & soutien', 'book-open'],
    'demenagement' => ['Demenagement', 'package'],
    'informatique' => ['Informatique', 'monitor'],
    'photo' => ['Photo & crea', 'camera'],
    'autres' => ['Autres', 'sparkles'],
];
?>
<form id="serviceSearchForm" action="index.php" method="get" class="mt-6">
    <input type="hidden" name="page" value="<?= htmlspecialchars($currentPage ?? 'services', ENT_QUOTES, 'UTF-8') ?>">
    <input
        id="categoryFilter"
        type="hidden"
        name="category"
        value="<?= htmlspecialchars((string) $filters['category'], ENT_QUOTES, 'UTF-8') ?>"
    >
    <div class="flex gap-3">
        <label class="flex-1 flex items-center gap-3 bg-white border border-slate-200 rounded-xl px-4 h-14">
            <i data-lucide="search" class="w-5 h-5 text-slate-400 shrink-0"></i>
            <input
                id="serviceSearch"
                name="q"
                maxlength="100"
                value="<?= htmlspecialchars((string) $filters['q'], ENT_QUOTES, 'UTF-8') ?>"
                class="w-full outline-none text-sm placeholder:text-slate-400"
                placeholder="Un service, un talent, un coup de main..."
                aria-label="Rechercher un service"
            >
        </label>
        <button
            type="submit"
            class="rounded-xl bg-teal-700 px-4 text-sm font-semibold text-white hover:bg-teal-800"
        >
            Rechercher
        </button>
        <button
            id="filterToggle"
            type="button"
            aria-expanded="false"
            aria-controls="filterPanel"
            class="px-4 md:px-5 rounded-xl border border-slate-200 bg-white hover:border-teal-300 flex items-center gap-2 text-sm"
        >
            <i data-lucide="sliders-horizontal" class="w-4 h-4"></i>
            <span class="hidden sm:inline">Filtres</span>
        </button>
    </div>

    <section
        id="filterPanel"
        class="hidden mt-3 p-5 md:p-6 bg-white border border-slate-200 rounded-xl"
        aria-label="Filtres de recherche"
    >
        <div class="grid md:grid-cols-[1fr_1fr_auto_auto] gap-6 items-end">
            <label class="text-sm text-slate-500">
                Budget maximum :
                <strong><span id="budgetValue"><?= (int) $filters['max_price'] ?></span> &euro;</strong>
                <input
                    id="budgetFilter"
                    name="max_price"
                    type="range"
                    min="5"
                    max="500"
                    step="5"
                    value="<?= (int) $filters['max_price'] ?>"
                    class="mt-4 w-full accent-teal-600"
                >
            </label>
            <label class="text-sm text-slate-500">
                Trier par
                <select
                    id="sortFilter"
                    name="sort"
                    class="block mt-2 w-full rounded-lg border border-slate-200 px-3 py-2.5 text-slate-600 bg-white"
                >
                    <option value="recommended" <?= $filters['sort'] === 'recommended' ? 'selected' : '' ?>>Recommandes</option>
                    <option value="price-asc" <?= $filters['sort'] === 'price-asc' ? 'selected' : '' ?>>Prix croissant</option>
                    <option value="rating-desc" <?= $filters['sort'] === 'rating-desc' ? 'selected' : '' ?>>Meilleures notes</option>
                </select>
            </label>
            <button
                id="applyFilters"
                type="submit"
                class="rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white"
            >
                Appliquer
            </button>
            <button
                id="resetFilters"
                type="button"
                class="text-sm text-teal-700 hover:underline pb-2"
            >
                Reinitialiser
            </button>
        </div>
    </section>

    <div id="categories" class="grid grid-cols-4 lg:grid-cols-7 gap-2 md:gap-4 mt-6">
        <?php foreach ($categoryButtons as $slug => [$label, $icon]): ?>
            <button
                type="button"
                data-category="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>"
                aria-pressed="<?= $filters['category'] === $slug ? 'true' : 'false' ?>"
                class="category-button rounded-2xl p-3 md:p-4 min-h-[100px] flex flex-col items-center justify-center gap-2 text-xs <?= $filters['category'] === $slug
                    ? 'active-category text-teal-700'
                    : 'text-slate-500' ?>"
            >
                <i data-lucide="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>" class="w-6 h-6"></i>
                <span><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
            </button>
        <?php endforeach; ?>
    </div>
</form>
<script>
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
        budget.addEventListener('input', () => value.textContent = budget.value);
        budget.addEventListener('change', () => form.requestSubmit());
        document.getElementById('sortFilter').addEventListener(
            'change',
            () => form.requestSubmit()
        );

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
</script>
