<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/../includes/helpers/config.php';
ensure_candidate_access_schema($pdo);

// Redirigir si ya esta logueado.
if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$login_logo     = '/assets/img/logos/logorp.webp';
$login_subtitle = 'Portal Ivan Cisneros';
try {
    $cfg_login = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave IN ('login_logo','login_subtitle')")
                     ->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!empty(trim($cfg_login['login_logo'] ?? '')))     $login_logo     = trim($cfg_login['login_logo']);
    if (!empty(trim($cfg_login['login_subtitle'] ?? ''))) $login_subtitle = trim($cfg_login['login_subtitle']);
} catch (Exception $e) {}

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();

    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $stmt = $pdo->prepare("SELECT id, nombre, password, rol, activo, candidato_id FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if (!$user['activo']) {
                $error = 'Tu cuenta esta desactivada. Contacta al administrador.';
            } else {
                $_SESSION['admin_id']     = $user['id'];
                $_SESSION['admin_nombre'] = $user['nombre'];
                $_SESSION['admin_rol']    = $user['rol'];
                $_SESSION['candidato_id'] = (int)($user['candidato_id'] ?? 0);
                session_regenerate_id(true);
                // Actualizar ultimo acceso.
                $pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?")->execute([$user['id']]);
                header('Location: ' . ($user['rol'] === 'candidato_distrital' ? 'mi-distrito.php' : 'dashboard.php'));
                exit;
            }
        }
    }
    $error = 'Correo o contrasena incorrectos.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Acceso Admin - Portal Ivan Cisneros</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{colors:{primary:'#1E3A8A',secondary:'#38BDF8',accent:'#FACC15'}}}}</script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-[#EFF6FF] via-[#DBEAFE] to-[#E0F2FE] flex items-center justify-center p-4">

  <div class="w-full max-w-md">
    <div class="bg-white rounded-3xl shadow-2xl p-8 sm:p-10">
      <div class="text-center mb-8">
        <img src="<?= htmlspecialchars(cfg_site_url($login_logo)) ?>"
             alt="Logo" class="h-14 mx-auto mb-4"
             onerror="this.style.display='none'">
        <h1 class="text-xl font-black text-[#1E3A8A]">Panel Administrativo</h1>
        <p class="text-gray-400 text-sm mt-1"><?= htmlspecialchars($login_subtitle) ?></p>
      </div>

      <?php if ($error): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-3 mb-6 flex items-center gap-2">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" class="space-y-5">
        <?= csrf_field() ?>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">Correo electronico</label>
          <input type="email" name="email" required
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 placeholder="admin@ivancisneros.pe"
                 class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition-all">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">Contrasena</label>
          <input type="password" name="password" required
                 placeholder="********"
                 class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition-all">
        </div>
        <button type="submit"
                class="w-full bg-[#1E3A8A] hover:bg-blue-900 text-white font-bold py-3.5 rounded-xl transition-all text-sm shadow-lg hover:shadow-xl">
          Ingresar al panel
        </button>
      </form>

      <p class="text-center text-xs text-gray-400 mt-6">
        Acceso restringido - Solo personal autorizado
      </p>
    </div>
  </div>
</body>
</html>
