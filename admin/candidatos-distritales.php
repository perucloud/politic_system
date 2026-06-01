<?php
// ============================================================
// candidatos-distritales.php — Lista de candidatos distritales
// Requiere rol mínimo: editor
// ============================================================
session_start();
require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_login();
require_rol('editor');
require_modulo('candidatos_distritales', $pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

// ── POST: acción delete ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    require_rol('admin');
    $del_id = isset($_POST['id']) ? (int)$_POST['id'] : -1;
    if ($del_id >= 0) {
        try {
            // Obtener slug antes de borrar
            $stmt = $pdo->prepare("SELECT slug FROM candidatos_distritales WHERE id = ?");
            $stmt->execute([$del_id]);
            $del_row = $stmt->fetch();

            if ($del_row) {
                $del_slug = $del_row['slug'];

                // Eliminar registro de BD
                $stmt = $pdo->prepare("DELETE FROM candidatos_distritales WHERE id = ?");
                $stmt->execute([$del_id]);

                // Eliminar wrapper PHP del slug (no tocar _ver.php ni _plantilla.php)
                $wrapper = __DIR__ . '/../../distritales/' . $del_slug . '.php';
                if (file_exists($wrapper)) {
                    @unlink($wrapper);
                }

                // Eliminar directorio de assets del slug
                $assets_dir = __DIR__ . '/../../assets/img/distritales/' . $del_slug . '/';
                if (is_dir($assets_dir)) {
                    $files = array_diff(scandir($assets_dir), ['.', '..']);
                    array_map(fn($f) => @unlink($assets_dir . $f), $files);
                    @rmdir($assets_dir);
                }

                log_activity($pdo, 'Eliminó distrito: ' . $del_slug, 'candidatos_distritales');
            }
        } catch (Exception $e) {
            // Silently fail; redirect will still happen
        }
    }
    header('Location: candidatos-distritales.php?msg=deleted');
    exit;
}

// ── POST: acción toggle activo ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    $tog_id = isset($_POST['id']) ? (int)$_POST['id'] : -1;
    $toggle_ok = false;
    if ($tog_id >= 0) {
        try {
            $affected = $pdo->prepare("UPDATE candidatos_distritales SET activo = NOT activo WHERE id = ?");
            $affected->execute([$tog_id]);
            $toggle_ok = $affected->rowCount() > 0;

            $slug_log = $pdo->prepare("SELECT slug, activo FROM candidatos_distritales WHERE id = ?");
            $slug_log->execute([$tog_id]);
            $sl_row = $slug_log->fetch();
            $sl = $sl_row['slug'] ?? '';
            $nuevo_estado = $sl_row['activo'] ? 'activó' : 'desactivó';
            log_activity($pdo, ucfirst($nuevo_estado) . ' distrito: ' . $sl, 'candidatos_distritales');
        } catch (Exception $e) {
            $toggle_ok = false;
        }
    }

    // Purgar caché de LiteSpeed para que el sitio público se actualice inmediatamente
    header('X-LiteSpeed-Purge: *');

    $msg = $toggle_ok ? 'toggled' : 'error';
    header('Location: candidatos-distritales.php?msg=' . $msg);
    exit;
}

// ── Cargar todos los registros de la BD ──────────────────────
$candidatos = [];
try {
    $rows = $pdo->query("SELECT * FROM candidatos_distritales ORDER BY orden ASC, id ASC")->fetchAll();
    foreach ($rows as $r) {
        $candidatos[$r['slug']] = $r;
    }
} catch (Exception $e) {
    $candidatos = [];
}

// ── Flash messages ────────────────────────────────────────────
$msg = trim($_GET['msg'] ?? '');

// ── Incluir layout DESPUÉS de toda la lógica PHP ─────────────
$page_title = 'Candidatos Distritales';
include __DIR__ . '/layout.php';
$msg_map  = [
    'updated' => ['tipo' => 'success', 'texto' => 'Candidato actualizado correctamente.'],
    'created' => ['tipo' => 'success', 'texto' => 'Distrito creado correctamente. Ahora puedes completar la información.'],
    'deleted' => ['tipo' => 'success', 'texto' => 'Distrito eliminado correctamente.'],
    'toggled' => ['tipo' => 'success', 'texto' => 'Estado del distrito actualizado correctamente.'],
    'error'   => ['tipo' => 'error',   'texto' => 'Ocurrió un error. Inténtalo de nuevo.'],
];
$flash = $msg_map[$msg] ?? null;
?>

