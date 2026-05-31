<?php
// ============================================================
// distritales/noticia.php — Noticia distrital individual
// URL: /distritales/noticia.php?id=123
// ============================================================
require_once __DIR__ . '/../includes/config/db.php';

$id      = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$noticia = null;
$candidato = null;

if ($id > 0) {
    try {
        $stmt = $pdo->prepare(
            "SELECT cn.*, cd.nombre AS candidato_nombre, cd.slug AS candidato_slug,
                    cd.distrito AS candidato_distrito, cd.foto AS candidato_foto
             FROM candidato_noticias cn
             JOIN candidatos_distritales cd ON cd.id = cn.candidato_id
             WHERE cn.id = ? AND cn.estado = 'publicado' AND cd.activo = 1
             LIMIT 1"
        );
        $stmt->execute([$id]);
        $noticia = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $noticia = null;
    }
}

if (!$noticia) {
    http_response_code(404);
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// Datos del distrito
$slug       = (string)$noticia['candidato_slug'];
$distrito   = (string)$noticia['candidato_distrito'];
$candidato_nombre = (string)$noticia['candidato_nombre'];
$distrito_url = BASE_URL . '/distritales/' . $slug . '.php';

// Noticias recientes del mismo distrito (sidebar)
$recientes = [];
try {
    $sr = $pdo->prepare(
        "SELECT id, titulo, imagen, creado_en
         FROM candidato_noticias
         WHERE candidato_id = ? AND estado = 'publicado' AND id != ?
         ORDER BY creado_en DESC LIMIT 4"
    );
    $sr->execute([(int)$noticia['candidato_id'], $id]);
    $recientes = $sr->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// SEO / meta
$page_title  = (string)$noticia['titulo'];
$seo_desc    = mb_substr(strip_tags((string)$noticia['contenido']), 0, 160);
$page_url    = BASE_URL . '/distritales/noticia.php?id=' . $id;
$share_text  = urlencode($page_title . ' — ' . $page_url);
$share_url   = urlencode($page_url);
$og_image    = !empty($noticia['imagen'])
               ? BASE_URL . '/' . ltrim((string)$noticia['imagen'], '/')
               : BASE_URL . '/assets/img/logos/logorp.webp';

// Fecha formateada
$meses     = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
$ts        = strtotime((string)$noticia['creado_en']);
$fecha_fmt = date('d', $ts) . ' ' . ($meses[(int)date('n', $ts)] ?? '') . '. ' . date('Y', $ts);

// Renderizar contenido (HTML Quill o texto plano legacy)
$contenido_html = (string)$noticia['contenido'];
if (!preg_match('/<[a-z][\s\S]*>/i', $contenido_html)) {
    $contenido_html = '<p>' . nl2br(htmlspecialchars($contenido_html)) . '</p>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title) ?> — <?= htmlspecialchars($distrito) ?> · Ivan Cisneros</title>
  <meta name="description" content="<?= htmlspecialchars($seo_desc) ?>">

  <!-- Open Graph (compartir en redes) -->
  <meta property="og:type"        content="article">
  <meta property="og:title"       content="<?= htmlspecialchars($page_title) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($seo_desc) ?>">
  <meta property="og:image"       content="<?= htmlspecialchars($og_image) ?>">
  <meta property="og:url"         content="<?= htmlspecialchars($page_url) ?>">
  <meta property="og:site_name"   content="Ivan Cisneros — Candidato Satipo">
  <!-- Twitter Card -->
  <meta name="twitter:card"        content="summary_large_image">
  <meta name="twitter:title"       content="<?= htmlspecialchars($page_title) ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars($seo_desc) ?>">
  <meta name="twitter:image"       content="<?= htmlspecialchars($og_image) ?>">

  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: { extend: {
        colors: { primary: '#1E3A8A', secondary: '#38BDF8', accent: '#FACC15' },
        fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }
      }}
    }
  </script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/custom.css">
  <!-- Quill CSS para renderizar contenido rico -->
  <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">

  <style>
    .noticia-contenido { line-height: 1.85; color: #374151; }
    .noticia-contenido .ql-editor { padding: 0; border: none !important; }
    .noticia-contenido .ql-snow.ql-container { border: none !important; }
    .noticia-contenido h2 { font-size: 1.4rem;  font-weight: 700; color: #111827; margin: 1.5rem 0 0.5rem; }
    .noticia-contenido h3 { font-size: 1.15rem; font-weight: 600; color: #111827; margin: 1.2rem 0 0.4rem; }
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
    .noticia-contenido a     { color: #1E3A8A; text-decoration: underline; }
    .noticia-contenido strong { font-weight: 700; }
    .noticia-contenido em    { font-style: italic; }
    .noticia-contenido img   { max-width: 100%; border-radius: 0.75rem; margin: 1.25rem 0; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
    .noticia-contenido iframe {
      max-width: 100%; width: 100%; aspect-ratio: 16/9;
      border-radius: 0.75rem; margin: 1.25rem 0; border: none;
    }
    .share-btn { transition: all 0.15s ease; }
    .share-btn:hover { transform: translateY(-1px); }
  </style>
</head>
<body class="font-sans text-gray-800 bg-white">

  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <!-- Breadcrumb -->
  <div class="bg-[#F8FAFC] border-b border-gray-100 py-3">
    <div class="max-w-5xl mx-auto px-4 sm:px-6">
      <nav class="flex items-center gap-2 text-sm text-gray-500 flex-wrap">
        <a href="<?= BASE_URL ?>/index.php" class="hover:text-[#1E3A8A] transition-colors">Inicio</a>
        <span class="text-gray-300">›</span>
        <a href="<?= BASE_URL ?>/distritales/<?= htmlspecialchars($slug) ?>.php"
           class="hover:text-[#1E3A8A] transition-colors">
          Candidatos Distritales
        </a>
        <span class="text-gray-300">›</span>
        <a href="<?= htmlspecialchars($distrito_url) ?>#noticias-distrito"
           class="hover:text-[#1E3A8A] transition-colors">
          <?= htmlspecialchars($distrito) ?>
        </a>
        <span class="text-gray-300">›</span>
        <span class="text-gray-700 font-medium truncate max-w-[200px] sm:max-w-sm">
          <?= htmlspecialchars($page_title) ?>
        </span>
      </nav>
    </div>
  </div>

  <!-- Contenido principal -->
  <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">

      <!-- ── Artículo principal ─────────────────────────────── -->
      <article class="lg:col-span-2">

        <!-- Distrito badge + fecha -->
        <div class="flex items-center gap-3 mb-4 flex-wrap">
          <a href="<?= htmlspecialchars($distrito_url) ?>"
             class="bg-[#1E3A8A]/10 text-[#1E3A8A] text-xs font-bold uppercase tracking-widest px-3 py-1 rounded-full hover:bg-[#1E3A8A]/20 transition-colors">
            <?= htmlspecialchars($distrito) ?>
          </a>
          <span class="text-gray-400 text-sm"><?= $fecha_fmt ?></span>
        </div>

        <!-- Título -->
        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-[#1E3A8A] leading-tight mb-5">
          <?= htmlspecialchars($page_title) ?>
        </h1>

        <!-- Candidato (autor) -->
        <div class="flex items-center gap-3 mb-7 pb-6 border-b border-gray-100">
          <div class="w-10 h-10 rounded-full overflow-hidden border-2 border-[#FACC15] flex-shrink-0 bg-[#1E3A8A]">
            <?php if (!empty($noticia['candidato_foto'])): ?>
            <img src="<?= htmlspecialchars(BASE_URL . '/' . ltrim((string)$noticia['candidato_foto'], '/')) ?>"
                 alt="<?= htmlspecialchars($candidato_nombre) ?>"
                 class="w-full h-full object-cover object-top">
            <?php else: ?>
            <div class="w-full h-full flex items-center justify-center">
              <span class="text-white text-sm font-black"><?= strtoupper(substr($candidato_nombre, 0, 1)) ?></span>
            </div>
            <?php endif; ?>
          </div>
          <div>
            <p class="font-bold text-gray-800 text-sm leading-tight"><?= htmlspecialchars($candidato_nombre) ?></p>
            <p class="text-xs text-gray-400">Candidato — <?= htmlspecialchars($distrito) ?> · <?= $fecha_fmt ?></p>
          </div>
        </div>

        <!-- Imagen destacada -->
        <?php if (!empty($noticia['imagen'])): ?>
        <div class="w-full aspect-video rounded-2xl overflow-hidden mb-8 bg-gray-100 shadow-md">
          <img src="<?= htmlspecialchars(BASE_URL . '/' . ltrim((string)$noticia['imagen'], '/')) ?>"
               alt="<?= htmlspecialchars($page_title) ?>"
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
          <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Compartir esta noticia</p>
          <div class="flex flex-wrap gap-2">

            <!-- WhatsApp -->
            <a href="https://wa.me/?text=<?= $share_text ?>"
               target="_blank" rel="noopener"
               class="share-btn inline-flex items-center gap-2 bg-[#25D366] hover:bg-green-500 text-white text-xs font-bold px-4 py-2.5 rounded-full shadow-sm">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347zM12 0C5.373 0 0 5.373 0 12c0 2.123.554 4.118 1.528 5.848L0 24l6.335-1.652A11.954 11.954 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0z"/>
              </svg>
              WhatsApp
            </a>

            <!-- Facebook -->
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $share_url ?>"
               target="_blank" rel="noopener"
               class="share-btn inline-flex items-center gap-2 bg-[#1877F2] hover:bg-blue-700 text-white text-xs font-bold px-4 py-2.5 rounded-full shadow-sm">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.41c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.235 2.686.235v2.97h-1.514c-1.491 0-1.956.93-1.956 1.886v2.271h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/>
              </svg>
              Facebook
            </a>

            <!-- X / Twitter -->
            <a href="https://twitter.com/intent/tweet?text=<?= urlencode($page_title) ?>&url=<?= $share_url ?>"
               target="_blank" rel="noopener"
               class="share-btn inline-flex items-center gap-2 bg-black hover:bg-gray-900 text-white text-xs font-bold px-4 py-2.5 rounded-full shadow-sm">
              <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.748l7.73-8.835L1.254 2.25H8.08l4.253 5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
              </svg>
              X (Twitter)
            </a>

            <!-- Copiar enlace -->
            <button @click="navigator.clipboard.writeText('<?= $page_url ?>').then(()=>{ copied=true; setTimeout(()=>copied=false,2500) })"
                    class="share-btn inline-flex items-center gap-2 bg-gray-700 hover:bg-gray-900 text-white text-xs font-bold px-4 py-2.5 rounded-full shadow-sm">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
              </svg>
              <span x-text="copied ? '¡Copiado!' : 'Copiar enlace'"></span>
            </button>
          </div>
        </div>

        <!-- Volver al distrito -->
        <div class="mt-8">
          <a href="<?= htmlspecialchars($distrito_url) ?>#noticias-distrito"
             class="inline-flex items-center gap-2 text-sm font-bold text-[#1E3A8A] hover:text-blue-900 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Ver todas las noticias de <?= htmlspecialchars($distrito) ?>
          </a>
        </div>

      </article>

      <!-- ── Sidebar: otras noticias del distrito ─────────── -->
      <aside class="lg:col-span-1">

        <!-- Candidato card -->
        <a href="<?= htmlspecialchars($distrito_url) ?>"
           class="block rounded-2xl border border-gray-100 overflow-hidden shadow-sm hover:shadow-md transition-all mb-6 group">
          <div class="bg-[#1E3A8A] px-4 py-3">
            <p class="text-white font-black text-xs uppercase tracking-widest">Candidato</p>
          </div>
          <div class="p-4 flex items-center gap-3">
            <?php if (!empty($noticia['candidato_foto'])): ?>
            <img src="<?= htmlspecialchars(BASE_URL . '/' . ltrim((string)$noticia['candidato_foto'], '/')) ?>"
                 alt="<?= htmlspecialchars($candidato_nombre) ?>"
                 class="w-14 h-14 rounded-full object-cover object-top border-2 border-[#FACC15] flex-shrink-0">
            <?php endif; ?>
            <div>
              <p class="font-black text-gray-900 text-sm leading-tight group-hover:text-[#1E3A8A] transition-colors">
                <?= htmlspecialchars($candidato_nombre) ?>
              </p>
              <p class="text-xs text-gray-400 mt-0.5">Candidato Alcalde — <?= htmlspecialchars($distrito) ?></p>
            </div>
          </div>
        </a>

        <!-- Otras noticias del distrito -->
        <?php if ($recientes): ?>
        <div class="rounded-2xl border border-gray-100 overflow-hidden shadow-sm">
          <div class="bg-gray-50 px-4 py-3 border-b border-gray-100">
            <p class="font-black text-gray-700 text-xs uppercase tracking-widest">Más noticias del distrito</p>
          </div>
          <div class="divide-y divide-gray-100">
            <?php foreach ($recientes as $r):
              $r_img  = !empty($r['imagen']) ? BASE_URL . '/' . ltrim((string)$r['imagen'], '/') : '';
              $r_ts   = strtotime((string)$r['creado_en']);
              $r_date = date('d', $r_ts) . ' ' . ($meses[(int)date('n', $r_ts)] ?? '') . '. ' . date('Y', $r_ts);
            ?>
            <a href="<?= BASE_URL ?>/distritales/noticia.php?id=<?= (int)$r['id'] ?>"
               class="flex gap-3 p-3 hover:bg-gray-50 transition-colors group">
              <?php if ($r_img): ?>
              <img src="<?= htmlspecialchars($r_img) ?>" alt=""
                   class="w-16 h-16 rounded-xl object-cover flex-shrink-0">
              <?php else: ?>
              <div class="w-16 h-16 rounded-xl bg-[#EFF6FF] flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6 text-[#1E3A8A]/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01"/>
                </svg>
              </div>
              <?php endif; ?>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-gray-800 group-hover:text-[#1E3A8A] transition-colors line-clamp-2 leading-snug">
                  <?= htmlspecialchars((string)$r['titulo']) ?>
                </p>
                <p class="text-xs text-gray-400 mt-1"><?= $r_date ?></p>
              </div>
            </a>
            <?php endforeach; ?>
          </div>
          <div class="p-3 border-t border-gray-100 bg-gray-50">
            <a href="<?= htmlspecialchars($distrito_url) ?>#noticias-distrito"
               class="block text-center text-xs font-bold text-[#1E3A8A] hover:text-blue-900 transition-colors">
              Ver todas →
            </a>
          </div>
        </div>
        <?php endif; ?>

      </aside>

    </div>
  </div>

  <?php include __DIR__ . '/../includes/footer.php'; ?>

  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
  <script>AOS.init({ once: true, duration: 500 });</script>
</body>
</html>
