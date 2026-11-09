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

$pdo = db();
$csrfToken = auth_csrf_token();
$error = '';
$notice = '';
$selectedEventId = (int) ($_GET['event'] ?? 0);

function campus_event_datetime(string $value, string $label): DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        throw new RuntimeException('Vérifie la date et l’heure de ' . $label . '.');
    }
    return $date;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $selectedEventId = (int) ($_POST['event_id'] ?? $_GET['event'] ?? 0);

    try {
        if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Session expirée, recharge la page.');
        }

        if ($action === 'event-create') {
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $location = trim((string) ($_POST['location'] ?? ''));
            $establishment = trim((string) ($_POST['establishment'] ?? ''));
            $startsAt = campus_event_datetime((string) ($_POST['starts_at'] ?? ''), 'début');
            $endsAtValue = trim((string) ($_POST['ends_at'] ?? ''));
            $endsAt = $endsAtValue !== '' ? campus_event_datetime($endsAtValue, 'fin') : null;
            $maxParticipantsValue = trim((string) ($_POST['max_participants'] ?? ''));
            $maxParticipants = $maxParticipantsValue === '' ? null : filter_var($maxParticipantsValue, FILTER_VALIDATE_INT);

            if ($title === '' || mb_strlen($title) > 255 || $description === '' || mb_strlen($description) > 10000) {
                throw new RuntimeException('Le titre et la description sont obligatoires. Vérifie également leur longueur.');
            }
            if (mb_strlen($location) > 255 || mb_strlen($establishment) > 255) {
                throw new RuntimeException('Le lieu et l’établissement ne doivent pas dépasser 255 caractères.');
            }
            if ($startsAt <= new DateTimeImmutable()) {
                throw new RuntimeException('La date de début doit être dans le futur.');
            }
            if ($endsAt !== null && $endsAt <= $startsAt) {
                throw new RuntimeException('La date de fin doit être postérieure à la date de début.');
            }
            if ($maxParticipantsValue !== '' && ($maxParticipants === false || $maxParticipants < 1)) {
                throw new RuntimeException('Le nombre de participants doit être un entier supérieur à zéro.');
            }

            $insert = $pdo->prepare(
                "INSERT INTO Events (title, description, location, establishment, starts_at, ends_at,
                                     max_participants, requires_approval, status, Id_users)
                 VALUES (:title, :description, :location, :establishment, :starts_at, :ends_at,
                         :max_participants, :requires_approval, 'published', :user)"
            );
            $insert->execute([
                'title' => $title,
                'description' => $description,
                'location' => $location !== '' ? $location : null,
                'establishment' => $establishment !== '' ? $establishment : null,
                'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                'ends_at' => $endsAt?->format('Y-m-d H:i:s'),
                'max_participants' => $maxParticipants,
                'requires_approval' => isset($_POST['requires_approval']) ? 1 : 0,
                'user' => $user['Id_users'],
            ]);

            header('Location: index.php?page=campus&event=' . (int) $pdo->lastInsertId() . '&created=1');
            exit;
        }

        if ($action === 'event-participate') {
            if ($selectedEventId < 1) {
                throw new RuntimeException('Événement invalide.');
            }

            $pdo->beginTransaction();
            $eventQuery = $pdo->prepare(
                "SELECT Id_events, max_participants, requires_approval, Id_users
                 FROM Events
                 WHERE Id_events = :event
                   AND starts_at >= NOW()
                   AND status NOT IN ('draft', 'cancelled', 'canceled')
                 FOR UPDATE"
            );
            $eventQuery->execute(['event' => $selectedEventId]);
            $event = $eventQuery->fetch();
            if (!$event || (int) $event['Id_users'] === (int) $user['Id_users']) {
                throw new RuntimeException('Cet événement n’est pas disponible pour une inscription.');
            }

            $registrationQuery = $pdo->prepare(
                'SELECT status FROM Events_participants WHERE Id_events = :event AND Id_users = :user'
            );
            $registrationQuery->execute(['event' => $selectedEventId, 'user' => $user['Id_users']]);
            $existingStatus = $registrationQuery->fetchColumn();
            if ($existingStatus === 'registered' || $existingStatus === 'pending') {
                $pdo->commit();
                header('Location: index.php?page=profile&tab=events&' . ($existingStatus === 'pending' ? 'event_pending=1' : 'event_already=1'));
                exit;
            }

            $participantQuery = $pdo->prepare(
                "SELECT COUNT(*) FROM Events_participants WHERE Id_events = :event AND status = 'registered'"
            );
            $participantQuery->execute(['event' => $selectedEventId]);
            $participantCount = (int) $participantQuery->fetchColumn();
            if ($event['max_participants'] !== null && $participantCount >= (int) $event['max_participants']) {
                throw new RuntimeException('Cet événement a atteint sa capacité maximale.');
            }

            $newStatus = (int) $event['requires_approval'] === 1 ? 'pending' : 'registered';
            $registration = $pdo->prepare(
                "INSERT INTO Events_participants (Id_events, Id_users, status)
                 VALUES (:event, :user, :status)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), registered_at = CURRENT_TIMESTAMP"
            );
            $registration->execute([
                'event' => $selectedEventId,
                'user' => $user['Id_users'],
                'status' => $newStatus,
            ]);
            $pdo->commit();

            if ($newStatus === 'pending') {
                header('Location: index.php?page=profile&tab=events&event_pending=1');
            } else {
                header('Location: index.php?page=profile&tab=events&event_joined=1');
            }
            exit;
        }

        if ($action === 'event-participation-review') {
            $participantId = (int) ($_POST['participant_id'] ?? 0);
            $decision = (string) ($_POST['decision'] ?? '');
            if ($selectedEventId < 1 || $participantId < 1 || !in_array($decision, ['registered', 'rejected'], true)) {
                throw new RuntimeException('Décision de validation invalide.');
            }

            $pdo->beginTransaction();
            $ownerQuery = $pdo->prepare(
                'SELECT max_participants FROM Events WHERE Id_events = :event AND Id_users = :user FOR UPDATE'
            );
            $ownerQuery->execute(['event' => $selectedEventId, 'user' => $user['Id_users']]);
            $event = $ownerQuery->fetch();
            if (!$event) {
                throw new RuntimeException('Seul l’organisateur peut valider les inscriptions.');
            }

            if ($decision === 'registered' && $event['max_participants'] !== null) {
                $countQuery = $pdo->prepare(
                    "SELECT COUNT(*) FROM Events_participants WHERE Id_events = :event AND status = 'registered'"
                );
                $countQuery->execute(['event' => $selectedEventId]);
                if ((int) $countQuery->fetchColumn() >= (int) $event['max_participants']) {
                    throw new RuntimeException('La capacité maximale est atteinte. Tu peux refuser cette demande.');
                }
            }

            $review = $pdo->prepare(
                "UPDATE Events_participants
                 SET status = :status
                 WHERE Id_events = :event AND Id_users = :participant AND status = 'pending'"
            );
            $review->execute([
                'status' => $decision,
                'event' => $selectedEventId,
                'participant' => $participantId,
            ]);
            if (!$review->rowCount()) {
                throw new RuntimeException('Cette demande a déjà été traitée ou n’existe plus.');
            }
            $pdo->commit();
            header('Location: index.php?page=campus&event=' . $selectedEventId . '&reviewed=1');
            exit;
        }

        throw new RuntimeException('Action inconnue.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof RuntimeException) {
            $error = $exception->getMessage();
        } else {
            error_log($exception->__toString());
            $error = 'Une erreur est survenue. Vérifie que la migration des événements est appliquée.';
        }
    }
}

