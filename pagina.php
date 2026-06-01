<?php
// ============================================================
// pagina.php — Template público para páginas personalizadas
// URL: /pagina.php?slug=mi-pagina
// ============================================================
require_once __DIR__ . '/includes/config/db.php';
require_once __DIR__ . '/includes/helpers/config.php';
$cfg_camp = [];
try { $cfg_camp = $pdo->query("SELECT clave, valor FROM configuracion")->fetchAll(PDO::FETCH_KEY_PAIR); } catch(Exception $e){}
require_once __DIR__ . '/includes/maintenance_check.php';

$slug   = trim($_GET['slug'] ?? '');
$pagina = null;

if ($slug) {
    try {
        $stmt = $pdo->prepare(
            "SELECT p.*, u.nombre AS autor_nombre
             FROM paginas p
             LEFT JOIN usuarios u ON u.id = p.autor_id
             WHERE p.slug = ? AND p.estado = 'publicado' LIMIT 1"
        );
        $stmt->execute([$slug]);
        $pagina = $stmt->fetch();
    } catch (Exception $e) {
        try {
            $stmt2 = $pdo->prepare("SELECT * FROM paginas WHERE slug = ? AND estado = 'publicado' LIMIT 1");
            $stmt2->execute([$slug]);
            $pagina = $stmt2->fetch();
        } catch (Exception $e2) {}
    }
}

if (!$pagina) {
    http_response_code(404);
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// SEO
$seo_title = !empty($pagina['seo_title']) ? $pagina['seo_title'] : $pagina['titulo'];
$seo_desc  = !empty($pagina['seo_description'])
             ? $pagina['seo_description']
             : mb_substr(strip_tags($pagina['contenido']), 0, 160);

// Render del contenido (HTML desde Quill o texto plano legacy)
$contenido_html = $pagina['contenido'] ?? '';
if (!preg_match('/<[a-z][\s\S]*>/i', $contenido_html)) {
    $contenido_html = '<p>' . nl2br(htmlspecialchars($contenido_html)) . '</p>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($seo_title) ?> — Ivan Cisneros</title>
  <meta name="description" content="<?= htmlspecialchars($seo_desc) ?>">
  <?php if (!empty($pagina['seo_keywords'])): ?>
  <meta name="keywords" content="<?= htmlspecialchars($pagina['seo_keywords']) ?>">
  <?php endif; ?>
  <meta property="og:title"       content="<?= htmlspecialchars($seo_title) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($seo_desc) ?>">
  <meta property="og:type"        content="website">
  <?php
  $fv_pag = '';
  try { $fv_pag = $pdo->query("SELECT valor FROM configuracion WHERE clave='site_favicon' LIMIT 1")->fetchColumn() ?: ''; } catch(Exception $e){}
  $fv_pag_url = (str_starts_with($fv_pag,'/') ? BASE_URL : '') . ($fv_pag ?: '/assets/img/logos/logorp.webp');
  ?>
  <link rel="icon" href="<?= htmlspecialchars($fv_pag_url) ?>">
  <link rel="apple-touch-icon" href="<?= htmlspecialchars($fv_pag_url) ?>">

  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: { extend: {
        colors: { primary: '#0039A6', secondary: '#38BDF8', accent: '#FACC15' },
        fontFamily: { sans: ['Inter','system-ui','sans-serif'] }
      }}
    }
  </script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/custom.css">
  <style>
    body { font-family: 'Inter', sans-serif; }
    [x-cloak] { display: none !important; }
    .pagina-contenido { line-height: 1.8; color: #374151; }
    .pagina-contenido .ql-editor { padding: 0; border: none !important; }
    .pagina-contenido .ql-snow.ql-container { border: none !important; }
    .pagina-contenido h1 { font-size: 1.75rem; font-weight: 800; color: #111827; margin: 1.5rem 0 .75rem; }
    .pagina-contenido h2 { font-size: 1.4rem;  font-weight: 700; color: #111827; margin: 1.25rem 0 .5rem; }
    .pagina-contenido h3 { font-size: 1.15rem; font-weight: 600; color: #111827; margin: 1rem 0 .4rem; }
    .pagina-contenido p  { margin-bottom: 1rem; }
    .pagina-contenido ul, .pagina-contenido ol { padding-left: 1.5rem; margin-bottom: 1rem; }
    .pagina-contenido li { margin-bottom: .3rem; }
    .pagina-contenido ul { list-style: disc; }
    .pagina-contenido ol { list-style: decimal; }
    .pagina-contenido blockquote { border-left: 4px solid #DBEAFE; background: #F0F7FF; padding: .75rem 1rem; border-radius: 0 .5rem .5rem 0; color: #4B5563; font-style: italic; margin: 1rem 0; }
    .pagina-contenido a   { color: #0039A6; text-decoration: underline; }
    .pagina-contenido img { max-width: 100%; border-radius: .75rem; margin: 1.25rem 0; box-shadow: 0 4px 12px rgba(0,0,0,.08); }
    .pagina-contenido iframe { max-width: 100%; width: 100%; aspect-ratio: 16/9; border-radius: .75rem; margin: 1.25rem 0; border: none; }
    .pagina-contenido strong { font-weight: 700; }
    .pagina-contenido em { font-style: italic; }
  </style>
</head>
<body class="font-sans text-gray-800 bg-white">

  <?php include __DIR__ . '/includes/navbar.php'; ?>

  <!-- Breadcrumb -->
  <div class="bg-[#F8FAFC] border-b border-gray-100 py-3">
    <div class="max-w-4xl mx-auto px-4 sm:px-6">
      <nav class="flex items-center gap-2 text-sm text-gray-500">
        <a href="<?= BASE_URL ?>/index.php" class="hover:text-[#0039A6] transition-colors">Inicio</a>
        <span class="text-gray-300">›</span>
        <span class="text-gray-700 font-medium truncate"><?= htmlspecialchars($pagina['titulo']) ?></span>
      </nav>
    </div>
  </div>

  <!-- Contenido -->
  <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">

    <!-- Título -->
    <h1 class="text-3xl sm:text-4xl font-black text-[#0039A6] leading-tight mb-4">
      <?= htmlspecialchars($pagina['titulo']) ?>
    </h1>

    <!-- Meta: autor + fecha -->
    <?php if (!empty($pagina['autor_nombre']) || !empty($pagina['creado_en'])): ?>
    <div class="flex items-center gap-3 mb-8 pb-6 border-b border-gray-100">
      <?php if (!empty($pagina['autor_nombre'])): ?>
      <div class="w-8 h-8 rounded-full bg-gradient-to-br from-[#38BDF8] to-[#0039A6] flex items-center justify-center flex-shrink-0">
        <span class="text-white text-xs font-black"><?= strtoupper(substr($pagina['autor_nombre'], 0, 1)) ?></span>
      </div>
      <div>
        <p class="text-sm font-semibold text-gray-700"><?= htmlspecialchars($pagina['autor_nombre']) ?></p>
        <?php if (!empty($pagina['creado_en'])): ?>
        <p class="text-xs text-gray-400"><?= date('d/m/Y', strtotime($pagina['creado_en'])) ?></p>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Cuerpo de la página -->
    <div class="pagina-contenido ql-snow">
      <div class="ql-editor">
        <?= $contenido_html ?>
      </div>
    </div>

    <!-- Volver -->
    <div class="mt-12 pt-6 border-t border-gray-100">
      <a href="<?= BASE_URL ?>/index.php"
         class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-[#0039A6] font-semibold transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Volver al inicio
      </a>
    </div>
  </div>

  <?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
