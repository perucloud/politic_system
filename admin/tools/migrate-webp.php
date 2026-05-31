<?php
// ── migrate-webp.php — Convierte imágenes existentes a WebP ────────────
// Acceso: solo admin. Procesa en lotes, muestra log en tiempo real.
// Elimina originales solo si la conversión fue exitosa.
// -----------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/config/db.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../../includes/helpers/webp.php';

require_login();
require_rol('admin');

$root = dirname(dirname(__DIR__));

// ── Zonas a procesar ────────────────────────────────────────────────────
$zonas = [
    'candidato'   => $root . '/assets/img/candidato/',
    'media'       => $root . '/uploads/media/',
    'noticias'    => $root . '/assets/img/noticias/',
    'distritales' => $root . '/assets/img/distritales/',
    'logos'       => $root . '/assets/img/logos/',
];

$img_exts = ['jpg', 'jpeg', 'png', 'gif'];

// ── Recolectar archivos a migrar ────────────────────────────────────────
function collect_images(array $zonas, array $exts): array
{
    $files = [];
    foreach ($zonas as $zona => $dir) {
        if (!is_dir($dir)) continue;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile()) continue;
            $ext = strtolower($f->getExtension());
            if (!in_array($ext, $exts)) continue;
            $files[] = ['zona' => $zona, 'path' => $f->getPathname(), 'ext' => $ext];
        }
    }
    return $files;
}

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Migración WebP</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-6 font-sans text-sm">
<div class="max-w-3xl mx-auto">

  <div class="bg-[#1E3A8A] text-white rounded-2xl px-6 py-4 mb-6">
    <h1 class="font-black text-lg">Migración WebP — Imágenes existentes</h1>
    <p class="text-blue-200 text-xs mt-1">Convierte todos los JPG/PNG/GIF del sitio a WebP y actualiza referencias en BD.</p>
  </div>

<?php

$files = collect_images($zonas, $img_exts);
$total = count($files);

