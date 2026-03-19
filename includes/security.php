<?php
/**
 * Red Rooster Restaurant — Security Layer
 * 
 * IMPORTANTE: php://input se lee UNA SOLA VEZ en api.php
 * Esta capa NO lee php://input — recibe el body ya parseado si lo necesita.
 */

require_once __DIR__ . '/config.php';

function apply_cors() {
    $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = [
        'https://redroosterestaurant.com',
        'https://www.redroosterestaurant.com',
        'http://redroosterestaurant.com',
        'http://www.redroosterestaurant.com',
        'http://localhost',
        'http://127.0.0.1',
    ];
    if (in_array($origin, $allowed, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    header('Access-Control-Allow-Credentials: true');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function set_security_headers() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

function start_secure_session() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name(SESSION_NAME);
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || ($_SERVER['SERVER_PORT'] ?? 80) == 443
             || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    if (!isset($_SESSION['_init'])) {
        session_regenerate_id(true);
        $_SESSION['_init'] = true;
    }
    if (isset($_SESSION['_last_active'])) {
        if ((time() - $_SESSION['_last_active']) > SESSION_LIFETIME) {
            session_unset();
            session_destroy();
            start_secure_session();
            return;
        }
    }
    $_SESSION['_last_active'] = time();
}

function is_auth() {
    return ($_SESSION['admin'] ?? false) === true;
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// NOTA: verify_csrf_token recibe el body ya parseado para no leer php://input dos veces
function verify_csrf_token($body = []) {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($body['_csrf'] ?? '');
    $saved = $_SESSION['csrf_token'] ?? '';
    if (empty($saved) || !hash_equals($saved, $token)) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF inválido']);
        exit;
    }
}

function check_rate_limit($action) {
    // Rate limiting simple por IP
    $ip      = md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $file    = sys_get_temp_dir() . '/rr_rate_' . $action . '_' . $ip . '.json';
    $window  = $action === 'login' ? RATE_LIMIT_LOGIN_WINDOW : RATE_LIMIT_WINDOW;
    $max     = $action === 'login' ? RATE_LIMIT_LOGIN : RATE_LIMIT_PEDIDOS;
    $now     = time();
    $data    = [];
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true) ?? [];
    }
    $data = array_filter($data, fn($t) => $t > $now - $window);
    if (count($data) >= $max) {
        http_response_code(429);
        echo json_encode(['error' => 'Demasiadas solicitudes. Intenta más tarde.']);
        exit;
    }
    $data[] = $now;
    file_put_contents($file, json_encode($data), LOCK_EX);
}

function sanitize_string($val, $max = 200) {
    return mb_substr(trim(strip_tags($val ?? '')), 0, $max);
}

function sanitize_int($val, $min = 0, $max = PHP_INT_MAX) {
    return max($min, min($max, intval($val)));
}

function sanitize_phone($val) {
    return preg_replace('/[^0-9+\-\s]/', '', $val ?? '');
}

function get_client_ip() {
    $keys = ['HTTP_CF_CONNECTING_IP','HTTP_X_REAL_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'];
    foreach ($keys as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = explode(',', $_SERVER[$k])[0];
            if (filter_var(trim($ip), FILTER_VALIDATE_IP)) return trim($ip);
        }
    }
    return '0.0.0.0';
}
