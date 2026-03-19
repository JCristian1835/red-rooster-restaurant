<?php
/**
 * Red Rooster Restaurant — Panel de administración
 *
 * Este archivo es PHP puro. La autenticación ocurre en el servidor:
 *   - Si no hay sesión válida → redirige al login (no muestra nada)
 *   - Si hay sesión → entrega el HTML del panel con el token CSRF embebido
 *
 * Esto elimina el bypass de login que existía cuando admin era un HTML puro.
 */

require_once __DIR__ . '/includes/security.php';

set_security_headers();
start_secure_session();

$logged_in   = is_auth();
$csrf_token  = $logged_in ? generate_csrf_token() : '';
$login_error = '';

// Procesar login si se envió el formulario
if (!$logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    check_rate_limit('login');

    $user = sanitize_string($_POST['usuario'] ?? '', 50);
    $pass = $_POST['password'] ?? '';

    if (hash_equals(ADMIN_USER, $user) && password_verify($pass, ADMIN_PASS_HASH)) {
        session_regenerate_id(true);
        $_SESSION['admin']        = true;
        $_SESSION['_last_active'] = time();
        header('Location: admin.php');
        exit;
    } else {
        $login_error = 'Usuario o contraseña incorrectos';
    }
}

// Procesar logout
if ($logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'logout') {
    $_SESSION = [];
    session_destroy();
    header('Location: admin.php');
    exit;
}

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — Red Rooster</title>
<!-- Robots: nunca indexar el panel admin -->
<meta name="robots" content="noindex, nofollow">
<style>
@font-face{font-family:'BigNoodle';src:url('assets/fonts/big_noodle_titling.ttf') format('truetype');}
@font-face{font-family:'Heartbreaking';src:url('assets/fonts/heartbreaking.otf') format('opentype');}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --red:#CC1212;--rd:#8B0000;--gold:#E8A020;
  --black:#0D0D0D;--smoke:#141414;--card:#1E1E1E;--ash:#2A2A2A;--border:#333;
  --gray:#777;--white:#FFF;--green:#22C55E;--orange:#F97316;--blue:#3B82F6;
}
body{background:var(--smoke);color:var(--white);font-family:'BigNoodle',sans-serif;min-height:100vh;}

/* LOGIN */
.login-wrap{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;}
.login-box{background:var(--card);border:1px solid var(--border);border-top:4px solid var(--red);padding:40px;width:100%;max-width:380px;}
.login-logo{text-align:center;margin-bottom:30px;}
.login-logo img{height:56px;filter:drop-shadow(0 0 10px rgba(204,18,18,.5));}
.login-logo h1{font-family:'Heartbreaking',cursive;font-size:34px;margin-top:8px;}
.login-logo p{font-size:11px;letter-spacing:3px;color:var(--gray);margin-top:4px;}
.field{margin-bottom:14px;}
.field label{font-size:11px;letter-spacing:2px;color:var(--gray);display:block;margin-bottom:5px;text-transform:uppercase;}
.field input{width:100%;background:var(--ash);border:1px solid var(--border);color:var(--white);padding:11px 13px;font-family:'BigNoodle',sans-serif;font-size:15px;letter-spacing:1px;outline:none;transition:border-color .2s;}
.field input:focus{border-color:var(--red);}
.login-btn{width:100%;background:var(--red);border:none;color:var(--white);font-family:'BigNoodle',sans-serif;font-size:16px;letter-spacing:3px;padding:13px;cursor:pointer;transition:background .2s;margin-top:6px;}
.login-btn:hover{background:var(--rd);}
.error{background:rgba(204,18,18,.1);border:1px solid rgba(204,18,18,.3);color:#ff6b6b;font-size:12px;letter-spacing:1px;padding:10px 14px;margin-top:12px;text-align:center;}

/* APP */
.sidebar{position:fixed;left:0;top:0;bottom:0;width:216px;background:var(--black);border-right:1px solid var(--border);display:flex;flex-direction:column;z-index:50;overflow-y:auto;}
.sidebar::-webkit-scrollbar{width:3px;}
.sidebar::-webkit-scrollbar-track{background:var(--black);}
.sidebar::-webkit-scrollbar-thumb{background:var(--ash);}
.sb-brand{padding:18px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;}
.sb-brand img{height:30px;}
.sb-brand span{font-family:'Heartbreaking',cursive;font-size:22px;}
.nav-item{display:flex;align-items:center;gap:11px;padding:10px 18px;color:var(--gray);cursor:pointer;transition:all .2s;font-size:13px;letter-spacing:2px;border-left:3px solid transparent;}
.nav-item:hover{color:var(--white);background:rgba(255,255,255,.03);}
.nav-item.active{color:var(--red);border-left-color:var(--red);background:rgba(204,18,18,.07);}
.sb-footer{margin-top:auto;padding:14px 16px;border-top:1px solid var(--border);}
.logout-form button{width:100%;background:none;border:1px solid var(--border);color:var(--gray);font-family:'BigNoodle',sans-serif;font-size:12px;letter-spacing:2px;padding:9px;cursor:pointer;transition:all .2s;}
.logout-form button:hover{border-color:var(--red);color:var(--red);}
.content{margin-left:216px;padding:26px;min-height:100vh;}
.page{display:none;}.page.active{display:block;}

/* STATS */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:26px;}
.stat-card{background:var(--card);border:1px solid var(--border);padding:18px;position:relative;}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--accent,var(--red));}
.stat-label{font-size:10px;letter-spacing:2px;color:var(--gray);text-transform:uppercase;}
.stat-value{font-family:'BigNoodle',sans-serif;font-size:40px;line-height:1;margin-top:4px;letter-spacing:2px;}
.stat-sub{font-size:11px;color:var(--gray);margin-top:3px;letter-spacing:1px;}

/* HEADERS */
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;}
.page-title{font-family:'Heartbreaking',cursive;font-size:38px;}
.page-sub{font-size:11px;letter-spacing:2px;color:var(--gray);margin-top:2px;}
.btn{font-family:'BigNoodle',sans-serif;font-size:12px;letter-spacing:2px;padding:9px 16px;cursor:pointer;border:none;transition:all .2s;}
.btn-red{background:var(--red);color:var(--white);}.btn-red:hover{background:var(--rd);}
.btn-ash{background:var(--ash);color:var(--white);border:1px solid var(--border);}.btn-ash:hover{background:var(--border);}
.btn-green{background:var(--green);color:var(--black);}
.btn-sm{padding:5px 11px;font-size:11px;}

/* TABLE */
.table-wrap{background:var(--card);border:1px solid var(--border);overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
th{font-size:10px;letter-spacing:2px;color:var(--gray);text-transform:uppercase;padding:11px 14px;text-align:left;border-bottom:1px solid var(--border);background:var(--black);}
td{padding:11px 14px;border-bottom:1px solid rgba(255,255,255,.05);font-size:13px;letter-spacing:1px;vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:rgba(255,255,255,.02);}

/* BADGES */
.badge{display:inline-block;font-size:9px;letter-spacing:1px;padding:2px 8px;text-transform:uppercase;}
.b-pendiente{background:rgba(249,115,22,.14);color:#F97316;border:1px solid rgba(249,115,22,.25);}
.b-preparando{background:rgba(59,130,246,.14);color:#60A5FA;border:1px solid rgba(59,130,246,.25);}
.b-entregado{background:rgba(34,197,94,.14);color:#4ADE80;border:1px solid rgba(34,197,94,.25);}
.b-cancelado{background:rgba(255,255,255,.05);color:var(--gray);border:1px solid var(--border);}

/* TOGGLE */
.toggle{position:relative;display:inline-block;width:42px;height:22px;}
.toggle input{opacity:0;width:0;height:0;}
.slider{position:absolute;cursor:pointer;inset:0;background:var(--border);transition:.3s;}
.slider:before{content:'';position:absolute;height:16px;width:16px;left:3px;bottom:3px;background:var(--gray);transition:.3s;}
.toggle input:checked + .slider{background:var(--green);}
.toggle input:checked + .slider:before{transform:translateX(20px);background:var(--white);}

/* MODAL */
.modal-bg{position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:200;display:none;align-items:center;justify-content:center;padding:20px;}
.modal-bg.open{display:flex;}
.modal{background:var(--card);border:1px solid var(--border);border-top:3px solid var(--red);padding:26px;width:100%;max-width:460px;max-height:88vh;overflow-y:auto;}
.modal h3{font-family:'Heartbreaking',cursive;font-size:28px;margin-bottom:18px;}
.mfield{margin-bottom:12px;}
.mfield label{font-size:10px;letter-spacing:2px;color:var(--gray);display:block;margin-bottom:4px;text-transform:uppercase;}
.mfield input,.mfield select,.mfield textarea{width:100%;background:var(--ash);border:1px solid var(--border);color:var(--white);padding:9px 11px;font-family:'BigNoodle',sans-serif;font-size:14px;letter-spacing:1px;outline:none;}
.mfield input:focus,.mfield select:focus{border-color:var(--red);}
.mfield select option{background:var(--ash);}
.modal-actions{display:flex;gap:10px;margin-top:18px;justify-content:flex-end;}

/* FILTERS */
.filters{display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap;align-items:center;}
.filters input[type=date],.filters select{background:var(--ash);border:1px solid var(--border);color:var(--white);padding:7px 11px;font-family:'BigNoodle',sans-serif;font-size:12px;letter-spacing:1px;outline:none;}
.filters input[type=date]:focus,.filters select:focus{border-color:var(--red);}

/* TOAST */
.toast{position:fixed;bottom:22px;right:22px;font-family:'BigNoodle',sans-serif;font-size:12px;letter-spacing:2px;padding:11px 18px;z-index:999;transform:translateY(70px);opacity:0;transition:all .3s;pointer-events:none;}
.toast.show{transform:translateY(0);opacity:1;}
.toast-ok{background:var(--green);color:var(--black);}
.toast-err{background:var(--red);color:var(--white);}

/* Session banner */
.session-bar{background:rgba(232,160,32,.1);border-bottom:1px solid rgba(232,160,32,.2);padding:6px 26px;font-size:11px;letter-spacing:1px;color:var(--gold);display:flex;align-items:center;justify-content:space-between;}

/* ── MÓDULO 08 — MESAS ──────────────────────────────────── */
.mapa-zona{margin-bottom:24px;}
.mapa-zona-title{font-family:'BigNoodle',sans-serif;font-size:13px;letter-spacing:3px;color:var(--gray);text-transform:uppercase;margin-bottom:10px;display:flex;align-items:center;gap:10px;}
.mapa-zona-title::after{content:'';flex:1;height:1px;background:var(--ash);}
.mapa-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;}
.mesa-card{background:var(--card);border:2px solid var(--border);padding:16px;text-align:center;cursor:pointer;transition:all .2s;position:relative;}
.mesa-card:hover{transform:translateY(-2px);}
.mesa-card.disponible{border-color:var(--green);}
.mesa-card.ocupada{border-color:var(--red);background:rgba(204,18,18,.08);}
.mesa-card.reservada{border-color:var(--gold);background:rgba(232,160,32,.08);}
.mesa-card.mantenimiento{border-color:var(--gray);opacity:.6;}
.mesa-num{font-family:'Heartbreaking',cursive;font-size:42px;color:var(--white);line-height:1;}
.mesa-cap{font-size:11px;color:var(--gray);letter-spacing:1px;margin-top:2px;}
.mesa-estado-badge{font-size:9px;letter-spacing:1px;padding:2px 8px;text-transform:uppercase;margin-top:6px;display:inline-block;}
.mesa-estado-badge.disponible{background:rgba(34,197,94,.12);color:#4ADE80;border:1px solid rgba(34,197,94,.25);}
.mesa-estado-badge.ocupada{background:rgba(204,18,18,.12);color:var(--red);border:1px solid rgba(204,18,18,.25);}
.mesa-estado-badge.reservada{background:rgba(232,160,32,.12);color:var(--gold);border:1px solid rgba(232,160,32,.25);}
.mesa-estado-badge.mantenimiento{background:rgba(255,255,255,.05);color:var(--gray);border:1px solid var(--border);}
.mesa-reserva-info{font-size:10px;color:var(--gold);margin-top:4px;letter-spacing:1px;}
.reserva-row{display:flex;align-items:center;gap:12px;padding:12px 14px;background:var(--card);border:1px solid var(--border);border-left:4px solid var(--gold);margin-bottom:6px;}
.reserva-hora{font-family:'BigNoodle',sans-serif;font-size:22px;color:var(--gold);letter-spacing:2px;min-width:55px;}
.reserva-info{flex:1;}
.reserva-nombre{font-family:'BigNoodle',sans-serif;font-size:15px;letter-spacing:1px;color:var(--white);text-transform:uppercase;}
.reserva-meta{font-size:11px;color:var(--gray);letter-spacing:1px;margin-top:2px;}
.res-estado-badge{font-size:9px;letter-spacing:1px;padding:2px 8px;text-transform:uppercase;}
.res-confirmada{background:rgba(59,130,246,.12);color:#60A5FA;border:1px solid rgba(59,130,246,.25);}
.res-sentada{background:rgba(34,197,94,.12);color:#4ADE80;border:1px solid rgba(34,197,94,.25);}
.res-completada{background:rgba(255,255,255,.05);color:var(--gray);border:1px solid var(--border);}
.res-cancelada{background:rgba(255,255,255,.05);color:var(--gray);border:1px solid var(--border);text-decoration:line-through;}

/* ── MÓDULO 07 — EMPLEADOS ──────────────────────────────── */
.turno-hoy-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;margin-bottom:24px;}
.turno-card{background:var(--card);border:1px solid var(--border);padding:16px;position:relative;}
.turno-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--accent,var(--gray));}
.turno-nombre{font-family:'BigNoodle',sans-serif;font-size:16px;letter-spacing:1px;color:var(--white);text-transform:uppercase;}
.turno-cargo{font-size:11px;color:var(--gray);letter-spacing:1px;margin-top:2px;}
.turno-horas{display:flex;gap:8px;margin-top:10px;}
.turno-hora{flex:1;text-align:center;background:var(--ash);padding:8px 4px;}
.turno-hora-label{font-size:9px;letter-spacing:1px;color:var(--gray);text-transform:uppercase;}
.turno-hora-val{font-family:'BigNoodle',sans-serif;font-size:18px;letter-spacing:1px;margin-top:2px;}
.emp-card{background:var(--card);border:1px solid var(--border);border-left:4px solid var(--red);padding:16px;margin-bottom:8px;display:flex;align-items:center;gap:14px;}
.emp-avatar{width:44px;height:44px;background:var(--red);border-radius:4px;display:flex;align-items:center;justify-content:center;font-family:'BigNoodle',sans-serif;font-size:18px;color:var(--white);flex-shrink:0;}
.emp-nombre{font-family:'BigNoodle',sans-serif;font-size:16px;letter-spacing:1px;color:var(--white);text-transform:uppercase;}
.emp-cargo{font-size:11px;color:var(--gray);letter-spacing:1px;margin-top:2px;}
.emp-meta{display:flex;gap:10px;margin-top:5px;flex-wrap:wrap;}
.emp-meta-item{font-size:11px;color:var(--gray);letter-spacing:1px;}
.dia-check{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;font-family:'BigNoodle',sans-serif;font-size:11px;letter-spacing:1px;cursor:pointer;border:1px solid var(--border);color:var(--gray);transition:all .2s;user-select:none;}
.dia-check.activo{background:var(--red);border-color:var(--red);color:var(--white);}

/* ── MÓDULO 06 — CLIENTES ───────────────────────────────── */
.cli-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:22px;}
.cli-stat{background:var(--card);border:1px solid var(--border);padding:16px;position:relative;}
.cli-stat::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--accent,var(--red));}
.cli-stat-val{font-family:'BigNoodle',sans-serif;font-size:32px;letter-spacing:2px;line-height:1;}
.cli-stat-label{font-size:10px;letter-spacing:2px;color:var(--gray);margin-top:4px;text-transform:uppercase;}
.cli-search{display:flex;gap:8px;margin-bottom:18px;}
.cli-search input{flex:1;background:var(--ash);border:1px solid var(--border);color:var(--white);padding:10px 14px;font-family:'BigNoodle',sans-serif;font-size:14px;letter-spacing:1px;outline:none;}
.cli-search input:focus{border-color:var(--red);}
.etiqueta-badge{display:inline-block;font-size:9px;letter-spacing:1px;padding:2px 8px;text-transform:uppercase;border-radius:2px;}
.etq-vip{background:rgba(232,160,32,.15);color:var(--gold);border:1px solid rgba(232,160,32,.3);}
.etq-frecuente{background:rgba(59,130,246,.12);color:#60A5FA;border:1px solid rgba(59,130,246,.25);}
.etq-nuevo{background:rgba(34,197,94,.12);color:#4ADE80;border:1px solid rgba(34,197,94,.25);}
.etq-inactivo{background:rgba(255,255,255,.05);color:var(--gray);border:1px solid var(--border);}
.cli-card{background:var(--card);border:1px solid var(--border);border-left:4px solid var(--red);padding:16px;margin-bottom:8px;display:flex;align-items:center;gap:16px;}
.cli-avatar{width:44px;height:44px;background:var(--red);border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:'BigNoodle',sans-serif;font-size:20px;color:var(--white);flex-shrink:0;letter-spacing:1px;}
.cli-info{flex:1;}
.cli-nombre{font-family:'BigNoodle',sans-serif;font-size:17px;letter-spacing:1px;color:var(--white);text-transform:uppercase;}
.cli-tel{font-size:12px;color:var(--gray);letter-spacing:1px;margin-top:2px;}
.cli-meta{display:flex;gap:10px;margin-top:6px;flex-wrap:wrap;}
.cli-meta-item{font-size:11px;color:var(--gray);letter-spacing:1px;}
.cli-right{text-align:right;flex-shrink:0;}
.cli-total{font-family:'BigNoodle',sans-serif;font-size:22px;color:var(--gold);letter-spacing:2px;}
.cli-pedidos-count{font-size:11px;color:var(--gray);margin-top:2px;letter-spacing:1px;}

/* ── MÓDULO 05 — INVENTARIO ─────────────────────────────── */
.inv-resumen{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:22px;}
.inv-res-card{background:var(--card);border:1px solid var(--border);padding:16px;text-align:center;position:relative;}
.inv-res-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--accent,var(--gray));}
.inv-res-val{font-family:'BigNoodle',sans-serif;font-size:34px;letter-spacing:2px;line-height:1;}
.inv-res-label{font-size:10px;letter-spacing:2px;color:var(--gray);margin-top:4px;text-transform:uppercase;}
.stock-badge{display:inline-block;font-size:9px;letter-spacing:1px;padding:2px 8px;text-transform:uppercase;}
.stock-ok{background:rgba(34,197,94,.12);color:#4ADE80;border:1px solid rgba(34,197,94,.25);}
.stock-bajo{background:rgba(249,115,22,.12);color:#F97316;border:1px solid rgba(249,115,22,.25);}
.stock-critico{background:rgba(204,18,18,.12);color:var(--red);border:1px solid rgba(204,18,18,.25);}
.stock-agotado{background:rgba(255,255,255,.05);color:var(--gray);border:1px solid var(--border);}
.inv-item-row{display:flex;align-items:center;gap:14px;padding:14px;background:var(--card);border:1px solid var(--border);border-left:4px solid transparent;margin-bottom:6px;transition:border-color .2s;}
.inv-item-row.ok{border-left-color:var(--green);}
.inv-item-row.bajo{border-left-color:var(--orange);}
.inv-item-row.critico,.inv-item-row.agotado{border-left-color:var(--red);}
.inv-nombre{font-family:'BigNoodle',sans-serif;font-size:16px;letter-spacing:1px;color:var(--white);text-transform:uppercase;}
.inv-cat{font-size:11px;color:var(--gray);letter-spacing:1px;margin-top:2px;}
.inv-stock{font-family:'BigNoodle',sans-serif;font-size:22px;letter-spacing:2px;text-align:center;min-width:80px;}
.inv-stock.ok{color:var(--green);}
.inv-stock.bajo{color:var(--orange);}
.inv-stock.critico,.inv-stock.agotado{color:var(--red);}
.inv-minimos{font-size:10px;color:var(--gray);letter-spacing:1px;margin-top:2px;text-align:center;}
.mov-btns{display:flex;gap:6px;}
.mov-btn{font-family:'BigNoodle',sans-serif;font-size:10px;letter-spacing:1px;padding:5px 10px;cursor:pointer;border:none;text-transform:uppercase;transition:all .2s;}
.mov-entrada{background:rgba(34,197,94,.15);color:#4ADE80;border:1px solid rgba(34,197,94,.3);}
.mov-salida{background:rgba(204,18,18,.12);color:var(--red);border:1px solid rgba(204,18,18,.25);}
.mov-ajuste{background:var(--ash);color:var(--gray);border:1px solid var(--border);}

/* ── MÓDULO 04 — PROMOS ──────────────────────────────────── */
.promo-card{background:var(--card);border:1px solid var(--border);border-left:4px solid var(--gold);padding:18px;margin-bottom:10px;display:flex;align-items:flex-start;gap:16px;position:relative;}
.promo-card.inactiva{opacity:.5;border-left-color:var(--border);}
.promo-badge{position:absolute;top:14px;right:14px;font-size:9px;letter-spacing:2px;padding:3px 10px;text-transform:uppercase;}
.promo-badge.vigente{background:rgba(34,197,94,.15);color:#4ADE80;border:1px solid rgba(34,197,94,.3);}
.promo-badge.inactiva{background:rgba(255,255,255,.05);color:var(--gray);border:1px solid var(--border);}
.promo-badge.vencida{background:rgba(204,18,18,.12);color:var(--red);border:1px solid rgba(204,18,18,.25);}
.promo-icon{font-size:32px;flex-shrink:0;margin-top:2px;}
.promo-info{flex:1;}
.promo-name{font-family:'BigNoodle',sans-serif;font-size:18px;letter-spacing:2px;color:var(--white);text-transform:uppercase;}
.promo-desc{font-size:12px;color:var(--gray);margin-top:3px;letter-spacing:1px;}
.promo-meta{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;}
.promo-tag{font-size:10px;letter-spacing:1px;background:var(--ash);color:var(--gray);padding:2px 8px;}
.promo-descuento{font-family:'BigNoodle',sans-serif;font-size:28px;color:var(--gold);letter-spacing:2px;flex-shrink:0;text-align:right;}
.promo-usos{font-size:10px;color:var(--gray);margin-top:2px;letter-spacing:1px;}
.tipo-desc-btns{display:flex;gap:8px;margin-top:6px;}
.tipo-desc-btn{flex:1;background:var(--ash);border:1px solid var(--border);color:var(--gray);font-family:'BigNoodle',sans-serif;font-size:12px;letter-spacing:1px;padding:10px;cursor:pointer;transition:all .2s;text-transform:uppercase;text-align:center;}
.tipo-desc-btn.active{background:var(--gold);border-color:var(--gold);color:var(--black);}

/* ── MÓDULO 03 — GASTOS ──────────────────────────────────── */
.balance-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:24px;}
.balance-card{background:var(--card);border:1px solid var(--border);padding:20px;position:relative;}
.balance-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--accent,var(--red));}
.balance-label{font-size:10px;letter-spacing:2px;color:var(--gray);text-transform:uppercase;}
.balance-val{font-family:'BigNoodle',sans-serif;font-size:34px;letter-spacing:2px;margin-top:4px;line-height:1;}
.balance-sub{font-size:11px;color:var(--gray);margin-top:3px;}
.gasto-form{background:var(--card);border:1px solid var(--border);border-top:3px solid var(--red);padding:20px;margin-bottom:24px;}
.gasto-form-grid{display:grid;grid-template-columns:2fr 1fr 1fr;gap:10px;align-items:end;}
.cat-btns{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;}
.cat-btn-g{background:var(--ash);border:1px solid var(--border);color:var(--gray);font-family:'BigNoodle',sans-serif;font-size:10px;letter-spacing:1px;padding:6px 10px;cursor:pointer;transition:all .2s;text-transform:uppercase;}
.cat-btn-g.active{background:var(--red);border-color:var(--red);color:var(--white);}
.cat-bar-row{display:flex;align-items:center;gap:10px;margin-bottom:8px;}
.cat-bar-label{font-size:13px;color:var(--white);min-width:180px;letter-spacing:1px;}
.cat-bar-track{flex:1;background:var(--ash);height:8px;border-radius:1px;}
.cat-bar-fill{height:8px;border-radius:1px;background:var(--red);transition:width .5s ease;}
.cat-bar-val{font-size:12px;color:var(--gray);min-width:70px;text-align:right;}

/* ── MÓDULO 02 — ESTADÍSTICAS ────────────────────────────── */
.period-btns{display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap;}
.period-btn{background:var(--ash);border:1px solid var(--border);color:var(--gray);font-family:'BigNoodle',sans-serif;font-size:11px;letter-spacing:2px;padding:8px 16px;cursor:pointer;transition:all .2s;text-transform:uppercase;}
.period-btn.active{background:var(--red);border-color:var(--red);color:var(--white);}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:24px;}
.stat-kpi{background:var(--card);border:1px solid var(--border);padding:18px;position:relative;}
.stat-kpi::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--accent,var(--red));}
.stat-kpi-label{font-size:10px;letter-spacing:2px;color:var(--gray);text-transform:uppercase;}
.stat-kpi-val{font-family:'BigNoodle',sans-serif;font-size:34px;color:var(--white);line-height:1;margin-top:4px;letter-spacing:2px;}
.stat-kpi-sub{font-size:11px;color:var(--gray);margin-top:3px;}
.charts-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:24px;}
.chart-panel{background:var(--card);border:1px solid var(--border);padding:20px;}
.chart-panel-title{font-family:'BigNoodle',sans-serif;font-size:14px;letter-spacing:2px;color:var(--gray);text-transform:uppercase;margin-bottom:16px;}
.bar-item{display:flex;align-items:center;gap:8px;margin-bottom:8px;}
.bar-item-label{font-size:13px;color:var(--white);min-width:150px;letter-spacing:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.bar-track{flex:1;background:var(--ash);height:8px;border-radius:1px;}
.bar-fill-el{height:8px;border-radius:1px;transition:width .6s ease;}
.bar-item-val{font-size:12px;color:var(--gray);min-width:32px;text-align:right;}
.hora-bars{display:flex;align-items:flex-end;gap:3px;height:60px;margin-bottom:6px;}
.hora-bar{flex:1;border-radius:2px 2px 0 0;transition:height .4s ease;min-height:2px;}
.hora-labels{display:flex;gap:3px;font-size:9px;color:var(--gray);}
.hora-label-el{flex:1;text-align:center;}
.metodo-row{display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--ash);}
.metodo-row:last-child{border-bottom:none;}
.metodo-row-left{display:flex;align-items:center;gap:8px;font-size:14px;letter-spacing:1px;}
.metodo-row-right{font-size:13px;color:var(--gold);}
.descanso-badge{background:rgba(232,160,32,.15);border:1px solid rgba(232,160,32,.3);color:var(--gold);font-size:10px;letter-spacing:1px;padding:2px 8px;text-transform:uppercase;}
@media(max-width:900px){.charts-grid{grid-template-columns:1fr;}}

