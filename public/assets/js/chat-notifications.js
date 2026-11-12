(() => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    async function updateUnreadBadge() {
        try {
            const response = await fetch('index.php?api=messages-unread');
            if (!response.ok) return;
            const data = await response.json();
            const link = document.querySelector('a[href="index.php?page=messages"]');
            let badge = document.getElementById('navMessagesBadge');

            if (Number(data.total) > 0) {
                if (!badge && link) {
                    badge = document.createElement('b');
                    badge.id = 'navMessagesBadge';
                    badge.className = 'ml-auto grid h-5 min-w-5 place-items-center rounded-full bg-teal-600 px-1.5 text-xs text-white';
                    link.append(badge);
                }
                if (badge) badge.textContent = String(data.total);
            } else {
                badge?.remove();
            }
        } catch {
            // The page remains usable while the unread count service is unavailable.
        }
    }

    async function sendPresence(state = 'online') {
        try {
            await fetch('index.php?api=chat-presence', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrfToken },
                body: new URLSearchParams({ state, csrf_token: csrfToken }),
            });
        } catch {
            // Presence is best effort.
        }
    }

    async function connectNotifications() {
        try {
            const response = await fetch('index.php?api=chat-token&conversation=0');
            const data = await response.json();
            if (!response.ok) return;

            const scheme = location.protocol === 'https:' ? 'wss' : 'ws';
            const socket = new WebSocket(`${scheme}://${location.hostname}:8081?ticket=${encodeURIComponent(data.ticket)}`);
            socket.addEventListener('message', (event) => {
                try {
                    if (JSON.parse(event.data).type === 'unread') updateUnreadBadge();
                } catch {
                    // Ignore malformed relay messages.
                }
            });
            socket.addEventListener('close', () => window.setTimeout(connectNotifications, 5000));
            socket.addEventListener('error', () => socket.close());
        } catch {
            window.setTimeout(connectNotifications, 7000);
        }
    }

    updateUnreadBadge();
    sendPresence();
    connectNotifications();
    window.setInterval(updateUnreadBadge, 10000);
    window.setInterval(() => sendPresence(), 15000);
    window.addEventListener('pagehide', () => {
        navigator.sendBeacon('index.php?api=chat-presence', new URLSearchParams({ state: 'offline', csrf_token: csrfToken }));
    });
})();
