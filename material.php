<?php
// ============================================================
// material.php - Página pública de Material Publicitario
// ============================================================
require_once __DIR__ . '/includes/config/db.php';
require_once __DIR__ . '/includes/helpers/config.php';

$cfg_camp = [];
try {
    $cfg_camp = $pdo->query("SELECT clave, valor FROM configuracion")
                    ->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {}

// ── Página desactivada por admin ─────────────────────────────
$page_activa = cfg_value($cfg_camp, 'material_page_active', '1') !== '0';

// ── Filtros públicos ─────────────────────────────────────────
$filtro_tipo   = $_GET['tipo']    ?? '';
$filtro_cand   = $_GET['candidat']?? '';
$filtro_q      = trim($_GET['q']  ?? '');
$valid_tipos   = ['banner','flyer','folleto','libro_electronico'];
$valid_cands   = ['provincial','distrital'];
$where = ["activo=1"]; $params = [];
if (in_array($filtro_tipo, $valid_tipos, true)) { $where[] = "tipo_material=?";    $params[] = $filtro_tipo; }
if (in_array($filtro_cand, $valid_cands, true)) { $where[] = "tipo_candidatura=?"; $params[] = $filtro_cand; }
if ($filtro_q !== '')                           { $where[] = "nombre LIKE ?";       $params[] = '%'.$filtro_q.'%'; }
$sql_w = 'WHERE ' . implode(' AND ', $where);

$materiales = [];
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS material_publicitario (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        nombre           VARCHAR(255)  NOT NULL,
        tipo_candidatura ENUM('provincial','distrital') NOT NULL DEFAULT 'provincial',
        tipo_material    ENUM('banner','flyer','folleto','libro_electronico') NOT NULL DEFAULT 'banner',
        formato          ENUM('jpg','png','cdr','ai','psd') NOT NULL DEFAULT 'jpg',
        archivo          VARCHAR(500)  NOT NULL,
        activo           TINYINT(1)   NOT NULL DEFAULT 1,
        fecha_publicacion DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        creado_en        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $sq = $pdo->prepare("SELECT * FROM material_publicitario $sql_w ORDER BY fecha_publicacion DESC");
    $sq->execute($params);
    $materiales = $sq->fetchAll();
} catch (Exception $e) {}

$tipo_labels = ['banner'=>'Banner','flyer'=>'Flyer','folleto'=>'Folleto','libro_electronico'=>'Libro Electrónico'];

// ── Página desactivada: mostrar pantalla de mantenimiento ────
if (!$page_activa) {
    http_response_code(503);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Material Publicitario - Próximamente</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gradient-to-br from-[#0F2057] to-[#1E3A8A] flex items-center justify-center p-6">
  <div class="text-center max-w-md">
    <div class="w-20 h-20 bg-[#FACC15] rounded-3xl flex items-center justify-center mx-auto mb-6 shadow-xl">
      <svg class="w-10 h-10 text-[#0F2057]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
      </svg>
    </div>
    <h1 class="text-3xl font-black text-white mb-3">Material Publicitario</h1>
    <p class="text-white/60 text-base mb-8">Esta sección estará disponible muy pronto.<br>Estamos preparando los diseños para ti.</p>
    <a href="<?= BASE_URL ?>/index.php"
       class="inline-flex items-center gap-2 bg-[#FACC15] text-[#0F2057] font-black px-6 py-3 rounded-xl hover:bg-yellow-300 transition-all">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
      </svg>
      Volver al inicio
    </a>
  </div>
</body>
</html>
<?php exit; } ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Material Publicitario - <?= htmlspecialchars(cfg_value($cfg_camp,'site_header_signature','Ivan Cisneros')) ?></title>
  <?php $fv=cfg_value($cfg_camp,'site_favicon','/assets/img/logos/logorp.webp'); $fv_url=(str_starts_with($fv,'/')?BASE_URL:'').$fv; ?>
  <link rel="icon" href="<?= htmlspecialchars($fv_url) ?>">
  <link rel="apple-touch-icon" href="<?= htmlspecialchars($fv_url) ?>">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{}}}</script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <style>
    [x-cloak]{ display:none !important; }
    .mat-card{ transition: transform .18s ease, box-shadow .18s ease; }
    .mat-card:hover{ transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0,0,0,.10); }
    .thumb-pub{ position:relative; height:200px; overflow:hidden; border-radius:16px 16px 0 0; }
    .thumb-pub img{ width:100%; height:100%; object-fit:cover; }
    .fmt-icon-pub{ width:100%; height:100%; display:flex; flex-direction:column;
                   align-items:center; justify-content:center; gap:8px; }
    .badge-sm{ display:inline-flex; align-items:center; font-size:10px; font-weight:800;
               padding:3px 10px; border-radius:99px; letter-spacing:.04em; text-transform:uppercase; }
  </style>
