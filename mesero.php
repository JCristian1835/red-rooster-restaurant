<?php
/**
 * Red Rooster Restaurant — Módulo Mesero
 * App móvil para toma de pedidos en mesa.
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
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Mesero — Red Rooster</title>
<style>
@font-face { font-family:'BigNoodle'; src:url('assets/fonts/big_noodle_titling.ttf') format('truetype'); }
@font-face { font-family:'Heartbreaking'; src:url('assets/fonts/heartbreaking.otf') format('opentype'); }

*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
:root {
  --red:#CC1212; --red-dark:#8B0000; --red-bright:#FF2020;
  --black:#0D0D0D; --charcoal:#1A1A1A; --smoke:#252525; --ash:#3A3A3A;
  --gold:#E8A020; --gold-dark:#C8860A; --cream:#F5ECD7;
  --white:#FFFFFF; --gray:#888;
  --green:#2ECC71; --orange:#E67E22; --yellow:#F1C40F;
}
html,body { height:100%; overflow:hidden; background:var(--black); color:var(--white); font-family:'BigNoodle',sans-serif; }

/* ── TOPBAR ── */
#topbar {
  position:fixed; top:0; left:0; right:0; z-index:100;
  background:rgba(13,13,13,.97); border-bottom:2px solid var(--red);
  display:flex; align-items:center; justify-content:space-between;
  padding:10px 16px; height:56px;
}
#topbar .brand { font-family:'Heartbreaking',cursive; font-size:22px; color:var(--white); }
#topbar .mesa-label { font-size:13px; letter-spacing:2px; color:var(--gold); text-transform:uppercase; }
#btn-cart {
  position:relative; background:var(--red); border:none; color:var(--white);
  font-family:'BigNoodle',sans-serif; font-size:14px; letter-spacing:2px;
  padding:8px 14px; cursor:pointer; display:flex; align-items:center; gap:6px;
  transition:background .2s;
}
#btn-cart:hover { background:var(--red-dark); }
#cart-badge {
  background:var(--gold); color:var(--black); border-radius:50%;
  width:20px; height:20px; display:flex; align-items:center; justify-content:center;
  font-size:12px; font-weight:bold; min-width:20px;
}

/* ── SCREENS ── */
#app { position:fixed; top:56px; left:0; right:0; bottom:0; overflow-y:auto; }
.screen { display:none; min-height:100%; }
.screen.active { display:block; }

/* ── LOGIN ── */
#screen-login {
  display:flex; flex-direction:column; align-items:center; justify-content:center;
  min-height:100%; padding:40px 24px; gap:28px;
}
#screen-login .logo { font-family:'Heartbreaking',cursive; font-size:40px; color:var(--white); text-align:center; }
#screen-login .subtitle { letter-spacing:4px; font-size:14px; color:var(--gold); text-align:center; }
#pin-dots { display:flex; gap:16px; justify-content:center; }
.pin-dot { width:18px; height:18px; border-radius:50%; border:2px solid var(--gray); background:transparent; transition:all .15s; }
.pin-dot.filled { background:var(--red); border-color:var(--red); }
#pin-error { color:var(--red-bright); font-size:13px; letter-spacing:2px; min-height:20px; text-align:center; }
#keypad { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; width:280px; }
.key {
  background:var(--charcoal); border:1px solid var(--ash); color:var(--white);
  font-family:'BigNoodle',sans-serif; font-size:26px; padding:18px 0;
  cursor:pointer; transition:background .1s, transform .1s; user-select:none;
  text-align:center; border-radius:4px;
}
.key:active { background:var(--ash); transform:scale(.95); }
.key.del { font-size:18px; color:var(--gray); }
.key.ok  { background:var(--red); color:var(--white); }
.key.ok:active { background:var(--red-dark); }

