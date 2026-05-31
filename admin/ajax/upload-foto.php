<?php
// ============================================================
// upload-foto.php — Sube foto de candidato distrital
// POST: file (imagen), candidato_id (int)
// Returns JSON: {ok, ruta} | {error}
// ============================================================
session_start();
require_once __DIR__ . '/../../includes/config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../includes/helpers/webp.php';

header('Content-Type: application/json; charset=utf-8');

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido.']); exit;
}

csrf_verify(true);

$candidato_id = (int)($_POST['candidato_id'] ?? 0);
if (!$candidato_id) {
    echo json_encode(['error' => 'ID de candidato inválido.']); exit;
}

// Verificar que existe el candidato
$stmt = $pdo->prepare("SELECT slug FROM candidatos_distritales WHERE id = ? LIMIT 1");
$stmt->execute([$candidato_id]);
$candidato = $stmt->fetch();
if (!$candidato) {
    echo json_encode(['error' => 'Candidato no encontrado.']); exit;
}

if (empty($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'No se recibió ningún archivo.']); exit;
}

$file    = $_FILES['foto'];
$maxSize = 5 * 1024 * 1024; // 5 MB

if ($file['size'] > $maxSize) {
    echo json_encode(['error' => 'La imagen no debe superar 5 MB.']); exit;
}

// Validar por extensión (más fiable en Windows que finfo)
$ext_foto = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext_foto, ['jpg','jpeg','png','webp','gif'])) {
    echo json_encode(['error' => 'Solo se permiten imágenes JPG, PNG, WEBP o GIF.']); exit;
}

$ext_foto = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$slug     = $candidato['slug'];
$destDir  = __DIR__ . '/../../assets/img/distritales/' . $slug . '/';

if (!is_dir($destDir)) mkdir($destDir, 0755, true);

$webp_f = img_to_webp($file['tmp_name'], $ext_foto);
if ($webp_f !== false) {
    $destFile    = 'foto.webp';
    $contenido_f = $webp_f;
} else {
    $destFile    = 'foto.' . $ext_foto;
    $contenido_f = @file_get_contents($file['tmp_name']);
}
$destPath = $destDir . $destFile;

if ($contenido_f === false || strlen($contenido_f) === 0 ||
    @file_put_contents($destPath, $contenido_f) === false || !file_exists($destPath)) {
    echo json_encode(['error' => 'Error al guardar la imagen.']); exit;
}

$ruta_publica = '/assets/img/distritales/' . $slug . '/' . $destFile;

// Actualizar foto en DB
$pdo->prepare("UPDATE candidatos_distritales SET foto = ? WHERE id = ?")
    ->execute([$ruta_publica, $candidato_id]);

log_activity($pdo, 'Subió foto de candidato slug=' . $slug, 'candidatos_distritales');

echo json_encode(['ok' => true, 'ruta' => BASE_URL . $ruta_publica]);
