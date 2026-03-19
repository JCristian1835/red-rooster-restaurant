<?php
/**
 * Red Rooster Restaurant — Pantalla de Cocina
 * Pantalla de cocina para ver y gestionar pedidos en tiempo real.
 */
require_once __DIR__ . '/includes/security.php';
set_security_headers();
start_secure_session();
$csrf = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cocina — Red Rooster</title>
<style>
@font-face { font-family:'BigNoodle'; src:url('assets/fonts/big_noodle_titling.ttf') format('truetype'); }

*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
:root {
  --red:#CC1212; --red-dark:#8B0000;
  --black:#0D0D0D; --charcoal:#1A1A1A; --smoke:#252525; --ash:#3A3A3A;
  --gold:#E8A020; --white:#FFFFFF; --gray:#888;
  --green:#2ECC71; --orange:#E67E22; --blue:#3498DB; --yellow:#F1C40F;
}
html,body { height:100%; background:var(--black); color:var(--white); font-family:'BigNoodle',sans-serif; overflow-x:hidden; }

/* ── TOPBAR ── */
#topbar {
  background:rgba(13,13,13,.97); border-bottom:3px solid var(--red);
  display:flex; align-items:center; justify-content:space-between;
  padding:12px 20px; position:sticky; top:0; z-index:100;
}
#topbar h1 { font-size:22px; letter-spacing:4px; color:var(--white); }
#topbar .right { display:flex; align-items:center; gap:12px; }
#status-dot { width:10px; height:10px; border-radius:50%; background:var(--gray); }
#status-dot.ok  { background:var(--green); animation:pulse 2s infinite; }
#status-txt { font-size:12px; letter-spacing:2px; color:var(--gray); }
#btn-logout { background:var(--ash); border:none; color:var(--gray); font-family:'BigNoodle',sans-serif; font-size:12px; letter-spacing:2px; padding:6px 14px; cursor:pointer; }
#btn-logout:hover { color:var(--white); }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

/* ── LOGIN ── */
#screen-login {
  display:flex; flex-direction:column; align-items:center; justify-content:center;
  min-height:100vh; gap:28px; padding:40px;
}
#screen-login h2 { font-size:18px; letter-spacing:4px; color:var(--gold); }
#pin-dots { display:flex; gap:16px; }
.pin-dot { width:18px; height:18px; border-radius:50%; border:2px solid var(--gray); background:transparent; transition:all .15s; }
.pin-dot.filled { background:var(--red); border-color:var(--red); }
#pin-error { color:var(--red); font-size:13px; letter-spacing:2px; min-height:20px; text-align:center; }
#keypad { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; width:280px; }
.key {
  background:var(--charcoal); border:1px solid var(--ash); color:var(--white);
  font-family:'BigNoodle',sans-serif; font-size:26px; padding:18px 0; cursor:pointer;
  transition:background .1s; text-align:center; border-radius:4px;
}
.key:hover { background:var(--ash); }
.key.del { font-size:18px; color:var(--gray); }
.key.ok  { background:var(--red); color:var(--white); }

/* ── KITCHEN ── */
#screen-kitchen { display:none; padding:16px; min-height:calc(100vh - 61px); }
#orders-header {
  display:flex; align-items:center; gap:12px; margin-bottom:16px; flex-wrap:wrap;
}
#orders-header h2 { font-size:16px; letter-spacing:3px; color:var(--gold); flex:1; }
#order-count { font-size:13px; letter-spacing:2px; color:var(--gray); }
#orders-grid {
  display:grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap:16px;
}