/* ── CUENTAS ABIERTAS ───────────────────────────────────── */
.cuentas-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;margin-bottom:24px;}
.cuenta-card{background:var(--card);border:2px solid var(--border);border-radius:4px;overflow:hidden;transition:border-color .2s;}
.cuenta-card.listo{border-color:#22c55e;}
.cuenta-card.entregado{border-color:#22c55e;}
.cuenta-card.preparando{border-color:#3b82f6;}
.cuenta-card.pendiente{border-color:var(--gold);}
.cuenta-header{background:var(--ash);padding:10px 14px;display:flex;justify-content:space-between;align-items:center;}
.cuenta-mesa{font-family:'Heartbreaking',cursive;font-size:28px;color:var(--white);line-height:1;}
.cuenta-meta{text-align:right;font-size:11px;color:var(--gray);letter-spacing:1px;line-height:1.6;}
.cuenta-items{padding:10px 14px;font-size:13px;color:var(--white);line-height:1.8;border-bottom:1px solid var(--border);}
.cuenta-footer{padding:10px 14px;display:flex;align-items:center;justify-content:space-between;gap:8px;}
.cuenta-total{font-family:'BigNoodle',sans-serif;font-size:22px;color:var(--gold);letter-spacing:1px;}
.cobrar-wrap{display:flex;gap:6px;align-items:center;}
.cobrar-metodo{background:var(--ash);border:1px solid var(--border);color:var(--white);font-family:'BigNoodle',sans-serif;font-size:12px;padding:6px 8px;cursor:pointer;letter-spacing:1px;}
.btn-cobrar{background:#22c55e;border:none;color:var(--black);font-family:'BigNoodle',sans-serif;font-size:13px;letter-spacing:2px;padding:8px 14px;cursor:pointer;transition:opacity .2s;white-space:nowrap;}
.btn-cobrar:hover{opacity:.85;}
.cuentas-empty{padding:20px;text-align:center;color:var(--gray);font-size:12px;letter-spacing:2px;border:1px dashed var(--border);margin-bottom:24px;}

/* ── MÓDULO 01 — CAJA ─────────────────────────────────── */
.caja-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:24px;}
.caja-card{background:var(--card);border:1px solid var(--border);padding:20px;position:relative;}
.caja-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--accent,var(--red));}
.caja-label{font-size:10px;letter-spacing:2px;color:var(--gray);text-transform:uppercase;}
.caja-value{font-family:'BigNoodle',sans-serif;font-size:36px;color:var(--white);margin-top:4px;line-height:1;letter-spacing:2px;}
.caja-sub{font-size:11px;color:var(--gray);margin-top:3px;}
.caja-value.green{color:var(--green);}
.caja-value.red{color:var(--red);}
.caja-value.gold{color:var(--gold);}

.metodos-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:24px;}
.metodo-card{background:var(--ash);border:1px solid var(--border);padding:14px;text-align:center;}
.metodo-icon{font-size:22px;margin-bottom:4px;}
.metodo-name{font-size:10px;letter-spacing:2px;color:var(--gray);text-transform:uppercase;}
.metodo-amount{font-family:'BigNoodle',sans-serif;font-size:20px;color:var(--gold);margin-top:4px;}

.tx-form{background:var(--card);border:1px solid var(--border);border-top:3px solid var(--gold);padding:20px;margin-bottom:24px;}
.tx-form-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:10px;align-items:end;}
.metodo-btns{display:flex;gap:6px;flex-wrap:wrap;}
.metodo-btn{background:var(--ash);border:1px solid var(--border);color:var(--gray);font-family:'BigNoodle',sans-serif;font-size:11px;letter-spacing:1px;padding:7px 12px;cursor:pointer;transition:all .2s;text-transform:uppercase;}
.metodo-btn.active{background:var(--gold);border-color:var(--gold);color:var(--black);}
.tipo-btns{display:flex;gap:6px;}
.tipo-btn{flex:1;background:var(--ash);border:1px solid var(--border);color:var(--gray);font-family:'BigNoodle',sans-serif;font-size:11px;letter-spacing:1px;padding:7px;cursor:pointer;transition:all .2s;text-transform:uppercase;}
.tipo-btn.ingreso.active{background:var(--green);border-color:var(--green);color:var(--black);}
.tipo-btn.egreso.active{background:var(--red);border-color:var(--red);color:var(--white);}

.turno-status{display:flex;align-items:center;gap:12px;padding:14px 18px;margin-bottom:20px;background:var(--ash);border:1px solid var(--border);}
.turno-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;}
.turno-dot.abierto{background:var(--green);box-shadow:0 0 6px var(--green);}
.turno-dot.cerrado{background:var(--gray);}
.turno-info{flex:1;font-size:13px;letter-spacing:1px;}
.turno-info strong{color:var(--white);}
.turno-info span{color:var(--gray);font-size:11px;margin-left:8px;}

@media(max-width:768px){
  .tx-form-grid{grid-template-columns:1fr;}
  .metodos-grid{grid-template-columns:repeat(2,1fr);}
}
</style>
</head>
<body>

<?php if (!$logged_in): ?>
<!-- ══ LOGIN (server-side rendered) ══════════════════════════════════════════ -->
<div class="login-wrap">
  <div class="login-box">
    <div class="login-logo">
      <img src="assets/images/3rgallo_transparent.png" alt="Red Rooster Logo">
      <h1>Red Rooster</h1>
      <p>PANEL DE ADMINISTRACIÓN</p>
    </div>
    <form method="POST" action="admin.php" autocomplete="off">
      <input type="hidden" name="action" value="login">
      <div class="field">
        <label for="u">Usuario</label>
        <input type="text" id="u" name="usuario" required autocomplete="username" placeholder="usuario">
      </div>
      <div class="field">
        <label for="p">Contraseña</label>
        <input type="password" id="p" name="password" required autocomplete="current-password" placeholder="••••••••">
      </div>
      <button type="submit" class="login-btn">ENTRAR AL PANEL</button>
      <?php if ($login_error): ?>
        <div class="error"><?= htmlspecialchars($login_error) ?></div>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ══ PANEL ADMIN (solo se renderiza con sesión PHP válida) ════════════════ -->

<!-- Session info bar -->
<div class="session-bar">
  <span>Sesión activa · Expira en <?= ceil((SESSION_LIFETIME - (time() - ($_SESSION['_last_active'] ?? time()))) / 60) ?> min</span>
  <a href="menu.html" target="_blank" style="color:var(--gold);text-decoration:none;">VER MENÚ ↗</a>
</div>