</head>
<body class="bg-[#F8FAFC]" x-data="shareModal()">

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<!-- ── Hero ──────────────────────────────────────────────── -->
<section class="pt-28 pb-16 bg-gradient-to-br from-[#0F2057] via-[#1E3A8A] to-[#1E4BAF] relative overflow-hidden">
  <div class="absolute inset-0 opacity-10"
       style="background-image:radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size:28px 28px;"></div>
  <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
    <span class="inline-flex items-center gap-2 bg-white/10 text-white/80 text-xs font-bold uppercase tracking-widest px-4 py-1.5 rounded-full mb-5 border border-white/15">
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
      </svg>
      Descarga gratuita
    </span>
    <h1 class="text-4xl sm:text-5xl font-black text-white mb-4 leading-tight">
      Material<br><span class="text-[#FACC15]">Publicitario</span>
    </h1>
    <p class="text-white/60 text-lg max-w-xl mx-auto mb-8">
      Descarga y difunde los diseños de campaña. Ayúdanos a llegar a más familias de la provincia.
    </p>
    <!-- Stats -->
    <div class="inline-flex items-center gap-6 bg-white/10 border border-white/15 rounded-2xl px-6 py-3">
      <div class="text-center">
        <p class="text-2xl font-black text-white"><?= count($materiales) ?></p>
        <p class="text-white/50 text-xs uppercase tracking-widest">Diseños</p>
      </div>
      <div class="w-px h-8 bg-white/20"></div>
      <div class="text-center">
        <p class="text-2xl font-black text-[#FACC15]">100%</p>
        <p class="text-white/50 text-xs uppercase tracking-widest">Gratuito</p>
      </div>
    </div>
  </div>
</section>

<!-- ── Filtros ────────────────────────────────────────────── -->
<section class="sticky top-16 z-20 bg-white border-b border-gray-100 shadow-sm">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
    <form method="GET" class="flex flex-wrap items-center gap-3">
      <input type="text" name="q" value="<?= htmlspecialchars($filtro_q) ?>"
             placeholder="Buscar diseño..."
             class="border border-gray-200 rounded-xl px-3 py-1.5 text-sm w-48 focus:outline-none focus:ring-2 focus:ring-blue-100">
      <div class="flex items-center gap-2 flex-wrap">
        <?php
        $tipos_btns = ['' => 'Todos', 'banner' => 'Banners', 'flyer' => 'Flyers', 'folleto' => 'Folletos', 'libro_electronico' => 'Libros'];
        foreach ($tipos_btns as $v => $l):
          $active = $filtro_tipo === $v;
        ?>
        <a href="?<?= http_build_query(array_filter(['tipo'=>$v,'candidat'=>$filtro_cand,'q'=>$filtro_q])) ?>"
           class="text-xs font-bold px-3 py-1.5 rounded-xl transition-all
                  <?= $active ? 'bg-[#1E3A8A] text-white shadow' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
          <?= $l ?>
        </a>
        <?php endforeach; ?>
      </div>
      <div class="flex items-center gap-2 ml-auto">
        <a href="?<?= http_build_query(array_filter(['tipo'=>$filtro_tipo,'candidat'=>'provincial','q'=>$filtro_q])) ?>"
           class="text-xs font-bold px-3 py-1.5 rounded-xl transition-all
                  <?= $filtro_cand==='provincial' ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
          Provincial
        </a>
        <a href="?<?= http_build_query(array_filter(['tipo'=>$filtro_tipo,'candidat'=>'distrital','q'=>$filtro_q])) ?>"
           class="text-xs font-bold px-3 py-1.5 rounded-xl transition-all
                  <?= $filtro_cand==='distrital' ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
          Distrital
        </a>
        <?php if ($filtro_q): ?>
        <button type="submit" class="text-xs font-bold bg-[#1E3A8A] text-white px-3 py-1.5 rounded-xl">Buscar</button>
        <a href="material.php" class="text-xs text-gray-400 hover:text-gray-600 font-medium py-1.5">× Limpiar</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</section>