/* ── ORDER CARD ── */
.order-card {
  background:var(--charcoal); border:3px solid var(--ash); border-radius:8px;
  overflow:hidden; transition:border-color .3s, box-shadow .3s;
}
.order-card.pendiente  { border-color:var(--yellow); box-shadow:0 0 12px rgba(241,196,15,.2); }
.order-card.preparando { border-color:var(--blue);   box-shadow:0 0 12px rgba(52,152,219,.2); }
.order-card.new-flash  { animation:flash .5s 3; }
@keyframes flash { 0%,100%{border-color:var(--yellow)} 50%{border-color:#fff} }

.card-header {
  display:flex; align-items:center; justify-content:space-between;
  padding:12px 16px; background:var(--smoke);
}
.card-mesa { font-size:32px; letter-spacing:2px; color:var(--white); }
.card-meta { text-align:right; }
.card-time  { font-size:11px; color:var(--gray); letter-spacing:1px; }
.card-mesero { font-size:12px; color:var(--gold); letter-spacing:1px; margin-top:2px; }
.card-badge {
  display:inline-block; font-size:9px; letter-spacing:2px; padding:2px 8px;
  text-transform:uppercase; margin-top:4px;
}
.pendiente  .card-badge { background:var(--yellow); color:var(--black); }
.preparando .card-badge { background:var(--blue); color:var(--white); }

.card-items { padding:12px 16px; border-bottom:1px solid var(--ash); }
.card-item  { font-size:18px; color:var(--white); line-height:1.6; }
.card-item .qty { color:var(--red); font-size:20px; }
.card-nota  { font-size:12px; color:var(--gold); letter-spacing:1px; margin-top:8px; font-style:italic; }

.card-actions { display:flex; gap:0; }
.act-btn {
  flex:1; padding:14px; font-family:'BigNoodle',sans-serif; font-size:14px; letter-spacing:2px;
  border:none; cursor:pointer; transition:opacity .15s; text-transform:uppercase;
}
.act-btn:hover { opacity:.85; }
.btn-preparando { background:var(--blue);  color:var(--white); }
.btn-listo      { background:var(--green); color:var(--black); }
.btn-cancel     { background:var(--ash);   color:var(--gray); flex:none; padding:14px 16px; font-size:12px; }

/* ── EMPTY ── */
#empty-state {
  display:none; flex-direction:column; align-items:center; justify-content:center;
  min-height:50vh; gap:12px; color:var(--gray); text-align:center;
}
#empty-state .icon { font-size:64px; opacity:.4; }
#empty-state p { font-size:14px; letter-spacing:2px; text-transform:uppercase; }

/* ── LOADING ── */
#loading { position:fixed; inset:0; background:var(--black); display:flex; align-items:center; justify-content:center; z-index:500; }
#loading.hidden { display:none; }
.spinner { width:40px; height:40px; border:3px solid var(--ash); border-top-color:var(--red); border-radius:50%; animation:spin .7s linear infinite; }
@keyframes spin { to{transform:rotate(360deg)} }
</style>
</head>
<body>

<div id="loading"><div class="spinner"></div></div>

<!-- LOGIN -->
<div id="screen-login" style="display:none">
  <h2>PANTALLA DE COCINA</h2>
  <div id="pin-dots">
    <div class="pin-dot" id="dot-0"></div>
    <div class="pin-dot" id="dot-1"></div>
    <div class="pin-dot" id="dot-2"></div>
    <div class="pin-dot" id="dot-3"></div>
  </div>
  <div id="pin-error"></div>
  <div id="keypad">
    <div class="key" onclick="keyPress('1')">1</div>
    <div class="key" onclick="keyPress('2')">2</div>
    <div class="key" onclick="keyPress('3')">3</div>
    <div class="key" onclick="keyPress('4')">4</div>
    <div class="key" onclick="keyPress('5')">5</div>
    <div class="key" onclick="keyPress('6')">6</div>
    <div class="key" onclick="keyPress('7')">7</div>
    <div class="key" onclick="keyPress('8')">8</div>
    <div class="key" onclick="keyPress('9')">9</div>
    <div class="key del" onclick="keyDel()">⌫</div>
    <div class="key" onclick="keyPress('0')">0</div>
    <div class="key ok" onclick="keyOk()">✓</div>
  </div>
</div>

<!-- KITCHEN -->
<div id="topbar" style="display:none">
  <h1>🔥 COCINA</h1>
  <div class="right">
    <div id="status-dot"></div>
    <div id="status-txt">CONECTANDO...</div>
    <button id="btn-logout" onclick="logout()">SALIR</button>
  </div>
</div>

<div id="screen-kitchen">
  <div id="orders-header">
    <h2>PEDIDOS ACTIVOS</h2>
    <span id="order-count"></span>
  </div>
  <div id="orders-grid"></div>
  <div id="empty-state">
    <div class="icon">🍽️</div>
    <p>Sin pedidos pendientes</p>
  </div>
</div>

<script>
const CSRF_INIT = '<?= htmlspecialchars($csrf, ENT_QUOTES, "UTF-8") ?>';

let csrfToken = CSRF_INIT;
let pin = '';
let pollTimer = null;
let knownIds = new Set();
let audioCtx = null;

// ── Audio alert ──
function beep() {
  try {
    if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    [0, 0.25].forEach(delay => {
      const osc = audioCtx.createOscillator();
      const gain = audioCtx.createGain();
      osc.connect(gain); gain.connect(audioCtx.destination);
      osc.type = 'square';
      osc.frequency.setValueAtTime(880, audioCtx.currentTime + delay);
      gain.gain.setValueAtTime(0.25, audioCtx.currentTime + delay);
      gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + delay + 0.2);
      osc.start(audioCtx.currentTime + delay);
      osc.stop(audioCtx.currentTime + delay + 0.2);
    });
  } catch(e) {}
}

