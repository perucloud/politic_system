<?php
// ============================================================
// upload-foto-perfil.php — Sube foto de perfil (sección Quién Es)
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
    echo json_encode(['error' => 'ID inválido.']); exit;
}

$stmt = $pdo->prepare("SELECT slug FROM candidatos_distritales WHERE id = ? LIMIT 1");
$stmt->execute([$candidato_id]);
$candidato = $stmt->fetch();
if (!$candidato) {
    echo json_encode(['error' => 'Candidato no encontrado.']); exit;
}

if (empty($_FILES['foto_perfil']) || $_FILES['foto_perfil']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'No se recibió archivo.']); exit;
}

$file = $_FILES['foto_perfil'];
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['error' => 'La imagen no debe superar 5 MB.']); exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
    echo json_encode(['error' => 'Solo JPG, PNG o WEBP.']); exit;
}
$slug    = $candidato['slug'];
$destDir = __DIR__ . '/../../assets/img/distritales/' . $slug . '/';
if (!is_dir($destDir)) mkdir($destDir, 0755, true);

$webp_p = img_to_webp($file['tmp_name'], $ext);
if ($webp_p !== false) {
    $destFile    = 'perfil.webp';
    $contenido_p = $webp_p;
} else {
    $destFile    = 'perfil.' . $ext;
    $contenido_p = @file_get_contents($file['tmp_name']);
}

if ($contenido_p === false || strlen($contenido_p) === 0 ||
    @file_put_contents($destDir . $destFile, $contenido_p) === false ||
    !file_exists($destDir . $destFile)) {
    echo json_encode(['error' => 'Error al guardar la imagen.']); exit;
}

$ruta = '/assets/img/distritales/' . $slug . '/' . $destFile;
$pdo->prepare("UPDATE candidatos_distritales SET foto_perfil = ? WHERE id = ?")
    ->execute([$ruta, $candidato_id]);

log_activity($pdo, 'Subió foto de perfil slug=' . $slug, 'candidatos_distritales');
echo json_encode(['ok' => true, 'ruta' => BASE_URL . $ruta]);