/* ── MESAS ── */
#screen-mesas { padding:16px; }
#screen-mesas h2 { letter-spacing:3px; font-size:15px; color:var(--gold); margin-bottom:16px; text-transform:uppercase; }
#mesas-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; }
.mesa-card {
  background:var(--charcoal); border:2px solid var(--ash); border-radius:6px;
  padding:14px 10px; text-align:center; cursor:pointer; transition:all .15s; user-select:none;
}
.mesa-card:active { transform:scale(.96); }
.mesa-card.disponible { border-color:var(--green); }
.mesa-card.ocupada    { border-color:var(--orange); opacity:.7; }
.mesa-card.reservada  { border-color:var(--yellow); opacity:.85; }
.mesa-card.mantenimiento { border-color:var(--gray); opacity:.5; pointer-events:none; }
.mesa-num  { font-size:28px; color:var(--white); line-height:1; }
.mesa-cap  { font-size:11px; color:var(--gray); letter-spacing:1px; margin-top:4px; }
.mesa-est  { font-size:10px; letter-spacing:2px; margin-top:6px; text-transform:uppercase; }
.disponible .mesa-est { color:var(--green); }
.ocupada .mesa-est    { color:var(--orange); }
.reservada .mesa-est  { color:var(--yellow); }

/* ── MENU ── */
#screen-menu { padding-bottom:80px; }
#cats-bar {
  position:sticky; top:0; background:var(--charcoal); border-bottom:1px solid var(--ash);
  display:flex; overflow-x:auto; gap:0; scrollbar-width:none; z-index:10;
}
#cats-bar::-webkit-scrollbar { display:none; }
.cat-btn {
  flex:none; padding:12px 16px; font-family:'BigNoodle',sans-serif; font-size:13px;
  letter-spacing:2px; text-transform:uppercase; color:var(--gray); background:transparent;
  border:none; cursor:pointer; border-bottom:3px solid transparent; white-space:nowrap;
  transition:color .15s, border-color .15s;
}
.cat-btn.active { color:var(--white); border-bottom-color:var(--red); }
#productos-list { padding:12px; display:flex; flex-direction:column; gap:8px; }
.prod-row {
  background:var(--charcoal); border:1px solid var(--ash); border-radius:6px;
  display:flex; align-items:center; padding:12px; gap:12px; transition:border-color .15s;
}
.prod-row.in-cart { border-color:var(--red); }
.prod-emoji { font-size:28px; flex:none; }
.prod-info  { flex:1; min-width:0; }
.prod-nombre { font-size:16px; color:var(--white); line-height:1.2; }
.prod-desc   { font-size:11px; color:var(--gray); margin-top:2px; }
.prod-precio { font-size:13px; color:var(--gold); margin-top:4px; }
.prod-tag    { display:inline-block; background:var(--red); color:var(--white); font-size:9px; letter-spacing:1px; padding:1px 6px; margin-left:6px; }
.prod-ctrl   { display:flex; align-items:center; gap:8px; flex:none; }
.qty-btn {
  width:32px; height:32px; border-radius:50%; border:1px solid var(--ash); background:var(--smoke);
  color:var(--white); font-size:20px; cursor:pointer; display:flex; align-items:center; justify-content:center;
  line-height:1; transition:background .1s;
}
.qty-btn:active { background:var(--ash); }
.qty-btn.add { background:var(--red); border-color:var(--red); font-size:22px; }
.qty-val { font-size:18px; min-width:24px; text-align:center; color:var(--white); }

/* ── BOTTOM BAR ── */
#bottom-bar {
  position:fixed; bottom:0; left:0; right:0; background:var(--charcoal); border-top:2px solid var(--red);
  padding:10px 16px; display:flex; align-items:center; justify-content:space-between; z-index:50;
  display:none;
}
#bottom-total { font-size:17px; color:var(--gold); }
#btn-ver-carrito {
  background:var(--red); border:none; color:var(--white); font-family:'BigNoodle',sans-serif;
  font-size:14px; letter-spacing:2px; padding:10px 20px; cursor:pointer; transition:background .2s;
}
#btn-ver-carrito:hover { background:var(--red-dark); }

