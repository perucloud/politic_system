<?php
// ============================================================
// CONFIGURACION BASE DE DATOS - Portal Ivan Cisneros
// Prioridad: 1) variables de entorno  2) db.local.php  3) default local
// En produccion: edita includes/config/db.local.php (NO subir a git)
// ============================================================

$_db_local_file = __DIR__ . '/db.local.php';
$_db_local = is_file($_db_local_file) ? require $_db_local_file : [];
if (!is_array($_db_local)) $_db_local = [];

define('DB_HOST',    getenv('IVAN_DB_HOST')    ?: ($_db_local['host']    ?? 'localhost'));
define('DB_USER',    getenv('IVAN_DB_USER')    ?: ($_db_local['user']    ?? 'dev'));
define('DB_PASS',    getenv('IVAN_DB_PASS')    ?: ($_db_local['pass']    ?? '1234'));
define('DB_NAME',    getenv('IVAN_DB_NAME')    ?: ($_db_local['name']    ?? 'ivancisneros'));
define('DB_CHARSET', getenv('IVAN_DB_CHARSET') ?: ($_db_local['charset'] ?? 'utf8mb4'));

// Zona horaria Peru
date_default_timezone_set('America/Lima');

// URL base del sitio (sin barra final). Se detecta automaticamente del host.
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$basePath = preg_replace('#/(admin|includes|distritales|noticias|public)$#', '', $scriptDir);
$basePath = $basePath === '/' ? '' : rtrim($basePath, '/');
define('BASE_URL', $scheme . '://' . $host . $basePath);

// Conexion PDO. El instalador puede cargar constantes sin conectar.
if (!defined('SKIP_DB_CONNECT')) {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        die(json_encode(['error' => 'Error de conexion a la base de datos.']));
    }
}
