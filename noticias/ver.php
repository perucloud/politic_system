<?php
// ============================================================
// noticias/ver.php — Detalle de noticia (con SEO + autor + share)
// ============================================================
require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/../includes/helpers/config.php';
$cfg_camp = [];
try { $cfg_camp = $pdo->query("SELECT clave, valor FROM configuracion")->fetchAll(PDO::FETCH_KEY_PAIR); } catch(Exception $e){}
require_once __DIR__ . '/../includes/maintenance_check.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$noticia = null;

if ($id > 0) {
    try {
        $stmt = $pdo->prepare(
            "SELECT n.*, u.nombre AS autor_nombre, u.rol AS autor_rol
             FROM noticias n
             LEFT JOIN usuarios u ON u.id = n.autor_id
             WHERE n.id = ? AND n.estado = 'publicado' LIMIT 1"
        );
        $stmt->execute([$id]);
        $noticia = $stmt->fetch();
    } catch (Exception $e) {
        // fallback sin columnas nuevas
        try {
            $stmt2 = $pdo->prepare("SELECT * FROM noticias WHERE id = ? AND estado = 'publicado' LIMIT 1");
            $stmt2->execute([$id]);
            $noticia = $stmt2->fetch();
        } catch (Exception $e2) {}
    }
}

if (!$noticia) {
    http_response_code(404);
    header('Location: ' . BASE_URL . '/index.php#noticias');
    exit;
}

// Noticias recientes para el sidebar
$recientes = [];
try {
    $stmt3 = $pdo->prepare(
        "SELECT id, titulo, imagen, fecha FROM noticias
         WHERE estado = 'publicado' AND id != ?
         ORDER BY fecha DESC LIMIT 4"
    );
    $stmt3->execute([$id]);
    $recientes = $stmt3->fetchAll();
} catch (Exception $e) {}

// SEO / meta
$seo_title = !empty($noticia['seo_title']) ? $noticia['seo_title'] : $noticia['titulo'];
$seo_desc  = !empty($noticia['seo_description'])
             ? $noticia['seo_description']
             : mb_substr(strip_tags($noticia['contenido']), 0, 160);
$seo_kw    = $noticia['seo_keywords'] ?? '';
$og_image  = !empty($noticia['imagen'])
             ? BASE_URL . '/assets/img/noticias/' . $noticia['imagen']
             : BASE_URL . '/assets/img/logos/logorp.webp';

$page_url   = BASE_URL . '/noticias/ver.php?id=' . $noticia['id'];
$share_text = urlencode($noticia['titulo'] . ' — ' . $page_url);
$share_url  = urlencode($page_url);

// Render del contenido (HTML desde Quill o texto plano legacy)
$contenido_html = $noticia['contenido'] ?? '';
if (!preg_match('/<[a-z][\s\S]*>/i', $contenido_html)) {
    $contenido_html = '<p>' . nl2br(htmlspecialchars($contenido_html)) . '</p>';
}