/* ── CART MODAL ── */
#cart-overlay {
  position:fixed; inset:0; background:rgba(0,0,0,.7); z-index:200; display:none; align-items:flex-end;
}
#cart-overlay.open { display:flex; }
#cart-panel {
  background:var(--charcoal); border-top:3px solid var(--red); width:100%;
  max-height:85vh; overflow-y:auto; padding:20px;
  border-radius:12px 12px 0 0; animation:slideUp .25s ease;
}
@keyframes slideUp { from{transform:translateY(100%)} to{transform:translateY(0)} }
#cart-panel h3 { letter-spacing:3px; color:var(--gold); font-size:16px; margin-bottom:16px; text-transform:uppercase; }
.cart-item { display:flex; align-items:center; gap:10px; padding:10px 0; border-bottom:1px solid var(--ash); }
.cart-item-name { flex:1; font-size:15px; }
.cart-item-price { font-size:13px; color:var(--gold); }
.cart-del { background:transparent; border:none; color:var(--gray); font-size:18px; cursor:pointer; padding:4px; }
.cart-del:hover { color:var(--red-bright); }
.cart-fields { margin-top:16px; display:flex; flex-direction:column; gap:10px; }
.cart-fields label { font-size:12px; letter-spacing:2px; color:var(--gray); text-transform:uppercase; }
.cart-fields input, .cart-fields textarea {
  width:100%; background:var(--smoke); border:1px solid var(--ash); color:var(--white);
  font-family:'BigNoodle',sans-serif; font-size:15px; padding:10px 12px; margin-top:4px;
  outline:none; border-radius:4px;
}
.cart-fields input:focus, .cart-fields textarea:focus { border-color:var(--red); }
.cart-fields textarea { resize:vertical; min-height:70px; }
#cart-total { font-size:20px; color:var(--gold); margin-top:16px; text-align:right; }
#btn-send {
  width:100%; margin-top:16px; background:var(--red); border:none; color:var(--white);
  font-family:'BigNoodle',sans-serif; font-size:18px; letter-spacing:3px; padding:16px;
  cursor:pointer; transition:background .2s; border-radius:4px;
}
#btn-send:hover { background:var(--red-dark); }
#btn-send:disabled { opacity:.5; cursor:not-allowed; }
#btn-cancel-cart {
  width:100%; margin-top:8px; background:transparent; border:1px solid var(--ash);
  color:var(--gray); font-family:'BigNoodle',sans-serif; font-size:14px; letter-spacing:2px;
  padding:12px; cursor:pointer; border-radius:4px;
}

/* ── SUCCESS SCREEN ── */
#screen-ok {
  display:flex; flex-direction:column; align-items:center; justify-content:center;
  min-height:100%; gap:20px; padding:40px 24px; text-align:center;
}
#screen-ok .ok-icon { font-size:64px; }
#screen-ok h2 { font-size:28px; letter-spacing:3px; color:var(--green); }
#screen-ok p  { font-size:14px; color:var(--gray); letter-spacing:1px; }
#btn-nuevo-pedido {
  background:var(--red); border:none; color:var(--white); font-family:'BigNoodle',sans-serif;
  font-size:16px; letter-spacing:3px; padding:14px 32px; cursor:pointer; margin-top:8px;
  transition:background .2s; border-radius:4px;
}
#btn-nuevo-pedido:hover { background:var(--red-dark); }

/* ── LOADING ── */
#loading {
  position:fixed; inset:0; background:var(--black); display:flex;
  align-items:center; justify-content:center; z-index:500; flex-direction:column; gap:16px;
}
#loading.hidden { display:none; }
.spinner { width:40px; height:40px; border:3px solid var(--ash); border-top-color:var(--red); border-radius:50%; animation:spin .7s linear infinite; }
@keyframes spin { to{transform:rotate(360deg)} }
</style>
</head>
<body>

<div id="loading">
  <div class="spinner"></div>
  <div style="letter-spacing:3px;font-size:13px;color:var(--gray)">CARGANDO...</div>
</div>

<!-- TOP BAR -->
<div id="topbar">
  <div class="brand">Red Rooster</div>
  <div id="mesa-label" class="mesa-label"></div>
  <button id="btn-cart" onclick="openCart()" style="display:none">
    🛒 <span id="cart-badge">0</span>
  </button>
