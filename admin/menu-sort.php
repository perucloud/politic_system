<?php
// Endpoint AJAX: guarda el orden y parentesco del menú tras drag & drop
if (ob_get_level() === 0) ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/config/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

csrf_verify(true);

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data) || empty($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Datos inválidos']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE menu_items SET parent_id = ?, orden = ? WHERE id = ?");
    foreach ($data as $item) {
        $id        = (int)($item['id'] ?? 0);
        $parent_id = isset($item['parent_id']) && $item['parent_id'] !== null
                     ? (int)$item['parent_id'] : null;
        $orden     = (int)($item['orden'] ?? 10);
        if ($id > 0) {
            $stmt->execute([$parent_id, $orden, $id]);
        }
    }
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
