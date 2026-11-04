<?php
declare(strict_types=1);

require_once __DIR__.'/authService.php';

$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
setcookie('uniflow_token','',['expires'=>time()-3600,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html><html lang="fr"><meta charset="utf-8"><title>Déconnexion</title><body><script>localStorage.removeItem('uniflow_token');window.location.replace('index.php?mode=login');</script><noscript><a href="index.php?mode=login">Continuer vers la connexion</a></noscript></body></html>
