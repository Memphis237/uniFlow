<?php
declare(strict_types=1);
require_once __DIR__ . '/../../Services/authService.php';
try { $user = auth_user_from_cookie(); } catch (Throwable $e) { http_response_code(503); exit('Service temporairement indisponible.'); }
if (!$user) { header('Location: index.php?mode=login'); exit; }
if (($user['role'] ?? '') === 'admin') { header('Location: index.php?page=admin'); exit; }
$csrfToken = auth_csrf_token();
$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS Campus_posts (Id_posts INT AUTO_INCREMENT PRIMARY KEY, content TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, Id_users INT NOT NULL, FOREIGN KEY (Id_users) REFERENCES Users(Id_users) ON DELETE CASCADE, INDEX (created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$columns = $pdo->query('SHOW COLUMNS FROM Events')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('published_until', $columns, true)) $pdo->exec('ALTER TABLE Events ADD published_until DATETIME NULL');
foreach (['address'=>'VARCHAR(255) NULL','postal_code'=>'VARCHAR(20) NULL','city'=>'VARCHAR(120) NULL','country'=>'VARCHAR(120) NULL'] as $column=>$definition) {
    if (!in_array($column, $columns, true)) $pdo->exec("ALTER TABLE Events ADD {$column} {$definition}");
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) throw new RuntimeException('Ta session a expiré. Recharge la page et réessaie.');
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'campus-post') {
            $content = trim((string)($_POST['content'] ?? ''));
            if ($content === '' || mb_strlen($content) > 2000) throw new RuntimeException('La publication doit contenir entre 1 et 2 000 caractères.');
            $q = $pdo->prepare('INSERT INTO Campus_posts(content,Id_users) VALUES(:content,:user)');
            $q->execute(['content' => $content, 'user' => $user['Id_users']]);
        } elseif ($action === 'campus-event') {
            $title = trim((string)($_POST['title'] ?? '')); $description = trim((string)($_POST['description'] ?? ''));
            $address = trim((string)($_POST['address'] ?? '')); $postalCode = trim((string)($_POST['postal_code'] ?? ''));
            $city = trim((string)($_POST['city'] ?? '')); $country = trim((string)($_POST['country'] ?? '')); $starts = trim((string)($_POST['starts_at'] ?? '')); $ends = trim((string)($_POST['ends_at'] ?? ''));
            $duration = (int)($_POST['duration'] ?? 24);
            $imageUpload = $_FILES['event_image'] ?? null; $imageFile = null;
            if ($title === '' || mb_strlen($title) > 255 || $description === '' || mb_strlen($description) > 4000 || $address === '' || $city === '' || $country === '' || mb_strlen($address)>255 || mb_strlen($postalCode)>20 || mb_strlen($city)>120 || mb_strlen($country)>120 || !in_array($duration, [24,48,72], true)) throw new RuntimeException('Vérifie les informations de l’événement et son adresse.');
            $date = DateTime::createFromFormat('Y-m-d\\TH:i', $starts);
            $endDate = DateTime::createFromFormat('Y-m-d\\TH:i', $ends);
            if (!$date || $date <= new DateTime()) throw new RuntimeException('Choisis une date future pour l’événement.');
            if (!$endDate || $endDate <= $date) throw new RuntimeException('L’heure de fin doit être après l’heure de début.');
            if ($imageUpload && (int)($imageUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if ((int)$imageUpload['error'] !== UPLOAD_ERR_OK || (int)$imageUpload['size'] > 5 * 1024 * 1024) throw new RuntimeException('L’image doit peser au maximum 5 Mo.');
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string)$imageUpload['tmp_name']);
                $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                if (!isset($extensions[$mime])) throw new RuntimeException('Formats acceptés : JPG, PNG ou WebP.');
                $imageFile = ['tmp'=>$imageUpload['tmp_name'],'name'=>basename((string)$imageUpload['name']),'size'=>(int)$imageUpload['size'],'ext'=>$extensions[$mime]];
            }
            $publishedUntil = (new DateTime())->modify('+' . $duration . ' hours')->format('Y-m-d H:i:s');
            $location = implode(', ', array_filter([$address,$postalCode,$city,$country]));
            $q = $pdo->prepare("INSERT INTO Events(title,description,location,address,postal_code,city,country,starts_at,ends_at,status,Id_users,published_until) VALUES(:title,:description,:location,:address,:postal_code,:city,:country,:starts,:ends,'published',:user,:published_until)");
            $q->execute(['title'=>$title,'description'=>$description,'location'=>$location,'address'=>$address,'postal_code'=>$postalCode,'city'=>$city,'country'=>$country,'starts'=>$date->format('Y-m-d H:i:s'),'ends'=>$endDate->format('Y-m-d H:i:s'),'user'=>(int)$user['Id_users'],'published_until'=>$publishedUntil]);
            $newEventId=(int)$pdo->lastInsertId();
            if ($imageFile) {
                $uploadDir=dirname(__DIR__,3).'/public/uploads/events';
                if (!is_dir($uploadDir) && !mkdir($uploadDir,0755,true)) throw new RuntimeException('Le stockage des images est indisponible.');
                $filename=bin2hex(random_bytes(20)).'.'.$imageFile['ext'];
                if (!move_uploaded_file($imageFile['tmp'],$uploadDir.'/'.$filename)) throw new RuntimeException('L’image de l’événement n’a pas pu être enregistrée.');
                try {
                    $media=$pdo->prepare('INSERT INTO Media(name_media,file_path,file_size,Id_events) VALUES(:name,:path,:size,:event)');
                    $media->execute(['name'=>$imageFile['name'],'path'=>'uploads/events/'.$filename,'size'=>$imageFile['size'],'event'=>$newEventId]);
                } catch (Throwable $e) { @unlink($uploadDir.'/'.$filename); throw $e; }
            }
        } elseif ($action === 'campus-join-event') {
            $eventId=(int)($_POST['event_id']??0);
            $q=$pdo->prepare("INSERT IGNORE INTO Events_participants(Id_events,Id_users) SELECT Id_events,:user FROM Events WHERE Id_events=:event AND starts_at>=NOW() AND status NOT IN ('draft','cancelled','canceled')");
            $q->execute(['user'=>(int)$user['Id_users'],'event'=>$eventId]);
        } elseif ($action === 'campus-leave-event') {
            $q=$pdo->prepare('DELETE FROM Events_participants WHERE Id_events=:event AND Id_users=:user');
            $q->execute(['event'=>(int)($_POST['event_id']??0),'user'=>(int)$user['Id_users']]);
        } else throw new RuntimeException('Action inconnue.');
        header('Location: index.php?page=campus#' . (in_array($action, ['campus-event','campus-join-event','campus-leave-event'], true) ? 'events' : 'posts')); exit;
    } catch (RuntimeException $e) { $error = $e->getMessage(); }
}
$events = $posts = [];
try { $q=$pdo->prepare("SELECT e.*,u.first_name,u.name,u.avatar, (SELECT m.file_path FROM Media m WHERE m.Id_events=e.Id_events ORDER BY m.Id_media DESC LIMIT 1) event_image,COUNT(ep.Id_users) participants,MAX(ep.Id_users=:viewer) joined FROM Events e JOIN Users u ON u.Id_users=e.Id_users LEFT JOIN Events_participants ep ON ep.Id_events=e.Id_events WHERE e.starts_at>=NOW() AND e.status NOT IN ('draft','cancelled','canceled') AND (e.published_until IS NULL OR e.published_until>NOW()) GROUP BY e.Id_events ORDER BY e.starts_at LIMIT 12"); $q->execute(['viewer'=>(int)$user['Id_users']]); $events=$q->fetchAll(); } catch (Throwable $e) { error_log($e->__toString()); }
try { $q=$pdo->query('SELECT p.content,p.created_at,u.first_name,u.name,u.avatar FROM Campus_posts p JOIN Users u ON u.Id_users=p.Id_users ORDER BY p.created_at DESC LIMIT 50'); $posts=$q->fetchAll(); } catch (Throwable $e) { error_log($e->__toString()); }
$currentPage='campus'; $pageTitle='Mon campus';
require __DIR__.'/../components/header.php'; require __DIR__.'/../components/navBar.php';
?>
<main class="px-5 md:px-8 py-8 max-w-[1500px] mx-auto">
    <p class="text-[11px] tracking-[.22em] font-bold text-teal-700 mb-3">LA VIE, ENSEMBLE</p>
    <h1 class="text-3xl md:text-[36px] font-extrabold tracking-tight text-slate-900">Ton campus a plein de choses à partager.</h1>
    <p class="mt-3 text-sm text-slate-500">Les rendez-vous de l’école, les actus du BDE et les petits moments entre étudiants.</p>
    <?php if($error): ?>
    <p class="mt-5 rounded-xl bg-rose-50 p-4 text-sm text-rose-700" role="alert">
    <?= htmlspecialchars($error,ENT_QUOTES,'UTF-8') ?>
    </p>
    <?php endif; ?>
    <section class="mt-8 rounded-2xl border border-teal-100 bg-[#e2f4f1] p-7 md:p-9 flex items-center gap-6">
    <span class="rounded-2xl bg-white p-5 text-teal-600">
    <i data-lucide="graduation-cap" class="h-10 w-10">
    </i>
    </span>
    <div>
    <p class="text-[11px] font-bold tracking-[.2em] text-teal-700">TON ÉCOLE CONNECTÉE</p>
    <h2 class="mt-2 text-3xl font-bold">Mon campus</h2>
    <p class="mt-2 text-slate-500">Une communauté étudiante qui partage et se retrouve.</p>
    </div>
    <span class="ml-auto hidden sm:flex items-center gap-2 text-sm text-teal-700">
    <i data-lucide="shield-check" class="h-5 w-5">
    </i> Communauté UniFlow</span>
    </section>
    <section id="events" class="mt-10">
    <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
    <h2 class="text-2xl font-bold">Les prochains rendez-vous</h2>
    <p class="mt-2 text-sm text-slate-500">Fais des rencontres, pas seulement des réservations.</p>
    </div>
    <button type="button" data-open-event class="rounded-lg bg-teal-600 px-4 py-3 text-sm font-semibold text-white">
    <i data-lucide="plus" class="mr-1 inline h-4 w-4">
    </i>Créer un événement</button>
    </div>
    <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    <?php foreach($events as $i=>$event): $eventData=['id'=>(int)$event['Id_events'],'title'=>$event['title'],'description'=>$event['description'],'organizer'=>$event['first_name'].' '.$event['name'],'starts_at'=>$event['starts_at'],'ends_at'=>$event['ends_at']??'','address'=>$event['address']??'','postal_code'=>$event['postal_code']??'','city'=>$event['city']??'','country'=>$event['country']??'','image'=>$event['event_image']??'','avatar'=>$event['avatar']??'','initial'=>mb_strtoupper(mb_substr(trim($event['first_name']?:$event['name']),0,1)),'color'=>['from-teal-500 to-cyan-600','from-violet-500 to-indigo-600','from-amber-500 to-orange-600','from-rose-500 to-pink-600','from-sky-500 to-blue-600'][(int)$event['Id_events']%5],'participants'=>(int)$event['participants'],'joined'=>(bool)$event['joined']]; $eventJson=htmlspecialchars(json_encode($eventData,JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_TAG|JSON_HEX_AMP),ENT_QUOTES,'UTF-8'); ?>
    <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
    <button type="button" data-event-open="<?= $eventJson ?>" class="group block w-full text-left">
    <div class="relative grid h-48 place-items-center overflow-hidden bg-gradient-to-br <?= htmlspecialchars($eventData['color'],ENT_QUOTES,'UTF-8') ?>">
    <?php if($eventData['image']): ?>
    <img class="absolute inset-0 h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]" src="<?= htmlspecialchars($eventData['image'],ENT_QUOTES,'UTF-8') ?>" alt="Image de l’événement">
    <?php elseif($eventData['avatar']): ?>
    <img class="absolute inset-0 h-full w-full object-cover opacity-80 transition duration-300 group-hover:scale-[1.03]" src="<?= htmlspecialchars($eventData['avatar'],ENT_QUOTES,'UTF-8') ?>" alt="Photo de l’organisateur">
    <?php else: ?>
    <span class="text-7xl font-bold text-white/90">
    <?= htmlspecialchars($eventData['initial'],ENT_QUOTES,'UTF-8') ?>
    </span>
    <?php endif; ?>
    <span class="absolute left-4 top-4 rounded-lg bg-white px-3 py-2 text-center text-teal-800 shadow">
    <b class="block whitespace-nowrap text-sm font-bold">
    <?= date('d M',strtotime($event['starts_at'])) ?>
    </b>
    </span>
    <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-slate-950/70 to-transparent px-4 pb-4 pt-10 text-sm font-medium text-white">Vie étudiante</span>
    </div>
    <div class="p-5">
    <small class="inline-flex items-center gap-1.5 font-semibold text-teal-800">
    <i class="fa-solid fa-user" aria-hidden="true">
    </i>
    <?= htmlspecialchars($eventData['organizer'],ENT_QUOTES,'UTF-8') ?>
    </small>
    <h3 class="mt-3 text-xl font-bold text-slate-900">
    <?= htmlspecialchars($event['title'],ENT_QUOTES,'UTF-8') ?>
    </h3>
    <p class="mt-5 flex items-center gap-2 text-sm text-slate-500">
    <i class="fa-solid fa-location-dot w-4 text-center text-slate-400" aria-hidden="true">
    </i>
    <?= htmlspecialchars($eventData['city']?:$eventData['address']?:'Lieu à préciser',ENT_QUOTES,'UTF-8') ?>
    </p>
    <p class="mt-2 flex items-center gap-2 text-sm text-slate-500">
    <i class="fa-regular fa-clock w-4 text-center text-slate-400" aria-hidden="true">
    </i>
    <?= date('H:i',strtotime($event['starts_at'])) ?>
    <?= !empty($event['ends_at'])?' - '.date('H:i',strtotime($event['ends_at'])):'' ?>
    </p>
    <div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4 text-sm">
    <span class="flex items-center gap-2 text-slate-500">
    <i class="fa-solid fa-user-group text-slate-400" aria-hidden="true">
    </i>
    <?= (int)$event['participants'] ?> participant<?= (int)$event['participants']>1?'s':'' ?>
    </span>
    <span class="flex items-center gap-1 font-medium text-teal-700">Découvrir <i class="fa-solid fa-arrow-right text-xs" aria-hidden="true">
    </i>
    </span>
    </div>
    </div>
    </button>
    </article>
    <?php endforeach; ?>
    <?php if(!$events): ?>
    <p class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-sm text-slate-500 md:col-span-2 xl:col-span-3">Aucun événement publié pour le moment. Lance le prochain rendez-vous !</p>
    <?php endif; ?>
    </div>
    </section>
    <section id="posts" class="mt-12">
    <h2 class="text-2xl font-bold">La voix du campus</h2>
    <form method="post" class="mt-5 rounded-2xl border border-slate-200 bg-white p-5">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken,ENT_QUOTES,'UTF-8') ?>">
    <input type="hidden" name="action" value="campus-post">
    <label class="sr-only" for="campusPost">Ta publication</label>
    <div class="flex flex-col gap-3 sm:flex-row">
    <textarea id="campusPost" name="content" rows="2" maxlength="2000" required placeholder="Un bon plan, une question, une envie de partager ?" class="min-w-0 flex-1 resize-y rounded-xl border border-slate-200 px-4 py-3 outline-none focus:border-teal-500">
    </textarea>
    <button class="self-end rounded-lg bg-teal-600 px-5 py-3 text-sm font-semibold text-white sm:self-center">
    <i data-lucide="send" class="mr-2 inline h-4 w-4">
    </i>Publier</button>
    </div>
    </form>
    <div class="mt-5 space-y-4">
    <?php foreach($posts as $post): ?>
    <article class="rounded-2xl border border-slate-200 bg-white p-6">
    <div class="flex items-center gap-3">
    <span class="grid h-11 w-11 place-items-center overflow-hidden rounded-xl bg-teal-50 text-teal-700">
    <?php if($post['avatar']): ?>
    <img src="<?= htmlspecialchars($post['avatar'],ENT_QUOTES,'UTF-8') ?>" class="h-full w-full object-cover" alt="">
    <?php else: ?>
    <i data-lucide="user-round" class="h-5 w-5">
    </i>
    <?php endif; ?>
    </span>
    <div>
    <b>
    <?= htmlspecialchars($post['first_name'].' '.$post['name'],ENT_QUOTES,'UTF-8') ?>
    </b>
    <small class="mt-1 block text-slate-400">
    <?= htmlspecialchars(date('d/m/Y à H:i',strtotime($post['created_at'])),ENT_QUOTES,'UTF-8') ?> · Campus</small>
    </div>
    </div>
    <p class="mt-5 whitespace-pre-wrap text-slate-600">
    <?= htmlspecialchars($post['content'],ENT_QUOTES,'UTF-8') ?>
    </p>
    </article>
    <?php endforeach; ?>
    <?php if(!$posts): ?>
    <p class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-sm text-slate-500">Pas encore de publication. Partage la première avec le campus !</p>
    <?php endif; ?>
    </div>
    </section>
