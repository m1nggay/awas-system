/**
 * chatbot.js — "AWAS Assistant" floating chat widget behavior:
 * open/close, sending messages to the /chatbot route, rendering the
 * conversation, and quick-reply chips. No API keys or secrets live here.
 */
(function () {
  const widget = document.getElementById('agasChatbot');
  if (!widget) return;

  const toggleBtn = document.getElementById('chatbotToggle');
  const panel = document.getElementById('chatbotPanel');
  const closeBtn = document.getElementById('chatbotClose');
  const minimizeBtn = document.getElementById('chatbotMinimize');
  const messages = document.getElementById('chatbotMessages');
  const form = document.getElementById('chatbotForm');
  const input = document.getElementById('chatbotInput');
  const quickReplies = document.getElementById('chatbotQuickReplies');
  const endpoint = widget.getAttribute('data-endpoint');
  const csrfToken = widget.getAttribute('data-csrf');

  let sending = false;

  function openPanel() {
    panel.classList.add('open');
    panel.setAttribute('aria-hidden', 'false');
    toggleBtn.classList.add('active');
    setTimeout(() => input.focus(), 150);
  }

  function closePanel() {
    panel.classList.remove('open');
    panel.setAttribute('aria-hidden', 'true');
    toggleBtn.classList.remove('active');
  }

  toggleBtn.addEventListener('click', () => {
    panel.classList.contains('open') ? closePanel() : openPanel();
  });
  closeBtn.addEventListener('click', closePanel);
  minimizeBtn.addEventListener('click', closePanel);

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function appendMessage(text, sender) {
    const row = document.createElement('div');
    row.className = 'chatbot-msg ' + sender;
    const bubble = document.createElement('div');
    bubble.className = 'chatbot-bubble';
    bubble.innerHTML = escapeHtml(text).replace(/\n/g, '<br>');
    row.appendChild(bubble);
    messages.appendChild(row);
    messages.scrollTop = messages.scrollHeight;
  }

  function showTyping() {
    const row = document.createElement('div');
    row.className = 'chatbot-msg bot';
    row.id = 'chatbotTypingRow';
    row.innerHTML = '<div class="chatbot-bubble chatbot-typing"><span class="chatbot-dot"></span><span class="chatbot-dot"></span><span class="chatbot-dot"></span></div>';
    messages.appendChild(row);
    messages.scrollTop = messages.scrollHeight;
  }

  function hideTyping() {
    const row = document.getElementById('chatbotTypingRow');
    if (row) row.remove();
  }

  async function sendMessage(rawText) {
    const text = (rawText || '').trim();
    if (!text || sending) return;

    sending = true;
    appendMessage(text, 'user');
    input.value = '';
    showTyping();

    try {
      const res = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({ message: text }),
      });
      const data = await res.json().catch(() => null);
      hideTyping();
      if (data && data.reply) {
        appendMessage(data.reply, 'bot');
      } else if (data && data.error) {
        appendMessage(data.error, 'bot');
      } else {
        appendMessage("Sorry, something went wrong. Please try again.", 'bot');
      }
    } catch (err) {
      hideTyping();
      appendMessage("I'm having trouble connecting right now. Please try again in a moment.", 'bot');
    } finally {
      sending = false;
    }
  }

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    sendMessage(input.value);
  });

  quickReplies.querySelectorAll('.chatbot-chip').forEach((chip) => {
    chip.addEventListener('click', () => sendMessage(chip.getAttribute('data-q')));
  });
})();
