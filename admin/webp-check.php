<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config/auth.php';
require_login();

$gd       = function_exists('gd_info') ? gd_info() : [];
$webp_gd  = !empty($gd['WebP Support']);
$imagick  = extension_loaded('imagick') && class_exists('Imagick');
$imagick_webp = false;
if ($imagick) {
    try { $imagick_webp = in_array('WEBP', (new Imagick())->queryFormats()); } catch(Exception $e) {}
}
$php_ver  = PHP_VERSION;
$upload_max = ini_get('upload_max_filesize');
$post_max   = ini_get('post_max_size');

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>WebP Check</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-8 font-sans">
<div class="max-w-lg mx-auto bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
  <div class="bg-[#1E3A8A] px-6 py-4">
    <h1 class="text-white font-black text-lg">Verificación WebP — Servidor</h1>
    <p class="text-blue-200 text-xs mt-1">ivancisneros.test</p>
  </div>
  <div class="p-6 space-y-3">

    <div class="flex items-center justify-between px-4 py-3 rounded-xl border <?= $webp_gd ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' ?>">
      <div>
        <p class="font-black text-sm <?= $webp_gd ? 'text-green-800' : 'text-red-800' ?>">GD + WebP Support</p>
        <p class="text-xs <?= $webp_gd ? 'text-green-600' : 'text-red-500' ?> mt-0.5">extension GD con imagewebp()</p>
      </div>
      <span class="text-2xl"><?= $webp_gd ? '✅' : '❌' ?></span>
    </div>

    <div class="flex items-center justify-between px-4 py-3 rounded-xl border <?= $imagick_webp ? 'bg-green-50 border-green-200' : 'bg-gray-50 border-gray-200' ?>">
      <div>
        <p class="font-black text-sm <?= $imagick_webp ? 'text-green-800' : 'text-gray-600' ?>">Imagick + WebP</p>
        <p class="text-xs <?= $imagick_webp ? 'text-green-600' : 'text-gray-400' ?> mt-0.5">extensión Imagick (alternativa)</p>
      </div>
      <span class="text-2xl"><?= $imagick_webp ? '✅' : ($imagick ? '⚠️' : '❌') ?></span>
    </div>

    <div class="px-4 py-3 rounded-xl bg-gray-50 border border-gray-200 space-y-1.5 text-sm">
      <div class="flex justify-between"><span class="text-gray-500">PHP Version</span><strong><?= htmlspecialchars($php_ver) ?></strong></div>
      <div class="flex justify-between"><span class="text-gray-500">upload_max_filesize</span><strong><?= htmlspecialchars($upload_max) ?></strong></div>
      <div class="flex justify-between"><span class="text-gray-500">post_max_size</span><strong><?= htmlspecialchars($post_max) ?></strong></div>
    </div>

    <?php if ($webp_gd): ?>
    <?php
    // Test real: crear imagen WebP en memoria
    $test_ok = false;
    $test_msg = '';
    try {
        $img = imagecreatetruecolor(10, 10);
        $col = imagecolorallocate($img, 30, 58, 138);
        imagefill($img, 0, 0, $col);
        ob_start();
        $result = imagewebp($img, null, 80);
        $bytes = ob_get_clean();
        imagedestroy($img);
        $test_ok = $result && strlen($bytes) > 0;
        $test_msg = $test_ok ? 'Generó ' . strlen($bytes) . ' bytes de WebP correctamente.' : 'imagewebp() retornó false.';
    } catch(Throwable $e) {
        $test_msg = 'Excepción: ' . $e->getMessage();
    }
    ?>
    <div class="flex items-center justify-between px-4 py-3 rounded-xl border <?= $test_ok ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' ?>">
      <div>
        <p class="font-black text-sm <?= $test_ok ? 'text-green-800' : 'text-red-800' ?>">Prueba real imagewebp()</p>
        <p class="text-xs <?= $test_ok ? 'text-green-600' : 'text-red-500' ?> mt-0.5"><?= htmlspecialchars($test_msg) ?></p>
      </div>
      <span class="text-2xl"><?= $test_ok ? '✅' : '❌' ?></span>
    </div>
    <?php endif; ?>

    <div class="mt-4 rounded-xl p-4 text-sm font-semibold text-center
      <?= ($webp_gd && ($test_ok ?? false)) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
      <?php if ($webp_gd && ($test_ok ?? false)): ?>
        ✅ El servidor soporta WebP. Se puede implementar la conversión automática.
      <?php elseif ($webp_gd): ?>
        ⚠️ GD tiene WebP habilitado pero la prueba real falló. Revisar configuración.
      <?php else: ?>
        ❌ GD no tiene soporte WebP. Se necesita habilitar en php.ini o usar Imagick.
      <?php endif; ?>
    </div>

  </div>
  <div class="px-6 py-3 bg-gray-50 border-t border-gray-100 text-xs text-gray-400 text-center">
    Elimina este archivo después de verificar: <code>admin/webp-check.php</code>
  </div>
</div>
</body>
</html>
