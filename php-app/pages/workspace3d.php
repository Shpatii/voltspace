<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/utils.php';
require_login();
$model = isset($_GET['model']) ? trim($_GET['model']) : '';
include __DIR__ . '/../includes/header.php';
?>
<h1>3D Workspace</h1>

<div class="grid device-split">
  <section class="card">
    <h2>Tools</h2>
    <form class="form" onsubmit="return false;">
      <label>Load From URL
        <input id="modelUrl" type="url" placeholder="https://example.com/model.glb" value="<?php echo h($model); ?>" />
      </label>
      <div class="inline">
        <button id="btnLoadUrl" type="button">Load URL</button>
        <label class="btn" style="cursor:pointer;">
          <input id="fileInput" type="file" accept=".glb,.gltf,.zip,model/*" style="display:none" /> Load File
        </label>
      </div>
      <hr />
      <div class="inline">
        <button id="btnAddCube" type="button">Add Cube</button>
        <button id="btnAddSphere" type="button">Add Sphere</button>
        <button id="btnResetCam" type="button">Reset Camera</button>
      </div>
      <label>Transform Mode
        <select id="mode">
          <option value="translate">Translate</option>
          <option value="rotate">Rotate</option>
          <option value="scale">Scale</option>
        </select>
      </label>
      <div class="inline">
        <label class="inline"><input type="checkbox" id="snap" /> Snap</label>
        <label class="inline"><input type="checkbox" id="grid" checked /> Grid</label>
        <label class="inline"><input type="checkbox" id="ground" checked /> Ground</label>
        <label class="inline"><input type="checkbox" id="axes" /> Axes</label>
      </div>
      <label>Ambient Light
        <input id="ambient" type="range" min="0" max="2" value="0.6" step="0.1" />
      </label>
      <div class="inline">
        <button id="btnExport" type="button">Export glTF</button>
        <button id="btnClear" type="button">Clear</button>
      </div>
      <p class="muted">Tip: You can pass a Meshy GLB URL via ?model=... to pre-load.</p>
    </form>
  </section>

  <section class="card" style="position:relative;min-height:520px">
    <div id="viewport" style="position:relative; width:100%; height:70vh; min-height:480px;"></div>
  </section>
</div>

<script type="module">
import * as THREE from 'https://unpkg.com/three@0.159.0/build/three.module.js';
import { OrbitControls } from 'https://unpkg.com/three@0.159.0/examples/jsm/controls/OrbitControls.js';
import { TransformControls } from 'https://unpkg.com/three@0.159.0/examples/jsm/controls/TransformControls.js';
import { GLTFLoader } from 'https://unpkg.com/three@0.159.0/examples/jsm/loaders/GLTFLoader.js';
import { GLTFExporter } from 'https://unpkg.com/three@0.159.0/examples/jsm/exporters/GLTFExporter.js';

const vp = document.getElementById('viewport');
const renderer = new THREE.WebGLRenderer({ antialias:true, alpha:true });
renderer.setPixelRatio(window.devicePixelRatio);
renderer.setSize(vp.clientWidth, vp.clientHeight);
renderer.outputColorSpace = THREE.SRGBColorSpace;
vp.appendChild(renderer.domElement);

const scene = new THREE.Scene();
scene.background = new THREE.Color(0x0b1220);
const camera = new THREE.PerspectiveCamera(60, vp.clientWidth/vp.clientHeight, 0.1, 2000);
camera.position.set(4,3,6);
const controls = new OrbitControls(camera, renderer.domElement);
controls.target.set(0,1,0);
controls.update();

// Lights
const hemi = new THREE.HemisphereLight(0xffffff, 0x404040, 0.4); scene.add(hemi);
const amb = new THREE.AmbientLight(0xffffff, 0.6); scene.add(amb);
const dir = new THREE.DirectionalLight(0xffffff, 0.8); dir.position.set(5,10,7); dir.castShadow = true; scene.add(dir);

// Grid / Ground / Axes
const grid = new THREE.GridHelper(20, 20, 0x224466, 0x223344); grid.material.opacity = 0.25; grid.material.transparent = true; scene.add(grid);
const groundGeo = new THREE.PlaneGeometry(100,100);
const groundMat = new THREE.MeshStandardMaterial({ color:0x0f172a, roughness:0.95, metalness:0.0 });
const ground = new THREE.Mesh(groundGeo, groundMat); ground.rotation.x = -Math.PI/2; ground.receiveShadow = true; scene.add(ground);
const axes = new THREE.AxesHelper(2); axes.visible = false; scene.add(axes);

// Selection + transform
let selected = null;
const transform = new TransformControls(camera, renderer.domElement);
transform.addEventListener('dragging-changed', e => { controls.enabled = !e.value; });
scene.add(transform);