</main>
<div id="eventModal" class="fixed inset-0 z-[70] hidden items-center justify-center overflow-y-auto bg-slate-950/50 p-4 backdrop-blur-sm">
    <form method="post" enctype="multipart/form-data" class="my-auto w-full max-w-xl space-y-4 rounded-2xl bg-white p-6 shadow-2xl">
    <div class="flex items-center justify-between">
    <h2 class="text-xl font-bold">Créer un événement</h2>
    <button type="button" data-close-event class="rounded-lg p-2 text-slate-500" aria-label="Fermer">
    <i class="fa-solid fa-xmark" aria-hidden="true">
    </i>
    </button>
    </div>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken,ENT_QUOTES,'UTF-8') ?>">
    <input type="hidden" name="action" value="campus-event">
    <label class="block text-sm">Nom de l’événement<input name="title" maxlength="255" required class="mt-1 w-full rounded-lg border border-slate-200 p-3">
    </label>
    <label class="block text-sm">Description<textarea name="description" maxlength="4000" rows="3" required class="mt-1 w-full rounded-lg border border-slate-200 p-3">
    </textarea>
    </label>
    <label class="block cursor-pointer rounded-xl border border-dashed border-slate-300 p-4 text-sm hover:border-teal-500">
    <span class="flex items-center gap-2 font-semibold">
    <i data-lucide="image-plus" class="h-4 w-4 text-teal-700">
    </i>Image de l’événement <span class="font-normal text-slate-400">(facultative)</span>
    </span>
    <small class="mt-1 block text-slate-500">JPG, PNG ou WebP · 5 Mo maximum. Sans image, la photo de profil ou une initiale colorée sera utilisée.</small>
    <input name="event_image" type="file" accept="image/jpeg,image/png,image/webp" class="mt-3 block w-full text-xs text-slate-500">
    </label>
    <div class="grid gap-3 sm:grid-cols-2">
    <label class="text-sm">Adresse<input name="address" maxlength="255" required placeholder="12 rue de l’Université" class="mt-1 w-full rounded-lg border border-slate-200 p-3">
    </label>
    <label class="text-sm">Code postal<input name="postal_code" maxlength="20" placeholder="69007" class="mt-1 w-full rounded-lg border border-slate-200 p-3">
    </label>
    <label class="text-sm">Ville<input name="city" maxlength="120" required placeholder="Lyon" class="mt-1 w-full rounded-lg border border-slate-200 p-3">
    </label>
    <label class="text-sm">Pays<input name="country" maxlength="120" required value="France" class="mt-1 w-full rounded-lg border border-slate-200 p-3">
    </label>
    <label class="text-sm">Début<input name="starts_at" type="datetime-local" required class="mt-1 w-full rounded-lg border border-slate-200 p-3">
    </label>
    <label class="text-sm">Fin<input name="ends_at" type="datetime-local" required class="mt-1 w-full rounded-lg border border-slate-200 p-3">
    </label>
    </div>
    <label class="block text-sm">Durée de publication<select name="duration" class="mt-1 w-full rounded-lg border border-slate-200 p-3">
    <option value="24">24 heures</option>
    <option value="48">48 heures</option>
    <option value="72">72 heures (maximum)</option>
    </select>
    </label>
    <div class="flex justify-end gap-3">
    <button type="button" data-close-event class="rounded-lg border px-4 py-2">Annuler</button>
    <button class="rounded-lg bg-teal-600 px-5 py-2 font-semibold text-white">Publier l’événement</button>
    </div>
    </form>
    </div>