<nav class="sidebar" aria-label="Navegación admin">
  <div class="sb-brand">
    <img src="assets/images/3rgallo_transparent.png" alt="Logo">
    <span>3R Admin</span>
  </div>
  <div class="nav-item active" onclick="showPage('dashboard',this)" role="button">📊 Dashboard</div>
  <div class="nav-item" onclick="showPage('pedidos',this)" role="button">🍗 Pedidos</div>
  <div class="nav-item" onclick="showPage('productos',this)" role="button">🥩 Productos</div>
  <div class="nav-item" onclick="showPage('reportes',this)" role="button">📈 Reportes</div>
  <div class="nav-item" onclick="showPage('caja',this)" role="button">💰 Caja</div>
  <div class="nav-item" onclick="showPage('stats',this)" role="button">📊 Estadísticas</div>
  <div class="nav-item" onclick="showPage('gastos',this)" role="button">💸 Gastos</div>
  <div class="nav-item" onclick="showPage('promos',this)" role="button">🎯 Promos</div>
  <div class="nav-item" onclick="showPage('inventario',this)" role="button">📦 Inventario</div>
  <div class="nav-item" onclick="showPage('clientes',this)" role="button">👥 Clientes</div>
  <div class="nav-item" onclick="showPage('empleados',this)" role="button">👔 Empleados</div>
  <div class="nav-item" onclick="showPage('mesas',this)" role="button">🍽️ Mesas</div>
  <div class="sb-footer">
    <form class="logout-form" method="POST" action="admin.php">
      <input type="hidden" name="action" value="logout">
      <button type="submit">CERRAR SESIÓN</button>
    </form>
  </div>
</nav>

