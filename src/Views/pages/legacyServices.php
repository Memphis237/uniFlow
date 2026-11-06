<?php
$currentAction = $currentAction ?? 'services';
$pageTitle = $currentAction === 'favorites' ? 'Mes favoris' : 'Services';
$services = $services ?? [];
$favoriteIds = $favoriteIds ?? [];
$serviceForm = $serviceForm ?? [];
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - UniFlow</title>
    <style>
        body{font-family:Arial,sans-serif;margin:0;background:#f6f9fa;color:#17212b}
        main{max-width:1100px;margin:2rem auto;padding:0 1rem}
        header,.service-card{background:#fff;border:1px solid #dce5e8;border-radius:12px;padding:1rem}
        header,nav,.services-grid{display:flex;gap:1rem;flex-wrap:wrap}
        header{align-items:center;justify-content:space-between}
        .services-grid{margin-top:1rem}
        .service-card{box-sizing:border-box;flex:1 1 260px;max-width:340px}
        .service-card img{width:100%;height:160px;object-fit:cover;border-radius:8px}
        button,input,select,textarea{font:inherit;padding:.55rem;border:1px solid #b9c9ce;border-radius:6px}
        button{cursor:pointer;background:#087f83;color:white}
        .notice{padding:.75rem;border-radius:8px;background:#e6f5f2}
        .error{background:#fff0ee}
    </style>
</head>
<body>
<main class="container">
    <header>
        <h1><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <nav>
            <a href="index.php?page=services">Catalogue</a>
            <a href="index.php?page=favorites">Favoris</a>
        </nav>
    </header>

    <?php if (!empty($success)): ?><p class="notice" role="status"><?= htmlspecialchars((string) $success, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="notice error" role="alert"><?= htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <?php if (!empty($dbError)): ?><p class="notice error" role="alert"><?= htmlspecialchars((string) $dbError, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>

    <?php if ($currentAction === 'services'): ?>
        <p><a href="index.php?action=services&add=1">Proposer un service</a></p>
        <?php if (!empty($showCreateForm)): ?>
            <form method="post" action="index.php?action=service-create" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <p><label>Titre <input name="title" maxlength="255" required value="<?= htmlspecialchars((string) ($serviceForm['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label></p>
                <p><label>Description <textarea name="description" maxlength="255"><?= htmlspecialchars((string) ($serviceForm['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea></label></p>
                <p><label>Catégorie
                    <select name="category_id" required>
                        <option value="">Choisir</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>" <?= (int) ($serviceForm['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label></p>
                <p><label>Prix (€) <input name="price" type="number" min="0" step="0.01" required value="<?= htmlspecialchars((string) ($serviceForm['price'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label></p>
                <p><label>Lieu <input name="location" maxlength="255" value="<?= htmlspecialchars((string) ($serviceForm['location'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label></p>
                <p><label>Lien direct vers une photo <input name="photo_url" type="url" maxlength="2000" value="<?= htmlspecialchars((string) ($serviceForm['photo_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></label></p>
                <p><label>Ou importer une photo <input name="photo_file" type="file" accept="image/jpeg,image/png,image/webp,image/gif"></label></p>
                <?php foreach (($serviceFormErrors ?? []) as $formError): ?>
                    <p class="notice error"><?= htmlspecialchars((string) $formError, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endforeach; ?>
                <?php if (!empty($serviceFormError)): ?><p class="notice error"><?= htmlspecialchars((string) $serviceFormError, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                <button type="submit">Publier le service</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>

    <section aria-label="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>">
        <?php if ($services === []): ?>
            <p>Aucun service à afficher pour le moment.</p>
        <?php else: ?>
            <div class="services-grid">
                <?php foreach ($services as $service): ?>
                    <?php
                    $serviceId = (int) ($service['id'] ?? 0);
                    $imagePath = (string) ($service['photo_url'] ?? '');
                    $isFavorite = !empty($service['is_favorite']) || in_array($serviceId, $favoriteIds, true);
                    ?>
                    <article class="service-card">
                        <?php if ($imagePath !== ''): ?>
                            <img src="<?= htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8') ?>"
                                 alt="<?= htmlspecialchars((string) ($service['title'] ?? 'Service'), ENT_QUOTES, 'UTF-8') ?>">
                        <?php endif; ?>
                        <h2><?= htmlspecialchars((string) ($service['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h2>
                        <?php if (!empty($service['category'])): ?><p><?= htmlspecialchars((string) $service['category'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                        <?php if (!empty($service['provider_name'])): ?><p>Par <?= htmlspecialchars((string) $service['provider_name'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                        <p><?= htmlspecialchars((string) ($service['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
                        <?php if (isset($service['price'])): ?>
                            <p><?= htmlspecialchars((string) $service['price'], ENT_QUOTES, 'UTF-8') ?> €</p>
                        <?php endif; ?>
                        <form method="post" action="index.php?action=favorite">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="service_id" value="<?= $serviceId ?>">
                            <input type="hidden" name="return_to" value="<?= $currentAction === 'favorites' ? 'favorites' : 'services' ?>">
                            <input type="hidden" name="favorite_action" value="<?= $isFavorite ? 'remove' : 'add' ?>">
                            <button type="submit"><?= $isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris' ?></button>
                        </form>
                        <?php if (!empty($service['is_owner'])): ?>
                            <form method="post" action="index.php?action=service-delete"
                                  onsubmit="return confirm('Supprimer ce service ?');">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="service_id" value="<?= $serviceId ?>">
                                <button type="submit">Supprimer le service</button>
                            </form>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
