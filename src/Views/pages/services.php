<?php
declare(strict_types=1);
require_once __DIR__ . '/../../Services/authService.php';
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
$currentPage = 'services';
$pageTitle = 'Tous les services';
require_once __DIR__ . '/../../Services/serviceCatalogService.php';
$catalog = service_catalog_search(db(), (int)$user['Id_users'], $_GET, true, 24);
$services = $catalog['services'];
$serviceFilters = $catalog['filters'];
$serviceTotal = $catalog['total'];
$servicePageCount = $catalog['pageCount'];
require __DIR__ . '/../components/header.php';
require __DIR__ . '/../components/navBar.php';
?>
<section class="px-5 md:px-8 py-8 lg:py-9 max-w-[1500px] mx-auto">
 <p class="text-[11px] tracking-[.22em] font-bold text-teal-700 mb-3">LE CAMPUS, EN MIEUX</p>
 <h1 class="text-3xl md:text-[38px] leading-tight font-extrabold tracking-tight text-slate-900">Un talent pour chaque besoin.</h1>
 <p class="mt-3 text-sm text-slate-500">Des talents à deux pas. Des petits prix. Et une communauté qui s’entraide.</p>
 <div class="mt-10"><h2 class="text-2xl font-bold tracking-tight">De quoi as-tu besoin ?</h2><p class="mt-2 text-sm text-slate-500">Les bons talents, juste à côté.</p></div>
 <?php require __DIR__ . '/../components/serviceSearch.php'; ?>
 <div class="mt-7 mb-4 flex items-center justify-between"><h2 class="text-lg font-bold">Les talents à découvrir</h2><span class="text-xs text-slate-400 flex items-center gap-2"><i class="w-2 h-2 bg-emerald-500 rounded-full"></i><?= (int)$serviceTotal ?> résultat(s)</span></div>
 <div id="serviceGrid" class="service-grid grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
 <?php foreach ($services as $service): ?>
 <?php require __DIR__ . '/../components/serviceCard.php'; ?>
 <?php endforeach; ?>
 <p id="emptyResults" class="<?= $serviceTotal ? 'hidden' : '' ?> sm:col-span-2 xl:col-span-3 py-10 text-center text-sm text-slate-500">Aucun service ne correspond à ces filtres.</p>
 </div>
 <?php if ($servicePageCount > 1): ?><nav class="mt-8 flex items-center justify-center gap-2" aria-label="Pagination des services"><?php for ($pageNumber = 1;$pageNumber <= $servicePageCount;$pageNumber++):$pageParams = ['page' => 'services','q' => $serviceFilters['q'],'category' => $serviceFilters['category'],'max_price' => $serviceFilters['max_price'],'sort' => $serviceFilters['sort'],'page_number' => $pageNumber]; ?><a href="index.php?<?= htmlspecialchars(http_build_query($pageParams), ENT_QUOTES, 'UTF-8') ?>" class="rounded-lg border px-3 py-2 text-sm <?= $pageNumber === $serviceFilters['page'] ? 'border-teal-700 bg-teal-700 text-white' : 'border-slate-200 text-slate-600 hover:border-teal-500' ?>" <?= $pageNumber === $serviceFilters['page'] ? 'aria-current="page"' : '' ?>><?= $pageNumber ?></a><?php endfor; ?></nav><?php endif; ?>
</section>
<?php require __DIR__ . '/../components/footer.php'; ?>