<div class="content">

  <!-- DASHBOARD -->
  <div class="page active" id="page-dashboard">
    <div class="page-header">
      <div><div class="page-title">Dashboard</div><div class="page-sub">Resumen del día</div></div>
    </div>
    <div class="stats-grid">
      <div class="stat-card" style="--accent:var(--red)"><div class="stat-label">Pedidos Hoy</div><div class="stat-value" id="s-hoy">—</div><div class="stat-sub">recibidos</div></div>
      <div class="stat-card" style="--accent:var(--gold)"><div class="stat-label">Ventas Hoy</div><div class="stat-value" id="s-ventas">—</div><div class="stat-sub">total</div></div>
      <div class="stat-card" style="--accent:var(--orange)"><div class="stat-label">Pendientes</div><div class="stat-value" id="s-pend">—</div><div class="stat-sub">por preparar</div></div>
      <div class="stat-card" style="--accent:var(--green)"><div class="stat-label">Entregados</div><div class="stat-value" id="s-entr">—</div><div class="stat-sub">completados</div></div>
    </div>
    <div class="page-header" style="margin-top:8px;">
      <div><div class="page-title" style="font-size:26px;">Últimos Pedidos</div></div>
      <button class="btn btn-red btn-sm" onclick="loadDashboard()">↺ ACTUALIZAR</button>
    </div>
    <div class="table-wrap" id="dash-table"></div>
  </div>

  <!-- PEDIDOS -->
  <div class="page" id="page-pedidos">
    <div class="page-header">
      <div><div class="page-title">Pedidos</div><div class="page-sub" id="ped-count">Cargando...</div></div>
      <button class="btn btn-red btn-sm" onclick="loadPedidos()">↺ ACTUALIZAR</button>
    </div>
    <div class="filters">
      <input type="date" id="f-desde" onchange="loadPedidos()">
      <input type="date" id="f-hasta" onchange="loadPedidos()">
      <select id="f-estado" onchange="loadPedidos()">
        <option value="">Todos los estados</option>
        <option value="pendiente">Pendiente</option>
        <option value="preparando">Preparando</option>
        <option value="entregado">Entregado</option>
        <option value="cancelado">Cancelado</option>
      </select>
    </div>
    <div class="table-wrap" id="ped-table"></div>
  </div>

  <!-- PRODUCTOS -->
  <div class="page" id="page-productos">
    <div class="page-header">
      <div><div class="page-title">Productos</div><div class="page-sub">Activa/desactiva en un clic</div></div>
      <button class="btn btn-red" onclick="openNewProd()">+ NUEVO</button>
    </div>
    <div class="table-wrap" id="prod-table"></div>
  </div>

  <!-- REPORTES -->
  <div class="page" id="page-reportes">
    <div class="page-header"><div><div class="page-title">Reportes</div><div class="page-sub">Exporta ventas a CSV</div></div></div>
    <div style="background:var(--card);border:1px solid var(--border);border-top:3px solid var(--gold);padding:26px;max-width:480px;">
      <div class="page-title" style="font-size:22px;margin-bottom:18px;">Exportar Pedidos</div>
      <div class="mfield"><label>Desde</label><input type="date" id="ex-desde" style="width:100%"></div>
      <div class="mfield" style="margin-top:10px;"><label>Hasta</label><input type="date" id="ex-hasta" style="width:100%"></div>
      <button class="btn btn-green" style="margin-top:18px;width:100%;padding:13px;" onclick="exportCSV()">↓ DESCARGAR CSV</button>
    </div>
  </div>

  <!-- ══ MÓDULO 01 — CAJA ══ -->
  <div class="page" id="page-caja">
    <div class="page-header">
      <div><div class="page-title">Cuentas Abiertas</div><div class="page-sub">Mesas con pedidos pendientes de cobro</div></div>
      <button class="btn btn-ash btn-sm" onclick="loadCuentas()">↺ Actualizar</button>
    </div>
    <div id="cuentas-container"><div class="cuentas-empty">CARGANDO...</div></div>

    <div class="page-header" style="margin-top:8px;">
      <div><div class="page-title">Control de Caja</div><div class="page-sub" id="caja-fecha"></div></div>
      <div style="display:flex;gap:8px;">
        <button class="btn btn-ash btn-sm" onclick="exportCajaCSV()">↓ CSV</button>
        <button class="btn btn-red" id="btnAbrirCerrar" onclick="toggleTurno()">ABRIR TURNO</button>
      </div>
    </div>
    <div class="turno-status" id="turnoStatus">
      <div class="turno-dot cerrado" id="turnoDot"></div>
      <div class="turno-info"><strong id="turnoLabel">Sin turno abierto</strong><span id="turnoHora"></span></div>
    </div>
    <div class="caja-grid">
      <div class="caja-card" style="--accent:var(--green)"><div class="caja-label">Total Ingresos</div><div class="caja-value green" id="totalIngresos">$0</div><div class="caja-sub">ventas del turno</div></div>
      <div class="caja-card" style="--accent:var(--red)"><div class="caja-label">Total Egresos</div><div class="caja-value red" id="totalEgresos">$0</div><div class="caja-sub">salidas de caja</div></div>
      <div class="caja-card" style="--accent:var(--gold)"><div class="caja-label">Neto del Turno</div><div class="caja-value gold" id="totalNeto">$0</div><div class="caja-sub">ingresos - egresos</div></div>
      <div class="caja-card" style="--accent:var(--blue)"><div class="caja-label">Transacciones</div><div class="caja-value" id="totalTx">0</div><div class="caja-sub">registros del turno</div></div>
    </div>
    <div class="metodos-grid">
      <div class="metodo-card"><div class="metodo-icon">💵</div><div class="metodo-name">Efectivo</div><div class="metodo-amount" id="m-efectivo">$0</div></div>
      <div class="metodo-card"><div class="metodo-icon">💳</div><div class="metodo-name">Tarjeta</div><div class="metodo-amount" id="m-tarjeta">$0</div></div>
      <div class="metodo-card"><div class="metodo-icon">📱</div><div class="metodo-name">Nequi</div><div class="metodo-amount" id="m-nequi">$0</div></div>
      <div class="metodo-card"><div class="metodo-icon">🔑</div><div class="metodo-name">BREB o Llave</div><div class="metodo-amount" id="m-breb">$0</div></div>
    </div>
    <div class="tx-form" id="txForm" style="display:none;">
      <div class="page-title" style="font-size:20px;margin-bottom:16px;">Registrar Transacción</div>
      <div class="tx-form-grid">
        <div class="mfield"><label>Concepto *</label><input type="text" id="txConcepto" placeholder="Ej: Venta 1 Pollo..." maxlength="200"></div>
        <div class="mfield"><label>Monto *</label><input type="number" id="txMonto" placeholder="0" min="1"></div>
        <div class="mfield"><label>Referencia</label><input type="text" id="txRef" placeholder="REF-001 (opcional)" maxlength="100"></div>
        <div class="mfield"><label>Pedido #</label><input type="number" id="txPedidoId" placeholder="opcional" min="1"></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:12px;">
        <div class="mfield"><label>Tipo</label>
          <div class="tipo-btns">
            <button class="tipo-btn ingreso active" id="tipoIngreso" onclick="setTipo('ingreso')">+ Ingreso</button>
            <button class="tipo-btn egreso" id="tipoEgreso" onclick="setTipo('egreso')">- Egreso</button>
          </div>
        </div>
        <div class="mfield"><label>Método de pago</label>
          <div class="metodo-btns">
            <button class="metodo-btn active" id="mb-efectivo" onclick="setMetodo('efectivo')">💵 Efectivo</button>
            <button class="metodo-btn" id="mb-tarjeta" onclick="setMetodo('tarjeta')">💳 Tarjeta</button>
            <button class="metodo-btn" id="mb-nequi" onclick="setMetodo('nequi')">📱 Nequi</button>
            <button class="metodo-btn" id="mb-breb" onclick="setMetodo('breb')">🔑 BREB o Llave</button>
          </div>
        </div>
      </div>
      <div style="margin-top:14px;">
        <button class="btn btn-red" style="width:100%;padding:14px;" onclick="registrarTx()">REGISTRAR TRANSACCIÓN</button>
      </div>
    </div>
    <div class="page-header" style="margin-top:4px;"><div><div class="page-title" style="font-size:22px;">Transacciones del Turno</div></div></div>
    <div class="table-wrap" id="txTable"></div>
  </div>

  <!-- ══ MÓDULO 02 — ESTADÍSTICAS ══ -->
  <div class="page" id="page-stats">
    <div class="page-header">
      <div><div class="page-title">Estadísticas</div><div class="page-sub" id="stats-rango">Cargando...</div></div>
      <div id="descanso-info"></div>
    </div>
    <div class="period-btns">
      <button class="period-btn active" onclick="loadStats('hoy',this)">Hoy</button>
      <button class="period-btn" onclick="loadStats('semana',this)">Esta semana</button>
      <button class="period-btn" onclick="loadStats('7d',this)">Últimos 7 días</button>
      <button class="period-btn" onclick="loadStats('30d',this)">Últimos 30 días</button>
    </div>
    <div class="stats-grid">
      <div class="stat-kpi" style="--accent:var(--green)"><div class="stat-kpi-label">Ventas totales</div><div class="stat-kpi-val" id="sk-ventas">—</div><div class="stat-kpi-sub">ingresos del período</div></div>
      <div class="stat-kpi" style="--accent:var(--red)"><div class="stat-kpi-label">Pedidos</div><div class="stat-kpi-val" id="sk-pedidos">—</div><div class="stat-kpi-sub">total del período</div></div>
      <div class="stat-kpi" style="--accent:var(--gold)"><div class="stat-kpi-label">Ticket promedio</div><div class="stat-kpi-val" id="sk-ticket">—</div><div class="stat-kpi-sub">por pedido</div></div>
      <div class="stat-kpi" style="--accent:var(--purple,#7f77dd)"><div class="stat-kpi-label">Plato estrella</div><div class="stat-kpi-val" id="sk-estrella" style="font-size:18px;padding-top:6px;">—</div><div class="stat-kpi-sub">más pedido</div></div>
    </div>
    <div class="charts-grid">
      <div class="chart-panel"><div class="chart-panel-title">Top platos más pedidos</div><div id="chart-platos"></div></div>
      <div class="chart-panel"><div class="chart-panel-title">Ventas por día</div><div id="chart-dias"></div></div>
      <div class="chart-panel"><div class="chart-panel-title">Horas pico</div><div class="hora-bars" id="chart-horas"></div><div class="hora-labels" id="chart-horas-labels"></div><div style="font-size:11px;color:var(--gray);margin-top:8px;" id="hora-pico-label"></div></div>
      <div class="chart-panel"><div class="chart-panel-title">Métodos de pago</div><div id="chart-metodos"></div></div>
    </div>
    <div class="chart-panel" style="margin-bottom:24px;background:var(--card);border:1px solid var(--border);padding:20px;">
      <div class="chart-panel-title">Estado de pedidos</div>
      <div id="chart-estados" style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;"></div>
    </div>
  </div>

  <!-- ══ MÓDULO 03 — GASTOS ══ -->
  <div class="page" id="page-gastos">
    <div class="page-header">
      <div><div class="page-title">Gastos y Costos</div><div class="page-sub">Control de egresos del negocio</div></div>
      <div style="display:flex;gap:8px;">
        <button class="btn btn-ash btn-sm" onclick="exportGastosCSV()">↓ CSV</button>
        <button class="btn btn-red" onclick="toggleGastoForm()">+ REGISTRAR GASTO</button>
      </div>
    </div>
    <div class="balance-grid">
      <div class="balance-card" style="--accent:var(--green)"><div class="balance-label">Ingresos del día</div><div class="balance-val" id="bal-ingresos" style="color:var(--green);">$0</div><div class="balance-sub">desde caja</div></div>
      <div class="balance-card" style="--accent:var(--red)"><div class="balance-label">Gastos del día</div><div class="balance-val" id="bal-gastos" style="color:var(--red);">$0</div><div class="balance-sub">registrados hoy</div></div>
      <div class="balance-card" style="--accent:var(--gold)"><div class="balance-label">Utilidad del día</div><div class="balance-val" id="bal-utilidad" style="color:var(--gold);">$0</div><div class="balance-sub">ingresos − gastos</div></div>
      <div class="balance-card" style="--accent:var(--blue)"><div class="balance-label">Margen</div><div class="balance-val" id="bal-margen" style="color:var(--white);">0%</div><div class="balance-sub">utilidad / ingresos</div></div>
    </div>
    <div class="gasto-form" id="gastoForm" style="display:none;">
      <div class="page-title" style="font-size:20px;margin-bottom:16px;">Nuevo Gasto</div>
      <div class="gasto-form-grid">
        <div class="mfield"><label>Concepto *</label><input type="text" id="gConcepto" placeholder="Ej: Compra de pollo..." maxlength="200"></div>
        <div class="mfield"><label>Monto *</label><input type="number" id="gMonto" placeholder="0" min="1"></div>
        <div class="mfield"><label>Nota (opcional)</label><input type="text" id="gNota" placeholder="Detalle adicional" maxlength="300"></div>
      </div>
      <div class="mfield" style="margin-top:12px;"><label>Categoría *</label><div class="cat-btns" id="catBtns"></div></div>
      <div style="display:flex;gap:10px;margin-top:14px;">
        <button class="btn btn-ash" onclick="toggleGastoForm()">CANCELAR</button>
        <button class="btn btn-red" style="flex:1;padding:12px;" onclick="registrarGasto()">GUARDAR GASTO</button>
      </div>
    </div>
    <div class="filters" style="margin-bottom:18px;">
      <input type="date" id="gDesde" onchange="loadGastos()">
      <input type="date" id="gHasta" onchange="loadGastos()">
      <select id="gCatFilter" onchange="loadGastos()" style="background:var(--ash);border:1px solid var(--border);color:var(--white);padding:7px 11px;font-family:'BigNoodle',sans-serif;font-size:12px;letter-spacing:1px;outline:none;"><option value="">Todas las categorías</option></select>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:24px;">
      <div style="background:var(--card);border:1px solid var(--border);padding:20px;">
        <div style="font-family:'BigNoodle',sans-serif;font-size:13px;letter-spacing:2px;color:var(--gray);text-transform:uppercase;margin-bottom:16px;">Gasto por categoría</div>
        <div id="catBarsChart"></div>
      </div>
      <div style="background:var(--card);border:1px solid var(--border);padding:20px;">
        <div style="font-family:'BigNoodle',sans-serif;font-size:13px;letter-spacing:2px;color:var(--gray);text-transform:uppercase;margin-bottom:12px;">Total del período</div>
        <div style="font-family:'BigNoodle',sans-serif;font-size:48px;color:var(--red);letter-spacing:2px;" id="gTotalPeriodo">$0</div>
        <div style="font-size:11px;color:var(--gray);margin-top:4px;" id="gCountPeriodo">0 gastos registrados</div>
      </div>
    </div>
    <div class="page-header" style="margin-top:4px;"><div><div class="page-title" style="font-size:22px;">Historial de Gastos</div></div></div>
    <div class="table-wrap" id="gastosTable"></div>
  </div>

  <!-- ══ MÓDULO 04 — PROMOS ══ -->
  <div class="page" id="page-promos">
    <div class="page-header">
      <div><div class="page-title">Promos y Descuentos</div><div class="page-sub">Las promos activas se muestran en el menú automáticamente</div></div>
      <button class="btn btn-red" onclick="togglePromoForm()">+ NUEVA PROMO</button>
    </div>
    <div class="gasto-form" id="promoForm" style="display:none;">
      <div class="page-title" style="font-size:20px;margin-bottom:16px;">Nueva Promo</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="mfield"><label>Nombre *</label><input type="text" id="pNombre" placeholder="Ej: Martes de Alitas 2x1" maxlength="100"></div>
        <div class="mfield"><label>Descripción</label><input type="text" id="pDesc" placeholder="Ej: Todos los martes" maxlength="200"></div>
        <div class="mfield"><label>Válida desde</label><input type="date" id="pDesde"></div>
        <div class="mfield"><label>Válida hasta</label><input type="date" id="pHasta"></div>
      </div>
      <div class="mfield" style="margin-top:12px;"><label>Tipo de descuento *</label>
        <div class="tipo-desc-btns">
          <div class="tipo-desc-btn active" id="tdPorcentaje" onclick="setTipoDesc('porcentaje')">% Porcentaje</div>
          <div class="tipo-desc-btn" id="tdValorFijo" onclick="setTipoDesc('valor_fijo')">$ Valor fijo</div>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px;">
        <div class="mfield"><label id="pValorLabel">Descuento (%) *</label><input type="number" id="pValor" placeholder="Ej: 20" min="1"></div>
        <div class="mfield"><label>Aplica a</label>
          <select id="pAplicaA" style="width:100%;background:var(--ash);border:1px solid var(--border);color:var(--white);padding:9px 11px;font-family:'BigNoodle',sans-serif;font-size:14px;outline:none;">
            <option value="todos">Todos los productos</option>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:10px;margin-top:16px;">
        <button class="btn btn-ash" onclick="togglePromoForm()">CANCELAR</button>
        <button class="btn btn-red" style="flex:1;padding:12px;" onclick="crearPromo()">CREAR PROMO</button>
      </div>
    </div>
    <div id="promosList"></div>
  </div>

  <!-- ══ MÓDULO 05 — INVENTARIO ══ -->
  <div class="page" id="page-inventario">
    <div class="page-header">
      <div><div class="page-title">Inventario</div><div class="page-sub">Stock de insumos con alertas automáticas</div></div>
      <div style="display:flex;gap:8px;">
        <button class="btn btn-ash btn-sm" onclick="filtrarAlertas()" id="btnAlertas">⚠ Solo alertas</button>
        <button class="btn btn-red" onclick="toggleInvForm()">+ AGREGAR INSUMO</button>
      </div>
    </div>
    <div class="inv-resumen">
      <div class="inv-res-card" style="--accent:var(--green)"><div class="inv-res-val" id="inv-ok" style="color:var(--green);">0</div><div class="inv-res-label">Stock OK</div></div>
      <div class="inv-res-card" style="--accent:var(--orange)"><div class="inv-res-val" id="inv-bajo" style="color:var(--orange);">0</div><div class="inv-res-label">Stock bajo</div></div>
      <div class="inv-res-card" style="--accent:var(--red)"><div class="inv-res-val" id="inv-critico" style="color:var(--red);">0</div><div class="inv-res-label">Crítico</div></div>
      <div class="inv-res-card" style="--accent:var(--gray)"><div class="inv-res-val" id="inv-agotado" style="color:var(--gray);">0</div><div class="inv-res-label">Agotado</div></div>
    </div>
    <div class="gasto-form" id="invForm" style="display:none;">
      <div class="page-title" style="font-size:20px;margin-bottom:16px;">Nuevo Insumo</div>
      <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:12px;">
        <div class="mfield"><label>Nombre *</label><input type="text" id="invNombre" placeholder="Ej: Pollo entero" maxlength="100"></div>
        <div class="mfield"><label>Categoría</label><input type="text" id="invCat" placeholder="Ej: Carnes..."></div>
        <div class="mfield"><label>Unidad *</label>
          <select id="invUnidad" style="width:100%;background:var(--ash);border:1px solid var(--border);color:var(--white);padding:9px 11px;font-family:'BigNoodle',sans-serif;font-size:14px;outline:none;">
            <option value="kg">Kilogramos (kg)</option><option value="g">Gramos (g)</option>
            <option value="l">Litros (l)</option><option value="ml">Mililitros (ml)</option>
            <option value="und" selected>Unidades (und)</option><option value="paq">Paquetes (paq)</option>
            <option value="caj">Cajas (caj)</option><option value="por">Porciones (por)</option>
          </select>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-top:12px;">
        <div class="mfield"><label>Stock actual</label><input type="number" id="invStock" placeholder="0" min="0" step="0.1"></div>
        <div class="mfield"><label>Mínimo (⚠)</label><input type="number" id="invMinimo" placeholder="0" min="0" step="0.1"></div>
        <div class="mfield"><label>Crítico (🔴)</label><input type="number" id="invCritico" placeholder="0" min="0" step="0.1"></div>
        <div class="mfield"><label>Costo x unidad</label><input type="number" id="invCosto" placeholder="0" min="0"></div>
      </div>
      <div class="mfield" style="margin-top:12px;"><label>Proveedor</label><input type="text" id="invProveedor" placeholder="Nombre del proveedor" maxlength="100"></div>
      <div style="display:flex;gap:10px;margin-top:14px;">
        <button class="btn btn-ash" onclick="toggleInvForm()">CANCELAR</button>
        <button class="btn btn-red" style="flex:1;padding:12px;" onclick="crearItemInv()">GUARDAR INSUMO</button>
      </div>
    </div>
    <div class="modal-bg" id="modalMov" role="dialog" aria-modal="true">
      <div class="modal">
        <h3 id="modalMovTitle">Registrar Movimiento</h3>
        <input type="hidden" id="movItemId">
        <div class="mfield"><label>Tipo de movimiento</label>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:6px;">
            <button class="mov-btn mov-entrada active" id="movTipoEntrada" onclick="setMovTipo('entrada')">↑ Entrada</button>
            <button class="mov-btn mov-salida" id="movTipoSalida" onclick="setMovTipo('salida')">↓ Salida</button>
            <button class="mov-btn mov-ajuste" id="movTipoAjuste" onclick="setMovTipo('ajuste')">⚖ Ajuste</button>
          </div>
        </div>
        <div class="mfield" style="margin-top:12px;"><label id="movCantLabel">Cantidad *</label><input type="number" id="movCantidad" placeholder="0" min="0.1" step="0.1"></div>
        <div class="mfield" style="margin-top:12px;"><label>Nota</label><input type="text" id="movNota" placeholder="Ej: Compra semanal..." maxlength="200"></div>
        <div class="modal-actions">
          <button class="btn btn-ash" onclick="closeModal()">CANCELAR</button>
          <button class="btn btn-red" onclick="registrarMovimiento()">REGISTRAR</button>
        </div>
      </div>
    </div>
    <div id="invList"></div>
  </div>

  <!-- ══ MÓDULO 06 — CLIENTES ══ -->
  <div class="page" id="page-clientes">
    <div class="page-header">
      <div><div class="page-title">Clientes</div><div class="page-sub">Se sincroniza automáticamente con los pedidos</div></div>
      <button class="btn btn-ash btn-sm" onclick="loadClientes()">↺ SINCRONIZAR</button>
    </div>
    <div class="cli-stats">
      <div class="cli-stat" style="--accent:var(--red)"><div class="cli-stat-val" id="cli-total">0</div><div class="cli-stat-label">Total clientes</div></div>
      <div class="cli-stat" style="--accent:var(--gold)"><div class="cli-stat-val" id="cli-gastado">$0</div><div class="cli-stat-label">Total gastado</div></div>
      <div class="cli-stat" style="--accent:var(--green)"><div class="cli-stat-val" id="cli-recurrentes">0</div><div class="cli-stat-label">Recurrentes</div></div>
      <div class="cli-stat" style="--accent:var(--blue)"><div class="cli-stat-val" id="cli-ticket">$0</div><div class="cli-stat-label">Ticket promedio</div></div>
    </div>
    <div class="cli-search">
      <input type="text" id="cliBuscar" placeholder="Buscar por nombre o teléfono..." oninput="buscarClientes()">
      <select id="cliEtiquetaFilter" onchange="loadClientes()" style="background:var(--ash);border:1px solid var(--border);color:var(--white);padding:8px 12px;font-family:'BigNoodle',sans-serif;font-size:12px;letter-spacing:1px;outline:none;">
        <option value="">Todas las etiquetas</option>
        <option value="vip">⭐ VIP</option><option value="frecuente">🔵 Frecuente</option>
        <option value="nuevo">🟢 Nuevo</option><option value="inactivo">⚪ Inactivo</option>
      </select>
    </div>
    <div id="cliList"></div>
    <div class="modal-bg" id="modalCliente" role="dialog" aria-modal="true">
      <div class="modal" style="max-width:560px;">
        <h3 id="modalCliNombre">Cliente</h3>
        <div id="modalCliInfo" style="margin-bottom:16px;"></div>
        <div class="mfield"><label>Etiqueta</label>
          <select id="modalCliEtiqueta" style="width:100%;background:var(--ash);border:1px solid var(--border);color:var(--white);padding:9px 11px;font-family:'BigNoodle',sans-serif;font-size:14px;outline:none;">
            <option value="">Sin etiqueta</option><option value="vip">⭐ VIP</option>
            <option value="frecuente">🔵 Frecuente</option><option value="nuevo">🟢 Nuevo</option>
            <option value="inactivo">⚪ Inactivo</option>
          </select>
        </div>
        <div class="mfield" style="margin-top:10px;"><label>Nota interna</label><input type="text" id="modalCliNota" placeholder="Ej: Alérgico al maní..." maxlength="300"></div>
        <input type="hidden" id="modalCliTel">
        <div style="margin-top:16px;">
          <div style="font-family:'BigNoodle',sans-serif;font-size:13px;letter-spacing:2px;color:var(--gray);text-transform:uppercase;margin-bottom:10px;">Últimos pedidos</div>
          <div id="modalCliPedidos"></div>
        </div>
        <div class="modal-actions">
          <button class="btn btn-ash" onclick="closeModal()">CERRAR</button>
          <button class="btn btn-red" onclick="guardarCliente()">GUARDAR</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ══ MÓDULO 07 — EMPLEADOS ══ -->
  <div class="page" id="page-empleados">
    <div class="page-header">
      <div><div class="page-title">Empleados</div><div class="page-sub">Turnos y asistencia del día</div></div>
      <div style="display:flex;gap:8px;">
        <button class="btn btn-ash btn-sm" onclick="loadTurnoHoy()">↺ ACTUALIZAR</button>
        <button class="btn btn-red" onclick="toggleEmpForm()">+ NUEVO EMPLEADO</button>
      </div>
    </div>
    <div style="font-family:'BigNoodle',sans-serif;font-size:13px;letter-spacing:2px;color:var(--gray);text-transform:uppercase;margin-bottom:12px;">TURNO DE HOY — <span id="empFechaHoy"></span></div>
    <div class="turno-hoy-grid" id="turnoHoyGrid"></div>
    <div class="gasto-form" id="empForm" style="display:none;">
      <div class="page-title" style="font-size:20px;margin-bottom:16px;">Nuevo Empleado</div>
      <div style="display:grid;grid-template-columns:2fr 1fr;gap:12px;">
        <div class="mfield"><label>Nombre completo *</label><input type="text" id="empNombre" placeholder="Nombre del empleado" maxlength="100"></div>
        <div class="mfield"><label>Cargo *</label>
          <select id="empCargo" style="width:100%;background:var(--ash);border:1px solid var(--border);color:var(--white);padding:9px 11px;font-family:'BigNoodle',sans-serif;font-size:14px;outline:none;">
            <option value="cajero">Cajero/a</option><option value="cocinero">Cocinero/a</option>
            <option value="mesero">Mesero/a</option><option value="domicilio">Domiciliario/a</option>
            <option value="limpieza">Limpieza</option><option value="admin">Administrador/a</option>
            <option value="otro">Otro</option>
          </select>
        </div>
        <div class="mfield"><label>Teléfono</label><input type="tel" id="empTel" placeholder="300 000 0000" maxlength="20"></div>
        <div class="mfield"><label>Cédula</label><input type="text" id="empCedula" placeholder="Número de cédula" maxlength="20"></div>
        <div class="mfield"><label>Salario mensual ($)</label><input type="number" id="empSalario" placeholder="0" min="0"></div>
        <div class="mfield"><label>Horario entrada — salida</label>
          <div style="display:flex;gap:8px;">
            <input type="time" id="empEntrada" value="08:00" style="flex:1;background:var(--ash);border:1px solid var(--border);color:var(--white);padding:9px 11px;font-family:'BigNoodle',sans-serif;font-size:14px;outline:none;">
            <input type="time" id="empSalida"  value="17:00" style="flex:1;background:var(--ash);border:1px solid var(--border);color:var(--white);padding:9px 11px;font-family:'BigNoodle',sans-serif;font-size:14px;outline:none;">
          </div>
        </div>
      </div>
      <div class="mfield" style="margin-top:12px;"><label>Días de trabajo</label>
        <div style="display:flex;gap:6px;margin-top:6px;flex-wrap:wrap;" id="diasCheck">
          <div class="dia-check activo" data-dia="lunes" onclick="toggleDia(this)">LUN</div>
          <div class="dia-check" data-dia="martes" onclick="toggleDia(this)">MAR</div>
          <div class="dia-check activo" data-dia="miercoles" onclick="toggleDia(this)">MIÉ</div>
          <div class="dia-check activo" data-dia="jueves" onclick="toggleDia(this)">JUE</div>
          <div class="dia-check activo" data-dia="viernes" onclick="toggleDia(this)">VIE</div>
          <div class="dia-check activo" data-dia="sabado" onclick="toggleDia(this)">SÁB</div>
          <div class="dia-check" data-dia="domingo" onclick="toggleDia(this)">DOM</div>
        </div>
        <div style="font-size:10px;color:var(--gold);margin-top:6px;letter-spacing:1px;">Los martes son día de descanso del restaurante</div>
      </div>
      <div style="display:flex;gap:10px;margin-top:14px;">
        <button class="btn btn-ash" onclick="toggleEmpForm()">CANCELAR</button>
        <button class="btn btn-red" style="flex:1;padding:12px;" onclick="crearEmpleado()">GUARDAR EMPLEADO</button>
      </div>
    </div>
    <div class="page-header" style="margin-top:8px;"><div><div class="page-title" style="font-size:22px;">Equipo</div></div></div>
    <div id="empList"></div>
    <div class="modal-bg" id="modalAsistencia" role="dialog" aria-modal="true">
      <div class="modal">
        <h3 id="modalAsisNombre">Registrar Asistencia</h3>
        <input type="hidden" id="modalAsisId">
        <div class="mfield"><label>Tipo</label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:6px;">
            <button class="tipo-btn ingreso active" id="asisTipoEntrada" onclick="setAsisTipo('entrada')" style="padding:12px;">↑ ENTRADA</button>
            <button class="tipo-btn egreso" id="asisTipoSalida" onclick="setAsisTipo('salida')" style="padding:12px;">↓ SALIDA</button>
          </div>
        </div>
        <div class="mfield" style="margin-top:12px;"><label>Nota</label><input type="text" id="modalAsisNota" placeholder="Ej: llegó tarde..." maxlength="200"></div>
        <div class="modal-actions">
          <button class="btn btn-ash" onclick="closeModal()">CANCELAR</button>
          <button class="btn btn-red" onclick="registrarAsistencia()">REGISTRAR</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ══ MÓDULO 08 — MESAS ══ -->
  <div class="page" id="page-mesas">
    <div class="page-header">
      <div><div class="page-title">Mesas y Reservas</div><div class="page-sub" id="mesasFecha"></div></div>
      <div style="display:flex;gap:8px;">
        <button class="btn btn-ash btn-sm" onclick="loadMesas()">↺ ACTUALIZAR</button>
        <button class="btn btn-ash btn-sm" onclick="toggleMesaForm()">+ MESA</button>
        <button class="btn btn-red" onclick="abrirReservaForm()">+ RESERVA</button>
      </div>
    </div>
    <div id="mapaMesas"></div>
    <div class="page-header" style="margin-top:8px;">
      <div><div class="page-title" style="font-size:22px;">Reservas de Hoy</div></div>
      <input type="date" id="reservaFiltroFecha" onchange="loadReservas()" style="background:var(--ash);border:1px solid var(--border);color:var(--white);padding:7px 11px;font-family:'BigNoodle',sans-serif;font-size:12px;letter-spacing:1px;outline:none;">
    </div>
    <div id="reservasList"></div>
    <div class="gasto-form" id="mesaForm" style="display:none;margin-top:20px;">
      <div class="page-title" style="font-size:20px;margin-bottom:16px;">Nueva Mesa</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="mfield"><label>Capacidad *</label><input type="number" id="mesaCap" value="4" min="1" max="50"></div>
        <div class="mfield"><label>Zona</label>
          <select id="mesaZona" style="width:100%;background:var(--ash);border:1px solid var(--border);color:var(--white);padding:9px 11px;font-family:'BigNoodle',sans-serif;font-size:14px;outline:none;">
            <option value="salon">Salón</option><option value="terraza">Terraza</option>
            <option value="privado">Privado</option><option value="barra">Barra</option>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:10px;margin-top:14px;">
        <button class="btn btn-ash" onclick="toggleMesaForm()">CANCELAR</button>
        <button class="btn btn-red" style="flex:1;padding:12px;" onclick="crearMesa()">CREAR MESA</button>
      </div>
    </div>
    <div class="modal-bg" id="modalMesa" role="dialog" aria-modal="true">
      <div class="modal">
        <h3 id="modalMesaTitle">Mesa</h3>
        <input type="hidden" id="modalMesaId">
        <div class="mfield"><label>Estado</label>
          <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin-top:6px;">
            <button class="btn btn-ash" style="color:var(--green);" onclick="cambiarEstadoMesa('disponible')">✓ DISPONIBLE</button>
            <button class="btn btn-ash" style="color:var(--red);" onclick="cambiarEstadoMesa('ocupada')">● OCUPADA</button>
            <button class="btn btn-ash" style="color:var(--gold);" onclick="cambiarEstadoMesa('reservada')">📅 RESERVADA</button>
            <button class="btn btn-ash" style="color:var(--gray);" onclick="cambiarEstadoMesa('mantenimiento')">🔧 MANTENIMIENTO</button>
          </div>
        </div>
        <div class="modal-actions"><button class="btn btn-ash" onclick="closeModal()">CERRAR</button></div>
      </div>
    </div>
    <div class="modal-bg" id="modalReserva" role="dialog" aria-modal="true">
      <div class="modal" style="max-width:520px;">
        <h3>Nueva Reserva</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <div class="mfield"><label>Nombre *</label><input type="text" id="rNombre" placeholder="Nombre del cliente" maxlength="100"></div>
          <div class="mfield"><label>Teléfono</label><input type="tel" id="rTel" placeholder="300 000 0000" maxlength="20"></div>
          <div class="mfield"><label>Fecha *</label><input type="date" id="rFecha"></div>
          <div class="mfield"><label>Hora *</label><input type="time" id="rHora" value="12:00"></div>
          <div class="mfield"><label>Personas *</label><input type="number" id="rPersonas" value="2" min="1" max="50"></div>
          <div class="mfield"><label>Mesa *</label>
            <select id="rMesa" style="width:100%;background:var(--ash);border:1px solid var(--border);color:var(--white);padding:9px 11px;font-family:'BigNoodle',sans-serif;font-size:14px;outline:none;"></select>
          </div>
        </div>
        <div class="mfield" style="margin-top:10px;"><label>Nota</label><input type="text" id="rNota" placeholder="Cumpleaños, menú especial..." maxlength="300"></div>
        <div class="modal-actions">
          <button class="btn btn-ash" onclick="closeModal()">CANCELAR</button>
          <button class="btn btn-red" onclick="crearReserva()">CREAR RESERVA</button>
        </div>
      </div>
    </div>
  </div>