<?php if ($flash): ?>
<div class="mb-5 flex items-center gap-3 rounded-xl px-5 py-3.5 text-sm font-semibold
            <?= $flash['tipo'] === 'success'
                ? 'bg-green-50 text-green-700 border border-green-200'
                : 'bg-red-50 text-red-700 border border-red-200' ?>"
     x-data="{ show: true }" x-show="show">
  <?php if ($flash['tipo'] === 'success'): ?>
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
    </svg>
  <?php else: ?>
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
    </svg>
  <?php endif; ?>
  <?= htmlspecialchars($flash['texto']) ?>
  <button @click="show=false" class="ml-auto text-current opacity-50 hover:opacity-100 transition-opacity">✕</button>
</div>
<?php endif; ?>

<!-- ── Cabecera de sección ──────────────────────────────────── -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
  <div>
    <h2 class="text-xl font-black text-gray-800">Candidatos Distritales</h2>
    <p class="text-sm text-gray-400 mt-0.5">
      Distritos de la provincia de Satipo — edita la información de cada candidato
    </p>
  </div>
  <div class="flex items-center gap-3">
    <div class="flex items-center gap-2 text-xs text-gray-400">
      <span class="inline-flex items-center gap-1.5 bg-green-50 text-green-700 font-semibold px-3 py-1.5 rounded-full border border-green-200">
        <span class="w-2 h-2 rounded-full bg-green-500 inline-block"></span>
        Activo
      </span>
      <span class="inline-flex items-center gap-1.5 bg-gray-100 text-gray-500 font-semibold px-3 py-1.5 rounded-full border border-gray-200">
        <span class="w-2 h-2 rounded-full bg-gray-400 inline-block"></span>
        Inactivo
      </span>
    </div>
    <a href="candidato-nuevo.php"
       class="inline-flex items-center gap-2 bg-[#1E3A8A] hover:bg-blue-900 text-white text-sm font-bold px-4 py-2 rounded-xl transition-all shadow-sm">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
      </svg>
      Nuevo Distrito
    </a>
  </div>
</div>

