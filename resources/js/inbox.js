import './bootstrap';

/**
 * Real-time inbox: subscribes to the store channel over Reverb and appends
 * new messages as they arrive. Also wires the AI-suggested reply button.
 */
document.addEventListener('DOMContentLoaded', function () {
    const thread = document.getElementById('thread');
    if (!thread) return;

    const storeId = window.StoreId;
    const conversationId = thread.getAttribute('data-conversation');

    // Subscribe to the specific conversation (and the store-wide feed).
    window.Echo.private('conversation.' + conversationId).listen('InboxMessageEvent', (e) => {
        if (!e.message) return;
        appendMessage(e.message);
    });

    window.Echo.private('store.' + storeId).listen('InboxMessageEvent', (e) => {
        if (e.conversation_id !== conversationId) return;
        if (!e.message) return;
        appendMessage(e.message);
    });

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
