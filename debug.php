<?php
/**
 * Red Rooster — Debug temporal
 * IMPORTANTE: Eliminar este archivo después de verificar que todo funciona
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/security.php';

start_secure_session();

// Forzar sesión admin para diagnóstico
$_SESSION['admin'] = true;
$_SESSION['_last_active'] = time();
$_SESSION['csrf_token'] = bin2hex(random_bytes(16));

header('Content-Type: application/json');

$results = [];

// 1. PHP version
$results['php_version'] = PHP_VERSION;

// 2. Archivos data/
$data_files = ['caja','pedidos','productos','gastos','promos','inventario','clientes','empleados','asistencias','mesas','reservas'];
foreach ($data_files as $f) {
    $path = DATA_DIR . $f . '.json';
    $results['data'][$f] = [
        'exists'   => file_exists($path),
        'readable' => is_readable($path),
        'writable' => is_writable($path),
        'size'     => file_exists($path) ? filesize($path) : 0,
    ];
}

// 3. Includes
$results['includes'] = [
    'config'   => file_exists(__DIR__ . '/includes/config.php'),
    'security' => file_exists(__DIR__ . '/includes/security.php'),
];

// 4. Session + auth
$results['session_active'] = is_auth();
$results['csrf_token']     = generate_csrf_token();

// 5. Test write to caja.json
try {
    $caja = json_decode(file_get_contents(DATA_DIR . 'caja.json'), true) ?? ['turnos' => []];
    $results['caja_read'] = 'OK — ' . count($caja['turnos']) . ' turnos';
    $test_write = file_put_contents(DATA_DIR . 'caja.json', json_encode($caja, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    $results['caja_write'] = $test_write !== false ? 'OK' : 'FALLO';
} catch (Exception $e) {
    $results['caja_error'] = $e->getMessage();
}

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
