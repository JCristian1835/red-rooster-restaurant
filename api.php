<?php
/**
 * Red Rooster Restaurant — API v3
 * php://input se lee UNA SOLA VEZ aquí arriba, luego se pasa a todo lo demás.
 */
@ini_set('display_errors', '0');
@error_reporting(0);
ob_start(); // Captura warnings/notices antes del JSON

require_once __DIR__ . '/includes/security.php';

// ── 1. CORS + headers + Sesión ───────────────────────────────────────────────
apply_cors();
set_security_headers();
start_secure_session();

// ── 2. Leer input UNA SOLA VEZ ───────────────────────────────────────────────
$raw    = file_get_contents('php://input');
$body   = json_decode($raw, true) ?? [];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($body['action'] ?? '');

// ── 5. Helpers ────────────────────────────────────────────────────────────────
function respond($data, $code = 200) {
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function rjson($file) {
    if (!file_exists($file)) return [];
    $d = json_decode(file_get_contents($file), true);
    return is_array($d) ? $d : [];
}

function wjson($file, $data) {
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function reqAuth() {
    if (!is_auth()) respond(['error' => 'No autorizado'], 401);
}

function verifyCsrf() {
    global $body;
    verify_csrf_token($body); // Pasa el body ya parseado — NO re-lee php://input
}

function san($v, $max = 200) { return sanitize_string($v, $max); }
function sanInt($v, $mn = 0, $mx = PHP_INT_MAX) { return sanitize_int($v, $mn, $mx); }

function is_mesero() { return ($_SESSION['mesero'] ?? false) === true || is_auth(); }
function is_cocina()  { return ($_SESSION['cocina']  ?? false) === true || is_auth(); }
function reqMesero()  { if (!is_mesero()) respond(['error' => 'No autorizado'], 401); }
function reqCocina()  { if (!is_cocina()) respond(['error' => 'No autorizado'], 401); }

$D = __DIR__ . '/data/';

// ══════════════════════════════════════════════════════════════════════════════
// ENDPOINTS PÚBLICOS
// ══════════════════════════════════════════════════════════════════════════════

if ($method === 'GET' && $action === 'menu') {
    $prods  = rjson($D.'productos.json');
    $active = array_values(array_filter($prods, fn($p) => !empty($p['activo'])));
    respond(['success' => true, 'productos' => $active]);
}

if ($method === 'GET' && $action === 'csrf_token') {
    respond(['token' => generate_csrf_token()]);
}

if ($method === 'GET' && $action === 'get_promos_activas') {
    $data = rjson($D.'promos.json');
    $ps   = $data['promos'] ?? $data;
    $hoy  = date('Y-m-d');
    $act  = array_values(array_filter($ps, function($p) use ($hoy) {
        if (empty($p['activa'])) return false;
        if (!empty($p['fecha_inicio']) && $hoy < $p['fecha_inicio']) return false;
        if (!empty($p['fecha_fin'])    && $hoy > $p['fecha_fin'])    return false;
        return true;
    }));
    respond(['success' => true, 'promos' => $act]);
}

if ($method === 'POST' && $action === 'save_pedido') {
    $cliente  = san($body['cliente'] ?? '', 100);
    $telefono = san($body['telefono'] ?? '', 20);
    if (!$cliente || !$telefono) respond(['error' => 'Nombre y teléfono requeridos'], 422);
    $prods = rjson($D.'productos.json');
    $pmap  = []; foreach ($prods as $p) $pmap[$p['id']] = $p;
    $items = []; $total = 0;
    foreach ($body['productos'] ?? [] as $item) {
        $id  = intval($item['id'] ?? 0);
        $qty = sanInt($item['qty'] ?? 0, 0, 50);
        if ($qty <= 0 || !isset($pmap[$id]) || empty($pmap[$id]['activo'])) continue;
        $items[] = ['id'=>$id,'nombre'=>$pmap[$id]['nombre'],'qty'=>$qty,'precio'=>$pmap[$id]['precio']];
        $total  += $pmap[$id]['precio'] * $qty;
    }
    if (!$items) respond(['error' => 'Sin productos válidos'], 422);
    $pedidos = rjson($D.'pedidos.json');
    $nid     = $pedidos ? max(array_column($pedidos, 'id')) + 1 : 101;
    $pedido  = ['id'=>$nid,'fecha'=>date('c'),'cliente'=>$cliente,'telefono'=>$telefono,
                'direccion'=>san($body['direccion']??'',200),'nota'=>san($body['nota']??'',300),
                'productos'=>$items,'total'=>$total,'estado'=>'pendiente',
                'ip'=>md5(get_client_ip())];
    $pedidos[] = $pedido;
    wjson($D.'pedidos.json', $pedidos);
    respond(['success'=>true,'id'=>$nid,'total'=>$total]);
}

// ══════════════════════════════════════════════════════════════════════════════
// AUTH
// ══════════════════════════════════════════════════════════════════════════════

if ($method === 'POST' && $action === 'login') {
    $user = $body['usuario'] ?? '';
    $pass = $body['password'] ?? '';
    if (hash_equals(ADMIN_USER, $user) && password_verify($pass, ADMIN_PASS_HASH)) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        $_SESSION['_last_active'] = time();
        respond(['success'=>true, 'csrf_token'=>generate_csrf_token()]);
    }
    respond(['error' => 'Credenciales incorrectas'], 401);
}

if ($method === 'POST' && $action === 'logout') {
    session_unset(); session_destroy();
    respond(['success' => true]);
}

if ($method === 'GET' && $action === 'check_auth') {
    respond(['auth' => is_auth(), 'csrf_token' => is_auth() ? generate_csrf_token() : '']);
}

// ══════════════════════════════════════════════════════════════════════════════
// AUTH MESERO
// ══════════════════════════════════════════════════════════════════════════════

if ($method === 'POST' && $action === 'mesero_login') {
    check_rate_limit('login');
    $pin = $body['pin'] ?? '';
    if (hash_equals(MESERO_PIN, (string)$pin)) {
        session_regenerate_id(true);
        $_SESSION['mesero'] = true;
        respond(['success' => true, 'csrf_token' => generate_csrf_token()]);
    }
    respond(['error' => 'PIN incorrecto'], 401);
}

if ($method === 'POST' && $action === 'mesero_logout') {
    unset($_SESSION['mesero']);
    respond(['success' => true]);
}

if ($method === 'GET' && $action === 'mesero_check_auth') {
    respond(['auth' => is_mesero(), 'csrf_token' => is_mesero() ? generate_csrf_token() : '']);
}

// ══════════════════════════════════════════════════════════════════════════════
// AUTH COCINA
// ══════════════════════════════════════════════════════════════════════════════

if ($method === 'POST' && $action === 'cocina_login') {
    check_rate_limit('login');
    $pin = $body['pin'] ?? '';
    if (hash_equals(COCINA_PIN, (string)$pin)) {
        session_regenerate_id(true);
        $_SESSION['cocina'] = true;
        respond(['success' => true, 'csrf_token' => generate_csrf_token()]);
    }
    respond(['error' => 'PIN incorrecto'], 401);
}

if ($method === 'POST' && $action === 'cocina_logout') {
    unset($_SESSION['cocina']);
    respond(['success' => true]);
}

if ($method === 'GET' && $action === 'cocina_check_auth') {
    respond(['auth' => is_cocina(), 'csrf_token' => is_cocina() ? generate_csrf_token() : '']);
}

// ══════════════════════════════════════════════════════════════════════════════
// MÓDULO MESERO — Toma de pedidos en mesa
// ══════════════════════════════════════════════════════════════════════════════

if ($method === 'POST' && $action === 'save_pedido_mesa') {
    reqMesero(); verifyCsrf();
    $tipo_raw = $body['tipo'] ?? 'mesa';
    $tipo    = in_array($tipo_raw, ['mesa','llevar','domicilio']) ? $tipo_raw : 'mesa';
    $mesa_id = san($body['mesa_id'] ?? '', 50);
    $mesa    = null;
    $mesas   = [];

    if ($tipo === 'llevar' || $tipo === 'domicilio') {
        // sin mesa
    } else if ($tipo === 'mesa') {
        if (!$mesa_id) respond(['error' => 'Mesa requerida'], 422);
        $mdata = rjson($D.'mesas.json'); $mesas = $mdata['mesas'] ?? [];
        if (!is_array($mesas) || isset($mesas['mesas'])) $mesas = [];
        foreach ($mesas as $m) {
            if (($m['id'] ?? '') === $mesa_id && !empty($m['activa'])) { $mesa = $m; break; }
        }
        if (!$mesa) respond(['error' => 'Mesa no encontrada'], 404);
    }

    $prods = rjson($D.'productos.json');
    $pmap  = []; foreach ($prods as $p) $pmap[$p['id']] = $p;
    $items = []; $total = 0;
    foreach ($body['productos'] ?? [] as $item) {
        $id  = intval($item['id'] ?? 0);
        $qty = sanInt($item['qty'] ?? 0, 0, 50);
        if ($qty <= 0 || !isset($pmap[$id]) || empty($pmap[$id]['activo'])) continue;
        $items[] = ['id'=>$id,'nombre'=>$pmap[$id]['nombre'],'qty'=>$qty,'precio'=>$pmap[$id]['precio']];
        $total  += $pmap[$id]['precio'] * $qty;
    }
    if (!$items) respond(['error' => 'Sin productos válidos'], 422);

    $pedidos = rjson($D.'pedidos.json');
    $nid     = $pedidos ? max(array_column($pedidos, 'id')) + 1 : 101;

    if ($tipo === 'llevar' || $tipo === 'domicilio') {
        $cliente = san($body['cliente'] ?? 'Cliente', 100);
        $pedido  = ['id'=>$nid,'fecha'=>date('c'),'tipo'=>$tipo,
                    'mesero'=>san($body['mesero']??'Mesero',50),
                    'nota'=>san($body['nota']??'',300),
                    'productos'=>$items,'total'=>$total,'estado'=>'pendiente',
                    'cliente'=>$cliente,
                    'telefono'=>san($body['telefono']??'',20),
                    'direccion'=>san($body['direccion']??'',200),
                    'ip'=>md5(get_client_ip())];
    } else {
        $pedido  = ['id'=>$nid,'fecha'=>date('c'),'tipo'=>'mesa',
                    'mesa_id'=>$mesa_id,'mesa_num'=>$mesa['numero']??0,
                    'mesero'=>san($body['mesero']??'Mesero',50),
                    'nota'=>san($body['nota']??'',300),
                    'productos'=>$items,'total'=>$total,'estado'=>'pendiente',
                    'cliente'=>'Mesa '.($mesa['numero']??'?'),'telefono'=>'',
                    'ip'=>md5(get_client_ip())];
        foreach ($mesas as &$m) {
            if ($m['id'] === $mesa_id) { $m['estado'] = 'ocupada'; break; }
        }
        wjson($D.'mesas.json', ['mesas' => $mesas]);
    }

    $pedidos[] = $pedido;
    wjson($D.'pedidos.json', $pedidos);
    respond(['success'=>true,'id'=>$nid,'total'=>$total]);
}

// ══════════════════════════════════════════════════════════════════════════════
// MÓDULO COCINA — Pantalla de cocina
// ══════════════════════════════════════════════════════════════════════════════

if ($method === 'GET' && $action === 'get_pedidos_cocina') {
    if (!is_mesero() && !is_cocina()) respond(['error' => 'No autorizado'], 401);
    $pedidos = rjson($D.'pedidos.json');
    $activos = array_values(array_filter($pedidos, fn($p) =>
        in_array($p['estado'] ?? '', ['pendiente', 'preparando'])
    ));
    usort($activos, fn($a, $b) => strcmp($a['fecha'] ?? '', $b['fecha'] ?? ''));
    respond(['success' => true, 'pedidos' => $activos]);
}

if ($method === 'POST' && $action === 'update_estado_cocina') {
    if (!is_mesero() && !is_cocina()) respond(['error' => 'No autorizado'], 401);
    verifyCsrf();
    $id     = intval($body['id'] ?? 0);
    $estado = $body['estado'] ?? '';
    if (!in_array($estado, ['preparando', 'listo', 'cancelado'])) respond(['error' => 'Estado inválido'], 400);
    $pedidos = rjson($D.'pedidos.json');
    $found   = false;
    foreach ($pedidos as &$p) {
        if ($p['id'] !== $id) continue;
        $p['estado'] = $estado; $found = true; break;
    }
    if (!$found) respond(['error' => 'Pedido no encontrado'], 404);
    wjson($D.'pedidos.json', $pedidos);
    respond(['success' => true]);
}

// ══════════════════════════════════════════════════════════════════════════════
// PEDIDOS
// ══════════════════════════════════════════════════════════════════════════════

if ($method === 'GET' && $action === 'get_pedidos') {
    reqAuth();
    $pedidos = rjson($D.'pedidos.json');
    usort($pedidos, fn($a,$b) => strcmp($b['fecha'], $a['fecha']));
    $desde  = $_GET['desde']  ?? '';
    $hasta  = $_GET['hasta']  ?? '';
    $estado = $_GET['estado'] ?? '';
    if ($desde)  $pedidos = array_values(array_filter($pedidos, fn($p) => substr($p['fecha'],0,10) >= $desde));
    if ($hasta)  $pedidos = array_values(array_filter($pedidos, fn($p) => substr($p['fecha'],0,10) <= $hasta));
    if ($estado) $pedidos = array_values(array_filter($pedidos, fn($p) => ($p['estado']??'') === $estado));
    respond(['success'=>true, 'pedidos'=>$pedidos]);
}

if ($method === 'POST' && $action === 'update_pedido') {
    reqAuth(); verifyCsrf();
    $id     = intval($body['id'] ?? 0);
    $estado = $body['estado'] ?? '';
    if (!in_array($estado, ['pendiente','preparando','listo','entregado','pagado','cancelado'])) respond(['error'=>'Estado inválido'],400);
    $pedidos = rjson($D.'pedidos.json');
    foreach ($pedidos as &$p) { if ($p['id'] === $id) { $p['estado'] = $estado; break; } }
    wjson($D.'pedidos.json', $pedidos);
    respond(['success' => true]);
}

// ══════════════════════════════════════════════════════════════════════════════
// PRODUCTOS
// ══════════════════════════════════════════════════════════════════════════════

if ($method === 'GET' && $action === 'get_productos') {
    reqAuth();
    respond(['success'=>true, 'productos'=>rjson($D.'productos.json')]);
}

if ($method === 'POST' && $action === 'update_producto') {
    reqAuth(); verifyCsrf();
    $id    = intval($body['id'] ?? 0);
    $prods = rjson($D.'productos.json');
    foreach ($prods as &$p) {
        if ($p['id'] !== $id) continue;
        if (isset($body['activo']))      $p['activo']      = (bool)$body['activo'];
        if (isset($body['precio']))      $p['precio']      = sanInt($body['precio'],100,9999900);
        if (isset($body['nombre']))      $p['nombre']      = san($body['nombre'],80);
        if (isset($body['descripcion'])) $p['descripcion'] = san($body['descripcion'],200);
        if (isset($body['emoji']))       $p['emoji']       = mb_substr($body['emoji'],0,2);
        break;
    }
    wjson($D.'productos.json', $prods);
    respond(['success' => true]);
}

if ($method === 'POST' && $action === 'create_producto') {
    reqAuth(); verifyCsrf();
    $nombre = san($body['nombre'] ?? '', 80);
    $precio = sanInt($body['precio'] ?? 0, 100, 9999900);
    if (!$nombre || $precio <= 0) respond(['error'=>'Nombre y precio requeridos'], 422);
    $prods = rjson($D.'productos.json');
    $nid   = $prods ? max(array_column($prods,'id')) + 1 : 1;
    $nuevo = ['id'=>$nid,'nombre'=>$nombre,'precio'=>$precio,
              'categoria'=>$body['categoria']??'pollo',
              'descripcion'=>san($body['descripcion']??'',200),
              'activo'=>true,'emoji'=>mb_substr($body['emoji']??'🍗',0,2),'tag'=>null];
    $prods[] = $nuevo;
    wjson($D.'productos.json', $prods);
    respond(['success'=>true,'producto'=>$nuevo]);
}

if ($method === 'GET' && $action === 'export_csv') {
    reqAuth();
    $pedidos = rjson($D.'pedidos.json');
    $desde = $_GET['desde']??''; $hasta=$_GET['hasta']??'';
    if ($desde) $pedidos = array_filter($pedidos, fn($p)=>substr($p['fecha'],0,10)>=$desde);
    if ($hasta) $pedidos = array_filter($pedidos, fn($p)=>substr($p['fecha'],0,10)<=$hasta);
    while (ob_get_level()>0) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pedidos_'.date('Y-m-d').'.csv"');
    $out = fopen('php://output','w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out,['ID','Fecha','Cliente','Teléfono','Dirección','Productos','Total','Estado']);
    foreach ($pedidos as $p) {
        fputcsv($out,[$p['id'],substr($p['fecha'],0,16),$p['cliente']??'',$p['telefono']??'',
            $p['direccion']??'',implode(' | ',array_map(fn($i)=>$i['nombre'].' x'.$i['qty'],$p['productos']??[])),
            '$'.number_format($p['total']??0,0,',','.'),$p['estado']??'']);
    }
    fclose($out); exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// MÓDULO 01 — CAJA
// ══════════════════════════════════════════════════════════════════════════════

function calcTotales($txs) {
    $t = ['efectivo'=>0,'tarjeta'=>0,'nequi'=>0,'breb'=>0,'total_ingresos'=>0,'total_egresos'=>0,'neto'=>0];
    foreach ($txs as $tx) {
        $m = $tx['metodo'] ?? ''; $v = intval($tx['monto'] ?? 0);
        if ($tx['tipo'] === 'ingreso') { $t['total_ingresos'] += $v; if (isset($t[$m])) $t[$m] += $v; }
        else $t['total_egresos'] += $v;
    }
    $t['neto'] = $t['total_ingresos'] - $t['total_egresos'];
    return $t;
}

function turnoAbierto($turnos) {
    $hoy = date('Y-m-d');
    foreach ($turnos as $i => $t) {
        if ($t['estado'] === 'abierto' && $t['fecha'] === $hoy) return $i;
    }
    return -1;
}

if ($method === 'GET' && $action === 'get_turno') {
    reqAuth();
    $caja = rjson($D.'caja.json');
    $turnos = $caja['turnos'] ?? [];
    $idx  = turnoAbierto($turnos);
    if ($idx === -1) respond(['success'=>true,'tiene_turno_abierto'=>false,'turno'=>null]);
    $turnos[$idx]['totales'] = calcTotales($turnos[$idx]['transacciones'] ?? []);
    respond(['success'=>true,'tiene_turno_abierto'=>true,'turno'=>$turnos[$idx]]);
}

if ($method === 'POST' && $action === 'abrir_turno') {
    reqAuth(); verifyCsrf();
    $caja   = rjson($D.'caja.json');
    $turnos = $caja['turnos'] ?? [];
    $hoy    = date('Y-m-d');
    if (turnoAbierto($turnos) !== -1) respond(['error'=>'Ya hay un turno abierto hoy'],409);
    $turno  = ['id'=>'turno_'.$hoy.'_'.substr(uniqid(),-4),'fecha'=>$hoy,'apertura'=>date('H:i'),
               'cierre'=>null,'estado'=>'abierto',
               'base_caja'=>sanInt($body['base_caja']??0,0,10000000),'transacciones'=>[],
               'totales'=>['efectivo'=>0,'tarjeta'=>0,'nequi'=>0,'breb'=>0,'total_ingresos'=>0,'total_egresos'=>0,'neto'=>0]];
    array_unshift($turnos, $turno);
    wjson($D.'caja.json', ['turnos'=>$turnos]);
    respond(['success'=>true,'turno'=>$turno]);
}

if ($method === 'POST' && $action === 'cerrar_turno') {
    reqAuth(); verifyCsrf();
    $caja   = rjson($D.'caja.json');
    $turnos = $caja['turnos'] ?? [];
    $idx    = turnoAbierto($turnos);
    if ($idx === -1) respond(['error'=>'No hay turno abierto'],404);
    $turnos[$idx]['estado']  = 'cerrado';
    $turnos[$idx]['cierre']  = date('H:i');
    $turnos[$idx]['totales'] = calcTotales($turnos[$idx]['transacciones'] ?? []);
    wjson($D.'caja.json', ['turnos'=>$turnos]);
    respond(['success'=>true,'turno'=>$turnos[$idx]]);
}

if ($method === 'POST' && $action === 'registrar_transaccion') {
    reqAuth(); verifyCsrf();
    $caja   = rjson($D.'caja.json');
    $turnos = $caja['turnos'] ?? [];
    $idx    = turnoAbierto($turnos);
    if ($idx === -1) respond(['error'=>'No hay turno abierto'],404);
    $concepto = san($body['concepto']??'',200);
    $monto    = sanInt($body['monto']??0,1,99999999);
    $metodo   = $body['metodo'] ?? 'efectivo';
    $tipo     = $body['tipo']   ?? 'ingreso';
    if (!$concepto) respond(['error'=>'Concepto requerido'],422);
    if (!in_array($metodo,['efectivo','tarjeta','nequi','breb'])) respond(['error'=>'Método inválido'],422);
    if (!in_array($tipo,['ingreso','egreso'])) respond(['error'=>'Tipo inválido'],422);
    $tx = ['id'=>'tx_'.time().'_'.substr(uniqid(),-4),'hora'=>date('H:i'),'fecha'=>date('Y-m-d'),
           'concepto'=>$concepto,'metodo'=>$metodo,'monto'=>$monto,'tipo'=>$tipo,
           'referencia'=>san($body['referencia']??'',100),'pedido_id'=>$body['pedido_id']??null];
    $turnos[$idx]['transacciones'][] = $tx;
    $turnos[$idx]['totales'] = calcTotales($turnos[$idx]['transacciones']);
    wjson($D.'caja.json', ['turnos'=>$turnos]);
    respond(['success'=>true,'transaccion'=>$tx,'totales'=>$turnos[$idx]['totales']]);
}

if ($method === 'GET' && $action === 'get_historial_caja') {
    reqAuth();
    $caja    = rjson($D.'caja.json');
    $turnos  = $caja['turnos'] ?? [];
    $cerrados = array_values(array_filter($turnos, fn($t)=>$t['estado']==='cerrado'));
    respond(['success'=>true,'turnos'=>array_slice($cerrados,0,intval($_GET['limit']??7))]);
}

if ($method === 'GET' && $action === 'export_caja_csv') {
    reqAuth();
    $caja   = rjson($D.'caja.json');
    $turnos = $caja['turnos'] ?? [];
    $desde  = $_GET['desde']??''; $hasta=$_GET['hasta']??'';
    while (ob_get_level()>0) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="caja_'.date('Y-m-d').'.csv"');
    $out = fopen('php://output','w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out,['Turno','Fecha','Hora','Concepto','Método','Tipo','Monto','Referencia']);
    foreach ($turnos as $turno) {
        $f = $turno['fecha']??'';
        if ($desde && $f<$desde) continue;
        if ($hasta && $f>$hasta) continue;
        foreach ($turno['transacciones']??[] as $tx) {
            fputcsv($out,[$turno['id'],$tx['fecha']??$f,$tx['hora']??'',$tx['concepto']??'',
                $tx['metodo']??'',$tx['tipo']??'','$'.number_format($tx['monto']??0,0,',','.'),$tx['referencia']??'']);
        }
    }
    fclose($out); exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// MÓDULO 02 — ESTADÍSTICAS
// ══════════════════════════════════════════════════════════════════════════════

if ($method === 'GET' && $action === 'get_stats') {
    reqAuth();
    $periodo = $_GET['periodo'] ?? '7d';
    $hoy     = date('Y-m-d');
    $rangos  = ['hoy'=>[$hoy,$hoy],
                'semana'=>[date('Y-m-d',strtotime('monday this week')),$hoy],
                '7d'=>[date('Y-m-d',strtotime('-7 days')),$hoy],
                '30d'=>[date('Y-m-d',strtotime('-30 days')),$hoy]];
    [$desde,$hasta] = $rangos[$periodo] ?? $rangos['7d'];
    $pedidos = rjson($D.'pedidos.json');
    $pr = array_values(array_filter($pedidos, fn($p)=>substr($p['fecha'],0,10)>=$desde && substr($p['fecha'],0,10)<=$hasta));
    $tv = array_sum(array_column($pr,'total'));
    $tp = count($pr);
    $conteo = [];
    foreach ($pr as $p) {
        foreach ($p['productos']??[] as $item) {
            $n = $item['nombre']??'?';
            if (!isset($conteo[$n])) $conteo[$n]=['nombre'=>$n,'qty'=>0,'total'=>0];
            $conteo[$n]['qty']   += intval($item['qty']??1);
            $conteo[$n]['total'] += intval($item['qty']??1)*intval($item['precio']??0);
        }
    }
    usort($conteo,fn($a,$b)=>$b['qty']-$a['qty']);
    $dias_es = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    $vdia = []; $cur=strtotime($desde); $end=strtotime($hasta);
    while ($cur<=$end) {
        $f=$date=date('Y-m-d',$cur);
        $vdia[$f]=['fecha'=>$f,'dia'=>$dias_es[date('w',$cur)],'pedidos'=>0,'total'=>0,'es_descanso'=>date('w',$cur)==DIA_DESCANSO];
        $cur=strtotime('+1 day',$cur);
    }
    foreach ($pr as $p) { $f=substr($p['fecha'],0,10); if(isset($vdia[$f])){$vdia[$f]['pedidos']++;$vdia[$f]['total']+=intval($p['total']??0);} }
    $horas = array_fill(HORA_APERTURA, HORA_CIERRE-HORA_APERTURA, 0);
    foreach ($pr as $p) { $h=intval(substr($p['fecha']??'',11,2)); if(isset($horas[$h])) $horas[$h]++; }
    $hp = $horas ? array_keys($horas,max($horas))[0] : 12;
    $caja = rjson($D.'caja.json');
    $tm   = ['efectivo'=>0,'tarjeta'=>0,'nequi'=>0,'breb'=>0];
    foreach ($caja['turnos']??[] as $turno) {
        if(($turno['fecha']??'')<$desde || ($turno['fecha']??'')>$hasta) continue;
        foreach ($turno['transacciones']??[] as $tx) {
            if($tx['tipo']!=='ingreso') continue;
            $m=$tx['metodo']??''; if(isset($tm[$m])) $tm[$m]+=intval($tx['monto']??0);
        }
    }
    $total_m=array_sum($tm);
    $metodos=[]; $lbs=['efectivo'=>'Efectivo','tarjeta'=>'Tarjeta','nequi'=>'Nequi','breb'=>'BREB o Llave'];
    foreach ($tm as $m=>$v) $metodos[]=['metodo'=>$m,'label'=>$lbs[$m]??$m,'total'=>$v,'pct'=>$total_m>0?round($v/$total_m*100):0];
    $estados=['pendiente'=>0,'preparando'=>0,'entregado'=>0,'cancelado'=>0];
    foreach ($pr as $p) { $e=$p['estado']??'pendiente'; if(isset($estados[$e])) $estados[$e]++; }
    respond(['success'=>true,'periodo'=>$periodo,'rango'=>['desde'=>$desde,'hasta'=>$hasta],
        'dia_descanso'=>DIA_DESCANSO,'kpis'=>['total_ventas'=>$tv,'total_pedidos'=>$tp,
        'ticket_promedio'=>$tp>0?intval($tv/$tp):0,'plato_estrella'=>array_values($conteo)[0]['nombre']??'—'],
        'top_platos'=>array_slice(array_values($conteo),0,10),'ventas_dia'=>array_values($vdia),
        'horas'=>$horas,'hora_pico'=>$hp,'metodos_pago'=>$metodos,'estados_pedidos'=>$estados]);
}

// ══════════════════════════════════════════════════════════════════════════════
// MÓDULO 03 — GASTOS
// ══════════════════════════════════════════════════════════════════════════════

$CATS = ['insumos'=>['label'=>'Insumos / Materia prima','icon'=>'🥩'],
         'nomina'=>['label'=>'Nómina','icon'=>'👥'],'servicios'=>['label'=>'Servicios','icon'=>'💡'],
         'arriendo'=>['label'=>'Arriendo','icon'=>'🏠'],'mantenimiento'=>['label'=>'Mantenimiento','icon'=>'🔧'],
         'empaque'=>['label'=>'Empaque y domicilios','icon'=>'📦'],'publicidad'=>['label'=>'Publicidad','icon'=>'📣'],
         'otros'=>['label'=>'Otros','icon'=>'➕']];

if ($method === 'GET' && $action === 'get_categorias_gasto') {
    reqAuth();
    $cats=[]; foreach($CATS as $id=>$i) $cats[]=['id'=>$id,'label'=>$i['label'],'icon'=>$i['icon']];
    respond(['success'=>true,'categorias'=>$cats]);
}

if ($method === 'POST' && $action === 'registrar_gasto') {
    reqAuth(); verifyCsrf();
    $concepto=$san=$body['concepto']??''; $concepto=san($concepto,200);
    $monto=sanInt($body['monto']??0,1,99999999);
    $cat=$body['categoria']??'';
    if(!$concepto) respond(['error'=>'Concepto requerido'],422);
    if(!isset($CATS[$cat])) respond(['error'=>'Categoría inválida'],422);
    $data=rjson($D.'gastos.json'); $gastos=$data['gastos']??$data;
    if(!is_array($gastos)||isset($gastos['gastos'])) $gastos=[];
    $g=['id'=>'gasto_'.time().'_'.substr(uniqid(),-4),'fecha'=>date('Y-m-d'),'hora'=>date('H:i'),
        'concepto'=>$concepto,'categoria'=>$cat,'monto'=>$monto,'nota'=>san($body['nota']??'',300)];
    array_unshift($gastos,$g);
    wjson($D.'gastos.json',['gastos'=>$gastos]);
    respond(['success'=>true,'gasto'=>$g]);
}

if ($method === 'GET' && $action === 'get_gastos') {
    reqAuth();
    $data=rjson($D.'gastos.json'); $gastos=$data['gastos']??$data;
    if(!is_array($gastos)||isset($gastos['gastos'])) $gastos=[];
    $desde=$_GET['desde']??''; $hasta=$_GET['hasta']??''; $cat=$_GET['categoria']??'';
    if($desde) $gastos=array_values(array_filter($gastos,fn($g)=>($g['fecha']??'')>=$desde));
    if($hasta) $gastos=array_values(array_filter($gastos,fn($g)=>($g['fecha']??'')<=$hasta));
    if($cat)   $gastos=array_values(array_filter($gastos,fn($g)=>($g['categoria']??'')===$cat));
    $pc=[];
    foreach($CATS as $id=>$i){$t=array_sum(array_map(fn($g)=>($g['categoria']??'')===$id?($g['monto']??0):0,$gastos));$pc[]=['id'=>$id,'label'=>$i['label'],'icon'=>$i['icon'],'total'=>$t];}
    usort($pc,fn($a,$b)=>$b['total']-$a['total']);
    respond(['success'=>true,'gastos'=>$gastos,'total'=>array_sum(array_column($gastos,'monto')),'por_categoria'=>$pc]);
}

if ($method === 'POST' && $action === 'eliminar_gasto') {
    reqAuth(); verifyCsrf();
    $id=san($body['id']??'',50);
    $data=rjson($D.'gastos.json'); $gastos=$data['gastos']??$data;
    if(!is_array($gastos)||isset($gastos['gastos'])) $gastos=[];
    $orig=count($gastos);
    $gastos=array_values(array_filter($gastos,fn($g)=>($g['id']??'')!==$id));
    if(count($gastos)===$orig) respond(['error'=>'No encontrado'],404);
    wjson($D.'gastos.json',['gastos'=>$gastos]);
    respond(['success'=>true]);
}

if ($method === 'GET' && $action === 'get_balance') {
    reqAuth();
    $desde=$_GET['desde']??date('Y-m-d'); $hasta=$_GET['hasta']??date('Y-m-d');
    $caja=rjson($D.'caja.json'); $ing=0; $eg=0;
    foreach($caja['turnos']??[] as $t){
        if(($t['fecha']??'')<$desde||($t['fecha']??'')>$hasta) continue;
        $ing+=intval($t['totales']['total_ingresos']??0);
        $eg+=intval($t['totales']['total_egresos']??0);
    }
    $dg=rjson($D.'gastos.json'); $gs=$dg['gastos']??$dg;
    if(!is_array($gs)||isset($gs['gastos'])) $gs=[];
    $tg=array_sum(array_map(fn($g)=>($g['fecha']??'')>=$desde&&($g['fecha']??'')<=$hasta?($g['monto']??0):0,$gs));
    $u=$ing-$tg-$eg;
    respond(['success'=>true,'desde'=>$desde,'hasta'=>$hasta,'ingresos'=>$ing,'egresos_caja'=>$eg,
        'gastos'=>$tg,'utilidad'=>$u,'margen'=>$ing>0?round($u/$ing*100,1):0]);
}

if ($method === 'GET' && $action === 'export_gastos_csv') {
    reqAuth();
    $data=rjson($D.'gastos.json'); $gastos=$data['gastos']??$data;
    $desde=$_GET['desde']??''; $hasta=$_GET['hasta']??'';
    if($desde) $gastos=array_filter($gastos,fn($g)=>($g['fecha']??'')>=$desde);
    if($hasta) $gastos=array_filter($gastos,fn($g)=>($g['fecha']??'')<=$hasta);
    while(ob_get_level()>0) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="gastos_'.date('Y-m-d').'.csv"');
    $out=fopen('php://output','w'); fprintf($out,chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out,['ID','Fecha','Hora','Concepto','Categoría','Monto','Nota']);
    foreach($gastos as $g){$cat=$CATS[$g['categoria']??'']??['label'=>$g['categoria']??''];fputcsv($out,[$g['id']??'',$g['fecha']??'',$g['hora']??'',$g['concepto']??'',$cat['label'],'$'.number_format($g['monto']??0,0,',','.'),$g['nota']??'']);}
    fclose($out); exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// MÓDULO 04 — PROMOS
// ══════════════════════════════════════════════════════════════════════════════

function esVigente($p) {
    if (empty($p['activa'])) return false;
    $hoy = date('Y-m-d');
    if (!empty($p['fecha_inicio']) && $hoy < $p['fecha_inicio']) return false;
    if (!empty($p['fecha_fin'])    && $hoy > $p['fecha_fin'])    return false;
    return true;
}

if ($method === 'GET' && $action === 'get_promos') {
    reqAuth();
    $data=rjson($D.'promos.json'); $ps=$data['promos']??$data;
    if(!is_array($ps)||isset($ps['promos'])) $ps=[];
    $ps=array_map(fn($p)=>array_merge($p,['vigente'=>esVigente($p)]),$ps);
    respond(['success'=>true,'promos'=>$ps]);
}

if ($method === 'POST' && $action === 'crear_promo') {
    reqAuth(); verifyCsrf();
    $nombre=san($body['nombre']??'',100); $tipo=$body['tipo']??''; $valor=floatval($body['valor']??0);
    if(!$nombre) respond(['error'=>'Nombre requerido'],422);
    if(!in_array($tipo,['porcentaje','valor_fijo'])) respond(['error'=>'Tipo inválido'],422);
    if($valor<=0) respond(['error'=>'Valor debe ser > 0'],422);
    $data=rjson($D.'promos.json'); $ps=$data['promos']??[];
    $p=['id'=>'promo_'.time().'_'.substr(uniqid(),-4),'nombre'=>$nombre,'descripcion'=>san($body['descripcion']??'',200),
        'tipo'=>$tipo,'valor'=>$valor,'aplica_a'=>$body['aplica_a']??'todos','activa'=>true,
        'fecha_inicio'=>$body['fecha_inicio']??'','fecha_fin'=>$body['fecha_fin']??'','creada_en'=>date('c'),'usos'=>0];
    array_unshift($ps,$p);
    wjson($D.'promos.json',['promos'=>$ps]);
    respond(['success'=>true,'promo'=>$p]);
}

if ($method === 'POST' && $action === 'toggle_promo') {
    reqAuth(); verifyCsrf();
    $id=san($body['id']??'',50);
    $data=rjson($D.'promos.json'); $ps=$data['promos']??[];
    foreach($ps as &$p){if($p['id']===$id){$p['activa']=!($p['activa']??false);break;}}
    wjson($D.'promos.json',['promos'=>$ps]);
    respond(['success'=>true]);
}

if ($method === 'POST' && $action === 'eliminar_promo') {
    reqAuth(); verifyCsrf();
    $id=san($body['id']??'',50);
    $data=rjson($D.'promos.json'); $ps=$data['promos']??[];
    $orig=count($ps); $ps=array_values(array_filter($ps,fn($p)=>$p['id']!==$id));
    if(count($ps)===$orig) respond(['error'=>'No encontrada'],404);
    wjson($D.'promos.json',['promos'=>$ps]);
    respond(['success'=>true]);
}

// ══════════════════════════════════════════════════════════════════════════════
// MÓDULO 05 — INVENTARIO
// ══════════════════════════════════════════════════════════════════════════════

function estStock($i){$s=$i['stock_actual']??0;$m=$i['stock_minimo']??0;$c=$i['stock_critico']??0;if($s<=0)return 'agotado';if($s<=$c)return 'critico';if($s<=$m)return 'bajo';return 'ok';}

if ($method === 'GET' && $action === 'get_inventario') {
    reqAuth();
    $data=rjson($D.'inventario.json'); $items=$data['items']??$data;
    if(!is_array($items)||isset($items['items'])) $items=[];
    $items=array_map(fn($i)=>array_merge($i,['estado'=>estStock($i)]),$items);
    $cat=$_GET['categoria']??''; $alrt=$_GET['alertas']??'';
    if($cat)  $items=array_values(array_filter($items,fn($i)=>($i['categoria']??'')===$cat));
    if($alrt) $items=array_values(array_filter($items,fn($i)=>$i['estado']!=='ok'));
    $r=['total'=>count($data['items']??[]),'ok'=>0,'bajo'=>0,'critico'=>0,'agotado'=>0];
    foreach($data['items']??[] as $i) $r[estStock($i)]++;
    respond(['success'=>true,'items'=>$items,'resumen'=>$r]);
}

if ($method === 'POST' && $action === 'crear_item_inventario') {
    reqAuth(); verifyCsrf();
    $nombre=san($body['nombre']??'',100);
    if(!$nombre) respond(['error'=>'Nombre requerido'],422);
    $data=rjson($D.'inventario.json'); $items=$data['items']??[];
    if(!is_array($items)||isset($items['items'])) $items=[];
    $stock=floatval($body['stock_actual']??0);
    $item=['id'=>'inv_'.time().'_'.substr(uniqid(),-4),'nombre'=>$nombre,'categoria'=>san($body['categoria']??'',50),
           'unidad'=>$body['unidad']??'und','stock_actual'=>$stock,'stock_minimo'=>floatval($body['stock_minimo']??0),
           'stock_critico'=>floatval($body['stock_critico']??0),'proveedor'=>san($body['proveedor']??'',100),
           'costo_unidad'=>intval($body['costo_unidad']??0),'creado_en'=>date('c'),
           'movimientos'=>$stock>0?[['id'=>'mov_init','fecha'=>date('Y-m-d'),'hora'=>date('H:i'),'tipo'=>'entrada','cantidad'=>$stock,'nota'=>'Stock inicial']]:[]];
    array_unshift($items,$item);
    wjson($D.'inventario.json',['items'=>$items]);
    respond(['success'=>true,'item'=>$item]);
}

if ($method === 'POST' && $action === 'movimiento_inventario') {
    reqAuth(); verifyCsrf();
    $id=san($body['id']??'',50); $tipo=$body['tipo']??''; $cant=floatval($body['cantidad']??0);
    if(!in_array($tipo,['entrada','salida','ajuste'])) respond(['error'=>'Tipo inválido'],422);
    if($cant<=0) respond(['error'=>'Cantidad > 0 requerida'],422);
    $data=rjson($D.'inventario.json'); $items=$data['items']??[];
    if(!is_array($items)||isset($items['items'])) $items=[];
    $found=false;
    foreach($items as &$item){
        if($item['id']!==$id) continue;
        $prev=floatval($item['stock_actual']??0);
        if($tipo==='entrada') $item['stock_actual']=$prev+$cant;
        elseif($tipo==='salida'){if($cant>$prev) respond(['error'=>'Stock insuficiente'],422);$item['stock_actual']=$prev-$cant;}
        else $item['stock_actual']=$cant;
        $mov=['id'=>'mov_'.time().'_'.substr(uniqid(),-4),'fecha'=>date('Y-m-d'),'hora'=>date('H:i'),
              'tipo'=>$tipo,'cantidad'=>$cant,'stock_anterior'=>$prev,'stock_nuevo'=>$item['stock_actual'],'nota'=>san($body['nota']??'',200)];
        if(!isset($item['movimientos'])) $item['movimientos']=[];
        array_unshift($item['movimientos'],$mov);
        $item['movimientos']=array_slice($item['movimientos'],0,50);
        $found=true;
        wjson($D.'inventario.json',['items'=>$items]);
        respond(['success'=>true,'movimiento'=>$mov,'stock_actual'=>$item['stock_actual'],'estado'=>estStock($item)]);
    }
    if(!$found) respond(['error'=>'Item no encontrado'],404);
}

if ($method === 'POST' && $action === 'eliminar_item_inventario') {
    reqAuth(); verifyCsrf();
    $id=san($body['id']??'',50);
    $data=rjson($D.'inventario.json'); $items=$data['items']??[];
    if(!is_array($items)||isset($items['items'])) $items=[];
    $orig=count($items); $items=array_values(array_filter($items,fn($i)=>($i['id']??'')!==$id));
    if(count($items)===$orig) respond(['error'=>'No encontrado'],404);
    wjson($D.'inventario.json',['items'=>$items]);
    respond(['success'=>true]);
}

if ($method === 'GET' && $action === 'get_alertas_inventario') {
    reqAuth();
    $data=rjson($D.'inventario.json'); $items=$data['items']??[];
    if(!is_array($items)||isset($items['items'])) $items=[];
    $alrt=array_values(array_filter($items,fn($i)=>estStock($i)!=='ok'));
    $alrt=array_map(function($i){$c=$i;$c['estado']=estStock($i);unset($c['movimientos']);return $c;},$alrt);
    respond(['success'=>true,'alertas'=>$alrt,'total'=>count($alrt)]);
}

// ══════════════════════════════════════════════════════════════════════════════
// MÓDULO 06 — CLIENTES
// ══════════════════════════════════════════════════════════════════════════════

if ($method === 'GET' && $action === 'get_clientes') {
    reqAuth();
    $pedidos=rjson($D.'pedidos.json');
    $data=rjson($D.'clientes.json'); $all=$data['clientes']??$data;
    if(!is_array($all)||isset($all['clientes'])) $all=[];
    $map=[];
    foreach($all as $c) $map[$c['telefono']??'']=$c;
    foreach($pedidos as $p){
        $tel=$p['telefono']??''; if(!$tel) continue;
        if(!isset($map[$tel])) $map[$tel]=['id'=>'cli_'.md5($tel),'nombre'=>$p['cliente']??'','telefono'=>$tel,
            'direccion'=>$p['direccion']??'','primer_pedido'=>substr($p['fecha']??'',0,10),
            'ultimo_pedido'=>substr($p['fecha']??'',0,10),'total_pedidos'=>0,'total_gastado'=>0,'pedidos_ids'=>[],'nota'=>'','etiqueta'=>''];
        $c=&$map[$tel];
        if(!in_array($p['id'],$c['pedidos_ids']??[])){
            $c['pedidos_ids'][]=$p['id'];$c['total_pedidos']++;$c['total_gastado']+=intval($p['total']??0);
            $fp=substr($p['fecha']??'',0,10);
            if($fp>($c['ultimo_pedido']??'')){$c['ultimo_pedido']=$fp;$c['nombre']=$p['cliente']??$c['nombre'];if(!empty($p['direccion']))$c['direccion']=$p['direccion'];}
        }
        unset($c);
    }
    usort($map,fn($a,$b)=>$b['total_pedidos']-$a['total_pedidos']);
    $all=array_values($map);
    wjson($D.'clientes.json',['clientes'=>$all]);
    $q=strtolower($_GET['q']??''); $etq=$_GET['etiqueta']??'';
    $res=$all;
    if($q)   $res=array_values(array_filter($res,fn($c)=>str_contains(strtolower($c['nombre']??''),$q)||str_contains($c['telefono']??'',$q)));
    if($etq) $res=array_values(array_filter($res,fn($c)=>($c['etiqueta']??'')===$etq));
    $tg=array_sum(array_column($all,'total_gastado')); $tp2=array_sum(array_column($all,'total_pedidos'));
    respond(['success'=>true,'clientes'=>$res,'stats'=>['total_clientes'=>count($all),'total_gastado'=>$tg,
        'ticket_promedio'=>$tp2>0?intval($tg/$tp2):0,'recurrentes'=>count(array_filter($all,fn($c)=>$c['total_pedidos']>1))]]);
}

if ($method === 'POST' && $action === 'actualizar_cliente') {
    reqAuth(); verifyCsrf();
    $tel=san($body['telefono']??'',20);
    $data=rjson($D.'clientes.json'); $all=$data['clientes']??$data;
    if(!is_array($all)||isset($all['clientes'])) $all=[];
    $found=false;
    foreach($all as &$c){if(($c['telefono']??'')===$tel){$c['nota']=san($body['nota']??'',300);$c['etiqueta']=san($body['etiqueta']??'',50);$found=true;break;}}
    if(!$found) respond(['error'=>'No encontrado'],404);
    wjson($D.'clientes.json',['clientes'=>$all]);
    respond(['success'=>true]);
}

if ($method === 'GET' && $action === 'get_cliente_detalle') {
    reqAuth();
    $tel=san($_GET['tel']??'',20);
    $data=rjson($D.'clientes.json'); $all=$data['clientes']??$data;
    if(!is_array($all)||isset($all['clientes'])) $all=[];
    $cli=null; foreach($all as $c){if(($c['telefono']??'')===$tel){$cli=$c;break;}}
    if(!$cli) respond(['error'=>'No encontrado'],404);
    $pedidos=rjson($D.'pedidos.json');
    $mios=array_values(array_filter($pedidos,fn($p)=>($p['telefono']??'')===$tel));
    usort($mios,fn($a,$b)=>strcmp($b['fecha']??'',$a['fecha']??''));
    respond(['success'=>true,'cliente'=>$cli,'pedidos'=>$mios]);
}

// ══════════════════════════════════════════════════════════════════════════════
// MÓDULO 07 — EMPLEADOS
// ══════════════════════════════════════════════════════════════════════════════

$CARGOS=['cajero'=>'Cajero/a','cocinero'=>'Cocinero/a','mesero'=>'Mesero/a',
         'domicilio'=>'Domiciliario/a','limpieza'=>'Limpieza','admin'=>'Administrador/a','otro'=>'Otro'];

if ($method === 'GET' && $action === 'get_empleados') {
    reqAuth();
    $data=rjson($D.'empleados.json'); $emps=$data['empleados']??$data;
    if(!is_array($emps)||isset($emps['empleados'])) $emps=[];
    $emps=array_map(function($e){$c=$e;unset($c['salario']);return $c;},$emps);
    respond(['success'=>true,'empleados'=>$emps]);
}

if ($method === 'POST' && $action === 'crear_empleado') {
    reqAuth(); verifyCsrf();
    $nombre=san($body['nombre']??'',100); $cargo=$body['cargo']??'otro';
    if(!$nombre) respond(['error'=>'Nombre requerido'],422);
    $data=rjson($D.'empleados.json'); $emps=$data['empleados']??[];
    if(!is_array($emps)||isset($emps['empleados'])) $emps=[];
    $cedula=san($body['cedula']??'',20);
    if($cedula){foreach($emps as $e){if(($e['cedula']??'')===$cedula) respond(['error'=>'Cédula duplicada'],409);}}
    $emp=['id'=>'emp_'.time().'_'.substr(uniqid(),-4),'nombre'=>$nombre,'cargo'=>$cargo,
          'telefono'=>san($body['telefono']??'',20),'cedula'=>$cedula,
          'salario'=>sanInt($body['salario']??0,0,99999999),'horario_dias'=>$body['horario']??[],
          'hora_entrada'=>san($body['hora_entrada']??'08:00',5),'hora_salida'=>san($body['hora_salida']??'17:00',5),
          'activo'=>true,'creado_en'=>date('c')];
    array_unshift($emps,$emp);
    wjson($D.'empleados.json',['empleados'=>$emps]);
    unset($emp['salario']);
    respond(['success'=>true,'empleado'=>$emp]);
}

if ($method === 'POST' && $action === 'toggle_empleado') {
    reqAuth(); verifyCsrf();
    $id=san($body['id']??'',50);
    $data=rjson($D.'empleados.json'); $emps=$data['empleados']??[];
    if(!is_array($emps)||isset($emps['empleados'])) $emps=[];
    foreach($emps as &$e){if($e['id']===$id){$e['activo']=!($e['activo']??true);break;}}
    wjson($D.'empleados.json',['empleados'=>$emps]);
    respond(['success'=>true]);
}

if ($method === 'POST' && $action === 'registrar_asistencia') {
    reqAuth(); verifyCsrf();
    $eid=san($body['empleado_id']??'',50); $tipo=$body['tipo']??'';
    if(!in_array($tipo,['entrada','salida'])) respond(['error'=>'Tipo inválido'],422);
    $data=rjson($D.'empleados.json'); $emps=$data['empleados']??[];
    if(!is_array($emps)||isset($emps['empleados'])) $emps=[];
    $ok=false; foreach($emps as $e){if(($e['id']??'')===$eid&&!empty($e['activo'])){$ok=true;break;}}
    if(!$ok) respond(['error'=>'Empleado no encontrado'],404);
    $adata=rjson($D.'asistencias.json'); $asis=$adata['asistencias']??$adata;
    if(!is_array($asis)||isset($asis['asistencias'])) $asis=[];
    $hoy=date('Y-m-d');
    foreach($asis as $a){if(($a['empleado_id']??'')===$eid&&($a['fecha']??'')===$hoy&&($a['tipo']??'')===$tipo) respond(['error'=>'Ya registrado hoy'],409);}
    $a=['id'=>'asis_'.time().'_'.substr(uniqid(),-4),'empleado_id'=>$eid,'fecha'=>$hoy,'hora'=>date('H:i'),'tipo'=>$tipo,'nota'=>san($body['nota']??'',200)];
    array_unshift($asis,$a);
    $asis=array_slice($asis,0,1000);
    wjson($D.'asistencias.json',['asistencias'=>$asis]);
    respond(['success'=>true,'asistencia'=>$a]);
}

if ($method === 'GET' && $action === 'get_turno_hoy') {
    reqAuth();
    $hoy=date('Y-m-d');
    $data=rjson($D.'empleados.json'); $emps=$data['empleados']??$data;
    if(!is_array($emps)||isset($emps['empleados'])) $emps=[];
    $adata=rjson($D.'asistencias.json'); $asis=$adata['asistencias']??$adata;
    if(!is_array($asis)||isset($asis['asistencias'])) $asis=[];
    $ah=[];
    foreach($asis as $a){if(($a['fecha']??'')!==$hoy) continue;$ah[$a['empleado_id']??''][$a['tipo']??'']=$a['hora']??'';}
    $res=[];
    foreach($emps as $e){
        if(empty($e['activo'])) continue;
        $a=$ah[$e['id']??'']??[];
        $res[]=['id'=>$e['id']??'','nombre'=>$e['nombre']??'','cargo'=>$CARGOS[$e['cargo']??'']??($e['cargo']??''),
                'entrada'=>$a['entrada']??null,'salida'=>$a['salida']??null,
                'estado'=>isset($a['salida'])?'completo':(isset($a['entrada'])?'en_turno':'ausente')];
    }
    respond(['success'=>true,'fecha'=>$hoy,'empleados'=>$res]);
}

if ($method === 'GET' && $action === 'get_asistencias') {
    reqAuth();
    $desde=$_GET['desde']??date('Y-m-d',strtotime('-7 days')); $hasta=$_GET['hasta']??date('Y-m-d'); $eid=$_GET['empleado_id']??'';
    $adata=rjson($D.'asistencias.json'); $asis=$adata['asistencias']??$adata;
    if(!is_array($asis)||isset($asis['asistencias'])) $asis=[];
    $res=array_values(array_filter($asis,fn($a)=>($a['fecha']??'')>=$desde&&($a['fecha']??'')<=$hasta&&(!$eid||($a['empleado_id']??'')===$eid)));
    respond(['success'=>true,'asistencias'=>$res]);
}

// ══════════════════════════════════════════════════════════════════════════════
// MÓDULO 08 — MESAS Y RESERVAS
// ══════════════════════════════════════════════════════════════════════════════

if ($method === 'GET' && $action === 'get_mesas') {
    reqMesero();
    $data=rjson($D.'mesas.json'); $mesas=$data['mesas']??$data;
    if(!is_array($mesas)||isset($mesas['mesas'])) $mesas=[];
    $rdata=rjson($D.'reservas.json'); $res=$rdata['reservas']??$rdata;
    if(!is_array($res)||isset($res['reservas'])) $res=[];
    $hoy=date('Y-m-d'); $ahora=date('H:i');
    $mesas=array_map(function($m) use($res,$hoy,$ahora){
        $ra=null;
        foreach($res as $r){
            if(($r['mesa_id']??'')!==($m['id']??'')||($r['fecha']??'')!==$hoy||($r['estado']??'')==='cancelada') continue;
            $ts_r=strtotime("$hoy ".($r['hora']??'')); $ts_n=strtotime("$hoy $ahora");
            if($ts_r<=$ts_n+3600&&$ts_r>=$ts_n-7200){$ra=$r;break;}
        }
        return array_merge($m,['reserva_activa'=>$ra]);
    },$mesas);
    $zonas=[];
    foreach($mesas as $m){$z=$m['zona']??'salon';if(!isset($zonas[$z]))$zonas[$z]=['disponibles'=>0,'ocupadas'=>0,'total'=>0];$zonas[$z]['total']++;if(($m['estado']??'')===('disponible'))$zonas[$z]['disponibles']++;else $zonas[$z]['ocupadas']++;}
    respond(['success'=>true,'mesas'=>$mesas,'zonas'=>$zonas]);
}

if ($method === 'POST' && $action === 'cambiar_estado_mesa') {
    reqMesero(); verifyCsrf();
    $id=san($body['id']??'',50); $estado=$body['estado']??'';
    if(!in_array($estado,['disponible','ocupada','reservada','mantenimiento'])) respond(['error'=>'Estado inválido'],422);
    $data=rjson($D.'mesas.json'); $mesas=$data['mesas']??[];
    if(!is_array($mesas)||isset($mesas['mesas'])) $mesas=[];
    $found=false;
    foreach($mesas as &$m){if(($m['id']??'')===$id){$m['estado']=$estado;$found=true;break;}}
    if(!$found) respond(['error'=>'Mesa no encontrada'],404);
    wjson($D.'mesas.json',['mesas'=>$mesas]);
    respond(['success'=>true]);
}

if ($method === 'POST' && $action === 'crear_mesa') {
    reqAuth(); verifyCsrf();
    $data=rjson($D.'mesas.json'); $mesas=$data['mesas']??[];
    if(!is_array($mesas)||isset($mesas['mesas'])) $mesas=[];
    $nums=array_column($mesas,'numero'); $n=$nums?max($nums)+1:1;
    $mesa=['id'=>'mesa_'.str_pad($n,2,'0',STR_PAD_LEFT),'numero'=>$n,'capacidad'=>sanInt($body['capacidad']??2,1,50),
           'zona'=>san($body['zona']??'salon',50),'estado'=>'disponible','activa'=>true];
    $mesas[]=$mesa;
    wjson($D.'mesas.json',['mesas'=>$mesas]);
    respond(['success'=>true,'mesa'=>$mesa]);
}

if ($method === 'GET' && $action === 'get_reservas') {
    reqAuth();
    $rdata=rjson($D.'reservas.json'); $res=$rdata['reservas']??$rdata;
    if(!is_array($res)||isset($res['reservas'])) $res=[];
    $fecha=$_GET['fecha']??date('Y-m-d'); $mid=$_GET['mesa_id']??'';
    $res=array_values(array_filter($res,fn($r)=>($r['fecha']??'')===$fecha&&(!$mid||($r['mesa_id']??'')===$mid)));
    usort($res,fn($a,$b)=>strcmp($a['hora']??'',$b['hora']??''));
    respond(['success'=>true,'reservas'=>$res,'fecha'=>$fecha]);
}

if ($method === 'POST' && $action === 'crear_reserva') {
    reqAuth(); verifyCsrf();
    $mid=san($body['mesa_id']??'',50); $fecha=$body['fecha']??''; $hora=$body['hora']??'';
    $nombre=san($body['nombre']??'',100); $personas=sanInt($body['personas']??1,1,50);
    if(!$mid||!$fecha||!$hora||!$nombre) respond(['error'=>'Campos requeridos'],422);
    if($fecha<date('Y-m-d')) respond(['error'=>'No se puede reservar en el pasado'],422);
    $mdata=rjson($D.'mesas.json'); $mesas=$mdata['mesas']??[];
    if(!is_array($mesas)||isset($mesas['mesas'])) $mesas=[];
    $mesa=null; foreach($mesas as $m){if(($m['id']??'')===$mid&&!empty($m['activa'])){$mesa=$m;break;}}
    if(!$mesa) respond(['error'=>'Mesa no encontrada'],404);
    if($personas>($mesa['capacidad']??99)) respond(['error'=>'Capacidad excedida'],422);
    $rdata=rjson($D.'reservas.json'); $res=$rdata['reservas']??[];
    if(!is_array($res)||isset($res['reservas'])) $res=[];
    $ts_n=strtotime("$fecha $hora");
    foreach($res as $r){
        if(($r['mesa_id']??'')!==$mid||($r['fecha']??'')!==$fecha||($r['estado']??'')==='cancelada') continue;
        if(abs($ts_n-strtotime("$fecha ".($r['hora']??'')))<7200) respond(['error'=>'Conflicto de horario'],409);
    }
    $r=['id'=>'res_'.time().'_'.substr(uniqid(),-4),'mesa_id'=>$mid,'mesa_num'=>$mesa['numero']??0,
        'fecha'=>$fecha,'hora'=>$hora,'nombre'=>$nombre,'telefono'=>san($body['telefono']??'',20),
        'personas'=>$personas,'nota'=>san($body['nota']??'',300),'estado'=>'confirmada','creada_en'=>date('c')];
    array_unshift($res,$r);
    wjson($D.'reservas.json',['reservas'=>$res]);
    if($fecha===date('Y-m-d')){foreach($mesas as &$m){if(($m['id']??'')===$mid){$m['estado']='reservada';break;}}wjson($D.'mesas.json',['mesas'=>$mesas]);}
    respond(['success'=>true,'reserva'=>$r]);
}

if ($method === 'POST' && $action === 'actualizar_reserva') {
    reqAuth(); verifyCsrf();
    $id=san($body['id']??'',50); $estado=$body['estado']??'';
    if(!in_array($estado,['confirmada','sentada','completada','cancelada'])) respond(['error'=>'Estado inválido'],422);
    $rdata=rjson($D.'reservas.json'); $res=$rdata['reservas']??[];
    if(!is_array($res)||isset($res['reservas'])) $res=[];
    $found=false;
    foreach($res as &$r){
        if(($r['id']??'')!==$id) continue;
        $r['estado']=$estado; $found=true;
        $mdata=rjson($D.'mesas.json'); $mesas=$mdata['mesas']??[];
        if(!is_array($mesas)||isset($mesas['mesas'])) $mesas=[];
        foreach($mesas as &$m){
            if(($m['id']??'')!==($r['mesa_id']??'')) continue;
            if($estado==='sentada') $m['estado']='ocupada';
            if(in_array($estado,['completada','cancelada'])) $m['estado']='disponible';
            break;
        }
        wjson($D.'mesas.json',['mesas'=>$mesas]); break;
    }
    if(!$found) respond(['error'=>'Reserva no encontrada'],404);
    wjson($D.'reservas.json',['reservas'=>$res]);
    respond(['success'=>true]);
}

// ══════════════════════════════════════════════════════════════════════════════
// MÓDULO CUENTAS ABIERTAS
// ══════════════════════════════════════════════════════════════════════════════

if ($method === 'GET' && $action === 'get_cuentas_abiertas') {
    reqAuth();
    $pedidos = rjson($D.'pedidos.json');
    $abiertas = array_values(array_filter($pedidos, fn($p) =>
        in_array($p['tipo'] ?? '', ['mesa','llevar','domicilio']) &&
        !in_array($p['estado'] ?? '', ['pagado', 'cancelado'])
    ));
    usort($abiertas, fn($a, $b) => strcmp($a['fecha'] ?? '', $b['fecha'] ?? ''));
    respond(['success' => true, 'cuentas' => $abiertas]);
}

if ($method === 'POST' && $action === 'cerrar_cuenta') {
    reqAuth(); verifyCsrf();
    $pedido_id = intval($body['pedido_id'] ?? 0);
    $metodo    = $body['metodo'] ?? 'efectivo';
    if (!in_array($metodo, ['efectivo','tarjeta','nequi','breb'])) respond(['error'=>'Método inválido'], 422);

    // Marcar pedido como pagado
    $pedidos = rjson($D.'pedidos.json');
    $pedido  = null;
    foreach ($pedidos as &$p) {
        if ($p['id'] !== $pedido_id) continue;
        $p['estado']    = 'pagado';
        $p['pagado_en'] = date('c');
        $p['metodo_pago'] = $metodo;
        $pedido = $p;
        break;
    }
    if (!$pedido) respond(['error' => 'Pedido no encontrado'], 404);
    wjson($D.'pedidos.json', $pedidos);

    // Liberar mesa
    $mdata = rjson($D.'mesas.json'); $mesas = $mdata['mesas'] ?? [];
    foreach ($mesas as &$m) {
        if (($m['id'] ?? '') === ($pedido['mesa_id'] ?? '')) {
            $m['estado'] = 'disponible'; break;
        }
    }
    wjson($D.'mesas.json', ['mesas' => $mesas]);

    // Registrar en caja si hay turno abierto
    $caja   = rjson($D.'caja.json');
    $turnos = $caja['turnos'] ?? [];
    $idx    = turnoAbierto($turnos);
    if ($idx !== -1) {
        $tx = ['id'=>'tx_'.time().'_'.substr(uniqid(),-4),'hora'=>date('H:i'),'fecha'=>date('Y-m-d'),
               'concepto'=>'Mesa '.($pedido['mesa_num']??'?').' — '.implode(', ', array_map(fn($i)=>$i['qty'].'x '.$i['nombre'], $pedido['productos']??[])),
               'metodo'=>$metodo,'monto'=>intval($pedido['total']??0),'tipo'=>'ingreso',
               'referencia'=>'','pedido_id'=>$pedido_id];
        $turnos[$idx]['transacciones'][] = $tx;
        $turnos[$idx]['totales'] = calcTotales($turnos[$idx]['transacciones']);
        wjson($D.'caja.json', ['turnos'=>$turnos]);
    }

    respond(['success' => true, 'en_caja' => $idx !== -1]);
}

// ══════════════════════════════════════════════════════════════════════════════
// FALLBACK — ningún endpoint coincidió
// ══════════════════════════════════════════════════════════════════════════════
respond(['error' => 'Endpoint no encontrado', 'action' => $action], 404);
 
