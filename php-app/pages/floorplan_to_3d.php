<?php
// Floor plan → 3D via Meshy through the FastAPI backend
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../config.php';
require_login();

// Meshy conversion can take a few minutes; extend this script's time limit
@set_time_limit(600);
@ini_set('max_execution_time', '600');

$result = null; $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hint = trim($_POST['hint'] ?? 'smart home floor plan');
    if (!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
        $error = 'Please choose a PNG or JPG image.';
    } else {
        $file = $_FILES['image'];
        $ctype = $file['type'] ?? '';
        if (!in_array($ctype, ['image/png','image/jpeg'], true)) {
            $error = 'Only PNG or JPG images are supported.';
        } else {
            // Submit multipart to FastAPI /meshify
            $cfile = new CURLFile($file['tmp_name'], $ctype, $file['name']);
            $payload = [ 'image' => $cfile, 'hint' => $hint ?: 'smart home floor plan' ];
            $ch = curl_init(AI_SERVICE_URL . '/meshify');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 420);       // total seconds
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15); // connect
            $resp = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_err = curl_error($ch);
            curl_close($ch);
            if ($resp === false || $http !== 200) {
                $error = 'Meshify service error. Please ensure FastAPI is running. ' . ($curl_err ?: 'HTTP ' . $http);
            } else {
                $data = json_decode($resp, true);
                if (!is_array($data) || empty($data['model_url'])) {
                    $error = 'No model URL returned. Try a different image.';
                } else {
                    $result = $data;
                }
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>
<h1>Generate 3D Floor Plan</h1>

<section class="card floorplan-page">
  <p class="muted">Upload a 2D floor plan (PNG/JPG). The AI service will generate a low‑poly GLB model. This may take 1–3 minutes.</p>
  <?php if ($error): ?><div class="alert warn"><?php echo h($error); ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" id="floorplanForm">
    <div class="upload-modal">
      <div class="dropzone" id="dropzone">
        <div class="drop-inner">
          <div class="cloud">☁️</div>
          <h3>Upload your file here</h3>
          <div class="muted small">Files supported: PNG, JPG</div>
          <div class="or">OR</div>
          <button type="button" class="btn btn-browse" id="browseBtn">BROWSE</button>
          <div class="muted small">Maximum size: 10MB</div>
        </div>
        <input type="file" name="image" id="imageInput" accept="image/png,image/jpeg" style="display:none">
      </div>

      <div class="file-list" id="fileList" aria-hidden="true"></div>

      <label style="display:block;margin-top:12px">Hint (optional)
        <input type="text" name="hint" placeholder="smart home floor plan" value="<?php echo h($_POST['hint'] ?? ''); ?>">
      </label>

      <div style="margin-top:12px;display:flex;gap:12px;align-items:center">
        <button type="submit" class="primary-btn">Generate 3D</button>
        <span class="muted">Generating 3D… this may take 1–3 minutes.</span>
      </div>
    </div>
  </form>
</section>

<?php if ($result && !empty($result['model_url'])): ?>
  <section class="card" style="margin-top:16px">
    <h2>Preview</h2>
    <script type="module" src="https://unpkg.com/@google/model-viewer/dist/model-viewer.min.js"></script>
    <model-viewer src="<?php echo h($result['model_url']); ?>" alt="3D Floor Plan" crossorigin="anonymous"
                  camera-controls auto-rotate ar shadow-intensity="1"
                  style="width:100%;height:520px;border-radius:12px;border:1px solid #22263a;background:#0e1120">
    </model-viewer>
    <p style="margin-top:10px"><a class="btn" href="<?php echo h($result['model_url']); ?>" download>Download GLB</a></p>
  </section>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
(() => {
  const drop = document.getElementById('dropzone');
  const input = document.getElementById('imageInput');
  const browse = document.getElementById('browseBtn');
  const fileList = document.getElementById('fileList');

  function renderFile(f){
    fileList.innerHTML = '';
    const row = document.createElement('div'); row.className = 'file-row';
    const icon = document.createElement('div'); icon.className = 'file-icon'; icon.textContent = '📄';
    const name = document.createElement('div'); name.className = 'file-name'; name.textContent = f.name;
    const size = document.createElement('div'); size.className = 'file-size'; size.textContent = (f.size/1024).toFixed(1)+' KB';
    const del = document.createElement('div'); del.className = 'file-delete'; del.title='Remove'; del.innerHTML='🗑️';
    del.onclick = ()=>{ input.value=''; fileList.innerHTML=''; fileList.setAttribute('aria-hidden','true'); };
    row.appendChild(icon); row.appendChild(name); row.appendChild(size); row.appendChild(del);
    fileList.appendChild(row); fileList.setAttribute('aria-hidden','false');
  }

  browse.addEventListener('click', ()=> input.click());
  input.addEventListener('change', (e)=>{ if (input.files && input.files[0]) renderFile(input.files[0]); });

  ['dragenter','dragover'].forEach(ev=> drop.addEventListener(ev, (e)=>{ e.preventDefault(); drop.classList.add('dragover'); }));
  ['dragleave','drop'].forEach(ev=> drop.addEventListener(ev, (e)=>{ e.preventDefault(); drop.classList.remove('dragover'); }));
  drop.addEventListener('drop', (e)=>{
    const f = e.dataTransfer.files && e.dataTransfer.files[0];
    if (f){ input.files = e.dataTransfer.files; renderFile(f); }
  });
})();
</script>
