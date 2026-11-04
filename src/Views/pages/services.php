<?php
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$favoritesPage = $currentAction === 'favorites';
$serviceForm = array_merge([
    'title' => '',
    'price' => '0',
    'location' => '',
    'description' => '',
    'photo_url' => '',
    'category_id' => '',
], $serviceForm ?? []);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $escape($pageTitle) ?> - UniFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-50 font-sans text-slate-800 flex min-h-screen">
    <aside class="w-64 bg-white border-r border-slate-200 p-6 hidden md:flex md:flex-col md:justify-between">
        <div>
            <a href="?action=profile" class="flex items-center gap-2 mb-8">
                <i data-lucide="graduation-cap" class="w-8 h-8 text-teal-600"></i>
                <span class="text-xl font-bold text-slate-900">UniFlow</span>
            </a>
            <nav class="space-y-1">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Mon espace</p>
                <a href="?action=services" class="flex items-center gap-3 px-3 py-2 rounded-lg <?= !$favoritesPage ? 'bg-teal-50 text-teal-700 font-medium' : 'text-slate-600 hover:bg-slate-100' ?>"><i data-lucide="grid" class="w-5 h-5"></i> Tous les services</a>
                <a href="?action=favorites" class="flex items-center gap-3 px-3 py-2 rounded-lg <?= $favoritesPage ? 'bg-teal-50 text-teal-700 font-medium' : 'text-slate-600 hover:bg-slate-100' ?>"><i data-lucide="heart" class="w-5 h-5"></i> Mes favoris</a>
                <a href="?action=profile" class="flex items-center gap-3 px-3 py-2 text-slate-600 rounded-lg hover:bg-slate-100"><i data-lucide="user" class="w-5 h-5"></i> Mon profil</a>
            </nav>
        </div>
        <p class="pt-6 border-t border-slate-100 text-xs text-slate-500">UniFlow</p>
    </aside>

    <main class="flex-1 max-w-6xl mx-auto p-5 md:p-8">
        <header class="mb-8">
            <p class="text-xs font-semibold text-teal-600 uppercase tracking-wide">Découvre les talents du campus</p>
            <h1 class="text-3xl font-extrabold text-slate-900 mt-1"><?= $escape($pageTitle) ?></h1>
            <p class="mt-2 text-slate-500"><?= $favoritesPage ? 'Retrouve ici les services que tu as enregistrés.' : 'Enregistre les services qui t’intéressent pour les retrouver facilement.' ?></p>
            <?php if (!$favoritesPage): ?>
                <a href="?action=services&amp;add=1" class="mt-5 inline-flex items-center gap-2 rounded-xl bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-700">
                    <i data-lucide="plus" class="h-4 w-4"></i>Ajouter un service
                </a>
            <?php endif; ?>
        </header>

        <?php if ($success): ?>
            <div role="status" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800"><?= $escape($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800"><?= $escape($error) ?></div>
        <?php endif; ?>
        <?php if ($dbError): ?>
            <div role="alert" class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900"><?= $escape($dbError) ?></div>
        <?php endif; ?>
        <?php if ($serviceFormError): ?>
            <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800"><?= $escape($serviceFormError) ?></div>
        <?php endif; ?>
        <?php if (!$favoritesPage && $showCreateForm): ?>
            <section id="ajouter-service" class="mb-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:p-7">
                <div class="mb-5">
                    <h2 class="text-xl font-bold text-slate-900">Publier un service</h2>
                    <p class="mt-1 text-sm text-slate-500">Présente ce que tu proposes aux autres étudiants.</p>
                </div>
                <?php if ($serviceFormErrors !== []): ?>
                    <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800">
                        <ul class="list-disc pl-5"><?php foreach ($serviceFormErrors as $formError): ?><li><?= $escape($formError) ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>
                <?php if ($categories === []): ?>
                    <p role="alert" class="mb-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900">Aucune catégorie n’est configurée. Ajoute d’abord une catégorie dans la table Categories.</p>
                <?php endif; ?>
                <form method="post" enctype="multipart/form-data" action="?action=service-create" class="grid gap-4 sm:grid-cols-2">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <label class="text-sm font-medium text-slate-700 sm:col-span-2">Nom du service *
                        <input required maxlength="255" name="title" value="<?= $escape($serviceForm['title']) ?>" placeholder="Ex. Cours particuliers de maths" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                    </label>
                    <label class="text-sm font-medium text-slate-700">Catégorie *
                        <select required name="category_id" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2">
                            <option value="">Choisir une catégorie</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $escape($category['id']) ?>" <?= (string) $serviceForm['category_id'] === (string) $category['id'] ? 'selected' : '' ?>><?= $escape($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="text-sm font-medium text-slate-700">Prix (€) *
                        <input required type="number" min="0" max="9999999999999.99" step="0.01" name="price" value="<?= $escape($serviceForm['price']) ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                    </label>
                    <label class="text-sm font-medium text-slate-700 sm:col-span-2">Description
                        <textarea maxlength="255" name="description" rows="3" placeholder="Décris ton service en quelques mots…" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"><?= $escape($serviceForm['description']) ?></textarea>
                    </label>
                    <label class="text-sm font-medium text-slate-700">Lieu
                        <input maxlength="255" name="location" value="<?= $escape($serviceForm['location']) ?>" placeholder="Ex. Campus ou en ligne" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                    </label>
                    <div class="space-y-3">
                        <label class="block text-sm font-medium text-slate-700">Photo depuis ton appareil
                            <input type="file" name="photo_file" accept="image/jpeg,image/png,image/webp,image/gif" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-teal-50 file:px-3 file:py-2 file:font-semibold file:text-teal-700">
                        </label>
                        <p class="text-xs text-slate-500">Pour une image trouvée sur Google : enregistre-la sur ton appareil, puis sélectionne-la ici. JPG, PNG, WebP ou GIF, 5 Mo max. Les liens des pages Google ne sont généralement pas des liens directs vers l’image.</p>
                        <div id="photo-preview" class="hidden overflow-hidden rounded-xl border border-slate-200">
                            <img id="photo-preview-image" alt="Aperçu de la photo choisie" class="max-h-48 w-full object-contain">
                        </div>
                        <label class="block text-sm font-medium text-slate-700">Ou lien direct d’une image publique
                            <input type="url" maxlength="2000" name="photo_url" value="<?= $escape($serviceForm['photo_url']) ?>" placeholder="https://exemple.fr/ma-photo.jpg" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        </label>
                        <p class="text-xs text-slate-500">Si tu choisis un fichier, il sera utilisé à la place du lien.</p>
                    </div>
                    <div class="flex flex-wrap gap-3 sm:col-span-2">
                        <button type="submit" <?= $categories === [] ? 'disabled' : '' ?> class="rounded-xl bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 disabled:cursor-not-allowed disabled:opacity-50">Publier le service</button>
                        <a href="?action=services" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Annuler</a>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <?php if ($services === [] && !$dbError): ?>
            <section class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-teal-50 text-teal-700"><i data-lucide="<?= $favoritesPage ? 'heart' : 'store' ?>" class="h-7 w-7"></i></div>
                <h2 class="text-lg font-semibold text-slate-900"><?= $favoritesPage ? 'Aucun favori pour le moment' : 'Aucun service disponible pour le moment' ?></h2>
                <p class="mt-2 text-sm text-slate-500"><?= $favoritesPage ? 'Parcours le catalogue et ajoute les services qui te plaisent.' : 'Les services publiés apparaîtront ici. Reviens bientôt !' ?></p>
                <?php if ($favoritesPage): ?>
                    <a href="?action=services" class="mt-5 inline-flex rounded-xl bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-700">Découvrir les services</a>
                <?php endif; ?>
            </section>
        <?php elseif ($services !== []): ?>
            <section aria-label="<?= $escape($pageTitle) ?>" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                <?php foreach ($services as $service): ?>
                    <?php
                    $photoUrl = (string) ($service['photo_url'] ?? '');
                    $photoScheme = strtolower((string) parse_url($photoUrl, PHP_URL_SCHEME));
                    $isUploadedPhoto = preg_match('~\A/uploads/services/[a-f0-9]{32}\.(?:jpg|png|webp|gif)\z~', $photoUrl) === 1;
                    $hasPhoto = $photoUrl !== '' && (in_array($photoScheme, ['http', 'https'], true) || $isUploadedPhoto);
                    ?>
                    <article class="flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="relative h-48 bg-slate-100">
                            <?php if ($hasPhoto): ?>
                                <img src="<?= $escape($photoUrl) ?>" alt="Photo du service <?= $escape($service['title']) ?>" loading="lazy" <?= $isUploadedPhoto ? '' : 'referrerpolicy="no-referrer"' ?> onerror="this.hidden=true;this.nextElementSibling.hidden=false" class="h-full w-full object-cover">
                            <?php endif; ?>
                            <div <?= $hasPhoto ? 'hidden' : '' ?> class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-sm text-slate-400">
                                <i data-lucide="image" class="h-8 w-8"></i>
                                <span>Photo du service</span>
                            </div>
                        </div>
                        <div class="flex flex-1 flex-col p-5">
                        <div class="mb-4 flex items-start justify-between gap-3">
                            <span class="rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-700"><?= $escape($service['category']) ?></span>
                            <?php if ((bool) $service['is_favorite']): ?><i data-lucide="heart" aria-label="Dans tes favoris" class="h-5 w-5 shrink-0 fill-rose-500 text-rose-500"></i><?php endif; ?>
                        </div>
                        <h2 class="text-lg font-bold text-slate-900"><?= $escape($service['title']) ?></h2>
                        <p class="mt-2 flex-1 whitespace-pre-line text-sm leading-6 text-slate-600"><?= $escape($service['description'] ?: 'Aucune description pour le moment.') ?></p>
                        <div class="mt-5 space-y-2 border-t border-slate-100 pt-4 text-sm">
                            <p class="font-semibold text-slate-900"><?= (float) $service['price'] > 0 ? $escape(number_format((float) $service['price'], 2, ',', ' ')) . ' €' : 'Gratuit' ?></p>
                            <?php if ($service['location']): ?><p class="flex items-center gap-2 text-slate-500"><i data-lucide="map-pin" class="h-4 w-4"></i><?= $escape($service['location']) ?></p><?php endif; ?>
                            <p class="flex items-center gap-2 text-slate-500"><i data-lucide="user-round" class="h-4 w-4"></i>Proposé par <?= $escape($service['provider_name']) ?></p>
                        </div>
                        <form method="post" action="?action=favorite" class="mt-5">
                            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                            <input type="hidden" name="service_id" value="<?= $escape($service['id']) ?>">
                            <input type="hidden" name="return_to" value="<?= $favoritesPage ? 'favorites' : 'services' ?>">
                            <?php if ((bool) $service['is_favorite']): ?>
                                <input type="hidden" name="favorite_action" value="remove">
                                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl border border-rose-200 px-4 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50"><i data-lucide="heart-off" class="h-4 w-4"></i>Retirer des favoris</button>
                            <?php else: ?>
                                <input type="hidden" name="favorite_action" value="add">
                                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-700"><i data-lucide="heart" class="h-4 w-4"></i>Ajouter aux favoris</button>
                            <?php endif; ?>
                        </form>
                        <?php if ((bool) $service['is_owner']): ?>
                            <form method="post" action="?action=service-delete" class="mt-2" onsubmit="return confirm('Supprimer définitivement ce service ?');">
                                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                                <input type="hidden" name="service_id" value="<?= $escape($service['id']) ?>">
                                <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50"><i data-lucide="trash-2" class="h-4 w-4"></i>Supprimer mon service</button>
                            </form>
                        <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
    <script>
        lucide.createIcons();
        const photoInput = document.querySelector('input[name="photo_file"]');
        if (photoInput) {
            let previewUrl = null;
            photoInput.addEventListener('change', () => {
                const file = photoInput.files[0];
                const preview = document.getElementById('photo-preview');
                const image = document.getElementById('photo-preview-image');
                if (previewUrl) {
                    URL.revokeObjectURL(previewUrl);
                    previewUrl = null;
                }
                if (!file) {
                    preview.classList.add('hidden');
                    image.removeAttribute('src');
                    return;
                }
                previewUrl = URL.createObjectURL(file);
                image.src = previewUrl;
                preview.classList.remove('hidden');
            });
        }
    </script>
</body>
</html>