</div><!-- /content -->

<!-- MODAL PRODUCTO -->
<div class="modal-bg" id="modal-prod" role="dialog" aria-modal="true" aria-labelledby="modal-title">
  <div class="modal">
    <h3 id="modal-title">Producto</h3>
    <input type="hidden" id="mp-id">
    <div class="mfield"><label>Nombre *</label><input type="text" id="mp-nombre" maxlength="80"></div>
    <div class="mfield"><label>Precio (COP) *</label><input type="number" id="mp-precio" min="100" max="9999900" step="100"></div>
    <div class="mfield"><label>Descripción</label><input type="text" id="mp-desc" maxlength="200"></div>
    <div class="mfield"><label>Categoría</label>
      <select id="mp-cat">
        <option value="pollo">Pollo</option>
        <option value="carta">Platos a la Carta</option>
        <option value="hamburguesas">Hamburguesas</option>
      </select>
    </div>
    <div class="mfield"><label>Emoji</label><input type="text" id="mp-emoji" maxlength="2" style="width:60px;text-align:center;font-size:22px;"></div>
    <div class="modal-actions">
      <button class="btn btn-ash" onclick="closeModal()">CANCELAR</button>
      <button class="btn btn-red" onclick="saveProd()">GUARDAR</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
// ── CSRF token embebido desde PHP (no viene del localStorage) ──────────────
const CSRF = <?= json_encode($csrf_token) ?>;

let pedidos = [], productos = [], editId = null;

// ── API helper (incluye CSRF en todas las mutaciones) ────────────────────────
async function api(action, body = null, method = null) {
  const isGet = body === null;
  const url   = 'api.php?action=' + action;
  const opts  = isGet
    ? { method: 'GET', credentials: 'same-origin' }
    : { method: method || 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
        body: JSON.stringify(body) };
  const res  = await fetch(url, opts);
  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Error ' + res.status);
  return data;
}

// ── Navegación ─────────────────────────────────────────────────────────────
function showPage(name, el) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(n => { n.classList.remove('active'); n.setAttribute('aria-selected','false'); });
  const page = document.getElementById('page-' + name);
  if (!page) { console.warn('Page not found: page-' + name); return; }
  page.classList.add('active');
  if (el) { el.classList.add('active'); }
  // Cargar datos del módulo correspondiente
  if (name === 'pedidos')    loadPedidos();
  if (name === 'productos')  loadProductos();
  if (name === 'caja')       loadCaja();
  if (name === 'stats')      loadStats(statsPeriodo);
  if (name === 'gastos')     { if (!categoriasGasto.length) initGastos().then(() => loadGastos()); else loadGastos(); }
  if (name === 'promos')     loadPromos();
  if (name === 'inventario') loadInventario();
  if (name === 'clientes')   loadClientes();
  if (name === 'empleados')  loadEmpleados();
  if (name === 'mesas')      loadMesas();
}

// ── Dashboard ──────────────────────────────────────────────────────────────
async function loadDashboard() {
  try {
    const d = await api('get_pedidos');
    pedidos = d.pedidos || [];
    const hoy = new Date().toISOString().slice(0,10);
    const hoyP = pedidos.filter(p => p.fecha.slice(0,10) === hoy);
    const ventas = hoyP.reduce((s,p) => s + p.total, 0);
    document.getElementById('s-hoy').textContent   = hoyP.length;
    document.getElementById('s-ventas').textContent = '$' + Math.round(ventas/1000) + 'K';
    document.getElementById('s-pend').textContent   = pedidos.filter(p => p.estado==='pendiente').length;
    document.getElementById('s-entr').textContent   = pedidos.filter(p => p.estado==='entregado').length;
    renderPedTable('dash-table', pedidos.slice(0,8));
  } catch(e) { toast(e.message, 'err'); }
}

// ── Pedidos ────────────────────────────────────────────────────────────────
async function loadPedidos() {
  try {
    const params = new URLSearchParams({ action: 'get_pedidos' });
    const desde  = document.getElementById('f-desde')?.value;
    const hasta  = document.getElementById('f-hasta')?.value;
    const estado = document.getElementById('f-estado')?.value;
    if (desde)  params.append('desde',  desde);
    if (hasta)  params.append('hasta',  hasta);
    if (estado) params.append('estado', estado);

    const res  = await fetch('api.php?' + params, { credentials: 'same-origin' });
    const data = await res.json();
    pedidos = data.pedidos || [];
    document.getElementById('ped-count').textContent = pedidos.length + ' pedidos';
    renderPedTable('ped-table', pedidos);
  } catch(e) { toast(e.message, 'err'); }
}

function renderPedTable(elId, data) {
  const el = document.getElementById(elId);
  if (!data.length) { el.innerHTML = '<div style="padding:36px;text-align:center;color:var(--gray);font-size:12px;letter-spacing:2px;">SIN PEDIDOS</div>'; return; }
  el.innerHTML = `<table><thead><tr><th>#</th><th>Cliente</th><th>Productos</th><th>Total</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
    ${data.map(p => `<tr>
      <td style="color:var(--gray)">#${p.id}</td>
      <td><div>${p.cliente}</div><div style="font-size:11px;color:var(--gray)">${p.telefono}</div>${p.direccion?`<div style="font-size:11px;color:var(--gray)">📍 ${p.direccion}</div>`:''}</td>
      <td style="font-size:12px;color:var(--gray)">${p.productos.map(i=>`${i.nombre} x${i.qty}`).join('<br>')}</td>
      <td style="color:var(--gold);font-size:17px;">$${p.total.toLocaleString('es-CO')}</td>
      <td><span class="badge b-${p.estado}">${p.estado.toUpperCase()}</span></td>
      <td><select onchange="updateEstado(${p.id},this.value)" style="background:var(--ash);border:1px solid var(--border);color:var(--white);padding:5px 7px;font-family:'BigNoodle',sans-serif;font-size:11px;letter-spacing:1px;outline:none;cursor:pointer;">
        ${['pendiente','preparando','entregado','cancelado'].map(s=>`<option value="${s}"${p.estado===s?' selected':''}>${s.charAt(0).toUpperCase()+s.slice(1)}</option>`).join('')}
      </select></td>
    </tr>`).join('')}
  </tbody></table>`;
}

async function updateEstado(id, estado) {
  try {
    await api('update_pedido', { action: 'update_pedido', id, estado });
    toast('Estado actualizado');
    loadDashboard();
  } catch(e) { toast(e.message, 'err'); }
}

// ── Productos ──────────────────────────────────────────────────────────────
async function loadProductos() {
  try {
    const d = await api('get_productos');
    productos = d.productos || [];
    const el = document.getElementById('prod-table');
    el.innerHTML = `<table><thead><tr><th>Producto</th><th>Precio</th><th>Categoría</th><th>Estado</th><th>Editar</th></tr></thead><tbody>
      ${productos.map(p => `<tr>
        <td><span style="font-size:18px;margin-right:7px;">${p.emoji||'🍗'}</span>${p.nombre}<div style="font-size:11px;color:var(--gray);margin-top:2px;">${p.descripcion}</div></td>
        <td style="color:var(--gold);font-size:17px;">$${p.precio.toLocaleString('es-CO')}</td>
        <td style="font-size:11px;letter-spacing:1px;color:var(--gray);text-transform:uppercase;">${p.categoria}</td>
        <td><label class="toggle"><input type="checkbox"${p.activo?' checked':''} onchange="toggleProd(${p.id},this.checked)"><span class="slider"></span></label>
          <span style="font-size:10px;letter-spacing:1px;margin-left:7px;color:${p.activo?'#4ADE80':'var(--gray)'};">${p.activo?'ACTIVO':'INACTIVO'}</span></td>
        <td><button class="btn btn-ash btn-sm" onclick="editProd(${p.id})">EDITAR</button></td>
      </tr>`).join('')}
    </tbody></table>`;
  } catch(e) { toast(e.message, 'err'); }
}

async function toggleProd(id, activo) {
  try {
    await api('update_producto', { action: 'update_producto', id, activo });
    toast(activo ? 'Activado' : 'Desactivado');
    loadProductos();
  } catch(e) { toast(e.message, 'err'); }
}

function editProd(id) {
  const p = productos.find(x => x.id === id);
  if (!p) return;
  editId = id;
  document.getElementById('modal-title').textContent = 'Editar Producto';
  document.getElementById('mp-id').value    = id;
  document.getElementById('mp-nombre').value = p.nombre;
  document.getElementById('mp-precio').value = p.precio;
  document.getElementById('mp-desc').value   = p.descripcion;
  document.getElementById('mp-cat').value    = p.categoria;
  document.getElementById('mp-emoji').value  = p.emoji || '🍗';
  document.getElementById('modal-prod').classList.add('open');
}

