<?php
// ============================================================
// auth.php - Helper centralizado de autenticacion y roles
// Incluir DESPUES de session_start() y db.php
// ============================================================

function require_login(): void {
    if (empty($_SESSION['admin_id'])) {
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }
}

function get_rol(): string {
    return $_SESSION['admin_rol'] ?? 'editor';
}

function is_superadmin(): bool {
    return get_rol() === 'superadmin';
}

function is_admin_or_above(): bool {
    return in_array(get_rol(), ['superadmin', 'admin'], true);
}

function is_candidato_distrital(): bool {
    return get_rol() === 'candidato_distrital';
}

function csrf_token(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

function csrf_request_token(): string {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    return (string)($_POST['_csrf'] ?? $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? '');
}

function csrf_verify(bool $json = false): void {
    $token = csrf_request_token();
    if ($token !== '' && hash_equals(csrf_token(), $token)) return;
    http_response_code(419);
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Token CSRF invalido.']);
    } else {
        echo 'Token CSRF invalido. Recarga la pagina e intenta nuevamente.';
    }
    exit;
}

function require_rol(string $min_rol): void {
    $jerarquia = ['candidato_distrital' => 0, 'editor' => 1, 'admin' => 2, 'superadmin' => 3];
    $user_nivel = $jerarquia[get_rol()] ?? 0;
    $req_nivel  = $jerarquia[$min_rol]  ?? 99;
    if ($user_nivel < $req_nivel) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
        <script src="https://cdn.tailwindcss.com"></script></head>
        <body class="min-h-screen bg-gray-50 flex items-center justify-center">
        <div class="text-center p-10">
          <div class="text-6xl mb-4">!</div>
          <h1 class="text-2xl font-black text-gray-800 mb-2">Acceso denegado</h1>
          <p class="text-gray-500 mb-6">No tienes permisos para acceder a esta seccion.</p>
          <a href="' . BASE_URL . '/admin/dashboard.php"
             class="bg-[#1E3A8A] text-white px-6 py-3 rounded-xl font-bold text-sm hover:bg-blue-900 transition-all">
            Volver al dashboard
          </a>
        </div></body></html>';
        exit;
    }
}

function log_activity(PDO $pdo, string $accion, string $modulo = ''): void {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO activity_logs (usuario_id, usuario_nombre, accion, modulo, ip)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $_SESSION['admin_id']     ?? 0,
            $_SESSION['admin_nombre'] ?? 'Desconocido',
            $accion,
            $modulo,
            $_SERVER['REMOTE_ADDR']   ?? ''
        ]);
    } catch (Exception $e) {}
}

// Modulos disponibles en el sistema.
const MODULOS_SISTEMA = [
    'candidatos_distritales' => 'Candidatos Distritales',
    'noticias'               => 'Noticias',
    'actividades'            => 'Actividades / Agenda',
    'paginas'                => 'Paginas',
    'media'                  => 'Archivos y Media',
    'simpatizantes'          => 'Simpatizantes',
    'militantes'             => 'Militantes',
    'personeros'             => 'Personeros',
    'menu_web'               => 'Menu Web',
    'configuracion_global'   => 'Configurar Index / Sitio',
    'seo'                    => 'SEO por Pagina',
    'auditoria'              => 'Auditoria',
    'usuarios'               => 'Usuarios y Editores',
    'material_publicitario'  => 'Material Publicitario',
];

function ensure_candidate_access_schema(PDO $pdo): void {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $col = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'candidato_id'")->fetch();
        if (!$col) {
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN candidato_id INT NULL AFTER activo");
            $pdo->exec("ALTER TABLE usuarios ADD INDEX idx_usuarios_candidato_id (candidato_id)");
        }
    } catch (Exception $e) {}
    try {
        $rol_col = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'rol'")->fetch(PDO::FETCH_ASSOC);
        if ($rol_col && strpos((string)$rol_col['Type'], 'candidato_distrital') === false) {
            $pdo->exec("ALTER TABLE usuarios MODIFY rol ENUM('superadmin','admin','editor','candidato_distrital') NOT NULL DEFAULT 'editor'");
        }
    } catch (Exception $e) {}
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS candidato_noticias (
                id INT AUTO_INCREMENT PRIMARY KEY,
                candidato_id INT NOT NULL,
                titulo VARCHAR(220) NOT NULL,
                contenido TEXT NULL,
                imagen VARCHAR(255) NULL,
                estado ENUM('borrador','publicado') NOT NULL DEFAULT 'publicado',
                creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                actualizado DATETIME NULL,
                INDEX idx_candidato_noticias_candidato (candidato_id),
                INDEX idx_candidato_noticias_estado (estado)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Exception $e) {}
}

