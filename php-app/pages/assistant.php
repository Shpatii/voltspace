<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';
require_login();
include __DIR__ . '/../includes/header.php';
?>
<h1>Assistant</h1>

<section class="card chat-card">
  <div id="messages" class="messages" aria-live="polite" aria-label="Chat transcript"></div>
  <form id="askForm" class="chat-input" onsubmit="return sendMsg(event)">
    <input type="text" id="question" placeholder="Ask something… (Enter to send)" autocomplete="off" required>
    <button type="submit">Send</button>
  </form>
</section>

<script>
const BASE_URL = <?php echo json_encode(BASE_URL); ?>;
const KEY = 'voltspace_chat_v1';
const $messages = document.getElementById('messages');
const $input = document.getElementById('question');

function loadHistory(){
  try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch(_) { return []; }
}
function saveHistory(items){ localStorage.setItem(KEY, JSON.stringify(items)); }

function addMsg(role, text){
  const el = document.createElement('div');
  el.className = 'msg ' + (role === 'user' ? 'user' : 'bot');
  el.innerHTML = `<div class="bubble">${escapeHtml(text)}</div>`;
  $messages.appendChild(el);
  $messages.scrollTop = $messages.scrollHeight;
}

function escapeHtml(s){
  return s.replace(/[&<>\"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
}

async function sendMsg(e){
  e.preventDefault();
  const q = $input.value.trim();
  if(!q) return false;
  $input.value='';
  addMsg('user', q);
  addMsg('bot', 'Thinking…');

  const history = loadHistory();
  history.push({role:'user', text:q});
  saveHistory(history);

  try {
    const res = await fetch(BASE_URL + '/api/agent_query.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({question:q})
    });
    const data = await res.json();
    // Replace the last bot placeholder with real answer
    const last = $messages.querySelector('.msg.bot:last-child .bubble');
    if (last) last.textContent = data.answer || 'No answer';
    history.push({role:'bot', text: data.answer || 'No answer'});
    saveHistory(history);
  } catch (err) {
    const last = $messages.querySelector('.msg.bot:last-child .bubble');
    if (last) last.textContent = 'Error contacting assistant';
  }
  return false;
}

// Submit on Enter, allow Shift+Enter for newline (for future textarea)
$input.addEventListener('keydown', (e)=>{
  if(e.key==='Enter' && !e.shiftKey){ e.preventDefault(); document.getElementById('askForm').dispatchEvent(new Event('submit', {cancelable:true})); }
});

// Bootstrap from localStorage
(function(){
  const history = loadHistory();
  history.forEach(m => addMsg(m.role, m.text));
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