<!-- ── Grid ───────────────────────────────────────────────── -->
<section class="py-14">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <?php if (empty($materiales)): ?>
    <div class="text-center py-24">
      <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-5">
        <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
      </div>
      <p class="text-gray-400 font-semibold text-lg">No hay materiales disponibles por el momento.</p>
      <p class="text-gray-300 text-sm mt-1">Vuelve pronto, estamos preparando los diseños.</p>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
      <?php foreach ($materiales as $mat):
        $es_imagen   = in_array($mat['formato'], ['jpg','png'], true);
        $archivo_url = BASE_URL . '/uploads/material/' . rawurlencode($mat['archivo']);
        $tipo_label  = $tipo_labels[$mat['tipo_material']] ?? $mat['tipo_material'];
        $cand_label  = $mat['tipo_candidatura'] === 'provincial' ? 'Provincial' : 'Distrital';
        $fmt_styles  = [
          'cdr' => ['bg'=>'linear-gradient(135deg,#007A5E,#00A383)', 'label'=>'CDR', 'sub'=>'CorelDRAW'],
          'ai'  => ['bg'=>'linear-gradient(135deg,#FF7C00,#FF9A00)', 'label'=>'Ai',  'sub'=>'Illustrator'],
          'psd' => ['bg'=>'linear-gradient(135deg,#1473E6,#31A8FF)', 'label'=>'Ps',  'sub'=>'Photoshop'],
        ];
        $fs = $fmt_styles[$mat['formato']] ?? ['bg'=>'linear-gradient(135deg,#4F46E5,#7C3AED)','label'=>strtoupper($mat['formato']),'sub'=>'Archivo'];
      ?>
      <div class="mat-card bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">

        <!-- Thumbnail -->
        <div class="thumb-pub">
          <?php if ($es_imagen): ?>
            <img src="<?= htmlspecialchars($archivo_url) ?>"
                 alt="<?= htmlspecialchars($mat['nombre']) ?>"
                 loading="lazy"
                 onerror="this.parentElement.innerHTML='<div class=\'fmt-icon-pub\' style=\'background:linear-gradient(135deg,#E5E7EB,#D1D5DB)\'><svg style=\'width:40px;height:40px;color:#9CA3AF\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'1.5\' d=\'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z\'/></svg><p style=\'font-size:12px;color:#9CA3AF;font-weight:600\'>Sin preview</p></div>'">
          <?php else: ?>
            <div class="fmt-icon-pub" style="background:<?= $fs['bg'] ?>">
              <span style="font-size:3.2rem;font-weight:900;color:rgba(255,255,255,.95);line-height:1;font-family:'Inter',sans-serif;letter-spacing:-.04em"><?= $fs['label'] ?></span>
              <span style="font-size:11px;color:rgba(255,255,255,.6);font-weight:700;letter-spacing:.08em;text-transform:uppercase"><?= $fs['sub'] ?></span>
              <div style="display:flex;align-items:center;gap:6px;margin-top:8px;background:rgba(0,0,0,.15);padding:5px 12px;border-radius:99px">
                <svg style="width:14px;height:14px;color:rgba(255,255,255,.7)" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                </svg>
                <span style="font-size:11px;color:rgba(255,255,255,.8);font-weight:700">Archivo de diseño</span>
              </div>
            </div>
          <?php endif; ?>
          <!-- Badges overlay -->
          <div class="absolute top-3 left-3 flex gap-1.5">
            <span class="badge-sm bg-white/90 text-[#1E3A8A] backdrop-blur-sm shadow-sm"><?= $tipo_label ?></span>
          </div>
          <div class="absolute top-3 right-3">
            <span class="badge-sm bg-[#1E3A8A]/85 text-white backdrop-blur-sm"><?= $cand_label ?></span>
          </div>
        </div>

        <!-- Info -->
        <div class="p-5 flex flex-col flex-1">
          <h3 class="font-bold text-gray-800 text-sm leading-tight mb-3 line-clamp-2">
            <?= htmlspecialchars($mat['nombre']) ?>
          </h3>
          <div class="flex items-center gap-2 mb-auto">
            <span class="badge-sm <?= $mat['formato']==='cdr'?'bg-green-100 text-green-700':($mat['formato']==='ai'?'bg-orange-100 text-orange-700':($mat['formato']==='psd'?'bg-sky-100 text-sky-700':'bg-indigo-100 text-indigo-700')) ?>">
              <?= strtoupper($mat['formato']) ?>
            </span>
            <span class="text-[10px] text-gray-400 ml-auto">
              <?= date('d M Y', strtotime($mat['fecha_publicacion'])) ?>
            </span>
          </div>

          <!-- Botones descargar + compartir -->
          <div class="mt-4 flex gap-2">
            <a href="<?= htmlspecialchars($archivo_url) ?>" download
               class="flex-1 flex items-center justify-center gap-1.5 py-2.5 rounded-xl
                      bg-[#1E3A8A] hover:bg-blue-900 text-white font-bold text-sm
                      transition-all hover:scale-[1.02] active:scale-95 shadow-md shadow-blue-900/20 group">
              <svg class="w-3.5 h-3.5 group-hover:animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
              </svg>
              Descargar
            </a>
            <button type="button"
                    @click="openShare(
                      '<?= addslashes($archivo_url) ?>',
                      '<?= addslashes(htmlspecialchars($mat['nombre'], ENT_QUOTES)) ?>',
                      '<?= $mat['formato'] ?>',
                      <?= $es_imagen ? "'" . addslashes($archivo_url) . "'" : 'null' ?>
                    )"
                    class="px-3 py-2.5 rounded-xl border-2 border-gray-200 hover:border-[#1E3A8A]
                           text-gray-500 hover:text-[#1E3A8A] transition-all hover:scale-[1.02] active:scale-95
                           flex items-center justify-center" title="Compartir">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
              </svg>
            </button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Mensaje difusión -->
    <div class="mt-16 text-center bg-gradient-to-r from-[#1E3A8A] to-[#1E4BAF] rounded-3xl p-10 shadow-xl">
      <div class="w-14 h-14 bg-[#FACC15] rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
        <svg class="w-7 h-7 text-[#0F2057]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/>
        </svg>
      </div>
      <h3 class="text-white font-black text-2xl mb-2">¡Ayúdanos a difundir!</h3>
      <p class="text-white/60 text-sm max-w-md mx-auto">
        Descarga, comparte e imprime estos materiales. Cada difusión cuenta para llegar a más familias de la provincia de Satipo.
      </p>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- ── Share Modal ────────────────────────────────────────── -->
<div x-cloak x-show="showModal"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @keydown.escape.window="close()"
     class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4"
     style="background:rgba(15,32,87,.55);backdrop-filter:blur(6px);">

  <!-- Panel -->
  <div x-show="showModal"
       x-transition:enter="transition ease-out duration-250"
       x-transition:enter-start="opacity-0 scale-95 translate-y-4"
       x-transition:enter-end="opacity-100 scale-100 translate-y-0"
       x-transition:leave="transition ease-in duration-150"
       x-transition:leave-start="opacity-100 scale-100 translate-y-0"
       x-transition:leave-end="opacity-0 scale-95 translate-y-4"
       @click.outside="close()"
       class="bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden">

    <!-- Header -->
    <div class="flex items-start justify-between px-6 pt-6 pb-4 border-b border-gray-100">
      <div class="flex-1 min-w-0 pr-3">
        <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Compartir diseño</p>
        <h3 class="font-black text-gray-800 text-base leading-tight truncate" x-text="current.nombre"></h3>
        <span class="inline-flex items-center mt-1.5 text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full"
              :class="{
                'bg-green-100 text-green-700': current.formato==='cdr',
                'bg-orange-100 text-orange-700': current.formato==='ai',
                'bg-sky-100 text-sky-700': current.formato==='psd',
                'bg-indigo-100 text-indigo-700': !['cdr','ai','psd'].includes(current.formato)
              }" x-text="current.formato.toUpperCase()"></span>
      </div>
      <button @click="close()"
              class="w-8 h-8 flex items-center justify-center rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-500 transition-colors flex-shrink-0">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    <!-- Preview -->
    <div class="mx-6 mt-5 rounded-2xl overflow-hidden" style="height:160px;">
      <template x-if="current.thumb">
        <img :src="current.thumb" :alt="current.nombre"
             class="w-full h-full object-cover">
      </template>
      <template x-if="!current.thumb">
        <div class="w-full h-full flex flex-col items-center justify-center gap-2"
             :style="fmtBg(current.formato)">
          <span class="font-black text-white/95 leading-none"
                style="font-size:3.5rem;letter-spacing:-.04em"
                x-text="fmtLabel(current.formato)"></span>
          <span class="text-xs font-bold text-white/60 uppercase tracking-widest"
                x-text="fmtSub(current.formato)"></span>
        </div>
      </template>
    </div>

    <!-- Acciones -->
    <div class="px-6 py-5 flex flex-col gap-3">

      <!-- Copiar enlace -->
      <button @click="copyLink()"
              class="w-full flex items-center gap-4 px-4 py-3.5 rounded-2xl border-2 transition-all"
              :class="copied
                ? 'border-green-400 bg-green-50 text-green-700'
                : 'border-gray-200 hover:border-[#1E3A8A] hover:bg-blue-50 text-gray-700'">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center transition-colors flex-shrink-0"
             :class="copied ? 'bg-green-100' : 'bg-gray-100'">
          <svg x-show="!copied" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
          </svg>
          <svg x-show="copied" class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
          </svg>
        </div>
        <div class="text-left">
          <p class="font-bold text-sm" x-text="copied ? '¡Enlace copiado!' : 'Copiar enlace'"></p>
          <p class="text-xs opacity-60 mt-0.5" x-text="copied ? 'Listo para pegar' : 'Copia el link de descarga'"></p>
        </div>
      </button>

      <!-- WhatsApp -->
      <button @click="shareWhatsapp()"
              class="w-full flex items-center gap-4 px-4 py-3.5 rounded-2xl border-2 border-gray-200
                     hover:border-green-400 hover:bg-green-50 text-gray-700 transition-all">
        <div class="w-10 h-10 rounded-xl bg-[#25D366] flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
          </svg>
        </div>
        <div class="text-left">
          <p class="font-bold text-sm">Compartir a WhatsApp</p>
          <p class="text-xs opacity-60 mt-0.5">Enviar por mensaje directo</p>
        </div>
      </button>

      <!-- Facebook -->
      <button @click="shareFacebook()"
              class="w-full flex items-center gap-4 px-4 py-3.5 rounded-2xl border-2 border-gray-200
                     hover:border-blue-500 hover:bg-blue-50 text-gray-700 transition-all">
        <div class="w-10 h-10 rounded-xl bg-[#1877F2] flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
          </svg>
        </div>
        <div class="text-left">
          <p class="font-bold text-sm">Compartir a Facebook</p>
          <p class="text-xs opacity-60 mt-0.5">Publicar en tu muro</p>
        </div>
      </button>

      <!-- Descargar -->
      <a :href="current.url" download
         class="w-full flex items-center gap-4 px-4 py-3.5 rounded-2xl
                bg-[#1E3A8A] hover:bg-blue-900 text-white transition-all shadow-lg shadow-blue-900/25">
        <div class="w-10 h-10 rounded-xl bg-white/15 flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
          </svg>
        </div>
        <div class="text-left">
          <p class="font-bold text-sm">Descargar archivo</p>
          <p class="text-xs text-white/60 mt-0.5 uppercase tracking-wider" x-text="current.formato.toUpperCase() + ' · Descarga directa'"></p>
        </div>
      </a>

    </div>
  </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
function shareModal() {
  return {
    showModal: false,
    copied: false,
    _copyTimer: null,
    current: { url: '', nombre: '', formato: '', thumb: null },

    openShare(url, nombre, formato, thumb) {
      this.current = { url, nombre, formato, thumb: thumb || null };
      this.copied  = false;
      this.showModal = true;
      document.body.style.overflow = 'hidden';
    },

    close() {
      this.showModal = false;
      document.body.style.overflow = '';
      clearTimeout(this._copyTimer);
    },

    copyLink() {
      const url = this.current.url;
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(url).then(() => this._flashCopied());
      } else {
        const el = document.createElement('input');
        el.value = url;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
        this._flashCopied();
      }
    },

    _flashCopied() {
      this.copied = true;
      clearTimeout(this._copyTimer);
      this._copyTimer = setTimeout(() => { this.copied = false; }, 2500);
    },

    shareWhatsapp() {
      const text = '¡Descarga este material de campaña: ' + this.current.nombre + '! ' + this.current.url;
      window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank', 'noopener,noreferrer');
    },

    shareFacebook() {
      window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(this.current.url), '_blank', 'noopener,noreferrer,width=600,height=500');
    },

    fmtBg(fmt) {
      const map = {
        cdr: 'background:linear-gradient(135deg,#007A5E,#00A383)',
        ai:  'background:linear-gradient(135deg,#FF7C00,#FF9A00)',
        psd: 'background:linear-gradient(135deg,#1473E6,#31A8FF)',
      };
      return map[fmt] || 'background:linear-gradient(135deg,#4F46E5,#7C3AED)';
    },

    fmtLabel(fmt) {
      return { cdr:'CDR', ai:'Ai', psd:'Ps', jpg:'JPG', png:'PNG' }[fmt] || fmt.toUpperCase();
    },

    fmtSub(fmt) {
      return { cdr:'CorelDRAW', ai:'Illustrator', psd:'Photoshop', jpg:'Imagen', png:'Imagen' }[fmt] || 'Archivo';
    },
  };
}
</script>
</body>
</html>
