<?php
$profileLocation = implode(', ', array_filter(array_map('trim', [
    (string) ($user['adresse'] ?? ''),
    (string) ($user['postal_code'] ?? ''),
    (string) ($user['city'] ?? ''),
    (string) ($user['country'] ?? ''),
])));
$profilePhone = trim((string) ($user['phone'] ?? ''));
$mapQuery = $profileLocation !== '' ? $profileLocation : 'Campus Lyon';
$googleMapsUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($mapQuery);
$mapEmbedUrl = 'https://maps.google.com/maps?q=' . rawurlencode($mapQuery) . '&output=embed';
?>
<div
    id="locationModal"
    data-profile-location="<?= htmlspecialchars($profileLocation, ENT_QUOTES, 'UTF-8') ?>"
    class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/45 p-4 backdrop-blur-[2px]"
    role="dialog"
    aria-modal="true"
    aria-labelledby="locationModalTitle"
>
    <div class="w-full max-w-3xl overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5 md:px-7">
            <h2 id="locationModalTitle" class="text-2xl font-bold tracking-tight text-slate-800">Localisation</h2>
            <button type="button" onclick="toggleLocationModal()" aria-label="Fermer" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>
        <div class="p-5 md:p-7">
            <div class="mb-5 flex items-center gap-3">
                <span class="grid h-12 w-12 place-items-center rounded-xl bg-teal-50 text-teal-600"><i data-lucide="map-pin" class="h-5 w-5"></i></span>
                <div>
                    <p id="locationPlace" class="font-semibold text-slate-800"><?= htmlspecialchars($mapQuery, ENT_QUOTES, 'UTF-8') ?></p>
                    <p id="locationDescription" class="mt-1 text-xs text-slate-500"><?= $profileLocation !== '' ? 'Adresse issue de ton profil' : 'Campus Lyon et alentours' ?></p>
                </div>
            </div>
            <p id="locationPhone" class="<?= $profilePhone !== '' ? 'mb-3 text-sm text-slate-500' : 'hidden' ?>">Téléphone : <?= htmlspecialchars($profilePhone, ENT_QUOTES, 'UTF-8') ?></p>
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-slate-100">
                <iframe id="locationMapFrame" title="Carte de localisation" src="<?= htmlspecialchars($mapEmbedUrl, ENT_QUOTES, 'UTF-8') ?>" class="h-[300px] w-full md:h-[370px]" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
            <p class="mt-3 text-[11px] text-slate-400">Carte et itinéraire fournis par Google Maps.</p>
            <a id="locationDirections" href="<?= htmlspecialchars($googleMapsUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="mt-5 flex items-center justify-center gap-2 rounded-lg bg-teal-600 px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-teal-700">
                <i data-lucide="navigation" class="h-4 w-4"></i>Ouvrir l’itinéraire dans Google Maps<i data-lucide="arrow-right" class="ml-1 h-4 w-4"></i>
            </a>
            <?php if ($profileLocation === ''): ?>
                <a href="index.php?page=profile" class="mt-3 block text-center text-xs text-teal-700 hover:underline">Renseigner mon adresse dans le profil</a>
            <?php endif; ?>
        </div>
    </div>
</div>
