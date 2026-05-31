<?php
$page_title = 'Backup';
include __DIR__ . '/layout.php';

// ── Solo admin ──────────────────────────────────────────────────
if (!in_array($admin_rol, ['admin', 'superadmin'])) {
    echo '<p class="text-red-600 font-semibold">Acceso denegado.</p>';
    echo '</main></div></body></html>';
    exit;
}

$action = $_POST['action'] ?? '';

// ══════════════════════════════════════════════════════════════════
// HELPERS
// ══════════════════════════════════════════════════════════════════

function backup_generate_sql(PDO $pdo): string
{
    $sql  = "-- Backup generado: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        // Estructura
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql .= $create[1] . ";\n\n";

        // Datos
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) { $sql .= "\n"; continue; }

        $cols = '`' . implode('`,`', array_keys($rows[0])) . '`';
        $sql .= "INSERT INTO `$table` ($cols) VALUES\n";

        $lines = [];
        foreach ($rows as $row) {
            $vals = array_map(function ($v) use ($pdo) {
                if ($v === null) return 'NULL';
                return $pdo->quote((string)$v);
            }, array_values($row));
            $lines[] = '(' . implode(',', $vals) . ')';
        }
        $sql .= implode(",\n", $lines) . ";\n\n";
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $sql;
}

function backup_add_dir_to_zip(ZipArchive $zip, string $dir, string $base, array $exclude = []): void
{
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iter as $file) {
        $realPath = $file->getRealPath();
        $relPath  = str_replace('\\', '/', substr($realPath, strlen($base) + 1));

        foreach ($exclude as $pat) {
            if (str_starts_with($relPath, $pat)) continue 2;
        }

        if ($file->isDir()) {
            $zip->addEmptyDir($relPath);
        } else {
            $zip->addFile($realPath, $relPath);
        }
    }
}

// ══════════════════════════════════════════════════════════════════
// ACCION: descargar solo DB
// ══════════════════════════════════════════════════════════════════
if ($action === 'db') {
    csrf_verify();
    $filename = 'backup_db_' . date('Ymd_His') . '.sql';
    $sql = backup_generate_sql($pdo);

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($sql));
    header('Pragma: no-cache');
    echo $sql;
    exit;
}

// ══════════════════════════════════════════════════════════════════
// ACCION: descargar archivos + DB (ZIP)
// ══════════════════════════════════════════════════════════════════
if ($action === 'full') {
    csrf_verify();

    if (!class_exists('ZipArchive')) {
        // Fallback: solo SQL si no hay ZipArchive
        $filename = 'backup_db_' . date('Ymd_His') . '.sql';
        $sql = backup_generate_sql($pdo);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($sql));
        header('Pragma: no-cache');
        echo $sql;
        exit;
    }

    $tmpFile  = sys_get_temp_dir() . '/backup_full_' . time() . '.zip';
    $filename = 'backup_full_' . date('Ymd_His') . '.zip';
    $rootDir  = dirname(__DIR__);

    $zip = new ZipArchive();
    if ($zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        die('No se pudo crear el archivo ZIP.');
    }

    // Directorios/prefijos a excluir del ZIP
    $exclude = [
        'admin/backup.php',   // este mismo archivo no es necesario excluirlo pero referencial
        '.git/',
        'node_modules/',
        'vendor/',
        'admin/tools/migrate-webp.php', // herramienta de migración puntual
    ];

    backup_add_dir_to_zip($zip, $rootDir, $rootDir, $exclude);

    // Añadir volcado SQL dentro del ZIP
    $sqlContent = backup_generate_sql($pdo);
    $zip->addFromString('backup_db_' . date('Ymd_His') . '.sql', $sqlContent);

    $zip->close();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmpFile));
    header('Pragma: no-cache');
    readfile($tmpFile);
    @unlink($tmpFile);
    exit;
}

// ══════════════════════════════════════════════════════════════════
// VISTA
// ══════════════════════════════════════════════════════════════════

// Detectar si ZipArchive está disponible
$zip_ok = class_exists('ZipArchive');

// Tamaño aproximado del directorio
function backup_dir_size(string $dir): int
{
    $size = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)) as $f) {
        if ($f->isFile()) $size += $f->getSize();
    }
    return $size;
}
function backup_human_size(int $bytes): string
{
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)    return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)       return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}

$root_dir   = dirname(__DIR__);
$dir_size   = backup_dir_size($root_dir);
$table_count = count($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN));
?>