</div>

<div id="app">

  <!-- LOGIN -->
  <div id="screen-login" class="screen active">
    <div class="logo">Red Rooster</div>
    <div class="subtitle">MÓDULO MESERO</div>
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

  <!-- MESAS -->
  <div id="screen-mesas" class="screen">
    <h2>Selecciona una mesa</h2>
    <div id="mesas-grid"></div>
  </div>

  <!-- MENU -->
  <div id="screen-menu" class="screen">
    <div id="cats-bar"></div>
    <div id="productos-list"></div>
  </div>

  <!-- SUCCESS -->
  <div id="screen-ok" class="screen">
    <div class="ok-icon">✅</div>
    <h2>¡PEDIDO ENVIADO!</h2>
    <p id="ok-msg">El pedido fue enviado a cocina.</p>
    <button id="btn-nuevo-pedido" onclick="goMesas()">NUEVA MESA</button>
  </div>

</div>

<!-- BOTTOM BAR -->
<div id="bottom-bar">
  <div id="bottom-total"></div>
  <button id="btn-ver-carrito" onclick="openCart()">VER CARRITO</button>
</div>

<!-- CART MODAL -->
<div id="cart-overlay" onclick="closeCartOutside(event)">
  <div id="cart-panel">
    <h3 id="cart-title">PEDIDO</h3>
    <div id="cart-items"></div>
    <div class="cart-fields">
      <div>
        <label>Tu nombre</label>
        <input id="mesero-nombre" type="text" placeholder="Ej: Ana" maxlength="50" autocomplete="off">
      </div>
      <div>
        <label>Nota para cocina (opcional)</label>
        <textarea id="nota-pedido" placeholder="Ej: Sin cebolla en el combo 2" maxlength="300"></textarea>
      </div>
    </div>
    <div id="cart-total"></div>
    <button id="btn-send" onclick="enviarPedido()">🔥 ENVIAR A COCINA</button>
    <button id="btn-cancel-cart" onclick="closeCart()">CANCELAR</button>
  </div>
</div>

<script>
const CSRF = '<?= htmlspecialchars($csrf, ENT_QUOTES, "UTF-8") ?>';

// ── State ──
let csrfToken = CSRF;
let state = { screen:'login', mesaActual:null, productos:[], cart:{}, meseroNombre:'' };

// ── API ──
async function api(action, method='GET', data=null) {
  try {
    const url = 'api.php?action=' + action;
    const opts = { method, credentials:'include', headers:{} };
    if (data) {
      opts.headers['Content-Type'] = 'application/json';
      opts.headers['X-CSRF-Token'] = csrfToken;
      opts.body = JSON.stringify(data);
    }
    const r = await fetch(url, opts);
    const text = await r.text();
    try { return JSON.parse(text); }
    catch(e) { return { error: 'Respuesta inválida: ' + text.substring(0,80) }; }
  } catch(e) {
    return { error: 'Sin conexión: ' + e.message };
  }
}

// ── Init ──
async function init() {
  try {
    const r = await api('mesero_check_auth');
    hide('loading');
    if (r.auth) { csrfToken = r.csrf_token; await goMesas(); }
    else showScreen('login');
  } catch(e) { hide('loading'); showScreen('login'); }
}

// ── PIN ──
let pin = '';
let pinBusy = false;
function keyPress(k) {
  if (pin.length >= 4 || pinBusy) return;
  pin += k;
  updateDots();
  if (pin.length === 4) keyOk();
}
function keyDel() { if (pinBusy) return; pin = pin.slice(0,-1); updateDots(); setErr(''); }
function updateDots() {
  for (let i=0;i<4;i++) el('dot-'+i).classList.toggle('filled', i < pin.length);
}
async function keyOk() {
  if (pin.length < 4 || pinBusy) return;
  pinBusy = true;
  const r = await api('mesero_login','POST',{pin});
  pinBusy = false;
  if (r.success) {
    csrfToken = r.csrf_token;
    pin = ''; updateDots();
    await goMesas();
  } else {
    setErr(r.error || 'PIN INCORRECTO');
    pin = ''; updateDots();
  }
}

