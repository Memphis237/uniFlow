<?php
declare(strict_types=1);
require_once __DIR__.'/../../Services/authService.php';
try {
    $user = auth_user_from_cookie();
} catch (Throwable $e) {
    error_log($e->__toString());
    http_response_code(503);
    exit('Service indisponible.');
}
if (!$user) {
    header('Location: index.php?mode=login');
    exit;
}
$currentPage = 'messages';
$pageTitle = 'Messages';
$q = db()->prepare('SELECT c.Id_conversations,(SELECT GROUP_CONCAT(CONCAT(u.first_name," ",u.name) SEPARATOR ", ") FROM lier peer JOIN Users u ON u.Id_users=peer.Id_users WHERE peer.Id_conversations=c.Id_conversations AND peer.Id_users<>:peer_user) participants,(SELECT m.content FROM Messages m WHERE m.Id_conversations=c.Id_conversations ORDER BY m.Id_messages DESC LIMIT 1) preview,(SELECT m.created_at FROM Messages m WHERE m.Id_conversations=c.Id_conversations ORDER BY m.Id_messages DESC LIMIT 1) last_message,(SELECT COUNT(*) FROM Messages unread WHERE unread.Id_conversations=c.Id_conversations AND unread.Id_users<>:unread_sender AND unread.read_at IS NULL) unread,(SELECT MAX(u.is_online=1 AND u.last_seen>=NOW()-INTERVAL 45 SECOND) FROM lier peer JOIN Users u ON u.Id_users=peer.Id_users WHERE peer.Id_conversations=c.Id_conversations AND peer.Id_users<>:peer_online) peer_online,(SELECT MAX(u.Id_users) FROM lier peer JOIN Users u ON u.Id_users=peer.Id_users WHERE peer.Id_conversations=c.Id_conversations AND peer.Id_users<>:peer_id) peer_id FROM Conversations c JOIN lier mine ON mine.Id_conversations=c.Id_conversations WHERE mine.Id_users=:user_id ORDER BY COALESCE(last_message,c.created_at) DESC');
$q->execute(['peer_user' => $user['Id_users'],'unread_sender' => $user['Id_users'],'peer_online' => $user['Id_users'],'peer_id' => $user['Id_users'],'user_id' => $user['Id_users']]);
$conversations = $q->fetchAll();
$totalUnreadMessages = array_sum(array_map(static fn ($row) => (int)$row['unread'], $conversations));
$conversation = (int)($_GET['conversation'] ?? 0);
$activeConversation = null;
foreach ($conversations as $item) {
    if ((int)$item['Id_conversations'] === $conversation) {
        $activeConversation = $item;
        break;
    }
}if (!$activeConversation && $conversations && !isset($_GET['conversation'])) {
    $activeConversation = $conversations[0];
    $conversation = (int)$activeConversation['Id_conversations'];
}if (!$activeConversation) {
    $conversation = 0;
}
require __DIR__.'/../components/header.php';
require __DIR__.'/../components/navBar.php';
?>
<style>
.chat-shell{height:calc(100dvh - 160px);min-height:420px}.chat-list-panel{width:340px;flex:none}.chat-shell{display:flex}.chat-window-panel{flex:1}.chat-bubble{max-width:min(78%,620px);overflow-wrap:anywhere}.chat-bubble.mine{margin-left:auto;background:#d9f8e9;border-radius:18px 18px 4px 18px}.chat-bubble.theirs{margin-right:auto;background:white;border:1px solid #edf0ef;border-radius:18px 18px 18px 4px}.chat-date{font-size:10px;color:#94a3a0;text-align:right;margin-top:5px}.typing-dots i{width:4px;height:4px;border-radius:50%;background:#0f9b8e;animation:typing-bounce 1s infinite ease-in-out}.typing-dots i:nth-child(2){animation-delay:.15s}.typing-dots i:nth-child(3){animation-delay:.3s}@keyframes typing-bounce{0%,60%,100%{transform:translateY(0);opacity:.45}30%{transform:translateY(-3px);opacity:1}}@media(max-width:767px){.chat-shell{height:calc(100dvh - 140px);min-height:400px;margin:-1.25rem}.chat-list-panel{width:100%;border:0}.chat-window-panel{display:none}.chat-shell.chat-open .chat-list-panel{display:none}.chat-shell.chat-open .chat-window-panel{display:flex;width:100%}}
</style>
<section id="chatShell" data-conversation-id="<?= (int) $conversation ?>" data-user-id="<?= (int) $user['Id_users'] ?>" class="chat-shell <?= $conversation ? 'chat-open' : '' ?> overflow-hidden rounded-none md:rounded-2xl border-y md:border border-slate-200 bg-white shadow-sm">
 <?php require __DIR__.'/../components/chatList.php'; ?>
 <div class="flex min-w-0 flex-1 flex-col"><?php require __DIR__.'/../components/chatWindow.php'; ?><?php require __DIR__.'/../components/chatInput.php'; ?></div>
</section>
<script src="assets/js/message.js" defer></script>
<?php require __DIR__.'/../components/footer.php'; ?>
