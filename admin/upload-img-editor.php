<?php
// Endpoint de upload de imagenes para el editor Quill.
if (ob_get_level() === 0) ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/../includes/helpers/webp.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo no permitido']);
    exit;
}

csrf_verify(true);

if (empty($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Sin archivo']);
    exit;
}

$file    = $_FILES['file'];
$ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

if (!in_array($ext, $allowed) || $file['size'] <= 0 || $file['size'] > 6 * 1024 * 1024) {
    echo json_encode(['error' => 'Tipo no permitido o excede 6 MB']);
    exit;
}

$info = @getimagesize($file['tmp_name']);
if ($info === false) {
    echo json_encode(['error' => 'El archivo no es una imagen valida']);
    exit;
}

$dir = __DIR__ . '/../assets/img/noticias/';
if (!is_dir($dir)) @mkdir($dir, 0755, true);

$webp_e = img_to_webp($file['tmp_name'], $ext);
if ($webp_e !== false) {
    $filename  = 'editor_' . uniqid() . '.webp';
    $file_data = $webp_e;
} else {
    $filename  = 'editor_' . uniqid() . '.' . $ext;
    $file_data = @file_get_contents($file['tmp_name']);
}
$dest = $dir . $filename;

if ($file_data !== false && strlen($file_data) > 0 && @file_put_contents($dest, $file_data) !== false) {
    echo json_encode(['url' => BASE_URL . '/assets/img/noticias/' . $filename]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Error al guardar la imagen']);
}
