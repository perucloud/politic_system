<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/../includes/helpers/config.php';

// Redirigir si ya está logueado
if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php'); exit;
}

// Config visual
$login_logo     = '/assets/img/logos/logorp.webp';
$login_subtitle = 'Portal Ivan Cisneros';
try {
    $cfg = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave IN ('login_logo','login_subtitle')")
               ->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!empty(trim($cfg['login_logo']     ?? ''))) $login_logo     = trim($cfg['login_logo']);
    if (!empty(trim($cfg['login_subtitle'] ?? ''))) $login_subtitle = trim($cfg['login_subtitle']);
} catch (Exception $e) {}

function rst_img_url(string $path): string {
    if (preg_match('#^https?://#', $path)) return $path;
    return BASE_URL . '/' . ltrim($path, '/');
}

// Inicializar CSRF
if (empty($_SESSION['_csrf_token'])) {
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}

// ── Validar token de la URL ───────────────────────────────────
$token_url = trim($_GET['token'] ?? '');
$token_valid = false;
$token_email = '';
$token_error = '';

if ($token_url === '' || strlen($token_url) !== 64 || !ctype_alnum($token_url)) {
    $token_error = 'El enlace no es válido.';
} else {
    try {
        $stmt = $pdo->prepare(
            "SELECT email FROM password_resets
             WHERE token = ? AND used = 0 AND expires_at > NOW()
             LIMIT 1"
        );
        $stmt->execute([$token_url]);
        $row = $stmt->fetch();
        if ($row) {
            $token_valid = true;
            $token_email = $row['email'];
        } else {
            $token_error = 'El enlace ha expirado o ya fue utilizado. Solicita uno nuevo.';
        }
    } catch (Exception $e) {
        $token_error = 'Error al verificar el enlace. Intenta de nuevo.';
    }
}