// Raycaster for clicking
const ray = new THREE.Raycaster();
const mouse = new THREE.Vector2();
renderer.domElement.addEventListener('pointerdown', ev => {
  const rect = renderer.domElement.getBoundingClientRect();
  mouse.x = ((ev.clientX - rect.left) / rect.width) * 2 - 1;
  mouse.y = -((ev.clientY - rect.top) / rect.height) * 2 + 1;
  ray.setFromCamera(mouse, camera);
  const objects = [];
  scene.traverse(o => { if (o.isMesh && o !== ground) objects.push(o); });
  const hit = ray.intersectObjects(objects, true)[0];
  if (hit) selectObject(hit.object);
});

function selectObject(obj){
  selected = obj;
  transform.attach(selected);
  highlight(selected);
}

function highlight(obj){
  scene.traverse(o => { if (o.material && o.material.emissive) o.material.emissive.setHex(0x000000); });
  if (obj && obj.material && obj.material.emissive) obj.material.emissive.setHex(0x223366);
}

// Helpers
function addCube(){
  const g = new THREE.BoxGeometry(1,1,1);
  const m = new THREE.MeshStandardMaterial({ color:0x8fa8ff, roughness:.6, metalness:.1 });
  const mesh = new THREE.Mesh(g,m); mesh.position.set(0,0.5,0); mesh.castShadow = true; mesh.receiveShadow = true; scene.add(mesh); selectObject(mesh);
}
function addSphere(){
  const g = new THREE.SphereGeometry(0.6, 32, 16);
  const m = new THREE.MeshStandardMaterial({ color:0xffc078, roughness:.5, metalness:.1 });
  const mesh = new THREE.Mesh(g,m); mesh.position.set(0,0.6,0); mesh.castShadow = true; mesh.receiveShadow = true; scene.add(mesh); selectObject(mesh);
}

// Loaders
const loader = new GLTFLoader();
async function loadFromUrl(url){
  try{
    const gltf = await loader.loadAsync(url);
    placeModel(gltf.scene);
  }catch(e){ alert('Failed to load model: '+e); }
}
function loadFromFile(file){
  const url = URL.createObjectURL(file);
  loadFromUrl(url);
}
function placeModel(root){
  root.traverse(o=>{ if (o.isMesh){ o.castShadow = true; o.receiveShadow = true; if (o.material) { o.material.side = THREE.FrontSide; } }});
  // Center and scale to a reasonable size
  const box = new THREE.Box3().setFromObject(root);
  const size = new THREE.Vector3(); box.getSize(size);
  const maxDim = Math.max(size.x, size.y, size.z) || 1;
  const scale = 3 / maxDim; root.scale.setScalar(scale);
  box.setFromObject(root); const center = box.getCenter(new THREE.Vector3());
  root.position.sub(center.multiplyScalar(1));
  scene.add(root);
  selectObject(root);
}

// Export
const exporter = new GLTFExporter();
function exportGLTF(){
  exporter.parse(scene, res => {
    const blob = new Blob([res instanceof ArrayBuffer ? res : JSON.stringify(res)], {type: 'model/gltf-binary'});
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'workspace.glb'; a.click();
  }, { binary: true, includeCustomExtensions: true, onlyVisible: true });
}

// UI wiring
document.getElementById('btnAddCube').onclick = addCube;
document.getElementById('btnAddSphere').onclick = addSphere;
document.getElementById('btnResetCam').onclick = ()=>{ camera.position.set(4,3,6); controls.target.set(0,1,0); controls.update(); };
document.getElementById('btnLoadUrl').onclick = ()=>{ const u=(document.getElementById('modelUrl').value||'').trim(); if(u) loadFromUrl(u); };
document.getElementById('fileInput').onchange = e => { const f=e.target.files[0]; if(f) loadFromFile(f); };
document.getElementById('mode').onchange = e => transform.setMode(e.target.value);
document.getElementById('snap').onchange = e => {
  const on = e.target.checked; transform.setTranslationSnap(on?0.5:null); transform.setRotationSnap(on?THREE.MathUtils.degToRad(15):null); transform.setScaleSnap(on?0.1:null);
};
document.getElementById('grid').onchange = e => grid.visible = e.target.checked;
document.getElementById('ground').onchange = e => ground.visible = e.target.checked;
document.getElementById('axes').onchange = e => axes.visible = e.target.checked;
document.getElementById('ambient').oninput = e => amb.intensity = parseFloat(e.target.value || '0.6');
document.getElementById('btnExport').onclick = exportGLTF;
document.getElementById('btnClear').onclick = ()=>{
  const toRemove=[]; scene.traverse(o=>{ if (o.parent===scene && o.isObject3D && o!==ground && o!==grid && o!==axes && !o.isLight) toRemove.push(o); });
  toRemove.forEach(o=>scene.remove(o)); transform.detach(); selected=null;
};

// Resize handling
function onResize(){
  const w = vp.clientWidth, h = vp.clientHeight;
  camera.aspect = w/h; camera.updateProjectionMatrix(); renderer.setSize(w,h);
}
window.addEventListener('resize', onResize);

// Animate
renderer.setAnimationLoop(()=>{ renderer.render(scene, camera); });

// Pre-load from query if provided
const initialUrl = (document.getElementById('modelUrl').value||'').trim();
if (initialUrl) { loadFromUrl(initialUrl); }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

