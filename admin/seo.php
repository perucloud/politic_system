<?php
// ============================================================
// seo.php — Configuración SEO por página
// Requiere rol mínimo: admin
// ============================================================
session_start();
require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/config/auth.php';

require_rol('admin');
require_modulo('seo', $pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

// ── Procesar guardado individual de página ─────────────────
$flash_id  = null;   // id de la página guardada
$flash_ok  = false;
$flash_err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['seo_id'])) {
    $seo_id          = (int)$_POST['seo_id'];
    $meta_title      = trim($_POST['meta_title']      ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    $og_image        = trim($_POST['og_image']         ?? '');

    // Validación básica de longitudes
    $meta_title      = mb_substr($meta_title, 0, 200);
    $meta_description = mb_substr($meta_description, 0, 320);
    $og_image        = mb_substr($og_image, 0, 255);

    try {
        $stmt = $pdo->prepare(
            "UPDATE seo_paginas
             SET meta_title = ?, meta_description = ?, og_image = ?, actualizado = NOW()
             WHERE id = ?"
        );
        $stmt->execute([$meta_title, $meta_description, $og_image, $seo_id]);

        // Obtener la etiqueta de la página para el log
        $row_label = $pdo->prepare("SELECT pagina_label FROM seo_paginas WHERE id = ?");
        $row_label->execute([$seo_id]);
        $pagina_label = $row_label->fetchColumn() ?: "id:{$seo_id}";

        log_activity($pdo, "Actualizó SEO de página: {$pagina_label}", 'configuracion');

        $flash_id = $seo_id;
        $flash_ok = true;
    } catch (Exception $e) {
        $flash_id  = $seo_id;
        $flash_err = 'Error al guardar: ' . htmlspecialchars($e->getMessage());
    }
}

// ── Cargar todas las páginas SEO ──────────────────────────
$paginas = [];
try {
    $paginas = $pdo->query(
        "SELECT id, pagina, pagina_label, meta_title, meta_description, og_image, actualizado
         FROM seo_paginas
         ORDER BY pagina_label ASC"
    )->fetchAll();
} catch (Exception $e) {
    $paginas = [];
}

// ── Helper: valor seguro para atributos HTML ──────────────
function s(mixed $v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── Helper: valor escapado para uso en Alpine.js x-data ──
function js_str(mixed $v): string {
    return addslashes((string)($v ?? ''));
}

$base_url_display = rtrim(BASE_URL, '/');

$page_title = 'SEO por Página';
include __DIR__ . '/layout.php';
?>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- CABECERA                                                    -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-6">
  <div>
    <h2 class="text-2xl font-black text-gray-800 flex items-center gap-3">
      SEO por Página
      <!-- Tooltip info -->
      <span class="relative inline-block" x-data="{ tip: false }">
        <button
          @mouseenter="tip = true" @mouseleave="tip = false"
          @focus="tip = true"    @blur="tip = false"
          type="button"
          class="w-5 h-5 rounded-full bg-blue-100 text-blue-500 text-xs font-black
                 flex items-center justify-center hover:bg-blue-200 transition-colors focus:outline-none"
          aria-label="Información sobre SEO"
        >?</button>
        <div
          x-show="tip"
          x-cloak
          x-transition:enter="transition ease-out duration-150"
          x-transition:enter-start="opacity-0 scale-95"
          x-transition:enter-end="opacity-100 scale-100"
          class="absolute left-1/2 -translate-x-1/2 top-7 z-50 w-72 bg-gray-900 text-white
                 text-xs rounded-xl p-3.5 shadow-xl leading-relaxed pointer-events-none"
        >
          <p class="font-bold text-yellow-300 mb-1">¿Qué es el SEO por página?</p>
          <p class="text-gray-300">
            Cada página de tu sitio puede tener su propio <strong class="text-white">Meta Title</strong>
            (el título que aparece en los resultados de búsqueda de Google),
            <strong class="text-white">Meta Description</strong> (el texto descriptivo bajo el título)
            y una imagen <strong class="text-white">Open Graph</strong> para redes sociales.
            Configurarlos correctamente mejora el posicionamiento y la apariencia en buscadores.
          </p>
          <!-- Arrow -->
          <div class="absolute -top-1.5 left-1/2 -translate-x-1/2 w-3 h-3 bg-gray-900 rotate-45 rounded-sm"></div>
        </div>
      </span>
    </h2>
    <p class="text-sm text-gray-400 mt-0.5">
      Gestiona los metadatos de búsqueda para cada página del sitio web.
    </p>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- ACORDEÓN DE PÁGINAS                                         -->
<!-- ═══════════════════════════════════════════════════════════ -->
<?php if (empty($paginas)): ?>
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 flex flex-col items-center justify-center py-20 text-center px-6">
  <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mb-4">
    <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
    </svg>
  </div>
  <p class="text-gray-500 font-semibold text-sm">No hay páginas SEO configuradas</p>
  <p class="text-gray-400 text-xs mt-1">
    Inserta filas en la tabla <code class="bg-gray-100 px-1 rounded">seo_paginas</code> para comenzar.
  </p>
</div>
<?php else: ?>

<div class="space-y-3">
  <?php foreach ($paginas as $pg):
    $pid         = (int)$pg['id'];
    $saved_ok    = ($flash_id === $pid && $flash_ok);
    $saved_err   = ($flash_id === $pid && !$flash_ok) ? ($flash_err ?? '') : '';
    $actualizado = $pg['actualizado']
                   ? date('d/m/Y H:i', strtotime($pg['actualizado']))
                   : 'Nunca';

    // Valores iniciales para Alpine
    $init_title = js_str($pg['meta_title']);
    $init_desc  = js_str($pg['meta_description']);
    $init_img   = js_str($pg['og_image']);
  ?>

  <!-- ── Card / Acordeón ────────────────────────────────────── -->
  <div
    x-data="{
      open: <?= $saved_ok || $saved_err ? 'true' : 'false' ?>,
      title: '<?= $init_title ?>',
      desc:  '<?= $init_desc ?>',
      img:   '<?= $init_img ?>',
      get titleLen() { return this.title.length; },
      get descLen()  { return this.desc.length;  }
    }"
    class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden
           hover:border-gray-200 transition-all duration-200"
  >

    <!-- Cabecera del acordeón -->
    <button
      type="button"
      @click="open = !open"
      class="w-full flex items-center justify-between px-5 py-4 text-left
             hover:bg-gray-50/60 transition-colors focus:outline-none group"
    >
      <div class="flex items-center gap-3 min-w-0">
        <!-- Ícono página -->
        <div class="w-9 h-9 flex-shrink-0 bg-[#EEF2FF] rounded-xl flex items-center justify-center">
          <svg class="w-4.5 h-4.5 text-[#1E3A8A]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
        </div>
        <div class="min-w-0">
          <p class="text-sm font-bold text-gray-800 truncate">
            <?= s($pg['pagina_label']) ?>
          </p>
          <p class="text-[11px] text-gray-400 font-mono truncate">
            <?= s($pg['pagina']) ?>
            <span class="mx-1.5 text-gray-300">·</span>
            <span class="not-italic font-sans text-gray-400">Actualizado: <?= $actualizado ?></span>
          </p>
        </div>
      </div>

      <div class="flex items-center gap-3 flex-shrink-0 ml-3">
        <!-- Badge "Guardado" si se acaba de salvar esta tarjeta -->
        <?php if ($saved_ok): ?>
        <span class="inline-flex items-center gap-1 text-[11px] font-bold bg-green-100 text-green-700
                     px-2.5 py-1 rounded-full">
          <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
          </svg>
          Guardado
        </span>
        <?php elseif ($saved_err): ?>
        <span class="inline-flex items-center gap-1 text-[11px] font-bold bg-red-100 text-red-600
                     px-2.5 py-1 rounded-full">Error</span>
        <?php endif; ?>

        <!-- Indicador SEO completo / incompleto -->
        <?php
        $has_title = trim($pg['meta_title'] ?? '') !== '';
        $has_desc  = trim($pg['meta_description'] ?? '') !== '';
        ?>
        <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-1 rounded-full
                     <?= ($has_title && $has_desc) ? 'bg-green-50 text-green-600' : 'bg-amber-50 text-amber-600' ?>">
          <span class="w-1.5 h-1.5 rounded-full <?= ($has_title && $has_desc) ? 'bg-green-500' : 'bg-amber-400' ?> inline-block"></span>
          <?= ($has_title && $has_desc) ? 'Completo' : 'Incompleto' ?>
        </span>

        <!-- Chevron -->
        <svg
          class="w-4 h-4 text-gray-400 flex-shrink-0 transition-transform duration-200 group-hover:text-gray-600"
          :class="open ? 'rotate-180' : ''"
          fill="none" viewBox="0 0 24 24" stroke="currentColor"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
      </div>
    </button>

    <!-- Contenido expandible -->
    <div
      x-show="open"
      x-cloak
      x-transition:enter="transition ease-out duration-200"
      x-transition:enter-start="opacity-0 -translate-y-1"
      x-transition:enter-end="opacity-100 translate-y-0"
      x-transition:leave="transition ease-in duration-150"
      x-transition:leave-start="opacity-100 translate-y-0"
      x-transition:leave-end="opacity-0 -translate-y-1"
    >
      <!-- Separador -->
      <div class="border-t border-gray-100 mx-5"></div>

      <!-- Flash de error para esta tarjeta -->
      <?php if ($saved_err): ?>
      <div class="mx-5 mt-4 flex items-center gap-2 bg-red-50 border border-red-200 text-red-700
                  text-sm font-medium px-4 py-3 rounded-xl">
        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <?= $saved_err ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="" class="p-5 pt-4">
        <input type="hidden" name="seo_id" value="<?= $pid ?>">

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

          <!-- ── Columna izquierda: campos ──────────────────── -->
          <div class="space-y-5">

            <!-- Meta Title -->
            <div>
              <div class="flex items-center justify-between mb-1.5">
                <label class="text-sm font-semibold text-gray-700">
                  Meta Title
                  <span class="text-gray-400 font-normal text-xs ml-1">(máx. 60 chars recomendado)</span>
                </label>
                <span
                  class="text-xs font-mono font-semibold transition-colors"
                  :class="titleLen > 60 ? 'text-red-500' : titleLen > 50 ? 'text-amber-500' : 'text-gray-400'"
                  x-text="titleLen + '/60'"
                ></span>
              </div>
              <input
                type="text"
                name="meta_title"
                maxlength="200"
                x-model="title"
                value="<?= s($pg['meta_title']) ?>"
                placeholder="Ej: Ivan Cisneros — Candidato al Congreso 2026"
                class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-800
                       focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/30 focus:border-[#1E3A8A]
                       transition placeholder-gray-300"
                :class="titleLen > 60 ? 'border-red-300 focus:ring-red-300 focus:border-red-400' : ''"
              >
              <p class="text-[11px] text-gray-400 mt-1">
                Aparece como enlace azul en los resultados de Google. Sé conciso y descriptivo.
              </p>
            </div>

            <!-- Meta Description -->
            <div>
              <div class="flex items-center justify-between mb-1.5">
                <label class="text-sm font-semibold text-gray-700">
                  Meta Description
                  <span class="text-gray-400 font-normal text-xs ml-1">(máx. 160 chars recomendado)</span>
                </label>
                <span
                  class="text-xs font-mono font-semibold transition-colors"
                  :class="descLen > 160 ? 'text-red-500' : descLen > 140 ? 'text-amber-500' : 'text-gray-400'"
                  x-text="descLen + '/160'"
                ></span>
              </div>
              <textarea
                name="meta_description"
                maxlength="320"
                rows="3"
                x-model="desc"
                placeholder="Ej: Conoce las propuestas de Ivan Cisneros para el desarrollo de Satipo…"
                class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-800
                       focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/30 focus:border-[#1E3A8A]
                       transition placeholder-gray-300 resize-none"
                :class="descLen > 160 ? 'border-red-300 focus:ring-red-300 focus:border-red-400' : ''"
              ><?= s($pg['meta_description']) ?></textarea>
              <p class="text-[11px] text-gray-400 mt-1">
                Texto de apoyo que aparece bajo el título en Google. Máximo recomendado: 160 caracteres.
              </p>
            </div>

            <!-- OG Image -->
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                Imagen Open Graph
                <span class="text-gray-400 font-normal text-xs ml-1">(URL)</span>
              </label>
              <input
                type="url"
                name="og_image"
                maxlength="255"
                x-model="img"
                value="<?= s($pg['og_image']) ?>"
                placeholder="https://tudominio.com/assets/img/og-<?= s($pg['pagina']) ?>.jpg"
                class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-800
                       focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/30 focus:border-[#1E3A8A]
                       transition placeholder-gray-300"
              >
              <p class="text-[11px] text-gray-400 mt-1">
                Imagen que aparece al compartir esta página en Facebook, WhatsApp y otras redes. Recomendado: 1200×630 px.
              </p>
            </div>

          </div><!-- /columna izquierda -->

          <!-- ── Columna derecha: previsualización Google ───── -->
          <div>
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
              Vista previa en Google
            </p>

            <!-- Simulación resultado Google -->
            <div class="bg-white border border-gray-200 rounded-2xl p-4 shadow-sm">
              <!-- Favicon + dominio (decorativo) -->
              <div class="flex items-center gap-2 mb-2">
                <div class="w-5 h-5 rounded-full bg-gradient-to-br from-[#1E3A8A] to-[#38BDF8] flex items-center justify-center flex-shrink-0">
                  <span class="text-white text-[8px] font-black">J</span>
                </div>
                <div>
                  <p class="text-xs text-gray-700 font-medium leading-none">ivancisneros.pe</p>
                  <p class="text-[10px] text-gray-400 leading-none mt-0.5">
                    <?= s($base_url_display) ?> ›
                    <span class="text-gray-500"><?= s($pg['pagina']) ?></span>
                  </p>
                </div>
              </div>

              <!-- Título -->
              <p
                class="text-blue-700 text-base font-medium leading-snug mb-1 line-clamp-1 cursor-pointer
                       hover:underline decoration-blue-700"
                x-text="title || '<?= js_str($pg['pagina_label']) ?> — Ivan Cisneros'"
              ></p>

              <!-- Descripción -->
              <p
                class="text-gray-600 text-sm leading-relaxed line-clamp-2"
                x-text="desc || 'Haz clic para leer más sobre <?= js_str($pg['pagina_label']) ?> en el sitio oficial de Ivan Cisneros.'"
              ></p>

              <!-- Advertencias de longitud -->
              <div class="mt-3 space-y-1">
                <template x-if="titleLen > 60">
                  <p class="text-[11px] text-amber-600 flex items-center gap-1">
                    <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                    El título supera los 60 caracteres recomendados y puede truncarse en Google.
                  </p>
                </template>
                <template x-if="descLen > 160">
                  <p class="text-[11px] text-amber-600 flex items-center gap-1">
                    <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                    La descripción supera los 160 caracteres recomendados y puede truncarse.
                  </p>
                </template>
              </div>
            </div>

            <!-- Preview OG Image si hay URL -->
            <div x-show="img.startsWith('http')" x-cloak class="mt-3">
              <p class="text-[11px] text-gray-400 mb-1.5 font-semibold uppercase tracking-wide">
                Open Graph Image
              </p>
              <div class="rounded-xl overflow-hidden border border-gray-200 bg-gray-50">
                <img
                  :src="img"
                  alt="Vista previa OG"
                  class="w-full h-36 object-cover"
                  onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                >
                <div class="hidden w-full h-36 items-center justify-center text-gray-400 text-xs flex-col gap-1">
                  <svg class="w-6 h-6 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                  </svg>
                  No se pudo cargar la imagen
                </div>
              </div>
            </div>

          </div><!-- /columna derecha -->

        </div><!-- /grid -->

        <!-- ── Botón guardar ──────────────────────────────── -->
        <div class="flex items-center justify-between mt-6 pt-4 border-t border-gray-100">
          <?php if ($saved_ok): ?>
          <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-green-600">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Cambios guardados correctamente
          </span>
          <?php else: ?>
          <span></span>
          <?php endif; ?>

          <button
            type="submit"
            class="inline-flex items-center gap-2 bg-yellow-400 hover:bg-yellow-500 text-gray-900 font-bold
                   px-5 py-2.5 rounded-xl text-sm transition-all duration-200 shadow-sm hover:shadow-md active:scale-95"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            Guardar cambios
          </button>
        </div>

      </form>
    </div><!-- /expandible -->

  </div><!-- /card -->

  <?php endforeach; ?>
</div><!-- /space-y-3 -->

<!-- Nota informativa -->
<div class="mt-5 bg-blue-50 border border-blue-200 rounded-2xl px-5 py-4 flex items-start gap-3">
  <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
  </svg>
  <div>
    <p class="text-sm font-semibold text-blue-800">Cambios aplicados en tiempo real</p>
    <p class="text-xs text-blue-600 mt-0.5">
      Los cambios que guardes aquí se reflejan inmediatamente en el HTML de cada página.
      Los motores de búsqueda como Google actualizan su caché en su próxima visita al sitio
      (generalmente entre 24 y 72 horas).
    </p>
  </div>
</div>

<?php endif; // fin if empty($paginas) ?>

    </main>
  </div>
</body>
</html>
