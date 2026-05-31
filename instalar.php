<?php
// ============================================================
// instalar.php - Instalador unico del portal.
// Para ejecutarlo crea temporalmente el archivo .allow-install
// en la raiz del proyecto y eliminalo al terminar.
// ============================================================
if (!is_file(__DIR__ . '/.allow-install')) {
    http_response_code(403);
    echo 'Instalador deshabilitado. Crea temporalmente .allow-install para ejecutarlo.';
    exit;
}define('SKIP_DB_CONNECT', true);
require_once __DIR__ . '/includes/config/db.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die(json_encode(['error' => 'Error de conexion a MySQL. Verifica credenciales en includes/config/db.php.']));
}

$pasos = [];
$error = false;

$sqls = [
  'Base de datos' => "CREATE DATABASE IF NOT EXISTS ivancisneros CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
  'Seleccionar base de datos' => "USE ivancisneros",
  'Tabla usuarios' => "CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    rol ENUM('admin','editor') NOT NULL DEFAULT 'editor',
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB",
  'Tabla noticias' => "CREATE TABLE IF NOT EXISTS noticias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(300) NOT NULL,
    contenido TEXT,
    imagen VARCHAR(300),
    categoria VARCHAR(100) DEFAULT 'General',
    estado ENUM('publicado','borrador') NOT NULL DEFAULT 'borrador',
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB",
  'Tabla simpatizantes' => "CREATE TABLE IF NOT EXISTS simpatizantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    dni CHAR(8) NOT NULL UNIQUE,
    telefono VARCHAR(20),
    distrito VARCHAR(100),
    fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB",
  'Tabla contactos' => "CREATE TABLE IF NOT EXISTS contactos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150),
    email VARCHAR(150),
    mensaje TEXT,
    leido TINYINT(1) DEFAULT 0,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB",
  'Tabla configuracion' => "CREATE TABLE IF NOT EXISTS configuracion (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT
  ) ENGINE=InnoDB",
  'Usuario admin' => "INSERT IGNORE INTO usuarios (nombre, email, password, rol)
    VALUES ('Super Admin', 'superadmin@gmail.com',
    '" . password_hash('admin123', PASSWORD_BCRYPT) . "', 'admin')",
  'Config inicial' => "INSERT IGNORE INTO configuracion (clave, valor) VALUES
    ('hero_titulo',    'Lic. Ivan Cisneros'),
    ('hero_subtitulo', 'Candidato a la Alcaldía Provincial de Satipo'),
    ('hero_eslogan',   'Ha llegado el momento de transformar Satipo'),
    ('quien_es_texto', 'Texto biográfico del candidato...'),
    ('partido_nombre', 'ALIANZA PARA EL PROGRESO')",
];

foreach ($sqls as $label => $sql) {
    try {
        $pdo->exec($sql);
        $pasos[] = ['ok' => true, 'label' => $label];
    } catch (Exception $e) {
        $pasos[] = ['ok' => false, 'label' => $label, 'msg' => $e->getMessage()];
        $error = true;
    }
}

$migration_militantes = __DIR__ . '/admin/migration_militantes.sql';
if (is_file($migration_militantes)) {
    try {
        $sql = preg_replace('/^--.*$/m', '', file_get_contents($migration_militantes));
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt !== '') $pdo->exec($stmt);
        }
        $pasos[] = ['ok' => true, 'label' => 'Modulo militantes'];
    } catch (Exception $e) {
        $pasos[] = ['ok' => false, 'label' => 'Modulo militantes', 'msg' => $e->getMessage()];
        $error = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Instalador — Portal Ivan Cisneros</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-lg p-8 w-full max-w-lg">
    <div class="text-center mb-8">
      <div class="text-4xl mb-3"><?= $error ? '⚠️' : '✅' ?></div>
      <h1 class="text-xl font-black text-gray-800">
        <?= $error ? 'Instalación con errores' : 'Instalación completada' ?>
      </h1>
      <p class="text-gray-500 text-sm mt-1">Portal Ivan Cisneros</p>
    </div>

    <ul class="space-y-2 mb-8">
      <?php foreach ($pasos as $p): ?>
      <li class="flex items-start gap-3 text-sm px-3 py-2 rounded-lg <?= $p['ok'] ? 'bg-green-50' : 'bg-red-50' ?>">
        <span class="text-base flex-shrink-0"><?= $p['ok'] ? '✅' : '❌' ?></span>
        <div>
          <span class="font-medium <?= $p['ok'] ? 'text-green-700' : 'text-red-700' ?>"><?= $p['label'] ?></span>
          <?php if (!$p['ok']): ?>
          <p class="text-red-500 text-xs mt-0.5"><?= htmlspecialchars($p['msg']) ?></p>
          <?php endif; ?>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>

    <?php if (!$error): ?>
    <div class="bg-blue-50 rounded-xl p-4 text-sm text-blue-800 mb-6">
      <p class="font-bold mb-2">Credenciales del panel admin:</p>
      <p>Usuario: <code class="bg-white px-1 rounded">superadmin@gmail.com</code></p>
      <p>Contraseña: <code class="bg-white px-1 rounded">admin123</code></p>
    </div>
    <div class="flex gap-3">
      <a href="<?= BASE_URL ?>/index.php"
         class="flex-1 text-center bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 rounded-xl text-sm transition-colors">
        Ver sitio
      </a>
      <a href="<?= BASE_URL ?>/admin/login.php"
         class="flex-1 text-center bg-[#1E3A8A] hover:bg-blue-900 text-white font-bold py-3 rounded-xl text-sm transition-colors">
        Ir al Admin →
      </a>
    </div>
    <p class="text-center text-xs text-red-400 mt-4">⚠️ Elimina este archivo después de instalar</p>
    <?php else: ?>
    <div class="bg-yellow-50 rounded-xl p-4 text-sm text-yellow-800 mb-4">
      Verifica la contraseña de MySQL en <code>includes/config/db.php</code>
    </div>
    <a href="instalar.php" class="block text-center bg-[#1E3A8A] text-white font-bold py-3 rounded-xl text-sm">
      Reintentar
    </a>
    <?php endif; ?>
  </div>
</body>
</html>