<!-- ── Grid de cards ─────────────────────────────────────────── -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
  <?php foreach ($candidatos as $slug_key => $c):
      $nombre   = trim($c['nombre'] ?? '');
      $foto     = trim($c['foto']   ?? '');
      $activo   = (bool)($c['activo'] ?? false);
      $id       = (int)$c['id'];
      $distrito = $c['distrito'] ?? ucwords(str_replace('-', ' ', $slug_key));

      // Iniciales para fallback
      $iniciales = '';
      if ($nombre) {
          $parts = explode(' ', $nombre);
          $iniciales = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));
      }
      if (!$iniciales) {
          $dp = explode('-', $slug_key);
          $iniciales = strtoupper(substr($dp[0] ?? '', 0, 1) . substr($dp[1] ?? '', 0, 1));
      }

      // Color de avatar por distrito (variedad visual)
      $avatar_colors = [
          'rio-negro'     => 'from-blue-600 to-blue-900',
          'pangoa'        => 'from-emerald-500 to-teal-700',
          'rio-tambo'     => 'from-sky-500 to-blue-700',
          'coviriali'     => 'from-violet-500 to-purple-700',
          'llaylla'       => 'from-amber-500 to-orange-600',
          'vizcatan'      => 'from-rose-500 to-red-700',
          'pampa-hermosa' => 'from-lime-500 to-green-700',
          'mazamari'      => 'from-cyan-500 to-teal-700',
      ];
      $grad = $avatar_colors[$slug_key] ?? 'from-indigo-500 to-indigo-800';
  ?>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden
              hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 flex flex-col">

    <!-- Franja superior de color -->
    <div class="h-1.5 w-full bg-gradient-to-r <?= $grad ?>"></div>

    <div class="p-5 flex flex-col flex-1">

      <!-- Foto + distrito -->
      <div class="flex items-center gap-4 mb-4">
        <!-- Avatar circular 64px -->
        <div class="flex-shrink-0 w-16 h-16 rounded-full overflow-hidden ring-2 ring-white shadow-md">
          <?php if ($foto): ?>
            <img src="<?= htmlspecialchars($foto) ?>"
                 alt="<?= htmlspecialchars($nombre ?: $distrito) ?>"
                 class="w-full h-full object-cover"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <div class="w-full h-full bg-gradient-to-br <?= $grad ?> items-center justify-center hidden">
              <span class="text-white font-black text-lg"><?= htmlspecialchars($iniciales) ?></span>
            </div>
          <?php else: ?>
            <div class="w-full h-full bg-gradient-to-br <?= $grad ?> flex items-center justify-center">
              <span class="text-white font-black text-lg"><?= htmlspecialchars($iniciales) ?></span>
            </div>
          <?php endif; ?>
        </div>

        <div class="min-w-0 flex-1">
          <!-- Nombre del distrito -->
          <p class="text-xs font-black text-[#1E3A8A] uppercase tracking-wide leading-tight truncate">
            <?= htmlspecialchars($distrito) ?>
          </p>
          <!-- Nombre del candidato -->
          <p class="text-sm font-semibold text-gray-800 mt-1 leading-tight
                    <?= !$nombre ? 'italic text-gray-400' : '' ?>">
            <?= $nombre ? htmlspecialchars($nombre) : '[Sin asignar]' ?>
          </p>
        </div>
      </div>

      <!-- Acciones -->
      <div class="flex flex-col gap-2 mt-auto">

        <!-- Fila: Editar + Ver sitio -->
        <div class="flex items-center gap-2">
          <a href="candidato-form.php?id=<?= $id ?>"
             class="flex-1 inline-flex items-center justify-center gap-1.5 bg-[#1E3A8A] hover:bg-blue-900
                    text-white text-xs font-bold px-3 py-2 rounded-xl transition-all shadow-sm">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Editar
          </a>
          <a href="<?= BASE_URL ?>/distritales/<?= htmlspecialchars($slug_key) ?>.php"
             target="_blank" title="Ver página pública"
             class="inline-flex items-center justify-center w-8 h-8 bg-[#FACC15] hover:bg-yellow-400
                    text-[#1E3A8A] rounded-xl transition-all shadow-sm flex-shrink-0">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
            </svg>
          </a>
        </div>

        <!-- Toggle pill Activo / Inactivo -->
        <form method="POST"
              x-data="{ on: <?= $activo ? 'true' : 'false' ?> }">
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id" value="<?= $id ?>">
          <button type="button"
                  @click="on = !on; $nextTick(() => $el.closest('form').submit())"
                  :class="on ? 'bg-green-500 shadow-green-200' : 'bg-gray-200 shadow-gray-100'"
                  class="relative w-full h-9 rounded-full p-0.5 transition-colors duration-300
                         cursor-pointer border-0 shadow-md">
            <!-- Knob deslizante -->
            <div :class="on ? 'left-0.5 right-[50%]' : 'left-[50%] right-0.5'"
                 class="absolute top-0.5 bottom-0.5 rounded-full bg-white shadow-sm
                        transition-all duration-300 ease-in-out"></div>
            <!-- Labels -->
            <div class="relative flex h-full z-10 select-none">
              <span :class="on ? 'text-green-700' : 'text-gray-400'"
                    class="flex-1 flex items-center justify-center text-[11px] font-black
                           transition-colors duration-300 gap-1">
                <span :class="on ? 'bg-green-400' : 'bg-gray-300'"
                      class="w-1.5 h-1.5 rounded-full inline-block transition-colors duration-300"></span>
                Activo
              </span>
              <span :class="on ? 'text-green-100' : 'text-gray-600'"
                    class="flex-1 flex items-center justify-center text-[11px] font-black
                           transition-colors duration-300">
                Inactivo
              </span>
            </div>
          </button>
        </form>

        <!-- Botón Eliminar → abre modal -->
        <button type="button"
                @click="window.dispatchEvent(new CustomEvent('confirmar-eliminar', {
                  detail: { id: <?= $id ?>, distrito: '<?= htmlspecialchars(addslashes($distrito)) ?>' }
                }))"
                class="w-full inline-flex items-center justify-center gap-1.5 bg-red-50 hover:bg-red-100
                       text-red-600 border border-red-200 text-xs font-bold px-3 py-2 rounded-xl transition-all">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
          </svg>
          Eliminar
        </button>

      </div>

    </div>
  </div>
  <?php endforeach; ?>

  <?php if (empty($candidatos)): ?>
  <div class="col-span-full text-center py-16 text-gray-400">
    <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
    </svg>
    <p class="text-sm font-semibold">No hay distritos registrados.</p>
    <a href="candidato-nuevo.php" class="mt-3 inline-block text-[#1E3A8A] font-bold text-sm hover:underline">
      + Crear el primer distrito
    </a>
  </div>
  <?php endif; ?>
