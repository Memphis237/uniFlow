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
$catalog = service_catalog_search(db(), (int)$user['Id_users'], $_GET, false, 24, true);
$services = $catalog['services'];
$serviceTotal = $catalog['total'];
$servicePageCount = $catalog['pageCount'];
$servicePage = $catalog['filters']['page'];

require __DIR__ . '/../components/header.php';
require __DIR__ . '/../components/navBar.php';
?>
<section class="px-5 md:px-8 py-8 lg:py-9 max-w-[1500px] mx-auto">
    <p class="text-[11px] tracking-[.22em] font-bold text-teal-700 mb-3">TON ESPACE UNIFLOW</p>
    <h1 class="text-3xl md:text-[38px] leading-tight font-extrabold tracking-tight text-slate-900">Mes favoris</h1>
    <p class="mt-3 text-sm text-slate-500">Retrouve ici les services que tu as enregistrés.</p>

    <div class="mt-8 mb-4 flex items-center justify-between">
        <h2 class="text-lg font-bold">Services enregistrés</h2>
        <span class="text-xs text-slate-400"><?= $serviceTotal ?> service(s)</span>
    </div>
    <div id="serviceGrid" class="service-grid grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
        <?php foreach ($services as $service): ?>
            <?php require __DIR__ . '/../components/serviceCard.php'; ?>
        <?php endforeach; ?>
        <p id="emptyResults" class="<?= $serviceTotal ? 'hidden' : '' ?> sm:col-span-2 xl:col-span-3 py-10 text-center text-sm text-slate-500">
            Tu n’as pas encore ajouté de service à tes favoris.
            <a href="index.php?page=services" class="font-semibold text-teal-700 hover:underline">Découvrir les services</a>
        </p>
    </div>
    <?php if ($servicePageCount > 1): ?>
        <nav class="mt-8 flex items-center justify-center gap-2" aria-label="Pagination des favoris">
            <?php for ($pageNumber = 1; $pageNumber <= $servicePageCount; $pageNumber++): ?>
                <a href="index.php?<?= htmlspecialchars(http_build_query(['page' => 'favorites', 'page_number' => $pageNumber]), ENT_QUOTES, 'UTF-8') ?>"
                   class="rounded-lg border px-3 py-2 text-sm <?= $pageNumber === $servicePage ? 'border-teal-700 bg-teal-700 text-white' : 'border-slate-200 text-slate-600 hover:border-teal-500' ?>"
                   <?= $pageNumber === $servicePage ? 'aria-current="page"' : '' ?>><?= $pageNumber ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../components/footer.php'; ?>