if (isset($_GET['created'])) {
    $notice = 'Ton événement est en ligne.';
} elseif (isset($_GET['reviewed'])) {
    $notice = 'La demande de participation a été mise à jour.';
}

try {
    $eventQuery = $pdo->prepare(
        "SELECT e.Id_events, e.title, e.description, e.location, e.establishment,
                e.starts_at, e.ends_at, e.max_participants, e.requires_approval, e.Id_users,
                CONCAT(organizer.first_name, ' ', organizer.name) AS organizer_name,
                (SELECT COUNT(*) FROM Events_participants ep
                 WHERE ep.Id_events = e.Id_events AND ep.status = 'registered') AS participants,
                EXISTS(SELECT 1 FROM Events_participants ep
                       WHERE ep.Id_events = e.Id_events AND ep.Id_users = :user AND ep.status = 'registered') AS is_registered,
                EXISTS(SELECT 1 FROM Events_participants ep
                       WHERE ep.Id_events = e.Id_events AND ep.Id_users = :pending_user AND ep.status = 'pending') AS is_pending
         FROM Events e
         JOIN Users organizer ON organizer.Id_users = e.Id_users
         WHERE e.starts_at >= NOW()
           AND e.status NOT IN ('draft', 'cancelled', 'canceled')
         ORDER BY e.starts_at ASC"
    );
    $eventQuery->execute(['user' => $user['Id_users'], 'pending_user' => $user['Id_users']]);
    $events = $eventQuery->fetchAll();
    $establishmentQuery = $pdo->query(
        "SELECT DISTINCT establishment FROM Events
         WHERE establishment IS NOT NULL AND establishment <> ''
         ORDER BY establishment"
    );
    $establishments = $establishmentQuery->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $exception) {
    error_log($exception->__toString());
    http_response_code(503);
    exit('Les événements du campus sont temporairement indisponibles. Vérifie que la migration des événements est appliquée.');
}

