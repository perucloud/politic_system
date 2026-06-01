<?php
// ============================================================
// noticias/index.php — Listado público de noticias
// ============================================================
require_once __DIR__ . '/../includes/config/db.php';

$por_pag = 9;
$pag     = max(1, (int)($_GET['pag'] ?? 1));
$offset  = ($pag - 1) * $por_pag;
$cat     = trim($_GET['cat'] ?? '');

// Categorías disponibles
$categorias = [];
try {
    $categorias = $pdo->query("SELECT nombre, color FROM categorias_noticias ORDER BY nombre ASC")->fetchAll();
} catch (Exception $e) {}

// Total y noticias
$where  = $cat ? "AND n.categoria = ?" : '';
$params = $cat ? [$cat] : [];

$total = 0;
$noticias = [];
try {
    $tq = $pdo->prepare("SELECT COUNT(*) FROM noticias n WHERE estado='publicado' $where");
    $tq->execute($params);
    $total = (int)$tq->fetchColumn();

    $sq = $pdo->prepare(
        "SELECT n.id, n.titulo, n.imagen, n.categoria, n.fecha, u.nombre AS autor_nombre
         FROM noticias n
         LEFT JOIN usuarios u ON u.id = n.autor_id
         WHERE n.estado='publicado' $where
         ORDER BY n.fecha DESC LIMIT ? OFFSET ?"
    );
    $sq->execute(array_merge($params, [$por_pag, $offset]));
    $noticias = $sq->fetchAll();
} catch (Exception $e) {
    try {
        $tq2 = $pdo->prepare("SELECT COUNT(*) FROM noticias WHERE estado='publicado' $where");
        $tq2->execute($params);
        $total = (int)$tq2->fetchColumn();
        $sq2 = $pdo->prepare("SELECT id,titulo,imagen,categoria,fecha FROM noticias WHERE estado='publicado' $where ORDER BY fecha DESC LIMIT ? OFFSET ?");
        $sq2->execute(array_merge($params, [$por_pag, $offset]));
        $noticias = $sq2->fetchAll();
    } catch (Exception $e2) {}
}

$pages = max(1, (int)ceil($total / $por_pag));
$meses = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Noticias — Ivan Cisneros · Alcalde Satipo</title>
  <meta name="description" content="Últimas noticias y novedades de la campaña de Ivan Cisneros, candidato a la Alcaldía Provincial de Satipo.">
  <?php
  $fv_n=''; try{$fv_n=$pdo->query("SELECT valor FROM configuracion WHERE clave='site_favicon' LIMIT 1")->fetchColumn()?:'';}catch(Exception $e){}
  $fv_n_url=(str_starts_with($fv_n,'/')?BASE_URL:'').($fv_n?:'/assets/img/logos/logorp.webp');
  ?>
  <link rel="icon" href="<?= htmlspecialchars($fv_n_url) ?>">
  <link rel="apple-touch-icon" href="<?= htmlspecialchars($fv_n_url) ?>">

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
  <style>
    body { font-family: 'Inter', sans-serif; }
    .noticia-card {
      background: white;
      border-radius: 1rem;
      overflow: hidden;
      border: 1px solid #F1F5F9;
      transition: transform 0.25s ease, box-shadow 0.25s ease;
    }
    .noticia-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 16px 40px rgba(0,57,166,0.12);
    }
  </style>