function openNewProd() {
  editId = null;
  document.getElementById('modal-title').textContent = 'Nuevo Producto';
  ['mp-id','mp-nombre','mp-precio','mp-desc'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('mp-cat').value   = 'pollo';
  document.getElementById('mp-emoji').value = '🍗';
  document.getElementById('modal-prod').classList.add('open');
}

function closeModal() { document.getElementById('modal-prod').classList.remove('open'); }

async function saveProd() {
  const nombre = document.getElementById('mp-nombre').value.trim();
  const precio = parseInt(document.getElementById('mp-precio').value);
  if (!nombre || !precio) return toast('Nombre y precio son obligatorios', 'err');
  const body = {
    action: editId ? 'update_producto' : 'create_producto',
    nombre, precio,
    descripcion: document.getElementById('mp-desc').value.trim(),
    categoria:   document.getElementById('mp-cat').value,
    emoji:       document.getElementById('mp-emoji').value.trim(),
    ...(editId ? { id: editId } : {}),
  };
  try {
    await api(body.action, body);
    toast(editId ? 'Producto actualizado' : 'Producto creado');
    closeModal();
    loadProductos();
  } catch(e) { toast(e.message, 'err'); }
}

// ── Export CSV ─────────────────────────────────────────────────────────────
function exportCSV() {
  const desde = document.getElementById('ex-desde').value;
  const hasta = document.getElementById('ex-hasta').value;
  let url = 'api.php?action=export_csv';
  if (desde) url += '&desde=' + encodeURIComponent(desde);
  if (hasta) url += '&hasta=' + encodeURIComponent(hasta);
  window.open(url);
}

// ── Toast ──────────────────────────────────────────────────────────────────
function toast(msg, type = 'ok') {
  const el = document.getElementById('toast');
  el.textContent = msg.toUpperCase();
  el.className = 'toast toast-' + type + ' show';
  setTimeout(() => el.classList.remove('show'), 2500);
}

// ── Init ───────────────────────────────────────────────────────────────────
const today = new Date().toISOString().slice(0,10);
const firstDay = new Date(new Date().setDate(1)).toISOString().slice(0,10);

// ══════════════════════════════════════════════════════════════
// MÓDULO 08 — MESAS Y RESERVAS JS
// ══════════════════════════════════════════════════════════════
let mesasData = [];

async function loadMesas() {
  try {
    const hoy = new Date().toLocaleDateString('es-CO',{weekday:'long',day:'numeric',month:'long'});
    document.getElementById('mesasFecha').textContent = hoy.charAt(0).toUpperCase()+hoy.slice(1);
    const d = await api('get_mesas');
    mesasData = d.mesas || [];
    renderMapa(mesasData, d.zonas || {});
    loadReservas();
  } catch(e) { toast(e.message,'err'); }
}

function renderMapa(mesas, zonas) {
  const zonaNames = {salon:'Salón',terraza:'Terraza',privado:'Privado',barra:'Barra'};
  const grupos = {};
  mesas.forEach(m => {
    const z = m.zona || 'salon';
    if (!grupos[z]) grupos[z] = [];
    if (m.activa) grupos[z].push(m);
  });

  document.getElementById('mapaMesas').innerHTML = Object.entries(grupos).map(([zona, ms]) => {
    const info = zonas[zona] || {};
    return `<div class="mapa-zona">
      <div class="mapa-zona-title">
        ${zonaNames[zona]||zona}
        <span style="font-size:11px;color:var(--green);">${info.disponibles||0} disponibles</span>
        <span style="font-size:11px;color:var(--red);">${info.ocupadas||0} ocupadas</span>
      </div>
      <div class="mapa-grid">
        ${ms.map(m => {
          const res = m.reserva_activa;
          return `<div class="mesa-card ${m.estado}" onclick="abrirModalMesa('${m.id}', ${m.numero})">
            <div class="mesa-num">${m.numero}</div>
            <div class="mesa-cap">${m.capacidad} personas</div>
            <div class="mesa-estado-badge ${m.estado}">${{disponible:'LIBRE',ocupada:'OCUPADA',reservada:'RESERVADA',mantenimiento:'MTTO'}[m.estado]||m.estado}</div>
            ${res ? `<div class="mesa-reserva-info">📅 ${res.hora} — ${res.nombre}</div>` : ''}
          </div>`;
        }).join('')}
      </div>
    </div>`;
  }).join('');
}

async function loadReservas() {
  const fecha = document.getElementById('reservaFiltroFecha')?.value || new Date().toISOString().slice(0,10);
  try {
    const d = await api('get_reservas&fecha=' + fecha);
    renderReservas(d.reservas || []);
  } catch(e) {}
}

function renderReservas(reservas) {
  const el = document.getElementById('reservasList');
  if (!reservas.length) {
    el.innerHTML = '<div style="padding:28px;text-align:center;color:var(--gray);font-size:12px;letter-spacing:2px;">SIN RESERVAS PARA ESTA FECHA</div>';
    return;
  }
  const esClass = {confirmada:'res-confirmada',sentada:'res-sentada',completada:'res-completada',cancelada:'res-cancelada'};
  const esLabel = {confirmada:'CONFIRMADA',sentada:'SENTADA',completada:'COMPLETADA',cancelada:'CANCELADA'};

  el.innerHTML = reservas.map(r => `
    <div class="reserva-row ${r.estado==='cancelada'?'opacity:.5':''}">
      <div class="reserva-hora">${r.hora}</div>
      <div class="reserva-info">
        <div class="reserva-nombre">${r.nombre}</div>
        <div class="reserva-meta">Mesa ${r.mesa_num} · ${r.personas} personas ${r.telefono?'· 📱 '+r.telefono:''} ${r.nota?'· 📝 '+r.nota:''}</div>
      </div>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;">
        <span class="res-estado-badge ${esClass[r.estado]||''}">${esLabel[r.estado]||r.estado}</span>
        <div style="display:flex;gap:4px;">
          ${r.estado==='confirmada' ? `<button class="btn btn-ash btn-sm" style="color:var(--green);" onclick="actualizarReserva('${r.id}','sentada')">SENTAR</button>` : ''}
          ${r.estado==='sentada'    ? `<button class="btn btn-ash btn-sm" onclick="actualizarReserva('${r.id}','completada')">COMPLETAR</button>` : ''}
          ${r.estado!=='cancelada' && r.estado!=='completada' ? `<button class="btn btn-ash btn-sm" style="color:var(--red);" onclick="actualizarReserva('${r.id}','cancelada')">✕</button>` : ''}
        </div>
      </div>
    </div>`).join('');
}

function abrirModalMesa(id, num) {
  document.getElementById('modalMesaId').value             = id;
  document.getElementById('modalMesaTitle').textContent    = 'MESA ' + num;
  document.getElementById('modalMesa').classList.add('open');
}

async function cambiarEstadoMesa(estado) {
  const id = document.getElementById('modalMesaId').value;
  try {
    await api('cambiar_estado_mesa',{action:'cambiar_estado_mesa',id,estado});
    toast('Mesa actualizada');
    closeModal();
    loadMesas();
  } catch(e) { toast(e.message,'err'); }
}

function toggleMesaForm() {
  const f = document.getElementById('mesaForm');
  f.style.display = f.style.display==='none'?'block':'none';
}

async function crearMesa() {
  const cap  = parseInt(document.getElementById('mesaCap').value)||2;
  const zona = document.getElementById('mesaZona').value;
  try {
    await api('crear_mesa',{action:'crear_mesa',capacidad:cap,zona});
    toast('Mesa creada');
    document.getElementById('mesaForm').style.display='none';
    loadMesas();
  } catch(e) { toast(e.message,'err'); }
}

async function abrirReservaForm() {
  // Cargar mesas disponibles en el select
  const sel = document.getElementById('rMesa');
  sel.innerHTML = mesasData.filter(m=>m.activa).map(m =>
    `<option value="${m.id}">Mesa ${m.numero} (${m.capacidad} personas) — ${m.zona}</option>`
  ).join('');
  document.getElementById('rFecha').value = new Date().toISOString().slice(0,10);
  document.getElementById('modalReserva').classList.add('open');
}

async function crearReserva() {
  const nombre   = document.getElementById('rNombre').value.trim();
  const tel      = document.getElementById('rTel').value.trim();
  const fecha    = document.getElementById('rFecha').value;
  const hora     = document.getElementById('rHora').value;
  const personas = parseInt(document.getElementById('rPersonas').value)||2;
  const mesa_id  = document.getElementById('rMesa').value;
  const nota     = document.getElementById('rNota').value.trim();

  if (!nombre) return toast('El nombre es obligatorio','err');
  if (!fecha || !hora) return toast('Fecha y hora son obligatorias','err');

  try {
    await api('crear_reserva',{action:'crear_reserva',mesa_id,fecha,hora,nombre,telefono:tel,personas,nota});
    toast('Reserva creada');
    closeModal();
    loadMesas();
  } catch(e) { toast(e.message,'err'); }
}

async function actualizarReserva(id, estado) {
  try {
    await api('actualizar_reserva',{action:'actualizar_reserva',id,estado});
    toast('Reserva actualizada');
    loadMesas();
  } catch(e) { toast(e.message,'err'); }
}

// ══════════════════════════════════════════════════════════════
// MÓDULO 07 — EMPLEADOS JS
// ══════════════════════════════════════════════════════════════
let asisTipoActual = 'entrada';

async function loadEmpleados() {
  await loadTurnoHoy();
  try {
    const d = await api('get_empleados');
    renderEmpleados(d.empleados || []);
  } catch(e) { toast(e.message,'err'); }
}

async function loadTurnoHoy() {
  try {
    const d = await api('get_turno_hoy');
    const dias = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    const fecha = new Date(d.fecha + 'T12:00:00');
    document.getElementById('empFechaHoy').textContent =
      dias[fecha.getDay()] + ' ' + d.fecha;
    renderTurnoHoy(d.empleados || []);
  } catch(e) {}
}

function renderTurnoHoy(emps) {
  const colores = {completo:'var(--green)',en_turno:'var(--gold)',ausente:'var(--gray)'};
  const labels  = {completo:'COMPLETO',en_turno:'EN TURNO',ausente:'AUSENTE'};
  document.getElementById('turnoHoyGrid').innerHTML = emps.map(e => `
    <div class="turno-card" style="--accent:${colores[e.estado]};">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;">
        <div>
          <div class="turno-nombre">${e.nombre}</div>
          <div class="turno-cargo">${e.cargo}</div>
        </div>
        <span style="font-size:9px;letter-spacing:1px;padding:2px 8px;background:${colores[e.estado]}22;color:${colores[e.estado]};border:1px solid ${colores[e.estado]}44;">
          ${labels[e.estado]}
        </span>
      </div>
      <div class="turno-horas">
        <div class="turno-hora">
          <div class="turno-hora-label">Entrada</div>
          <div class="turno-hora-val" style="color:${e.entrada?'var(--green)':'var(--gray)'};">${e.entrada||'—'}</div>
        </div>
        <div class="turno-hora">
          <div class="turno-hora-label">Salida</div>
          <div class="turno-hora-val" style="color:${e.salida?'var(--red)':'var(--gray)'};">${e.salida||'—'}</div>
        </div>
      </div>
      <div style="display:flex;gap:6px;margin-top:10px;">
        ${!e.entrada ? `<button class="btn btn-ash btn-sm" style="flex:1;color:var(--green);" onclick="abrirAsistencia('${e.id}','${e.nombre}','entrada')">↑ ENTRADA</button>` : ''}
        ${e.entrada && !e.salida ? `<button class="btn btn-ash btn-sm" style="flex:1;color:var(--red);" onclick="abrirAsistencia('${e.id}','${e.nombre}','salida')">↓ SALIDA</button>` : ''}
      </div>
    </div>`).join('') ||
    '<div style="color:var(--gray);font-size:12px;letter-spacing:2px;padding:20px;">SIN EMPLEADOS ACTIVOS</div>';
}

function renderEmpleados(emps) {
  const el = document.getElementById('empList');
  const cargos = {cajero:'Cajero/a',cocinero:'Cocinero/a',mesero:'Mesero/a',domicilio:'Domiciliario/a',limpieza:'Limpieza',admin:'Administrador/a',otro:'Otro'};
  const dias_labels = {lunes:'Lun',martes:'Mar',miercoles:'Mié',jueves:'Jue',viernes:'Vie',sabado:'Sáb',domingo:'Dom'};

  el.innerHTML = emps.map(e => {
    const initials = e.nombre.split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase();
    const dias = (e.horario_dias||[]).map(d => `<span style="font-size:10px;background:var(--ash);padding:1px 6px;letter-spacing:1px;">${dias_labels[d]||d}</span>`).join(' ');
    return `<div class="emp-card" style="opacity:${e.activo?1:.5};">
      <div class="emp-avatar">${initials}</div>
      <div style="flex:1;">
        <div class="emp-nombre">${e.nombre}</div>
        <div class="emp-cargo">${cargos[e.cargo]||e.cargo}</div>
        <div class="emp-meta">
          ${e.telefono ? `<span class="emp-meta-item">📱 ${e.telefono}</span>` : ''}
          ${e.cedula   ? `<span class="emp-meta-item">🪪 ${e.cedula}</span>`   : ''}
          <span class="emp-meta-item">${e.hora_entrada||'?'} — ${e.hora_salida||'?'}</span>
        </div>
        <div style="margin-top:6px;display:flex;gap:4px;flex-wrap:wrap;">${dias}</div>
      </div>
      <div style="display:flex;flex-direction:column;gap:6px;align-items:flex-end;">
        <button class="btn btn-ash btn-sm" onclick="toggleEmp('${e.id}')">${e.activo?'DESACTIVAR':'ACTIVAR'}</button>
      </div>
    </div>`;
  }).join('') ||
  '<div style="padding:32px;text-align:center;color:var(--gray);font-size:12px;letter-spacing:2px;">SIN EMPLEADOS — AGREGA EL PRIMERO ARRIBA</div>';
}

function toggleEmpForm() {
  const f = document.getElementById('empForm');
  f.style.display = f.style.display === 'none' ? 'block' : 'none';
}

function toggleDia(el) { el.classList.toggle('activo'); }

async function crearEmpleado() {
  const nombre   = document.getElementById('empNombre').value.trim();
  const cargo    = document.getElementById('empCargo').value;
  const tel      = document.getElementById('empTel').value.trim();
  const cedula   = document.getElementById('empCedula').value.trim();
  const salario  = parseInt(document.getElementById('empSalario').value)||0;
  const entrada  = document.getElementById('empEntrada').value;
  const salida   = document.getElementById('empSalida').value;
  const dias     = [...document.querySelectorAll('#diasCheck .dia-check.activo')].map(d=>d.dataset.dia);

  if (!nombre) return toast('El nombre es obligatorio','err');

  try {
    await api('crear_empleado',{
      action:'crear_empleado', nombre, cargo, telefono:tel,
      cedula, salario, hora_entrada:entrada, hora_salida:salida, horario:dias,
    });
    toast('Empleado creado');
    document.getElementById('empNombre').value  = '';
    document.getElementById('empTel').value     = '';
    document.getElementById('empCedula').value  = '';
    document.getElementById('empSalario').value = '';
    document.getElementById('empForm').style.display = 'none';
    loadEmpleados();
  } catch(e) { toast(e.message,'err'); }
}

async function toggleEmp(id) {
  try {
    await api('toggle_empleado',{action:'toggle_empleado',id});
    toast('Estado actualizado');
    loadEmpleados();
  } catch(e) { toast(e.message,'err'); }
}

function abrirAsistencia(id, nombre, tipo) {
  document.getElementById('modalAsisId').value        = id;
  document.getElementById('modalAsisNombre').textContent = nombre.toUpperCase();
  document.getElementById('modalAsisNota').value      = '';
  setAsisTipo(tipo);
  document.getElementById('modalAsistencia').classList.add('open');
}

function setAsisTipo(tipo) {
  asisTipoActual = tipo;
  document.getElementById('asisTipoEntrada').classList.toggle('active', tipo==='entrada');
  document.getElementById('asisTipoSalida').classList.toggle('active',  tipo==='salida');
}

async function registrarAsistencia() {
  const id   = document.getElementById('modalAsisId').value;
  const nota = document.getElementById('modalAsisNota').value.trim();
  try {
    await api('registrar_asistencia',{action:'registrar_asistencia',empleado_id:id,tipo:asisTipoActual,nota});
    toast(asisTipoActual === 'entrada' ? 'Entrada registrada' : 'Salida registrada');
    closeModal();
    loadTurnoHoy();
  } catch(e) { toast(e.message,'err'); }
}

// ══════════════════════════════════════════════════════════════
// MÓDULO 06 — CLIENTES JS
// ══════════════════════════════════════════════════════════════
let cliData = [];
let cliBuscarTimeout = null;

async function loadClientes() {
  try {
    const etiqueta = document.getElementById('cliEtiquetaFilter')?.value || '';
    let url = 'get_clientes';
    if (etiqueta) url += '&etiqueta=' + etiqueta;
    const d = await api(url);
    cliData = d.clientes || [];
    renderCliStats(d.stats || {});
    renderClientes(cliData);
  } catch(e) { toast(e.message, 'err'); }
}

function renderCliStats(s) {
  const fmt = v => '$' + Math.round(v/1000) + 'K';
  document.getElementById('cli-total').textContent       = s.total_clientes || 0;
  document.getElementById('cli-gastado').textContent     = fmt(s.total_gastado || 0);
  document.getElementById('cli-recurrentes').textContent = s.recurrentes || 0;
  document.getElementById('cli-ticket').textContent      = fmt(s.ticket_promedio || 0);
}

function renderClientes(clientes) {
  const el = document.getElementById('cliList');
  if (!clientes.length) {
    el.innerHTML = '<div style="padding:32px;text-align:center;color:var(--gray);font-size:12px;letter-spacing:2px;">SIN CLIENTES — SE LLENAN AUTOMÁTICAMENTE CON LOS PEDIDOS</div>';
    return;
  }
  const etqClass = {vip:'etq-vip',frecuente:'etq-frecuente',nuevo:'etq-nuevo',inactivo:'etq-inactivo'};
  const etqLabel = {vip:'⭐ VIP',frecuente:'🔵 Frecuente',nuevo:'🟢 Nuevo',inactivo:'⚪ Inactivo'};

  el.innerHTML = clientes.map(c => {
    const initials = (c.nombre || '?').split(' ').map(w => w[0]).join('').slice(0,2).toUpperCase();
    const etq = c.etiqueta ? `<span class="etiqueta-badge ${etqClass[c.etiqueta]||''}">${etqLabel[c.etiqueta]||c.etiqueta}</span>` : '';
    return `<div class="cli-card" onclick="abrirCliente('${c.telefono}')" style="cursor:pointer;">
      <div class="cli-avatar">${initials}</div>
      <div class="cli-info">
        <div class="cli-nombre">${c.nombre} ${etq}</div>
        <div class="cli-tel">📱 ${c.telefono} ${c.direccion ? '· 📍 ' + c.direccion : ''}</div>
        <div class="cli-meta">
          <span class="cli-meta-item">Primer pedido: ${c.primer_pedido || '—'}</span>
          <span class="cli-meta-item">Último: ${c.ultimo_pedido || '—'}</span>
          ${c.nota ? `<span class="cli-meta-item" style="color:var(--gold);">📝 ${c.nota}</span>` : ''}
        </div>
      </div>
      <div class="cli-right">
        <div class="cli-total">$${(c.total_gastado||0).toLocaleString('es-CO')}</div>
        <div class="cli-pedidos-count">${c.total_pedidos} pedido${c.total_pedidos!==1?'s':''}</div>
        <a href="https://wa.me/57${c.telefono.replace(/\D/g,'')}" target="_blank" rel="noopener noreferrer"
           onclick="event.stopPropagation()"
           style="display:inline-block;margin-top:6px;background:#25D366;color:white;font-family:'BigNoodle',sans-serif;font-size:10px;letter-spacing:1px;padding:4px 10px;text-decoration:none;">
          📱 WhatsApp
        </a>
      </div>
    </div>`;
  }).join('');
}

function buscarClientes() {
  clearTimeout(cliBuscarTimeout);
  cliBuscarTimeout = setTimeout(() => {
    const q = document.getElementById('cliBuscar').value.toLowerCase().trim();
    if (!q) { renderClientes(cliData); return; }
    const filtrados = cliData.filter(c =>
      c.nombre.toLowerCase().includes(q) || c.telefono.includes(q)
    );
    renderClientes(filtrados);
  }, 300);
}

async function abrirCliente(tel) {
  try {
    const d = await api('get_cliente_detalle&tel=' + encodeURIComponent(tel));
    const c = d.cliente;
    document.getElementById('modalCliNombre').textContent = c.nombre.toUpperCase();
    document.getElementById('modalCliTel').value          = c.telefono;
    document.getElementById('modalCliEtiqueta').value     = c.etiqueta || '';
    document.getElementById('modalCliNota').value         = c.nota     || '';
    document.getElementById('modalCliInfo').innerHTML = `
      <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:12px;color:var(--gray);letter-spacing:1px;">
        <span>📱 ${c.telefono}</span>
        <span>🛒 ${c.total_pedidos} pedidos</span>
        <span style="color:var(--gold);">💰 $${(c.total_gastado||0).toLocaleString('es-CO')}</span>
        ${c.direccion ? `<span>📍 ${c.direccion}</span>` : ''}
      </div>`;
    // Últimos 5 pedidos
    const pedidos = (d.pedidos || []).slice(0,5);
    document.getElementById('modalCliPedidos').innerHTML = pedidos.length
      ? pedidos.map(p => `
          <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--ash);font-size:13px;">
            <span style="color:var(--gray);">#${p.id} · ${p.fecha.slice(0,10)}</span>
            <span>${p.productos.map(i=>i.nombre+' x'+i.qty).join(', ')}</span>
            <span style="color:var(--gold);">$${p.total.toLocaleString('es-CO')}</span>
          </div>`).join('')
      : '<div style="color:var(--gray);font-size:12px;letter-spacing:1px;padding:8px 0;">Sin pedidos registrados</div>';
    document.getElementById('modalCliente').classList.add('open');
  } catch(e) { toast(e.message, 'err'); }
}

async function guardarCliente() {
  const tel      = document.getElementById('modalCliTel').value;
  const nota     = document.getElementById('modalCliNota').value.trim();
  const etiqueta = document.getElementById('modalCliEtiqueta').value;
  try {
    await api('actualizar_cliente', { action:'actualizar_cliente', telefono:tel, nota, etiqueta });
    toast('Cliente actualizado');
    closeModal();
    loadClientes();
  } catch(e) { toast(e.message,'err'); }
}

// ══════════════════════════════════════════════════════════════
// MÓDULO 05 — INVENTARIO JS
// ══════════════════════════════════════════════════════════════
let soloAlertasInv = false;
let movTipoActual  = 'entrada';

async function loadInventario() {
  try {
    let url = 'get_inventario';
    if (soloAlertasInv) url += '&alertas=1';
    const d = await api(url);
    renderInventario(d);
  } catch(e) { toast(e.message, 'err'); }
}

function renderInventario(d) {
  // Resumen
  const r = d.resumen || {};
  document.getElementById('inv-ok').textContent      = r.ok      || 0;
  document.getElementById('inv-bajo').textContent    = r.bajo    || 0;
  document.getElementById('inv-critico').textContent = r.critico || 0;
  document.getElementById('inv-agotado').textContent = r.agotado || 0;

  const el = document.getElementById('invList');
  if (!d.items.length) {
    el.innerHTML = '<div style="padding:32px;text-align:center;color:var(--gray);font-size:12px;letter-spacing:2px;">' +
      (soloAlertasInv ? 'SIN ALERTAS — TODO EN ORDEN ✅' : 'SIN INSUMOS — AGREGA EL PRIMERO ARRIBA') + '</div>';
    return;
  }
  const estadoLabel = {ok:'OK',bajo:'BAJO',critico:'CRÍTICO',agotado:'AGOTADO'};
  const estadoClass = {ok:'stock-ok',bajo:'stock-bajo',critico:'stock-critico',agotado:'stock-agotado'};

  el.innerHTML = d.items.map(item => `
    <div class="inv-item-row ${item.estado}">
      <div style="flex:1;">
        <div class="inv-nombre">${item.nombre}</div>
        <div class="inv-cat">${item.categoria || '—'} · ${item.proveedor ? 'Proveedor: ' + item.proveedor : 'Sin proveedor'}</div>
      </div>
      <div style="text-align:center;min-width:90px;">
        <div class="inv-stock ${item.estado}">${item.stock_actual} ${item.unidad}</div>
        <div class="inv-minimos">Min: ${item.stock_minimo} · Crít: ${item.stock_critico}</div>
      </div>
      <div><span class="stock-badge ${estadoClass[item.estado]}">${estadoLabel[item.estado]}</span></div>
      <div class="mov-btns">
        <button class="mov-btn mov-entrada" onclick="abrirMovimiento('${item.id}','${item.nombre}','entrada')">↑ Entrada</button>
        <button class="mov-btn mov-salida"  onclick="abrirMovimiento('${item.id}','${item.nombre}','salida')">↓ Salida</button>
        <button class="mov-btn mov-ajuste"  onclick="abrirMovimiento('${item.id}','${item.nombre}','ajuste')">⚖</button>
        <button class="btn btn-ash btn-sm"  onclick="eliminarItemInv('${item.id}')" style="color:var(--red);">✕</button>
      </div>
    </div>`).join('');
}

function filtrarAlertas() {
  soloAlertasInv = !soloAlertasInv;
  const btn = document.getElementById('btnAlertas');
  btn.style.background = soloAlertasInv ? 'var(--red)' : '';
  btn.style.color      = soloAlertasInv ? 'var(--white)' : '';
  loadInventario();
}

function toggleInvForm() {
  const f = document.getElementById('invForm');
  f.style.display = f.style.display === 'none' ? 'block' : 'none';
}

async function crearItemInv() {
  const nombre   = document.getElementById('invNombre').value.trim();
  const cat      = document.getElementById('invCat').value.trim();
  const unidad   = document.getElementById('invUnidad').value;
  const stock    = parseFloat(document.getElementById('invStock').value)   || 0;
  const minimo   = parseFloat(document.getElementById('invMinimo').value)  || 0;
  const critico  = parseFloat(document.getElementById('invCritico').value) || 0;
  const costo    = parseInt(document.getElementById('invCosto').value)     || 0;
  const prov     = document.getElementById('invProveedor').value.trim();

  if (!nombre) return toast('El nombre es obligatorio', 'err');

  try {
    await api('crear_item_inventario', {
      action: 'crear_item_inventario',
      nombre, categoria: cat, unidad,
      stock_actual: stock, stock_minimo: minimo,
      stock_critico: critico, costo_unidad: costo,
      proveedor: prov,
    });
    toast('Insumo agregado');
    ['invNombre','invCat','invProveedor'].forEach(id => document.getElementById(id).value = '');
    ['invStock','invMinimo','invCritico','invCosto'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('invForm').style.display = 'none';
    loadInventario();
  } catch(e) { toast(e.message, 'err'); }
}

function abrirMovimiento(id, nombre, tipo) {
  document.getElementById('movItemId').value = id;
  document.getElementById('modalMovTitle').textContent = nombre.toUpperCase();
  document.getElementById('movCantidad').value = '';
  document.getElementById('movNota').value     = '';
  setMovTipo(tipo);
  document.getElementById('modalMov').classList.add('open');
}

function setMovTipo(tipo) {
  movTipoActual = tipo;
  ['entrada','salida','ajuste'].forEach(t => {
    const btn = document.getElementById('movTipo' + t.charAt(0).toUpperCase() + t.slice(1));
    if (btn) btn.classList.toggle('active', t === tipo);
  });
  const labels = {entrada:'Cantidad a ingresar', salida:'Cantidad a retirar', ajuste:'Nuevo stock total'};
  document.getElementById('movCantLabel').textContent = labels[tipo] + ' *';
}

async function registrarMovimiento() {
  const id       = document.getElementById('movItemId').value;
  const cantidad = parseFloat(document.getElementById('movCantidad').value) || 0;
  const nota     = document.getElementById('movNota').value.trim();

  if (cantidad <= 0) return toast('La cantidad debe ser mayor a 0', 'err');

  try {
    const d = await api('movimiento_inventario', {
      action: 'movimiento_inventario',
      id, tipo: movTipoActual, cantidad, nota,
    });
    toast('Stock actualizado → ' + d.stock_actual);
    closeModal();
    loadInventario();
  } catch(e) { toast(e.message, 'err'); }
}

async function eliminarItemInv(id) {
  if (!confirm('¿Eliminar este insumo del inventario?')) return;
  try {
    await api('eliminar_item_inventario', { action: 'eliminar_item_inventario', id });
    toast('Insumo eliminado');
    loadInventario();
  } catch(e) { toast(e.message, 'err'); }
}

// ══════════════════════════════════════════════════════════════
// MÓDULO 04 — PROMOS JS
// ══════════════════════════════════════════════════════════════
let tipoDescActual = 'porcentaje';

async function loadPromos() {
  try {
    const d = await api('get_promos');
    renderPromos(d.promos || []);
    // Cargar productos para el selector "Aplica a"
    const dp = await api('get_productos');
    const sel = document.getElementById('pAplicaA');
    if (sel.options.length <= 1) {
      (dp.productos || []).forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.emoji + ' ' + p.nombre;
        sel.appendChild(opt);
      });
    }
  } catch(e) { toast(e.message, 'err'); }
}