$meses = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
$ts_not = strtotime($noticia['fecha']);
$fecha_fmt = date('d', $ts_not) . ' ' . ($meses[(int)date('n', $ts_not)] ?? '') . '. ' . date('Y', $ts_not);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($seo_title) ?> — Ivan Cisneros</title>
  <meta name="description" content="<?= htmlspecialchars($seo_desc) ?>">
  <?php if ($seo_kw): ?><meta name="keywords" content="<?= htmlspecialchars($seo_kw) ?>"><?php endif; ?>
  <?php
  $fv_v=''; try{$fv_v=$pdo->query("SELECT valor FROM configuracion WHERE clave='site_favicon' LIMIT 1")->fetchColumn()?:'';}catch(Exception $e){}
  $fv_v_url=(str_starts_with($fv_v,'/')?BASE_URL:'').($fv_v?:'/assets/img/logos/logorp.webp');
  ?>
  <link rel="icon" href="<?= htmlspecialchars($fv_v_url) ?>">
  <link rel="apple-touch-icon" href="<?= htmlspecialchars($fv_v_url) ?>">

  <!-- Open Graph -->
  <meta property="og:type"        content="article">
  <meta property="og:title"       content="<?= htmlspecialchars($seo_title) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($seo_desc) ?>">
  <meta property="og:image"       content="<?= htmlspecialchars($og_image) ?>">
  <meta property="og:url"         content="<?= htmlspecialchars($page_url) ?>">
  <meta property="og:site_name"   content="Ivan Cisneros — Alcalde Satipo">
  <!-- Twitter Card -->
  <meta name="twitter:card"        content="summary_large_image">
  <meta name="twitter:title"       content="<?= htmlspecialchars($seo_title) ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars($seo_desc) ?>">
  <meta name="twitter:image"       content="<?= htmlspecialchars($og_image) ?>">

  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: { extend: {
        colors: { primary: '#0039A6', secondary: '#38BDF8', accent: '#FACC15' },
        fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }
      }}
    }
  </script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/custom.css">
  <!-- Quill CSS para renderizar contenido rico correctamente -->
  <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">

  <style>
    /* Contenido rico de la noticia */
    .noticia-contenido { line-height: 1.8; color: #374151; }
    .noticia-contenido .ql-editor { padding: 0; border: none !important; }
    .noticia-contenido .ql-snow.ql-container { border: none !important; }
    .noticia-contenido h1 { font-size: 1.75rem; font-weight: 800; color: #111827; margin: 1.5rem 0 0.75rem; }
    .noticia-contenido h2 { font-size: 1.4rem;  font-weight: 700; color: #111827; margin: 1.25rem 0 0.5rem; }
    .noticia-contenido h3 { font-size: 1.15rem; font-weight: 600; color: #111827; margin: 1rem 0 0.4rem; }
    .noticia-contenido p  { margin-bottom: 1rem; }
    .noticia-contenido ul, .noticia-contenido ol { padding-left: 1.5rem; margin-bottom: 1rem; }
    .noticia-contenido li { margin-bottom: 0.3rem; }
    .noticia-contenido ul { list-style: disc; }
    .noticia-contenido ol { list-style: decimal; }
    .noticia-contenido blockquote {
      border-left: 4px solid #DBEAFE; background: #F0F7FF;
      padding: 0.75rem 1rem; border-radius: 0 0.5rem 0.5rem 0;
      color: #4B5563; font-style: italic; margin: 1rem 0;
    }
    .noticia-contenido a { color: #0039A6; text-decoration: underline; }
    .noticia-contenido img { max-width: 100%; border-radius: 0.75rem; margin: 1.25rem 0; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    .noticia-contenido iframe {
      max-width: 100%; width: 100%; aspect-ratio: 16/9;
      border-radius: 0.75rem; margin: 1.25rem 0; border: none;
      box-shadow: 0 4px 12px rgba(0,0,0,0.10);
    }
    .noticia-contenido strong { font-weight: 700; }
    .noticia-contenido em { font-style: italic; }
    .share-btn { transition: all 0.15s ease; }
    .share-btn:hover { transform: translateY(-1px); }
  </style>
</head>
<body class="font-sans text-gray-800 bg-white">

  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <!-- Breadcrumb -->
  <div class="bg-[#F8FAFC] border-b border-gray-100 py-3">
    <div class="max-w-5xl mx-auto px-4 sm:px-6">
      <nav class="flex items-center gap-2 text-sm text-gray-500">
        <a href="<?= BASE_URL ?>/index.php" class="hover:text-[#0039A6] transition-colors">Inicio</a>
        <span class="text-gray-300">›</span>
        <a href="<?= BASE_URL ?>/index.php#noticias" class="hover:text-[#0039A6] transition-colors">Noticias</a>
        <span class="text-gray-300">›</span>
        <span class="text-gray-700 font-medium truncate max-w-[180px] sm:max-w-xs">
          <?= htmlspecialchars($noticia['titulo']) ?>
        </span>
      </nav>
    </div>
  </div>

  <!-- Contenido principal -->
  <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">

      <!-- Artículo -->
      <article class="lg:col-span-2">

        <!-- Categoría + fecha -->
        <div class="flex items-center gap-3 mb-4 flex-wrap">
          <span class="bg-[#0039A6]/10 text-[#0039A6] text-xs font-bold uppercase tracking-widest px-3 py-1 rounded-full">
            <?= htmlspecialchars($noticia['categoria'] ?: 'Noticias') ?>
          </span>
          <span class="text-gray-400 text-sm"><?= $fecha_fmt ?></span>
        </div>

        <!-- Título -->
        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-[#0039A6] leading-tight mb-5">
          <?= htmlspecialchars($noticia['titulo']) ?>
        </h1>

        <!-- Autor -->
        <?php $autor = $noticia['autor_nombre'] ?? null; ?>
        <div class="flex items-center gap-3 mb-6 pb-5 border-b border-gray-100">
          <div class="w-9 h-9 rounded-full bg-gradient-to-br from-[#38BDF8] to-[#0039A6] flex items-center justify-center flex-shrink-0">
            <span class="text-white text-sm font-black">
              <?= strtoupper(substr($autor ?: 'J', 0, 1)) ?>
            </span>
          </div>
          <div>
            <p class="font-semibold text-gray-800 text-sm leading-tight">
              <?= htmlspecialchars($autor ?: 'Equipo Ivan Cisneros') ?>
            </p>
            <p class="text-xs text-gray-400">Publicado el <?= $fecha_fmt ?></p>
          </div>
        </div>

        <!-- Imagen destacada -->
        <?php if (!empty($noticia['imagen'])): ?>
        <div class="w-full aspect-video rounded-2xl overflow-hidden mb-8 bg-gray-100 shadow-md">
          <img src="<?= BASE_URL ?>/assets/img/noticias/<?= htmlspecialchars($noticia['imagen']) ?>"
               alt="<?= htmlspecialchars($noticia['titulo']) ?>"
               class="w-full h-full object-cover">
        </div>
        <?php endif; ?>

        <!-- Contenido rico -->
        <div class="noticia-contenido ql-snow">
          <div class="ql-editor">
            <?= $contenido_html ?>
          </div>
        </div>

        <!-- Compartir -->
        <div class="mt-10 pt-6 border-t border-gray-100" x-data="{ copied: false }">
          <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Compartir esta nota</p>
          <div class="flex flex-wrap gap-2">

            <!-- WhatsApp -->
            <a href="https://wa.me/?text=<?= $share_text ?>"
               target="_blank" rel="noopener" title="Compartir en WhatsApp"
               class="share-btn inline-flex items-center gap-2 bg-[#25D366] hover:bg-green-500 text-white text-xs font-bold px-4 py-2.5 rounded-full shadow-sm">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347zM12 0C5.373 0 0 5.373 0 12c0 2.123.554 4.118 1.528 5.848L0 24l6.335-1.652A11.954 11.954 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.818 9.818 0 01-5.017-1.38l-.36-.214-3.732.977.995-3.636-.235-.374A9.818 9.818 0 1112 21.818z"/>
              </svg>
              WhatsApp
            </a>

            <!-- Facebook -->
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $share_url ?>"
               target="_blank" rel="noopener" title="Compartir en Facebook"
               class="share-btn inline-flex items-center gap-2 bg-[#1877F2] hover:bg-blue-700 text-white text-xs font-bold px-4 py-2.5 rounded-full shadow-sm">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.235 2.686.235v2.97h-1.514c-1.491 0-1.956.93-1.956 1.886v2.271h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/>
              </svg>
              Facebook
            </a>

            <!-- X / Twitter -->
            <a href="https://twitter.com/intent/tweet?text=<?= urlencode($noticia['titulo']) ?>&url=<?= $share_url ?>"
               target="_blank" rel="noopener" title="Compartir en X (Twitter)"
               class="share-btn inline-flex items-center gap-2 bg-black hover:bg-gray-900 text-white text-xs font-bold px-4 py-2.5 rounded-full shadow-sm">
              <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.748l7.73-8.835L1.254 2.25H8.08l4.253 5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
              </svg>
              X (Twitter)
            </a>

            <!-- Copiar enlace (Instagram / otros) -->
            <button @click="navigator.clipboard.writeText('<?= $page_url ?>').then(()=>{ copied=true; setTimeout(()=>copied=false,2500) })"
                    title="Copiar enlace (para Instagram u otros)"
                    class="share-btn inline-flex items-center gap-2 bg-gradient-to-r from-purple-500 to-pink-500 hover:from-purple-600 hover:to-pink-600 text-white text-xs font-bold px-4 py-2.5 rounded-full shadow-sm">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>
              </svg>
              <span x-text="copied ? '¡Enlace copiado!' : 'Copiar para Instagram'"></span>
            </button>

          </div>

          <!-- Volver -->
          <div class="mt-6">
            <a href="<?= BASE_URL ?>/index.php#noticias"
               class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-[#0039A6] font-semibold transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
              </svg>
              Volver a Noticias
            </a>
          </div>
        </div>

      </article>

      <!-- Sidebar: noticias recientes -->
      <aside class="lg:col-span-1">
        <div class="sticky top-24">
          <h3 class="text-sm font-black text-gray-500 uppercase tracking-widest mb-4">Más noticias</h3>
          <?php if (empty($recientes)): ?>
            <p class="text-gray-400 text-sm">No hay más noticias disponibles.</p>
          <?php else: ?>
          <div class="space-y-4">
            <?php foreach ($recientes as $r):
              $ts_r = strtotime($r['fecha']);
            ?>
            <a href="<?= BASE_URL ?>/noticias/ver.php?id=<?= $r['id'] ?>"
               class="flex items-start gap-3 group hover:bg-gray-50 rounded-xl p-2 -m-2 transition-colors">
              <div class="w-16 h-16 rounded-xl overflow-hidden bg-gray-100 flex-shrink-0">
                <?php if (!empty($r['imagen'])): ?>
                <img src="<?= BASE_URL ?>/assets/img/noticias/<?= htmlspecialchars($r['imagen']) ?>"
                     alt="<?= htmlspecialchars($r['titulo']) ?>"
                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                     onerror="this.style.display='none'">
                <?php else: ?>
                <div class="w-full h-full bg-[#0039A6]/10 flex items-center justify-center text-2xl">📰</div>
                <?php endif; ?>
              </div>
              <div class="min-w-0">
                <p class="text-xs text-gray-400 mb-1">
                  <?= date('d', $ts_r) . ' ' . ($meses[(int)date('n', $ts_r)] ?? '') . '. ' . date('Y', $ts_r) ?>
                </p>
                <p class="text-sm font-semibold text-gray-800 group-hover:text-[#0039A6] transition-colors leading-snug line-clamp-2">
                  <?= htmlspecialchars($r['titulo']) ?>
                </p>
              </div>
            </a>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <!-- CTA campaña -->
          <div class="mt-8 bg-gradient-to-br from-[#0039A6] to-[#0056D6] rounded-2xl p-5 text-white text-center">
            <p class="font-black text-base mb-1">Únete al cambio</p>
            <p class="text-blue-200 text-xs mb-4">Sé parte del equipo de Ivan Cisneros</p>
            <a href="<?= BASE_URL ?>/index.php#unete"
               class="inline-block bg-[#FACC15] hover:bg-yellow-300 text-[#0039A6] font-black text-xs px-5 py-2.5 rounded-full transition-all">
              Registrarme
            </a>
          </div>
        </div>
      </aside>

    </div>
  </div>

  <?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