// ── Manejo POST ───────────────────────────────────────────────
$flash      = '';
$flash_type = 'ok';
$done       = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    $csrf_post = $_POST['_csrf'] ?? '';
    $csrf_sess = $_SESSION['_csrf_token'] ?? '';

    if (!hash_equals($csrf_sess, $csrf_post)) {
        $flash      = 'Error de sesión. Recarga la página e intenta de nuevo.';
        $flash_type = 'error';
    } else {
        $pass1 = $_POST['password']  ?? '';
        $pass2 = $_POST['password2'] ?? '';

        if (strlen($pass1) < 8) {
            $flash      = 'La contraseña debe tener al menos 8 caracteres.';
            $flash_type = 'error';
        } elseif ($pass1 !== $pass2) {
            $flash      = 'Las contraseñas no coinciden.';
            $flash_type = 'error';
        } else {
            try {
                // Verificar token aún válido (doble check en POST)
                $stmt = $pdo->prepare(
                    "SELECT email FROM password_resets
                     WHERE token = ? AND used = 0 AND expires_at > NOW() LIMIT 1"
                );
                $stmt->execute([$token_url]);
                $row2 = $stmt->fetch();

                if (!$row2) {
                    $flash      = 'El enlace ya no es válido. Solicita uno nuevo.';
                    $flash_type = 'error';
                } else {
                    $hashed = password_hash($pass1, PASSWORD_DEFAULT);

                    // Actualizar contraseña
                    $pdo->prepare("UPDATE usuarios SET password = ? WHERE email = ?")
                        ->execute([$hashed, $row2['email']]);

                    // Invalidar token (marca como usado)
                    $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?")
                        ->execute([$token_url]);

                    // Invalidar remember_token (operación secundaria, no crítica)
                    try {
                        $pdo->prepare("UPDATE usuarios SET remember_token = NULL WHERE email = ?")
                            ->execute([$row2['email']]);
                    } catch (Exception $e2) {}

                    $done  = true;
                    $flash = 'Tu contraseña ha sido actualizada correctamente. Ya puedes iniciar sesión.';
                }
            } catch (Exception $e) {
                $flash      = 'Error al actualizar la contraseña. Intenta de nuevo.';
                $flash_type = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Restablecer contraseña — Admin Ivan Cisneros</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{colors:{primary:'#1E3A8A',accent:'#FACC15'}}}}</script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-[#EFF6FF] via-[#DBEAFE] to-[#E0F2FE]
             flex items-center justify-center p-4">

  <div class="w-full max-w-md">
    <div class="bg-white rounded-3xl shadow-2xl p-8 sm:p-10">

      <!-- Logo -->
      <div class="text-center mb-8">
        <img src="<?= htmlspecialchars(rst_img_url($login_logo)) ?>"
             alt="Logo" class="h-12 mx-auto mb-4" onerror="this.style.display='none'">
        <h1 class="text-xl font-black text-[#1E3A8A]">Nueva contraseña</h1>
        <p class="text-gray-400 text-sm mt-1"><?= htmlspecialchars($login_subtitle) ?></p>
      </div>

      <!-- Token inválido -->
      <?php if (!$token_valid && !$done): ?>
      <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-xl px-4 py-4 mb-6 flex items-start gap-3">
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div>
          <p class="font-bold">Enlace no válido</p>
          <p class="mt-0.5 text-red-600"><?= htmlspecialchars($token_error) ?></p>
        </div>
      </div>
      <a href="<?= BASE_URL ?>/admin/recuperar-password.php"
         class="block w-full text-center bg-[#1E3A8A] hover:bg-blue-900 text-white font-bold py-3.5 rounded-xl
                transition-all text-sm shadow-lg mb-4">
        Solicitar nuevo enlace
      </a>
      <?php endif; ?>

      <!-- Flash message -->
      <?php if ($flash): ?>
      <div class="px-4 py-3 rounded-xl text-sm font-medium mb-6 flex items-start gap-2
                  <?= $flash_type === 'error'
                      ? 'bg-red-50 border border-red-200 text-red-700'
                      : 'bg-green-50 border border-green-200 text-green-700' ?>">
        <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <?php if ($flash_type === 'error'): ?>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          <?php else: ?>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          <?php endif; ?>
        </svg>
        <?= htmlspecialchars($flash) ?>
      </div>
      <?php endif; ?>

      <!-- Formulario nueva contraseña -->
      <?php if ($token_valid && !$done): ?>
      <p class="text-sm text-gray-500 mb-5 leading-relaxed">
        Elige una contraseña segura de al menos <strong>8 caracteres</strong>.
      </p>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['_csrf_token']) ?>">

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">Nueva contraseña</label>
          <input type="password" name="password" required minlength="8"
                 placeholder="Mínimo 8 caracteres"
                 class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm
                        focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition-all">
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">Confirmar contraseña</label>
          <input type="password" name="password2" required minlength="8"
                 placeholder="Repite la contraseña"
                 class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm
                        focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition-all">
        </div>

        <button type="submit"
                class="w-full bg-[#1E3A8A] hover:bg-blue-900 text-white font-bold py-3.5 rounded-xl
                       transition-all text-sm shadow-lg hover:shadow-xl">
          Guardar nueva contraseña
        </button>
      </form>
      <?php endif; ?>

      <!-- Éxito: ir al login -->
      <?php if ($done): ?>
      <a href="<?= BASE_URL ?>/admin/login.php"
         class="block w-full text-center bg-[#1E3A8A] hover:bg-blue-900 text-white font-bold py-3.5 rounded-xl
                transition-all text-sm shadow-lg">
        Ir al login
      </a>
      <?php endif; ?>

      <div class="text-center mt-6">
        <a href="<?= BASE_URL ?>/admin/login.php"
           class="text-sm text-[#1E3A8A] font-semibold hover:underline inline-flex items-center gap-1">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
          </svg>
          Volver al login
        </a>
      </div>

    </div>
  </div>
</body>
</html>
