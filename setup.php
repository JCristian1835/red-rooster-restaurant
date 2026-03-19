<?php
/**
 * Red Rooster Restaurant — Setup inicial
 * Corre este archivo UNA SOLA VEZ después de clonar el repo.
 * Luego bórralo o bloquéalo.
 *
 * Uso: php setup.php
 */

echo "\n🐓 RED ROOSTER — Setup inicial\n";
echo str_repeat('─', 40) . "\n\n";

$D = __DIR__ . '/data/';
$I = __DIR__ . '/includes/';

// 1. Crear carpeta data si no existe
if (!is_dir($D)) { mkdir($D, 0755, true); echo "✓ Carpeta data/ creada\n"; }
else echo "✓ Carpeta data/ existe\n";

// 2. Crear JSONs vacíos si no existen
$archivos = [
    'pedidos.json'     => '[]',
    'productos.json'   => '[]',
    'mesas.json'       => '{"mesas":[]}',
    'empleados.json'   => '{"empleados":[]}',
    'clientes.json'    => '{"clientes":[]}',
    'inventario.json'  => '{"items":[]}',
    'caja.json'        => '{"turnos":[]}',
    'gastos.json'      => '{"gastos":[]}',
    'reservas.json'    => '{"reservas":[]}',
    'promos.json'      => '{"promos":[]}',
    'asistencias.json' => '{"asistencias":[]}',
];

foreach ($archivos as $nombre => $vacio) {
    $path = $D . $nombre;
    if (!file_exists($path)) {
        file_put_contents($path, $vacio);
        echo "✓ data/$nombre creado\n";
    } else {
        echo "  data/$nombre ya existe, se respeta\n";
    }
}

// 3. Verificar config.php
if (!file_exists($I . 'config.php')) {
    echo "\n⚠️  FALTA includes/config.php\n";
    echo "   Copia includes/config.example.php → includes/config.php\n";
    echo "   y rellena tus valores.\n";
} else {
    echo "✓ includes/config.php existe\n";
}

// 4. Mesas de ejemplo
$mesasPath = $D . 'mesas.json';
$mesas = json_decode(file_get_contents($mesasPath), true);
if (empty($mesas['mesas'])) {
    $mesas['mesas'] = [];
    for ($i = 1; $i <= 5; $i++) {
        $mesas['mesas'][] = [
            'id'       => 'mesa_' . str_pad($i, 2, '0', STR_PAD_LEFT),
            'numero'   => $i,
            'capacidad'=> $i <= 3 ? 4 : ($i === 4 ? 2 : 6),
            'zona'     => 'salon',
            'estado'   => 'disponible',
            'activa'   => true,
        ];
    }
    file_put_contents($mesasPath, json_encode($mesas, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "✓ 5 mesas de ejemplo creadas\n";
}

// 5. Productos de ejemplo
$prodsPath = $D . 'productos.json';
$prods = json_decode(file_get_contents($prodsPath), true);
if (empty($prods)) {
    $prods = [
        ['id'=>1,'nombre'=>'1 Pollo','precio'=>39900,'categoria'=>'pollo','descripcion'=>'Con papa, plátano y arepas','activo'=>true,'emoji'=>'🐓','tag'=>'Más Pedido'],
        ['id'=>15,'nombre'=>'½ Pollo','precio'=>12000,'categoria'=>'pollo','descripcion'=>'Con papas','activo'=>true,'emoji'=>'🍗','tag'=>null],
        ['id'=>2,'nombre'=>'¼ Pollo','precio'=>23000,'categoria'=>'pollo','descripcion'=>'Con papas','activo'=>true,'emoji'=>'🍗','tag'=>null],
        ['id'=>3,'nombre'=>'Bandeja con Pollo','precio'=>23000,'categoria'=>'pollo','descripcion'=>'Con papa y plátano','activo'=>true,'emoji'=>'🍗','tag'=>null],
        ['id'=>4,'nombre'=>'Pechuga a la Plancha','precio'=>27000,'categoria'=>'carta','descripcion'=>'Con papas y ensalada','activo'=>true,'emoji'=>'🥩','tag'=>null],
        ['id'=>5,'nombre'=>'Churrasco','precio'=>35000,'categoria'=>'carta','descripcion'=>'Con papas y ensalada','activo'=>true,'emoji'=>'🥩','tag'=>'Favorito'],
        ['id'=>6,'nombre'=>'Lomo de Cerdo','precio'=>27000,'categoria'=>'carta','descripcion'=>'Arroz, papas y ensalada','activo'=>true,'emoji'=>'🥩','tag'=>null],
        ['id'=>7,'nombre'=>'Chuleta Apanada','precio'=>30000,'categoria'=>'carta','descripcion'=>'Arroz, plátano y ensalada','activo'=>true,'emoji'=>'🥩','tag'=>null],
        ['id'=>8,'nombre'=>'Mojarra Frita','precio'=>35000,'categoria'=>'carta','descripcion'=>'Arroz, plátano y ensalada','activo'=>true,'emoji'=>'🐟','tag'=>null],
        ['id'=>9,'nombre'=>'Alitas BBQ','precio'=>23000,'categoria'=>'carta','descripcion'=>'Con papas','activo'=>true,'emoji'=>'🍖','tag'=>null],
        ['id'=>10,'nombre'=>'Costillas BBQ','precio'=>27000,'categoria'=>'carta','descripcion'=>'Con papas','activo'=>true,'emoji'=>'🍖','tag'=>null],
        ['id'=>11,'nombre'=>'Hamburguesa de Res','precio'=>23000,'categoria'=>'hamburguesas','descripcion'=>'Con papas y gaseosa','activo'=>true,'emoji'=>'🍔','tag'=>null],
        ['id'=>12,'nombre'=>'Hamburguesa de Pollo','precio'=>23000,'categoria'=>'hamburguesas','descripcion'=>'Con papas y gaseosa','activo'=>true,'emoji'=>'🍔','tag'=>null],
        ['id'=>13,'nombre'=>'Hamburguesa 3R','precio'=>28000,'categoria'=>'hamburguesas','descripcion'=>'Con papas y gaseosa','activo'=>true,'emoji'=>'🍔','tag'=>'Exclusiva'],
        ['id'=>14,'nombre'=>'Salchipapas 3R','precio'=>23000,'categoria'=>'carta','descripcion'=>'Estilo Red Rooster','activo'=>true,'emoji'=>'🍟','tag'=>null],
    ];
    file_put_contents($prodsPath, json_encode($prods, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "✓ " . count($prods) . " productos de ejemplo creados\n";
}

echo "\n" . str_repeat('─', 40) . "\n";
echo "✅ Setup completo.\n\n";
echo "Corre el servidor con:\n";
echo "  php -S localhost:8080\n\n";
echo "Luego abre:\n";
echo "  http://localhost:8080           → Sitio público\n";
echo "  http://localhost:8080/admin.php → Panel admin\n";
echo "  http://localhost:8080/mesero.php → App mesero\n";
echo "  http://localhost:8080/cocina.php → Pantalla cocina\n\n";
echo "⚠️  Borra este archivo después de usarlo.\n\n";