function setErr(msg) { el('pin-error').textContent = msg; }

// ── Screens ──
function showScreen(name) {
  state.screen = name;
  document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
  el('screen-'+name).classList.add('active');
  const showCart = name === 'menu';
  el('btn-cart').style.display = showCart ? 'flex' : 'none';
  el('bottom-bar').style.display = showCart ? 'flex' : 'none';
  el('mesa-label').textContent = state.mesaActual ? 'MESA ' + state.mesaActual.numero : '';
  updateCartBadge();
}

// ── Mesas ──
async function goMesas() {
  state.mesaActual = null;
  state.cart = {};
  const r = await api('get_mesas');
  if (!r.success) { showScreen('login'); setErr('Error cargando mesas: ' + (r.error||'sin respuesta')); return; }
  const g = el('mesas-grid');
  g.innerHTML = '';
  (r.mesas || []).filter(m => m.activa).forEach(m => {
    const d = document.createElement('div');
    d.className = 'mesa-card ' + (m.estado || 'disponible');
    const labels = {disponible:'Libre', ocupada:'Ocupada', reservada:'Reservada', mantenimiento:'Mant.'};
    d.innerHTML = `<div class="mesa-num">${m.numero}</div>
      <div class="mesa-cap">👥 ${m.capacidad}</div>
      <div class="mesa-est">${labels[m.estado]||m.estado}</div>`;
    d.onclick = () => goMenu(m);
    g.appendChild(d);
  });
  showScreen('mesas');
}

// ── Menu ──
async function goMenu(mesa) {
  state.mesaActual = mesa;
  state.cart = {};
  const r = await api('menu');
  if (!r.success) return;
  state.productos = r.productos || [];

  const cats = ['todos', ...new Set(state.productos.map(p=>p.categoria))];
  const catsBar = el('cats-bar');
  catsBar.innerHTML = '';
  cats.forEach((c,i) => {
    const b = document.createElement('button');
    b.className = 'cat-btn' + (i===0?' active':'');
    b.textContent = c === 'todos' ? 'TODOS' : c.toUpperCase();
    b.dataset.cat = c;
    b.onclick = () => {
      catsBar.querySelectorAll('.cat-btn').forEach(x=>x.classList.remove('active'));
      b.classList.add('active');
      renderProductos(c);
    };
    catsBar.appendChild(b);
  });
  renderProductos('todos');
  showScreen('menu');
  updateBottomBar();
}

function renderProductos(cat) {
  const list = el('productos-list');
  list.innerHTML = '';
  const prods = cat === 'todos' ? state.productos : state.productos.filter(p=>p.categoria===cat);
  prods.forEach(p => {
    const qty = state.cart[p.id]?.qty || 0;
    const row = document.createElement('div');
    row.className = 'prod-row' + (qty > 0 ? ' in-cart' : '');
    row.id = 'prod-row-' + p.id;
    row.innerHTML = `
      <div class="prod-emoji">${p.emoji||'🍽️'}</div>
      <div class="prod-info">
        <div class="prod-nombre">${p.nombre}${p.tag?`<span class="prod-tag">${p.tag}</span>`:''}</div>
        ${p.descripcion?`<div class="prod-desc">${p.descripcion}</div>`:''}
        <div class="prod-precio">$${fmt(p.precio)}</div>
      </div>
      <div class="prod-ctrl">
        ${qty > 0
          ? `<button class="qty-btn" onclick="changeQty(${p.id},-1)">−</button>
             <span class="qty-val" id="qty-${p.id}">${qty}</span>
             <button class="qty-btn add" onclick="changeQty(${p.id},1)">+</button>`
          : `<button class="qty-btn add" onclick="changeQty(${p.id},1)" style="width:38px;height:38px">+</button>`
        }
      </div>`;
    list.appendChild(row);
  });
}

