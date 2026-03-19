<?php
/**
 * Red Rooster Restaurant — Configuración central (EJEMPLO)
 *
 * Copia este archivo como config.php y rellena tus valores reales.
 * NUNCA subas config.php a GitHub.
 *
 * Para generar un hash de contraseña:
 *   php -r "echo password_hash('TU_PASS', PASSWORD_BCRYPT);"
 * Para generar SESSION_SECRET:
 *   php -r "echo bin2hex(random_bytes(32));"
 */

// ── Administrador ──────────────────────────────────────────────────────────────
define('ADMIN_USER',      'redrooster');
define('ADMIN_PASS_HASH', 'GENERA_TU_HASH_AQUI');

// ── Sesión ─────────────────────────────────────────────────────────────────────
define('SESSION_NAME',    'rr_session');
define('SESSION_LIFETIME', 3600);
define('SESSION_SECRET',  'GENERA_TU_SECRETO_AQUI');

// ── CORS ───────────────────────────────────────────────────────────────────────
define('ALLOWED_ORIGIN', 'https://TU_DOMINIO.com');

// ── Rate limiting ──────────────────────────────────────────────────────────────
define('RATE_LIMIT_PEDIDOS',  10);
define('RATE_LIMIT_LOGIN',     5);
define('RATE_LIMIT_WINDOW',  3600);
define('RATE_LIMIT_LOGIN_WINDOW', 900);

// ── Archivos de datos ──────────────────────────────────────────────────────────
define('DATA_DIR',       dirname(__DIR__) . '/data/');
define('PRODUCTOS_FILE', DATA_DIR . 'productos.json');
define('PEDIDOS_FILE',   DATA_DIR . 'pedidos.json');
define('RATE_FILE',      DATA_DIR . 'rate_limits.json');

define('PRICE_CACHE_TTL', 300);

// ── Teléfono WhatsApp ──────────────────────────────────────────────────────────
define('WA_NUMBER', '57XXXXXXXXXX');

// ── PINs de acceso rápido ──────────────────────────────────────────────────────
define('MESERO_PIN', '0000');  // PIN para meseros
define('COCINA_PIN', '0000');  // PIN para pantalla de cocina

// ── Configuración del negocio ──────────────────────────────────────────────────
define('DIA_DESCANSO', 2); // 0=Dom ... 6=Sáb
define('METODOS_PAGO', json_encode([
    ['id' => 'efectivo', 'label' => 'Efectivo',    'icon' => '💵', 'activo' => true],
    ['id' => 'tarjeta',  'label' => 'Tarjeta',     'icon' => '💳', 'activo' => true],
    ['id' => 'nequi',    'label' => 'Nequi',       'icon' => '📱', 'activo' => true],
    ['id' => 'breb',     'label' => 'BREB o Llave','icon' => '🔑', 'activo' => true],
]));
define('HORA_APERTURA', 10);
define('HORA_CIERRE',   22);
