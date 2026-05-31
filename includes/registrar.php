<?php
// ============================================================
// registrar.php - Procesa formulario "Unete al equipo".
// ============================================================
require_once __DIR__ . '/config/db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
    exit;
}

$nombre         = trim($_POST['nombre']         ?? '');
$dni            = trim($_POST['dni']            ?? '');
$telefono       = trim($_POST['telefono']       ?? '');
$distrito       = trim($_POST['distrito']       ?? '');
$tipo_documento = trim($_POST['tipo_documento'] ?? 'DNI');
$correo         = trim($_POST['correo']         ?? '') ?: null;
$celular        = trim($_POST['celular']        ?? '') ?: null;
$whatsapp       = trim($_POST['whatsapp']       ?? '') ?: null;
$formas_apoyo   = trim($_POST['formas_apoyo']   ?? '') ?: null;

// Validaciones obligatorias (paso 1)
if (!$nombre || !$dni || !$telefono || !$distrito) {
    echo json_encode(['error' => 'Todos los campos son obligatorios.']);
    exit;
}
if (!preg_match('/^\d{8}$/', $dni)) {
    echo json_encode(['error' => 'El DNI debe tener 8 dígitos.']);
    exit;
}
if ($correo && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'El correo electrónico no es válido.']);
    exit;
}

try {
    // Verificar DNI duplicado
    $check = $pdo->prepare("SELECT id FROM simpatizantes WHERE dni = ?");
    $check->execute([$dni]);
    if ($check->fetch()) {
        echo json_encode(['error' => 'Este DNI ya está registrado.']);
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO simpatizantes
         (nombre, dni, telefono, distrito, tipo_documento, correo, celular, whatsapp, formas_apoyo, fecha_registro)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([$nombre, $dni, $telefono, $distrito, $tipo_documento, $correo, $celular, $whatsapp, $formas_apoyo]);

    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al guardar. Intenta de nuevo.']);
}
