<?php
// ============================================================
// config-plan.php — Configuración del Plan de Gobierno
// Tabs: "Ejes de Trabajo" + "Hero / Página"
// Requiere rol: admin
// ============================================================
if (ob_get_level() === 0) ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/../includes/helpers/config.php';

require_login();
require_rol('admin');
require_modulo('configuracion_global', $pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

// ── Cargar config ─────────────────────────────────────────────
$config = [];
try {
    $config = $pdo->query("SELECT clave, valor FROM configuracion")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {}

// ── Defaults ──────────────────────────────────────────────────
$work_axes_default   = cfg_default_work_axes();
$work_section_defaults = [
    'index_work_eyebrow'   => 'Propuestas 2026',
    'index_work_title'     => 'Nuestros Ejes de Trabajo',
    'index_work_text'      => 'Un plan concreto, realista y comprometido para transformar Satipo en cuatro anos.',
    'index_work_button_text' => 'Ver Plan Completo 2026-2030',
    'index_work_button_url'  => '/plan.php',
];

$plan_page_defaults = [
    'plan_hero_logo'   => '/assets/img/logos/logo_renovacion_popular.webp',
    'plan_hero_img'    => '/assets/img/candidato/agricultura.webp',
    'plan_hero_anios'  => '2026 - 2030',
    'plan_hero_slogan' => '"Satipo que crece, Satipo que avanza"',
    'plan_pdf_titulo'  => 'Descarga el Plan de Gobierno Completo',
    'plan_pdf_desc'    => 'Version extendida en PDF con todos los detalles y proyecciones 2026-2030.',
    'plan_cta_titulo'  => 'Compartes esta vision para Satipo?',
    'plan_cta_texto'   => 'Unete al equipo y se parte del cambio.',
];

// ── Helper: parsear axes desde POST ──────────────────────────
if (!function_exists('cfg_parse_axes')) {
    function cfg_parse_axes(array $post, array $fallback): array {
        // Nuevo: leer desde JSON serializado por Alpine (modal approach)
        if (!empty($post['axes_json'])) {
            $decoded = json_decode($post['axes_json'], true);
            if (is_array($decoded) && !empty($decoded)) {
                // Sanitizar y garantizar estructura mínima
                $clean = [];
                foreach ($decoded as $p) {
                    if (!is_array($p) || empty(trim((string)($p['title'] ?? '')))) continue;
                    $title = trim((string)$p['title']);
                    $clean[] = [
                        'id'            => trim((string)($p['id']            ?? cfg_slug($title))),
                        'icon'          => trim((string)($p['icon']          ?? 'gestion')),
                        'label'         => trim((string)($p['label']         ?? $title)),
                        'title'         => $title,
                        'desc'          => trim((string)($p['desc']          ?? '')),
                        'grad'          => trim((string)($p['grad']          ?? 'from-blue-500 to-indigo-600')),
                        'nav_color'     => trim((string)($p['nav_color']     ?? 'bg-blue-100 text-blue-700')),
                        'nav_border'    => trim((string)($p['nav_border']    ?? 'border-blue-300')),
                        'section_bg'    => trim((string)($p['section_bg']    ?? '#EFF6FF')),
                        'section_border'=> trim((string)($p['section_border']?? '#BFDBFE')),
                        'img'           => trim((string)($p['img']           ?? '')),
                        'plan_desc'     => trim((string)($p['plan_desc']     ?? '')),
                        'proposals'     => trim((string)($p['proposals']     ?? '')),
                        'active'        => isset($p['active']) ? (bool)$p['active'] : true,
                    ];
                }
                return $clean ?: $fallback;
            }
        }
        // Fallback legado: arrays eje_*[]
        $titles = $post['eje_title'] ?? [];
        $axes   = [];
        $used_ids = [];
        for ($i = 0; $i < count($titles); $i++) {
            $title = trim($titles[$i] ?? '');
            if ($title === '') continue;
            $id      = cfg_slug((string)($post['eje_id'][$i] ?? $title), 'eje-' . ($i + 1));
            $base_id = $id;
            $suffix  = 2;
            while (in_array($id, $used_ids)) { $id = $base_id . '-' . $suffix++; }
            $used_ids[] = $id;
            $axes[] = [
                'id'            => $id,
                'icon'          => trim($post['eje_icon'][$i]          ?? '') ?: cfg_axis_auto_icon($title . ' ' . ($post['eje_label'][$i] ?? '')),
                'label'         => trim($post['eje_label'][$i]         ?? $title),
                'title'         => $title,
                'desc'          => trim($post['eje_desc'][$i]          ?? ''),
                'grad'          => trim($post['eje_grad'][$i]          ?? 'from-blue-500 to-indigo-600'),
                'nav_color'     => trim($post['eje_nav_color'][$i]     ?? 'bg-blue-100 text-blue-700'),
                'nav_border'    => trim($post['eje_nav_border'][$i]    ?? 'border-blue-300'),
                'section_bg'    => trim($post['eje_section_bg'][$i]    ?? '#EFF6FF'),
                'section_border'=> trim($post['eje_section_border'][$i]?? '#BFDBFE'),
                'img'           => trim($post['eje_img'][$i]           ?? ''),
                'plan_desc'     => trim($post['eje_plan_desc'][$i]     ?? ''),
                'proposals'     => trim($post['eje_proposals'][$i]     ?? ''),
            ];
        }
        return $axes ?: $fallback;
    }
}

if (!function_exists('cfg_slug')) {
    function cfg_slug(string $text, string $fallback = 'eje'): string {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');
        return $text ?: $fallback;
    }
}

if (!function_exists('cfg_admin_val')) {
    function cfg_admin_val(array $config, array $defaults, string $key): string {
        return htmlspecialchars(cfg_value($config, $key, $defaults[$key] ?? ''), ENT_QUOTES);
    }
}

// ── PDF del Plan de Gobierno ──────────────────────────────────
define('PLAN_PDF_PATH', dirname(__DIR__) . '/assets/docs/plan.pdf');
define('PLAN_PDF_MAX',  50 * 1024 * 1024); // 50 MB
if (!is_dir(dirname(PLAN_PDF_PATH))) @mkdir(dirname(PLAN_PDF_PATH), 0755, true);

// ── Flash ─────────────────────────────────────────────────────
$flash      = null;
$flash_type = 'success';
$active_tab = 'ejes';

// ── POST handlers ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $active_tab = $_POST['tab'] ?? 'ejes';

    // ── Subir PDF ────────────────────────────────────────────
    if (($_POST['pdf_action'] ?? '') === 'upload_pdf') {
        $active_tab = 'hero';
        $file = $_FILES['plan_pdf_file'] ?? null;
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $flash = 'Selecciona un archivo PDF antes de subir.';
            $flash_type = 'error';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $php_errs = [
                1 => 'supera el límite del servidor (' . ini_get('upload_max_filesize') . ')',
                3 => 'se subió parcialmente — intenta de nuevo',
                6 => 'el servidor no tiene directorio temporal',
                7 => 'error de escritura en disco',
            ];
            $flash = 'Error al subir: ' . ($php_errs[$file['error']] ?? 'código ' . $file['error']) . '.';
            $flash_type = 'error';
        } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'pdf') {
            $flash = 'Solo se permiten archivos en formato PDF.';
            $flash_type = 'error';
        } elseif ($file['size'] > PLAN_PDF_MAX) {
            $flash = 'El archivo supera el límite de 50 MB.';
            $flash_type = 'error';
        } else {
            $bytes = @file_get_contents($file['tmp_name']);
            if ($bytes !== false && @file_put_contents(PLAN_PDF_PATH, $bytes) !== false) {
                $sz = round($file['size'] / 1024);
                log_activity($pdo, 'Subió Plan de Gobierno PDF: ' . $file['name'] . " ({$sz} KB)", 'configuracion_global');
                $flash = 'PDF "' . htmlspecialchars($file['name']) . '" subido correctamente.';
            } else {
                $flash = 'No se pudo guardar el archivo. Verifica permisos del directorio /assets/docs/.';
                $flash_type = 'error';
            }
        }
    }

    // ── Eliminar PDF ─────────────────────────────────────────
    if (($_POST['pdf_action'] ?? '') === 'delete_pdf') {
        $active_tab = 'hero';
        if (file_exists(PLAN_PDF_PATH) && @unlink(PLAN_PDF_PATH)) {
            log_activity($pdo, 'Eliminó Plan de Gobierno PDF', 'configuracion_global');
            $flash = 'PDF eliminado correctamente.';
        } else {
            $flash = 'No se encontró el PDF o no se pudo eliminar.';
            $flash_type = 'error';
        }
    }

    if ($active_tab === 'ejes') {
        $values = [];
        foreach (array_keys($work_section_defaults) as $key) {
            $values[$key] = trim($_POST[$key] ?? $work_section_defaults[$key]);
        }
        $values['index_work_axes'] = cfg_parse_axes($_POST, $work_axes_default);
        try {
            cfg_save_values($pdo, $values);
            $config = array_merge($config, array_map(
                fn($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string)$v,
                $values
            ));
            log_activity($pdo, 'Actualizo Plan de Gobierno: Ejes de Trabajo', 'configuracion_global');
            $flash = 'Ejes de trabajo guardados correctamente.';
        } catch (Exception $e) {
            $flash      = 'Error al guardar: ' . $e->getMessage();
            $flash_type = 'error';
        }
    }

    if ($active_tab === 'hero') {
        $values = [];
        foreach (array_keys($plan_page_defaults) as $key) {
            $values[$key] = trim($_POST[$key] ?? $plan_page_defaults[$key]);
        }
        try {
            cfg_save_values($pdo, $values);
            $config = array_merge($config, array_map('strval', $values));
            log_activity($pdo, 'Actualizo Plan de Gobierno: Hero y pagina', 'configuracion_global');
            $flash = 'Configuracion de la pagina plan actualizada correctamente.';
        } catch (Exception $e) {
            $flash      = 'Error al guardar: ' . $e->getMessage();
            $flash_type = 'error';
        }
    }
}

