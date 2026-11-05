const http = require('http');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const { WebSocketServer } = require('ws');

const env = {};
for (const line of fs.readFileSync(path.join(__dirname, '..', '.env'), 'utf8').split(/\r?\n/)) {
  const match = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*?)\s*$/);
  if (match && !match[1].startsWith('#')) env[match[1]] = match[2].replace(/^['"]|['"]$/g, '');
}
const jwtSecret = env.JWT_SECRET || '';
if (jwtSecret.length < 32) throw new Error('Set JWT_SECRET in .env before starting the chat server.');
const ticketKey = crypto.createHmac('sha256', jwtSecret).update('uniflow-ws-ticket').digest('hex');
const internalKey = crypto.createHmac('sha256', jwtSecret).update('uniflow-ws-internal').digest('hex');
const clients = new Set();
const server = http.createServer((req, res) => {
  if (req.method !== 'POST' || req.url !== '/publish' || req.headers['x-uniflow-key'] !== internalKey) {
    res.writeHead(404); res.end(); return;
  }
  let body = '';
  req.on('data', chunk => { body += chunk; if (body.length > 65536) req.destroy(); });
  req.on('end', () => {
    try {
      const event = JSON.parse(body);
      const recipients = new Set((event.recipients || []).map(Number));
      const conversations = new Set((event.conversations || [event.conversation]).map(Number));
      const onlineUsers = new Set();
      for (const client of clients) {
        if (client.readyState !== 1) continue;
        if (conversations.has(client.conversation)) {
          if (event.type === 'message' && recipients.has(client.userId)) onlineUsers.add(client.userId);
          client.send(JSON.stringify({ ...event, conversation: client.conversation }));
        }
        if (event.type === 'message' && recipients.has(client.userId)) client.send(JSON.stringify({ type: 'unread', conversation: event.conversation }));
      }
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ onlineUsers: [...onlineUsers] }));
    } catch { res.writeHead(400); res.end(); }
  });
});
const wss = new WebSocketServer({ noServer: true, maxPayload: 4096 });
server.on('upgrade', (req, socket, head) => {
  try {
    const ticket = new URL(req.url, 'http://localhost').searchParams.get('ticket') || '';
    const [payload, signature] = ticket.split('.');
    const expected = crypto.createHmac('sha256', ticketKey).update(payload || '').digest('hex');
    if (!payload || !signature || !crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expected))) throw new Error('Invalid ticket');
    const claims = JSON.parse(Buffer.from(payload, 'base64url').toString('utf8'));
    if (!claims.user || claims.exp <= Date.now() / 1000) throw new Error('Expired ticket');
    wss.handleUpgrade(req, socket, head, ws => {
      ws.userId = Number(claims.user); ws.conversation = Number(claims.conversation || 0); clients.add(ws);
      const disconnect = () => {
        clients.delete(ws);
        if (ws.conversation > 0 && ![...clients].some(client => client.readyState === 1 && client.userId === ws.userId && client.conversation === ws.conversation)) {
          for (const client of clients) if (client.readyState === 1 && client.conversation === ws.conversation) client.send(JSON.stringify({ type: 'presence', conversation: ws.conversation, userId: ws.userId, online: false }));
        }
      };
      ws.on('close', disconnect);
      ws.on('error', disconnect);
      ws.send(JSON.stringify({ type: 'connected' }));
      for (const client of clients) if (client !== ws && client.readyState === 1 && client.conversation === ws.conversation && ws.conversation > 0) client.send(JSON.stringify({ type: 'presence', conversation: ws.conversation, userId: ws.userId, online: true }));
    });
  } catch { socket.destroy(); }
});
server.listen(Number(env.WS_PORT || 8081), env.WS_HOST || '127.0.0.1', () => console.log(`UniFlow chat WebSocket listening on ${env.WS_HOST || '127.0.0.1'}:${env.WS_PORT || 8081}`));