function changeQty(id, delta) {
  const p = state.productos.find(x=>x.id===id);
  if (!p) return;
  const cur = state.cart[id]?.qty || 0;
  const nxt = Math.max(0, cur + delta);
  if (nxt === 0) delete state.cart[id];
  else state.cart[id] = { id, nombre:p.nombre, precio:p.precio, qty:nxt };
  // re-render only this row
  const catBtn = el('cats-bar')?.querySelector('.cat-btn.active');
  const cat = catBtn?.dataset.cat || 'todos';
  renderProductos(cat);
  updateCartBadge();
  updateBottomBar();
}

function updateCartBadge() {
  const total = Object.values(state.cart).reduce((s,i)=>s+i.qty,0);
  el('cart-badge').textContent = total;
}

function updateBottomBar() {
  const total = cartTotal();
  if (total > 0) {
    el('bottom-total').textContent = 'Total: $' + fmt(total);
    el('bottom-bar').style.display = 'flex';
  } else {
    el('bottom-bar').style.display = 'none';
  }
}

// ── Cart ──
function openCart() {
  const items = Object.values(state.cart);
  if (!items.length) return;
  el('cart-title').textContent = 'PEDIDO MESA ' + (state.mesaActual?.numero || '');
  const c = el('cart-items');
  c.innerHTML = '';
  items.forEach(item => {
    const d = document.createElement('div');
    d.className = 'cart-item';
    d.innerHTML = `
      <div class="prod-ctrl">
        <button class="qty-btn" onclick="changeQty(${item.id},-1);renderCartItems()">−</button>
        <span class="qty-val">${item.qty}</span>
        <button class="qty-btn add" onclick="changeQty(${item.id},1);renderCartItems()">+</button>
      </div>
      <div class="cart-item-name">${item.nombre}</div>
      <div class="cart-item-price">$${fmt(item.precio * item.qty)}</div>
      <button class="cart-del" onclick="removeItem(${item.id})">✕</button>`;
    c.appendChild(d);
  });
  if (state.meseroNombre) el('mesero-nombre').value = state.meseroNombre;
  el('cart-total').textContent = 'TOTAL: $' + fmt(cartTotal());
  el('cart-overlay').classList.add('open');
}

function renderCartItems() {
  openCart();
  // keep overlay open
}

function removeItem(id) {
  delete state.cart[id];
  updateCartBadge();
  updateBottomBar();
  if (!Object.keys(state.cart).length) { closeCart(); return; }
  openCart();
}

function closeCart()           { el('cart-overlay').classList.remove('open'); }
function closeCartOutside(e)   { if (e.target === el('cart-overlay')) closeCart(); }
function cartTotal()           { return Object.values(state.cart).reduce((s,i)=>s+i.precio*i.qty,0); }

async function enviarPedido() {
  const meseroNombre = el('mesero-nombre').value.trim() || 'Mesero';
  state.meseroNombre = meseroNombre;
  const nota = el('nota-pedido').value.trim();
  const items = Object.values(state.cart);
  if (!items.length) return;

  el('btn-send').disabled = true;
  el('btn-send').textContent = 'ENVIANDO...';

  const r = await api('save_pedido_mesa','POST',{
    mesa_id: state.mesaActual.id,
    mesero:  meseroNombre,
    nota:    nota,
    productos: items.map(i=>({id:i.id, qty:i.qty})),
    _csrf:   csrfToken
  });

  el('btn-send').disabled = false;
  el('btn-send').textContent = '🔥 ENVIAR A COCINA';

  if (r.success) {
    closeCart();
    el('nota-pedido').value = '';
    state.cart = {};
    el('ok-msg').textContent = `Pedido #${r.id} enviado a cocina — Mesa ${state.mesaActual.numero}`;
    showScreen('ok');
  } else {
    alert('Error: ' + (r.error || 'No se pudo enviar'));
  }
}

// ── Utils ──
function el(id)    { return document.getElementById(id); }
function hide(id)  { el(id).classList.add('hidden'); }
function fmt(n)    { return Number(n).toLocaleString('es-CO'); }

// ── Boot ──
init();
</script>
</body>
</html>