<div id="eventDetailModal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-slate-950/50 p-3 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="eventDetailTitle">
    <article class="max-h-[94vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
    <div class="sticky top-0 z-10 flex items-center justify-between bg-white px-6 py-4">
    <h2 class="text-xl font-bold">Un rendez-vous à ne pas manquer</h2>
    <button type="button" data-close-detail class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Fermer">
    <i class="fa-solid fa-xmark" aria-hidden="true">
    </i>
    </button>
    </div>
    <img id="detailImage" class="hidden h-48 w-full object-cover sm:h-60" src="" alt="">
    <div id="detailImageFallback" class="hidden h-48 place-items-center bg-gradient-to-br text-7xl font-bold text-white sm:h-60">
    </div>
    <div class="p-6 sm:p-8">
    <small id="detailOrganizer" class="text-teal-700">
    </small>
    <h3 id="eventDetailTitle" class="mt-3 text-3xl font-bold text-slate-900">
    </h3>
    <p id="detailDescription" class="mt-5 whitespace-pre-wrap text-[15px] leading-7 text-slate-500">
    </p>
    <p id="detailDate" class="mt-6 flex items-center gap-3 text-slate-500">
    <i class="fa-regular fa-calendar-days text-teal-700" aria-hidden="true">
    </i>
    <span>
    </span>
    </p>
    <a id="detailMap" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex items-start gap-3 text-slate-800 hover:text-teal-700">
    <i class="fa-solid fa-location-dot mt-0.5 text-teal-700" aria-hidden="true">
    </i>
    <span id="detailAddress">
    </span>
    <i class="fa-solid fa-arrow-up-right-from-square mt-0.5 text-xs text-slate-400" aria-hidden="true">
    </i>
    </a>
    <p id="detailParticipants" class="mt-4 flex items-center gap-3 text-slate-500">
    <i class="fa-solid fa-user-group text-teal-700" aria-hidden="true">
    </i>
    <span>
    </span>
    </p>
    <form id="eventParticipationForm" method="post" class="mt-7">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken,ENT_QUOTES,'UTF-8') ?>">
    <input id="participationAction" type="hidden" name="action" value="campus-join-event">
    <input id="participationEventId" type="hidden" name="event_id">
    <button id="participationButton" class="w-full rounded-xl bg-teal-600 py-4 font-semibold text-white hover:bg-teal-700">
    </button>
    </form>
    </div>
    </article>
    </div>
