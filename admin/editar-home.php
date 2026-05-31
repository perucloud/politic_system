<?php
$page_title = 'Editar Home';
include __DIR__ . '/layout.php';
require_once __DIR__ . '/../includes/helpers/webp.php';

$msg = '';
$tipo_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

// ── Guardar cambios ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $campos = ['hero_titulo','hero_subtitulo','hero_eslogan','quien_es_texto','partido_nombre'];
    try {
        $stmt = $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES (?,?)
                               ON DUPLICATE KEY UPDATE valor=VALUES(valor)");
        foreach ($campos as $campo) {
            $stmt->execute([$campo, trim($_POST[$campo] ?? '')]);
        }

        // Subir imagen candidato
        if (!empty($_FILES['imagen']['tmp_name']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $ext   = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
            $allow = ['jpg','jpeg','png','webp'];
            if (in_array($ext, $allow) && $_FILES['imagen']['size'] > 0) {
                $webp_h = img_to_webp($_FILES['imagen']['tmp_name'], $ext);
                if ($webp_h !== false) {
                    @file_put_contents(__DIR__ . '/../assets/img/candidato/joyer-bastidas.webp', $webp_h);
                } else {
                    $contenido = @file_get_contents($_FILES['imagen']['tmp_name']);
                    if ($contenido !== false && strlen($contenido) > 0) {
                        @file_put_contents(__DIR__ . '/../assets/img/candidato/joyer-bastidas.' . $ext, $contenido);
                    }
                }
            }
        }
        $msg = '¡Cambios guardados correctamente!';
        $tipo_msg = 'success';
    } catch (Exception $e) {
        $msg = 'Error al guardar. Inténtalo de nuevo.';
        $tipo_msg = 'error';
    }
}

// ── Cargar config actual ─────────────────────────────────────
$config = [];
try {
    $rows = $pdo->query("SELECT clave, valor FROM configuracion")->fetchAll();
    foreach ($rows as $r) $config[$r['clave']] = $r['valor'];
} catch (Exception $e) {}
$val = fn($k) => htmlspecialchars($config[$k] ?? '');
?>

<?php if ($msg): ?>
<div class="mb-6 rounded-xl px-5 py-4 text-sm font-semibold
            <?= $tipo_msg === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
  <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data"
      class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8 space-y-6">

  <h2 class="font-black text-[#1E3A8A] text-lg">Textos del Hero Principal</h2>

  <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
    <div>
      <label class="block text-sm font-semibold text-gray-700 mb-1.5">Título principal</label>
      <input type="text" name="hero_titulo" value="<?= $val('hero_titulo') ?>"
             class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none">
    </div>
    <div>
      <label class="block text-sm font-semibold text-gray-700 mb-1.5">Subtítulo / Cargo</label>
      <input type="text" name="hero_subtitulo" value="<?= $val('hero_subtitulo') ?>"
             class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none">
    </div>
    <div>
      <label class="block text-sm font-semibold text-gray-700 mb-1.5">Eslogan</label>
      <input type="text" name="hero_eslogan" value="<?= $val('hero_eslogan') ?>"
             class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none">
    </div>
    <div>
      <label class="block text-sm font-semibold text-gray-700 mb-1.5">Nombre del partido</label>
      <input type="text" name="partido_nombre" value="<?= $val('partido_nombre') ?>"
             class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none">
    </div>
  </div>

  <div>
    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Texto "¿Quién es?"</label>
    <textarea name="quien_es_texto" rows="4"
              class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none resize-none"
              ><?= $val('quien_es_texto') ?></textarea>
  </div>

  <div>
    <label class="block text-sm font-semibold text-gray-700 mb-1.5">Foto del candidato (PNG/JPG)</label>
    <div class="flex items-center gap-4">
      <img src="<?= BASE_URL ?>/assets/img/candidato/joyer-bastidas-2.webp"
           alt="Candidato" class="w-16 h-16 rounded-xl object-cover border"
           onerror="this.style.display='none'">
      <input type="file" name="imagen" accept="image/*"
             class="text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-blue-50 file:text-blue-700 file:font-semibold hover:file:bg-blue-100 cursor-pointer">
    </div>
  </div>

  <button type="submit"
          class="bg-[#FACC15] hover:bg-yellow-400 text-[#1E3A8A] font-black px-8 py-3 rounded-xl transition-all shadow text-sm">
    Guardar cambios
  </button>
</form>

    </main>
  </div>
</body>
</html>