// ── API ──
async function api(action, method='GET', data=null) {
  const url = 'api.php?action=' + action;
  const opts = { method, credentials:'include', headers:{} };
  if (data) {
    opts.headers['Content-Type'] = 'application/json';
    opts.headers['X-CSRF-Token'] = csrfToken;
    opts.body = JSON.stringify(data);
  }
  const r = await fetch(url, opts);
  return r.json();
}

// ── Init ──
async function init() {
  const r = await api('cocina_check_auth');
  el('loading').classList.add('hidden');
  if (r.auth) { csrfToken = r.csrf_token; startKitchen(); }
  else { el('screen-login').style.display = 'flex'; }
}

// ── PIN ──
function keyPress(k) {
  if (pin.length >= 4) return;
  pin += k; updateDots();
  if (pin.length === 4) keyOk();
}
function keyDel() { pin = pin.slice(0,-1); updateDots(); el('pin-error').textContent=''; }
function updateDots() {
  for (let i=0;i<4;i++) el('dot-'+i).classList.toggle('filled', i < pin.length);
}
async function keyOk() {
  if (pin.length < 4) return;
  const r = await api('cocina_login','POST',{pin});
  if (r.success) {
    csrfToken = r.csrf_token; pin = ''; updateDots();
    el('screen-login').style.display = 'none';
    startKitchen();
  } else {
    el('pin-error').textContent = 'PIN INCORRECTO';
    pin = ''; updateDots();
  }
}

// ── Kitchen ──
function startKitchen() {
  el('topbar').style.display = 'flex';
  el('screen-kitchen').style.display = 'block';
  // Init audio context on first interaction
  document.addEventListener('click', () => {
    if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
  }, { once: true });
  fetchOrders();
  pollTimer = setInterval(fetchOrders, 3500);
}

async function fetchOrders() {
  try {
    const r = await api('get_pedidos_cocina');
    if (!r.success) { setStatus(false); return; }
    setStatus(true);
    renderOrders(r.pedidos || []);
  } catch(e) { setStatus(false); }
}

function setStatus(ok) {
  el('status-dot').className = ok ? 'ok' : '';
  el('status-txt').textContent = ok ? 'EN LÍNEA' : 'SIN CONEXIÓN';
}