<script>(()=>{const createModal=document.getElementById('eventModal'),detailModal=document.getElementById('eventDetailModal'),images=['photo-1511795409834-ef04bbd61622','photo-1523580494863-6f3031224c94','photo-1529156069898-49953e39b3ac'];function open(m){m.classList.remove('hidden');m.classList.add('flex');document.body.classList.add('overflow-hidden')}function close(m){m.classList.add('hidden');m.classList.remove('flex');if(document.querySelectorAll('#eventModal.flex,#eventDetailModal.flex').length===0)document.body.classList.remove('overflow-hidden')}document.querySelector('[data-open-event]').addEventListener('click',()=>open(createModal));document.querySelectorAll('[data-close-event]').forEach(b=>b.addEventListener('click',()=>close(createModal)));createModal.addEventListener('click',e=>{if(e.target===createModal)close(createModal)});document.querySelectorAll('[data-event-open]').forEach((card,index)=>card.addEventListener('click',()=>{const e=JSON.parse(card.dataset.eventOpen),address=[e.address,e.postal_code,e.city,e.country].filter(Boolean).join(', ');const detailImage=document.getElementById('detailImage'),fallback=document.getElementById('detailImageFallback');if(e.image||e.avatar){detailImage.src=e.image||e.avatar;detailImage.classList.remove('hidden');fallback.classList.add('hidden')}else{detailImage.classList.add('hidden');fallback.className='grid h-48 place-items-center bg-gradient-to-br '+e.color+' text-7xl font-bold text-white sm:h-60';fallback.textContent=e.initial;fallback.classList.remove('hidden')}const organizerNode=document.getElementById('detailOrganizer'),organizerIcon=document.createElement('i');organizerIcon.className='fa-solid fa-user mr-1.5';organizerIcon.setAttribute('aria-hidden','true');organizerNode.replaceChildren(organizerIcon,document.createTextNode(' '+e.organizer));document.getElementById('eventDetailTitle').textContent=e.title;document.getElementById('detailDescription').textContent=e.description;const startAt=new Date(e.starts_at.replace(' ','T')),endAt=e.ends_at?new Date(e.ends_at.replace(' ','T')):null,dateFormat=new Intl.DateTimeFormat('fr-FR',{day:'2-digit',month:'short'}),timeFormat=new Intl.DateTimeFormat('fr-FR',{hour:'2-digit',minute:'2-digit'});document.querySelector('#detailDate span').textContent=new Intl.DateTimeFormat('en-GB',{day:'2-digit',month:'short'}).format(startAt)+' - '+timeFormat.format(startAt)+(endAt?' - '+timeFormat.format(endAt):'');document.getElementById('detailAddress').textContent=address;document.getElementById('detailMap').href='https://www.google.com/maps/search/?api=1&query='+encodeURIComponent(address);document.querySelector('#detailParticipants span').textContent=e.participants+' participant'+(e.participants>1?'s':'');document.getElementById('participationEventId').value=e.id;document.getElementById('participationAction').value=e.joined?'campus-leave-event':'campus-join-event';document.getElementById('participationButton').innerHTML=e.joined?'<i class=\"fa-solid fa-circle-check mr-2\">\n    </i>Tu participes - Annuler ma participation':'<i class=\"fa-solid fa-check mr-2\">\n    </i>Je participe';open(detailModal)}));document.querySelectorAll('[data-close-detail]').forEach(b=>b.addEventListener('click',()=>close(detailModal)));detailModal.addEventListener('click',e=>{if(e.target===detailModal)close(detailModal)});document.addEventListener('keydown',e=>{if(e.key==='Escape'){close(createModal);close(detailModal)}})})();</script>
<?php require __DIR__.'/../components/footer.php'; ?>
