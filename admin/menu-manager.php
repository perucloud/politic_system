<?php
if (ob_get_level() === 0) ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_login();
require_rol('admin');
require_modulo('menu_web', $pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

$msg      = '';
$tipo_msg = '';
$editing  = null;

// ── Toggle visible ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_visible') {
    try { $pdo->prepare("UPDATE menu_items SET visible=IF(visible=1,0,1) WHERE id=?")->execute([(int)($_POST['id'] ?? 0)]); } catch (Exception $e) {}
    header('Location: menu-manager.php?ok=toggle'); exit;
}

// ── Eliminar ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_item') {
    try {
        $del_id = (int)($_POST['id'] ?? 0);
        // Promover hijos a raíz antes de eliminar el padre
        $pdo->prepare("UPDATE menu_items SET parent_id=NULL WHERE parent_id=?")->execute([$del_id]);
        $pdo->prepare("DELETE FROM menu_items WHERE id=?")->execute([$del_id]);
    } catch (Exception $e) {}
    header('Location: menu-manager.php?ok=del'); exit;
}

// ── Cargar para editar ────────────────────────────────────────
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $r = $pdo->prepare("SELECT * FROM menu_items WHERE id=?");
    $r->execute([(int)$_GET['edit']]); $editing = $r->fetch();
}

// ── Guardar (crear / actualizar) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id   = (int)($_POST['item_id'] ?? 0);
    $label     = trim($_POST['label']    ?? '');
    $url_raw   = trim($_POST['url']      ?? '/');
    $tipo      = in_array($_POST['tipo']??'', ['interno','pagina','noticia','externo','auto-candidatos'])
                 ? $_POST['tipo'] : 'interno';
    $target    = $_POST['target'] === '_blank' ? '_blank' : '_self';
    $parent_id = isset($_POST['parent_id']) && $_POST['parent_id'] !== ''
                 ? (int)$_POST['parent_id'] : null;
    // auto-candidatos siempre en raíz
    if ($tipo === 'auto-candidatos') $parent_id = null;

    if (!$label) {
        $msg = 'El nombre es obligatorio.'; $tipo_msg = 'error';
    } else {
        try {
            if ($item_id) {
                $pdo->prepare("UPDATE menu_items SET label=?,url=?,tipo=?,target=?,parent_id=? WHERE id=?")
                    ->execute([$label,$url_raw,$tipo,$target,$parent_id,$item_id]);
            } else {
                $max = (int)$pdo->query("SELECT COALESCE(MAX(orden),0) FROM menu_items")->fetchColumn();
                $pdo->prepare("INSERT INTO menu_items (label,url,tipo,target,orden,visible,parent_id) VALUES (?,?,?,?,?,1,?)")
                    ->execute([$label,$url_raw,$tipo,$target,$max+10,$parent_id]);
            }
            header('Location: menu-manager.php?ok=saved'); exit;
        } catch (Exception $e) { $msg = 'Error al guardar.'; $tipo_msg = 'error'; }
    }
}

// ── Cargar árbol de ítems ─────────────────────────────────────
$root_items = [];
$child_map  = [];
try {
    $all = $pdo->query("SELECT * FROM menu_items ORDER BY orden ASC")->fetchAll();
    foreach ($all as $it) {
        if ($it['parent_id'] === null) $root_items[] = $it;
        else $child_map[(int)$it['parent_id']][] = $it;
    }
} catch (Exception $e) {}

// ── Páginas publicadas para el selector ─────────────────────
$paginas_list = [];
try { $paginas_list = $pdo->query("SELECT id,titulo,slug FROM paginas WHERE estado='publicado' ORDER BY titulo ASC")->fetchAll(); } catch (Exception $e) {}

$index_sections = [
    '/index.php' => 'Inicio',
    '/index.php#quien-es' => 'Quien es',
    '/index.php#plan' => 'Plan de Gobierno',
    '/index.php#equipo' => 'Equipo distrital',
    '/index.php#redes-sociales' => 'Redes sociales',
    '/index.php#noticias' => 'Noticias',
    '/index.php#unete' => 'Unete',
    '/index.php#agenda' => 'Agenda',
    '/index.php#local' => 'Local / Contacto',
];

