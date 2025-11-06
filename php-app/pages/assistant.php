<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';
require_login();
include __DIR__ . '/../includes/header.php';
?>
<h1>Assistant</h1>

<form id="askForm" class="card" onsubmit="return ask(event)">
    <label>Ask a question
        <input type="text" name="question" id="question" placeholder="Which lights were on > 8h?" required>
    </label>
    <button type="submit">Ask</button>
    <div id="answer" class="answer"></div>
</form>

<script>
const BASE_URL = <?php echo json_encode(BASE_URL); ?>;
async function ask(e){
  e.preventDefault();
  const q = document.getElementById('question').value.trim();
  if(!q) return false;
  const ans = document.getElementById('answer');
  ans.textContent = 'Thinking...';
  try {
    const res = await fetch(BASE_URL + '/api/agent_query.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({question:q})});
    const data = await res.json();
    ans.textContent = data.answer || 'No answer';
  } catch (err) {
    ans.textContent = 'Error contacting assistant';
  }
  return false;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
