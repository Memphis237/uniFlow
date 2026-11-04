<?php
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$profile = array_merge([
    'nom' => '',
    'prenom' => '',
    'email' => '',
    'formation' => '',
    'ecole' => '',
    'bio' => '',
    'avatar' => '',
], $user ?? []);
$fullName = trim($profile['prenom'] . ' ' . $profile['nom']);
$initials = mb_strtoupper(mb_substr($profile['prenom'], 0, 1) . mb_substr($profile['nom'], 0, 1));
$avatarScheme = strtolower((string) parse_url($profile['avatar'], PHP_URL_SCHEME));
$hasSafeAvatar = $profile['avatar'] !== '' && in_array($avatarScheme, ['http', 'https'], true);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil - UniFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-50 font-sans text-slate-800 flex min-h-screen">
    <aside class="w-64 bg-white border-r border-slate-200 p-6 hidden md:flex md:flex-col md:justify-between">
        <div>
            <div class="flex items-center gap-2 mb-8">
                <i data-lucide="graduation-cap" class="w-8 h-8 text-teal-600"></i>
                <span class="text-xl font-bold text-slate-900">UniFlow</span>
            </div>
            <div class="bg-teal-50/50 p-3 rounded-xl border border-teal-100 mb-6 flex items-center gap-3">
                <div class="bg-teal-100 text-teal-700 p-2 rounded-lg"><i data-lucide="school" class="w-5 h-5"></i></div>
                <div>
                    <p class="text-xs font-medium text-slate-500">Mon campus</p>
                    <p class="text-xs font-semibold text-slate-800"><?= $escape($profile['ecole'] ?: 'À renseigner') ?></p>
                </div>
            </div>
            <nav class="space-y-1">
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Mon espace</p>
                <a href="?action=profile" class="flex items-center gap-3 px-3 py-2 text-slate-600 rounded-lg hover:bg-slate-100"><i data-lucide="compass" class="w-5 h-5"></i> Découvrir</a>
                <a href="?action=services" class="flex items-center gap-3 px-3 py-2 text-slate-600 rounded-lg hover:bg-slate-100"><i data-lucide="grid" class="w-5 h-5"></i> Tous les services</a>
                <a href="?action=favorites" class="flex items-center gap-3 px-3 py-2 text-slate-600 rounded-lg hover:bg-slate-100"><i data-lucide="heart" class="w-5 h-5"></i> Mes favoris</a>
                <a href="?action=profile" class="flex items-center gap-3 px-3 py-2 bg-teal-50 text-teal-700 font-medium rounded-lg mt-4"><i data-lucide="user" class="w-5 h-5"></i> Mon profil</a>
            </nav>
        </div>
        <div class="pt-6 border-t border-slate-100 text-xs text-slate-500">UniFlow</div>
    </aside>

    <main class="flex-1 max-w-5xl mx-auto p-5 md:p-8">
        <header class="mb-8">
            <p class="text-xs font-semibold text-teal-600 uppercase tracking-wide">Un compte, tous tes talents</p>
            <h1 class="text-3xl font-extrabold text-slate-900 mt-1">Ton espace, des deux côtés du service.</h1>
        </header>

        <?php if ($success): ?>
            <div role="status" class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800"><?= $escape($success) ?></div>
        <?php endif; ?>
        <?php if ($dbError): ?>
            <div role="alert" class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-900"><?= $escape($dbError) ?></div>
        <?php endif; ?>
        <?php if ($errors !== []): ?>
            <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800">
                <ul class="list-disc pl-5"><?php foreach ($errors as $error): ?><li><?= $escape($error) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>

        <section class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
            <div class="h-32 bg-gradient-to-r from-teal-100 to-emerald-100"></div>
            <div class="p-6 relative">
                <div class="flex justify-between items-start gap-4">
                    <div class="flex items-end -mt-16 mb-4">
                        <?php if ($hasSafeAvatar): ?>
                            <img src="<?= $escape($profile['avatar']) ?>" alt="Photo de <?= $escape($fullName) ?>" class="w-24 h-24 rounded-full border-4 border-white object-cover shadow-sm">
                        <?php else: ?>
                            <div aria-label="Avatar de <?= $escape($fullName) ?>" class="w-24 h-24 rounded-full border-4 border-white bg-teal-600 text-white flex items-center justify-center text-2xl font-bold shadow-sm"><?= $escape($initials) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if (!$dbError): ?>
                        <a href="?action=profile<?= $editing ? '' : '&amp;edit=1' ?>" class="border border-slate-300 hover:bg-slate-50 px-4 py-2 rounded-xl text-sm font-medium text-slate-700"><?= $editing ? 'Annuler' : 'Modifier le profil' ?></a>
                    <?php endif; ?>
                </div>

                <?php if ($editing && !$dbError): ?>
                    <form method="post" action="?action=profile" class="mt-2 space-y-5">
                        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block text-sm font-medium text-slate-700">Prénom
                                <input required maxlength="255" name="prenom" value="<?= $escape($profile['prenom']) ?>" autocomplete="given-name" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                            </label>
                            <label class="block text-sm font-medium text-slate-700">Nom
                                <input required maxlength="255" name="nom" value="<?= $escape($profile['nom']) ?>" autocomplete="family-name" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                            </label>
                            <label class="block text-sm font-medium text-slate-700 sm:col-span-2">Adresse e-mail
                                <input required type="email" maxlength="255" name="email" value="<?= $escape($profile['email']) ?>" autocomplete="email" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                            </label>
                            <label class="block text-sm font-medium text-slate-700">Formation
                                <input maxlength="255" name="formation" value="<?= $escape($profile['formation']) ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                            </label>
                            <label class="block text-sm font-medium text-slate-700">École
                                <input maxlength="255" name="ecole" value="<?= $escape($profile['ecole']) ?>" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                            </label>
                            <label class="block text-sm font-medium text-slate-700 sm:col-span-2">URL de la photo (facultatif)
                                <input type="url" maxlength="255" name="avatar" value="<?= $escape($profile['avatar']) ?>" placeholder="https://…" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                            </label>
                            <label class="block text-sm font-medium text-slate-700 sm:col-span-2">Biographie
                                <textarea maxlength="1000" name="bio" rows="4" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"><?= $escape($profile['bio']) ?></textarea>
                            </label>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <button type="submit" class="rounded-xl bg-teal-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-700">Enregistrer les modifications</button>
                            <a href="?action=profile" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Annuler</a>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="space-y-1">
                        <h2 class="text-2xl font-bold text-slate-900"><?= $escape($fullName ?: 'Profil utilisateur') ?></h2>
                        <?php if ($profile['formation'] !== ''): ?><p class="text-slate-500 text-sm"><?= $escape($profile['formation']) ?></p><?php endif; ?>
                        <?php if ($profile['ecole'] !== ''): ?><p class="text-teal-700 text-sm font-medium mt-1"><?= $escape($profile['ecole']) ?></p><?php endif; ?>
                        <?php if ($profile['email'] !== ''): ?><p class="text-slate-500 text-sm"><?= $escape($profile['email']) ?></p><?php endif; ?>
                        <?php if ($profile['bio'] !== ''): ?><p class="text-slate-600 text-sm mt-3 whitespace-pre-line"><?= $escape($profile['bio']) ?></p><?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
    <script>lucide.createIcons();</script>
</body>
</html>
