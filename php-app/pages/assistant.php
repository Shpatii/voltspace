<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';
require_login();
include __DIR__ . '/../includes/header.php';
?>
<h1>Assistant</h1>
<style>
    body{margin:0;background:linear-gradient(180deg,#eef5ff 0%, #e9f1ff 35%, #f5f8ff 100%);color:var(--text);font:13px/1.35 "Segoe UI",system-ui,-apple-system,sans-serif;background-attachment:fixed}
</style>

<section class="card chat-card">
  <div class="chat-toolbar">
    <span class="muted">Ask VoltSpace</span>
    <button id="clearChat" type="button" class="btn">Clear Chat</button>
  </div>
  <div id="messages" class="messages" aria-live="polite" aria-label="Chat transcript"></div>
  <form id="askForm" class="chat-input" onsubmit="return sendMsg(event)">
    <input type="text" id="question" placeholder="Ask something (Enter to send)" autocomplete="off" required>
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
  el.innerHTML = '<div class="bubble">' + escapeHtml(text) + '</div>';
  $messages.appendChild(el);
  $messages.scrollTop = $messages.scrollHeight;
}

function escapeHtml(s){
  return (s||'').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
}

async function sendMsg(e){
  e.preventDefault();
  const q = ($input.value || '').trim();
  if(!q) return false;
  $input.value='';
  addMsg('user', q);
  addMsg('bot', 'Thinking...');

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

$input.addEventListener('keydown', (e)=>{
  if(e.key==='Enter' && !e.shiftKey){ e.preventDefault(); document.getElementById('askForm').dispatchEvent(new Event('submit', {cancelable:true})); }
});

(function(){
  const history = loadHistory();
  history.forEach(m => addMsg(m.role, m.text));
})();

(function(){
  const btn = document.getElementById('clearChat');
  if (!btn) return;
  btn.addEventListener('click', ()=>{
    try { localStorage.removeItem(KEY); } catch(err) {}
    $messages.innerHTML = '';
  });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