if ($accion === '') {
    // ── Vista previa ────────────────────────────────────────────
    $por_zona = [];
    foreach ($files as $f) {
        $por_zona[$f['zona']] = ($por_zona[$f['zona']] ?? 0) + 1;
    }
    $total_size = array_sum(array_map(fn($f) => filesize($f['path']) ?: 0, $files));
    $mb = round($total_size / 1048576, 1);
?>
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
    <h2 class="font-bold text-gray-700 mb-4">Archivos a convertir: <span class="text-[#1E3A8A]"><?= $total ?></span> (<?= $mb ?> MB)</h2>
    <div class="space-y-2 mb-6">
      <?php foreach ($por_zona as $zona => $cnt): ?>
      <div class="flex justify-between px-4 py-2 rounded-lg bg-gray-50 border border-gray-100">
        <span class="font-semibold text-gray-600"><?= htmlspecialchars($zona) ?></span>
        <span class="font-black text-[#1E3A8A]"><?= $cnt ?> archivo<?= $cnt !== 1 ? 's' : '' ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($total === 0): ?>
    <div class="rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-green-700 font-semibold text-center">
      ✅ No hay imágenes JPG/PNG/GIF para migrar. Todo ya está en WebP.
    </div>
    <?php else: ?>
    <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 text-amber-700 text-xs mb-4">
      <strong>Importante:</strong> El proceso convertirá cada imagen a WebP y eliminará el original solo si la conversión fue exitosa. Las referencias en base de datos también serán actualizadas.
    </div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="accion" value="migrar">
      <button type="submit"
              class="w-full bg-[#1E3A8A] hover:bg-blue-800 text-white font-black px-6 py-3 rounded-xl transition-all">
        Iniciar migración de <?= $total ?> imagen<?= $total !== 1 ? 'es' : '' ?>
      </button>
    </form>
    <?php endif; ?>
  </div>

<?php
} elseif ($accion === 'migrar') {
    // ── Proceso de migración ────────────────────────────────────
    $ok_count   = 0;
    $skip_count = 0;
    $err_count  = 0;
    $log        = [];

    foreach ($files as $f) {
        $path     = $f['path'];
        $ext      = $f['ext'];
        $webp_path = preg_replace('/\.' . preg_quote($ext, '/') . '$/i', '.webp', $path);

        // Si ya existe el .webp correspondiente, saltar
        if (file_exists($webp_path)) {
            $log[] = ['skip', basename($path), 'ya existe .webp'];
            $skip_count++;
            continue;
        }

        $webp_data = img_to_webp($path, $ext);
        if ($webp_data === false) {
            $log[] = ['error', basename($path), 'conversión fallida'];
            $err_count++;
            continue;
        }

        if (@file_put_contents($webp_path, $webp_data) === false) {
            $log[] = ['error', basename($path), 'no se pudo escribir .webp'];
            $err_count++;
            continue;
        }

        // Actualizar referencias en BD
        $ruta_old = '/' . ltrim(str_replace($root, '', str_replace('\\', '/', $path)), '/');
        $ruta_new = preg_replace('/\.' . preg_quote($ext, '/') . '$/i', '.webp', $ruta_old);

        try {
            // media_files
            $pdo->prepare("UPDATE media_files SET ruta = REPLACE(ruta, ?, ?) WHERE ruta LIKE ?")
                ->execute([basename($path), basename($webp_path), '%' . basename($path) . '%']);
            $pdo->prepare("UPDATE media_files SET tipo = 'image/webp' WHERE ruta LIKE '%.webp'")->execute();

            // noticias.imagen
            $pdo->prepare("UPDATE noticias SET imagen = ? WHERE imagen = ?")
                ->execute([basename($ruta_new), basename($ruta_old)]);

            // noticias.contenido (imágenes embebidas en HTML)
            $pdo->prepare("UPDATE noticias SET contenido = REPLACE(contenido, ?, ?) WHERE contenido LIKE ?")
                ->execute([$ruta_old, $ruta_new, '%' . basename($path) . '%']);

            // candidatos_distritales.foto
            $pdo->prepare("UPDATE candidatos_distritales SET foto = ? WHERE foto = ?")
                ->execute([$ruta_new, $ruta_old]);

            // candidatos_distritales.foto_perfil
            $pdo->prepare("UPDATE candidatos_distritales SET foto_perfil = ? WHERE foto_perfil = ?")
                ->execute([$ruta_new, $ruta_old]);

            // configuracion (logo, hero_img, etc.)
            $pdo->prepare("UPDATE configuracion SET valor = REPLACE(valor, ?, ?) WHERE valor LIKE ?")
                ->execute([$ruta_old, $ruta_new, '%' . basename($path) . '%']);
        } catch (Throwable $e) {
            // continuar aunque falle la BD
        }

        // Eliminar original
        @unlink($path);

        $log[] = ['ok', basename($path), '→ ' . basename($webp_path)];
        $ok_count++;
    }
?>
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 mb-6">
    <!-- Resumen -->
    <div class="grid grid-cols-3 gap-3 mb-6">
      <div class="rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-center">
        <div class="text-2xl font-black text-green-700"><?= $ok_count ?></div>
        <div class="text-xs text-green-600 font-semibold">Convertidas</div>
      </div>
      <div class="rounded-xl bg-gray-50 border border-gray-200 px-4 py-3 text-center">
        <div class="text-2xl font-black text-gray-500"><?= $skip_count ?></div>
        <div class="text-xs text-gray-400 font-semibold">Omitidas</div>
      </div>
      <div class="rounded-xl <?= $err_count > 0 ? 'bg-red-50 border-red-200' : 'bg-gray-50 border-gray-200' ?> border px-4 py-3 text-center">
        <div class="text-2xl font-black <?= $err_count > 0 ? 'text-red-600' : 'text-gray-400' ?>"><?= $err_count ?></div>
        <div class="text-xs <?= $err_count > 0 ? 'text-red-500' : 'text-gray-400' ?> font-semibold">Errores</div>
      </div>
    </div>

    <!-- Log detallado -->
    <div class="rounded-xl border border-gray-100 overflow-hidden">
      <div class="bg-gray-50 px-4 py-2 border-b border-gray-100 font-bold text-gray-600 text-xs uppercase tracking-wide">
        Log detallado
      </div>
      <div class="divide-y divide-gray-50 max-h-96 overflow-y-auto">
        <?php foreach ($log as [$status, $name, $detail]): ?>
        <div class="flex items-center gap-3 px-4 py-2 text-xs">
          <?php if ($status === 'ok'): ?>
            <span class="text-green-500 flex-shrink-0">✅</span>
            <span class="text-gray-700 truncate"><?= htmlspecialchars($name) ?></span>
            <span class="text-gray-400 ml-auto flex-shrink-0"><?= htmlspecialchars($detail) ?></span>
          <?php elseif ($status === 'skip'): ?>
            <span class="text-gray-300 flex-shrink-0">—</span>
            <span class="text-gray-400 truncate"><?= htmlspecialchars($name) ?></span>
            <span class="text-gray-300 ml-auto flex-shrink-0"><?= htmlspecialchars($detail) ?></span>
          <?php else: ?>
            <span class="text-red-400 flex-shrink-0">❌</span>
            <span class="text-red-600 truncate"><?= htmlspecialchars($name) ?></span>
            <span class="text-red-400 ml-auto flex-shrink-0"><?= htmlspecialchars($detail) ?></span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="mt-4 flex gap-3">
      <a href="migrate-webp.php"
         class="flex-1 text-center bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold px-4 py-2.5 rounded-xl transition-all text-sm">
        Ver estado actual
      </a>
      <a href="../media.php"
         class="flex-1 text-center bg-[#1E3A8A] hover:bg-blue-800 text-white font-bold px-4 py-2.5 rounded-xl transition-all text-sm">
        Ir a Media →
      </a>
    </div>
  </div>

<?php } ?>

  <div class="text-center text-xs text-gray-400 mt-2">
    <a href="../dashboard.php" class="hover:text-gray-600">← Volver al panel</a>
  </div>
</div>
</body>
</html>
