/* CartPing Smart WhatsApp Widget
 * Loaded via Shopify ScriptTag or manual snippet with:
 *   <script src="https://your-app/js/widget.js" data-shop="shop.myshopify.com" async></script>
 *
 * Supports 4 types: simple_button | tooltip | chat_widget | smart_contextual
 * plus entry popup (discount for number capture) and exit popup (exit-intent/inactivity).
 */
(function () {
  'use strict';

  var script = document.currentScript;
  var shop = script && script.getAttribute('data-shop');
  var API_BASE = (script && script.getAttribute('data-base')) || '';
  var SESSION_KEY = '_cp_widget_' + (shop || 'x');
  var session = (function () {
    try { return localStorage.getItem(SESSION_KEY); } catch (e) {}
  })();
  if (!session) {
    session = 's_' + Math.random().toString(36).slice(2) + '_' + Date.now();
    try { localStorage.setItem(SESSION_KEY, session); } catch (e) {}
  }

  var state = { config: null, open: false, pageType: detectPageType() };

  function detectPageType() {
    var path = window.location.pathname;
    if (/\/products\/|^\/products$/.test(path)) return 'product';
    if (/\/cart/.test(path)) return 'cart';
    if (/\/orders\/|\/account\/orders|\/checkouts/.test(path)) return 'order';
    if (/\/collections\//.test(path)) return 'collection';
    if (/\/blogs?/.test(path)) return 'blog';
    return 'home';
  }

  function api(path, body, method) {
    var url = API_BASE + '/widget/' + path + '?shop=' + encodeURIComponent(shop);
    return fetch(url, {
      method: method || 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: body ? JSON.stringify(body) : undefined,
    }).then(function (r) { return r.json(); });
  }

  function css(styles) {
    var el = document.createElement('style');
    el.textContent = styles;
    document.head.appendChild(el);
  }

  function el(tag, attrs, html) {
    var n = document.createElement(tag);
    if (attrs) for (var k in attrs) n.setAttribute(k, attrs[k]);
    if (html != null) n.innerHTML = html;
    return n;
  }

  /* ---------------------------- Session boot ---------------------------- */
  api('session', {
    session_key: session,
    page_type: state.pageType,
    referrer: document.referrer || '',
    fingerprint: fingerprint(),
  }).then(function () { /* session recorded */ });

  function fingerprint() {
    try {
      var a = localStorage.getItem('_cp_fid');
      if (!a) { a = Math.random().toString(36).slice(2); localStorage.setItem('_cp_fid', a); }
      return a;
    } catch (e) { return null; }
  }

  /* --------------------------- Build & mount ---------------------------- */
  function load() {
    return api('config').then(function (cfg) {
      if (!cfg || !cfg.enabled) return;
      state.config = cfg;
      trackView();
      mountLauncher();
      maybeShowEntryPopup(cfg);
      bindExitPopup(cfg);
    });
  }

  function trackView() {
    if (state.pageType === 'product') {
      var m = window.location.pathname.match(/\/products\/([\w-]+)/);
      var pid = document.querySelector('[data-product-id]');
      api('view', {
        session_key: session,
        page_type: state.pageType,
        product_id: pid ? pid.getAttribute('data-product-id') : (m ? m[1] : null),
      });
    }
  }

  function mountLauncher() {
    var cfg = state.config;
    var launcher = cfg.launcher || {};
    var color = launcher.color || '#25D366';
    var pos = launcher.position || 'bottom-right';

    css(
      '#cp-launcher{border:none;cursor:pointer;position:fixed;z-index:99999;border-radius:50%;' +
      'width:60px;height:60px;background:' + color + ';box-shadow:0 4px 14px rgba(0,0,0,.3);' +
      'display:flex;align-items:center;justify-content:center;font-size:28px}' +
      '#cp-launcher[data-pos="bottom-left"]{left:18px;bottom:18px}' +
      '#cp-launcher[data-pos="bottom-right"]{right:18px;bottom:18px}' +
      '#cp-tip{position:fixed;bottom:92px;right:18px;z-index:99999;background:#fff;color:#111;' +
      'padding:10px 14px;border-radius:10px;box-shadow:0 4px 14px rgba(0,0,0,.2);font-size:14px;max-width:240px}'
    );

    var launcherBtn = el('button', { id: 'cp-launcher', 'data-pos': pos }, launcher.icon || '💬');
    launcherBtn.addEventListener('click', function () {
      if (cfg.type === 'chat_widget') { toggleChat(); }
      else if (cfg.type === 'simple_button') { openWhatsApp(); }
      else if (cfg.type === 'smart_contextual') { smartAction(); }
      else { openWhatsApp(); }
    });
    document.body.appendChild(launcherBtn);

    if (cfg.type === 'tooltip' && cfg.tooltip) {
      var tip = el('div', { id: 'cp-tip' }, cfg.tooltip.text || 'Chat with us on WhatsApp 👋');
      document.body.appendChild(tip);
      setTimeout(function () { tip.remove(); }, (cfg.tooltip.delay_ms || 3000) + 1500);
    }

    if (cfg.type === 'smart_contextual') {
      mountChatPanel(cfg);
    }
  }

  function whatsappUrl(message) {
    var num = state.config.whatsapp_number || '15551234567';
    var text = encodeURIComponent(message || 'Hi! I have a question about your store.');
    return 'https://wa.me/' + num.replace(/\D/g, '') + '?text=' + text;
  }

  function openWhatsApp(msg) {
    window.open(whatsappUrl(msg), '_blank');
  }

  function smartAction() {
    var sc = state.config.smart_contextual || {};
    var page = sc[state.pageType];
    var action = page && (page.action || 'wa');
    var message = page && page.message;

    recordClick();
    if (action === 'chat') { toggleChat(); }
    else { window.open(whatsappUrl(message), '_blank'); }
  }

  function recordClick() {
    api('trackView', { session_key: session, page_type: state.pageType }).catch(function () {});
  }

  /* ---------------------------- Chat widget ----------------------------- */
  function mountChatPanel() {
    css('#cp-chat{position:fixed;right:18px;bottom:92px;z-index:99999;width:340px;max-width:92vw;' +
      'background:#fff;color:#111;border-radius:14px;box-shadow:0 8px 30px rgba(0,0,0,.3);display:none;' +
      'flex-direction:column;height:460px}' +
      '#cp-chat header{padding:12px 16px;background:#075E54;color:#fff;border-radius:14px 14px 0 0}' +
      '#cp-chat .msgs{flex:1;overflow-y:auto;padding:14px;font-size:14px}' +
      '#cp-chat .b{background:#ece5dd;border-radius:10px;padding:8px 12px;margin:6px 0;max-width:80%}' +
      '#cp-chat .b.me{margin-left:auto;background:#dcf8c6}' +
      '#cp-chat footer{display:flex;border-top:1px solid #eee}' +
      '#cp-chat input{flex:1;border:none;padding:12px;font-size:14px;outline:none}' +
      '#cp-chat button{border:none;background:#075E54;color:#fff;padding:0 16px}');

    var panel = el('div', { id: 'cp-chat' },
      '<header>Store Assistant</header>' +
      '<div class="msgs"><div class="b">Hi! 👋 Ask me about orders, products or shipping.</div></div>' +
      '<footer><input placeholder="Type a message…"><button>➤</button></footer>');

    var input = panel.querySelector('input');
    var msgs = panel.querySelector('.msgs');

    panel.querySelector('button').addEventListener('click', send);
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter') send(); });

    function send() {
      var text = input.value.trim();
      if (!text) return;
      msgs.insertAdjacentHTML('beforeend', '<div class="b me">' + escapeHtml(text) + '</div>');
      input.value = '';
      api('agent/message', { shop: shop, session_key: session, message: text }, 'POST').catch(function () {})
        .then(function (r) {
          if (r && r.reply) msgs.insertAdjacentHTML('beforeend', '<div class="b">' + escapeHtml(r.reply) + '</div>');
          else if (r && r.error) msgs.insertAdjacentHTML('beforeend', '<div class="b">Sorry, the assistant is unavailable right now.</div>');
          msgs.scrollTop = msgs.scrollHeight;
        });
    }

    document.body.appendChild(panel);
    state.chatPanel = panel;
  }

  function toggleChat() {
    if (!state.chatPanel) mountChatPanel();
    var open = state.chatPanel.style.display === 'flex';
    state.chatPanel.style.display = open ? 'none' : 'flex';
    recordClick();
  }

  function escapeHtml(s) {
    return s.replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ---------------------------- Entry popup ----------------------------- */
  function maybeShowEntryPopup(cfg) {
    var ep = cfg.entry_popup || {};
    if (!ep.enabled) return;
    var shown = sessionStorage.getItem('_cp_entry_shown');
    if (shown) return;
    var delay = ep.delay_ms || 4000;
    setTimeout(function () {
      api('popup', { session_key: session, popup: 'entry' });
      var overlay = el('div', { id: 'cp-overlay' },
        '<div class="cp-box"><button id="cp-close" style="float:right">✕</button>' +
        '<h3>' + (ep.offer || 'Get 10% off!') + '</h3>' +
        '<p>Enter your WhatsApp number to receive your discount code.</p>' +
        '<input id="cp-num" placeholder="+15551234567"><br><br>' +
        '<button id="cp-go" class="cp-cta">Send me the code</button></div>');
      css('#cp-overlay{position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center}' +
        '.cp-box{background:#fff;color:#111;padding:26px;border-radius:14px;max-width:340px;width:90%;text-align:center}' +
        '.cp-cta{background:#25D366;border:none;color:#06220f;padding:10px 18px;border-radius:8px;font-weight:600}' +
        '#cp-num{padding:10px;border:1px solid #ccc;border-radius:8px;width:100%;box-sizing:border-box}');
      var num = overlay.querySelector('#cp-num');
      overlay.querySelector('#cp-go').addEventListener('click', function () {
        if (!num.value) return;
        api('optin', { session_key: session, wa_number: num.value })
          .then(function () { overlay.remove(); });
      });
      overlay.querySelector('#cp-close').addEventListener('click', function () { overlay.remove(); });
      document.body.appendChild(overlay);
      sessionStorage.setItem('_cp_entry_shown', '1');
    }, delay);
  }

  /* ---------------------------- Exit popup ------------------------------ */
  function bindExitPopup(cfg) {
    var xp = cfg.exit_popup || {};
    if (!xp.enabled) return;

    function showExit() {
      if (sessionStorage.getItem('_cp_exit_shown')) return;
      sessionStorage.setItem('_cp_exit_shown', '1');
      api('popup', { session_key: session, popup: 'exit' });
      var box = el('div', { id: 'cp-overlay' },
        '<div class="cp-box"><button id="cp-close" style="float:right">✕</button>' +
        '<h3>Wait! 👋</h3><p>' + (xp.message || 'We can help you right now on WhatsApp.') + '</p>' +
        (xp.mode === 'qr'
          ? '<div>Scan the QR on the WhatsApp widget to chat with us.</div>'
          : '<input id="cp-num" placeholder="+15551234567"><br><br><button id="cp-go" class="cp-cta">Get help on WhatsApp</button>') +
        '</div>');
      css('#cp-overlay{position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center}' +
        '.cp-box{background:#fff;color:#111;padding:26px;border-radius:14px;max-width:340px;width:90%;text-align:center}' +
        '.cp-cta{background:#25D366;border:none;color:#06220f;padding:10px 18px;border-radius:8px;font-weight:600}' +
        '#cp-num{padding:10px;border:1px solid #ccc;border-radius:8px;width:100%;box-sizing:border-box}');
      var num = box.querySelector('#cp-num');
      if (num) {
        box.querySelector('#cp-go').addEventListener('click', function () {
          if (num.value) api('optin', { session_key: session, wa_number: num.value });
          window.open(whatsappUrl(), '_blank');
          box.remove();
        });
      }
      box.querySelector('#cp-close').addEventListener('click', function () { box.remove(); });
      document.body.appendChild(box);
    }

    // Exit intent.
    document.addEventListener('mouseout', function (e) {
      if (!e.relatedTarget && !e.toElement && e.clientY <= 0) showExit();
    });
    // Inactivity.
    var t;
    var reset = function () { clearTimeout(t); t = setTimeout(showExit, (xp.inactivity_timeout_ms || 30000)); };
    ['mousemove', 'scroll', 'keydown'].forEach(function (ev) { document.addEventListener(ev, reset); });
    reset();
  }

  if (script && script.getAttribute('data-auto') !== 'false') {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', load);
    } else {
      load();
    }
  }

  window.CartPingWidget = { reload: load, shop: shop };
})();