</div>

<!-- ── Nota informativa ──────────────────────────────────────── -->
<div class="mt-6 bg-blue-50 border border-blue-200 rounded-2xl px-5 py-4 flex items-start gap-3">
  <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
  </svg>
  <div>
    <p class="text-sm font-semibold text-blue-800">Gestión de distritos</p>
    <p class="text-xs text-blue-600 mt-0.5">
      Usa "Nuevo Distrito" para agregar un distrito al sistema. Usa "Editar" para actualizar nombre, foto,
      biografía, propuestas y PDF del plan de gobierno. Usa "Eliminar" para remover un distrito y sus archivos.
    </p>
  </div>
</div>

    </main>
  </div>

  <!-- ══ MODAL CONFIRMACIÓN ELIMINAR ══════════════════════════ -->
  <div x-data="{
         abierto: false,
         distrito: '',
         eliminarId: null,
         abrir(e) { this.distrito = e.detail.distrito; this.eliminarId = e.detail.id; this.abierto = true; },
         confirmar() { document.getElementById('form-eliminar-' + this.eliminarId).submit(); }
       }"
       @confirmar-eliminar.window="abrir($event)">

    <!-- Formularios ocultos de eliminación (uno por candidato) -->
    <?php foreach ($candidatos as $c): ?>
    <form id="form-eliminar-<?= $c['id'] ?>" method="POST" style="display:none">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= $c['id'] ?>">
    </form>
    <?php endforeach; ?>

    <!-- Overlay + Modal -->
    <div x-show="abierto" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[100] flex items-center justify-center p-4">

      <!-- Backdrop -->
      <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"
           @click="abierto = false"></div>

      <!-- Card del modal -->
      <div x-show="abierto"
           x-transition:enter="transition ease-out duration-250"
           x-transition:enter-start="opacity-0 scale-90 translate-y-4"
           x-transition:enter-end="opacity-100 scale-100 translate-y-0"
           x-transition:leave="transition ease-in duration-150"
           x-transition:leave-start="opacity-100 scale-100 translate-y-0"
           x-transition:leave-end="opacity-0 scale-90 translate-y-4"
           class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md z-10 overflow-hidden">

        <!-- Header rojo -->
        <div class="bg-gradient-to-r from-red-500 to-rose-600 px-6 py-5 text-white">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
              </svg>
            </div>
            <div>
              <h3 class="font-black text-lg leading-tight">Eliminar Distrito</h3>
              <p class="text-red-100 text-xs mt-0.5">Esta acción no se puede deshacer</p>
            </div>
          </div>
        </div>

        <!-- Cuerpo -->
        <div class="px-6 py-6">
          <p class="text-gray-700 text-sm leading-relaxed">
            ¿Estás seguro de que deseas eliminar el distrito de
            <strong class="text-gray-900" x-text="'«' + distrito + '»'"></strong>?
          </p>
          <div class="mt-4 bg-red-50 border border-red-200 rounded-xl px-4 py-3 space-y-1.5">
            <p class="text-xs text-red-700 font-semibold flex items-center gap-2">
              <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
              Se eliminará el registro del candidato
            </p>
            <p class="text-xs text-red-700 font-semibold flex items-center gap-2">
              <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
              Se borrarán las fotos y archivos del candidato
            </p>
            <p class="text-xs text-red-700 font-semibold flex items-center gap-2">
              <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
              La página pública del distrito dejará de funcionar
            </p>
          </div>
        </div>

        <!-- Botones -->
        <div class="px-6 pb-6 flex items-center gap-3">
          <button @click="abierto = false"
                  class="flex-1 py-3 rounded-xl border-2 border-gray-200 text-gray-600 font-bold text-sm
                         hover:bg-gray-50 transition-all duration-200">
            Cancelar
          </button>
          <button @click="confirmar()"
                  class="flex-1 py-3 rounded-xl bg-gradient-to-r from-red-500 to-rose-600
                         hover:from-red-600 hover:to-rose-700 text-white font-black text-sm
                         shadow-lg shadow-red-500/30 hover:shadow-red-500/50 transition-all duration-200
                         hover:-translate-y-0.5 active:scale-95">
            Sí, eliminar
          </button>
        </div>
      </div>
    </div>
  </div>

</body>
</html>
