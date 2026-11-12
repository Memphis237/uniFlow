<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/database/db.php';
require_once __DIR__ . '/../src/Services/authService.php';
require_once __DIR__ . '/../src/Services/chatService.php';

if (($_GET['api'] ?? '') !== '') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $api = (string) $_GET['api'];
        if (in_array($api, ['service-create','service-like','service-detail','booking-create','review-create','contact','messages-list','message-send','messages-unread','chat-token','chat-presence'], true)) {
            $actor = auth_user_from_cookie();
            if (!$actor) { http_response_code(401); echo json_encode(['error'=>'Connecte-toi pour continuer.']); exit; }
            $pdo = db();
            if (in_array($api,['messages-list','message-send','messages-unread','chat-token','chat-presence'],true)) chat_handle_api($api,$actor,$pdo);
            if ($api==='messages-list') { $c=(int)($_GET['conversation']??0);$q=$pdo->prepare('SELECT m.Id_messages,m.content,m.created_at,m.Id_users,u.first_name,u.name FROM Messages m JOIN Users u ON u.Id_users=m.Id_users JOIN lier l ON l.Id_conversations=m.Id_conversations WHERE m.Id_conversations=:c AND EXISTS(SELECT 1 FROM lier mine WHERE mine.Id_conversations=:c2 AND mine.Id_users=:u) ORDER BY m.Id_messages');$q->execute(['c'=>$c,'c2'=>$c,'u'=>$actor['Id_users']]);echo json_encode(['messages'=>$q->fetchAll()]);exit; }
            if ($api==='message-send' && $_SERVER['REQUEST_METHOD']==='POST') { $c=(int)($_POST['conversation']??0);$content=trim((string)($_POST['content']??''));if($content===''||mb_strlen($content)>2000)throw new RuntimeException('Écris un message de 1 à 2000 caractères.');$q=$pdo->prepare('INSERT INTO Messages(content,Id_users,Id_conversations) SELECT :content,:u,:c WHERE EXISTS(SELECT 1 FROM lier WHERE Id_users=:u2 AND Id_conversations=:c2)');$q->execute(['content'=>$content,'u'=>$actor['Id_users'],'c'=>$c,'u2'=>$actor['Id_users'],'c2'=>$c]);if(!$q->rowCount())throw new RuntimeException('Discussion introuvable.');echo json_encode(['ok'=>true]);exit; }
            $serviceId = (int) ($_GET['id'] ?? $_POST['service_id'] ?? 0);
            if ($api === 'service-create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $title=trim((string)($_POST['title']??'')); $category=trim((string)($_POST['category']??'')); $description=trim((string)($_POST['description']??'')); $price=(float)($_POST['price']??-1); $unit=trim((string)($_POST['unit']??'')); $uploads=$_FILES['images']??null; $images=[];
                if ($title===''||$category===''||$description===''||$unit===''||$price<0||!$uploads||!isset($uploads['name'])||!is_array($uploads['name'])) throw new RuntimeException('Verifie les champs et ajoute au moins une image.');
                if(count($uploads['name'])<1||count($uploads['name'])>8)throw new RuntimeException('Ajoute entre 1 et 8 images.');
                $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];$finfo=new finfo(FILEINFO_MIME_TYPE);
                foreach($uploads['name'] as $i=>$originalName){$errorCode=(int)$uploads['error'][$i];if($errorCode!==UPLOAD_ERR_OK||(int)$uploads['size'][$i]>5*1024*1024)throw new RuntimeException('Chaque image doit peser au maximum 5 Mo.');$tmp=(string)$uploads['tmp_name'][$i];$mime=$finfo->file($tmp);$ext=$allowed[$mime]??null;if(!$ext)throw new RuntimeException('Formats acceptes : JPG, PNG ou WebP.');$images[]=['tmp'=>$tmp,'name'=>(string)$originalName,'size'=>(int)$uploads['size'][$i],'ext'=>$ext];}
                $cat=$pdo->prepare('SELECT Id_categories FROM Categories WHERE name=:name LIMIT 1');$cat->execute(['name'=>$category]);$categoryId=$cat->fetchColumn();if(!$categoryId){$ins=$pdo->prepare('INSERT INTO Categories(name) VALUES(:name)');$ins->execute(['name'=>$category]);$categoryId=(int)$pdo->lastInsertId();}
                $serviceLocation=implode(', ',array_filter(array_map('trim',[(string)($actor['adresse']??''),(string)($actor['postal_code']??''),(string)($actor['city']??''),(string)($actor['country']??'')])));
                if($serviceLocation==='')$serviceLocation='Campus Lyon';
                $ins=$pdo->prepare('INSERT INTO Services(title,price,description,unit,location,Id_categories,Id_users) VALUES(:title,:price,:description,:unit,:location,:category,:user)');$ins->execute(['title'=>$title,'price'=>$price,'description'=>$description,'unit'=>$unit,'location'=>$serviceLocation,'category'=>$categoryId,'user'=>$actor['Id_users']]);$newId=(int)$pdo->lastInsertId();
                $dir=__DIR__.'/uploads/services';if(!is_dir($dir)&&!mkdir($dir,0755,true))throw new RuntimeException('Impossible de preparer le stockage des images.');
                $media=$pdo->prepare('INSERT INTO Media(name_media,file_path,file_size,Id_services) VALUES(:name,:path,:size,:service)');
                foreach($images as $image){$filename=bin2hex(random_bytes(16)).'.'.$image['ext'];if(!move_uploaded_file($image['tmp'],$dir.'/'.$filename))throw new RuntimeException('Une image na pas pu etre enregistree.');$media->execute(['name'=>$image['name'],'path'=>'uploads/services/'.$filename,'size'=>$image['size'],'service'=>$newId]);}echo json_encode(['ok'=>true]);exit;
            }
            if ($api==='service-like' && $_SERVER['REQUEST_METHOD']==='POST') { $q=$pdo->prepare('INSERT IGNORE INTO Favorites(Id_services,Id_users) VALUES(:s,:u)');$q->execute(['s'=>$serviceId,'u'=>$actor['Id_users']]);if(!$q->rowCount()){$q=$pdo->prepare('DELETE FROM Favorites WHERE Id_services=:s AND Id_users=:u');$q->execute(['s'=>$serviceId,'u'=>$actor['Id_users']]);}$q=$pdo->prepare('SELECT COUNT(*) FROM Favorites WHERE Id_services=:s');$q->execute(['s'=>$serviceId]);$likes=(int)$q->fetchColumn();$q=$pdo->prepare('SELECT EXISTS(SELECT 1 FROM Favorites WHERE Id_services=:s AND Id_users=:u)');$q->execute(['s'=>$serviceId,'u'=>$actor['Id_users']]);echo json_encode(['likes'=>$likes,'liked'=>(bool)$q->fetchColumn()]);exit; }
            if ($api==='service-detail') { $q=$pdo->prepare('SELECT s.*,u.first_name,u.name,COALESCE((SELECT m.file_path FROM Media m WHERE m.Id_services=s.Id_services ORDER BY m.Id_media LIMIT 1),\'\') image,(SELECT COUNT(*) FROM Favorites f WHERE f.Id_services=s.Id_services) likes FROM Services s JOIN Users u ON u.Id_users=s.Id_users WHERE s.Id_services=:id LIMIT 1');$q->execute(['id'=>$serviceId]);$service=$q->fetch();if(!$service){http_response_code(404);echo json_encode(['error'=>'Service introuvable']);exit;}$media=$pdo->prepare('SELECT file_path FROM Media WHERE Id_services=:id ORDER BY Id_media');$media->execute(['id'=>$serviceId]);$service['images']=array_column($media->fetchAll(),'file_path');if(!$service['images']&&$service['image']!=='')$service['images']=[$service['image']];$r=$pdo->prepare('SELECT r.rating,r.comment,r.created_at,u.first_name,u.name FROM Reviews r JOIN Users u ON u.Id_users=r.Id_users JOIN Bookings b ON b.Id_bookings=r.Id_bookings WHERE b.Id_services=:id ORDER BY r.created_at DESC');$r->execute(['id'=>$serviceId]);echo json_encode(['service'=>$service,'reviews'=>$r->fetchAll()]);exit; }
            if ($api==='booking-create' && $_SERVER['REQUEST_METHOD']==='POST') { $q=$pdo->prepare('INSERT INTO Bookings(scheduled_at,message,Id_services,Id_users) SELECT :date,:message,Id_services,:user FROM Services WHERE Id_services=:service AND Id_users<>:user2');$q->execute(['date'=>$_POST['scheduled_at']??'','message'=>trim((string)($_POST['message']??'')),'user'=>$actor['Id_users'],'service'=>$serviceId,'user2'=>$actor['Id_users']]);if(!$q->rowCount())throw new RuntimeException('Date invalide ou service indisponible.');echo json_encode(['ok'=>true]);exit; }
            if ($api === 'review-create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
                $comment = trim((string) ($_POST['comment'] ?? ''));

                if ($rating === false || $rating < 1 || $rating > 5) {
                    throw new RuntimeException('Choisis une note entre 1 et 5 étoiles.');
                }
                if ($comment === '' || mb_strlen($comment) > 255) {
                    throw new RuntimeException('Ton avis doit contenir entre 1 et 255 caractères.');
                }

                $review = $pdo->prepare(
                    "INSERT INTO Reviews(rating, comment, Id_bookings, Id_users)
                    SELECT :rating, :comment, b.Id_bookings, :user
                    FROM Bookings b
                    WHERE b.Id_services = :service
                        AND b.Id_users = :reviewer
                        AND b.status = 'completed'
                        AND NOT EXISTS (
                            SELECT 1 FROM Reviews existing
                            WHERE existing.Id_bookings = b.Id_bookings
                        )
                    ORDER BY b.Id_bookings
                    LIMIT 1"
                );
                $review->execute([
                    'rating' => $rating,
                    'comment' => $comment,
                    'user' => $actor['Id_users'],
                    'service' => $serviceId,
                    'reviewer' => $actor['Id_users'],
                ]);

                if (!$review->rowCount()) {
                    throw new RuntimeException('Un avis peut être publié une seule fois après un rendez-vous terminé.');
                }
                echo json_encode(['ok' => true]);
                exit;
            }
            if ($api==='contact' && $_SERVER['REQUEST_METHOD']==='POST') { $q=$pdo->prepare('SELECT Id_users FROM Services WHERE Id_services=:id');$q->execute(['id'=>$serviceId]);$owner=(int)$q->fetchColumn();if(!$owner||$owner===(int)$actor['Id_users'])throw new RuntimeException('Contact indisponible.');$pdo->beginTransaction();try{$find=$pdo->prepare('SELECT l1.Id_conversations FROM lier l1 JOIN lier l2 ON l1.Id_conversations=l2.Id_conversations WHERE l1.Id_users=:a AND l2.Id_users=:b LIMIT 1');$find->execute(['a'=>$actor['Id_users'],'b'=>$owner]);$conversation=(int)$find->fetchColumn();if(!$conversation){$pdo->exec('INSERT INTO Conversations() VALUES()');$conversation=(int)$pdo->lastInsertId();$link=$pdo->prepare('INSERT INTO lier(Id_users,Id_conversations) VALUES(:u,:c)');$link->execute(['u'=>$actor['Id_users'],'c'=>$conversation]);$link->execute(['u'=>$owner,'c'=>$conversation]);}$pdo->commit();}catch(Throwable $e){$pdo->rollBack();throw $e;}echo json_encode(['redirect'=>'index.php?page=messages&conversation='.$conversation]);exit; }
        }
        if ($_GET['api'] === 'auth' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
            $user = auth_handle((string) ($input['action'] ?? 'login'), (string) ($input['name'] ?? ''), (string) ($input['email'] ?? ''), (string) ($input['password'] ?? ''), (string) ($input['first_name'] ?? ''));
            $token = auth_create_token($user);
            auth_set_cookie($user);
            echo json_encode(['token' => $token], JSON_THROW_ON_ERROR);
            exit;
        }
        if ($_GET['api'] === 'me' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            $user = auth_user_from_authorization();
            if ($user === null) http_response_code(401);
            echo json_encode(['user' => $user], JSON_THROW_ON_ERROR);
            exit;
        }
        http_response_code(404);
        echo json_encode(['error' => 'Requête inconnue.']);
    } catch (RuntimeException $exception) {
        http_response_code(400);
        echo json_encode(['error' => $exception->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (Throwable $exception) {
        error_log($exception->__toString());
        http_response_code(500);
        $message = ($_GET['api'] ?? '') === 'service-create'
            ? 'Le service n’a pas pu être enregistré. Vérifie les tables Services, Categories et Media dans schema.sql, puis consulte le journal PHP pour le détail.'
            : 'Une erreur est survenue. Réessayez.';
        echo json_encode(['error' => $message], JSON_INVALID_UTF8_SUBSTITUTE);
    }
    exit;
}

if (($_GET['page'] ?? '') === 'home') {
    require __DIR__ . '/../src/Views/pages/home.php';
    exit;
}
if (($_GET['page'] ?? '') === 'services') {
    require __DIR__ . '/../src/Views/pages/services.php';
    exit;
}
if (($_GET['page'] ?? '') === 'favorites') {
    require __DIR__ . '/../src/Views/pages/favorites.php';
    exit;
}
if (($_GET['page'] ?? '') === 'campus') { require __DIR__ . '/../src/Views/pages/campus.php'; exit; }
if (($_GET['page'] ?? '') === 'messages') { require __DIR__ . '/../src/Views/pages/message.php'; exit; }
if (($_GET['page'] ?? '') === 'profile') { require __DIR__ . '/../src/Views/pages/profile.php'; exit; }
if (($_GET['page'] ?? '') === 'logout') { require __DIR__ . '/../src/Services/logout.php'; exit; }
if (($_GET['page'] ?? '') === 'admin') {
    require __DIR__ . '/../src/Views/pages/admin.php';
    exit;
}
$simplePages = ['help'];
if (in_array($_GET['page'] ?? '', $simplePages, true)) {
    require __DIR__ . '/../src/Views/pages/welcome.php';
    exit;
}

$mode = (($_GET['mode'] ?? 'login') === 'register') ? 'register' : 'login';
$error = '';
$oldName = '';
$oldFirstName = '';
$oldEmail = '';
$csrf = auth_csrf_token();
$loginLockedUntil = 0;
$loginWarning = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode = (($_POST['action'] ?? '') === 'register') ? 'register' : 'login';
    $oldName = trim((string) ($_POST['name'] ?? ''));
    $oldFirstName = trim((string) ($_POST['first_name'] ?? ''));
    $oldEmail = trim((string) ($_POST['email'] ?? ''));
    try {
        if (!hash_equals($csrf, (string) ($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Votre session a expiré. Rechargez la page et réessayez.');
        }
        $user = auth_handle($mode, $oldName, $oldEmail, (string) ($_POST['password'] ?? ''), $oldFirstName);
        $loginToken = auth_create_token($user);
        auth_set_cookie($user);
        $redirectPage = ($user['role'] ?? '') === 'admin' ? 'admin' : 'home';
    } catch (RuntimeException $exception) {
        $message = $exception->getMessage();
        if ($message === 'locked:900') {
            $loginLockedUntil = auth_login_attempt_state()['locked_until'];
            $error = '';
        } elseif (str_starts_with($message, 'warning:')) {
            $loginWarning = true;
            $error = substr($message, 8);
        } else {
            $error = $message;
        }
    } catch (Throwable $exception) {
        error_log($exception->__toString());
        $error = 'Une erreur est survenue. Vérifiez la configuration de la base de données puis réessayez.';
    }
}
if ($mode === 'login' && $loginLockedUntil === 0) {
    $attemptState = auth_login_attempt_state();
    $loginLockedUntil = $attemptState['locked_until'];
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#087e8b">
    <title><?= $mode === 'register' ? 'Créer un compte' : 'Connexion' ?> — UniFlow</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="assets/css/auth.css?v=7">
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="welcome-panel" aria-label="Présentation de UniFlow">
            <a class="brand" href="index.php" aria-label="UniFlow, accueil"><img class="auth-logo" width="68" height="68" src="assets/images/UniFlow_logo1_clean.png" alt="UniFlow"></a>
            <div class="welcome-copy">
                <span class="eyebrow">LE CAMPUS, EN MOUVEMENT</span>
                <h1>Faites circuler<br>les bonnes idées.</h1>
                <p>Les talents de votre campus se rencontrent ici. Partagez, apprenez et avancez ensemble.</p>
            </div>
            <div class="campus-note"><span class="status-dot"></span> Votre communauté commence ici</div>
            <span class="decor decor-one"></span><span class="decor decor-two"></span>
        </section>

        <section class="form-panel">
            <!-- <div class="mobile-brand"><img class="auth-logo" width="68" height="68" src="assets/images/UniFlow_logo1_clean.png" alt="UniFlow"></div> -->
            <div class="form-content">
                <div class="form-heading">
                    <span class="eyebrow teal"><?= $mode === 'register' ? 'BIENVENUE DANS LA COMMUNAUTÉ' : 'HEUREUX DE VOUS REVOIR' ?></span>
                    <h2><?= $mode === 'register' ? 'Créer votre compte' : 'Connectez-vous' ?></h2>
                    <p><?= $mode === 'register' ? 'Quelques informations et vous êtes prêt à démarrer.' : 'Retrouvez votre espace UniFlow en quelques secondes.' ?></p>
                </div>

                <div class="tabs" role="tablist" aria-label="Choisir une action">
                    <a role="tab" aria-selected="<?= $mode === 'login' ? 'true' : 'false' ?>" class="tab <?= $mode === 'login' ? 'active' : '' ?>" href="index.php?mode=login">Connexion</a>
                    <a role="tab" aria-selected="<?= $mode === 'register' ? 'true' : 'false' ?>" class="tab <?= $mode === 'register' ? 'active' : '' ?>" href="index.php?mode=register">Inscription</a>
                </div>

                <button class="google-button" type="button" title="La connexion Google nécessite une configuration OAuth">
                    <span class="google-mark" aria-hidden="true">G</span>
                    <span>Continuer avec Google</span>
                </button>
                <div class="social-divider"><span>ou avec ton adresse e-mail</span></div>

                <?php if ($error !== ''): ?>
                    <div class="alert<?= $loginWarning ? ' login-warning' : '' ?>" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <form method="post" action="index.php?mode=<?= $mode ?>" class="auth-form">
                    <input type="hidden" name="action" value="<?= $mode ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <?php if ($mode === 'register'): ?>
                    <label for="first_name">Prénom</label>
                    <div class="input-wrap"><svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#91a9a9" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3.5"></circle><path d="M5 20c.8-3.3 3.2-5 7-5s6.2 1.7 7 5"></path></svg><input id="first_name" name="first_name" type="text" autocomplete="given-name" placeholder="Ex. Camille" value="<?= htmlspecialchars($oldFirstName, ENT_QUOTES, 'UTF-8') ?>" required maxlength="255"></div>
                    <label for="name">Nom</label>
                    <div class="input-wrap"><svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#91a9a9" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="3.5"></circle><path d="M5 20c.8-3.3 3.2-5 7-5s6.2 1.7 7 5"></path></svg><input id="name" name="name" type="text" autocomplete="family-name" placeholder="Ex. Martin" value="<?= htmlspecialchars($oldName, ENT_QUOTES, 'UTF-8') ?>" required maxlength="255"></div>
                    <?php endif; ?>
                    <label for="email">Adresse e-mail</label>
                    <div class="input-wrap"><svg class="input-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#91a9a9" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="5" width="17" height="14" rx="3"></rect><path d="m5 7 7 5 7-5"></path></svg><input id="email" name="email" type="email" autocomplete="email" placeholder="vous@exemple.fr" value="<?= htmlspecialchars($oldEmail, ENT_QUOTES, 'UTF-8') ?>" required maxlength="255"></div>
                    <div class="password-label"><label for="password">Mot de passe</label><?php if ($mode === 'login'): ?><span class="secure-note">Protégé et confidentiel</span><?php endif; ?></div>
                    <div class="input-wrap"><svg class="input-icon lock" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#91a9a9" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4.5" y="10" width="15" height="11" rx="2.5"></rect><path d="M8 10V7a4 4 0 0 1 8 0v3"></path><path d="M12 14v3"></path></svg><input id="password" name="password" type="password" autocomplete="<?= $mode === 'register' ? 'new-password' : 'current-password' ?>" placeholder="<?= $mode === 'register' ? '8 caractères minimum' : 'Votre mot de passe' ?>" required minlength="<?= $mode === 'register' ? '8' : '1' ?>"><button class="reveal" type="button" aria-label="Afficher le mot de passe" title="Afficher le mot de passe"><svg class="eye-open" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"></path><circle cx="12" cy="12" r="3"></circle></svg><svg class="eye-closed" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 3 18 18"></path><path d="M10.6 10.6a2 2 0 0 0 2.8 2.8"></path><path d="M9.9 5.2A10 10 0 0 1 12 5c6.4 0 10 7 10 7a16 16 0 0 1-3.1 4.1M6.2 6.2C3.5 8 2 12 2 12s3.6 7 10 7a10 10 0 0 0 4-.8"></path></svg></button></div>
                    <?php if ($mode === 'register'): ?><p class="hint">Utilisez au moins 8 caractères.</p><?php endif; ?>
                    <button class="submit-button" type="submit"><?= $mode === 'register' ? 'Créer mon compte' : 'Se connecter' ?><span aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span></button>
                </form>
                <p class="switch-copy"><?= $mode === 'register' ? 'Vous avez déjà un compte ?' : 'Vous découvrez UniFlow ?' ?> <a href="index.php?mode=<?= $mode === 'register' ? 'login' : 'register' ?>"><?= $mode === 'register' ? 'Se connecter' : 'Créer un compte' ?></a></p>
                <div class="form-footer"><span>UNIFLOW</span><span>Un campus. Mille possibilités.</span></div>
            </div>
        </section>
    </main>
    <script>
    const loginToken = <?= json_encode($loginToken ?? null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const redirectPage = <?= json_encode($redirectPage ?? 'home', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const loginLockedUntil = <?= (int) $loginLockedUntil ?>;
    if (loginToken) {
        localStorage.setItem('uniflow_token', loginToken);
        window.location.replace('index.php?page=' + redirectPage);
    }
    if (loginLockedUntil > Math.floor(Date.now() / 1000)) {
        const overlay = document.createElement('div');
        overlay.className = 'lock-modal-backdrop';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'lockModalTitle');
        overlay.innerHTML = '<section class="lock-modal"><div class="lock-icon"><i class="fa-solid fa-shield-halved"></i></div><p class="lock-eyebrow">SÉCURITÉ UNIFLOW</p><h2 id="lockModalTitle">Connexion temporairement bloquée</h2><p>Trop de tentatives incorrectes. Vous pourrez réessayer dans :</p><div class="lock-countdown" aria-live="polite"><span id="lockTimer">15:00</span></div><small>Cette mesure protège votre compte.</small></section>';
        document.body.append(overlay);
        document.querySelectorAll('.auth-form input, .auth-form button[type="submit"]').forEach(element => element.disabled = true);
        const timer = document.getElementById('lockTimer');
        const updateLockTimer = () => {
            const remaining = Math.max(0, loginLockedUntil - Math.floor(Date.now() / 1000));
            timer.textContent = String(Math.floor(remaining / 60)).padStart(2, '0') + ':' + String(remaining % 60).padStart(2, '0');
            if (remaining === 0) window.location.reload();
        };
        updateLockTimer();
        window.setInterval(updateLockTimer, 1000);
    }
    document.querySelector('.reveal')?.addEventListener('click', function () {
        const field = document.querySelector('#password');
        const shown = field.type === 'password';
        field.type = shown ? 'text' : 'password';
        this.setAttribute('aria-label', shown ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        this.setAttribute('title', shown ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        this.classList.toggle('is-visible', shown);
    });
    </script>
</body>
</html>
