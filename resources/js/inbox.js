import './bootstrap';

/**
 * Inbox with two transport strategies:
 *  1. Reverb WebSockets (Echo) — when available (VPS).
 *  2. Polling fallback — works on shared hosting without a WebSocket server.
 *
 * For embedded (Shopify admin iframe) the session token (id_token/session query
 * param) is forwarded to API calls, since cookies are blocked inside the iframe.
 */
document.addEventListener('DOMContentLoaded', function () {
    const thread = document.getElementById('thread');
    if (!thread) return;

    const conversationId = thread.getAttribute('data-conversation');
    const messagesUrl = thread.getAttribute('data-messages-url');
    let lastMessageId = thread.getAttribute('data-last-message-id');

    function authParams() {
        // Forward the Shopify session token so the shopify.session middleware can
        // authenticate inside the embedded admin iframe.
        const qs = new URLSearchParams(window.location.search);
        const parts = [];
        if (qs.get('id_token')) parts.push('id_token=' + encodeURIComponent(qs.get('id_token')));
        if (qs.get('session')) parts.push('session=' + encodeURIComponent(qs.get('session')));
        if (qs.get('host')) parts.push('host=' + encodeURIComponent(qs.get('host')));
        return parts.join('&');
    }

    function appendMessage(msg) {
        const wrap = document.createElement('div');
        wrap.className = 'msg ' + (msg.direction === 'outbound' ? 'outbound' : 'inbound');
        wrap.innerHTML = '<div class="bubble">' + escapeHtml(msg.body || '') + '</div>';
        thread.appendChild(wrap);
        thread.scrollTop = thread.scrollHeight;
    }

    function escapeHtml(s) {
        return s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    // --- WebSocket transport (when Reverb/Echo is configured & connected) ---
    let usingWs = false;
    try {
        if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
            usingWs = true;
            window.Echo.private('conversation.' + conversationId).listen('InboxMessageEvent', (e) => {
                if (e.message) appendMessage(e.message);
            });
        }
    } catch (e) { usingWs = false; }

    // --- Polling fallback (shared hosting / BROADCAST_CONNECTION=log) ---
    let renderedCount = thread.querySelectorAll('.msg').length;

    function poll() {
        if (usingWs || !messagesUrl) return;
        const sep = messagesUrl.indexOf('?') === -1 ? '?' : '&';
        fetch(messagesUrl + sep + authParams())
            .then((r) => (r.ok ? r.json() : []))
            .then((msgs) => {
                if (!Array.isArray(msgs) || msgs.length <= renderedCount) return;
                // Append messages beyond the ones already rendered.
                const newOnes = msgs.slice(renderedCount);
                newOnes.forEach((m) => appendMessage(m));
                renderedCount = msgs.length;
            })
            .catch(() => {});
    }

    if (!usingWs) setInterval(poll, 5000);
    poll();

    const suggestBtn = document.getElementById('suggest-btn');
    if (suggestBtn) {
        suggestBtn.addEventListener('click', async () => {
            const res = await fetch(suggestBtn.getAttribute('data-url'));
            const data = await res.json();
            if (data.suggestion) {
                document.getElementById('reply-input').value = data.suggestion;
            }
        });
    }
});