// ── Estado actual del PDF ─────────────────────────────────────
$pdf_exists   = file_exists(PLAN_PDF_PATH);
$pdf_bytes    = $pdf_exists ? (int)filesize(PLAN_PDF_PATH) : 0;
$pdf_size_fmt = $pdf_bytes >= 1048576
    ? number_format($pdf_bytes / 1048576, 2) . ' MB'
    : number_format($pdf_bytes / 1024, 1) . ' KB';
$pdf_date_fmt = $pdf_exists ? date('d/m/Y H:i', (int)filemtime(PLAN_PDF_PATH)) : '';
$pdf_pub_url  = BASE_URL . '/assets/docs/plan.pdf';

// ── Preparar datos para render ────────────────────────────────
$work_axes      = cfg_json($config, 'index_work_axes', $work_axes_default);
$work_axes_json = json_encode($work_axes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

$page_title = 'Plan de Gobierno';
include __DIR__ . '/layout.php';
?>
<script src="https://cdn.jsdelivr.net/npm/@alpinejs/sort@3.x.x/dist/cdn.min.js"></script>

<style>
  .config-tab {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 0.55rem 1.1rem; border-radius: 0.75rem; font-weight: 700;
    font-size: 0.8rem; border: 1.5px solid transparent;
    cursor: pointer; transition: all 0.15s ease; color: #64748B;
    background: transparent; white-space: nowrap;
  }
  .config-tab:hover { background: #EFF6FF; color: #1E3A8A; border-color: #BFDBFE; }
  .config-tab.is-active { background: #1E3A8A; color: #fff; border-color: #1E3A8A; box-shadow: 0 2px 8px #1E3A8A33; }
  .config-tabs { display: flex; gap: 6px; flex-wrap: wrap; background: #fff; border: 1.5px solid #E2E8F0; border-radius: 1rem; padding: 6px; margin-bottom: 1.5rem; box-shadow: 0 1px 4px #0001; }
  .config-group { border: 1.5px solid #E0ECFF; border-radius: 0.95rem; background: linear-gradient(180deg,#f8fbff,#fff); padding: 1rem; margin-bottom: 0; }
  .config-group-title { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; }
  .config-group-title h3 { font-size: 0.82rem; font-weight: 800; color: #1E3A8A; text-transform: uppercase; letter-spacing: .07em; margin: 0; }
  .config-group-title p  { font-size: 0.73rem; color: #64748B; margin: 2px 0 0; }
  .config-field-grid { display: grid; gap: 1rem; grid-template-columns: 1fr; }
  @media (min-width:640px) { .config-field-grid.two { grid-template-columns: repeat(2, minmax(0,1fr)); } }
</style>

<div class="max-w-6xl mx-auto" x-data="{ activeTab: '<?= htmlspecialchars($active_tab) ?>' }">

  <!-- Page header -->
  <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div>
      <div class="flex items-center gap-3 mb-1">
        <div class="w-8 h-8 bg-[#FACC15] rounded-xl flex items-center justify-center flex-shrink-0">
          <svg class="w-4 h-4 text-[#0F2057]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
        </div>
        <h1 class="text-2xl font-black text-gray-800">Plan de Gobierno</h1>
      </div>
      <p class="text-sm text-gray-500 ml-11">Ejes temáticos, propuestas y configuración visual de la página plan.</p>
    </div>
    <div class="flex items-center gap-2">
      <a href="<?= BASE_URL ?>/plan.php" target="_blank"
         class="inline-flex items-center gap-2 bg-white border border-gray-200 text-gray-600 hover:text-[#1E3A8A] font-bold px-4 py-2.5 rounded-xl text-sm shadow-sm transition-colors">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
        </svg>
        Ver plan público
      </a>
    </div>
  </div>

  <?php if ($flash): ?>
  <div class="mb-5 rounded-xl px-5 py-3.5 text-sm font-semibold border flex items-center gap-3
              <?= $flash_type === 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' ?>">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
      <?php if ($flash_type === 'success'): ?>
      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
      <?php else: ?>
      <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
      <?php endif; ?>
    </svg>
    <?= htmlspecialchars($flash) ?>
  </div>
  <?php endif; ?>

  <!-- Tabs -->
  <div class="config-tabs">
    <button type="button" @click="activeTab='ejes'"
            class="config-tab" :class="activeTab==='ejes' ? 'is-active' : ''">
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
      </svg>
      Ejes de Trabajo
    </button>
    <button type="button" @click="activeTab='hero'"
            class="config-tab" :class="activeTab==='hero' ? 'is-active' : ''">
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
      </svg>
      Hero / Página
    </button>
  </div>

  <!-- ══════════════════════════════════════════════════════════ -->
  <!-- TAB: EJES DE TRABAJO                                      -->
  <!-- ══════════════════════════════════════════════════════════ -->
  <div x-show="activeTab === 'ejes'" x-data="workAxesManager()" @keydown.escape.window="closeModal()">

    <!-- Input oculto con JSON — se llena antes del submit -->
    <form id="ejes-form" method="POST" class="hidden">
      <input type="hidden" name="tab" value="ejes">
      <?= csrf_field() ?>
      <input type="hidden" name="axes_json" id="axes-json-input">
    </form>

    <!-- Campos de encabezado (sección portada) + botón CTA -->
    <form method="POST" class="space-y-6 mb-6">
      <input type="hidden" name="tab" value="ejes">
      <?= csrf_field() ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Encabezado de la sección en portada -->
      <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-[#1E3A8A] px-6 py-4 flex items-center gap-3">
          <svg class="w-4 h-4 text-[#FACC15]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h7"/>
          </svg>
          <h2 class="text-white font-bold text-sm">Encabezado de la sección en portada</h2>
        </div>
        <div class="p-6">
          <div class="config-group">
            <div class="config-group-title">
              <div>
                <h3>Textos visibles en portada</h3>
                <p>Etiqueta, título y descripción que aparecen sobre las tarjetas de ejes en index.php.</p>
              </div>
            </div>
            <div class="config-field-grid two">
              <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Etiqueta superior</label>
                <input name="index_work_eyebrow" value="<?= cfg_admin_val($config, $work_section_defaults, 'index_work_eyebrow') ?>"
                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none">
              </div>
              <div>
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Título</label>
                <input name="index_work_title" value="<?= cfg_admin_val($config, $work_section_defaults, 'index_work_title') ?>"
                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none">
              </div>
              <div class="sm:col-span-2">
                <label class="block text-xs font-black text-gray-500 uppercase mb-1">Descripción</label>
                <textarea name="index_work_text" rows="2"
                          class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none"><?= cfg_admin_val($config, $work_section_defaults, 'index_work_text') ?></textarea>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Botón del plan -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-[#1E3A8A] px-6 py-4 flex items-center gap-3">
          <svg class="w-4 h-4 text-[#FACC15]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"/>
          </svg>
          <h2 class="text-white font-bold text-sm">Llamado a la acción</h2>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Texto botón</label>
            <input name="index_work_button_text" value="<?= cfg_admin_val($config, $work_section_defaults, 'index_work_button_text') ?>"
                   class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none">
          </div>
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">URL botón</label>
            <input name="index_work_button_url" value="<?= cfg_admin_val($config, $work_section_defaults, 'index_work_button_url') ?>"
                   class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-mono focus:ring-2 focus:ring-[#1E3A8A] outline-none">
          </div>
          <div class="rounded-xl bg-blue-50 border border-blue-100 p-3">
            <p class="text-xs text-blue-700 font-semibold">
              Este botón aparece en <strong>portada (index.php)</strong> al final de la sección de ejes, enlazando al plan público.
            </p>
          </div>
        </div>
      </div>
    </div>

    </form><!-- fin form encabezado/CTA -->

    <!-- ── Grid de cards de ejes ─────────────────────────────── -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="bg-[#1E3A8A] px-6 py-4 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <svg class="w-4 h-4 text-[#FACC15]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
          </svg>
          <h2 class="text-white font-bold text-sm">Ejes de Trabajo</h2>
          <span class="bg-white/20 text-white text-xs font-black px-2 py-0.5 rounded-full"
                x-text="axes.length + (axes.length !== 1 ? ' ejes' : ' eje')"></span>
        </div>
        <button type="button" @click="openNew()"
                class="inline-flex items-center gap-1.5 bg-[#FACC15] hover:bg-yellow-400 text-[#0F2057]
                       text-xs font-black px-4 py-2 rounded-lg transition-colors shadow-sm">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
          </svg>
          Nuevo eje
        </button>
      </div>

      <div class="p-6">
        <!-- Empty state -->
        <div x-show="axes.length === 0"
             class="rounded-2xl border-2 border-dashed border-blue-200 bg-blue-50/50 py-16 text-center">
          <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-blue-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
          </div>
          <p class="text-base font-black text-[#0F2057] mb-1">Sin ejes configurados</p>
          <p class="text-sm text-blue-500 mb-5">Crea el primer eje de trabajo pulsando el botón de arriba.</p>
          <button type="button" @click="openNew()"
                  class="inline-flex items-center gap-2 bg-[#1E3A8A] hover:bg-blue-900 text-white font-black px-6 py-3 rounded-xl text-sm transition-all shadow-md">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            Crear primer eje
          </button>
        </div>

        <!-- Grid de cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4"
             x-show="axes.length > 0"
             x-sort="reorderAxes($item, $position)">
          <template x-for="(axis, i) in axes" :key="axis.id || String(i)">
            <div class="group relative rounded-2xl border bg-white overflow-hidden transition-all duration-200"
                 x-sort:item="String(i)"
                 :class="axis.active !== false
                   ? 'border-gray-200 hover:shadow-lg hover:border-[#1E3A8A]/30'
                   : 'border-gray-200 border-dashed opacity-60'">

              <!-- Barra de color superior -->
              <div class="h-1.5 w-full"
                   :class="axis.active !== false
                     ? 'bg-gradient-to-r ' + (axis.grad || 'from-blue-500 to-indigo-600')
                     : 'bg-gray-200'"></div>

              <!-- Badge OCULTO flotante -->
              <div x-show="axis.active === false"
                   class="absolute top-3 right-3 z-10 flex items-center gap-1
                          bg-gray-700 text-white text-[9px] font-black px-2 py-0.5 rounded-full uppercase tracking-wide">
                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                </svg>
                Oculto
              </div>

              <!-- Card body -->
              <div class="p-5">
                <!-- Número + ícono -->
                <div class="flex items-start justify-between mb-3">
                  <div class="flex items-center gap-2.5">
                    <!-- Handle drag -->
                    <div class="drag-handle cursor-grab active:cursor-grabbing text-gray-300 hover:text-gray-500 transition-colors flex-shrink-0 mr-0.5" title="Arrastrar para reordenar">
                      <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <circle cx="9" cy="5" r="1.5"/><circle cx="15" cy="5" r="1.5"/>
                        <circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/>
                        <circle cx="9" cy="19" r="1.5"/><circle cx="15" cy="19" r="1.5"/>
                      </svg>
                    </div>
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 border"
                         :class="axis.active !== false ? (axis.nav_color || 'bg-blue-100 text-blue-700') : 'bg-gray-100 text-gray-400 border-gray-200'">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath(axis.icon)"/>
                      </svg>
                    </div>
                    <span class="text-xs font-black text-gray-400 uppercase tracking-widest">Eje <span x-text="i + 1"></span></span>
                  </div>
                  <!-- Badge label (solo cuando activo) -->
                  <span x-show="axis.active !== false"
                        class="text-[10px] font-black px-2 py-0.5 rounded-full border"
                        :class="axis.nav_color + ' ' + axis.nav_border"
                        x-text="axis.label || axis.title"></span>
                </div>

                <!-- Título -->
                <h3 class="font-black text-sm leading-snug mb-1"
                    :class="axis.active !== false ? 'text-gray-900' : 'text-gray-400'"
                    x-text="axis.title || 'Sin título'"></h3>

                <!-- Desc preview -->
                <p class="text-xs text-gray-500 leading-relaxed line-clamp-2 mb-4"
                   x-text="axis.desc || 'Sin descripción'"></p>

                <!-- Propuestas count -->
                <div class="flex items-center gap-3 mb-4">
                  <div class="flex items-center gap-1 text-xs text-gray-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
                    </svg>
                    <span x-text="countLines(axis.proposals) + ' propuesta' + (countLines(axis.proposals) !== 1 ? 's' : '')"></span>
                  </div>
                  <div x-show="axis.img" class="flex items-center gap-1 text-xs text-gray-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01"/>
                    </svg>
                    <span>Con imagen</span>
                  </div>
                </div>

                <!-- Acciones -->
                <div class="flex gap-2 pt-3 border-t border-gray-100">
                  <button type="button" @click="openEdit(i)"
                          class="flex-1 inline-flex items-center justify-center gap-1.5
                                 bg-[#1E3A8A] hover:bg-blue-900 text-white
                                 text-xs font-black px-3 py-2 rounded-xl transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Editar
                  </button>

                  <!-- Toggle ocultar/mostrar -->
                  <button type="button"
                          @click="axis.active = axis.active === false ? true : false"
                          :title="axis.active !== false ? 'Ocultar del sitio público' : 'Mostrar en el sitio público'"
                          :class="axis.active !== false
                            ? 'bg-amber-50 hover:bg-amber-100 text-amber-600 border border-amber-200'
                            : 'bg-green-50 hover:bg-green-100 text-green-600 border border-green-200'"
                          class="inline-flex items-center justify-center gap-1.5
                                 text-xs font-black px-3 py-2 rounded-xl transition-colors">
                    <!-- Ojo tachado (activo → click oculta) -->
                    <template x-if="axis.active !== false">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                      </svg>
                    </template>
                    <!-- Ojo abierto (oculto → click activa) -->
                    <template x-if="axis.active === false">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                      </svg>
                    </template>
                  </button>

                  <button type="button"
                          @click="if(confirm('¿Eliminar el eje «' + (axis.title || 'este eje') + '»? Esta acción no se puede deshacer.')) axes.splice(i, 1)"
                          class="inline-flex items-center justify-center gap-1.5
                                 bg-red-50 hover:bg-red-100 text-red-600
                                 text-xs font-black px-3 py-2 rounded-xl transition-colors border border-red-100">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Eliminar
                  </button>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- ── MODAL: Crear / Editar eje ─────────────────────────── -->
    <div x-show="showModal"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
         @click.self="closeModal()"
         style="display:none">

      <div x-show="showModal"
           x-transition:enter="transition ease-out duration-200"
           x-transition:enter-start="opacity-0 scale-95 translate-y-2"
           x-transition:enter-end="opacity-100 scale-100 translate-y-0"
           x-transition:leave="transition ease-in duration-150"
           x-transition:leave-start="opacity-100 scale-100 translate-y-0"
           x-transition:leave-end="opacity-0 scale-95 translate-y-2"
           class="bg-white rounded-3xl shadow-2xl w-full max-w-3xl max-h-[92vh] flex flex-col overflow-hidden">

        <!-- Modal header -->
        <div class="bg-gradient-to-r from-[#0F2057] to-[#1E3A8A] px-7 py-5 flex items-center justify-between flex-shrink-0">
          <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-[#FACC15] rounded-xl flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5 text-[#0F2057]" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
              </svg>
            </div>
            <div>
              <h3 class="text-white font-black text-base" x-text="editingIndex === -1 ? 'Nuevo Eje de Trabajo' : 'Editar: ' + (modalData.title || 'Eje')"></h3>
              <p class="text-blue-200 text-xs mt-0.5">Visible en portada (index.php) y en plan de gobierno (plan.php)</p>
            </div>
          </div>
          <button @click="closeModal()"
                  class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors flex-shrink-0">
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <!-- Modal body scrollable -->
        <div class="flex-1 overflow-y-auto p-7 space-y-6">

          <!-- Fila 1: Título + Etiqueta nav -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-1.5">
                Título completo <span class="text-red-500">*</span>
              </label>
              <input x-model="modalData.title"
                     @input.debounce.400ms="autoFillModal()"
                     placeholder="Ej: Seguridad Ciudadana"
                     class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition-all">
              <p class="text-xs text-gray-400 mt-1">Título principal visible en ambas páginas.</p>
            </div>
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-1.5">Etiqueta menú nav</label>
              <input x-model="modalData.label"
                     placeholder="Ej: Seguridad"
                     class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition-all">
              <p class="text-xs text-gray-400 mt-1">Texto corto del botón en el índice de plan.php.</p>
            </div>
          </div>

          <!-- Fila 2: ID slug + Ícono -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-1.5">
                Identificador <span class="text-gray-400 font-normal text-[10px]">(slug automático)</span>
              </label>
              <input x-model="modalData.id"
                     placeholder="seguridad-ciudadana"
                     class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition-all">
            </div>
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-1.5">Ícono</label>
              <select x-model="modalData.icon" @change="applyPreset(modalData)"
                      class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition-all bg-white">
                <option value="seguridad">🛡️ Seguridad</option>
                <option value="obra">🏗️ Infraestructura / Obra</option>
                <option value="campo">🌾 Campo / Agricultura</option>
                <option value="salud">🏥 Salud</option>
                <option value="educacion">📚 Educación</option>
                <option value="comunidad">🏘️ Comunidades</option>
                <option value="agua">💧 Agua / Saneamiento</option>
                <option value="transporte">🚌 Transporte</option>
                <option value="economia">💼 Economía / Empleo</option>
                <option value="turismo">🌿 Turismo / Cultura</option>
                <option value="deporte">⚽ Deporte / Juventud</option>
                <option value="ambiente">🌱 Ambiente / Ecología</option>
                <option value="juventud">👫 Juventud</option>
                <option value="gestion">⚙️ Gestión Municipal</option>
              </select>
            </div>
          </div>

          <!-- Botón auto colores -->
          <div class="flex items-center gap-3 p-4 bg-blue-50 rounded-2xl border border-blue-100">
            <svg class="w-5 h-5 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
            </svg>
            <div class="flex-1">
              <p class="text-xs font-black text-blue-800">Detección automática de colores</p>
              <p class="text-xs text-blue-600 mt-0.5">El sistema detecta los colores apropiados según el título e ícono del eje.</p>
            </div>
            <button type="button" @click="applyPreset(modalData)"
                    class="flex-shrink-0 bg-[#1E3A8A] hover:bg-blue-900 text-white text-xs font-black px-4 py-2 rounded-xl transition-colors">
              ✨ Auto-detectar
            </button>
          </div>

          <!-- Colores (compactos) -->
          <div>
            <div class="flex items-center gap-2 mb-3">
              <h4 class="text-xs font-black text-gray-500 uppercase tracking-widest">Colores y estilos</h4>
              <div class="flex-1 border-t border-gray-100"></div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase mb-1">Gradiente</label>
                <input x-model="modalData.grad"
                       class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-[10px] font-mono focus:ring-1 focus:ring-[#1E3A8A] outline-none">
              </div>
              <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase mb-1">Nav color</label>
                <input x-model="modalData.nav_color"
                       class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-[10px] font-mono focus:ring-1 focus:ring-[#1E3A8A] outline-none">
              </div>
              <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase mb-1">Nav borde</label>
                <input x-model="modalData.nav_border"
                       class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-[10px] font-mono focus:ring-1 focus:ring-[#1E3A8A] outline-none">
              </div>
              <div>
                <label class="block text-[10px] font-black text-gray-400 uppercase mb-1">Fondo plan</label>
                <input x-model="modalData.section_bg"
                       class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-[10px] font-mono focus:ring-1 focus:ring-[#1E3A8A] outline-none">
              </div>
            </div>
            <!-- Preview colores -->
            <div class="mt-3 flex items-center gap-2">
              <span class="text-xs text-gray-400">Preview:</span>
              <span class="text-xs font-black px-3 py-1 rounded-full border"
                    :class="(modalData.nav_color || 'bg-blue-100 text-blue-700') + ' ' + (modalData.nav_border || 'border-blue-300')"
                    x-text="modalData.label || modalData.title || 'Eje'"></span>
            </div>
          </div>

          <!-- Imagen -->
          <div>
            <div class="flex items-center gap-2 mb-3">
              <h4 class="text-xs font-black text-gray-500 uppercase tracking-widest">Imagen del eje</h4>
              <div class="flex-1 border-t border-gray-100"></div>
            </div>
            <div class="flex gap-2">
              <input x-model="modalData.img"
                     placeholder="/assets/img/candidato/agricultura.png"
                     class="min-w-0 flex-1 border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition-all">
              <button type="button" @click="openMediaPicker((picked)=>{ modalData.img = picked }, 'image')"
                      class="px-4 rounded-xl bg-indigo-50 text-indigo-600 text-sm font-black border border-indigo-200 hover:bg-indigo-100 transition-colors">
                Media
              </button>
            </div>
            <p class="text-xs text-gray-400 mt-1">Se muestra en plan.php junto al contenido del eje. Recomendado: 480×290 px.</p>
          </div>

          <!-- Separador plan.php -->
          <div class="rounded-2xl bg-gradient-to-r from-[#EFF6FF] to-blue-50 border border-blue-100 p-4">
            <div class="flex items-center gap-2 mb-1">
              <svg class="w-4 h-4 text-[#1E3A8A]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
              <p class="text-xs font-black text-[#1E3A8A]">Contenido extendido — exclusivo de plan.php</p>
            </div>
            <p class="text-xs text-blue-500">Los campos siguientes no aparecen en la portada, solo en la página del Plan de Gobierno.</p>
          </div>

          <!-- Desc portada + plan_desc + proposals -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-1.5">Descripción en portada</label>
              <textarea x-model="modalData.desc" rows="4"
                        placeholder="Resumen visible en index.php..."
                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition-all resize-y"></textarea>
            </div>
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-1.5">Descripción extendida <span class="text-blue-400 font-normal">(plan.php)</span></label>
              <textarea x-model="modalData.plan_desc" rows="4"
                        placeholder="Un párrafo por línea..."
                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition-all resize-y"></textarea>
              <p class="text-xs text-gray-400 mt-1">Un párrafo por línea.</p>
            </div>
          </div>

          <div>
            <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-1.5">Propuestas concretas <span class="text-blue-400 font-normal">(plan.php)</span></label>
            <textarea x-model="modalData.proposals" rows="5"
                      placeholder="Una propuesta por línea:&#10;Construir puentes en comunidades alejadas&#10;Asfaltar la vía principal del distrito"
                      class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition-all resize-y"></textarea>
            <p class="text-xs text-gray-400 mt-1">Una propuesta por línea. Se muestran como lista con viñetas amarillas en plan.php.</p>
          </div>

        </div><!-- fin modal body -->

        <!-- Modal footer -->
        <div class="flex-shrink-0 flex items-center justify-between gap-3 px-7 py-5 border-t border-gray-100 bg-gray-50">
          <div class="text-xs text-gray-400 hidden sm:block">
            <template x-if="editingIndex === -1">
              <span>El eje se agregará al final de la lista.</span>
            </template>
            <template x-if="editingIndex >= 0">
              <span>Modificando eje #<span x-text="editingIndex + 1"></span> — Los cambios son locales hasta que guardes.</span>
            </template>
          </div>
          <div class="flex gap-3 ml-auto">
            <button type="button" @click="closeModal()"
                    class="px-5 py-2.5 rounded-xl bg-white border border-gray-200 text-gray-600 font-bold text-sm hover:border-gray-300 hover:text-gray-800 transition-colors">
              Cancelar
            </button>
            <button type="button" @click="saveModal()"
                    :disabled="!modalData.title"
                    :class="modalData.title ? 'bg-[#1E3A8A] hover:bg-blue-900 cursor-pointer' : 'bg-gray-200 cursor-not-allowed'"
                    class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl text-white font-black text-sm transition-all shadow-sm">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
              </svg>
              <span x-text="editingIndex === -1 ? 'Agregar eje' : 'Guardar cambios'"></span>
            </button>
          </div>
        </div>

      </div><!-- fin modal panel -->
    </div><!-- fin modal overlay -->

    <!-- ── Sticky footer: guardar todo ───────────────────────── -->
    <div class="sticky bottom-0 bg-[#F1F5F9]/95 backdrop-blur py-4 border-t border-gray-200 flex items-center justify-between gap-3">
      <p class="text-xs text-gray-400 hidden sm:block">
        Los ejes se publican en
        <a href="<?= BASE_URL ?>/index.php" target="_blank" class="text-[#1E3A8A] font-bold hover:underline">index.php</a>
        y en
        <a href="<?= BASE_URL ?>/plan.php" target="_blank" class="text-[#1E3A8A] font-bold hover:underline">plan.php</a>
      </p>
      <div class="flex gap-3">
        <a href="<?= BASE_URL ?>/plan.php" target="_blank"
           class="bg-white border border-gray-200 text-gray-600 font-bold px-5 py-3 rounded-xl text-sm hover:border-[#1E3A8A] hover:text-[#1E3A8A] transition-colors">
          Ver plan
        </a>
        <button type="button" @click="submitEjes()"
                class="inline-flex items-center gap-2 bg-[#FACC15] hover:bg-yellow-400 text-[#1E3A8A] font-black px-8 py-3 rounded-xl text-sm shadow-md hover:shadow-lg transition-all active:scale-95">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
          </svg>
          Guardar ejes
        </button>
      </div>
    </div>

  </div><!-- fin TAB EJES -->

  <!-- ══════════════════════════════════════════════════════════ -->
  <!-- TAB: HERO / PÁGINA                                        -->
  <!-- ══════════════════════════════════════════════════════════ -->
  <form method="POST" x-show="activeTab === 'hero'" class="space-y-6">
    <input type="hidden" name="tab" value="hero">
    <?= csrf_field() ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- Hero visual -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="bg-[#1E3A8A] px-6 py-4 flex items-center gap-3">
          <svg class="w-4 h-4 text-[#FACC15]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
          <h2 class="text-white font-bold text-sm">Hero — Cabecera de plan.php</h2>
        </div>
        <div class="p-6 space-y-5">

          <!-- Logo -->
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Logo del hero</label>
            <div class="flex gap-2">
              <input name="plan_hero_logo" id="plan_hero_logo"
                     value="<?= cfg_admin_val($config, $plan_page_defaults, 'plan_hero_logo') ?>"
                     class="min-w-0 flex-1 border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-mono focus:ring-2 focus:ring-[#1E3A8A] outline-none"
                     placeholder="/assets/img/logos/...">
              <button type="button"
                      onclick="openMediaPicker(function(url){ document.getElementById('plan_hero_logo').value = url; }, 'image')"
                      class="px-3 rounded-xl bg-indigo-50 text-indigo-600 text-xs font-black border border-indigo-200 hover:bg-indigo-100 transition-colors">
                Media
              </button>
            </div>
            <p class="text-xs text-gray-400 mt-1">Logo que aparece junto al título en el banner superior de plan.php.</p>
          </div>

          <!-- Imagen de fondo -->
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Imagen de fondo del hero</label>
            <div class="flex gap-2">
              <input name="plan_hero_img" id="plan_hero_img"
                     value="<?= cfg_admin_val($config, $plan_page_defaults, 'plan_hero_img') ?>"
                     class="min-w-0 flex-1 border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-mono focus:ring-2 focus:ring-[#1E3A8A] outline-none"
                     placeholder="/assets/img/...">
              <button type="button"
                      onclick="openMediaPicker(function(url){ document.getElementById('plan_hero_img').value = url; }, 'image')"
                      class="px-3 rounded-xl bg-indigo-50 text-indigo-600 text-xs font-black border border-indigo-200 hover:bg-indigo-100 transition-colors">
                Media
              </button>
            </div>
          </div>

          <!-- Años -->
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Período de gobierno</label>
            <input name="plan_hero_anios"
                   value="<?= cfg_admin_val($config, $plan_page_defaults, 'plan_hero_anios') ?>"
                   class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none"
                   placeholder="2026 - 2030">
            <p class="text-xs text-gray-400 mt-1">Se muestra en amarillo bajo el título "Plan de Gobierno".</p>
          </div>

          <!-- Slogan -->
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1">Slogan del plan</label>
            <input name="plan_hero_slogan"
                   value="<?= cfg_admin_val($config, $plan_page_defaults, 'plan_hero_slogan') ?>"
                   class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none"
                   placeholder='"Satipo que crece, Satipo que avanza"'>
          </div>

        </div>
      </div>

      <!-- Sección PDF + CTA -->
      <div class="space-y-6">

        <!-- PDF -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="bg-[#1E3A8A] px-6 py-4 flex items-center gap-3">
            <svg class="w-4 h-4 text-[#FACC15]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
            <h2 class="text-white font-bold text-sm">Sección descarga PDF</h2>
          </div>
          <div class="p-6 space-y-4">
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">Título de la sección</label>
              <input name="plan_pdf_titulo"
                     value="<?= cfg_admin_val($config, $plan_page_defaults, 'plan_pdf_titulo') ?>"
                     class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none">
            </div>
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">Descripción</label>
              <textarea name="plan_pdf_desc" rows="2"
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none resize-none"><?= cfg_admin_val($config, $plan_page_defaults, 'plan_pdf_desc') ?></textarea>
            </div>
            <!-- ── Gestión del archivo PDF ──────────────────── -->
            <div class="border-t border-gray-100 pt-4">
              <label class="block text-xs font-black text-gray-500 uppercase mb-3">Archivo PDF</label>

              <!-- Estado actual -->
              <?php if ($pdf_exists): ?>
              <div class="flex items-center gap-3 bg-green-50 border border-green-200 rounded-xl px-4 py-3 mb-4">
                <div class="w-9 h-9 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
                  <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-xs font-black text-green-800">PDF disponible</p>
                  <p class="text-xs text-green-600 mt-0.5">
                    <?= htmlspecialchars($pdf_size_fmt) ?> &bull; Actualizado: <?= htmlspecialchars($pdf_date_fmt) ?>
                  </p>
                </div>
                <a href="<?= htmlspecialchars($pdf_pub_url) ?>" target="_blank"
                   class="flex-shrink-0 inline-flex items-center gap-1.5 bg-white border border-green-200 text-green-700 font-bold text-xs px-3 py-1.5 rounded-lg hover:bg-green-50 transition-colors">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                  </svg>
                  Ver PDF
                </a>
              </div>
              <?php else: ?>
              <div class="flex items-center gap-3 bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 mb-4">
                <div class="w-9 h-9 bg-gray-100 rounded-xl flex items-center justify-center flex-shrink-0">
                  <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                  </svg>
                </div>
                <div>
                  <p class="text-xs font-black text-gray-600">Sin PDF subido</p>
                  <p class="text-xs text-gray-400 mt-0.5">El botón de descarga en plan.php aparece desactivado hasta que subas el archivo.</p>
                </div>
              </div>
              <?php endif; ?>

              <!-- Drop zone Alpine -->
              <div x-data="{
                     dragging: false,
                     file: null,
                     uploading: false,
                     progress: 0,
                     error: '',
                     handleDrop(e) {
                       this.dragging = false;
                       const f = e.dataTransfer?.files?.[0];
                       if (f) this.setFile(f);
                     },
                     setFile(f) {
                       if (f.type !== 'application/pdf' && !f.name.toLowerCase().endsWith('.pdf')) {
                         this.error = 'Solo se permiten archivos PDF.'; this.file = null; return;
                       }
                       if (f.size > 50 * 1024 * 1024) {
                         this.error = 'El archivo supera el límite de 50 MB.'; this.file = null; return;
                       }
                       this.error = ''; this.file = f;
                     },
                     fmtSize(b) { return b >= 1048576 ? (b/1048576).toFixed(2)+' MB' : (b/1024).toFixed(1)+' KB'; },
                     submit() {
                       if (!this.file) return;
                       this.uploading = true;
                       this.progress = 0;
                       const fd = new FormData();
                       fd.append('plan_pdf_file', this.file);
                       fd.append('pdf_action', 'upload_pdf');
                       fd.append('tab', 'hero');
                       fd.append('_csrf', '<?= csrf_token() ?>');
                       const xhr = new XMLHttpRequest();
                       xhr.upload.onprogress = (e) => { if (e.lengthComputable) this.progress = Math.round(e.loaded/e.total*100); };
                       xhr.onload = () => { window.location.href = window.location.pathname + '?tab=hero&uploaded=1'; };
                       xhr.onerror = () => { this.uploading = false; this.error = 'Error de red. Intenta de nuevo.'; };
                       xhr.open('POST', window.location.pathname);
                       xhr.send(fd);
                     }
                   }">

                <!-- Drop area -->
                <div class="border-2 border-dashed rounded-xl transition-all duration-200 cursor-pointer"
                     :class="dragging ? 'border-[#1E3A8A] bg-blue-50' : (file ? 'border-green-400 bg-green-50' : 'border-gray-200 hover:border-[#1E3A8A] hover:bg-blue-50/50')"
                     @dragover.prevent="dragging = true"
                     @dragleave.prevent="dragging = false"
                     @drop.prevent="handleDrop($event)"
                     @click="$refs.pdfInput.click()">
                  <template x-if="!file">
                    <div class="flex flex-col items-center justify-center py-6 px-4 text-center">
                      <div class="w-12 h-12 bg-red-50 rounded-2xl flex items-center justify-center mb-3">
                        <svg class="w-6 h-6 text-red-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                      </div>
                      <p class="text-sm font-bold text-gray-700">Arrastra el PDF aquí o <span class="text-[#1E3A8A]">haz clic</span></p>
                      <p class="text-xs text-gray-400 mt-1">Solo PDF &bull; Máximo 50 MB</p>
                    </div>
                  </template>
                  <template x-if="file">
                    <div class="flex items-center gap-3 px-4 py-4">
                      <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                      </div>
                      <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-gray-800 truncate" x-text="file.name"></p>
                        <p class="text-xs text-gray-500" x-text="fmtSize(file.size)"></p>
                      </div>
                      <button type="button" @click.stop="file = null; $refs.pdfInput.value = ''"
                              class="text-gray-400 hover:text-red-500 transition-colors flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                      </button>
                    </div>
                  </template>
                </div>

                <input type="file" accept=".pdf,application/pdf" class="hidden"
                       x-ref="pdfInput" @change="setFile($event.target.files[0])">

                <!-- Error -->
                <p x-show="error" x-text="error"
                   class="text-xs text-red-600 font-semibold mt-2 flex items-center gap-1">
                </p>

                <!-- Progress bar -->
                <div x-show="uploading" class="mt-3">
                  <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                    <span>Subiendo PDF...</span>
                    <span x-text="progress + '%'"></span>
                  </div>
                  <div class="w-full bg-gray-100 rounded-full h-2">
                    <div class="bg-[#1E3A8A] h-2 rounded-full transition-all duration-200"
                         :style="'width:' + progress + '%'"></div>
                  </div>
                </div>

                <!-- Acciones -->
                <div class="flex gap-2 mt-3">
                  <button type="button"
                          @click="submit()"
                          :disabled="!file || uploading"
                          :class="(!file || uploading) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-900'"
                          class="flex-1 inline-flex items-center justify-center gap-2 bg-[#1E3A8A] text-white font-black text-xs px-4 py-2.5 rounded-xl transition-all">
                    <svg x-show="!uploading" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    <svg x-show="uploading" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span x-text="uploading ? 'Subiendo...' : (file ? 'Subir PDF' : 'Selecciona un archivo')"></span>
                  </button>

                  <?php if ($pdf_exists): ?>
                  <button type="button"
                          onclick="if(confirm('¿Eliminar el PDF del Plan de Gobierno? Esta acción no se puede deshacer.')) document.getElementById('pdf-delete-form').submit()"
                          class="inline-flex items-center gap-1.5 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 font-black text-xs px-4 py-2.5 rounded-xl transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Eliminar
                  </button>
                  <?php endif; ?>
                </div>

              </div><!-- fin Alpine upload -->
            </div><!-- fin gestión PDF -->
          </div>
        </div>

        <!-- CTA Final -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
          <div class="bg-[#1E3A8A] px-6 py-4 flex items-center gap-3">
            <svg class="w-4 h-4 text-[#FACC15]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <h2 class="text-white font-bold text-sm">Sección final — Únete al equipo</h2>
          </div>
          <div class="p-6 space-y-4">
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">Título</label>
              <input name="plan_cta_titulo"
                     value="<?= cfg_admin_val($config, $plan_page_defaults, 'plan_cta_titulo') ?>"
                     class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none">
            </div>
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase mb-1">Texto descriptivo</label>
              <input name="plan_cta_texto"
                     value="<?= cfg_admin_val($config, $plan_page_defaults, 'plan_cta_texto') ?>"
                     class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none">
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- Preview del hero -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="bg-gray-50 px-6 py-3 border-b border-gray-100">
        <p class="text-xs font-black text-gray-500 uppercase tracking-widest">Vista previa del hero (simulada)</p>
      </div>
      <div class="relative h-40 bg-gradient-to-r from-[#1E3A8A] to-[#1E3A8A]/80 flex items-center px-8 overflow-hidden rounded-b-2xl">
        <div class="absolute inset-0 opacity-20 bg-gradient-to-r from-[#0F2057] to-transparent"></div>
        <div class="relative flex items-center gap-6 text-white">
          <div class="w-20 h-20 bg-white/10 rounded-2xl flex items-center justify-center flex-shrink-0 border border-white/20">
            <span class="text-2xl font-black text-[#FACC15]">R</span>
          </div>
          <div>
            <p class="text-xs text-blue-300 font-bold uppercase tracking-widest mb-1">Plan de Gobierno</p>
            <p class="text-2xl font-black text-[#FACC15]">
              <?= htmlspecialchars(cfg_value($config, 'plan_hero_anios', $plan_page_defaults['plan_hero_anios'])) ?>
            </p>
            <p class="text-sm text-blue-200 mt-1 italic">
              <?= htmlspecialchars(cfg_value($config, 'plan_hero_slogan', $plan_page_defaults['plan_hero_slogan'])) ?>
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Sticky footer -->
    <div class="sticky bottom-0 bg-[#F1F5F9]/95 backdrop-blur py-4 border-t border-gray-200 flex justify-end gap-3">
      <a href="<?= BASE_URL ?>/plan.php" target="_blank"
         class="bg-white border border-gray-200 text-gray-600 font-bold px-5 py-3 rounded-xl text-sm hover:border-[#1E3A8A] hover:text-[#1E3A8A] transition-colors">
        Ver plan
      </a>
      <button type="submit"
              class="bg-[#FACC15] hover:bg-yellow-400 text-[#1E3A8A] font-black px-8 py-3 rounded-xl text-sm shadow-md hover:shadow-lg transition-all active:scale-95">
        Guardar configuración
      </button>
    </div>
  </form>

</div>

<script>
  window.__configWorkAxes = <?= $work_axes_json ?: '[]' ?>;

  // Paths SVG por ícono (subset para las cards)
  const _iconPaths = {
    seguridad: 'M12 3l7 4v5c0 5-3 8-7 9-4-1-7-4-7-9V7l7-4z',
    obra:      'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
    campo:     'M3 7l9-5 9 5v10l-9 5-9-5V7zm9-5v16M3 7l9 5 9-5',
    salud:     'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
    educacion: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
    comunidad: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0',
    agua:      'M12 3v1m0 16v1M4.22 4.22l.707.707M18.364 18.364l.707.707M1 12h1m20 0h1M4.22 19.778l.707-.707M18.364 5.636l.707-.707M12 7a5 5 0 110 10A5 5 0 0112 7z',
    transporte:'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4',
    economia:  'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    turismo:   'M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064',
    deporte:   'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    ambiente:  'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
    juventud:  'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
    gestion:   'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
  };

  function workAxesManager() {
    const presets = {
      campo:     { grad:'from-green-500 to-emerald-600',  nav_color:'bg-green-100 text-green-700',    nav_border:'border-green-300',    section_bg:'#F0FDF4', section_border:'#BBF7D0' },
      salud:     { grad:'from-rose-500 to-pink-600',      nav_color:'bg-rose-100 text-rose-700',      nav_border:'border-rose-300',     section_bg:'#FFF1F2', section_border:'#FECDD3' },
      agua:      { grad:'from-sky-500 to-cyan-600',       nav_color:'bg-sky-100 text-sky-700',        nav_border:'border-sky-300',      section_bg:'#F0F9FF', section_border:'#BAE6FD' },
      obra:      { grad:'from-orange-500 to-amber-600',   nav_color:'bg-orange-100 text-orange-700',  nav_border:'border-orange-300',   section_bg:'#FFF7ED', section_border:'#FED7AA' },
      educacion: { grad:'from-blue-500 to-indigo-600',    nav_color:'bg-blue-100 text-blue-700',      nav_border:'border-blue-300',     section_bg:'#EFF6FF', section_border:'#BFDBFE' },
      seguridad: { grad:'from-red-500 to-rose-600',       nav_color:'bg-red-100 text-red-700',        nav_border:'border-red-300',      section_bg:'#FEF2F2', section_border:'#FECACA' },
      comunidad: { grad:'from-emerald-500 to-teal-600',   nav_color:'bg-emerald-100 text-emerald-700',nav_border:'border-emerald-300',  section_bg:'#ECFDF5', section_border:'#A7F3D0' },
      transporte:{ grad:'from-slate-500 to-blue-600',     nav_color:'bg-slate-100 text-slate-700',    nav_border:'border-slate-300',    section_bg:'#F8FAFC', section_border:'#CBD5E1' },
      economia:  { grad:'from-yellow-500 to-amber-600',   nav_color:'bg-yellow-100 text-yellow-700',  nav_border:'border-yellow-300',   section_bg:'#FEFCE8', section_border:'#FDE68A' },
      turismo:   { grad:'from-fuchsia-500 to-purple-600', nav_color:'bg-fuchsia-100 text-fuchsia-700',nav_border:'border-fuchsia-300',  section_bg:'#FDF4FF', section_border:'#F5D0FE' },
      deporte:   { grad:'from-violet-500 to-indigo-600',  nav_color:'bg-violet-100 text-violet-700',  nav_border:'border-violet-300',   section_bg:'#F5F3FF', section_border:'#DDD6FE' },
      ambiente:  { grad:'from-lime-500 to-green-600',     nav_color:'bg-lime-100 text-lime-700',      nav_border:'border-lime-300',     section_bg:'#F7FEE7', section_border:'#D9F99D' },
      juventud:  { grad:'from-cyan-500 to-blue-600',      nav_color:'bg-cyan-100 text-cyan-700',      nav_border:'border-cyan-300',     section_bg:'#ECFEFF', section_border:'#A5F3FC' },
      gestion:   { grad:'from-blue-500 to-indigo-600',    nav_color:'bg-blue-100 text-blue-700',      nav_border:'border-blue-300',     section_bg:'#EFF6FF', section_border:'#BFDBFE' },
    };
    const rules = [
      ['campo',     ['agricultura','agro','agrario','campo','cafe','cacao','productor','ganader']],
      ['salud',     ['salud','posta','hospital','medic','sanitario']],
      ['agua',      ['agua','saneamiento','desague','alcantarillado','potable']],
      ['obra',      ['infraestructura','carretera','vial','vias','puente','obra','pista','camino']],
      ['educacion', ['educacion','colegio','escuela','beca','aula','docente']],
      ['seguridad', ['seguridad','serenazgo','policia','pnp','vigilancia']],
      ['comunidad', ['comunidad','comunidades','nativa','nativo','pueblo','intercultural']],
      ['transporte',['transporte','movilidad','terminal','transito']],
      ['economia',  ['economia','empleo','trabajo','mercado','emprend','comercio']],
      ['turismo',   ['turismo','turistico','cultura','cultural']],
      ['deporte',   ['deporte','recreacion','joven','juventud']],
      ['ambiente',  ['ambiente','forestal','bosque','ecologia','residuo','limpieza']],
    ];

    const _emptyAxis = () => ({
      id:'', icon:'gestion', label:'', title:'', desc:'',
      plan_desc:'', proposals:'', img:'', ...presets.gestion,
    });

    return {
      axes: Array.isArray(window.__configWorkAxes) ? JSON.parse(JSON.stringify(window.__configWorkAxes)) : [],

      // ── Drag & drop: reordenar axes (llamado por x-sort) ────────
      reorderAxes(itemKey, newPosition) {
        const oldPosition = parseInt(itemKey);
        if (oldPosition === newPosition) return;
        const moved = this.axes.splice(oldPosition, 1)[0];
        this.axes.splice(newPosition, 0, moved);
      },

      // ── Modal state ───────────────────────────────────────────
      showModal: false,
      editingIndex: -1,
      modalData: _emptyAxis(),

      openNew() {
        this.editingIndex = -1;
        this.modalData = _emptyAxis();
        this.showModal = true;
        document.body.style.overflow = 'hidden';
      },

      openEdit(i) {
        this.editingIndex = i;
        this.modalData = JSON.parse(JSON.stringify(this.axes[i]));
        this.showModal = true;
        document.body.style.overflow = 'hidden';
      },

      closeModal() {
        this.showModal = false;
        document.body.style.overflow = '';
      },

      saveModal() {
        if (!this.modalData.title) return;
        // Auto-generar ID si está vacío
        if (!this.modalData.id) {
          this.modalData.id = this.modalData.title
            .toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'')
            .replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');
        }
        // Auto-fill label si está vacío
        if (!this.modalData.label) this.modalData.label = this.modalData.title;

        if (this.editingIndex === -1) {
          this.axes.push(JSON.parse(JSON.stringify(this.modalData)));
        } else {
          this.axes[this.editingIndex] = JSON.parse(JSON.stringify(this.modalData));
        }
        this.closeModal();
      },

      // ── Auto-fill al escribir título ─────────────────────────
      autoFillModal() {
        if (!this.modalData.label) this.modalData.label = this.modalData.title;
        if (!this.modalData.id && this.modalData.title) {
          this.modalData.id = this.modalData.title
            .toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g,'')
            .replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');
        }
        this.applyPreset(this.modalData);
      },

      // ── Helpers ───────────────────────────────────────────────
      detectIcon(axis) {
        const text = `${axis.title||''} ${axis.label||''}`.toLowerCase()
          .normalize('NFD').replace(/[̀-ͯ]/g,'');
        for (const [icon,words] of rules) {
          if (words.some(w => text.includes(w))) return icon;
        }
        return axis.icon || 'gestion';
      },

      applyPreset(axis) {
        const icon = this.detectIcon(axis);
        axis.icon = icon;
        Object.assign(axis, presets[icon] || presets.gestion);
        if (!axis.id && axis.title) {
          axis.id = axis.title.toLowerCase().normalize('NFD')
            .replace(/[̀-ͯ]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');
        }
      },

      iconPath(icon) {
        return _iconPaths[icon] || _iconPaths.gestion;
      },

      countLines(text) {
        if (!text || !text.trim()) return 0;
        return text.trim().split('\n').filter(l => l.trim()).length;
      },

      // ── Submit: serializa axes a JSON y envía el form ────────
      submitEjes() {
        const hidden = document.getElementById('axes-json-input');
        if (hidden) hidden.value = JSON.stringify(this.axes);
        document.getElementById('ejes-form').submit();
      },
    };
  }
</script>

<?php include __DIR__ . '/_media-picker.php'; ?>

<!-- Formulario oculto: eliminar PDF -->
<form id="pdf-delete-form" method="POST" style="display:none">
  <?= csrf_field() ?>
  <input type="hidden" name="pdf_action" value="delete_pdf">
  <input type="hidden" name="tab" value="hero">
</form>

    </main>
  </div>
</body>
</html>