<!-- INFO CARDS -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex items-center gap-4">
    <div class="w-11 h-11 rounded-xl bg-blue-50 flex items-center justify-center text-[#1E3A8A]">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7M4 7c0-2 1-3 3-3h10c2 0 3 1 3 3M4 7h16M10 12h4"/>
      </svg>
    </div>
    <div>
      <p class="text-xs text-gray-400 uppercase tracking-wide">Tablas en DB</p>
      <p class="text-2xl font-black text-[#1E3A8A]"><?= $table_count ?></p>
    </div>
  </div>

  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex items-center gap-4">
    <div class="w-11 h-11 rounded-xl bg-yellow-50 flex items-center justify-center text-yellow-600">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
      </svg>
    </div>
    <div>
      <p class="text-xs text-gray-400 uppercase tracking-wide">Tamaño archivos</p>
      <p class="text-2xl font-black text-yellow-600"><?= backup_human_size($dir_size) ?></p>
    </div>
  </div>

  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex items-center gap-4">
    <div class="w-11 h-11 rounded-xl <?= $zip_ok ? 'bg-green-50 text-green-600' : 'bg-red-50 text-red-500' ?> flex items-center justify-center">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="<?= $zip_ok ? 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' : 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z' ?>"/>
      </svg>
    </div>
    <div>
      <p class="text-xs text-gray-400 uppercase tracking-wide">ZipArchive PHP</p>
      <p class="text-sm font-black <?= $zip_ok ? 'text-green-600' : 'text-red-500' ?>">
        <?= $zip_ok ? 'Disponible' : 'No instalado' ?>
      </p>
    </div>
  </div>
</div>

<!-- OPCIONES DE BACKUP -->
<div class="grid grid-cols-1 sm:grid-cols-2 gap-6">

  <!-- Solo DB -->
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
    <div class="flex items-center gap-3 mb-4">
      <div class="w-12 h-12 rounded-2xl bg-blue-50 flex items-center justify-center text-[#1E3A8A]">
        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 7v10c0 2 1 3 3 3h10c2 0 3-1 3-3V7M4 7c0-2 1-3 3-3h10c2 0 3 1 3 3M4 7h16"/>
        </svg>
      </div>
      <div>
        <h2 class="font-black text-[#1E3A8A] text-base">Solo Base de Datos</h2>
        <p class="text-xs text-gray-400"><?= $table_count ?> tablas · archivo .sql</p>
      </div>
    </div>
    <p class="text-sm text-gray-500 mb-5 leading-relaxed">
      Exporta todas las tablas y datos de la base de datos en formato SQL. Ideal para migraciones o respaldo rápido.
    </p>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="db">
      <button type="submit"
              class="w-full inline-flex items-center justify-center gap-2 bg-[#1E3A8A] hover:bg-blue-900 text-white font-bold px-6 py-3 rounded-xl transition-all shadow text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
        </svg>
        Descargar SQL
      </button>
    </form>
  </div>

  <!-- Archivos + DB -->
  <div class="bg-white rounded-2xl border <?= $zip_ok ? 'border-gray-100' : 'border-yellow-200 bg-yellow-50/30' ?> shadow-sm p-6">
    <div class="flex items-center gap-3 mb-4">
      <div class="w-12 h-12 rounded-2xl bg-green-50 flex items-center justify-center text-green-600">
        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
        </svg>
      </div>
      <div>
        <h2 class="font-black text-[#1E3A8A] text-base">Archivos completos + DB</h2>
        <p class="text-xs text-gray-400"><?= backup_human_size($dir_size) ?> · archivo .zip</p>
      </div>
    </div>
    <p class="text-sm text-gray-500 mb-2 leading-relaxed">
      Comprime todos los archivos del sitio (PHP, imágenes, assets, uploads) junto con el volcado SQL en un único <code class="bg-gray-100 px-1 rounded">.zip</code>.
    </p>
    <?php if (!$zip_ok): ?>
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl px-4 py-3 text-xs text-yellow-700 font-semibold mb-4">
      ZipArchive no está habilitado en este servidor. Se descargará solo el SQL como alternativa.
    </div>
    <?php endif; ?>
    <p class="text-xs text-gray-400 mb-5">
      <strong>Nota:</strong> en proyectos grandes puede tardar varios segundos. No cierres la pestaña.
    </p>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="full">
      <button type="submit"
              class="w-full inline-flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 text-white font-bold px-6 py-3 rounded-xl transition-all shadow text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
        </svg>
        Descargar ZIP completo
      </button>
    </form>
  </div>

</div>

<!-- AVISO DE SEGURIDAD -->
<div class="mt-6 bg-blue-50 border border-blue-200 rounded-2xl px-5 py-4 flex gap-3">
  <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
  </svg>
  <p class="text-xs text-blue-700 leading-relaxed">
    <strong>Recomendación:</strong> guarda los backups en un lugar seguro fuera del servidor (Google Drive, disco externo).
    El archivo ZIP incluye credenciales de conexión a la DB — no lo compartas públicamente.
  </p>
</div>

    </main>
  </div>
</body>
</html>
