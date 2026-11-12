<?php
$userName = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['name'] ?? '')));
if ($userName === '') $userName = 'Mon profil';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f9fa">
    <title><?= htmlspecialchars($pageTitle ?? 'UniFlow', ENT_QUOTES, 'UTF-8') ?> — UniFlow</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
    <style>body{font-family:'Plus Jakarta Sans',sans-serif}.category-button{transition:background-color .18s,color .18s}.category-button:hover{background:#f0fdfa}.category-button.active-category{background:#e8f7f5;color:#008b96}</style>
</head>
<body class="bg-[#f6f9fa] text-slate-800 min-h-screen">
<header class="h-[76px] bg-white border-b border-slate-200 sticky top-0 z-30 md:pl-[260px]">
    <div class="h-full px-5 md:px-8 flex items-center justify-between gap-4">
    <div class="flex items-center gap-4">
        <span class="font-semibold text-sm"><?= htmlspecialchars($pageTitle ?? 'Découvrir', ENT_QUOTES, 'UTF-8') ?></span>
        <span class="hidden sm:block h-5 border-l border-slate-200"></span>
        <button type="button" onclick="toggleLocationModal()" class="hidden sm:flex items-center gap-2 text-sm text-slate-500">
            <i data-lucide="map-pin" class="w-4 h-4"></i> 
            <?= htmlspecialchars(trim((string)($user['city']??'').' '.(string)($user['country']??'')) ?: (trim((string)($user['adresse']??'').' '.(string)($user['postal_code']??'')) ?: 'Definir ma localisation'),ENT_QUOTES,'UTF-8') ?>
        </button>
    </div>
    <div class="flex items-center gap-4">
        <button type="button" onclick="toggleServiceModal()" class="rounded-lg border border-teal-600 px-3.5 py-2 text-sm text-teal-700 hover:bg-teal-50 flex items-center gap-2">
            <i data-lucide="plus" class="w-4 h-4"></i>
            <span class="hidden sm:inline">Proposer un service</span>
        </button>
        <button class="text-slate-500" aria-label="Notifications">
            <i data-lucide="bell" class="w-5 h-5"></i>
        </button>
        <span class="grid h-9 w-9 shrink-0 place-items-center overflow-hidden rounded-full bg-teal-100 font-bold text-teal-800">
            <?php if(!empty($user['avatar'])): ?>
                <img src="<?= htmlspecialchars((string)$user['avatar'],ENT_QUOTES,'UTF-8') ?>" alt="Photo de profil de <?= htmlspecialchars($userName,ENT_QUOTES,'UTF-8') ?>" class="h-full w-full object-cover">
            <?php else: ?>
                <?= htmlspecialchars(mb_strtoupper(mb_substr($userName, 0, 1)), ENT_QUOTES, 'UTF-8') ?>
            <?php endif; ?>
        </span>
    </div>
    </div>
</header>
<div class="flex min-h-[calc(100vh-76px)]">
