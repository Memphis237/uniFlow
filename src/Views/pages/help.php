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

$currentPage = 'help';
$pageTitle = 'Aide & conseils';

require __DIR__ . '/../components/header.php';
require __DIR__ . '/../components/navBar.php';
?>
<section class="mx-auto max-w-6xl px-5 py-8 md:px-8 md:py-12">
    <div class="max-w-3xl">
        <p class="mb-3 text-[11px] font-bold uppercase tracking-[.22em] text-teal-700">
            CENTRE D’AIDE UNIFLOW
        </p>
        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 md:text-4xl">
            Aide & conseils
        </h1>
        <p class="mt-4 text-base leading-7 text-slate-600">
            Les repères essentiels pour trouver un service, proposer ton savoir-faire et
            profiter de la vie du campus en toute confiance.
        </p>
    </div>

    <div class="mt-8 grid gap-4 md:grid-cols-2">
        <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4 grid h-11 w-11 place-items-center rounded-xl bg-teal-50 text-teal-700">
                <i data-lucide="search" class="h-5 w-5" aria-hidden="true"></i>
            </div>
            <h2 class="text-lg font-bold text-slate-900">Trouver le bon service</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">
                Parcours les services ou utilise la recherche, les catégories et les filtres
                pour préciser ton besoin. Enregistre les offres qui t’intéressent dans tes
                favoris pour les retrouver facilement.
            </p>
            <a href="index.php?page=services" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-teal-700 hover:text-teal-800">
                Parcourir les services
                <i data-lucide="arrow-right" class="h-4 w-4" aria-hidden="true"></i>
            </a>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4 grid h-11 w-11 place-items-center rounded-xl bg-cyan-50 text-cyan-700">
                <i data-lucide="handshake" class="h-5 w-5" aria-hidden="true"></i>
            </div>
            <h2 class="text-lg font-bold text-slate-900">Demander une prestation</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">
                Ouvre une offre pour consulter ses détails, puis contacte la personne ou
                envoie une demande de rendez-vous. Indique clairement tes disponibilités et
                échange dans la messagerie pour convenir des modalités.
            </p>
            <a href="index.php?page=messages" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-teal-700 hover:text-teal-800">
                Ouvrir la messagerie
                <i data-lucide="arrow-right" class="h-4 w-4" aria-hidden="true"></i>
            </a>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4 grid h-11 w-11 place-items-center rounded-xl bg-violet-50 text-violet-700">
                <i data-lucide="store" class="h-5 w-5" aria-hidden="true"></i>
            </div>
            <h2 class="text-lg font-bold text-slate-900">Proposer ton savoir-faire</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">
                Depuis le bouton « Proposer un service », choisis une catégorie et décris
                concrètement ce que tu proposes. Un tarif, des informations précises et une
                photo adaptée aident les autres étudiants à comprendre ton offre.
            </p>
            <a href="index.php?page=profile" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-teal-700 hover:text-teal-800">
                Gérer mon profil
                <i data-lucide="arrow-right" class="h-4 w-4" aria-hidden="true"></i>
            </a>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4 grid h-11 w-11 place-items-center rounded-xl bg-amber-50 text-amber-700">
                <i data-lucide="calendar-days" class="h-5 w-5" aria-hidden="true"></i>
            </div>
            <h2 class="text-lg font-bold text-slate-900">Participer à la vie du campus</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">
                Consulte les événements et les publications du campus. Vérifie les
                informations pratiques avant de participer et garde les échanges respectueux
                et utiles pour toute la communauté.
            </p>
            <a href="index.php?page=campus" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-teal-700 hover:text-teal-800">
                Découvrir le campus
                <i data-lucide="arrow-right" class="h-4 w-4" aria-hidden="true"></i>
            </a>
        </article>
    </div>

    <section class="mt-8 rounded-2xl border border-teal-100 bg-teal-50 p-6 md:p-8" aria-labelledby="safety-title">
        <div class="flex items-start gap-4">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-white text-teal-700">
                <i data-lucide="shield-check" class="h-5 w-5" aria-hidden="true"></i>
            </span>
            <div>
                <h2 id="safety-title" class="text-lg font-bold text-slate-900">
                    Quelques réflexes de sécurité
                </h2>
                <ul class="mt-3 space-y-2 text-sm leading-6 text-slate-700">
                    <li>Ne partage pas ton mot de passe ni tes informations bancaires dans les messages.</li>
                    <li>Clarifie le prix, le lieu et les disponibilités avant de confirmer un rendez-vous.</li>
                    <li>Choisis un lieu adapté et préviens une personne de confiance si nécessaire.</li>
                    <li>Déconnecte-toi après avoir utilisé un appareil partagé.</li>
                </ul>
            </div>
        </div>
    </section>

    <div class="mt-8 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-6">
        <div>
            <h2 class="font-bold text-slate-900">Tu ne sais pas par où commencer ?</h2>
            <p class="mt-1 text-sm text-slate-600">Découvre les offres disponibles près de toi.</p>
        </div>
        <a href="index.php?page=home" class="inline-flex items-center gap-2 rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">
            Explorer UniFlow
            <i data-lucide="arrow-right" class="h-4 w-4" aria-hidden="true"></i>
        </a>
    </div>
</section>
<?php require __DIR__ . '/../components/footer.php'; ?>