</head>
<body class="font-sans bg-[#F8FAFC] text-gray-800">

  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <!-- Hero -->
  <div class="bg-gradient-to-br from-[#001f6e] via-[#0039A6] to-[#0056D6] pt-28 pb-14">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 text-center">
      <span class="inline-block bg-white/10 text-white/80 text-xs font-bold uppercase tracking-widest px-4 py-1.5 rounded-full mb-4">
        Actualidad
      </span>
      <h1 class="text-4xl sm:text-5xl font-black text-white mb-3">Todas las Noticias</h1>
      <p class="text-blue-200 text-base max-w-md mx-auto">
        <?= $total ?> publicación<?= $total !== 1 ? 'es' : '' ?> — mantente informado sobre la campaña
      </p>
    </div>
  </div>

  <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12">

    <!-- Filtros por categoría -->
    <?php if (!empty($categorias)): ?>
    <div class="flex flex-wrap gap-2 mb-8">
      <a href="<?= BASE_URL ?>/noticias/index.php"
         class="text-xs font-semibold px-4 py-2 rounded-full transition-all
                <?= !$cat ? 'bg-[#0039A6] text-white shadow' : 'bg-white text-gray-600 border border-gray-200 hover:border-[#0039A6] hover:text-[#0039A6]' ?>">
        Todas
      </a>
      <?php foreach ($categorias as $c): ?>
      <a href="<?= BASE_URL ?>/noticias/index.php?cat=<?= urlencode($c['nombre']) ?>"
         class="text-xs font-semibold px-4 py-2 rounded-full transition-all
                <?= $cat === $c['nombre'] ? 'text-white shadow' : 'bg-white text-gray-600 border border-gray-200 hover:text-[#0039A6] hover:border-[#0039A6]' ?>"
         <?= $cat === $c['nombre'] ? 'style="background:' . htmlspecialchars($c['color']) . '"' : '' ?>>
        <?= htmlspecialchars($c['nombre']) ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Grid de noticias -->
    <?php if (empty($noticias)): ?>
    <div class="text-center py-20">
      <div class="text-5xl mb-4">📰</div>
      <p class="text-gray-500 font-semibold">No hay noticias publicadas<?= $cat ? ' en esta categoría' : '' ?>.</p>
      <?php if ($cat): ?>
      <a href="<?= BASE_URL ?>/noticias/index.php" class="text-[#0039A6] text-sm font-bold mt-2 inline-block hover:underline">
        Ver todas las categorías
      </a>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php foreach ($noticias as $n):
        $ts  = strtotime($n['fecha']);
        $img = !empty($n['imagen'])
               ? BASE_URL . '/assets/img/noticias/' . htmlspecialchars($n['imagen'])
               : 'https://placehold.co/400x200/EEF2FF/0039A6?text=Noticia';
      ?>
      <article class="noticia-card group flex flex-col">
        <!-- Imagen -->
        <a href="<?= BASE_URL ?>/noticias/ver.php?id=<?= $n['id'] ?>" class="block overflow-hidden" style="height:200px">
          <img src="<?= $img ?>"
               alt="<?= htmlspecialchars($n['titulo']) ?>"
               class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
               onerror="this.src='https://placehold.co/400x200/EEF2FF/0039A6?text=Noticia'">
        </a>

        <!-- Contenido -->
        <div class="p-5 flex flex-col flex-1">
          <!-- Categoría -->
          <span class="inline-block text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full mb-3 self-start"
                style="background:<?= htmlspecialchars(array_column($categorias,'color','nombre')[$n['categoria']] ?? '#EEF2FF') ?>22;
                       color:<?= htmlspecialchars(array_column($categorias,'color','nombre')[$n['categoria']] ?? '#0039A6') ?>">
            <?= htmlspecialchars($n['categoria'] ?: 'Noticias') ?>
          </span>

          <!-- Título -->
          <h2 class="font-bold text-gray-900 text-base leading-snug mb-3 line-clamp-3 flex-1">
            <a href="<?= BASE_URL ?>/noticias/ver.php?id=<?= $n['id'] ?>"
               class="hover:text-[#0039A6] transition-colors">
              <?= htmlspecialchars($n['titulo']) ?>
            </a>
          </h2>

          <!-- Footer card -->
          <div class="flex items-center justify-between mt-auto pt-3 border-t border-gray-100">
            <div>
              <p class="text-xs text-gray-400">
                <?= date('d', $ts) . ' ' . ($meses[(int)date('n', $ts)] ?? '') . ' ' . date('Y', $ts) ?>
              </p>
              <?php if (!empty($n['autor_nombre'])): ?>
              <p class="text-[11px] text-gray-400">por <?= htmlspecialchars($n['autor_nombre']) ?></p>
              <?php endif; ?>
            </div>
            <a href="<?= BASE_URL ?>/noticias/ver.php?id=<?= $n['id'] ?>"
               class="inline-flex items-center gap-1 bg-[#0039A6] hover:bg-[#0056D6] text-white
                      text-xs font-bold px-3 py-1.5 rounded-full transition-all">
              Leer
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
              </svg>
            </a>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>

    <!-- Paginación -->
    <?php if ($pages > 1): ?>
    <div class="flex items-center justify-center gap-2 mt-10 flex-wrap">
      <?php if ($pag > 1): ?>
      <a href="?pag=<?= $pag - 1 ?><?= $cat ? '&cat=' . urlencode($cat) : '' ?>"
         class="w-9 h-9 flex items-center justify-center rounded-full border border-gray-200 text-gray-500 hover:bg-[#0039A6] hover:text-white hover:border-[#0039A6] transition-all text-sm">
        ‹
      </a>
      <?php endif; ?>

      <?php for ($i = max(1, $pag - 2); $i <= min($pages, $pag + 2); $i++): ?>
      <a href="?pag=<?= $i ?><?= $cat ? '&cat=' . urlencode($cat) : '' ?>"
         class="w-9 h-9 flex items-center justify-center rounded-full text-sm font-semibold transition-all
                <?= $i === $pag ? 'bg-[#0039A6] text-white shadow-sm' : 'border border-gray-200 text-gray-500 hover:bg-[#0039A6] hover:text-white hover:border-[#0039A6]' ?>">
        <?= $i ?>
      </a>
      <?php endfor; ?>

      <?php if ($pag < $pages): ?>
      <a href="?pag=<?= $pag + 1 ?><?= $cat ? '&cat=' . urlencode($cat) : '' ?>"
         class="w-9 h-9 flex items-center justify-center rounded-full border border-gray-200 text-gray-500 hover:bg-[#0039A6] hover:text-white hover:border-[#0039A6] transition-all text-sm">
        ›
      </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

  </div>

  <?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