function renderPromos(promos) {
  const el = document.getElementById('promosList');
  if (!promos.length) {
    el.innerHTML = '<div style="padding:40px;text-align:center;color:var(--gray);font-size:12px;letter-spacing:2px;">SIN PROMOS CREADAS — CREA LA PRIMERA ARRIBA</div>';
    return;
  }
  el.innerHTML = promos.map(p => {
    const vigente = p.vigente;
    const hoy = new Date().toISOString().slice(0,10);
    const vencida = p.fecha_fin && p.fecha_fin < hoy;
    const statusClass = !p.activa ? 'inactiva' : vencida ? 'vencida' : vigente ? 'vigente' : 'inactiva';
    const statusLabel = !p.activa ? 'INACTIVA' : vencida ? 'VENCIDA' : vigente ? 'ACTIVA' : 'INACTIVA';
    const descLabel = p.tipo === 'porcentaje' ? p.valor + '%' : '$' + p.valor.toLocaleString('es-CO');

    return `<div class="promo-card ${!p.activa || vencida ? 'inactiva' : ''}">
      <span class="promo-badge ${statusClass}">${statusLabel}</span>
      <div class="promo-icon">${p.tipo==='porcentaje'?'🏷️':'💰'}</div>
      <div class="promo-info">
        <div class="promo-name">${p.nombre}</div>
        <div class="promo-desc">${p.descripcion || '—'}</div>
        <div class="promo-meta">
          <span class="promo-tag">${p.tipo === 'porcentaje' ? '% Porcentaje' : '$ Valor fijo'}</span>
          ${p.aplica_a !== 'todos' ? `<span class="promo-tag">Producto específico</span>` : '<span class="promo-tag">Todos los productos</span>'}
          ${p.fecha_inicio ? `<span class="promo-tag">Desde: ${p.fecha_inicio}</span>` : ''}
          ${p.fecha_fin    ? `<span class="promo-tag">Hasta: ${p.fecha_fin}</span>`    : '<span class="promo-tag">Sin fecha límite</span>'}
        </div>
      </div>
      <div style="text-align:right;flex-shrink:0;">
        <div class="promo-descuento">${descLabel}</div>
        <div class="promo-usos">${p.usos || 0} uso${(p.usos||0)!==1?'s':''}</div>
        <div style="display:flex;gap:6px;margin-top:8px;justify-content:flex-end;">
          <button class="btn btn-ash btn-sm" onclick="togglePromo('${p.id}')">${p.activa?'DESACTIVAR':'ACTIVAR'}</button>
          <button class="btn btn-ash btn-sm" style="color:var(--red);" onclick="eliminarPromo('${p.id}')">✕</button>
        </div>
      </div>
    </div>`;
  }).join('');
}

function togglePromoForm() {
  const f = document.getElementById('promoForm');
  f.style.display = f.style.display === 'none' ? 'block' : 'none';
}

function setTipoDesc(tipo) {
  tipoDescActual = tipo;
  document.querySelectorAll('.tipo-desc-btn').forEach(b => b.classList.remove('active'));
  document.getElementById(tipo === 'porcentaje' ? 'tdPorcentaje' : 'tdValorFijo').classList.add('active');
  document.getElementById('pValorLabel').textContent = tipo === 'porcentaje' ? 'Descuento (%) *' : 'Descuento ($ pesos) *';
  document.getElementById('pValor').placeholder = tipo === 'porcentaje' ? 'Ej: 20' : 'Ej: 5000';
}

async function crearPromo() {
  const nombre = document.getElementById('pNombre').value.trim();
  const desc   = document.getElementById('pDesc').value.trim();
  const valor  = parseFloat(document.getElementById('pValor').value) || 0;
  const desde  = document.getElementById('pDesde').value;
  const hasta  = document.getElementById('pHasta').value;
  const aplica = document.getElementById('pAplicaA').value;

  if (!nombre)  return toast('El nombre es obligatorio', 'err');
  if (valor<=0) return toast('El valor debe ser mayor a 0', 'err');

  try {
    await api('crear_promo', {
      action: 'crear_promo',
      nombre, descripcion: desc, valor,
      tipo: tipoDescActual,
      aplica_a: aplica,
      fecha_inicio: desde,
      fecha_fin: hasta,
    });
    toast('Promo creada');
    document.getElementById('pNombre').value = '';
    document.getElementById('pDesc').value   = '';
    document.getElementById('pValor').value  = '';
    document.getElementById('promoForm').style.display = 'none';
    loadPromos();
  } catch(e) { toast(e.message, 'err'); }
}

async function togglePromo(id) {
  try {
    await api('toggle_promo', { action: 'toggle_promo', id });
    toast('Estado actualizado');
    loadPromos();
  } catch(e) { toast(e.message, 'err'); }
}

async function eliminarPromo(id) {
  if (!confirm('¿Eliminar esta promo?')) return;
  try {
    await api('eliminar_promo', { action: 'eliminar_promo', id });
    toast('Promo eliminada');
    loadPromos();
  } catch(e) { toast(e.message, 'err'); }
}

// ══════════════════════════════════════════════════════════════
// MÓDULO 03 — GASTOS JS
// ══════════════════════════════════════════════════════════════
let categoriasGasto = [];
let catActualGasto  = '';

async function initGastos() { // Returns promise so callers can chain .then()
  // Cargar categorías para los botones y filtro
  try {
    const d = await api('get_categorias_gasto');
    categoriasGasto = d.categorias || [];

    // Botones de categoría en el formulario
    document.getElementById('catBtns').innerHTML = categoriasGasto.map(c =>
      `<button class="cat-btn-g" id="cat-${c.id}" onclick="setCatGasto('${c.id}')">${c.icon} ${c.label}</button>`
    ).join('');

    // Filtro select
    const sel = document.getElementById('gCatFilter');
    categoriasGasto.forEach(c => {
      const opt = document.createElement('option');
      opt.value = c.id;
      opt.textContent = c.icon + ' ' + c.label;
      sel.appendChild(opt);
    });

    // Seleccionar primera categoría por defecto
    if (categoriasGasto.length) setCatGasto(categoriasGasto[0].id);
  } catch(e) { toast(e.message, 'err'); }
}

async function loadGastos() {
  const desde = document.getElementById('gDesde')?.value || '';
  const hasta = document.getElementById('gHasta')?.value || '';
  const cat   = document.getElementById('gCatFilter')?.value || '';

  try {
    let url = 'get_gastos';
    const params = [];
    if (desde) params.push('desde=' + desde);
    if (hasta) params.push('hasta=' + hasta);
    if (cat)   params.push('categoria=' + cat);
    if (params.length) url += '&' + params.join('&');

    const d = await api(url);
    renderGastos(d);

    // Cargar balance del día
    const hoy = new Date().toISOString().slice(0,10);
    const bal = await api('get_balance&desde=' + hoy + '&hasta=' + hoy);
    renderBalance(bal);
  } catch(e) { toast(e.message, 'err'); }
}

function renderBalance(b) {
  const fmt = v => '$' + Math.abs(v).toLocaleString('es-CO');
  document.getElementById('bal-ingresos').textContent = fmt(b.ingresos);
  document.getElementById('bal-gastos').textContent   = fmt(b.gastos);
  document.getElementById('bal-utilidad').textContent = fmt(b.utilidad);
  document.getElementById('bal-margen').textContent   = b.margen + '%';
  // Color utilidad según positivo/negativo
  const utilEl = document.getElementById('bal-utilidad');
  utilEl.style.color = b.utilidad >= 0 ? 'var(--green)' : 'var(--red)';
}

function renderGastos(d) {
  // Total período
  document.getElementById('gTotalPeriodo').textContent = '$' + d.total.toLocaleString('es-CO');
  document.getElementById('gCountPeriodo').textContent = d.gastos.length + ' gasto' + (d.gastos.length !== 1 ? 's' : '') + ' registrado' + (d.gastos.length !== 1 ? 's' : '');

  // Barras por categoría
  const maxCat = Math.max(...d.por_categoria.map(c => c.total), 1);
  document.getElementById('catBarsChart').innerHTML = d.por_categoria
    .filter(c => c.total > 0)
    .map(c => `
      <div class="cat-bar-row">
        <span class="cat-bar-label">${c.icon} ${c.label}</span>
        <div class="cat-bar-track"><div class="cat-bar-fill" style="width:${c.total/maxCat*100}%"></div></div>
        <span class="cat-bar-val">$${Math.round(c.total/1000)}K</span>
      </div>`).join('') || '<div style="color:var(--gray);font-size:12px;letter-spacing:1px;">SIN GASTOS REGISTRADOS</div>';

  // Tabla
  const el = document.getElementById('gastosTable');
  if (!d.gastos.length) {
    el.innerHTML = '<div style="padding:32px;text-align:center;color:var(--gray);font-size:12px;letter-spacing:2px;">SIN GASTOS EN ESTE PERÍODO</div>';
    return;
  }
  const catMap = {};
  categoriasGasto.forEach(c => catMap[c.id] = c);

  el.innerHTML = `<table><thead><tr>
    <th>Fecha</th><th>Concepto</th><th>Categoría</th><th>Nota</th><th>Monto</th><th>Acción</th>
  </tr></thead><tbody>${d.gastos.map(g => {
    const cat = catMap[g.categoria] || {icon:'➕', label: g.categoria};
    return `<tr>
      <td style="color:var(--gray);font-size:12px;">${g.fecha}<br><span style="font-size:10px;">${g.hora||''}</span></td>
      <td style="font-size:14px;">${g.concepto}</td>
      <td><span style="font-size:12px;">${cat.icon} ${cat.label}</span></td>
      <td style="font-size:12px;color:var(--gray);">${g.nota||'—'}</td>
      <td style="font-size:20px;color:var(--red);">-$${g.monto.toLocaleString('es-CO')}</td>
      <td><button class="btn btn-ash btn-sm" onclick="eliminarGasto('${g.id}')">✕</button></td>
    </tr>`;
  }).join('')}</tbody></table>`;
}

