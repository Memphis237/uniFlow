<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Services/authService.php';
require_once __DIR__ . '/../../Services/serviceCatalogService.php';

try {
    $user = auth_user_from_cookie();
} catch (Throwable $exception) {
    error_log($exception->__toString());
    http_response_code(503);
    exit('Service temporairement indisponible.');
}

if ($user === null) {
    header('Location: index.php?mode=login');
    exit;
}
if (($user['role'] ?? '') === 'admin') {
    header('Location: index.php?page=admin');
    exit;
}

$currentPage = 'favorites';
$pageTitle = 'Mes favoris';
$catalog = service_catalog_search(db(), (int)$user['Id_users'], $_GET, true, 24, true);
$services = $catalog['services'];
$serviceFilters = $catalog['filters'];
$serviceTotal = $catalog['total'];
$servicePageCount = $catalog['pageCount'];
$servicePage = $catalog['filters']['page'];
$favoriteTotal = service_catalog_search(db(), (int)$user['Id_users'], [], false, 1, true)['total'];

require __DIR__ . '/../components/header.php';
require __DIR__ . '/../components/navBar.php';
?>
<section class="px-5 md:px-8 py-8 lg:py-9 max-w-[1500px] mx-auto">
    <p class="text-[11px] tracking-[.22em] font-bold text-teal-700 mb-3">TON ESPACE UNIFLOW</p>
    <h1 class="text-3xl md:text-[38px] leading-tight font-extrabold tracking-tight text-slate-900">Mes favoris</h1>
    <p class="mt-3 text-sm text-slate-500">Retrouve ici les services que tu as enregistrés.</p>

    <?php require __DIR__ . '/../components/serviceSearch.php'; ?>
    <div class="mt-8 mb-4 flex items-center justify-between">
        <h2 class="text-lg font-bold">À retrouver quand tu veux</h2>
        <span class="text-xs text-slate-400"><?= $serviceTotal ?> service(s)</span>
    </div>
    <div id="serviceGrid" class="service-grid grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
        <?php foreach ($services as $service): ?>
            <?php require __DIR__ . '/../components/serviceCard.php'; ?>
        <?php endforeach; ?>
        <?php if ($serviceTotal === 0 && $favoriteTotal > 0): ?>
            <p id="emptyResults" class="sm:col-span-2 xl:col-span-3 py-10 text-center text-sm text-slate-500">Aucun favori ne correspond à ces filtres.</p>
        <?php endif; ?>
    </div>
    <?php if ($favoriteTotal === 0): ?>
        <div class="mt-2 rounded-2xl border border-dashed border-teal-100 bg-white/70 px-6 py-14 text-center">
            <i data-lucide="heart" class="mx-auto h-10 w-10 text-teal-700/60"></i>
            <h3 class="mt-5 text-2xl font-bold text-slate-700">Tes futurs coups de cœur t’attendent</h3>
            <p class="mt-4 text-sm text-slate-500">Clique sur le cœur d’un service pour le retrouver ici.</p>
            <a href="index.php?page=services" class="mt-8 inline-flex rounded-lg bg-teal-600 px-5 py-3 text-sm font-semibold text-white hover:bg-teal-700">Explorer les services</a>
        </div>
    <?php endif; ?>
    <?php if ($servicePageCount > 1): ?>
        <nav class="mt-8 flex items-center justify-center gap-2" aria-label="Pagination des favoris">
            <?php for ($pageNumber = 1; $pageNumber <= $servicePageCount; $pageNumber++): ?>
                <a href="index.php?<?= htmlspecialchars(http_build_query(['page' => 'favorites', 'q' => $serviceFilters['q'], 'category' => $serviceFilters['category'], 'max_price' => $serviceFilters['max_price'], 'sort' => $serviceFilters['sort'], 'page_number' => $pageNumber]), ENT_QUOTES, 'UTF-8') ?>"
                   class="rounded-lg border px-3 py-2 text-sm <?= $pageNumber === $servicePage ? 'border-teal-700 bg-teal-700 text-white' : 'border-slate-200 text-slate-600 hover:border-teal-500' ?>"
                   <?= $pageNumber === $servicePage ? 'aria-current="page"' : '' ?>><?= $pageNumber ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../components/footer.php'; ?>