// Cache en memoria para los modulos del usuario actual.
$_modulos_cache = null;

function get_modulos_usuario(int $user_id, PDO $pdo): array {
    try {
        $stmt = $pdo->prepare("SELECT modulo FROM user_modulos WHERE usuario_id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Exception $e) { return []; }
}

function has_modulo(string $modulo, PDO $pdo): bool {
    if (is_superadmin()) return true;
    global $_modulos_cache;
    if ($_modulos_cache === null) {
        $_modulos_cache = get_modulos_usuario((int)($_SESSION['admin_id'] ?? 0), $pdo);
    }
    return in_array($modulo, $_modulos_cache, true);
}

function require_modulo(string $modulo, PDO $pdo): void {
    if (has_modulo($modulo, $pdo)) return;
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
    <script src="https://cdn.tailwindcss.com"></script></head>
    <body class="min-h-screen bg-gray-50 flex items-center justify-center font-sans">
    <div class="text-center p-10 max-w-md">
      <div class="w-20 h-20 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-5">
        <svg class="w-10 h-10 text-[#1E3A8A]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
        </svg>
      </div>
      <h1 class="text-2xl font-black text-gray-800 mb-2">Modulo no disponible</h1>
      <p class="text-gray-500 mb-6 text-sm">No tienes acceso al modulo <strong>' . htmlspecialchars($modulo) . '</strong>.<br>Contacta al administrador para solicitar acceso.</p>
      <a href="' . BASE_URL . '/admin/dashboard.php"
         class="inline-flex items-center gap-2 bg-[#1E3A8A] text-white px-6 py-3 rounded-xl font-bold text-sm hover:bg-blue-900 transition-all">
        Volver al dashboard
      </a>
    </div></body></html>';
    exit;
}

function guardar_modulos_usuario(int $user_id, array $modulos, PDO $pdo): void {
    try {
        $pdo->prepare("DELETE FROM user_modulos WHERE usuario_id = ?")->execute([$user_id]);
        $stmt = $pdo->prepare("INSERT IGNORE INTO user_modulos (usuario_id, modulo) VALUES (?, ?)");
        foreach ($modulos as $m) {
            if (array_key_exists($m, MODULOS_SISTEMA)) {
                $stmt->execute([$user_id, $m]);
            }
        }
    } catch (Exception $e) {}
}

function rol_badge(string $rol): string {
    $map = [
        'superadmin' => '<span class="bg-purple-100 text-purple-700 text-xs font-bold px-2 py-0.5 rounded-full">SuperAdmin</span>',
        'admin'      => '<span class="bg-blue-100 text-blue-700 text-xs font-bold px-2 py-0.5 rounded-full">Admin</span>',
        'editor'     => '<span class="bg-green-100 text-green-700 text-xs font-bold px-2 py-0.5 rounded-full">Editor</span>',
        'candidato_distrital' => '<span class="bg-amber-100 text-amber-700 text-xs font-bold px-2 py-0.5 rounded-full">Candidato Distrital</span>',
    ];
    return $map[$rol] ?? '<span class="bg-gray-100 text-gray-600 text-xs font-bold px-2 py-0.5 rounded-full">' . htmlspecialchars($rol) . '</span>';
}

function candidato_scope_id(): int {
    return (int)($_SESSION['candidato_id'] ?? 0);
}

function require_candidato_scope(PDO $pdo): int {
    ensure_candidate_access_schema($pdo);
    $session_id = (int)($_SESSION['admin_id'] ?? 0);
    if (!is_candidato_distrital() || $session_id <= 0) {
        http_response_code(403);
        echo 'Cuenta sin distrito asignado.';
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT candidato_id, activo, rol FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$session_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $id = (int)($user['candidato_id'] ?? 0);
        if (!$user || (int)$user['activo'] !== 1 || $user['rol'] !== 'candidato_distrital' || $id <= 0) {
            http_response_code(403);
            echo 'Cuenta sin distrito asignado.';
            exit;
        }
        $_SESSION['candidato_id'] = $id;
    } catch (Exception $e) {
        $id = candidato_scope_id();
    }

    if ($id <= 0) {
        http_response_code(403);
        echo 'Cuenta sin distrito asignado.';
        exit;
    }
    return $id;
}
