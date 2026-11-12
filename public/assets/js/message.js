(() => {
  const shell = document.getElementById('chatShell');
  const conversationId = Number(shell?.dataset.conversationId || 0);
  const ownId = Number(shell?.dataset.userId || 0);
  const box = document.getElementById('chatMessages');
  const form = document.getElementById('chatForm');
  const fileInput = document.getElementById('chatAttachments');
  let lastId = 0,
    loading = false,
    socket = null,
    typingSent = false,
    typingTimer = null,
    remoteTypingTimer = null;
  const activePeer = document.querySelector(
    '[data-chat-item][data-conversation="' + conversationId + '"]',
  )?.dataset.peer;
  if (activePeer) document.body.dataset.peerId = activePeer;
  function setPresence(userId, online) {
    document.querySelectorAll('[data-chat-item]').forEach((item) => {
      if (Number(item.dataset.peer) === Number(userId)) {
        const dot = item.querySelector('[data-presence-dot]');
        dot?.classList.toggle('bg-emerald-500', online);
        dot?.classList.toggle('bg-slate-300', !online);
      }
    });
    if (Number(document.body.dataset.peerId || 0) === Number(userId)) {
      const dot = document.getElementById('activePresenceDot'),
        text = document.getElementById('activePresenceText');
      dot?.classList.toggle('bg-emerald-500', online);
      dot?.classList.toggle('bg-slate-300', !online);
      if (text) {
        text.textContent = online ? 'En ligne' : 'Hors ligne';
        text.classList.toggle('text-emerald-600', online);
        text.classList.toggle('text-slate-400', !online);
      }
    }
  }
  function sendTyping(active) {
    if (socket && socket.readyState === WebSocket.OPEN && conversationId)
      socket.send(JSON.stringify({ type: 'typing', active }));
  }
  function showTyping(active, userId) {
    if (Number(userId) === ownId) return;
    const indicator = document.getElementById('typingIndicator'),
      bubble = document.getElementById('typingBubble'),
      presence = document.getElementById('activePresenceText');
    if (!indicator || !bubble) return;
    clearTimeout(remoteTypingTimer);
    indicator.classList.toggle('hidden', !active);
    indicator.classList.toggle('flex', active);
    bubble.classList.toggle('hidden', !active);
    bubble.classList.toggle('flex', active);
    presence?.classList.toggle('hidden', active);
    if (active) {
      box.append(bubble);
      box.scrollTop = box.scrollHeight;
      remoteTypingTimer = setTimeout(() => showTyping(false, userId), 3000);
    }
  }
  function paintReceipt(row, message) {
    const receipt = row.querySelector('[data-receipt]');
    if (!receipt || Number(message.Id_users) !== ownId) return;
    receipt.textContent = message.read_at
      ? '\u2713\u2713'
      : message.delivered_at
        ? '\u2713\u2713'
        : '\u2713';
    receipt.className =
      'ml-1 text-[11px] ' +
      (message.read_at
        ? 'text-sky-600'
        : message.delivered_at
          ? 'text-slate-400'
          : 'text-slate-400');
  }
  async function refreshChat() {
    if (!conversationId || !box || loading) return;
    loading = true;
    try {
      const response = await fetch(
          'index.php?api=messages-list&conversation=' + conversationId,
          { headers: { Accept: 'application/json' } },
        ),
        data = await response.json();
      if (!response.ok) throw Error(data.error || 'Loading failed');
      const nearBottom =
        box.scrollHeight - box.scrollTop - box.clientHeight < 100;
      for (const m of data.messages) {
        const id = Number(m.Id_messages);
        let row = box.querySelector('[data-message-id="' + id + '"]');
        if (row) {
          paintReceipt(row, m);
          continue;
        }
        lastId = Math.max(lastId, id);
        row = document.createElement('article');
        row.dataset.messageId = String(id);
        row.className =
          'chat-bubble ' +
          (Number(m.Id_users) === ownId ? 'mine' : 'theirs') +
          ' px-4 py-2.5 shadow-sm';
        if (m.content) {
          const body = document.createElement('p');
          body.className =
            'whitespace-pre-wrap text-sm leading-6 text-slate-800';
          body.textContent = m.content;
          row.append(body);
        }
        for (const attachment of m.attachments || []) {
          const link = document.createElement('a');
          link.href = attachment.file_path;
          link.target = '_blank';
          link.rel = 'noopener noreferrer';
          link.className =
            'mt-2 flex items-center gap-2 text-sm text-teal-800 underline';
          if (String(attachment.mime_type).startsWith('image/')) {
            const image = document.createElement('img');
            image.src = attachment.file_path;
            image.alt = attachment.name_original;
            image.className = 'max-h-56 max-w-full rounded-lg object-cover';
            link.append(image);
          } else {
            link.textContent =
              attachment.name_original +
              ' (' +
              Math.ceil(Number(attachment.file_size) / 1024) +
              ' KB)';
          }
          row.append(link);
        }
        const time = document.createElement('div');
        time.className = 'chat-date';
        const date = new Date(String(m.created_at).replace(' ', 'T'));
        time.textContent =
          (Number(m.Id_users) === ownId ? 'Moi - ' : '') +
          (Number.isNaN(date.getTime())
            ? ''
            : date.toLocaleTimeString('fr-FR', {
                hour: '2-digit',
                minute: '2-digit',
              }));
        if (Number(m.Id_users) === ownId) {
          const receipt = document.createElement('span');
          receipt.dataset.receipt = '';
          time.append(receipt);
        }
        row.append(time);
        paintReceipt(row, m);
        box.append(row);
      }
      if (data.presence) {
        const peer = document.querySelector(
          '[data-chat-item][data-conversation="' + conversationId + '"]',
        )?.dataset.peer;
        setPresence(peer, Boolean(Number(data.presence.is_online)));
      }
      if (nearBottom) box.scrollTop = box.scrollHeight;
      await refreshUnread();
    } catch (error) {
      console.error(error);
    } finally {
      loading = false;
    }
  }
  async function refreshUnread() {
    try {
      const response = await fetch('index.php?api=messages-unread'),
        data = await response.json();
      if (!response.ok) return;
      document.querySelectorAll('[data-chat-item]').forEach((item) => {
        const count = Number(data.counts?.[item.dataset.conversation] || 0),
          badge = item.querySelector('[data-unread-badge]');
        if (badge) {
          badge.textContent = String(count);
          badge.classList.toggle('hidden', count === 0);
        }
      });
      let badge = document.getElementById('navMessagesBadge');
      const navLink = document.querySelector(
        'a[href="index.php?page=messages"]',
      );
      if (Number(data.total) > 0) {
        if (!badge && navLink) {
          badge = document.createElement('b');
          badge.id = 'navMessagesBadge';
          badge.className =
            'ml-auto grid h-5 min-w-5 place-items-center rounded-full bg-teal-600 px-1.5 text-white text-xs';
          navLink.append(badge);
        }
        if (badge) badge.textContent = String(data.total);
      } else badge?.remove();
    } catch {}
  }
  async function connectSocket() {
    try {
      const response = await fetch(
          'index.php?api=chat-token&conversation=' + conversationId,
        ),
        data = await response.json();
      if (!response.ok) throw Error('No chat ticket');
      const scheme = location.protocol === 'https:' ? 'wss' : 'ws';
      socket = new WebSocket(
        scheme +
          '://' +
          location.hostname +
          ':8081?ticket=' +
          encodeURIComponent(data.ticket),
      );
      socket.addEventListener('message', (event) => {
        let data;
        try {
          data = JSON.parse(event.data);
        } catch {
          return;
        }
        if (Number(data.conversation) === conversationId) {
          if (data.type === 'presence')
            setPresence(data.userId, Boolean(data.online));
          if (data.type === 'typing')
            showTyping(Boolean(data.active), data.userId);
          if (['message', 'read', 'delivery'].includes(data.type))
            setTimeout(refreshChat, 120);
        }
        if (data.type === 'unread') refreshUnread();
      });
      socket.addEventListener('close', () => setTimeout(connectSocket, 4000));
      socket.addEventListener('error', () => socket.close());
    } catch {
      setTimeout(connectSocket, 6000);
    }
  }
  if (form) {
    const input = form.elements.content;
    input.addEventListener('input', () => {
      input.style.height = 'auto';
      input.style.height = Math.min(input.scrollHeight, 128) + 'px';
      if (input.value.trim()) {
        sendTyping(true);
        typingSent = true;
        clearTimeout(typingTimer);
        typingTimer = setTimeout(() => {
          sendTyping(false);
          typingSent = false;
        }, 1300);
      } else if (typingSent) {
        sendTyping(false);
        typingSent = false;
        clearTimeout(typingTimer);
      }
    });
    input.addEventListener('blur', () => {
      if (typingSent) {
        sendTyping(false);
        typingSent = false;
        clearTimeout(typingTimer);
      }
    });
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        form.requestSubmit();
      }
    });
    fileInput?.addEventListener('change', () => {
      const names = Array.from(fileInput.files || []).map((file) => file.name);
      document.getElementById('chatAttachmentNames').textContent =
        names.join(', ') || 'No files selected';
    });
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = form.querySelector('button[type="submit"]'),
        files = Array.from(fileInput?.files || []);
      if (!input.value.trim() && !files.length) return;
      if (
        files.length > 5 ||
        files.some((file) => file.size > 8 * 1024 * 1024) ||
        files.reduce((sum, file) => sum + file.size, 0) > 20 * 1024 * 1024
      ) {
        alert('Maximum 5 files, 8 MB each and 20 MB total.');
        return;
      }
      button.disabled = true;
      if (typingSent) {
        sendTyping(false);
        typingSent = false;
        clearTimeout(typingTimer);
      }
      try {
        const body = new FormData(form);
        body.append('conversation', conversationId);
        body.append(
          'csrf_token',
          document.querySelector('meta[name="csrf-token"]')?.content || '',
        );
        const response = await fetch('index.php?api=message-send', {
            method: 'POST',
            body,
          }),
          result = await response.json();
        if (!response.ok || !result.ok)
          throw Error(result.error || 'Message could not be sent.');
        form.reset();
        input.style.height = 'auto';
        document.getElementById('chatAttachmentNames').textContent = '';
        await refreshChat();
      } catch (error) {
        alert(error.message);
      } finally {
        button.disabled = false;
        input.focus();
      }
    });
    refreshChat();
    connectSocket();
    setInterval(() => {
      if (!socket || socket.readyState !== WebSocket.OPEN) refreshChat();
    }, 2500);
    setInterval(refreshUnread, 4000);
  }
  document
    .getElementById('mobileChatBack')
    ?.addEventListener('click', () => shell.classList.remove('chat-open'));
  document.getElementById('chatSearch')?.addEventListener('input', (event) => {
    const query = event.target.value.toLocaleLowerCase('fr');
    document
      .querySelectorAll('[data-chat-item]')
      .forEach((item) =>
        item.classList.toggle(
          'hidden',
          !item.textContent.toLocaleLowerCase('fr').includes(query),
        ),
      );
  });
  if (window.lucide) lucide.createIcons();
})();