function renderOrders(pedidos) {
  const grid = el('orders-grid');
  const newIds = new Set(pedidos.map(p=>p.id));

  // Detect new orders
  let hasNew = false;
  newIds.forEach(id => { if (!knownIds.has(id)) hasNew = true; });
  if (hasNew && knownIds.size > 0) beep();
  knownIds = newIds;

  el('order-count').textContent = pedidos.length
    ? pedidos.length + (pedidos.length===1?' PEDIDO':' PEDIDOS')
    : '';

  if (!pedidos.length) {
    grid.innerHTML = '';
    el('empty-state').style.display = 'flex';
    return;
  }
  el('empty-state').style.display = 'none';

  // Update or add cards
  const existingCards = new Map();
  grid.querySelectorAll('.order-card').forEach(c => existingCards.set(Number(c.dataset.id), c));

  // Remove cards no longer in list
  existingCards.forEach((card, id) => { if (!newIds.has(id)) card.remove(); });

  pedidos.forEach((p, idx) => {
    const existing = existingCards.get(p.id);
    const card = buildCard(p);
    if (existing) {
      // Only update if state changed
      if (existing.dataset.estado !== p.estado) {
        existing.replaceWith(card);
      }
    } else {
      // New card — insert in order
      const cards = grid.querySelectorAll('.order-card');
      if (cards[idx]) grid.insertBefore(card, cards[idx]);
      else grid.appendChild(card);
      if (knownIds.size > 1) card.classList.add('new-flash');
    }
  });
}

function buildCard(p) {
  const card = document.createElement('div');
  card.className = 'order-card ' + p.estado;
  card.dataset.id = p.id;
  card.dataset.estado = p.estado;

  const minAgo = Math.floor((Date.now() - new Date(p.fecha)) / 60000);
  const timeStr = minAgo < 1 ? 'Ahora mismo' : `hace ${minAgo} min`;
  const labels  = { pendiente:'NUEVO', preparando:'EN PREPARACIÓN' };

  const itemsHtml = (p.productos||[]).map(i =>
    `<div class="card-item"><span class="qty">${i.qty}×</span> ${i.nombre}</div>`
  ).join('');

  const notaHtml = p.nota ? `<div class="card-nota">📝 ${p.nota}</div>` : '';

  const isPending = p.estado === 'pendiente';

  card.innerHTML = `
    <div class="card-header">
      <div>
        <div class="card-mesa">MESA ${p.mesa_num || '?'}</div>
        <span class="card-badge">${labels[p.estado]||p.estado}</span>
      </div>
      <div class="card-meta">
        <div class="card-time">${timeStr}</div>
        <div class="card-mesero">${p.mesero||''}</div>
        <div class="card-time">#${p.id}</div>
      </div>
    </div>
    <div class="card-items">${itemsHtml}${notaHtml}</div>
    <div class="card-actions">
      ${isPending
        ? `<button class="act-btn btn-preparando" onclick="updateEstado(${p.id},'preparando')">▶ PREPARANDO</button>`
        : `<button class="act-btn btn-listo" onclick="updateEstado(${p.id},'listo')">✓ LISTO</button>`
      }
      <button class="act-btn btn-cancel" onclick="updateEstado(${p.id},'cancelado')" title="Cancelar">✕</button>
    </div>`;
  return card;
}

async function updateEstado(id, estado) {
  if (estado === 'cancelado' && !confirm('¿Cancelar este pedido?')) return;
  const r = await api('update_estado_cocina','POST',{id, estado, _csrf:csrfToken});
  if (r.success) fetchOrders();
  else alert('Error: ' + (r.error||'No se pudo actualizar'));
}

async function logout() {
  clearInterval(pollTimer);
  await api('cocina_logout','POST',{_csrf:csrfToken});
  location.reload();
}

function el(id) { return document.getElementById(id); }
init();
</script>
</body>
</html>
