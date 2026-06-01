<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/../includes/config/mail.php';
require_once __DIR__ . '/../includes/smtp-mailer.php';
require_once __DIR__ . '/../includes/helpers/config.php';

// Redirigir si ya está logueado
if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php'); exit;
}

// Migración tabla password_resets
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        email      VARCHAR(150) NOT NULL,
        token      VARCHAR(64)  NOT NULL,
        expires_at DATETIME     NOT NULL,
        used       TINYINT(1)   NOT NULL DEFAULT 0,
        created_at DATETIME     DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token),
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {}

// Config visual
$login_logo     = '/assets/img/logos/logorp.webp';
$login_subtitle = 'Portal Ivan Cisneros';
try {
    $cfg = $pdo->query("SELECT clave, valor FROM configuracion WHERE clave IN ('login_logo','login_subtitle')")
               ->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!empty(trim($cfg['login_logo']     ?? ''))) $login_logo     = trim($cfg['login_logo']);
    if (!empty(trim($cfg['login_subtitle'] ?? ''))) $login_subtitle = trim($cfg['login_subtitle']);
} catch (Exception $e) {}

function rp_img_url(string $path): string {
    if (preg_match('#^https?://#', $path)) return $path;
    return BASE_URL . '/' . ltrim($path, '/');
}

$flash      = '';
$flash_type = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF básico (token de sesión)
    $token_post = $_POST['_csrf'] ?? '';
    $token_sess = $_SESSION['_csrf_token'] ?? '';
    if ($token_sess === '' || !hash_equals($token_sess, $token_post)) {
        $flash = 'Error de sesión. Recarga la página e intenta de nuevo.';
        $flash_type = 'error';
    } else {
        $email = strtolower(trim($_POST['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $flash      = 'Ingresa un correo electrónico válido.';
            $flash_type = 'error';
        } else {
            // Respuesta siempre genérica (no revelar si el email existe)
            $flash = 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña en los próximos minutos.';

            try {
                $stmt = $pdo->prepare("SELECT id, nombre FROM usuarios WHERE email = ? AND activo = 1 LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user) {
                    // Invalidar tokens anteriores del mismo email
                    $pdo->prepare("UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0")
                        ->execute([$email]);

                    // Generar nuevo token
                    $token     = bin2hex(random_bytes(32)); // 64 chars hex
                    $expires   = date('Y-m-d H:i:s', time() + 3600); // 1 hora

                    $pdo->prepare(
                        "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)"
                    )->execute([$email, $token, $expires]);

                    // Construir enlace
                    $reset_link = BASE_URL . '/admin/reset-password.php?token=' . urlencode($token);
                    $nombre     = $user['nombre'];

                    // Cuerpo del correo
                    $body = "Hola {$nombre},\n\n"
                        . "Recibimos una solicitud para restablecer la contraseña de tu cuenta de administrador.\n\n"
                        . "Haz clic en el siguiente enlace para crear una nueva contraseña:\n"
                        . "{$reset_link}\n\n"
                        . "Este enlace expira en 1 hora.\n\n"
                        . "Si no solicitaste este cambio, ignora este mensaje. Tu contraseña no será modificada.\n\n"
                        . "— Sistema Admin · Ivan Cisneros";

                    $mail_error = null;
                    smtp_send_mail($email, $nombre, 'Restablecer contraseña — Panel Admin', $body, $mail_error);
                }
            } catch (Exception $e) {
                // Silencioso: no revelar errores internos al usuario
            }
        }
    }
}

// Inicializar CSRF si no existe
if (empty($_SESSION['_csrf_token'])) {
    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recuperar contraseña — Admin Ivan Cisneros</title>
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
        <img src="<?= htmlspecialchars(rp_img_url($login_logo)) ?>"
             alt="Logo" class="h-12 mx-auto mb-4" onerror="this.style.display='none'">
        <h1 class="text-xl font-black text-[#1E3A8A]">Recuperar contraseña</h1>
        <p class="text-gray-400 text-sm mt-1"><?= htmlspecialchars($login_subtitle) ?></p>
      </div>

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

      <?php if ($flash_type !== 'ok' || !$flash): ?>
      <!-- Instrucciones -->
      <p class="text-sm text-gray-500 mb-6 leading-relaxed">
        Ingresa el correo electrónico asociado a tu cuenta. Te enviaremos un enlace para crear una nueva contraseña.
      </p>

      <form method="POST" class="space-y-4">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_SESSION['_csrf_token']) ?>">

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">Correo electrónico</label>
          <input type="email" name="email" required autocomplete="email"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 placeholder="admin@ivancisneros.pe"
                 class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm
                        focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition-all">
        </div>

        <button type="submit"
                class="w-full bg-[#1E3A8A] hover:bg-blue-900 text-white font-bold py-3.5 rounded-xl
                       transition-all text-sm shadow-lg hover:shadow-xl">
          Enviar enlace de recuperación
        </button>
      </form>
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
