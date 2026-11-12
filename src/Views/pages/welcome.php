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
}if (($user['role'] ?? '') === 'admin') {
    header('Location: index.php?page=admin');
    exit;
}
$pageKey = (string)($_GET['page'] ?? 'home');
$pages = ['campus' => ['Mon campus','Bienvenue sur ton campus'],'messages' => ['Messages','Bienvenue dans tes messages'],'favorites' => ['Mes favoris','Bienvenue dans tes favoris'],'profile' => ['Mon profil','Bienvenue dans ton profil'],'help' => ['Aide & conseils','Bienvenue dans l’aide UniFlow']];
[$pageTitle,$welcomeText] = $pages[$pageKey] ?? ['UniFlow','Bienvenue sur UniFlow'];
$currentPage = $pageKey;
require __DIR__.'/../components/header.php';
require __DIR__.'/../components/navBar.php';
?>
<section class="px-5 md:px-8 py-12 max-w-4xl"><p class="text-[11px] tracking-[.22em] font-bold text-teal-700 mb-3">TON ESPACE UNIFLOW</p><h1 class="text-3xl md:text-4xl font-extrabold tracking-tight text-slate-900"><?= htmlspecialchars($welcomeText, ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars((string)($user['first_name'] ?? $user['name']), ENT_QUOTES, 'UTF-8') ?> !</h1><p class="mt-4 text-slate-500">Cette rubrique sera bientôt disponible. En attendant, découvre les talents et les services de ton campus.</p><a href="index.php?page=home" class="inline-flex mt-7 rounded-lg bg-teal-700 text-white px-5 py-3 text-sm font-semibold items-center gap-2">Retour à l’accueil <i data-lucide="arrow-right" class="w-4 h-4"></i></a></section>
<?php require __DIR__.'/../components/footer.php'; ?>