$selectedEvent = null;
foreach ($events as $event) {
    if ((int) $event['Id_events'] === $selectedEventId) {
        $selectedEvent = $event;
        break;
    }
}

$pendingParticipants = [];
if ($selectedEvent && (int) $selectedEvent['Id_users'] === (int) $user['Id_users']) {
    $pendingQuery = $pdo->prepare(
        "SELECT u.Id_users, u.first_name, u.name, ep.registered_at
         FROM Events_participants ep
         JOIN Users u ON u.Id_users = ep.Id_users
         WHERE ep.Id_events = :event AND ep.status = 'pending'
         ORDER BY ep.registered_at ASC"
    );
    $pendingQuery->execute(['event' => $selectedEventId]);
    $pendingParticipants = $pendingQuery->fetchAll();
}

$campusPageTitle = 'Mon campus';
$currentPage = 'campus';
$pageTitle = 'Événements étudiants';
require __DIR__ . '/../components/header.php';
require __DIR__ . '/../components/navBar.php';
?>
<style>
    .campus-calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr))}
    .campus-calendar-day{min-height:112px}
    .campus-event-chip{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .campus-calendar-shell{background:linear-gradient(145deg,#102337,#142c40)}
    @media(max-width:640px){.campus-calendar-day{min-height:74px}.campus-event-chip{font-size:10px;padding:3px}}
</style>
<section class="mx-auto max-w-[1500px] px-4 py-6 md:px-8 md:py-8">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="mb-2 text-[11px] font-bold tracking-[.22em] text-teal-700">TON ESPACE UNIFLOW</p>
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 md:text-[36px]">Événements étudiants</h1>
            <p class="mt-2 text-sm text-slate-500">Le calendrier de ta communauté : découvre, propose et rejoins les rendez-vous du campus.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="index.php?page=profile&tab=events" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:border-teal-500 hover:text-teal-700">
                <i data-lucide="calendar-check" class="h-4 w-4"></i> Mes événements
            </a>
            <button type="button" id="openEventForm" class="inline-flex items-center gap-2 rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">
                <i data-lucide="plus" class="h-4 w-4"></i> Ajouter un événement
            </button>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <p class="mb-5 rounded-xl bg-rose-50 p-4 text-sm text-rose-800" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php elseif ($notice !== ''): ?>
        <p class="mb-5 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800" role="status"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
        <section class="campus-calendar-shell overflow-hidden rounded-2xl p-4 text-white shadow-lg md:p-5" aria-label="Calendrier de la communauté">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-bold">Calendrier de la communauté</h2>
                <div class="flex rounded-lg border border-white/15 bg-slate-950/20 p-1" role="group" aria-label="Affichage du calendrier">
                    <button type="button" data-calendar-view="month" class="calendar-view rounded-md bg-white/15 px-3 py-1.5 text-xs font-medium" aria-pressed="true">Mois</button>
                    <button type="button" data-calendar-view="week" class="calendar-view rounded-md px-3 py-1.5 text-xs font-medium text-slate-300" aria-pressed="false">Semaine</button>
                    <button type="button" data-calendar-view="list" class="calendar-view rounded-md px-3 py-1.5 text-xs font-medium text-slate-300" aria-pressed="false">Liste</button>
                </div>
            </div>

            <div class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-white/10 bg-white/[.04] p-3">
                <div class="flex items-center gap-2">
                    <button type="button" id="calendarPrevious" class="rounded-lg border border-white/10 p-2 hover:bg-white/10" aria-label="Période précédente"><i data-lucide="chevron-left" class="h-4 w-4"></i></button>
                    <button type="button" id="calendarToday" class="rounded-lg border border-white/10 px-3 py-2 text-xs hover:bg-white/10">Aujourd’hui</button>
                    <button type="button" id="calendarNext" class="rounded-lg border border-white/10 p-2 hover:bg-white/10" aria-label="Période suivante"><i data-lucide="chevron-right" class="h-4 w-4"></i></button>
                    <h3 id="calendarHeading" class="ml-1 text-sm font-semibold md:ml-3 md:text-base"></h3>
                </div>
                <div class="relative min-w-[190px] flex-1 sm:max-w-[260px]">
                    <i data-lucide="search" class="absolute left-3 top-2.5 h-4 w-4 text-slate-400"></i>
                    <input id="eventSearch" type="search" placeholder="Rechercher un événement" class="w-full rounded-lg border border-white/10 bg-slate-950/25 py-2 pl-9 pr-3 text-xs text-white placeholder:text-slate-400 focus:border-teal-400 focus:outline-none">
                </div>
            </div>

            <div class="mb-3 flex items-center gap-2">
                <label for="establishmentFilter" class="text-xs text-slate-300">Établissement</label>
                <select id="establishmentFilter" class="max-w-[260px] rounded-lg border border-white/10 bg-[#183146] px-3 py-2 text-xs text-white focus:border-teal-400 focus:outline-none">
                    <option value="">Tous les établissements</option>
                    <?php foreach ($establishments as $establishment): ?>
                        <option value="<?= htmlspecialchars((string) $establishment, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $establishment, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <span id="eventResultCount" class="ml-auto text-xs text-slate-400"></span>
            </div>

            <div id="calendarMonthView" class="overflow-hidden rounded-xl border border-white/10">
                <div class="campus-calendar-grid bg-white/[.06] text-center text-[11px] font-semibold text-slate-300">
                    <?php foreach (['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $weekday): ?>
                        <div class="border-b border-white/10 py-2"><?= $weekday ?></div>
                    <?php endforeach; ?>
                </div>
                <div id="calendarDays" class="campus-calendar-grid"></div>
            </div>
            <div id="calendarWeekView" class="hidden overflow-x-auto rounded-xl border border-white/10">
                <div id="calendarWeekDays" class="campus-calendar-grid min-w-[680px]"></div>
            </div>
            <div id="calendarListView" class="hidden min-h-40 space-y-2 rounded-xl border border-white/10 bg-white/[.03] p-3"></div>
        </section>

        <aside class="space-y-4">
            <section id="eventDetail" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <?php if ($selectedEvent): ?>
                    <?php $remainingSeats = $selectedEvent['max_participants'] === null ? null : max(0, (int) $selectedEvent['max_participants'] - (int) $selectedEvent['participants']); ?>
                    <p class="text-xs font-semibold uppercase tracking-wider text-teal-700">Rejoindre un événement</p>
                    <h2 class="mt-3 text-xl font-bold text-slate-900"><?= htmlspecialchars($selectedEvent['title'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <p class="mt-3 text-sm leading-6 text-slate-600"><?= nl2br(htmlspecialchars($selectedEvent['description'], ENT_QUOTES, 'UTF-8')) ?></p>
                    <div class="mt-4 space-y-3 text-sm text-slate-600">
                        <p class="flex items-start gap-2"><i data-lucide="calendar-days" class="mt-0.5 h-4 w-4 shrink-0 text-teal-700"></i><span><?= date('d/m/Y', strtotime($selectedEvent['starts_at'])) ?><br><?= date('H:i', strtotime($selectedEvent['starts_at'])) ?><?php if ($selectedEvent['ends_at']): ?> – <?= date('H:i', strtotime($selectedEvent['ends_at'])) ?><?php endif; ?></span></p>
                        <p class="flex items-start gap-2"><i data-lucide="user-round" class="mt-0.5 h-4 w-4 shrink-0 text-teal-700"></i><span>Organisé par <?= htmlspecialchars($selectedEvent['organizer_name'], ENT_QUOTES, 'UTF-8') ?></span></p>
                        <?php if ($selectedEvent['establishment']): ?><p class="flex items-start gap-2"><i data-lucide="graduation-cap" class="mt-0.5 h-4 w-4 shrink-0 text-teal-700"></i><?= htmlspecialchars($selectedEvent['establishment'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                        <?php if ($selectedEvent['location']): ?><p class="flex items-start gap-2"><i data-lucide="map-pin" class="mt-0.5 h-4 w-4 shrink-0 text-teal-700"></i><?= htmlspecialchars($selectedEvent['location'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                    </div>
                    <div class="mt-5">
                        <p class="mb-2 flex items-center justify-between text-xs text-slate-500"><span>Capacité</span><span><?= (int) $selectedEvent['participants'] ?><?php if ($selectedEvent['max_participants'] !== null): ?> / <?= (int) $selectedEvent['max_participants'] ?><?php else: ?> participant(s)<?php endif; ?></span></p>
                        <?php if ($selectedEvent['max_participants'] !== null): ?><div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-teal-500" style="width:<?= (int) $selectedEvent['max_participants'] > 0 ? min(100, (int) round((int) $selectedEvent['participants'] * 100 / (int) $selectedEvent['max_participants'])) : 0 ?>%"></div></div><?php endif; ?>
                        <?php if ($remainingSeats !== null): ?><p class="mt-2 text-xs text-slate-500"><?= $remainingSeats ?> place(s) restante(s)</p><?php endif; ?>
                    </div>

                    <?php if ((int) $selectedEvent['Id_users'] === (int) $user['Id_users']): ?>
                        <p class="mt-5 rounded-lg bg-teal-50 p-3 text-sm font-medium text-teal-800">Tu es l’organisateur de cet événement.</p>
                        <?php if ((int) $selectedEvent['requires_approval'] === 1): ?>
                            <h3 class="mt-5 font-semibold text-slate-900">Demandes à valider <span class="text-xs font-normal text-slate-500">(<?= count($pendingParticipants) ?>)</span></h3>
                            <?php foreach ($pendingParticipants as $participant): ?>
                                <div class="mt-3 rounded-lg border border-slate-200 p-3">
                                    <p class="text-sm font-medium text-slate-800"><?= htmlspecialchars($participant['first_name'] . ' ' . $participant['name'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <div class="mt-3 flex gap-2">
                                        <?php foreach (['registered' => 'Accepter', 'rejected' => 'Refuser'] as $decision => $label): ?>
                                            <form method="post" class="flex-1">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="action" value="event-participation-review">
                                                <input type="hidden" name="event_id" value="<?= (int) $selectedEvent['Id_events'] ?>">
                                                <input type="hidden" name="participant_id" value="<?= (int) $participant['Id_users'] ?>">
                                                <button name="decision" value="<?= $decision ?>" class="w-full rounded-lg <?= $decision === 'registered' ? 'bg-teal-700 text-white' : 'border border-slate-200 text-slate-600' ?> px-3 py-2 text-xs font-semibold"><?= $label ?></button>
                                            </form>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$pendingParticipants): ?><p class="mt-2 text-sm text-slate-500">Aucune demande en attente.</p><?php endif; ?>
                        <?php endif; ?>
                    <?php elseif ((int) $selectedEvent['is_registered'] === 1): ?>
                        <p class="mt-5 rounded-lg bg-teal-50 p-3 text-sm font-medium text-teal-800" role="status">Ta participation est confirmée.</p>
                        <a href="index.php?page=profile&tab=events" class="mt-3 block text-center text-sm font-semibold text-teal-700 hover:underline">Voir mes événements</a>
                    <?php elseif ((int) $selectedEvent['is_pending'] === 1): ?>
                        <p class="mt-5 rounded-lg bg-amber-50 p-3 text-sm font-medium text-amber-800" role="status">Ta demande attend la validation de l’organisateur.</p>
                    <?php elseif ($remainingSeats === 0): ?>
                        <p class="mt-5 rounded-lg bg-slate-100 p-3 text-center text-sm font-semibold text-slate-600">Événement complet</p>
                    <?php else: ?>
                        <form method="post" class="mt-5">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="action" value="event-participate">
                            <input type="hidden" name="event_id" value="<?= (int) $selectedEvent['Id_events'] ?>">
                            <button class="w-full rounded-lg bg-teal-600 px-4 py-3 text-sm font-semibold text-white hover:bg-teal-700"><?= (int) $selectedEvent['requires_approval'] === 1 ? 'Demander à participer' : 'Je participe !' ?></button>
                        </form>
                        <?php if ((int) $selectedEvent['requires_approval'] === 1): ?><p class="mt-2 text-center text-xs text-slate-500">L’organisateur devra confirmer ta demande.</p><?php endif; ?>
                        <a href="index.php?page=profile&tab=events" class="mt-3 flex items-center justify-center gap-2 rounded-lg border border-slate-200 px-4 py-2.5 text-sm text-slate-600 hover:bg-slate-50"><i data-lucide="calendar-plus" class="h-4 w-4"></i> Ajouter à mes événements</a>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-xs font-semibold uppercase tracking-wider text-teal-700">Rejoindre un événement</p>
                    <div class="mt-4 grid h-12 w-12 place-items-center rounded-xl bg-teal-50 text-teal-700"><i data-lucide="calendar-heart" class="h-6 w-6"></i></div>
                    <h2 class="mt-4 text-lg font-bold text-slate-900">La communauté t’attend</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Sélectionne un rendez-vous dans le calendrier pour découvrir les informations et confirmer ta participation.</p>
                    <button type="button" id="openEventFormAside" class="mt-5 w-full rounded-lg bg-teal-700 px-4 py-3 text-sm font-semibold text-white hover:bg-teal-800">Proposer un événement</button>
                <?php endif; ?>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5">
                <h2 class="font-semibold text-slate-900">Organise un rendez-vous</h2>
                <p class="mt-2 text-sm leading-6 text-slate-500">Choisis un établissement, indique le lieu et la capacité, puis décide si les demandes doivent être validées.</p>
                <button type="button" id="openEventFormCard" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-teal-700 hover:underline"><i data-lucide="plus-circle" class="h-4 w-4"></i> Créer un événement</button>
            </section>
        </aside>
    </div>

    <div id="eventData" class="hidden" aria-hidden="true"><?php foreach ($events as $event): ?>
        <span data-event
              data-id="<?= (int) $event['Id_events'] ?>"
              data-title="<?= htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8') ?>"
              data-description="<?= htmlspecialchars($event['description'], ENT_QUOTES, 'UTF-8') ?>"
              data-location="<?= htmlspecialchars((string) ($event['location'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
              data-establishment="<?= htmlspecialchars((string) ($event['establishment'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
              data-start="<?= htmlspecialchars($event['starts_at'], ENT_QUOTES, 'UTF-8') ?>"
              data-end="<?= htmlspecialchars((string) ($event['ends_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
              data-participants="<?= (int) $event['participants'] ?>"
              data-capacity="<?= htmlspecialchars((string) ($event['max_participants'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
              data-registered="<?= (int) $event['is_registered'] ?>"
              data-pending="<?= (int) $event['is_pending'] ?>"
              data-organizer="<?= (int) $event['Id_users'] === (int) $user['Id_users'] ? '1' : '0' ?>"
              data-href="index.php?page=campus&amp;event=<?= (int) $event['Id_events'] ?>"></span>
    <?php endforeach; ?></div>
</section>

<div id="eventFormModal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="eventFormTitle">
    <div class="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
        <div class="sticky top-0 flex items-center justify-between border-b border-slate-100 bg-white px-5 py-4 md:px-7">
            <div><p class="text-xs font-semibold uppercase tracking-wider text-teal-700">Vie du campus</p><h2 id="eventFormTitle" class="mt-1 text-xl font-bold text-slate-900">Créer un événement</h2></div>
            <button type="button" id="closeEventForm" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Fermer"><i data-lucide="x" class="h-5 w-5"></i></button>
        </div>
        <form method="post" class="space-y-4 p-5 md:p-7">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="event-create">
            <?php if ($error !== ''): ?><p class="rounded-lg bg-rose-50 p-3 text-sm text-rose-800" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
            <label class="block text-sm font-medium text-slate-700">Nom de l’événement
                <input name="title" required maxlength="255" value="<?= htmlspecialchars((string) ($_POST['title'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Ex. Atelier CV et entretien" class="mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-2.5 outline-none focus:border-teal-500">
            </label>
            <label class="block text-sm font-medium text-slate-700">Description
                <textarea name="description" required maxlength="10000" rows="4" placeholder="Présente l’activité et les informations utiles aux participants…" class="mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-2.5 outline-none focus:border-teal-500"><?= htmlspecialchars((string) ($_POST['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            </label>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm font-medium text-slate-700">Établissement
                    <input name="establishment" list="eventEstablishmentOptions" maxlength="255" value="<?= htmlspecialchars((string) ($_POST['establishment'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Sélectionner ou saisir" class="mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-2.5 outline-none focus:border-teal-500">
                    <datalist id="eventEstablishmentOptions"><?php foreach ($establishments as $establishment): ?><option value="<?= htmlspecialchars((string) $establishment, ENT_QUOTES, 'UTF-8') ?>"><?php endforeach; ?></datalist>
                </label>
                <label class="block text-sm font-medium text-slate-700">Lieu précis
                    <input name="location" maxlength="255" value="<?= htmlspecialchars((string) ($_POST['location'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Bâtiment, salle, adresse…" class="mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-2.5 outline-none focus:border-teal-500">
                </label>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm font-medium text-slate-700">Début
                    <input name="starts_at" type="datetime-local" required value="<?= htmlspecialchars((string) ($_POST['starts_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-2.5 outline-none focus:border-teal-500">
                </label>
                <label class="block text-sm font-medium text-slate-700">Fin <span class="font-normal text-slate-400">(facultative)</span>
                    <input name="ends_at" type="datetime-local" value="<?= htmlspecialchars((string) ($_POST['ends_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" class="mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-2.5 outline-none focus:border-teal-500">
                </label>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 sm:items-center">
                <label class="block text-sm font-medium text-slate-700">Nombre maximum de participants <span class="font-normal text-slate-400">(facultatif)</span>
                    <input name="max_participants" type="number" min="1" step="1" value="<?= htmlspecialchars((string) ($_POST['max_participants'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Illimité" class="mt-1.5 w-full rounded-lg border border-slate-200 px-3 py-2.5 outline-none focus:border-teal-500">
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 text-sm text-slate-700">
                    <input type="checkbox" name="requires_approval" value="1" <?= isset($_POST['requires_approval']) ? 'checked' : '' ?> class="mt-0.5 h-4 w-4 accent-teal-700">
                    <span><b class="block">Valider les participations</b><small class="mt-1 block text-slate-500">Chaque demande devra être acceptée par l’organisateur.</small></span>
                </label>
            </div>
            <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
                <button type="button" id="cancelEventForm" class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm text-slate-600">Annuler</button>
                <button type="submit" class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">Publier l’événement</button>
            </div>
        </form>
    </div>
</div>
<script>
(() => {
    const records = [...document.querySelectorAll('#eventData [data-event]')].map((node) => ({
        id: node.dataset.id,
        title: node.dataset.title,
        description: node.dataset.description,
        location: node.dataset.location,
        establishment: node.dataset.establishment,
        start: new Date(node.dataset.start.replace(' ', 'T')),
        end: node.dataset.end ? new Date(node.dataset.end.replace(' ', 'T')) : null,
        participants: Number(node.dataset.participants),
        capacity: node.dataset.capacity ? Number(node.dataset.capacity) : null,
        registered: node.dataset.registered === '1',
        pending: node.dataset.pending === '1',
        organizer: node.dataset.organizer === '1',
        href: node.dataset.href
    }));
    const monthView = document.getElementById('calendarMonthView');
    const weekView = document.getElementById('calendarWeekView');
    const listView = document.getElementById('calendarListView');
    const monthDays = document.getElementById('calendarDays');
    const weekDays = document.getElementById('calendarWeekDays');
    const heading = document.getElementById('calendarHeading');
    const countLabel = document.getElementById('eventResultCount');
    const search = document.getElementById('eventSearch');
    const establishmentFilter = document.getElementById('establishmentFilter');
    const monthFormatter = new Intl.DateTimeFormat('fr-FR', {month: 'long', year: 'numeric'});
    const fullDateFormatter = new Intl.DateTimeFormat('fr-FR', {weekday: 'long', day: 'numeric', month: 'long'});
    let activeDate = new Date();
    let activeView = 'month';

    function filteredEvents() {
        const query = search.value.trim().toLocaleLowerCase('fr');
        const establishment = establishmentFilter.value;
        return records.filter((event) => {
            const text = `${event.title} ${event.description} ${event.location} ${event.establishment}`.toLocaleLowerCase('fr');
            return (!query || text.includes(query)) && (!establishment || event.establishment === establishment);
        });
    }

    function eventLink(event) {
        const link = document.createElement('a');
        link.href = event.href;
        link.className = 'campus-event-chip rounded-md bg-teal-400/90 px-1.5 py-1 text-[11px] leading-4 text-slate-950 hover:bg-teal-300';
        link.textContent = `${event.start.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'})} ${event.title}`;
        link.title = `${event.title} · ${event.start.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'})}`;
        return link;
    }

    function renderMonth(events) {
        const year = activeDate.getFullYear();
        const month = activeDate.getMonth();
        heading.textContent = monthFormatter.format(activeDate);
        monthDays.replaceChildren();
        const firstWeekday = (new Date(year, month, 1).getDay() + 6) % 7;
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const totalCells = Math.ceil((firstWeekday + daysInMonth) / 7) * 7;
        for (let cell = 0; cell < totalCells; cell += 1) {
            const dayNumber = cell - firstWeekday + 1;
            const cellDate = new Date(year, month, dayNumber);
            const day = document.createElement('div');
            const inMonth = dayNumber > 0 && dayNumber <= daysInMonth;
            day.className = `campus-calendar-day overflow-hidden border-b border-r border-white/10 p-1.5 md:p-2 ${inMonth ? 'bg-white/[.025]' : 'bg-slate-950/20 text-slate-600'}`;
            const number = document.createElement('span');
            number.className = `mb-1 grid h-6 w-6 place-items-center rounded-full text-xs ${inMonth && cellDate.toDateString() === new Date().toDateString() ? 'bg-teal-400 font-bold text-slate-950' : 'text-slate-300'}`;
            number.textContent = String(cellDate.getDate());
            day.append(number);
            if (inMonth) {
                events.filter((event) => event.start.getFullYear() === year && event.start.getMonth() === month && event.start.getDate() === dayNumber)
                    .forEach((event) => day.append(eventLink(event)));
            }
            monthDays.append(day);
        }
    }

    function startOfWeek(date) {
        const start = new Date(date);
        start.setDate(date.getDate() - ((date.getDay() + 6) % 7));
        start.setHours(0, 0, 0, 0);
        return start;
    }

    function renderWeek(events) {
        const firstDay = startOfWeek(activeDate);
        const lastDay = new Date(firstDay);
        lastDay.setDate(firstDay.getDate() + 6);
        heading.textContent = `${fullDateFormatter.format(firstDay)} – ${fullDateFormatter.format(lastDay)}`;
        weekDays.replaceChildren();
        for (let index = 0; index < 7; index += 1) {
            const date = new Date(firstDay);
            date.setDate(firstDay.getDate() + index);
            const column = document.createElement('div');
            column.className = 'min-h-48 border-r border-white/10 p-2';
            const label = document.createElement('p');
            label.className = 'mb-2 border-b border-white/10 pb-2 text-xs font-semibold text-slate-300';
            label.textContent = fullDateFormatter.format(date);
            column.append(label);
            events.filter((event) => event.start.toDateString() === date.toDateString()).forEach((event) => column.append(eventLink(event)));
            weekDays.append(column);
        }
    }

    function renderList(events) {
        heading.textContent = 'Tous les événements à venir';
        listView.replaceChildren();
        if (!events.length) {
            const empty = document.createElement('p');
            empty.className = 'p-6 text-center text-sm text-slate-400';
            empty.textContent = 'Aucun événement ne correspond à ces filtres.';
            listView.append(empty);
            return;
        }
        events.forEach((event) => {
            const link = document.createElement('a');
            link.href = event.href;
            link.className = 'flex flex-wrap items-center justify-between gap-3 rounded-lg border border-white/10 bg-white/[.04] p-3 hover:bg-white/[.08]';
            const details = document.createElement('span');
            const title = document.createElement('b');
            title.className = 'block text-sm text-white';
            title.textContent = event.title;
            const date = document.createElement('small');
            date.className = 'mt-1 block text-xs text-slate-400';
            date.textContent = `${fullDateFormatter.format(event.start)} · ${event.start.toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'})}${event.establishment ? ` · ${event.establishment}` : ''}`;
            details.append(title, date);
            const seats = document.createElement('small');
            seats.className = 'text-xs text-slate-300';
            seats.textContent = event.capacity ? `${event.participants}/${event.capacity} participants` : `${event.participants} participant(s)`;
            link.append(details, seats);
            listView.append(link);
        });
    }

    function render() {
        const events = filteredEvents();
        countLabel.textContent = `${events.length} événement(s)`;
        monthView.classList.toggle('hidden', activeView !== 'month');
        weekView.classList.toggle('hidden', activeView !== 'week');
        listView.classList.toggle('hidden', activeView !== 'list');
        if (activeView === 'month') renderMonth(events);
        if (activeView === 'week') renderWeek(events);
        if (activeView === 'list') renderList(events);
    }

    document.querySelectorAll('[data-calendar-view]').forEach((button) => button.addEventListener('click', () => {
        activeView = button.dataset.calendarView;
        document.querySelectorAll('[data-calendar-view]').forEach((item) => {
            const active = item === button;
            item.setAttribute('aria-pressed', String(active));
            item.classList.toggle('bg-white/15', active);
            item.classList.toggle('text-slate-300', !active);
        });
        render();
    }));
    document.getElementById('calendarPrevious').addEventListener('click', () => {
        if (activeView === 'week') activeDate.setDate(activeDate.getDate() - 7);
        else activeDate.setMonth(activeDate.getMonth() - 1);
        render();
    });
    document.getElementById('calendarNext').addEventListener('click', () => {
        if (activeView === 'week') activeDate.setDate(activeDate.getDate() + 7);
        else activeDate.setMonth(activeDate.getMonth() + 1);
        render();
    });
    document.getElementById('calendarToday').addEventListener('click', () => { activeDate = new Date(); render(); });
    search.addEventListener('input', render);
    establishmentFilter.addEventListener('change', render);
    render();

    const modal = document.getElementById('eventFormModal');
    const openModal = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); modal.querySelector('[name="title"]').focus(); };
    const closeModal = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };
    ['openEventForm', 'openEventFormAside', 'openEventFormCard'].forEach((id) => {
        const button = document.getElementById(id);
        if (button) button.addEventListener('click', openModal);
    });
    document.getElementById('closeEventForm').addEventListener('click', closeModal);
    document.getElementById('cancelEventForm').addEventListener('click', closeModal);
    modal.addEventListener('click', (event) => { if (event.target === modal) closeModal(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeModal(); });
    <?php if ($error !== '' && ($_POST['action'] ?? '') === 'event-create'): ?>openModal();<?php endif; ?>
    if (window.lucide) window.lucide.createIcons();
})();
</script>
<?php require __DIR__ . '/../components/footer.php'; ?>