$page_title = 'Gestor de Menú';
include __DIR__ . '/layout.php';

$tipo_colors = [
    'interno'         => 'bg-blue-100 text-blue-700',
    'pagina'          => 'bg-violet-100 text-violet-700',
    'noticia'         => 'bg-sky-100 text-sky-700',
    'externo'         => 'bg-orange-100 text-orange-700',
    'auto-candidatos' => 'bg-green-100 text-green-700',
];
$tipo_labels = [
    'interno'         => 'Interno',
    'pagina'          => 'Página',
    'noticia'         => 'Noticia',
    'externo'         => 'Externo',
    'auto-candidatos' => 'Auto-Candidatos',
];
?>

<style>
  .sortable-ghost    { opacity:.35; background:#EEF2FF !important; border:2px dashed #6366F1 !important; border-radius:.75rem; }
  .sortable-chosen   { box-shadow:0 8px 24px rgba(99,102,241,.25); z-index:10; }
  .drag-handle       { cursor:grab; touch-action:none; }
  .drag-handle:active{ cursor:grabbing; }

  /* Zona de drop siempre visible */
  .nested-sortable {
    min-height: 36px;
    margin-top: 4px;
    border-radius: .6rem;
    border: 2px dashed #E0E7FF;
    background: #F8F9FF;
    transition: all .2s ease;
    position: relative;
  }
  /* Texto de ayuda cuando está vacía */
  .nested-sortable:not(:has(li))::after {
    content: '↳ Suelta aquí para convertir en submenú';
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    color: #A5B4FC;
    font-weight: 600;
    pointer-events: none;
  }
  /* Estado al arrastrar sobre ella */
  .nested-sortable.sortable-drag-over,
  .nested-sortable:has(.sortable-ghost) {
    min-height: 48px;
    border-color: #818CF8;
    background: #EEF2FF;
  }
  /* Zona con hijos: borde más sutil */
  .nested-sortable:has(li) {
    border-color: #C7D2FE;
    background: #F5F7FF;
  }
  #save-toast { transition:all .3s; }
</style>

<!-- Toast guardado -->
<div id="save-toast"
     class="fixed bottom-6 right-6 z-50 hidden bg-green-600 text-white text-sm font-bold px-5 py-3 rounded-xl shadow-xl flex items-center gap-2">
  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
  </svg>
  Orden guardado
</div>

<?php if (isset($_GET['ok'])): ?>
<div class="mb-4 bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl px-4 py-3 font-semibold">
  Cambio guardado correctamente.
</div>
<?php endif; ?>
<?php if ($msg): ?>
<div class="mb-4 rounded-xl px-4 py-3 text-sm font-semibold
            <?= $tipo_msg==='error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200' ?>">
  <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Info -->
<div class="bg-indigo-50 border border-indigo-200 rounded-xl px-4 py-3 mb-6 flex items-start gap-3">
  <svg class="w-5 h-5 text-indigo-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
  </svg>
  <p class="text-sm text-indigo-700">
    <strong>Arrastra</strong> los ítems para reordenar.
    <strong>Arrastra uno sobre otro</strong> para convertirlo en submenú (máx. 1 nivel).
    Los cambios se guardan automáticamente al soltar.
  </p>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6" x-data="{ delId:null, delLabel:'' }">

  <!-- Modal eliminar -->
  <div x-show="delId" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4"
       x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="delId=null"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 text-center"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
      <p class="font-black text-gray-900 text-lg mb-2">¿Eliminar ítem?</p>
      <p class="text-sm text-gray-500 mb-5">Se eliminará <strong x-text="'«' + delLabel + '»'"></strong>.<br>
         <span class="text-xs text-gray-400">Sus subítems pasarán al menú principal.</span></p>
      <div class="flex gap-3">
        <button @click="delId=null" class="flex-1 border-2 border-gray-200 text-gray-600 font-bold py-2.5 rounded-xl text-sm">Cancelar</button>
        <form method="POST" class="flex-1">
          <input type="hidden" name="action" value="delete_item">
          <input type="hidden" name="id" :value="delId">
          <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 rounded-xl text-sm text-center">Eliminar</button>
        </form>
      </div>
    </div>
  </div>

  <!-- ══ ÁRBOL SORTABLE ══════════════════════════════════════ -->
  <div class="lg:col-span-2">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="font-black text-gray-800 text-sm">Estructura del Menú</h2>
        <span class="text-xs text-gray-400"><?= count($root_items) + array_sum(array_map('count', $child_map)) ?> ítems</span>
      </div>

      <?php if (empty($root_items)): ?>
      <p class="text-center text-gray-400 py-8 text-sm">No hay ítems. Añade el primero.</p>
      <?php else: ?>

      <ul id="sortable-root" class="space-y-2">
        <?php foreach ($root_items as $item): ?>
        <?php
          $has_children = !empty($child_map[(int)$item['id']]);
          $tc = $tipo_colors[$item['tipo']] ?? 'bg-gray-100 text-gray-600';
          $tl = $tipo_labels[$item['tipo']] ?? $item['tipo'];
        ?>
        <li class="root-item select-none" data-id="<?= $item['id'] ?>" data-tipo="<?= htmlspecialchars($item['tipo']) ?>">

          <!-- Fila del ítem raíz -->
          <div class="flex items-center gap-3 bg-white border border-gray-200 rounded-xl px-4 py-3
                      <?= !$item['visible'] ? 'opacity-50' : '' ?> hover:border-indigo-200 transition-colors">
            <!-- Handle -->
            <div class="drag-handle flex-shrink-0 text-gray-300 hover:text-indigo-400 transition-colors">
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path d="M7 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 14zm6-8a2 2 0 1 0-.001-4.001A2 2 0 0 0 13 6zm0 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 14z"/>
              </svg>
            </div>

            <!-- Info -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($item['label']) ?></span>
                <span class="text-[10px] px-2 py-0.5 rounded-full font-bold <?= $tc ?>"><?= $tl ?></span>
                <?php if ($has_children): ?>
                <span class="text-[10px] text-indigo-500 bg-indigo-50 px-2 py-0.5 rounded-full font-bold">
                  <?= count($child_map[(int)$item['id']]) ?> subítem<?= count($child_map[(int)$item['id']]) !== 1 ? 's' : '' ?>
                </span>
                <?php endif; ?>
                <?php if ($item['target']==='_blank'): ?>
                <span class="text-[10px] text-gray-400">↗</span>
                <?php endif; ?>
              </div>
              <?php if ($item['tipo'] !== 'auto-candidatos'): ?>
              <p class="text-[10px] text-gray-400 font-mono truncate mt-0.5"><?= htmlspecialchars($item['url']) ?></p>
              <?php endif; ?>
            </div>

            <!-- Acciones -->
            <div class="flex items-center gap-1 flex-shrink-0">
              <form method="POST" class="inline-flex">
                <input type="hidden" name="action" value="toggle_visible">
                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                <button type="submit" title="<?= $item['visible']?'Ocultar':'Mostrar' ?>"
                   class="w-7 h-7 flex items-center justify-center rounded-lg transition-all
                          <?= $item['visible'] ? 'bg-green-100 text-green-600 hover:bg-green-200' : 'bg-gray-100 text-gray-400 hover:bg-gray-200' ?>">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                  </svg>
                </button>
              </form>
              <a href="menu-manager.php?edit=<?= $item['id'] ?>"
                 class="w-7 h-7 flex items-center justify-center rounded-lg bg-[#1E3A8A] text-white hover:bg-blue-800 transition-all">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
              </a>
              <button @click="delId=<?= $item['id'] ?>; delLabel='<?= addslashes(htmlspecialchars($item['label'])) ?>'"
                      class="w-7 h-7 flex items-center justify-center rounded-lg bg-red-50 text-red-400 hover:bg-red-500 hover:text-white transition-all">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
              </button>
            </div>
          </div>

          <!-- Lista de subítems (siempre presente para ser drop target) -->
          <ul class="nested-sortable mt-1 ml-8 space-y-1"
              data-parent="<?= $item['id'] ?>"
              <?php if ($item['tipo']==='auto-candidatos'): ?>data-locked="1"<?php endif; ?>>

            <?php foreach (($child_map[(int)$item['id']] ?? []) as $child): ?>
            <?php
              $ctc = $tipo_colors[$child['tipo']] ?? 'bg-gray-100 text-gray-600';
              $ctl = $tipo_labels[$child['tipo']] ?? $child['tipo'];
            ?>
            <li class="child-item select-none" data-id="<?= $child['id'] ?>" data-tipo="<?= htmlspecialchars($child['tipo']) ?>">
              <div class="flex items-center gap-3 bg-indigo-50/60 border border-indigo-100 rounded-xl px-4 py-2.5
                          <?= !$child['visible'] ? 'opacity-50' : '' ?> hover:border-indigo-300 transition-colors">
                <!-- Handle -->
                <div class="drag-handle flex-shrink-0 text-indigo-200 hover:text-indigo-400 transition-colors">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M7 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 2zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 7 14zm6-8a2 2 0 1 0-.001-4.001A2 2 0 0 0 13 6zm0 2a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 8zm0 6a2 2 0 1 0 .001 4.001A2 2 0 0 0 13 14z"/>
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 flex-shrink-0"></span>
                    <span class="font-semibold text-gray-700 text-sm"><?= htmlspecialchars($child['label']) ?></span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded-full font-bold <?= $ctc ?>"><?= $ctl ?></span>
                  </div>
                  <p class="text-[10px] text-gray-400 font-mono truncate ml-3.5 mt-0.5"><?= htmlspecialchars($child['url']) ?></p>
                </div>
                <div class="flex items-center gap-1 flex-shrink-0">
                  <form method="POST" class="inline-flex">
                    <input type="hidden" name="action" value="toggle_visible">
                    <input type="hidden" name="id" value="<?= $child['id'] ?>">
                    <button type="submit"
                       class="w-6 h-6 flex items-center justify-center rounded-lg transition-all
                              <?= $child['visible'] ? 'bg-green-100 text-green-600 hover:bg-green-200' : 'bg-gray-100 text-gray-400' ?>">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                      </svg>
                    </button>
                  </form>
                  <a href="menu-manager.php?edit=<?= $child['id'] ?>"
                     class="w-6 h-6 flex items-center justify-center rounded-lg bg-[#1E3A8A] text-white hover:bg-blue-800 transition-all">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                  </a>
                  <button @click="delId=<?= $child['id'] ?>; delLabel='<?= addslashes(htmlspecialchars($child['label'])) ?>'"
                          class="w-6 h-6 flex items-center justify-center rounded-lg bg-red-50 text-red-400 hover:bg-red-500 hover:text-white transition-all">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                  </button>
                </div>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
        </li>
        <?php endforeach; ?>
      </ul>

      <?php endif; ?>
    </div>
  </div>

  <!-- ══ FORMULARIO ═════════════════════════════════════════ -->
  <div>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 sticky top-20">
      <h2 class="font-black text-gray-800 text-sm mb-4">
        <?= $editing ? 'Editar ítem' : 'Nuevo ítem' ?>
      </h2>
      <form method="POST" class="space-y-3.5" x-data="menuForm()" x-init="init()">
        <input type="hidden" name="item_id" value="<?= $editing ? (int)$editing['id'] : 0 ?>">

        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Nombre visible *</label>
          <input type="text" name="label" required
                 value="<?= htmlspecialchars($editing['label'] ?? '') ?>"
                 placeholder="Ej: Sobre Nosotros"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none">
        </div>

        <!-- Posición en el menú -->
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Posición</label>
          <select name="parent_id"
                  class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none">
            <option value="">Menú principal (raíz)</option>
            <?php foreach ($root_items as $ri): ?>
            <?php if ($ri['tipo'] !== 'auto-candidatos'): ?>
            <option value="<?= $ri['id'] ?>"
                    <?= (isset($editing['parent_id']) && (int)$editing['parent_id'] === (int)$ri['id']) ? 'selected' : '' ?>>
              Subítem de: <?= htmlspecialchars($ri['label']) ?>
            </option>
            <?php endif; ?>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Tipo de enlace</label>
          <select name="tipo" x-model="tipo"
                  class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none">
            <option value="interno">Interno (sección del sitio)</option>
            <option value="pagina">Página creada</option>
            <option value="noticia">Noticia específica</option>
            <option value="externo">URL externa</option>
            <option value="auto-candidatos">Auto — Candidatos Distritales</option>
          </select>
        </div>

        <!-- URL -->
        <div x-show="tipo !== 'auto-candidatos'">
          <label class="block text-xs font-semibold text-gray-600 mb-1">URL / Enlace</label>

          <div x-show="tipo === 'interno'" class="mb-2">
            <select
              @change="if ($event.target.value && $refs.urlInput) $refs.urlInput.value = $event.target.value"
              class="w-full border border-blue-100 bg-blue-50 text-blue-800 rounded-xl px-3 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-[#1E3A8A] outline-none">
              <option value="">Secciones rapidas del Index...</option>
              <?php foreach ($index_sections as $url => $label): ?>
              <option value="<?= htmlspecialchars($url) ?>" <?= ($editing['url'] ?? '') === $url ? 'selected' : '' ?>>
                <?= htmlspecialchars($label) ?> - <?= htmlspecialchars($url) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <p class="text-[11px] text-blue-500 mt-1">Selecciona una seccion o escribe una URL manual abajo.</p>
          </div>

          <template x-if="tipo === 'interno' || tipo === 'externo' || tipo === 'noticia'">
            <input type="text" name="url"
                   x-ref="urlInput"
                   value="<?= htmlspecialchars($editing['url'] ?? '') ?>"
                   :placeholder="tipo==='externo' ? 'https://...' : '/index.php#seccion'"
                   class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono focus:ring-2 focus:ring-[#1E3A8A] outline-none">
          </template>

          <template x-if="tipo === 'pagina'">
            <select name="url"
                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none">
              <option value="">Selecciona una página...</option>
              <?php foreach ($paginas_list as $pg): ?>
              <option value="/pagina.php?slug=<?= urlencode($pg['slug']) ?>"
                      <?= ($editing['url'] ?? '') === '/pagina.php?slug=' . $pg['slug'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($pg['titulo']) ?>
              </option>
              <?php endforeach; ?>
              <?php if (empty($paginas_list)): ?><option disabled>Sin páginas publicadas</option><?php endif; ?>
            </select>
          </template>
        </div>

        <template x-if="tipo === 'auto-candidatos'">
          <div>
            <input type="hidden" name="url" value="#">
            <p class="text-xs text-green-600 bg-green-50 border border-green-200 rounded-xl px-3 py-2">
              ✓ Dropdown automático con distritos activos.
            </p>
          </div>
        </template>

        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Comportamiento</label>
          <select name="target" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none">
            <option value="_self"  <?= ($editing['target'] ?? '_self') === '_self'  ? 'selected' : '' ?>>Misma pestaña</option>
            <option value="_blank" <?= ($editing['target'] ?? '') === '_blank' ? 'selected' : '' ?>>Nueva pestaña ↗</option>
          </select>
        </div>

        <div class="flex gap-2 pt-1">
          <button type="submit"
                  class="flex-1 bg-[#1E3A8A] hover:bg-blue-900 text-white font-bold py-2.5 rounded-xl text-sm transition-all">
            <?= $editing ? 'Actualizar' : 'Añadir' ?>
          </button>
          <?php if ($editing): ?>
          <a href="menu-manager.php" class="px-4 py-2.5 border border-gray-200 text-gray-500 rounded-xl text-sm font-semibold hover:bg-gray-50">
            Cancelar
          </a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

</div>

<!-- SortableJS CDN -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
<script>
function menuForm() {
  return {
    tipo: '<?= htmlspecialchars($editing['tipo'] ?? 'interno') ?>',
    init() { this.tipo = this.$el.querySelector('[name="tipo"]').value; }
  };
}

(function () {
  const toast    = document.getElementById('save-toast');
  let toastTimer = null;

  function showToast() {
    toast.classList.remove('hidden');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.add('hidden'), 2500);
  }

  function serializeOrder() {
    const result = [];
    const rootList = document.getElementById('sortable-root');
    if (!rootList) return result;

    rootList.querySelectorAll(':scope > li.root-item').forEach((li, idx) => {
      const id = parseInt(li.dataset.id);
      result.push({ id, parent_id: null, orden: (idx + 1) * 10 });

      const nested = li.querySelector(':scope > ul.nested-sortable');
      if (nested) {
        nested.querySelectorAll(':scope > li').forEach((child, cidx) => {
          const cid = parseInt(child.dataset.id);
          result.push({ id: cid, parent_id: id, orden: (cidx + 1) * 10 });
        });
      }
    });
    return result;
  }

  function saveOrder() {
    const data = serializeOrder();
    fetch('menu-sort.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': window.CSRF_TOKEN || ''
      },
      body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(d => { if (d.ok) showToast(); })
    .catch(() => {});
  }

  const commonOptions = {
    group: {
      name:  'menu',
      pull:  true,
      put:   true
    },
    animation: 200,
    handle:    '.drag-handle',
    ghostClass: 'sortable-ghost',
    chosenClass: 'sortable-chosen',
    fallbackOnBody: true,
    swapThreshold: 0.5,

    onDragOver: function (evt) {
      if (evt.to && evt.to.classList.contains('nested-sortable')) {
        evt.to.classList.add('sortable-drag-over');
      }
    },

    onMove: function (evt) {
      // Highlight zona de drop
      document.querySelectorAll('.nested-sortable').forEach(el => el.classList.remove('sortable-drag-over'));
      if (evt.to && evt.to.classList.contains('nested-sortable')) {
        evt.to.classList.add('sortable-drag-over');
      }
      // Prevenir que auto-candidatos sea subítem
      if (evt.dragged.dataset.tipo === 'auto-candidatos' &&
          evt.to.classList.contains('nested-sortable')) {
        return false;
      }
      // Prevenir 3er nivel: buscar en los ANCESTROS (no en el elemento mismo)
      if (evt.to.classList.contains('nested-sortable') &&
          evt.to.parentElement && evt.to.parentElement.closest('.nested-sortable')) {
        return false;
      }
      // No permitir drop en nested-sortable bloqueado (auto-candidatos)
      if (evt.to.dataset.locked === '1') return false;
      return true;
    },

    onEnd: function (evt) {
      // Limpiar highlights
      document.querySelectorAll('.nested-sortable').forEach(el => el.classList.remove('sortable-drag-over'));
      const item       = evt.item;
      const toList     = evt.to;
      const isNowChild = toList.classList.contains('nested-sortable');
      const wasChild   = evt.from.classList.contains('nested-sortable');

      // Cambió de nivel: ocultar/mostrar nested-sortable propio
      const ownNested = item.querySelector(':scope > ul.nested-sortable');
      if (ownNested) {
        ownNested.style.display = isNowChild ? 'none' : '';
      }

      // Actualizar clases visuales del ítem
      if (isNowChild) {
        item.classList.add('child-item');
        item.classList.remove('root-item');
        // Cambiar estilo de la tarjeta
        const card = item.querySelector(':scope > div');
        if (card) {
          card.classList.remove('bg-white', 'border-gray-200');
          card.classList.add('bg-indigo-50/60', 'border-indigo-100');
        }
      } else if (wasChild && !isNowChild) {
        item.classList.add('root-item');
        item.classList.remove('child-item');
        const card = item.querySelector(':scope > div');
        if (card) {
          card.classList.add('bg-white', 'border-gray-200');
          card.classList.remove('bg-indigo-50/60', 'border-indigo-100');
        }
        // Restaurar nested-sortable si existe y no tiene el atributo locked
        if (ownNested) {
          ownNested.style.display = '';
          // Re-inicializar SortableJS en este nested si no está inicializado
          if (!ownNested._sortable) {
            ownNested._sortable = Sortable.create(ownNested, { ...commonOptions });
          }
        }
      }

      saveOrder();
    }
  };

  // Inicializar root
  const rootList = document.getElementById('sortable-root');
  if (rootList) Sortable.create(rootList, commonOptions);

  // Inicializar todos los nested-sortable
  document.querySelectorAll('.nested-sortable').forEach(el => {
    el._sortable = Sortable.create(el, commonOptions);
  });

})();
</script>

    </main>
  </div>
</body>
</html>
