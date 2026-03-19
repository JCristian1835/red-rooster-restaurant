<?php
/**
 * Red Rooster Restaurant — Configuración central
 *
 * INSTRUCCIONES DE SEGURIDAD:
 * 1. Cambia ADMIN_USER y genera un nuevo hash con: php -r "echo password_hash('TU_PASS',PASSWORD_BCRYPT);"
 * 2. Genera SESSION_SECRET con: php -r "echo bin2hex(random_bytes(32));"
 * 3. Cambia ALLOWED_ORIGIN a tu dominio exacto.
 * 4. Nunca subas este archivo a GitHub ni compartas públicamente.
 */

// ── Administrador ──────────────────────────────────────────────────────────────
define('ADMIN_USER', 'redrooster');

// Hash bcrypt de la contraseña — NUNCA guardes la contraseña en texto plano.
// Para regenerar: php -r "echo password_hash('nueva_contrasena', PASSWORD_BCRYPT);"
define('ADMIN_PASS_HASH', '$2y$10$IhHQbZ/qx1VRXl2Ya/HEgOZCVTk1w2QZuibcJ8fjjwia7pzjkMgqa');
// La contraseña por defecto es: Abundance26!
// CAMBIA ESTO ANTES DE SUBIR AL SERVIDOR

// ── Sesión ─────────────────────────────────────────────────────────────────────
define('SESSION_NAME',    'rr_session');
define('SESSION_LIFETIME', 3600);       // 1 hora de inactividad → logout automático
define('SESSION_SECRET',  'CAMBIA_ESTE_SECRETO_ANTES_DE_SUBIR_usa_random_bytes_32');

// ── CORS ───────────────────────────────────────────────────────────────────────
define('ALLOWED_ORIGIN', 'https://redroosterestaurant.com');  // Solo tu dominio
// En local usa: 'http://localhost' o '*' solo para pruebas

// ── Rate limiting ──────────────────────────────────────────────────────────────
define('RATE_LIMIT_PEDIDOS',  10);   // max pedidos por IP por hora
define('RATE_LIMIT_LOGIN',     5);   // max intentos de login por IP por 15 min
define('RATE_LIMIT_WINDOW',  3600);  // ventana en segundos (1 hora)
define('RATE_LIMIT_LOGIN_WINDOW', 900); // ventana login (15 min)

// ── Archivos de datos ──────────────────────────────────────────────────────────
define('DATA_DIR', dirname(__DIR__) . '/data/');
define('PRODUCTOS_FILE', DATA_DIR . 'productos.json');
define('PEDIDOS_FILE',   DATA_DIR . 'pedidos.json');
define('RATE_FILE',      DATA_DIR . 'rate_limits.json');

// ── Precios: fuente de verdad (servidor, nunca cliente) ────────────────────────
// Estos precios son los OFICIALES. El frontend los lee vía /api.php?action=menu.
// Cualquier total enviado por el cliente es RECALCULADO aquí con estos valores.
// Para cambiar un precio: edita products.json desde el admin panel.
// Este mapa es un caché de arranque — se sobreescribe con productos.json en runtime.
define('PRICE_CACHE_TTL', 300); // segundos antes de refrescar desde JSON

// ── Teléfono WhatsApp ──────────────────────────────────────────────────────────
define('WA_NUMBER', '573043987213');

// ── PINs de acceso rápido ──────────────────────────────────────────────────────
define('MESERO_PIN', '1234');  // PIN para meseros — ¡cámbialo!
define('COCINA_PIN', '5678');  // PIN para pantalla de cocina — ¡cámbialo!

// ── Configuración del negocio ──────────────────────────────────────────────────
// Días de la semana: 0=Domingo, 1=Lunes, 2=Martes, 3=Miércoles,
//                   4=Jueves, 5=Viernes, 6=Sábado
// Para cambiar el día de descanso, edita el número aquí:
define('DIA_DESCANSO', 2); // 2 = Martes

// Métodos de pago activos (editar aquí para activar/desactivar)
define('METODOS_PAGO', json_encode([
    ['id' => 'efectivo',   'label' => 'Efectivo',      'icon' => '💵', 'activo' => true],
    ['id' => 'tarjeta',    'label' => 'Tarjeta',        'icon' => '💳', 'activo' => true],
    ['id' => 'nequi',      'label' => 'Nequi',          'icon' => '📱', 'activo' => true],
    ['id' => 'breb',       'label' => 'BREB o Llave',   'icon' => '🔑', 'activo' => true],
]));

// Horario de atención (para estadísticas de horas pico)
define('HORA_APERTURA', 10); // 10am
define('HORA_CIERRE',   22); // 10pm
