{{-- Floating "AWAS Assistant" chat window for residents; see public/assets/js/chatbot.js. --}}
<div id="agasChatbot" class="chatbot-widget" data-csrf="{{ csrf_token() }}" data-endpoint="{{ route('chatbot') }}">
  <button id="chatbotToggle" class="chatbot-fab" type="button" aria-label="Open AWAS Assistant chat" title="Chat with AWAS Assistant">
    <span class="chatbot-fab-icon" aria-hidden="true"><i class="ph ph-chat-circle-dots" aria-hidden="true"></i></span>
  </button>

  <div id="chatbotPanel" class="chatbot-panel" role="dialog" aria-label="AWAS Assistant chat window" aria-hidden="true">
    <div class="chatbot-header">
      <div class="chatbot-header-info">
        <span class="chatbot-avatar" aria-hidden="true"><i class="ph ph-drop" aria-hidden="true"></i></span>
        <div>
          <div class="chatbot-title">AWAS Assistant</div>
          <div class="chatbot-subtitle">Online · Ready to help</div>
        </div>
      </div>
      <div class="chatbot-header-actions">
        <button type="button" id="chatbotMinimize" class="chatbot-icon-btn" title="Minimize">&#8211;</button>
        <button type="button" id="chatbotClose" class="chatbot-icon-btn" title="Close">&times;</button>
      </div>
    </div>

    <div id="chatbotMessages" class="chatbot-messages">
      <div class="chatbot-msg bot">
        <div class="chatbot-bubble">Hi! I'm <strong>AWAS Assistant</strong>. I can help with questions about your water bill, consumption, payments, and account. What would you like to know?</div>
      </div>
    </div>

    <div id="chatbotQuickReplies" class="chatbot-quick-replies">
      <button type="button" class="chatbot-chip" data-q="How is my bill calculated?"><i class="ph ph-drop" aria-hidden="true"></i> How is my bill calculated?</button>
      <button type="button" class="chatbot-chip" data-q="View my current bill"><i class="ph ph-receipt" aria-hidden="true"></i> View my current bill</button>
      <button type="button" class="chatbot-chip" data-q="What is my consumption?"><i class="ph ph-chart-bar" aria-hidden="true"></i> What is my consumption?</button>
      <button type="button" class="chatbot-chip" data-q="How can I pay?"><i class="ph ph-wallet" aria-hidden="true"></i> How can I pay?</button>
      <button type="button" class="chatbot-chip" data-q="When is my due date?"><i class="ph ph-calendar-blank" aria-hidden="true"></i> When is my due date?</button>
      <button type="button" class="chatbot-chip" data-q="I forgot my password"><i class="ph ph-lock-key" aria-hidden="true"></i> I forgot my password</button>
      <button type="button" class="chatbot-chip" data-q="How does AWAS work?"><i class="ph ph-book-open" aria-hidden="true"></i> How does AWAS work?</button>
    </div>

    <form id="chatbotForm" class="chatbot-input-row" autocomplete="off">
      <label for="chatbotInput" class="sr-only">Type your question</label>
      <input type="text" id="chatbotInput" class="chatbot-input" placeholder="Type your question…" maxlength="500" required>
      <button type="submit" class="chatbot-send" title="Send" aria-label="Send message"><i class="ph ph-paper-plane-right" aria-hidden="true"></i></button>
    </form>
    <div class="chatbot-disclaimer">AWAS Assistant shares general info only — it can't verify payments or change your records.</div>
  </div>
</div>