function toggleGastoForm() {
  const f = document.getElementById('gastoForm');
  f.style.display = f.style.display === 'none' ? 'block' : 'none';
}

function setCatGasto(id) {
  catActualGasto = id;
  document.querySelectorAll('.cat-btn-g').forEach(b => b.classList.remove('active'));
  document.getElementById('cat-' + id)?.classList.add('active');
}

async function registrarGasto() {
  const concepto = document.getElementById('gConcepto').value.trim();
  const monto    = parseInt(document.getElementById('gMonto').value) || 0;
  const nota     = document.getElementById('gNota').value.trim();

  if (!concepto)       return toast('El concepto es obligatorio', 'err');
  if (monto <= 0)      return toast('El monto debe ser mayor a 0', 'err');
  if (!catActualGasto) return toast('Selecciona una categoría', 'err');

  try {
    await api('registrar_gasto', {
      action: 'registrar_gasto',
      concepto, monto, nota,
      categoria: catActualGasto
    });
    toast('Gasto registrado');
    document.getElementById('gConcepto').value = '';
    document.getElementById('gMonto').value    = '';
    document.getElementById('gNota').value     = '';
    document.getElementById('gastoForm').style.display = 'none';
    loadGastos();
  } catch(e) { toast(e.message, 'err'); }
}

async function eliminarGasto(id) {
  if (!confirm('¿Eliminar este gasto?')) return;
  try {
    await api('eliminar_gasto', { action: 'eliminar_gasto', id });
    toast('Gasto eliminado');
    loadGastos();
  } catch(e) { toast(e.message, 'err'); }
}

function exportGastosCSV() {
  const desde = document.getElementById('gDesde')?.value || '';
  const hasta = document.getElementById('gHasta')?.value || '';
  let url = 'api.php?action=export_gastos_csv';
  if (desde) url += '&desde=' + desde;
  if (hasta) url += '&hasta=' + hasta;
  window.open(url);
}

// ══════════════════════════════════════════════════════════════
// MÓDULO 02 — ESTADÍSTICAS JS
// ══════════════════════════════════════════════════════════════
let statsPeriodo = 'hoy';

async function loadStats(periodo, btn) {
  statsPeriodo = periodo || statsPeriodo;
  if (btn) {
    document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
  }
  try {
    const d = await api('get_stats&periodo=' + statsPeriodo);
    renderStats(d);
  } catch(e) { toast(e.message, 'err'); }
}

function renderStats(d) {
  // Rango label
  document.getElementById('stats-rango').textContent =
    d.rango.desde === d.rango.hasta
      ? d.rango.desde
      : `${d.rango.desde}  →  ${d.rango.hasta}`;

  // Día de descanso badge
  const dias = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
  document.getElementById('descanso-info').innerHTML =
    `<span class="descanso-badge">Descanso: ${dias[d.dia_descanso]}</span>`;

  // KPIs
  const fmt = v => '$' + Math.round(v/1000) + 'K';
  document.getElementById('sk-ventas').textContent  = fmt(d.kpis.total_ventas);
  document.getElementById('sk-pedidos').textContent = d.kpis.total_pedidos;
  document.getElementById('sk-ticket').textContent  = fmt(d.kpis.ticket_promedio);
  document.getElementById('sk-estrella').textContent = d.kpis.plato_estrella;

  // Top platos
  const maxQty = d.top_platos[0]?.qty || 1;
  document.getElementById('chart-platos').innerHTML = d.top_platos.map(p => `
    <div class="bar-item">
      <span class="bar-item-label">${p.nombre}</span>
      <div class="bar-track"><div class="bar-fill-el" style="width:${p.qty/maxQty*100}%;background:var(--red);"></div></div>
      <span class="bar-item-val">${p.qty}</span>
    </div>`).join('');

  // Ventas por día
  const maxVenta = Math.max(...d.ventas_dia.map(v => v.total), 1);
  document.getElementById('chart-dias').innerHTML = d.ventas_dia.map(v => `
    <div class="bar-item">
      <span class="bar-item-label" style="${v.es_descanso?'color:var(--gold);':''}">
        ${v.dia} ${v.es_descanso ? '🔴' : ''}
      </span>
      <div class="bar-track"><div class="bar-fill-el" style="width:${v.total/maxVenta*100}%;background:var(--blue,#378add);"></div></div>
      <span class="bar-item-val" style="font-size:11px;">$${Math.round(v.total/1000)}K</span>
    </div>`).join('');

  // Horas pico
  const horas = Object.entries(d.horas);
  const maxH   = Math.max(...horas.map(([,v]) => v), 1);
  document.getElementById('chart-horas').innerHTML = horas.map(([h, v]) =>
    `<div class="hora-bar" style="height:${Math.max(v/maxH*100,4)}%;background:${v===d.hora_pico?'var(--gold)':'var(--ash)'};" title="${h}:00 — ${v} pedidos"></div>`
  ).join('');
  document.getElementById('chart-horas-labels').innerHTML = horas.map(([h]) =>
    `<span class="hora-label-el">${h}</span>`
  ).join('');
  document.getElementById('hora-pico-label').textContent =
    `Hora pico: ${d.hora_pico}:00 — ${d.horas[d.hora_pico] || 0} pedidos`;

  // Métodos de pago
  const iconos = {efectivo:'💵',tarjeta:'💳',nequi:'📱',breb:'🔑'};
  const labels = {efectivo:'Efectivo',tarjeta:'Tarjeta',nequi:'Nequi',breb:'BREB o Llave'};
  document.getElementById('chart-metodos').innerHTML = d.metodos_pago.map(m => `
    <div class="metodo-row">
      <span class="metodo-row-left">${iconos[m.metodo]||'💰'} ${labels[m.metodo]||m.metodo}</span>
      <span class="metodo-row-right">${m.pct}% · $${Math.round(m.total/1000)}K</span>
    </div>`).join('');

  // Estados pedidos
  const eColors = {pendiente:'var(--orange)',preparando:'var(--blue,#378add)',entregado:'var(--green)',cancelado:'var(--gray)'};
  const eLabels = {pendiente:'Pendientes',preparando:'Preparando',entregado:'Entregados',cancelado:'Cancelados'};
  document.getElementById('chart-estados').innerHTML = Object.entries(d.estados_pedidos).map(([e,v]) => `
    <div class="stat-kpi" style="--accent:${eColors[e]}">
      <div class="stat-kpi-label">${eLabels[e]}</div>
      <div class="stat-kpi-val">${v}</div>
    </div>`).join('');
}

// ══════════════════════════════════════════════════════════════
// MÓDULO 01 — CAJA JS
// ══════════════════════════════════════════════════════════════
let turnoActivo = null;
let metodoActual = 'efectivo';
let tipoActual   = 'ingreso';

async function loadCaja() {
  try {
    const [turnoData] = await Promise.all([api('get_turno'), loadCuentas()]);
    turnoActivo = turnoData.turno;
    renderCaja();
  } catch(e) { toast(e.message, 'err'); }
}

async function loadCuentas() {
  try {
    const d = await api('get_cuentas_abiertas');
    renderCuentas(d.cuentas || []);
  } catch(e) {}
}

function renderCuentas(cuentas) {
  const c = document.getElementById('cuentas-container');
  if (!cuentas.length) {
    c.innerHTML = '<div class="cuentas-empty">✓ SIN CUENTAS ABIERTAS</div>';
    return;
  }
  const estadoLabel = {pendiente:'En espera',preparando:'Preparando',listo:'¡Listo!',entregado:'Entregado'};
  const metodos = [{v:'efectivo',l:'💵 Efectivo'},{v:'tarjeta',l:'💳 Tarjeta'},{v:'nequi',l:'📱 Nequi'},{v:'breb',l:'🔑 BREB'}];
  c.innerHTML = '<div class="cuentas-grid">' + cuentas.map(p => {
    const mins = Math.floor((Date.now() - new Date(p.fecha)) / 60000);
    const tiempoStr = mins < 1 ? 'Ahora' : `hace ${mins} min`;
    const items = (p.productos||[]).map(i => `${i.qty}× ${i.nombre}`).join(' · ');
    const opts  = metodos.map(m => `<option value="${m.v}">${m.l}</option>`).join('');
    return `<div class="cuenta-card ${p.estado}">
      <div class="cuenta-header">
        <div class="cuenta-mesa">Mesa ${p.mesa_num||'?'}</div>
        <div class="cuenta-meta">
          <div>${tiempoStr}</div>
          <div>${p.mesero||''}</div>
          <div>#${p.id} · ${estadoLabel[p.estado]||p.estado}</div>
        </div>
      </div>
      <div class="cuenta-items">${items}${p.nota?`<div style="color:var(--gold);font-size:11px;margin-top:4px;">📝 ${p.nota}</div>`:''}</div>
      <div class="cuenta-footer">
        <div class="cuenta-total">$${p.total.toLocaleString('es-CO')}</div>
        <div class="cobrar-wrap">
          <select class="cobrar-metodo" id="metodo-${p.id}">${opts}</select>
          <button class="btn-cobrar" onclick="cobrarCuenta(${p.id})">COBRAR</button>
        </div>
      </div>
    </div>`;
  }).join('') + '</div>';
}

async function cobrarCuenta(pedidoId) {
  const metodo = document.getElementById('metodo-' + pedidoId)?.value || 'efectivo';
  try {
    const r = await api('cerrar_cuenta', {pedido_id: pedidoId, metodo});
    if (r.success) {
      toast(r.en_caja ? 'Cuenta cobrada y registrada en caja ✓' : 'Cuenta cobrada (sin turno activo)');
      await loadCaja();
    } else { toast(r.error || 'Error al cobrar', 'err'); }
  } catch(e) { toast(e.message, 'err'); }
}

function renderCaja() {
  const hoy = new Date().toLocaleDateString('es-CO', {weekday:'long',day:'numeric',month:'long'});
  document.getElementById('caja-fecha').textContent = hoy.charAt(0).toUpperCase() + hoy.slice(1);

  const btn  = document.getElementById('btnAbrirCerrar');
  const dot  = document.getElementById('turnoDot');
  const lbl  = document.getElementById('turnoLabel');
  const hora = document.getElementById('turnoHora');
  const form = document.getElementById('txForm');

  if (turnoActivo) {
    btn.textContent = 'CERRAR TURNO';
    btn.className   = 'btn btn-ash';
    dot.className   = 'turno-dot abierto';
    lbl.textContent = 'Turno abierto';
    hora.textContent = `Desde las ${turnoActivo.apertura}  ·  Base: $${turnoActivo.base_caja.toLocaleString('es-CO')}`;
    form.style.display = 'block';
    renderTotales(turnoActivo.totales, turnoActivo.transacciones?.length || 0);
    renderTxTable(turnoActivo.transacciones || []);
  } else {
    btn.textContent = 'ABRIR TURNO';
    btn.className   = 'btn btn-red';
    dot.className   = 'turno-dot cerrado';
    lbl.textContent = 'Sin turno abierto';
    hora.textContent = '';
    form.style.display = 'none';
    renderTotales(null, 0);
    renderTxTable([]);
  }
}

function renderTotales(t, txCount) {
  const fmt = v => '$' + (v||0).toLocaleString('es-CO');
  document.getElementById('totalIngresos').textContent = fmt(t?.total_ingresos);
  document.getElementById('totalEgresos').textContent  = fmt(t?.total_egresos);
  document.getElementById('totalNeto').textContent     = fmt(t?.neto);
  document.getElementById('totalTx').textContent       = txCount;
  document.getElementById('m-efectivo').textContent    = fmt(t?.efectivo);
  document.getElementById('m-tarjeta').textContent     = fmt(t?.tarjeta);
  document.getElementById('m-nequi').textContent       = fmt(t?.nequi);
  document.getElementById('m-breb').textContent = fmt(t?.bancolombia);
}

function renderTxTable(txs) {
  const el = document.getElementById('txTable');
  if (!txs.length) {
    el.innerHTML = '<div style="padding:32px;text-align:center;color:var(--gray);font-size:12px;letter-spacing:2px;">SIN TRANSACCIONES EN ESTE TURNO</div>';
    return;
  }
  const rows = [...txs].reverse().map(tx => `
    <tr>
      <td style="color:var(--gray);font-size:11px;">${tx.hora}</td>
      <td>${tx.concepto}</td>
      <td><span style="font-size:11px;letter-spacing:1px;">${{efectivo:'💵 Efectivo',tarjeta:'💳 Tarjeta',nequi:'📱 Nequi',breb:'🏦 BREB o Llave'}[tx.metodo]||tx.metodo}</span></td>
      <td><span class="badge ${tx.tipo==='ingreso'?'b-entregado':'b-cancelado'}">${tx.tipo.toUpperCase()}</span></td>
      <td style="font-size:18px;color:${tx.tipo==='ingreso'?'var(--green)':'var(--red)'};">
        ${tx.tipo==='ingreso'?'+':'-'}$${tx.monto.toLocaleString('es-CO')}
      </td>
      <td style="font-size:11px;color:var(--gray);">${tx.referencia||'—'}</td>
    </tr>`).join('');
  el.innerHTML = `<table><thead><tr><th>Hora</th><th>Concepto</th><th>Método</th><th>Tipo</th><th>Monto</th><th>Ref.</th></tr></thead><tbody>${rows}</tbody></table>`;
}

async function toggleTurno() {
  if (turnoActivo) {
    if (!confirm('¿Cerrar el turno de caja?')) return;
    try {
      await api('cerrar_turno', {action:'cerrar_turno'});
      toast('Turno cerrado correctamente');
      await loadCaja();
    } catch(e) { toast(e.message,'err'); }
  } else {
    const base = prompt('¿Cuánto dinero hay en caja al abrir? (base de caja)');
    if (base === null) return;
    const monto = parseInt(base.replace(/\D/g,'')) || 0;
    try {
      await api('abrir_turno', {action:'abrir_turno', base_caja: monto});
      toast('Turno abierto');
      await loadCaja();
    } catch(e) { toast(e.message,'err'); }
  }
}

function setMetodo(m) {
  metodoActual = m;
  document.querySelectorAll('.metodo-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('mb-' + m)?.classList.add('active');
}

function setTipo(t) {
  tipoActual = t;
  document.querySelectorAll('.tipo-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tipo' + t.charAt(0).toUpperCase() + t.slice(1))?.classList.add('active');
}

async function registrarTx() {
  const concepto  = document.getElementById('txConcepto').value.trim();
  const monto     = parseInt(document.getElementById('txMonto').value) || 0;
  const referencia = document.getElementById('txRef').value.trim();
  const pedido_id  = parseInt(document.getElementById('txPedidoId').value) || null;

  if (!concepto) return toast('El concepto es obligatorio','err');
  if (monto <= 0) return toast('El monto debe ser mayor a 0','err');

  try {
    const d = await api('registrar_transaccion', {
      action: 'registrar_transaccion',
      concepto, monto, referencia, pedido_id,
      metodo: metodoActual,
      tipo:   tipoActual,
    });
    // Actualizar totales en tiempo real
    if (turnoActivo) {
      turnoActivo.totales = d.totales;
      turnoActivo.transacciones = turnoActivo.transacciones || [];
      turnoActivo.transacciones.push(d.transaccion);
      renderTotales(d.totales, turnoActivo.transacciones.length);
      renderTxTable(turnoActivo.transacciones);
    }
    // Limpiar formulario
    document.getElementById('txConcepto').value = '';
    document.getElementById('txMonto').value    = '';
    document.getElementById('txRef').value      = '';
    document.getElementById('txPedidoId').value = '';
    toast('Transacción registrada');
  } catch(e) { toast(e.message,'err'); }
}

function exportCajaCSV() {
  window.open('api.php?action=export_caja_csv');
}

// showPage unificado — todas las cargas están en la función principal arriba

document.addEventListener('DOMContentLoaded', () => {
  const fd = document.getElementById('f-desde'); if (fd) fd.value = firstDay;
  const gd = document.getElementById('gDesde'); if (gd) gd.value = firstDay;
  const gh = document.getElementById('gHasta'); if (gh) gh.value = today;
  const eh = document.getElementById('ex-hasta'); if (eh) eh.value = today;
  const ed = document.getElementById('ex-desde'); if (ed) ed.value = firstDay;
  loadDashboard();
});
</script>

<?php endif; ?>
</body>
</html>
 
