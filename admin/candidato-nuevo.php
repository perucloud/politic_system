<?php
// ============================================================
// candidato-nuevo.php — Crear nuevo distrito distrital
// Requiere rol mínimo: admin
// ============================================================
// Bootstrap manual ANTES del layout para poder usar header()
session_start();
require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_login();
require_rol('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

// ── Variables del formulario ──────────────────────────────────
$errors   = [];
$f_distrito = '';
$f_slug     = '';
$f_orden    = 9;
$f_activo   = 1;

// ── Procesar POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $f_distrito = trim($_POST['distrito'] ?? '');
    $f_slug     = trim($_POST['slug']     ?? '');
    $f_orden    = (int)($_POST['orden']   ?? 0);
    $f_activo   = isset($_POST['activo']) ? 1 : 0;

    // Validaciones
    if ($f_distrito === '') {
        $errors[] = 'El nombre del distrito es obligatorio.';
    }
    if ($f_slug === '') {
        $errors[] = 'El slug es obligatorio.';
    } elseif (!preg_match('/^[a-z0-9\-]+$/', $f_slug)) {
        $errors[] = 'El slug solo puede contener letras minúsculas, números y guiones (sin espacios ni caracteres especiales).';
    } else {
        // Verificar que el slug no exista ya en BD
        try {
            $chk = $pdo->prepare("SELECT id FROM candidatos_distritales WHERE slug = ?");
            $chk->execute([$f_slug]);
            if ($chk->fetch()) {
                $errors[] = 'Ya existe un distrito con ese slug. Elige uno diferente.';
            }
        } catch (Exception $e) {
            $errors[] = 'Error al verificar el slug en la base de datos.';
        }
    }

    if (empty($errors)) {
        try {
            // Insertar en BD
            $ins = $pdo->prepare(
                "INSERT INTO candidatos_distritales
                 (distrito, slug, nombre, foto, foto_perfil, bio, propuestas, plan_pdf, activo, orden)
                 VALUES (?, ?, '', '', '', '', NULL, '', ?, ?)"
            );
            $ins->execute([$f_distrito, $f_slug, $f_activo, $f_orden]);
            $new_id = (int)$pdo->lastInsertId();

            // Generar wrapper PHP en /distritales/{slug}.php
            $wrapper_path    = dirname(__DIR__) . '/distritales/' . $f_slug . '.php';
            $wrapper_content = '<?php $candidato_slug = \'' . $f_slug . '\'; require __DIR__ . \'/_ver.php\';' . "\n";
            file_put_contents($wrapper_path, $wrapper_content);

            // Crear directorio de assets del distrito
            $assets_dir = dirname(__DIR__) . '/assets/img/distritales/' . $f_slug . '/';
            if (!is_dir($assets_dir)) {
                @mkdir($assets_dir, 0755, true);
            }

            // Crear directorio de planes PDF si no existe
            $planes_dir = dirname(__DIR__) . '/assets/docs/planes/';
            if (!is_dir($planes_dir)) {
                @mkdir($planes_dir, 0755, true);
            }

            log_activity($pdo, 'Creó nuevo distrito: ' . $f_slug, 'candidatos_distritales');

            header('Location: candidato-form.php?id=' . $new_id . '&msg=created');
            exit;

        } catch (Exception $e) {
            $errors[] = 'Error al guardar en la base de datos: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// ── Incluir layout DESPUÉS de toda la lógica PHP ─────────────
$page_title = 'Nuevo Distrito';
include __DIR__ . '/layout.php';
?>

<!-- ── Cabecera de sección ──────────────────────────────────── -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
  <div>
    <h2 class="text-xl font-black text-gray-800">Nuevo Distrito</h2>
    <p class="text-sm text-gray-400 mt-0.5">
      Crea un nuevo distrito en el sistema de candidatos distritales
    </p>
  </div>
  <a href="candidatos-distritales.php"
     class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-800 font-semibold transition-colors">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
    </svg>
    Volver a la lista
  </a>
</div>

<!-- ── Mensajes de error ─────────────────────────────────────── -->
<?php if (!empty($errors)): ?>
<div class="mb-6 rounded-xl bg-red-50 border border-red-200 px-5 py-4">
  <div class="flex items-start gap-3">
    <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <div>
      <p class="text-sm font-bold text-red-700 mb-1">Por favor corrige los siguientes errores:</p>
      <ul class="text-sm text-red-600 list-disc list-inside space-y-0.5">
        <?php foreach ($errors as $err): ?>
          <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ── Formulario ────────────────────────────────────────────── -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden max-w-2xl">

  <!-- Header de la card -->
  <div class="bg-[#1E3A8A] px-6 py-4">
    <h3 class="text-base font-black text-white flex items-center gap-2">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
      </svg>
      Crear Nuevo Distrito
    </h3>
    <p class="text-blue-200 text-xs mt-0.5">
      Completa los datos básicos. Podrás agregar foto, bio y propuestas en el siguiente paso.
    </p>
  </div>

  <form method="POST" action="candidato-nuevo.php" class="p-6 space-y-5">

    <!-- Nombre del Distrito -->
    <div>
      <label for="distrito" class="block text-sm font-bold text-gray-700 mb-1.5">
        Nombre del Distrito <span class="text-red-500">*</span>
      </label>
      <input type="text"
             id="distrito"
             name="distrito"
             value="<?= htmlspecialchars($f_distrito) ?>"
             placeholder="Ej: Río Tambo"
             required
             class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm text-gray-800
                    focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                    placeholder-gray-300 transition-all"
             oninput="autoSlug(this.value)">
    </div>

    <!-- Slug / URL -->
    <div>
      <label for="slug" class="block text-sm font-bold text-gray-700 mb-1.5">
        Slug / URL <span class="text-red-500">*</span>
      </label>
      <input type="text"
             id="slug"
             name="slug"
             value="<?= htmlspecialchars($f_slug) ?>"
             placeholder="Ej: rio-tambo"
             required
             pattern="[a-z0-9\-]+"
             class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm text-gray-800
                    font-mono focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent
                    placeholder-gray-300 transition-all"
             oninput="updatePreview(this.value)">
      <p class="mt-1.5 text-xs text-gray-400">
        Solo letras minúsculas, números y guiones. Ej: <code class="bg-gray-100 px-1 py-0.5 rounded font-mono">rio-tambo</code>
      </p>
      <!-- Preview URL -->
      <div id="slug-preview"
           class="mt-2 inline-flex items-center gap-1.5 bg-blue-50 border border-blue-100 rounded-lg px-3 py-1.5 text-xs text-blue-600 font-mono <?= $f_slug ? '' : 'hidden' ?>">
        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
        </svg>
        ivancisneros.test/distritales/<span id="slug-preview-text"><?= htmlspecialchars($f_slug) ?></span>
      </div>
    </div>

    <!-- Orden y Activo en fila -->
    <div class="flex items-start gap-4">

      <!-- Orden -->
      <div class="flex-1">
        <label for="orden" class="block text-sm font-bold text-gray-700 mb-1.5">
          Orden de visualización
        </label>
        <input type="number"
               id="orden"
               name="orden"
               value="<?= (int)$f_orden ?>"
               min="0"
               max="99"
               class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm text-gray-800
                      focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
        <p class="mt-1 text-xs text-gray-400">Número menor = aparece primero</p>
      </div>

      <!-- Activo -->
      <div class="pt-1">
        <label class="block text-sm font-bold text-gray-700 mb-1.5">Estado</label>
        <label class="inline-flex items-center gap-2.5 cursor-pointer mt-1">
          <input type="checkbox"
                 id="activo"
                 name="activo"
                 value="1"
                 <?= $f_activo ? 'checked' : '' ?>
                 class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500">
          <span class="text-sm font-semibold text-gray-700">Activo</span>
        </label>
      </div>

    </div>

    <!-- Nota informativa -->
    <div class="rounded-xl bg-amber-50 border border-amber-200 px-4 py-3 flex items-start gap-3">
      <svg class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      <p class="text-xs text-amber-700 font-medium">
        Podrás agregar foto, bio y propuestas en el siguiente paso después de crear el distrito.
      </p>
    </div>

    <!-- Botones -->
    <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
      <button type="submit"
              class="inline-flex items-center gap-2 bg-[#1E3A8A] hover:bg-blue-900 text-white
                     text-sm font-bold px-6 py-2.5 rounded-xl transition-all shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 4v16m8-8H4"/>
        </svg>
        Crear Distrito
      </button>
      <a href="candidatos-distritales.php"
         class="text-sm font-semibold text-gray-500 hover:text-gray-800 transition-colors px-4 py-2.5">
        Cancelar
      </a>
    </div>

  </form>
</div>

<script>
function slugify(text) {
    return text.toLowerCase()
        .normalize('NFD').replace(/[̀-ͯ]/g, '') // remove accents
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .trim();
}

function updatePreview(val) {
    const preview = document.getElementById('slug-preview');
    const previewText = document.getElementById('slug-preview-text');
    if (val) {
        preview.classList.remove('hidden');
        previewText.textContent = val;
    } else {
        preview.classList.add('hidden');
    }
}

function autoSlug(val) {
    const slugInput = document.getElementById('slug');
    // Only auto-fill if slug hasn't been manually edited
    if (!slugInput.dataset.manuallyEdited) {
        const generated = slugify(val);
        slugInput.value = generated;
        updatePreview(generated);
    }
}

// Mark slug as manually edited when user types in it directly
document.getElementById('slug').addEventListener('input', function() {
    this.dataset.manuallyEdited = 'true';
    updatePreview(this.value);
});
</script>

    </main>
  </div>
</body>
</html>
